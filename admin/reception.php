<?php
/**
 * Reception Desk & Live Queue Console
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';
require_role('admin');

$pdo = Database::getConnection();
$user = current_user();

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m-d');

// Fetch doctors
$doctors = $pdo->query("
    SELECT d.id, u.name, d.specialization 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_active = 1
")->fetchAll();

if (!$doctorId && !empty($doctors)) {
    $doctorId = (int)$doctors[0]['id'];
}

// Handle Walk-In Token Creation POST
$walkInResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'walk_in') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $pName   = trim($_POST['patient_name'] ?? '');
        $pPhone  = trim($_POST['patient_phone'] ?? '');
        $pEmail  = trim($_POST['patient_email'] ?? '');
        $pDocId  = (int)($_POST['doctor_id'] ?? $doctorId);
        $pReason = trim($_POST['reason_for_visit'] ?? 'Walk-in consultation');

        if (empty($pName) || empty($pPhone)) {
            set_flash('danger', 'Patient name and phone number are required for walk-in token.');
        } else {
            // Find existing user by phone or email, or create
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $uStmt->execute([$pPhone]);
            $patientId = $uStmt->fetchColumn();

            if (!$patientId) {
                $fakeEmail = !empty($pEmail) ? $pEmail : ('walkin_' . time() . '_' . rand(100, 999) . '@clinic.local');
                $hash = password_hash($pPhone, PASSWORD_BCRYPT);
                $insUser = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'patient')");
                $insUser->execute([$pName, $fakeEmail, $pPhone, $hash]);
                $patientId = (int)$pdo->lastInsertId();
            }

            // Issue atomic token
            $walkInResult = TokenManager::bookToken($patientId, $pDocId, $date, $pReason);
            if ($walkInResult['success']) {
                set_flash('success', "Walk-in Token #{$walkInResult['token_number']} issued successfully for {$pName}!");
                redirect(BASE_URL . "/admin/reception.php?doctor_id={$pDocId}&date={$date}");
            } else {
                set_flash('danger', $walkInResult['message']);
            }
        }
    }
}

// Fetch live queue
$queue = TokenManager::getLiveQueue($doctorId, $date);

$pageTitle = "Reception Desk Console";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-hospital-user" style="color:var(--primary);"></i> Reception Queue Desk</h1>
        <p class="page-subtitle">Advance doctor queues, issue walk-in tokens, and manage patient attendance</p>
    </div>
</div>

<!-- TOP SELECTOR & ACTIONS -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.2rem;">Doctor Queue:</label>
            <select name="doctor_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($doctorId == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="width: 180px;">
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.2rem;">Date:</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
        </div>

        <div style="margin-top: 1.2rem; display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Refresh</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openWalkInModal()"><i class="fas fa-plus"></i> Issue Walk-In Token</button>
        </div>
    </form>
</div>

<div class="grid-2" style="grid-template-columns: 360px 1fr; align-items: start;">
    
    <!-- LEFT: RECEPTION CONTROLS & NOW SERVING -->
    <div>
        <div class="serving-hero-card" style="padding: 1.75rem 1.25rem; margin-bottom: 1.5rem;">
            <span class="serving-hero-tag">Now Serving</span>
            <div class="serving-token-display" style="font-size: 3.5rem;">
                <?= $queue['current_serving_token'] > 0 ? ('#' . $queue['current_serving_token']) : 'None' ?>
            </div>
            <div style="font-size: 0.95rem; margin-bottom: 1rem;">
                <?= $queue['currently_serving'] ? htmlspecialchars($queue['currently_serving']['patient_name']) : 'Doctor is currently idle' ?>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <button type="button" class="btn btn-warning" onclick="advanceQueue('completed')">
                    <i class="fas fa-step-forward"></i> Call Next Token (Token #<?= $queue['next_token'] ?? '1' ?>)
                </button>
                <?php if ($queue['currently_serving']): ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="updateStatus('<?= $queue['currently_serving']['id'] ?>', 'no_show')">
                        <i class="fas fa-user-slash"></i> Mark Token #<?= $queue['current_serving_token'] ?> as No-Show
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- QUEUE STATS -->
        <div class="card">
            <h4 style="margin-bottom: 0.75rem;"><i class="fas fa-chart-pie"></i> Queue Summary</h4>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color:var(--text-muted);">Waiting in Line:</span>
                    <strong style="color:var(--warning);"><?= $queue['waiting_count'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color:var(--text-muted);">Served Successfully:</span>
                    <strong style="color:var(--success);"><?= $queue['completed_count'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color:var(--text-muted);">No-Show Patients:</span>
                    <strong style="color:var(--danger);"><?= $queue['no_show_count'] ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                    <span>Total Tokens Issued:</span>
                    <strong><?= $queue['total_tokens'] ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: FULL APPOINTMENTS LIST FOR DOCTOR TODAY -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Token Schedule for <?= htmlspecialchars($queue['doctor']['name']) ?></h3>
            <span class="badge" style="background:#e0f2fe; color:#0369a1;"><?= format_date($date) ?></span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Patient Details</th>
                        <th>Estimated Time</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
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
                            <tr>
                                <td>
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size: 0.9rem;">
                                        #<?= $row['token_number'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['patient_name']) ?></strong><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($row['patient_phone']) ?></span>
                                </td>
                                <td><?= $row['estimated_time'] ? date('h:i A', strtotime($row['estimated_time'])) : 'TBD' ?></td>
                                <td>
                                    <span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($row['status'] === 'waiting'): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="updateStatus('<?= $row['id'] ?>', 'serving')">
                                            <i class="fas fa-bullhorn"></i> Call
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="updateStatus('<?= $row['id'] ?>', 'no_show')">
                                            No-Show
                                        </button>
                                    <?php elseif ($row['status'] === 'serving'): ?>
                                        <button type="button" class="btn btn-success btn-sm" onclick="updateStatus('<?= $row['id'] ?>', 'completed')">
                                            <i class="fas fa-check"></i> Done
                                        </button>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size: 0.8rem;">Finished</span>
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

<!-- WALK-IN TOKEN MODAL -->
<div id="walkInModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.25rem;"><i class="fas fa-ticket-alt" style="color:var(--primary);"></i> Issue Walk-In Token</h3>
            <button type="button" class="close-alert" onclick="closeWalkInModal()">&times;</button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="walk_in">
            <input type="hidden" name="doctor_id" value="<?= $doctorId ?>">

            <div class="form-group">
                <label class="form-label">Doctor:</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($queue['doctor']['name']) ?>" readonly style="background:#f1f5f9;">
            </div>

            <div class="form-group">
                <label class="form-label" for="patient_name">Patient Name *</label>
                <input type="text" id="patient_name" name="patient_name" class="form-control" placeholder="Patient's Full Name" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="patient_phone">Phone Number *</label>
                <input type="tel" id="patient_phone" name="patient_phone" class="form-control" placeholder="10-digit mobile number" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="patient_email">Email (Optional)</label>
                <input type="email" id="patient_email" name="patient_email" class="form-control" placeholder="patient@example.com">
            </div>

            <div class="form-group">
                <label class="form-label" for="reason_for_visit">Reason / Symptoms</label>
                <input type="text" id="reason_for_visit" name="reason_for_visit" class="form-control" placeholder="Walk-in consultation">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeWalkInModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-ticket-alt"></i> Generate Token Now
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openWalkInModal() {
    document.getElementById('walkInModal').classList.add('active');
}
function closeWalkInModal() {
    document.getElementById('walkInModal').classList.remove('active');
}

async function advanceQueue(currentAction = 'completed') {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/call_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'call_next',
                doctor_id: <?= (int)$doctorId ?>,
                date: '<?= htmlspecialchars($date) ?>',
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
        alert('Server communication error.');
    }
}

async function updateStatus(appointmentId, status) {
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
        alert('Server communication error.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
