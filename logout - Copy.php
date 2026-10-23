<?php
/**
 * AttendEase — Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();

set_flash('success', 'You have been signed out successfully.');
header('Location: login.php');
exit;
