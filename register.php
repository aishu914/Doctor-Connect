<?php
/**
 * Patient Registration Page
 */

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/patient/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $error = "Invalid security token. Please try again.";
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            try {
                $pdo = Database::getConnection();

                // Check for duplicate email or phone
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
                $chk->execute([$email, $phone]);
                if ($chk->fetch()) {
                    $error = "An account with this email or phone number already exists.";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password_hash, role) 
                        VALUES (?, ?, ?, ?, 'patient')
                    ");
                    $ins->execute([$name, $email, $phone, $hash]);

                    $newUserId = (int)$pdo->lastInsertId();

                    login_user([
                        'id' => $newUserId,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'role' => 'patient'
                    ]);

                    set_flash('success', "Account created successfully! Welcome to DoctorConnect.");
                    redirect(BASE_URL . '/patient/dashboard.php');
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

$pageTitle = "Patient Registration";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 520px; margin: 2rem auto;">
    <div class="card" style="box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="width: 60px; height: 60px; background: var(--secondary-light); color: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.75rem;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Create Patient Account</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                Register to book doctor tokens, track queues, and join video visits
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
                <label class="form-label" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" 
                       placeholder="e.g. John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="password">Password (min 6 chars)</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 0.5rem;">
                <i class="fas fa-check-circle"></i> Complete Registration
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            Already have an account? <a href="<?= BASE_URL ?>/login.php" style="color: var(--primary); font-weight: 600;">Sign in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
