<?php
/**
 * AttendEase — Authentication & Login
 * Module 1: Secure Login with Session Handling and Role-based Redirection
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (user_role() === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$error = '';
$identifier = trim($_POST['identifier'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form session token. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';

        if ($identifier === '' || $password === '') {
            $error = 'Please enter both your login identifier and password.';
        } else {
            try {
                $pdo = get_db_connection();
                // Match either college email OR register number
                $stmt = $pdo->prepare("
                    SELECT u.*, d.dept_name, d.dept_code, s.semester_name 
                    FROM `users` u
                    LEFT JOIN `departments` d ON u.department_id = d.id
                    LEFT JOIN `semesters` s ON u.semester_id = s.id
                    WHERE u.email = :ident1 OR u.register_number = :ident2
                    LIMIT 1
                ");
                $stmt->execute([
                    'ident1' => $identifier,
                    'ident2' => strtoupper($identifier),
                ]);
                $user = $stmt->fetch();

                if ($user && verify_user_password($password, $user['password_hash'], (int)$user['id'], (string)$user['email'])) {
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . $user['name'] . '!');

                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid credentials. Please verify your email / register number and password.';
                }
            } catch (PDOException $e) {
                $error = 'Database error during authentication: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Account Login';
$is_auth_page = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-brand">
        <div class="brand-logo">A</div>
        <h1 class="brand-name">AttendEase</h1>
        <p class="brand-tagline">Sign in to your academic attendance portal</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content"><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="identifier">College Email or Register Number <span class="required">*</span></label>
            <input type="text" id="identifier" name="identifier" class="form-control" 
                   placeholder="e.g. student@attendease.edu or REG2026CS101" 
                   value="<?= e($identifier) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-control" 
                   placeholder="Enter your password" required>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </div>
    </form>

    <div class="card" style="margin-top: 1.5rem; margin-bottom: 0; background-color: #f8fafc; border-style: dashed;">
        <div class="card-body" style="padding: 0.85rem 1rem;">
            <div style="font-size: 0.775rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.35rem;">
                &#128273; Quick Sign-in Credentials:
            </div>
            <div style="font-size: 0.775rem; color: #475569; line-height: 1.6;">
                <div><strong>Admin:</strong> <code>admin@attendease.edu</code> / <code>AdminPassword123!</code></div>
                <div><strong>Student:</strong> <code>student@attendease.edu</code> / <code>StudentPassword123!</code></div>
            </div>
        </div>
    </div>

    <div class="auth-footer-links">
        New Student? <a href="register.php">Create an Account</a> &bull; <a href="index.php">Return Home</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
