<?php
/**
 * AttendEase — Attendance What-If Calculator
 * Module 7: Hypothetical Attendance Planning Simulator (Pure PHP Calculation)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();

// Fetch enrolled subjects
$stmt = $pdo->prepare("
    SELECT 
        s.id AS subject_id,
        s.subject_code,
        s.subject_name,
        s.faculty_name,
        s.required_percentage,
        COALESCE(ar.classes_conducted, 0) AS classes_conducted,
        COALESCE(ar.classes_attended, 0) AS classes_attended
    FROM `student_subjects` ss
    INNER JOIN `subjects` s ON ss.subject_id = s.id
    LEFT JOIN `attendance_records` ar ON ar.student_id = :sid AND ar.subject_id = s.id
    WHERE ss.student_id = :sid2
    ORDER BY s.subject_code ASC
");
$stmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
$subjects = $stmt->fetchAll();

$errors = [];
$simulationResult = null;
$selectedSubjectId = (int)($_POST['subject_id'] ?? ($_GET['subject_id'] ?? 0));
$futureAttend = trim($_POST['future_attend'] ?? '5');
$futureMiss   = trim($_POST['future_miss'] ?? '0');

if ($selectedSubjectId === 0 && !empty($subjects)) {
    $selectedSubjectId = (int)$subjects[0]['subject_id'];
}

// Find selected subject
$selectedSubject = null;
foreach ($subjects as $s) {
    if ((int)$s['subject_id'] === $selectedSubjectId) {
        $selectedSubject = $s;
        break;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security session token. Please try again.';
    } elseif (!$selectedSubject) {
        $errors[] = 'Please select a valid subject for simulation.';
    } else {
        if (!ctype_digit($futureAttend) || !ctype_digit($futureMiss)) {
            $errors[] = 'Future classes to attend and miss must be positive whole numbers (0 or greater).';
        } else {
            $futA = (int)$futureAttend;
            $futM = (int)$futureMiss;

            if ($futA < 0 || $futM < 0) {
                $errors[] = 'Values cannot be negative.';
            } elseif ($futA === 0 && $futM === 0) {
                $errors[] = 'Please enter at least 1 future class to attend or miss for a meaningful simulation.';
            } else {
                // Perform pure PHP calculation without modifying the database
                $currentA = (int)$selectedSubject['classes_attended'];
                $currentC = (int)$selectedSubject['classes_conducted'];
                $reqPct   = (float)$selectedSubject['required_percentage'];

                $simulationResult = calculate_what_if($currentA, $currentC, $futA, $futM, $reqPct);
            }
        }
    }
}

$page_title = 'What-If Attendance Simulator';
$active_nav = 'what_if';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Attendance What-If Calculator</h3>
            <p class="card-subtitle">Simulate future attendance percentages without altering your official records</p>
        </div>
        <div class="card-actions">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
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

    <div class="card-body">
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#129300;</div>
                <h4 class="empty-title">No Subjects Available</h4>
                <p class="empty-description">Enroll in subjects before simulating what-if scenarios.</p>
                <a href="subjects.php" class="btn btn-primary">Enroll in Subjects</a>
            </div>
        <?php else: ?>
            <form method="POST" action="what_if_calculator.php">
                <?= csrf_field() ?>

                <div class="form-row">
                    <div class="col" style="flex: 2;">
                        <div class="form-group">
                            <label for="subject_id">Select Subject <span class="required">*</span></label>
                            <select id="subject_id" name="subject_id" class="form-control" required>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= (int)$s['subject_id'] ?>" <?= ((int)$s['subject_id'] === $selectedSubjectId) ? 'selected' : '' ?>>
                                        <?= e($s['subject_code']) ?> &mdash; <?= e($s['subject_name']) ?> (Current: <?= number_format(calculate_percentage((int)$s['classes_attended'], (int)$s['classes_conducted']), 2) ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">Current requirement: <strong><?= number_format((float)($selectedSubject['required_percentage'] ?? 75), 1) ?>%</strong></div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label for="future_attend">Classes Planned to Attend <span class="required">*</span></label>
                            <input type="number" id="future_attend" name="future_attend" class="form-control" 
                                   value="<?= e($futureAttend) ?>" min="0" required>
                            <div class="form-help">Upcoming lectures you will be present for</div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label for="future_miss">Classes Planned to Miss <span class="required">*</span></label>
                            <input type="number" id="future_miss" name="future_miss" class="form-control" 
                                   value="<?= e($futureMiss) ?>" min="0" required>
                            <div class="form-help">Upcoming lectures you anticipate missing</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 0.5rem;">
                    <button type="submit" class="btn btn-primary">&#128640; Simulate Attendance Outcome</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($simulationResult && $selectedSubject): 
    $res = $simulationResult;
    $isProjSafe = ($res['projected_status'] === 'SAFE');
?>
    <!-- Simulation Projections Display Card -->
    <div class="card" style="border-left: 4px solid <?= $isProjSafe ? '#10b981' : '#ef4444' ?>;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Simulation Results &mdash; <?= e($selectedSubject['subject_name']) ?> (<?= e($selectedSubject['subject_code']) ?>)</h3>
                <p class="card-subtitle">Hypothetical outcome if you attend <?= $res['future_attended'] ?> and miss <?= $res['future_missed'] ?> future classes</p>
            </div>
            <div class="card-actions">
                <?php if ($res['percentage_change'] >= 0): ?>
                    <span class="badge badge-safe">+<?= number_format($res['percentage_change'], 2) ?>% Percentage Gain</span>
                <?php else: ?>
                    <span class="badge badge-shortage"><?= number_format($res['percentage_change'], 2) ?>% Percentage Drop</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <div class="projection-comparison">
                <!-- Current Actual Standing -->
                <div class="proj-card">
                    <div class="proj-label">Current Official Record</div>
                    <div class="proj-val" style="color: #0f172a;">
                        <?= number_format($res['current_percentage'], 2) ?>%
                    </div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; line-height: 1.6;">
                        <div>Classes Conducted: <strong><?= $res['current_conducted'] ?></strong></div>
                        <div>Classes Attended: <strong><?= $res['current_attended'] ?></strong></div>
                        <div>Classes Absent: <strong><?= max(0, $res['current_conducted'] - $res['current_attended']) ?></strong></div>
                    </div>
                </div>

                <!-- Projected Hypothetical Standing -->
                <div class="proj-card" style="border-color: <?= $isProjSafe ? '#a7f3d0' : '#fecaca' ?>; background-color: <?= $isProjSafe ? '#f0fdf4' : '#fff5f5' ?>;">
                    <div class="proj-label" style="color: <?= $isProjSafe ? '#065f46' : '#991b1b' ?>;">
                        Projected Future Standing
                    </div>
                    <div class="proj-val" style="color: <?= $isProjSafe ? '#065f46' : '#991b1b' ?>;">
                        <?= number_format($res['projected_percentage'], 2) ?>%
                    </div>
                    <div style="font-size: 0.85rem; color: #334155; margin-top: 0.5rem; line-height: 1.6;">
                        <div>Projected Conducted: <strong><?= $res['projected_conducted'] ?></strong> (+<?= ($res['future_attended'] + $res['future_missed']) ?>)</div>
                        <div>Projected Attended: <strong><?= $res['projected_attended'] ?></strong> (+<?= $res['future_attended'] ?>)</div>
                        <div>Projected Absent: <strong><?= $res['projected_absent'] ?></strong> (+<?= $res['future_missed'] ?>)</div>
                    </div>
                </div>
            </div>

            <!-- Status Banner & Recommendation -->
            <div style="margin-top: 1.5rem; padding: 1.25rem; border-radius: 8px; background-color: <?= $isProjSafe ? '#ecfdf5' : '#fef2f2' ?>; border: 1px solid <?= $isProjSafe ? '#a7f3d0' : '#fecaca' ?>;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="font-weight: 800; font-size: 1.05rem; color: <?= $isProjSafe ? '#065f46' : '#991b1b' ?>;">
                            Projected Status: <?= $res['projected_status'] ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #334155; margin-top: 0.25rem;">
                            <?php if ($isProjSafe): ?>
                                &#10004; Excellent! Under this scenario, your attendance would remain at <strong><?= number_format($res['projected_percentage'], 2) ?>%</strong>, which satisfies the required <?= number_format($res['required_percentage'], 1) ?>% threshold.
                            <?php else: ?>
                                &#9888; Warning! Under this scenario, your attendance would be <strong><?= number_format($res['projected_percentage'], 2) ?>%</strong>, falling below the mandatory <?= number_format($res['required_percentage'], 1) ?>% college requirement.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge <?= $isProjSafe ? 'badge-safe' : 'badge-shortage' ?>">
                            <?= $isProjSafe ? 'Compliant' : 'Shortage Alert' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer" style="background-color: #f8fafc;">
            <em>&#128161; Note: This calculation is purely exploratory. No permanent records in the MySQL database have been changed.</em>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
