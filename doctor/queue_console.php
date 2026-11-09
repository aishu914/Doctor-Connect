<?php
/**
 * Doctor Consultation Cockpit & Live Queue Console
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';
require_role('doctor');

$user = current_user();
$pdo = Database::getConnection();

// Fetch doctor details
$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ? LIMIT 1");
$docStmt->execute([$user['id']]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    set_flash('danger', 'Doctor profile not found.');
    redirect(BASE_URL . '/index.php');
}

$today = date('Y-m-d');
$selectedDate = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?: $today;

// 1. Handle Save Prescription POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_prescription') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $apptId = (int)$_POST['appointment_id'];
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $clinicalNotes = trim($_POST['clinical_notes'] ?? '');
        $medNames = $_POST['med_name'] ?? [];
        $medFreqs = $_POST['med_freq'] ?? [];
        $medDurs  = $_POST['med_duration'] ?? [];
        $medNotes = $_POST['med_notes'] ?? [];

        $medicines = [];
        for ($i = 0; $i < count($medNames); $i++) {
            if (!empty(trim($medNames[$i]))) {
                $medicines[] = [
                    'name' => trim($medNames[$i]),
                    'frequency' => trim($medFreqs[$i] ?? ''),
                    'duration' => trim($medDurs[$i] ?? ''),
                    'instructions' => trim($medNotes[$i] ?? '')
                ];
            }
        }

        // Fetch appointment to get patient_id
        $apptQuery = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $apptQuery->execute([$apptId]);
        $patientId = $apptQuery->fetchColumn();

        if ($patientId && !empty($diagnosis)) {
            // Check if prescription already exists
            $checkPresc = $pdo->prepare("SELECT id FROM prescriptions WHERE appointment_id = ?");
            $checkPresc->execute([$apptId]);
            $existingPrescId = $checkPresc->fetchColumn();

            if ($existingPrescId) {
                $updPresc = $pdo->prepare("
                    UPDATE prescriptions 
                    SET diagnosis = ?, medicines = ?, clinical_notes = ? 
                    WHERE id = ?
                ");
                $updPresc->execute([$diagnosis, json_encode($medicines), $clinicalNotes, $existingPrescId]);
            } else {
                $insPresc = $pdo->prepare("
                    INSERT INTO prescriptions (appointment_id, doctor_id, patient_id, diagnosis, medicines, clinical_notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insPresc->execute([$apptId, $doctor['id'], $patientId, $diagnosis, json_encode($medicines), $clinicalNotes]);
            }

            // Also mark appointment completed if requested
            if (!empty($_POST['mark_completed'])) {
                TokenManager::updateAppointmentStatus($apptId, 'completed', $user['id']);
            }

            set_flash('success', 'Prescription saved successfully!');
            redirect(BASE_URL . '/doctor/queue_console.php?date=' . urlencode($selectedDate));
        } else {
            set_flash('danger', 'Please provide a diagnosis before saving prescription.');
        }
    }
}

// 2. Fetch live queue snapshot
$queue = TokenManager::getLiveQueue($doctor['id'], $selectedDate);
$serving = $queue['currently_serving'];

// If serving patient, check for existing prescription draft
$servingPrescription = null;
if ($serving) {
    $pStmt = $pdo->prepare("SELECT * FROM prescriptions WHERE appointment_id = ? LIMIT 1");
    $pStmt->execute([$serving['id']]);
    $servingPrescription = $pStmt->fetch();
}

$pageTitle = "Consultation Cockpit";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-stethoscope" style="color:var(--primary);"></i> Doctor Consultation Cockpit</h1>
        <p class="page-subtitle">Call patients, launch video meetings, and prescribe in real-time</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" 
               onchange="window.location.href='queue_console.php?date=' + this.value;">
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1fr; align-items: start; margin-bottom: 2rem;">
    
    <!-- LEFT COLUMN: CURRENT SERVING CONSOLE & ACTIONS -->
    <div>
        <div class="card" style="border-top: 4px solid var(--primary); box-shadow: var(--shadow-md);">
            <div class="card-header">
                <div>
                    <span class="badge badge-serving" style="font-size:0.8rem;">IN CONSULTATION</span>
                    <h3 class="card-title" style="margin-top: 0.25rem;">Active Patient Consultation</h3>
                </div>
                <div style="display: flex; gap: 0.4rem;">
                    <button type="button" class="btn btn-primary" onclick="callNextToken('completed')">
                        <i class="fas fa-step-forward"></i> Call Next Patient
                    </button>
                </div>
            </div>

            <?php if ($serving): ?>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <div>
                            <span class="badge badge-primary" style="background:#0284c7;color:#fff;font-size:1rem;padding:0.35rem 0.85rem;">
                                Token #<?= $serving['token_number'] ?>
                            </span>
                            <h2 style="font-size: 1.5rem; margin-top: 0.35rem;"><?= htmlspecialchars($serving['patient_name']) ?></h2>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">
                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($serving['patient_phone']) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <a href="<?= BASE_URL ?>/video/room.php?id=<?= $serving['id'] ?>" target="_blank" class="btn btn-success btn-lg">
                                <i class="fas fa-video"></i> Start Video Call
                            </a>
                        </div>
                    </div>

                    <div style="background: #ffffff; border-radius: var(--radius-sm); padding: 0.75rem; font-size: 0.875rem; border: 1px solid #e2e8f0;">
                        <span style="color: var(--text-muted);"><i class="fas fa-notes-medical"></i> Reason for Visit:</span><br>
                        <strong><?= htmlspecialchars($serving['reason_for_visit'] ?: 'Routine checkup / general review') ?></strong>
                    </div>

                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="markAppointment('<?= $serving['id'] ?>', 'no_show')">
                            <i class="fas fa-user-slash" style="color:var(--danger);"></i> Mark No-Show
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="markAppointment('<?= $serving['id'] ?>', 'completed')">
                            <i class="fas fa-check-circle"></i> Mark Served
                        </button>
                    </div>
                </div>

                <!-- PRESCRIPTION WRITER -->
                <div class="card" style="margin-bottom: 0; padding: 1.25rem; background: #ffffff;">
                    <div class="card-header" style="padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                        <h4 class="card-title" style="font-size: 1.05rem;"><i class="fas fa-file-prescription" style="color:var(--primary);"></i> Issue Prescription Notes</h4>
                    </div>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_prescription">
                        <input type="hidden" name="appointment_id" value="<?= $serving['id'] ?>">

                        <div class="form-group">
                            <label class="form-label" for="diagnosis">Diagnosis / Clinical Impression *</label>
                            <input type="text" id="diagnosis" name="diagnosis" class="form-control" 
                                   placeholder="e.g. Acute Viral Bronchitis, Stage 1 HTN" 
                                   value="<?= htmlspecialchars($servingPrescription['diagnosis'] ?? '') ?>" required>
                        </div>

                        <div style="margin-bottom: 0.75rem;">
                            <label class="form-label">Medications (Rx)</label>
                            <div id="medicinesContainer">
                                <?php 
                                    $meds = !empty($servingPrescription['medicines']) ? json_decode($servingPrescription['medicines'], true) : [[]];
                                    foreach ($meds as $m):
                                ?>
                                <div class="med-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr auto; gap: 0.4rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="text" name="med_name[]" class="form-control" placeholder="Medicine Name" value="<?= htmlspecialchars($m['name'] ?? '') ?>">
                                    <input type="text" name="med_freq[]" class="form-control" placeholder="1-0-1" value="<?= htmlspecialchars($m['frequency'] ?? '') ?>">
                                    <input type="text" name="med_duration[]" class="form-control" placeholder="5 Days" value="<?= htmlspecialchars($m['duration'] ?? '') ?>">
                                    <input type="text" name="med_notes[]" class="form-control" placeholder="After food" value="<?= htmlspecialchars($m['instructions'] ?? '') ?>">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" title="Remove">&times;</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addMedicineRow()" style="margin-top: 0.25rem;">
                                <i class="fas fa-plus"></i> Add Medicine
                            </button>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="clinical_notes">Diet, Lifestyle & Clinical Advice</label>
                            <textarea id="clinical_notes" name="clinical_notes" class="form-control" rows="2" 
                                      placeholder="Dietary precautions, steam inhalation, hydration..."><?= htmlspecialchars($servingPrescription['clinical_notes'] ?? '') ?></textarea>
                        </div>

                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                            <label style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                                <input type="checkbox" name="mark_completed" value="1" checked> Also mark consultation completed
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Prescription
                            </button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                    <i class="fas fa-user-md fa-3x" style="opacity: 0.35; margin-bottom: 1rem; display: block;"></i>
                    <h3>No patient currently in consultation</h3>
                    <p style="font-size: 0.9rem; margin-top: 0.4rem; margin-bottom: 1.5rem;">
                        There are <strong><?= $queue['waiting_count'] ?></strong> patient(s) waiting in today's queue.
                    </p>
                    <button type="button" class="btn btn-primary btn-lg" onclick="callNextToken('completed')">
                        <i class="fas fa-bullhorn"></i> Call First Patient (Token #<?= $queue['next_token'] ?? '1' ?>)
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN: QUEUE LIST & QUEUE PROGRESSION -->
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="fas fa-list-ol"></i> Today's Patient Queue (<?= count($queue['all_appointments']) ?>)</h3>
                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?= $queue['waiting_count'] ?> waiting &bull; <?= $queue['completed_count'] ?> served</span>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.reload();">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Token #</th>
                            <th>Patient</th>
                            <th>Est. Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queue['all_appointments'])): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    No tokens booked for this date yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queue['all_appointments'] as $row): ?>
                                <tr style="<?= ($serving && $serving['id'] == $row['id']) ? 'background: #eff6ff;' : '' ?>">
                                    <td>
                                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.9rem;">
                                            #<?= $row['token_number'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['patient_name']) ?></strong><br>
                                        <span style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($row['patient_phone']) ?></span>
                                    </td>
                                    <td><?= $row['estimated_time'] ? date('h:i A', strtotime($row['estimated_time'])) : 'TBD' ?></td>
                                    <td>
                                        <span class="badge badge-<?= $row['status'] ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'waiting'): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="callSpecificToken('<?= $row['id'] ?>')">
                                                <i class="fas fa-phone-volume"></i> Call
                                            </button>
                                        <?php elseif ($row['status'] === 'serving'): ?>
                                            <a href="<?= BASE_URL ?>/video/room.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-video"></i>
                                            </a>
                                        <?php elseif ($row['status'] === 'completed'): ?>
                                            <a href="<?= BASE_URL ?>/patient/prescription.php?appointment_id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm" title="View Prescription">
                                                <i class="fas fa-file-prescription"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- JAVASCRIPT CONTROLLERS -->
<script>
function addMedicineRow() {
    const container = document.getElementById('medicinesContainer');
    const row = document.createElement('div');
    row.className = 'med-row';
    row.style = 'display: grid; grid-template-columns: 2fr 1fr 1fr 2fr auto; gap: 0.4rem; margin-bottom: 0.5rem; align-items: center;';
    row.innerHTML = `
        <input type="text" name="med_name[]" class="form-control" placeholder="Medicine Name">
        <input type="text" name="med_freq[]" class="form-control" placeholder="1-0-1">
        <input type="text" name="med_duration[]" class="form-control" placeholder="5 Days">
        <input type="text" name="med_notes[]" class="form-control" placeholder="After food">
        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" title="Remove">&times;</button>
    `;
    container.appendChild(row);
}

async function callNextToken(currentAction = 'completed') {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/call_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'call_next',
                doctor_id: <?= (int)$doctor['id'] ?>,
                date: '<?= htmlspecialchars($selectedDate) ?>',
                current_token_action: currentAction
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error advancing queue');
        }
    } catch (e) {
        alert('Failed to connect to queue server.');
    }
}

async function markAppointment(appointmentId, status) {
    if (!confirm(`Are you sure you want to mark this token as ${status}?`)) return;
    try {
        const res = await fetch('<?= BASE_URL ?>/api/call_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                appointment_id: appointmentId,
                status: status
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Server request failed.');
    }
}

async function callSpecificToken(appointmentId) {
    await markAppointment(appointmentId, 'serving');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
