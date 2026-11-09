<?php
/**
 * Doctor Schedule Management
 */

require_once __DIR__ . '/../config/config.php';
require_role('doctor');

$user = current_user();
$pdo = Database::getConnection();

// Fetch doctor
$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ? LIMIT 1");
$docStmt->execute([$user['id']]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    set_flash('danger', 'Doctor profile not found.');
    redirect(BASE_URL . '/index.php');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        // Update general doctor settings
        $fee = (float)($_POST['consultation_fee'] ?? 500);
        $avgMins = (int)($_POST['avg_consultation_mins'] ?? 15);

        $pdo->prepare("UPDATE doctors SET consultation_fee = ?, avg_consultation_mins = ? WHERE id = ?")
            ->execute([$fee, $avgMins, $doctor['id']]);

        // Update day-by-day schedules
        $days = $_POST['days'] ?? [];
        for ($day = 0; $day <= 6; $day++) {
            $isActive  = isset($days[$day]['active']) ? 1 : 0;
            $startTime = $days[$day]['start_time'] ?? '09:00:00';
            $endTime   = $days[$day]['end_time'] ?? '13:00:00';
            $maxTokens = (int)($days[$day]['max_tokens'] ?? 25);

            // Check if schedule exists for this day
            $chk = $pdo->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
            $chk->execute([$doctor['id'], $day]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $upd = $pdo->prepare("
                    UPDATE doctor_schedules 
                    SET start_time = ?, end_time = ?, max_tokens = ?, slot_duration = ?, is_active = ?
                    WHERE id = ?
                ");
                $upd->execute([$startTime, $endTime, $maxTokens, $avgMins, $isActive, $existingId]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_tokens, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$doctor['id'], $day, $startTime, $endTime, $avgMins, $maxTokens, $isActive]);
            }
        }

        set_flash('success', 'Weekly schedule and consultation settings updated successfully!');
        redirect(BASE_URL . '/doctor/schedule.php');
    }
}

// Fetch existing schedules indexed by day_of_week
$schedStmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week ASC");
$schedStmt->execute([$doctor['id']]);
$rawSchedules = $schedStmt->fetchAll();

$schedules = [];
foreach ($rawSchedules as $s) {
    $schedules[$s['day_of_week']] = $s;
}

$pageTitle = "My Schedule & Availability";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Consultation Schedule & Availability</h1>
        <p class="page-subtitle">Define your working days, session hours, slot duration, and token quotas</p>
    </div>
</div>

<form method="POST" action="">
    <?= csrf_field() ?>

    <!-- GENERAL SETTINGS -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">Consultation Rates & Session Length</h3>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label" for="consultation_fee">Consultation Fee (<?= CURRENCY_SYMBOL ?>)</label>
                <input type="number" step="10" name="consultation_fee" id="consultation_fee" class="form-control" 
                       value="<?= htmlspecialchars($doctor['consultation_fee']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="avg_consultation_mins">Avg Consultation Duration (Minutes)</label>
                <input type="number" min="5" max="60" name="avg_consultation_mins" id="avg_consultation_mins" class="form-control" 
                       value="<?= htmlspecialchars($doctor['avg_consultation_mins']) ?>" required>
                <small style="color:var(--text-muted);">Used to calculate estimated meeting times for each token.</small>
            </div>
        </div>
    </div>

    <!-- WEEKLY DAY-BY-DAY SCHEDULE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Weekly Working Days & Session Times</h3>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Day of Week</th>
                        <th style="width: 120px;">Available?</th>
                        <th>Session Start Time</th>
                        <th>Session End Time</th>
                        <th>Max Tokens / Day</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($d = 0; $d <= 6; $d++): 
                        $curr = $schedules[$d] ?? null;
                        $isActive = $curr ? (bool)$curr['is_active'] : ($d !== 0);
                        $start = $curr ? $curr['start_time'] : '09:00:00';
                        $end = $curr ? $curr['end_time'] : '13:00:00';
                        $max = $curr ? $curr['max_tokens'] : 25;
                    ?>
                    <tr>
                        <td><strong><?= get_day_name($d) ?></strong></td>
                        <td>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="days[<?= $d ?>][active]" value="1" <?= $isActive ? 'checked' : '' ?>>
                                <span><?= $isActive ? 'Open' : 'Off' ?></span>
                            </label>
                        </td>
                        <td>
                            <input type="time" name="days[<?= $d ?>][start_time]" class="form-control" value="<?= substr($start, 0, 5) ?>">
                        </td>
                        <td>
                            <input type="time" name="days[<?= $d ?>][end_time]" class="form-control" value="<?= substr($end, 0, 5) ?>">
                        </td>
                        <td>
                            <input type="number" min="1" max="100" name="days[<?= $d ?>][max_tokens]" class="form-control" value="<?= $max ?>">
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save Schedule Changes
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
