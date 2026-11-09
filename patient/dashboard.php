<?php
/**
 * Patient Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_role('patient');

$user = current_user();
$pdo = Database::getConnection();
$today = date('Y-m-d');

// Handle token cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_id'])) {
    $cancelId = (int)$_POST['cancel_appointment_id'];
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        require_once __DIR__ . '/../includes/token_manager.php';
        $res = TokenManager::updateAppointmentStatus($cancelId, 'cancelled', $user['id'], 'Cancelled by patient');
        if ($res['success']) {
            set_flash('success', 'Token cancelled successfully.');
        } else {
            set_flash('danger', $res['message']);
        }
        redirect(BASE_URL . '/patient/dashboard.php');
    }
}

// 1. Fetch today's active token if any
$todayStmt = $pdo->prepare("
    SELECT a.*, u.name AS doctor_name, d.specialization, d.avg_consultation_mins,
           (SELECT COUNT(*) FROM appointments w WHERE w.doctor_id = a.doctor_id AND w.appointment_date = a.appointment_date AND w.status = 'waiting' AND w.token_number < a.token_number) AS patients_ahead
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ? AND a.appointment_date = ? AND a.status IN ('waiting', 'serving')
    LIMIT 1
");
$todayStmt->execute([$user['id'], $today]);
$todayAppt = $todayStmt->fetch();

// 2. Fetch past and all appointments
$historyStmt = $pdo->prepare("
    SELECT a.*, u.name AS doctor_name, d.specialization,
           p.id AS prescription_id
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN prescriptions p ON a.id = p.appointment_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.token_number DESC
");
$historyStmt->execute([$user['id']]);
$allAppointments = $historyStmt->fetchAll();

$pageTitle = "Patient Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Welcome, <?= htmlspecialchars($user['name']) ?></h1>
        <p class="page-subtitle">Track your consultation tokens, live queue positions, and prescriptions</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-primary">
            <i class="fas fa-ticket-alt"></i> Book New Token
        </a>
    </div>
</div>

<!-- TODAY'S ACTIVE TOKEN BANNER -->
<?php if ($todayAppt): ?>
    <div class="card" style="border: 2px solid var(--primary); background: #f0f9ff; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge badge-<?= $todayAppt['status'] ?>">
                    <?= strtoupper($todayAppt['status']) ?>
                </span>
                <h2 style="font-size: 1.85rem; margin: 0.5rem 0 0.25rem;">
                    Token #<?= $todayAppt['token_number'] ?> &bull; <?= htmlspecialchars($todayAppt['doctor_name']) ?>
                </h2>
                <div style="color: var(--text-muted); font-size: 0.95rem;">
                    <span><i class="fas fa-user-md"></i> <?= htmlspecialchars($todayAppt['specialization']) ?></span> &bull;
                    <span><i class="far fa-clock"></i> Est. Time: <strong><?= format_datetime($todayAppt['estimated_time']) ?></strong></span>
                    <?php if ($todayAppt['status'] === 'waiting'): ?>
                        &bull; <span style="color: var(--primary-dark); font-weight: 600;"><i class="fas fa-users"></i> <?= (int)$todayAppt['patients_ahead'] ?> patient(s) ahead of you</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <a href="<?= BASE_URL ?>/patient/queue.php?doctor_id=<?= $todayAppt['doctor_id'] ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-tv"></i> Open Live Queue Board
                </a>
                <?php if ($todayAppt['status'] === 'serving'): ?>
                    <a href="<?= BASE_URL ?>/video/room.php?id=<?= $todayAppt['id'] ?>" target="_blank" class="btn btn-success btn-lg">
                        <i class="fas fa-video"></i> Join Consultation
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- APPOINTMENT HISTORY -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> My Appointments & Tokens</h3>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor & Specialty</th>
                    <th>Token #</th>
                    <th>Estimated Time</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allAppointments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            You have no appointments booked yet. <a href="<?= BASE_URL ?>/patient/book.php" style="color:var(--primary); font-weight:600;">Book your first token</a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allAppointments as $row): ?>
                        <tr>
                            <td><strong><?= format_date($row['appointment_date']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($row['doctor_name']) ?></strong><br>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($row['specialization']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.9rem;">
                                    #<?= $row['token_number'] ?>
                                </span>
                            </td>
                            <td><?= $row['estimated_time'] ? date('h:i A', strtotime($row['estimated_time'])) : 'TBD' ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($row['reason_for_visit'] ?: 'General checkup') ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem;">
                                    <?php if ($row['appointment_date'] >= $today && in_array($row['status'], ['waiting', 'serving'])): ?>
                                        <a href="<?= BASE_URL ?>/patient/queue.php?doctor_id=<?= $row['doctor_id'] ?>" class="btn btn-secondary btn-sm" title="Live Queue">
                                            <i class="fas fa-tv"></i> Queue
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($row['prescription_id']): ?>
                                        <a href="<?= BASE_URL ?>/patient/prescription.php?id=<?= $row['prescription_id'] ?>" class="btn btn-outline-primary btn-sm" title="View Prescription">
                                            <i class="fas fa-file-prescription"></i> Rx
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($row['status'] === 'waiting' && $row['appointment_date'] >= $today): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this token?');" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="cancel_appointment_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Cancel Token">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
