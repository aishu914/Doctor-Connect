<?php
/**
 * Book Token Page (Patient)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';
require_role('patient');

$user = current_user();
$pdo = Database::getConnection();

$selectedDoctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;
$today = date('Y-m-d');
$bookingResult = null;
$error = null;

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = "Session expired or invalid security token. Please try again.";
    } else {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $date = trim($_POST['appointment_date'] ?? '');
        $reason = trim($_POST['reason_for_visit'] ?? '');

        if (!$doctorId || !$date) {
            $error = "Please select both a doctor and appointment date.";
        } else {
            $bookingResult = TokenManager::bookToken($user['id'], $doctorId, $date, $reason);
            if (!$bookingResult['success']) {
                $error = $bookingResult['message'];
            }
        }
    }
}

// Fetch all available doctors for dropdown
$doctors = $pdo->query("
    SELECT d.id, u.name, d.specialization, d.consultation_fee, d.avg_consultation_mins
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.is_active = 1
    ORDER BY u.name ASC
")->fetchAll();

$pageTitle = "Book Doctor Token";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 650px; margin: 1.5rem auto;">
    <?php if ($bookingResult && $bookingResult['success']): ?>
        <!-- TOKEN BOOKED SUCCESS CARD -->
        <div class="card" style="border: 2px solid var(--success); text-align: center; padding: 2.5rem 1.5rem; box-shadow: var(--shadow-lg);">
            <div style="width: 70px; height: 70px; background: var(--success-light); color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2.25rem;">
                <i class="fas fa-check"></i>
            </div>

            <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.35rem 1rem;">BOOKING CONFIRMED</span>
            <h2 style="font-size: 2.25rem; margin: 0.75rem 0 0.25rem;">Token #<?= $bookingResult['token_number'] ?></h2>
            <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 1.5rem;">
                Consultation with <strong><?= htmlspecialchars($bookingResult['doctor_name']) ?></strong>
            </p>

            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 1.25rem; max-width: 450px; margin: 0 auto 1.75rem; text-align: left; font-size: 0.95rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem;">
                    <span style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> Date:</span>
                    <strong><?= format_date($_POST['appointment_date']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem;">
                    <span style="color: var(--text-muted);"><i class="far fa-clock"></i> Estimated Meeting:</span>
                    <strong style="color: var(--primary);"><?= date('h:i A', strtotime($bookingResult['estimated_time'])) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);"><i class="fas fa-video"></i> Video Meeting:</span>
                    <span style="font-size: 0.85rem; color: #15803d; font-weight:600;">Activates when turn arrives</span>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/patient/queue.php?doctor_id=<?= (int)$_POST['doctor_id'] ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-tv"></i> Go to Live Queue Board
                </a>
                <a href="<?= BASE_URL ?>/patient/dashboard.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-th-large"></i> Back to Dashboard
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- BOOKING FORM -->
        <div class="card" style="box-shadow: var(--shadow-lg);">
            <div class="card-header">
                <div>
                    <h2 class="card-title" style="font-size: 1.4rem;"><i class="fas fa-calendar-plus" style="color:var(--primary);"></i> Book Doctor Consultation Token</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                        Select doctor and date to generate your race-safe token number
                    </p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="bookingDoctorSelect">Choose Doctor</label>
                    <select name="doctor_id" id="bookingDoctorSelect" class="form-control" required>
                        <option value="">-- Select a Doctor --</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($selectedDoctorId == $d['id'] || (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $d['id'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?> - <?= htmlspecialchars($d['specialization']) ?> (<?= CURRENCY_SYMBOL ?><?= number_format($d['consultation_fee'], 0) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bookingDateInput">Appointment Date</label>
                    <input type="date" name="appointment_date" id="bookingDateInput" class="form-control" 
                           min="<?= $today ?>" value="<?= htmlspecialchars($_POST['appointment_date'] ?? $today) ?>" required>
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Tokens are generated for today or upcoming dates.</small>
                </div>

                <!-- Dynamic slot & timing information container -->
                <div id="slotInfoBox" style="margin-bottom: 1.25rem;">
                    <!-- Populated dynamically via assets/js/main.js -->
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason_for_visit">Reason for Visit / Symptoms (Optional)</label>
                    <textarea name="reason_for_visit" id="reason_for_visit" class="form-control" rows="3" 
                              placeholder="Describe your symptoms, health concerns, or consultation reason..."><?= htmlspecialchars($_POST['reason_for_visit'] ?? '') ?></textarea>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <a href="<?= BASE_URL ?>/patient/dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" id="bookSubmitBtn" class="btn btn-primary btn-lg">
                        <i class="fas fa-ticket-alt"></i> Confirm & Book Token
                    </button>
                </div>
            </form>
        </div>

    <?php endif; ?>
</div>

<script>
// Trigger initial slot check if doctor is already selected
document.addEventListener('DOMContentLoaded', () => {
    const docSelect = document.getElementById('bookingDoctorSelect');
    if (docSelect && docSelect.value) {
        docSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
