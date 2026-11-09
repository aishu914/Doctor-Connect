<?php
/**
 * Video Consultation Room (Jitsi Meet Integration)
 */

require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointmentId) {
    set_flash('danger', 'Invalid consultation room.');
    redirect(BASE_URL . '/index.php');
}

$pdo = Database::getConnection();

// Fetch appointment with doctor and patient details
$stmt = $pdo->prepare("
    SELECT a.*, 
           uDoc.name AS doctor_name, d.specialization, d.user_id AS doctor_user_id,
           uPat.name AS patient_name, uPat.id AS patient_user_id
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users uDoc ON d.user_id = uDoc.id
    JOIN users uPat ON a.patient_id = uPat.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$appointmentId]);
$appt = $stmt->fetch();

if (!$appt) {
    set_flash('danger', 'Consultation session not found.');
    redirect(BASE_URL . '/index.php');
}

// Security Check: Verify user is either this appointment's patient, the doctor, or an admin
$isDoctor = ($user['role'] === 'doctor' && $user['id'] == $appt['doctor_user_id']);
$isPatient = ($user['role'] === 'patient' && $user['id'] == $appt['patient_user_id']);
$isAdmin = ($user['role'] === 'admin');

if (!$isDoctor && !$isPatient && !$isAdmin) {
    set_flash('danger', 'Access denied to this consultation room.');
    redirect(BASE_URL . '/index.php');
}

// Security Check for Patient:
// Key Design Decision 3: Reveal video link / allow access ONLY when token is NEXT or SERVING!
if ($isPatient) {
    // Fetch currently serving token for this doctor on this day
    $servingStmt = $pdo->prepare("
        SELECT token_number FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'serving'
        LIMIT 1
    ");
    $servingStmt->execute([$appt['doctor_id'], $appt['appointment_date']]);
    $servingToken = (int)$servingStmt->fetchColumn();

    if (!is_meeting_link_accessible($appt, $servingToken)) {
        set_flash('warning', "Consultation room is not yet active for Token #{$appt['token_number']}. Please wait on the Live Queue board until your turn is called.");
        redirect(BASE_URL . '/patient/queue.php?doctor_id=' . $appt['doctor_id']);
    }
}

// Generate room name from meeting_link or derive safe name
$meetingUrl = $appt['meeting_link'] ?: generate_jitsi_link($appt['id'], $appt['appointment_date']);
$parsedUrl = parse_url($meetingUrl);
$roomName = ltrim($parsedUrl['path'] ?? ('DoctorConnect-Room-' . $appt['id']), '/');

$pageTitle = "Video Consultation - Token #" . $appt['token_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #0f172a;
        }
        .video-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .video-top-bar {
            background: #1e293b;
            color: #fff;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .jitsi-frame {
            flex: 1;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>

<div class="video-container">
    <!-- TOP BAR -->
    <div class="video-top-bar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span class="badge" style="background:#0284c7; color:#fff; font-size: 0.85rem;">
                TOKEN #<?= $appt['token_number'] ?>
            </span>
            <div>
                <strong><?= $isDoctor ? ('Patient: ' . htmlspecialchars($appt['patient_name'])) : ('Doctor: ' . htmlspecialchars($appt['doctor_name'])) ?></strong>
                <span style="font-size:0.8rem; color:#94a3b8; margin-left: 0.5rem;">
                    (<?= htmlspecialchars($appt['specialization']) ?>)
                </span>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a href="<?= htmlspecialchars($meetingUrl) ?>" target="_blank" class="btn btn-secondary btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
                <i class="fas fa-external-link-alt"></i> Open Fullscreen / Tab
            </a>

            <?php if ($isDoctor): ?>
                <a href="<?= BASE_URL ?>/doctor/queue_console.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-prescription"></i> Write Prescription
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/patient/dashboard.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-phone-slash"></i> Leave Consultation
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- JITSI MEET IFRAME -->
    <iframe class="jitsi-frame"
            src="https://<?= JITSI_DEFAULT_DOMAIN ?>/<?= htmlspecialchars($roomName) ?>#userInfo.displayName=<?= urlencode($user['name'] . ($isDoctor ? ' (Doctor)' : ' (Patient)')) ?>"
            allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-write"
            title="Doctor Connect Video Consultation">
    </iframe>
</div>

</body>
</html>
