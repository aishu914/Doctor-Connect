<?php
/**
 * View & Print Prescription
 */

require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$prescriptionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$appointmentId = filter_input(INPUT_GET, 'appointment_id', FILTER_VALIDATE_INT);

$pdo = Database::getConnection();

if ($prescriptionId) {
    $stmt = $pdo->prepare("
        SELECT p.*, a.appointment_date, a.token_number, 
               uDoc.name AS doctor_name, d.specialization, d.qualification,
               uPat.name AS patient_name, uPat.phone AS patient_phone, uPat.email AS patient_email
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN doctors d ON p.doctor_id = d.id
        JOIN users uDoc ON d.user_id = uDoc.id
        JOIN users uPat ON p.patient_id = uPat.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$prescriptionId]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, a.appointment_date, a.token_number, 
               uDoc.name AS doctor_name, d.specialization, d.qualification,
               uPat.name AS patient_name, uPat.phone AS patient_phone, uPat.email AS patient_email
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN doctors d ON p.doctor_id = d.id
        JOIN users uDoc ON d.user_id = uDoc.id
        JOIN users uPat ON p.patient_id = uPat.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$appointmentId]);
}

$rx = $stmt->fetch();

if (!$rx) {
    set_flash('danger', 'Prescription not found.');
    redirect(BASE_URL . '/index.php');
}

// Access check: only the patient themselves, the doctor, or admin can view
if ($user['role'] === 'patient' && $rx['patient_id'] != $user['id']) {
    set_flash('danger', 'Unauthorized access to prescription.');
    redirect(BASE_URL . '/patient/dashboard.php');
}

// Decode medicines JSON if any
$medicines = [];
if (!empty($rx['medicines'])) {
    $medicines = json_decode($rx['medicines'], true) ?: [];
}

$pageTitle = "Prescription - Token #" . $rx['token_number'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;" class="no-print">
    <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <button type="button" onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Prescription
    </button>
</div>

<div class="prescription-container">
    <!-- CLINIC & DOCTOR HEADER -->
    <div class="prescription-header">
        <div>
            <h2 style="color: var(--primary-dark); font-size: 1.5rem; margin-bottom: 0.2rem;">
                <?= htmlspecialchars($rx['doctor_name']) ?>
            </h2>
            <div style="font-weight: 600; color: var(--secondary);">
                <?= htmlspecialchars($rx['specialization']) ?> &bull; <?= htmlspecialchars($rx['qualification']) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                DoctorConnect Telehealth Network
            </div>
        </div>

        <div style="text-align: right; font-size: 0.85rem; color: var(--text-muted);">
            <div><strong>Date:</strong> <?= format_date($rx['appointment_date']) ?></div>
            <div><strong>Token #:</strong> <span class="badge badge-primary" style="background:#0284c7;color:#fff;">#<?= $rx['token_number'] ?></span></div>
            <div><strong>Rx ID:</strong> #<?= str_pad($rx['id'], 5, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <!-- PATIENT DETAILS BAR -->
    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem 1.25rem; margin: 1.5rem 0; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
        <div>
            <span style="color: var(--text-muted);">Patient Name:</span><br>
            <strong><?= htmlspecialchars($rx['patient_name']) ?></strong>
        </div>
        <div>
            <span style="color: var(--text-muted);">Contact:</span><br>
            <strong><?= htmlspecialchars($rx['patient_phone']) ?></strong>
        </div>
        <div>
            <span style="color: var(--text-muted);">Consultation:</span><br>
            <strong>Online Video</strong>
        </div>
    </div>

    <!-- DIAGNOSIS -->
    <div style="margin-bottom: 1.5rem;">
        <h4 style="font-size: 0.95rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 0.4rem;">
            Diagnosis / Clinical Impression
        </h4>
        <div style="font-size: 1.1rem; font-weight: 600; color: #1e293b; background: #f1f5f9; padding: 0.65rem 1rem; border-radius: var(--radius-sm);">
            <?= htmlspecialchars($rx['diagnosis']) ?>
        </div>
    </div>

    <!-- RX MEDICATIONS -->
    <div style="margin-bottom: 2rem;">
        <div class="rx-symbol">℞</div>

        <?php if (!empty($medicines)): ?>
            <table class="data-table" style="margin-top: 0.5rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine & Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $idx => $m): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><strong><?= htmlspecialchars($m['name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($m['frequency'] ?? '') ?></td>
                            <td><?= htmlspecialchars($m['duration'] ?? '') ?></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($m['instructions'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic;">No specific oral medications prescribed.</p>
        <?php endif; ?>
    </div>

    <!-- CLINICAL NOTES & ADVICE -->
    <?php if (!empty($rx['clinical_notes'])): ?>
        <div style="margin-bottom: 2rem;">
            <h4 style="font-size: 0.95rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 0.4rem;">
                Advice & Dietary / Lifestyle Instructions
            </h4>
            <div style="font-size: 0.95rem; color: #334155; line-height: 1.6; white-space: pre-line; background: #fafafa; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
                <?= htmlspecialchars($rx['clinical_notes']) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER SIGNATURE -->
    <div style="margin-top: 3.5rem; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
        <div style="font-size: 0.775rem; color: var(--text-muted); max-width: 380px;">
            This is a digitally generated medical prescription issued via DoctorConnect Telemedicine platform following verified patient turn.
        </div>
        <div style="text-align: center;">
            <div style="font-family: 'Brush Script MT', cursive; font-size: 1.6rem; color: var(--primary-dark); margin-bottom: -0.25rem;">
                <?= htmlspecialchars($rx['doctor_name']) ?>
            </div>
            <div style="width: 180px; border-top: 1px solid #1e293b; padding-top: 0.25rem; font-size: 0.8rem; font-weight: 600;">
                Doctor's Signature
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
