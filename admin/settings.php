<?php
/**
 * Admin: Clinic Settings
 */

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $settings = $_POST['settings'] ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO clinic_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($settings as $key => $val) {
            $stmt->execute([$key, trim($val)]);
        }

        set_flash('success', 'Clinic configuration updated successfully!');
        redirect(BASE_URL . '/admin/settings.php');
    }
}

// Fetch existing settings
$rawSettings = $pdo->query("SELECT setting_key, setting_value FROM clinic_settings")->fetchAll();
$settings = [];
foreach ($rawSettings as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = "Clinic Settings";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-sliders-h" style="color:var(--primary);"></i> Clinic Profile & Global Settings</h1>
        <p class="page-subtitle">Configure clinic branding, cancellation policy, and telemedicine defaults</p>
    </div>
</div>

<div style="max-width: 650px; margin: 0 auto;">
    <div class="card">
        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Clinic Name</label>
                <input type="text" name="settings[clinic_name]" class="form-control" 
                       value="<?= htmlspecialchars($settings['clinic_name'] ?? 'DoctorConnect Clinic') ?>" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Clinic Contact Phone</label>
                    <input type="text" name="settings[clinic_phone]" class="form-control" 
                           value="<?= htmlspecialchars($settings['clinic_phone'] ?? '+1 (555) 234-5678') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Clinic Support Email</label>
                    <input type="email" name="settings[clinic_email]" class="form-control" 
                           value="<?= htmlspecialchars($settings['clinic_email'] ?? 'care@doctorconnect.com') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Clinic Physical Address</label>
                <textarea name="settings[clinic_address]" class="form-control" rows="2"><?= htmlspecialchars($settings['clinic_address'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Cancellation Cutoff (Hours)</label>
                    <input type="number" min="0" max="24" name="settings[cancellation_cutoff_hours]" class="form-control" 
                           value="<?= htmlspecialchars($settings['cancellation_cutoff_hours'] ?? '2') ?>">
                    <small style="color:var(--text-muted);">Patients cannot cancel within this many hours of estimated time.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Jitsi Meet Domain</label>
                    <input type="text" name="settings[jitsi_domain]" class="form-control" 
                           value="<?= htmlspecialchars($settings['jitsi_domain'] ?? 'meet.jit.si') ?>">
                </div>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
