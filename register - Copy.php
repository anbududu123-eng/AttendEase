<?php
/**
 * AttendEase — Student Registration
 * Module 1: Registration with Server-side Validation & Auto Enrollment
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (user_role() === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$pdo = get_db_connection();

// Fetch active departments and semesters for registration dropdowns
$departments = $pdo->query("SELECT * FROM `departments` ORDER BY `dept_name` ASC")->fetchAll();
$semesters   = $pdo->query("SELECT * FROM `semesters` ORDER BY `semester_number` ASC")->fetchAll();

$errors = [];
$name           = trim($_POST['name'] ?? '');
$registerNumber = strtoupper(trim($_POST['register_number'] ?? ''));
$email          = strtolower(trim($_POST['email'] ?? ''));
$departmentId   = (int)($_POST['department_id'] ?? 0);
$yearOfStudy    = (int)($_POST['year_of_study'] ?? 0);
$semesterId     = (int)($_POST['semester_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid form session token. Please try submitting again.';
    }

    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation rules
    if ($name === '') {
        $errors['name'] = 'Full Name is required.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors['name'] = 'Full Name must be between 2 and 100 characters.';
    }

    if ($registerNumber === '') {
        $errors['register_number'] = 'Register Number is required.';
    } elseif (!preg_match('/^[A-Z0-9_-]{3,30}$/i', $registerNumber)) {
        $errors['register_number'] = 'Register number must be 3-30 alphanumeric characters.';
    } else {
        // Unique register number check
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `register_number` = :regno LIMIT 1");
        $stmt->execute(['regno' => $registerNumber]);
        if ($stmt->fetch()) {
            $errors['register_number'] = 'This register number is already registered in the system.';
        }
    }

    if ($email === '') {
        $errors['email'] = 'College Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        // Unique email check
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email address is already associated with an account.';
        }
    }

    if ($departmentId <= 0) {
        $errors['department_id'] = 'Please select your department.';
    }

    if ($yearOfStudy < 1 || $yearOfStudy > 4) {
        $errors['year_of_study'] = 'Please select a valid academic year (1st to 4th Year).';
    }

    if ($semesterId <= 0) {
        $errors['semester_id'] = 'Please select your current semester.';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Process Registration if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insertStmt = $pdo->prepare("
                INSERT INTO `users` (
                    `role`, `name`, `register_number`, `email`, 
                    `password_hash`, `department_id`, `year_of_study`, `semester_id`
                ) VALUES (
                    'student', :name, :regno, :email, 
                    :hash, :dept, :year, :sem
                )
            ");
            $insertStmt->execute([
                'name'  => $name,
                'regno' => $registerNumber,
                'email' => $email,
                'hash'  => $passwordHash,
                'dept'  => $departmentId,
                'year'  => $yearOfStudy,
                'sem'   => $semesterId,
            ]);

            $newStudentId = (int)$pdo->lastInsertId();

            // Automatically enroll the student into existing subjects for this department & semester
            $subjStmt = $pdo->prepare("
                SELECT id FROM `subjects` 
                WHERE `department_id` = :dept AND `semester_id` = :sem
            ");
            $subjStmt->execute(['dept' => $departmentId, 'sem' => $semesterId]);
            $matchingSubjects = $subjStmt->fetchAll();

            $enrollStmt = $pdo->prepare("
                INSERT INTO `student_subjects` (`student_id`, `subject_id`) 
                VALUES (:student_id, :subject_id)
                ON DUPLICATE KEY UPDATE `subject_id` = `subject_id`
            ");

            $attInitStmt = $pdo->prepare("
                INSERT INTO `attendance_records` (`student_id`, `subject_id`, `classes_conducted`, `classes_attended`)
                VALUES (:student_id, :subject_id, 0, 0)
                ON DUPLICATE KEY UPDATE `classes_conducted` = `classes_conducted`
            ");

            foreach ($matchingSubjects as $subj) {
                $enrollStmt->execute([
                    'student_id' => $newStudentId,
                    'subject_id' => (int)$subj['id'],
                ]);
                $attInitStmt->execute([
                    'student_id' => $newStudentId,
                    'subject_id' => (int)$subj['id'],
                ]);
            }

            $pdo->commit();

            set_flash('success', 'Registration successful! You have been enrolled in your semester courses. Please sign in to proceed.');
            header('Location: login.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['system'] = 'Database transaction failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Student Registration';
$is_auth_page = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container auth-container-wide">
    <div class="auth-brand">
        <div class="brand-logo">A</div>
        <h1 class="brand-name">Create Student Account</h1>
        <p class="brand-tagline">Register to track your attendance and monitor shortage risk</p>
    </div>

    <?php if (!empty($errors['system'])): ?>
        <div class="alert alert-error">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content"><?= e($errors['system']) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['csrf'])): ?>
        <div class="alert alert-error">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content"><?= e($errors['csrf']) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="col">
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" 
                           placeholder="e.g. Alex Johnson" value="<?= e($name) ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="form-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col">
                <div class="form-group">
                    <label for="register_number">Register Number <span class="required">*</span></label>
                    <input type="text" id="register_number" name="register_number" class="form-control" 
                           placeholder="e.g. REG2026CS105" value="<?= e($registerNumber) ?>" required>
                    <?php if (isset($errors['register_number'])): ?>
                        <div class="form-error"><?= e($errors['register_number']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="email">College Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-control" 
                   placeholder="e.g. alex@college.edu" value="<?= e($email) ?>" required>
            <?php if (isset($errors['email'])): ?>
                <div class="form-error"><?= e($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="col">
                <div class="form-group">
                    <label for="department_id">Department / Branch <span class="required">*</span></label>
                    <select id="department_id" name="department_id" class="form-control" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int)$dept['id'] ?>" <?= ($departmentId === (int)$dept['id']) ? 'selected' : '' ?>>
                                <?= e($dept['dept_code']) ?> &mdash; <?= e($dept['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['department_id'])): ?>
                        <div class="form-error"><?= e($errors['department_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col">
                <div class="form-group">
                    <label for="year_of_study">Year of Study <span class="required">*</span></label>
                    <select id="year_of_study" name="year_of_study" class="form-control" required>
                        <option value="">-- Select Year --</option>
                        <option value="1" <?= ($yearOfStudy === 1) ? 'selected' : '' ?>>1st Year (Freshman)</option>
                        <option value="2" <?= ($yearOfStudy === 2) ? 'selected' : '' ?>>2nd Year (Sophomore)</option>
                        <option value="3" <?= ($yearOfStudy === 3) ? 'selected' : '' ?>>3rd Year (Junior)</option>
                        <option value="4" <?= ($yearOfStudy === 4) ? 'selected' : '' ?>>4th Year (Senior)</option>
                    </select>
                    <?php if (isset($errors['year_of_study'])): ?>
                        <div class="form-error"><?= e($errors['year_of_study']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col">
                <div class="form-group">
                    <label for="semester_id">Semester <span class="required">*</span></label>
                    <select id="semester_id" name="semester_id" class="form-control" required>
                        <option value="">-- Select Semester --</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= (int)$sem['id'] ?>" <?= ($semesterId === (int)$sem['id']) ? 'selected' : '' ?>>
                                <?= e($sem['semester_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['semester_id'])): ?>
                        <div class="form-error"><?= e($errors['semester_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="col">
                <div class="form-group">
                    <label for="password">Create Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="At least 6 characters" required>
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col">
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Re-enter password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="form-error"><?= e($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.25rem;">
            <button type="submit" class="btn btn-primary btn-block">Complete Registration</button>
        </div>
    </form>

    <div class="auth-footer-links">
        Already registered? <a href="login.php">Sign In Here</a> &bull; <a href="index.php">Home</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
