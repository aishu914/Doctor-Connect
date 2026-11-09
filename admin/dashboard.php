<?php
/**
 * Admin / Receptionist Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = Database::getConnection();
$today = date('Y-m-d');

// Key metrics
$totalDoctors = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active = 1")->fetchColumn();
$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();

// Today's appointments stats
$todayStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_today,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_today,
        SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) AS serving_today,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_today,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS noshow_today
    FROM appointments 
    WHERE appointment_date = ?
");
$todayStats->execute([$today]);
$metrics = $todayStats->fetch();

// Doctor Queues Today
$docQueues = $pdo->prepare("
    SELECT d.id AS doctor_id, u.name AS doctor_name, d.specialization,
           COUNT(a.id) AS total_tokens,
           SUM(CASE WHEN a.status = 'serving' THEN a.token_number ELSE 0 END) AS current_serving,
           SUM(CASE WHEN a.status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date = ?
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY total_tokens DESC
");
$docQueues->execute([$today]);
$activeQueues = $docQueues->fetchAll();

$pageTitle = "Admin & Clinic Control";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-hospital-alt" style="color:var(--primary);"></i> Clinic Administration & Overview</h1>
        <p class="page-subtitle">Real-time clinic queues, doctor availability, and appointment monitoring</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= BASE_URL ?>/admin/reception.php" class="btn btn-primary">
            <i class="fas fa-concierge-bell"></i> Open Reception Desk
        </a>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-secondary">
            <i class="fas fa-file-csv"></i> Reports & Export
        </a>
    </div>
</div>

<!-- TOP METRICS -->
<div class="grid-4" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-calendar-day"></i></div>
        <div>
            <div class="stat-num"><?= (int)$metrics['total_today'] ?></div>
            <div class="stat-label">Appointments Today</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-user-clock"></i></div>
        <div>
            <div class="stat-num"><?= (int)$metrics['waiting_today'] ?></div>
            <div class="stat-label">Currently Waiting</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-num"><?= (int)$metrics['completed_today'] ?></div>
            <div class="stat-label">Served Successfully</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-num"><?= (int)$metrics['noshow_today'] ?></div>
            <div class="stat-label">No-Shows</div>
        </div>
    </div>
</div>

<!-- TODAY'S QUEUE STATUS BY DOCTOR -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users-line"></i> Active Doctor Queues (<?= format_date($today) ?>)</h3>
        <a href="<?= BASE_URL ?>/admin/reception.php" class="btn btn-primary btn-sm">Manage Live Queues</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Specialty</th>
                    <th>Now Serving</th>
                    <th>Waiting</th>
                    <th>Completed</th>
                    <th>Total Booked</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeQueues)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 2rem; color:var(--text-muted);">
                            No active doctors configured yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeQueues as $q): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($q['doctor_name']) ?></strong></td>
                            <td><span class="badge" style="background:#e0f2fe; color:#0369a1;"><?= htmlspecialchars($q['specialization']) ?></span></td>
                            <td>
                                <?php if ($q['current_serving'] > 0): ?>
                                    <span class="badge badge-serving">Token #<?= $q['current_serving'] ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">Idle</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color:var(--warning);"><?= (int)$q['waiting_count'] ?></strong></td>
                            <td><strong style="color:var(--success);"><?= (int)$q['completed_count'] ?></strong></td>
                            <td><strong><?= (int)$q['total_tokens'] ?></strong></td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/reception.php?doctor_id=<?= $q['doctor_id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-headset"></i> Control Queue
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
