<?php
/**
 * User Login Page
 */

require_once __DIR__ . '/config/config.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'patient';
    redirect(match($role) {
        'admin' => BASE_URL . '/admin/dashboard.php',
        'doctor' => BASE_URL . '/doctor/queue_console.php',
        default => BASE_URL . '/patient/dashboard.php'
    });
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                set_flash('success', "Welcome back, {$user['name']}!");

                // Check for redirect intent
                $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
                unset($_SESSION['redirect_after_login']);

                if ($redirectUrl) {
                    redirect($redirectUrl);
                }

                // Default role redirect
                redirect(match($user['role']) {
                    'admin' => BASE_URL . '/admin/dashboard.php',
                    'doctor' => BASE_URL . '/doctor/queue_console.php',
                    default => BASE_URL . '/patient/dashboard.php'
                });
            } else {
                $error = "Invalid email address or password.";
            }
        }
    }
}

$pageTitle = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2.5rem auto;">
    <div class="card" style="box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="width: 60px; height: 60px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.75rem;">
                <i class="fas fa-user-lock"></i>
            </div>
            <h2>Sign In to DoctorConnect</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                Access your consultation tokens, live queue, or doctor console
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div style="position: relative;">
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="e.g. john.doe@example.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; font-size: 0.85rem;">
                <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                    <input type="checkbox" name="remember"> Remember this device
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            Don't have a patient account? <a href="<?= BASE_URL ?>/register.php" style="color: var(--primary); font-weight: 600;">Register as Patient</a>
        </div>
    </div>

    <!-- Quick Demo Accounts Helper -->
    <div class="card" style="background: #f8fafc; border-style: dashed; padding: 1rem 1.25rem;">
        <div style="font-size: 0.825rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
            <i class="fas fa-key"></i> Quick Demo Logins:
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('dr.smith@doctorconnect.com', 'doctor123')">
                <i class="fas fa-user-md" style="color:var(--primary);"></i> Doctor (Dr. Alexander Smith)
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('john.doe@example.com', 'patient123')">
                <i class="fas fa-user" style="color:var(--success);"></i> Patient (John Doe)
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('admin@doctorconnect.com', 'admin123')">
                <i class="fas fa-shield-alt" style="color:var(--accent);"></i> Admin / Reception
            </button>
        </div>
    </div>
</div>

<script>
function setDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
