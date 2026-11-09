<?php
/**
 * Doctor Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';
require_role('doctor');

$user = current_user();
$pdo = Database::getConnection();

// Fetch doctor record
$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ? LIMIT 1");
$docStmt->execute([$user['id']]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    set_flash('danger', 'Doctor profile not found.');
    redirect(BASE_URL . '/index.php');
}

$today = date('Y-m-d');
$queueData = TokenManager::getLiveQueue($doctor['id'], $today);

// Total patients ever served by this doctor
$allServedStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
$allServedStmt->execute([$doctor['id']]);
$totalCompletedAllTime = (int)$allServedStmt->fetchColumn();

$pageTitle = "Doctor Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Doctor Dashboard</h1>
        <p class="page-subtitle"><?= htmlspecialchars($user['name']) ?> &bull; <?= htmlspecialchars($doctor['specialization']) ?></p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= BASE_URL ?>/doctor/queue_console.php" class="btn btn-primary btn-lg">
            <i class="fas fa-headset"></i> Open Queue Console
        </a>
    </div>
</div>

<!-- STATS CARDS -->
<div class="grid-4" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-play-circle"></i></div>
        <div>
            <div class="stat-num">
                <?= $queueData['current_serving_token'] ? ('#' . $queueData['current_serving_token']) : 'None' ?>
            </div>
            <div class="stat-label">Currently Serving</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-user-clock"></i></div>
        <div>
            <div class="stat-num"><?= $queueData['waiting_count'] ?></div>
            <div class="stat-label">Waiting in Queue</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-num"><?= $queueData['completed_count'] ?></div>
            <div class="stat-label">Served Today</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stat-num"><?= $queueData['no_show_count'] ?></div>
            <div class="stat-label">No-Shows Today</div>
        </div>
    </div>
</div>

<!-- CURRENT SESSION & QUICK ACTION -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calendar-day"></i> Today's Consultation Schedule</h3>
            <span class="badge badge-primary" style="background:#0284c7;color:#fff;"><?= format_date($today) ?></span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1rem;">
            Consultation Session: <strong><?= format_time($queueData['doctor']['session_start']) ?> - <?= format_time($queueData['doctor']['session_end']) ?></strong><br>
            Average Time Per Patient: <strong><?= (int)$doctor['avg_consultation_mins'] ?> Minutes</strong>
        </p>

        <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.9rem; margin-bottom: 0.4rem;">
                <strong>Queue Progress:</strong> <?= $queueData['completed_count'] ?> of <?= $queueData['total_tokens'] ?> completed
            </div>
            <?php 
                $pct = $queueData['total_tokens'] > 0 ? round(($queueData['completed_count'] / $queueData['total_tokens']) * 100) : 0;
            ?>
            <div style="background: #e2e8f0; height: 10px; border-radius: 9999px; overflow: hidden;">
                <div style="background: var(--success); width: <?= $pct ?>%; height: 100%;"></div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/doctor/queue_console.php" class="btn btn-primary" style="width: 100%;">
            <i class="fas fa-stethoscope"></i> Launch Live Consultation Console
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock"></i> Next Patients Waiting</h3>
            <a href="<?= BASE_URL ?>/doctor/queue_console.php" style="font-size: 0.85rem; color: var(--primary);">View Full Queue</a>
        </div>

        <?php if (empty($queueData['waiting_tokens'])): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <i class="fas fa-coffee fa-2x" style="margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                No patients waiting right now.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach (array_slice($queueData['waiting_tokens'], 0, 4) as $w): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: #f8fafc; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <div>
                            <strong>Token #<?= $w['token_number'] ?></strong> &bull; <?= htmlspecialchars($w['patient_name']) ?>
                        </div>
                        <div style="font-size: 0.825rem; color: var(--text-muted);">
                            <i class="far fa-clock"></i> <?= $w['estimated_time'] ? date('h:i A', strtotime($w['estimated_time'])) : 'TBD' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
