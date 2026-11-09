<?php
/**
 * Patient Profile Management
 */

require_once __DIR__ . '/../config/config.php';
require_role('patient');

$user = current_user();
$pdo = Database::getConnection();

// Fetch fresh user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$patient = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newPass = $_POST['new_password'] ?? '';

        if (empty($name) || empty($phone)) {
            set_flash('danger', 'Name and phone are required.');
        } else {
            // Check duplicate phone for other users
            $chk = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
            $chk->execute([$phone, $user['id']]);
            if ($chk->fetch()) {
                set_flash('danger', 'This phone number is already registered by another account.');
            } else {
                if (!empty($newPass)) {
                    if (strlen($newPass) < 6) {
                        set_flash('danger', 'New password must be at least 6 characters.');
                    } else {
                        $hash = password_hash($newPass, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?");
                        $upd->execute([$name, $phone, $hash, $user['id']]);
                        set_flash('success', 'Profile and password updated successfully.');
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                    $upd->execute([$name, $phone, $user['id']]);
                    set_flash('success', 'Profile updated successfully.');
                }
                $_SESSION['user_name'] = $name;
                $_SESSION['user_phone'] = $phone;
                redirect(BASE_URL . '/patient/profile.php');
            }
        }
    }
}

$pageTitle = "My Profile";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 550px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-circle" style="color:var(--primary);"></i> Patient Profile</h3>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($patient['email']) ?>" readonly style="background:#f1f5f9;">
                <small style="color:var(--text-muted);">Email address cannot be changed.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($patient['name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($patient['phone']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Change Password (Leave blank to keep existing)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="••••••••">
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
