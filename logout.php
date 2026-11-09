<?php
/**
 * Logout script
 */

require_once __DIR__ . '/config/config.php';

logout_user();
set_flash('info', 'You have been successfully logged out.');
redirect(BASE_URL . '/login.php');
