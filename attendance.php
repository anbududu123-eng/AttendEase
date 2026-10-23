<?php
/**
 * AttendEase — Attendance Entry & Log Management
 * Module 4: Record and Update Classes Conducted and Attended
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();

$errors = [];
$successMsg = '';

// Handle Attendance Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session security token. Please try again.';
    } else {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $conducted = trim($_POST['classes_conducted'] ?? '');
        $attended  = trim($_POST['classes_attended'] ?? '');

        // Validation
        if (!ctype_digit($conducted) || !ctype_digit($attended)) {
            $errors[] = 'Classes conducted and attended must be positive integers (0 or greater).';
        } else {
            $c = (int)$conducted;
            $a = (int)$attended;

            if ($c < 0 || $a < 0) {
                $errors[] = 'Negative values are not permitted for attendance entries.';
            } elseif ($a > $c) {
                $errors[] = "Validation Failed: Classes attended ($a) cannot exceed classes conducted ($c).";
            } else {
                // Ensure student is actually enrolled in this subject
                $chk = $pdo->prepare("SELECT id FROM `student_subjects` WHERE `student_id` = :sid AND `subject_id` = :subjid");
                $chk->execute(['sid' => $studentId, 'subjid' => $subjectId]);
                if (!$chk->fetch()) {
                    $errors[] = 'You are not authorized to update attendance for this course.';
                } else {
                    try {
                        $upd = $pdo->prepare("
                            INSERT INTO `attendance_records` (
                                `student_id`, `subject_id`, `classes_conducted`, `classes_attended`, `last_updated`
                            ) VALUES (
                                :sid, :subjid, :c, :a, NOW()
                            )
                            ON DUPLICATE KEY UPDATE 
                                `classes_conducted` = :c2,
                                `classes_attended` = :a2,
                                `last_updated` = NOW()
                        ");
                        $upd->execute([
                            'sid'    => $studentId,
                            'subjid' => $subjectId,
                            'c'      => $c,
                            'a'      => $a,
                            'c2'     => $c,
                            'a2'     => $a,
                        ]);

                        set_flash('success', 'Attendance records updated successfully.');
                        header('Location: attendance.php');
                        exit;
                    } catch (PDOException $e) {
                        $errors[] = 'Database error while saving attendance: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch all enrolled subjects and current attendance
$stmt = $pdo->prepare("
    SELECT 
        s.id AS subject_id,
        s.subject_code,
        s.subject_name,
        s.faculty_name,
        s.required_percentage,
        COALESCE(ar.classes_conducted, 0) AS classes_conducted,
        COALESCE(ar.classes_attended, 0) AS classes_attended,
        ar.last_updated
    FROM `student_subjects` ss
    INNER JOIN `subjects` s ON ss.subject_id = s.id
    LEFT JOIN `attendance_records` ar ON ar.student_id = :sid AND ar.subject_id = s.id
    WHERE ss.student_id = :sid2
    ORDER BY s.subject_code ASC
");
$stmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
$subjects = $stmt->fetchAll();

// Check if a specific subject was targeted for editing
$editSubjectId = (int)($_GET['edit'] ?? 0);

$page_title = 'Log Attendance';
$active_nav = 'attendance';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Record & Update Subject Attendance</h3>
            <p class="card-subtitle">Keep your class log accurate to ensure precise shortage calculations</p>
        </div>
        <div class="card-actions">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin: 1.25rem 1.5rem 0;">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128221;</div>
                <h4 class="empty-title">No Subjects Enrolled</h4>
                <p class="empty-description">You must enroll in courses before logging attendance numbers.</p>
                <a href="subjects.php" class="btn btn-primary">Enroll in Courses</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject Info</th>
                            <th style="text-align: center; width: 140px;">Conducted</th>
                            <th style="text-align: center; width: 140px;">Attended</th>
                            <th style="text-align: center;">Absent</th>
                            <th style="text-align: center;">Percentage</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 160px;">Update Log</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $row): 
                            $c = (int)$row['classes_conducted'];
                            $a = (int)$row['classes_attended'];
                            $req = (float)$row['required_percentage'];
                            $absent = max(0, $c - $a);
                            $pct = calculate_percentage($a, $c);
                            $status = get_attendance_status($a, $c, $req);
                            $isEditing = ($editSubjectId === (int)$row['subject_id']);
                        ?>
                            <tr class="<?= ($status === 'SHORTAGE') ? 'row-shortage' : '' ?>">
                                <td>
                                    <div class="subject-info">
                                        <span class="subject-name"><?= e($row['subject_name']) ?></span>
                                        <span class="subject-meta">
                                            <strong><?= e($row['subject_code']) ?></strong> &bull; <?= e($row['faculty_name']) ?> &bull; Min Req: <?= number_format($req, 0) ?>%
                                        </span>
                                    </div>
                                </td>

                                <?php if ($isEditing): ?>
                                    <!-- In-line Edit Form -->
                                    <td colspan="6" style="background-color: #f8fafc; padding: 1rem;">
                                        <form method="POST" action="attendance.php" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 1rem;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="subject_id" value="<?= (int)$row['subject_id'] ?>">

                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <label for="c_<?= $row['subject_id'] ?>" style="font-size: 0.825rem; font-weight: 600;">Conducted:</label>
                                                <input type="number" id="c_<?= $row['subject_id'] ?>" name="classes_conducted" 
                                                       class="form-control" style="width: 90px; text-align: center;" 
                                                       value="<?= $c ?>" min="0" required>
                                            </div>

                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <label for="a_<?= $row['subject_id'] ?>" style="font-size: 0.825rem; font-weight: 600;">Attended:</label>
                                                <input type="number" id="a_<?= $row['subject_id'] ?>" name="classes_attended" 
                                                       class="form-control" style="width: 90px; text-align: center;" 
                                                       value="<?= $a ?>" min="0" required>
                                            </div>

                                            <div style="display: flex; gap: 0.5rem;">
                                                <button type="submit" class="btn btn-success btn-sm">&#10004; Save</button>
                                                <a href="attendance.php" class="btn btn-secondary btn-sm">Cancel</a>
                                            </div>
                                        </form>
                                    </td>
                                <?php else: ?>
                                    <td style="text-align: center; font-weight: 700; font-size: 1rem;"><?= $c ?></td>
                                    <td style="text-align: center; font-weight: 700; font-size: 1rem; color: #065f46;"><?= $a ?></td>
                                    <td style="text-align: center; font-weight: 600; color: #991b1b;"><?= $absent ?></td>
                                    <td style="text-align: center;">
                                        <span style="font-weight: 800; font-size: 1.05rem; color: <?= ($status === 'SAFE') ? '#065f46' : (($status === 'SHORTAGE') ? '#991b1b' : '#64748b') ?>;">
                                            <?= number_format($pct, 2) ?>%
                                        </span>
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
                                        <a href="attendance.php?edit=<?= (int)$row['subject_id'] ?>" class="btn btn-secondary btn-sm">
                                            &#9997; Edit Values
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="background-color: #f8fafc; border-style: dashed;">
    <div class="card-body">
        <h4 style="font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">
            &#128161; Attendance Logging Guidelines:
        </h4>
        <ul style="font-size: 0.825rem; color: #64748b; padding-left: 1.25rem; line-height: 1.6;">
            <li><strong>Classes Conducted:</strong> The total number of lecture/lab hours officially completed by the faculty to date.</li>
            <li><strong>Classes Attended:</strong> The number of completed classes in which you were marked present.</li>
            <li><strong>Constraint:</strong> Classes attended cannot be greater than classes conducted. Neither value can be negative.</li>
            <li><strong>Real-Time Sync:</strong> Saving attendance here immediately re-calculates your shortage statistics and recovery requirements across all dashboard tools.</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
