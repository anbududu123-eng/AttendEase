<?php
/**
 * AttendEase — Student Attendance and Shortage Calculator
 * Landing & Gateway Page
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (user_role() === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$page_title = 'Welcome to AttendEase';
$is_auth_page = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container auth-container-wide">
    <div class="auth-brand">
        <div class="brand-logo">A</div>
        <h1 class="brand-name">AttendEase</h1>
        <p class="brand-tagline">Student Attendance & Shortage Calculator</p>
    </div>

    <div class="alert alert-info">
        <span class="alert-icon">&#8505;</span>
        <div class="alert-content">
            <div class="alert-title">Academic Attendance Management System</div>
            Calculate subject-wise attendance percentages, monitor shortage thresholds, simulate future attendance outcomes, and determine exactly how many consecutive classes you must attend to satisfy college regulations.
        </div>
    </div>

    <div class="form-row" style="margin-bottom: 1.5rem;">
        <div class="col">
            <div class="card" style="margin-bottom: 0; height: 100%; display: flex; flex-direction: column;">
                <div class="card-header">
                    <h3 class="card-title">&#127891; Student Access</h3>
                </div>
                <div class="card-body" style="flex: 1;">
                    <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.25rem;">
                        View enrolled courses, record attended classes, check attendance shortages, and simulate what-if scenarios.
                    </p>
                    <a href="login.php" class="btn btn-primary btn-block" style="margin-bottom: 0.5rem;">Student Sign In</a>
                    <a href="register.php" class="btn btn-secondary btn-block">New Student Registration</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card" style="margin-bottom: 0; height: 100%; display: flex; flex-direction: column;">
                <div class="card-header">
                    <h3 class="card-title">&#128737; Administrator Portal</h3>
                </div>
                <div class="card-body" style="flex: 1;">
                    <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.25rem;">
                        Manage departments, subjects, students, attendance criteria, and monitor campus-wide attendance shortages.
                    </p>
                    <a href="login.php" class="btn btn-secondary btn-block">Administrator Login</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="background-color: #f8fafc; border-style: dashed; margin-bottom: 0;">
        <div class="card-body" style="padding: 1rem 1.25rem;">
            <h4 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 0.5rem;">
                &#128273; Preloaded Demo Credentials:
            </h4>
            <div style="font-size: 0.825rem; color: #334155; line-height: 1.6;">
                <div><strong>Admin:</strong> <code>admin@attendease.edu</code> &bull; Password: <code>AdminPassword123!</code></div>
                <div><strong>Student (Mixed Attendance):</strong> <code>student@attendease.edu</code> &bull; Password: <code>StudentPassword123!</code> (Alex Johnson)</div>
                <div><strong>Student (Critical Shortage):</strong> <code>priya@attendease.edu</code> &bull; Password: <code>StudentPassword123!</code> (Priya Sharma)</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
