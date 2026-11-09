<?php
/**
 * Authentication and Access Control
 */

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'patient'
    ];
}

function has_role(string|array $roles): bool {
    if (!is_logged_in()) return false;
    $currentRole = $_SESSION['user_role'] ?? '';
    if (is_array($roles)) {
        return in_array($currentRole, $roles);
    }
    return $currentRole === $roles;
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please login to continue.');
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(BASE_URL . '/login.php');
    }
}

function require_role(string|array $roles): void {
    require_login();
    if (!has_role($roles)) {
        set_flash('danger', 'Unauthorized access! You do not have permission for this section.');
        // Redirect according to actual role
        $role = $_SESSION['user_role'] ?? 'patient';
        if ($role === 'doctor') {
            redirect(BASE_URL . '/doctor/dashboard.php');
        } elseif ($role === 'admin') {
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            redirect(BASE_URL . '/patient/dashboard.php');
        }
    }
}

function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_phone'] = $user['phone'];
    $_SESSION['user_role'] = $user['role'];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
