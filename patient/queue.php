<?php
/**
 * Live Queue Board (Patient View)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';

$user = current_user();
$pdo = Database::getConnection();

// Select doctor: either from query param or find first active doctor
$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m-d');

// If no doctor selected and user is logged in, check if patient has a token for today
if (!$doctorId && $user && $user['role'] === 'patient') {
    $myAppt = $pdo->prepare("
        SELECT doctor_id, appointment_date 
        FROM appointments 
        WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN ('waiting', 'serving')
        ORDER BY appointment_date ASC, token_number ASC LIMIT 1
    ");
    $myAppt->execute([$user['id']]);
    if ($found = $myAppt->fetch()) {
        $doctorId = (int)$found['doctor_id'];
        $date = $found['appointment_date'];
    }
}

// Fallback to first doctor if still null
if (!$doctorId) {
    $firstDoc = $pdo->query("SELECT id FROM doctors WHERE is_active = 1 LIMIT 1")->fetch();
    $doctorId = $firstDoc ? (int)$firstDoc['id'] : 1;
}

// Fetch all doctors for selector dropdown
$allDoctors = $pdo->query("
    SELECT d.id, u.name, d.specialization 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.is_active = 1
")->fetchAll();

// Fetch initial queue snapshot
$queueData = TokenManager::getLiveQueue($doctorId, $date);

// Find user's token number if patient
$userTokenNumber = null;
$userAppointmentId = null;
$userCanJoinMeeting = false;
$userMeetingLink = null;

if ($user && $user['role'] === 'patient') {
    $userApptStmt = $pdo->prepare("
        SELECT * FROM appointments 
        WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status != 'cancelled'
        LIMIT 1
    ");
    $userApptStmt->execute([$user['id'], $doctorId, $date]);
    if ($uAppt = $userApptStmt->fetch()) {
        $userTokenNumber = (int)$uAppt['token_number'];
        $userAppointmentId = (int)$uAppt['id'];
        $userMeetingLink = $uAppt['meeting_link'];
        $userCanJoinMeeting = is_meeting_link_accessible($uAppt, $queueData['current_serving_token']);
    }
}

$pageTitle = "Live Queue Board - " . htmlspecialchars($queueData['doctor']['name'] ?? 'Doctor');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- QUEUE SELECTOR BAR -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <label class="form-label" style="margin-bottom: 0.2rem; font-size: 0.8rem;">Select Doctor:</label>
            <select name="doctor_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($allDoctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>" <?= ($doctorId == $doc['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doc['name']) ?> (<?= htmlspecialchars($doc['specialization']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="width: 180px;">
            <label class="form-label" style="margin-bottom: 0.2rem; font-size: 0.8rem;">Date:</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
        </div>

        <div style="margin-top: 1.2rem;">
            <button type="submit" class="btn btn-secondary btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>

        <div style="margin-left: auto; margin-top: 1.2rem;">
            <button type="button" id="enableNotifsBtn" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-bell"></i> Enable Sound & Turn Alerts
            </button>
        </div>
    </form>
</div>

<!-- PATIENT ACTION BANNER (MEETING LINK / STATUS) -->
<?php if ($userTokenNumber): ?>
    <div class="card" style="border: 2px solid var(--primary); background: #f0f9ff; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge" style="background:#0284c7; color:#fff; font-size:0.85rem; padding: 0.35rem 0.85rem;">YOUR ACTIVE TOKEN</span>
                <h3 style="font-size: 1.75rem; margin: 0.35rem 0 0.2rem;">Token #<?= $userTokenNumber ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">
                    Doctor: <strong><?= htmlspecialchars($queueData['doctor']['name']) ?></strong> &bull; Date: <strong><?= format_date($date) ?></strong>
                </p>
            </div>

            <!-- DYNAMIC MEETING ACTION CONTAINER -->
            <div id="patientMeetingAction" style="min-width: 280px; text-align: right;">
                <?php if ($userCanJoinMeeting): ?>
                    <div class="alert alert-success" style="animation: pulse 1.5s infinite; margin-bottom: 0.5rem; text-align: left;">
                        <i class="fas fa-video fa-lg"></i>
                        <div><strong>Doctor is ready!</strong> Join the consultation room now.</div>
                    </div>
                    <a href="<?= BASE_URL ?>/video/room.php?id=<?= $userAppointmentId ?>" target="_blank" class="btn btn-success btn-lg" style="width: 100%;">
                        <i class="fas fa-video"></i> Join Consultation Now
                    </a>
                <?php else: ?>
                    <div style="background: rgba(2, 132, 199, 0.08); border: 1px dashed var(--primary); border-radius: var(--radius-md); padding: 0.85rem 1rem; text-align: center;">
                        <i class="fas fa-lock" style="color: var(--primary); margin-bottom: 0.25rem;"></i>
                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--primary-dark);">Consultation Link Locked</div>
                        <div style="font-size: 0.775rem; color: var(--text-muted); margin-top: 0.2rem;">
                            Activates automatically when you are NEXT or SERVING.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- LIVE QUEUE DASHBOARD -->
<div class="queue-board-wrapper">
    <!-- LEFT: SERVING & STATISTICS -->
    <div>
        <div class="serving-hero-card">
            <span class="serving-hero-tag"><i class="fas fa-broadcast-tower"></i> Now Serving</span>
            <div class="serving-token-display" id="currentServingToken">
                <?= $queueData['current_serving_token'] > 0 ? ('#' . $queueData['current_serving_token']) : 'None' ?>
            </div>
            <div class="pulsing-indicator">
                <span class="pulsing-dot"></span>
                <span>Live queue actively updating every 3 seconds</span>
            </div>
        </div>

        <div class="grid-3" style="margin-top: 1.5rem;">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-forward"></i></div>
                <div>
                    <div class="stat-num" id="nextTokenNumber">
                        <?= $queueData['next_token'] ? ('#' . $queueData['next_token']) : 'None' ?>
                    </div>
                    <div class="stat-label">Next in Line</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-user-clock"></i></div>
                <div>
                    <div class="stat-num" id="waitingCount"><?= $queueData['waiting_count'] ?></div>
                    <div class="stat-label">Waiting Patients</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-num" id="completedCount"><?= $queueData['completed_count'] ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: UPCOMING TOKENS LIST -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list-ol"></i> Queue Sequence</h3>
            <span class="badge badge-waiting">Today's Line</span>
        </div>

        <div class="token-queue-list" id="waitingTokensList">
            <?php if (empty($queueData['waiting_tokens'])): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    No patients currently in queue.
                </div>
            <?php else: ?>
                <?php foreach ($queueData['waiting_tokens'] as $t): ?>
                    <?php $isMine = ($userTokenNumber && $t['token_number'] == $userTokenNumber); ?>
                    <div class="token-queue-item <?= $isMine ? 'is-user-token' : '' ?>">
                        <div class="token-queue-badge">
                            <span>Token #<?= $t['token_number'] ?></span>
                            <?php if ($isMine): ?>
                                <span class="badge" style="background:#0284c7; color:#fff;">YOU</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.875rem; color:var(--text-muted);">
                            <i class="far fa-clock"></i> Est: <?= $t['estimated_time'] ? date('h:i A', strtotime($t['estimated_time'])) : 'TBD' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- REAL-TIME QUEUE POLLING SCRIPT -->
<script src="<?= BASE_URL ?>/assets/js/live_queue.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new LiveQueue({
        doctorId: <?= (int)$doctorId ?>,
        date: <?= json_encode($date) ?>,
        userTokenNumber: <?= json_encode($userTokenNumber) ?>,
        apiUrl: '<?= BASE_URL ?>/api/queue_status.php',
        pollInterval: 3000
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
