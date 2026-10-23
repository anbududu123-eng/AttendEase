<?php
/**
 * AttendEase — Enrolled Subjects Management
 * Module 3: View Enrolled Subjects and Browse Available Department Courses
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();
$student = current_user();

$actionMessage = '';
$actionError = '';

// Handle Enrollment / Unenrollment Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $actionError = 'Invalid session token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $subjectId = (int)($_POST['subject_id'] ?? 0);

        if ($action === 'enroll' && $subjectId > 0) {
            try {
                // Verify subject exists and matches student's department & semester
                $checkStmt = $pdo->prepare("SELECT id, subject_name FROM `subjects` WHERE id = :id");
                $checkStmt->execute(['id' => $subjectId]);
                $subj = $checkStmt->fetch();

                if ($subj) {
                    // Check duplicate
                    $dupStmt = $pdo->prepare("SELECT id FROM `student_subjects` WHERE `student_id` = :sid AND `subject_id` = :subjid");
                    $dupStmt->execute(['sid' => $studentId, 'subjid' => $subjectId]);
                    if ($dupStmt->fetch()) {
                        $actionError = 'You are already enrolled in this subject.';
                    } else {
                        // Enroll and initialize attendance record
                        $pdo->beginTransaction();
                        $ins = $pdo->prepare("INSERT INTO `student_subjects` (`student_id`, `subject_id`) VALUES (:sid, :subjid)");
                        $ins->execute(['sid' => $studentId, 'subjid' => $subjectId]);

                        $insAtt = $pdo->prepare("
                            INSERT INTO `attendance_records` (`student_id`, `subject_id`, `classes_conducted`, `classes_attended`)
                            VALUES (:sid, :subjid, 0, 0)
                            ON DUPLICATE KEY UPDATE `classes_conducted` = `classes_conducted`
                        ");
                        $insAtt->execute(['sid' => $studentId, 'subjid' => $subjectId]);
                        $pdo->commit();

                        set_flash('success', 'Successfully enrolled in ' . $subj['subject_name'] . '.');
                        header('Location: subjects.php');
                        exit;
                    }
                } else {
                    $actionError = 'Selected subject does not exist.';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $actionError = 'Error during enrollment: ' . $e->getMessage();
            }
        } elseif ($action === 'unenroll' && $subjectId > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM `student_subjects` WHERE `student_id` = :sid AND `subject_id` = :subjid");
                $del->execute(['sid' => $studentId, 'subjid' => $subjectId]);

                set_flash('success', 'Subject removed from your enrollment.');
                header('Location: subjects.php');
                exit;
            } catch (PDOException $e) {
                $actionError = 'Error during unenrollment: ' . $e->getMessage();
            }
        }
    }
}

// Fetch currently enrolled subjects with attendance summary
$enrolledStmt = $pdo->prepare("
    SELECT 
        s.id AS subject_id,
        s.subject_code,
        s.subject_name,
        s.faculty_name,
        s.required_percentage,
        sem.semester_name,
        d.dept_code,
        COALESCE(ar.classes_conducted, 0) AS classes_conducted,
        COALESCE(ar.classes_attended, 0) AS classes_attended
    FROM `student_subjects` ss
    INNER JOIN `subjects` s ON ss.subject_id = s.id
    INNER JOIN `departments` d ON s.department_id = d.id
    INNER JOIN `semesters` sem ON s.semester_id = sem.id
    LEFT JOIN `attendance_records` ar ON ar.student_id = :sid AND ar.subject_id = s.id
    WHERE ss.student_id = :sid2
    ORDER BY s.subject_code ASC
");
$enrolledStmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
$enrolledSubjects = $enrolledStmt->fetchAll();

// Fetch available subjects for the student's department & semester that are NOT yet enrolled
$availStmt = $pdo->prepare("
    SELECT s.*, sem.semester_name, d.dept_code
    FROM `subjects` s
    INNER JOIN `departments` d ON s.department_id = d.id
    INNER JOIN `semesters` sem ON s.semester_id = sem.id
    WHERE s.department_id = :dept_id 
      AND s.semester_id = :sem_id
      AND s.id NOT IN (
          SELECT subject_id FROM `student_subjects` WHERE student_id = :sid
      )
    ORDER BY s.subject_code ASC
");
$availStmt->execute([
    'dept_id' => (int)($student['department_id'] ?? 0),
    'sem_id'  => (int)($student['semester_id'] ?? 0),
    'sid'     => $studentId,
]);
$availableSubjects = $availStmt->fetchAll();

$page_title = 'My Enrolled Subjects';
$active_nav = 'subjects';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Enrolled Academic Subjects</h3>
            <p class="card-subtitle">Active courses assigned to your current semester study plan</p>
        </div>
        <div class="card-actions">
            <span class="badge badge-slate"><?= count($enrolledSubjects) ?> Subjects Active</span>
        </div>
    </div>

    <?php if ($actionError !== ''): ?>
        <div class="alert alert-error" style="margin: 1.25rem 1.5rem 0;">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content"><?= e($actionError) ?></div>
        </div>
    <?php endif; ?>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($enrolledSubjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128218;</div>
                <h4 class="empty-title">No Enrolled Subjects</h4>
                <p class="empty-description">You have not enrolled in any subjects yet. Review the available courses below to add them to your timetable.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Faculty Name</th>
                            <th>Semester</th>
                            <th style="text-align: center;">Requirement</th>
                            <th style="text-align: center;">Current Attendance</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrolledSubjects as $subj): 
                            $c = (int)$subj['classes_conducted'];
                            $a = (int)$subj['classes_attended'];
                            $req = (float)$subj['required_percentage'];
                            $pct = calculate_percentage($a, $c);
                            $status = get_attendance_status($a, $c, $req);
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: #1e3a8a;"><?= e($subj['subject_code']) ?></td>
                                <td style="font-weight: 600;"><?= e($subj['subject_name']) ?></td>
                                <td><?= e($subj['faculty_name']) ?></td>
                                <td><?= e($subj['semester_name']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= number_format($req, 1) ?>%</td>
                                <td style="text-align: center;">
                                    <?php if ($c > 0): ?>
                                        <span style="font-weight: 700; color: <?= ($status === 'SAFE') ? '#065f46' : '#991b1b' ?>;">
                                            <?= number_format($pct, 2) ?>%
                                        </span>
                                        <div style="font-size: 0.75rem; color: #64748b;"><?= $a ?> / <?= $c ?> attended</div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.8rem;">0 conducted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'SAFE'): ?>
                                        <span class="badge badge-safe">&#10004; SAFE</span>
                                    <?php elseif ($status === 'SHORTAGE'): ?>
                                        <span class="badge badge-shortage">&#9888; SHORTAGE</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">&#9711; NO CLASSES</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="subjects.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="unenroll">
                                        <input type="hidden" name="subject_id" value="<?= (int)$subj['subject_id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #dc2626;">Unenroll</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($availableSubjects)): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Available Courses in <?= e($student['dept_code'] ?? '') ?> - <?= e($student['semester_name'] ?? '') ?></h3>
                <p class="card-subtitle">Additional department subjects available for enrollment</p>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Faculty</th>
                            <th style="text-align: center;">Required %</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableSubjects as $avail): ?>
                            <tr>
                                <td style="font-weight: 700; color: #1e3a8a;"><?= e($avail['subject_code']) ?></td>
                                <td style="font-weight: 600;"><?= e($avail['subject_name']) ?></td>
                                <td><?= e($avail['faculty_name']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= number_format((float)$avail['required_percentage'], 1) ?>%</td>
                                <td style="text-align: right;">
                                    <form method="POST" action="subjects.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="enroll">
                                        <input type="hidden" name="subject_id" value="<?= (int)$avail['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">+ Enroll Subject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
