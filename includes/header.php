<?php
/**
 * Global Header Template
 */
require_once __DIR__ . '/../config/config.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?><?= APP_NAME ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Primary Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284c7'><path d='M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5h15c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5h-15z'/><path d='M10.5 4.5C10.5 3.67 11.17 3 12 3s1.5.67 1.5 1.5v15c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5v-15z'/></svg>">
</head>
<body>

<header class="navbar">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>/index.php" class="nav-brand">
            <i class="fas fa-stethoscope"></i>
            <div><?= APP_NAME ?> <span style="font-size:0.75rem; display:block; font-weight:400; color:var(--text-muted); line-height:1;"><?= APP_TAGLINE ?></span></div>
        </a>

        <ul class="nav-links">
            <li><a href="<?= BASE_URL ?>/index.php"><i class="fas fa-home"></i> Home</a></li>
            
            <?php if ($user): ?>
                <?php if ($user['role'] === 'patient'): ?>
                    <li><a href="<?= BASE_URL ?>/patient/dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/patient/book.php"><i class="fas fa-calendar-plus"></i> Book Token</a></li>
                    <li><a href="<?= BASE_URL ?>/patient/queue.php"><i class="fas fa-users-line"></i> Live Queue</a></li>
                <?php elseif ($user['role'] === 'doctor'): ?>
                    <li><a href="<?= BASE_URL ?>/doctor/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/doctor/queue_console.php" style="color:var(--primary); font-weight:700;"><i class="fas fa-headset"></i> Queue Console</a></li>
                    <li><a href="<?= BASE_URL ?>/doctor/schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/reception.php"><i class="fas fa-hospital-user"></i> Reception Desk</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/reports.php"><i class="fas fa-file-invoice"></i> Reports</a></li>
                <?php endif; ?>

                <li>
                    <div class="user-badge">
                        <i class="fas fa-user-circle"></i>
                        <span><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
                        <span class="role-tag"><?= htmlspecialchars($user['role']) ?></span>
                    </div>
                </li>
                <li><a href="<?= BASE_URL ?>/logout.php" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="<?= BASE_URL ?>/login.php" class="btn btn-secondary btn-sm"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<main class="main-wrapper">
    <?= render_flash_messages() ?>
