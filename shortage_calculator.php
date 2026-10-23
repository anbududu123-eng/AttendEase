<?php
/**
 * AttendEase — Attendance Shortage Calculator
 * Module 6: Minimum Consecutive Classes Calculator & Buffer Missable Calculator
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();

// Fetch all enrolled subjects with attendance records
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

// Determine selected subject
$selectedSubjectId = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
if ($selectedSubjectId === 0 && !empty($subjects)) {
    // Default to the first subject that has a shortage, or first subject
    foreach ($subjects as $s) {
        $c = (int)$s['classes_conducted'];
        $a = (int)$s['classes_attended'];
        $req = (float)$s['required_percentage'];
        if ($c > 0 && calculate_percentage($a, $c) < $req) {
            $selectedSubjectId = (int)$s['subject_id'];
            break;
        }
    }
    if ($selectedSubjectId === 0) {
        $selectedSubjectId = (int)$subjects[0]['subject_id'];
    }
}

$activeSubject = null;
$activeCalc = null;

foreach ($subjects as $s) {
    if ((int)$s['subject_id'] === $selectedSubjectId) {
        $activeSubject = $s;
        $activeCalc = calculate_shortage(
            (int)$s['classes_attended'],
            (int)$s['classes_conducted'],
            (float)$s['required_percentage']
        );
        break;
    }
}

$page_title = 'Attendance Shortage Calculator';
$active_nav = 'shortage_calculator';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Subject Shortage & Recovery Analysis</h3>
            <p class="card-subtitle">Select an enrolled subject to calculate required recovery classes or safe bunk limits</p>
        </div>
        <div class="card-actions">
            <form method="GET" action="shortage_calculator.php" style="display: flex; gap: 0.5rem; align-items: center;">
                <label for="subject-select" style="font-size: 0.85rem; font-weight: 600; color: #475569;">Select Subject:</label>
                <select id="subject-select" name="subject_id" class="form-control" style="width: auto; padding: 0.4rem 0.75rem;">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['subject_id'] ?>" <?= ((int)$s['subject_id'] === $selectedSubjectId) ? 'selected' : '' ?>>
                            <?= e($s['subject_code']) ?> &mdash; <?= e($s['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Analyze</button>
            </form>
        </div>
    </div>

    <div class="card-body">
        <?php if (!$activeSubject): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128218;</div>
                <h4 class="empty-title">No Subjects Available</h4>
                <p class="empty-description">Enroll in courses first to use the attendance shortage calculator.</p>
                <a href="subjects.php" class="btn btn-primary">Enroll in Courses</a>
            </div>
        <?php else: 
            $c = (int)$activeSubject['classes_conducted'];
            $a = (int)$activeSubject['classes_attended'];
            $req = (float)$activeSubject['required_percentage'];
            $calc = $activeCalc;
        ?>
            <!-- Subject Header Banner -->
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">
                            <?= e($activeSubject['subject_code']) ?> &bull; Faculty: <?= e($activeSubject['faculty_name']) ?>
                        </div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            <?= e($activeSubject['subject_name']) ?>
                        </h2>
                    </div>
                    <div>
                        <?php if ($calc['status'] === 'SAFE'): ?>
                            <span class="badge badge-safe" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">&#10004; SAFE</span>
                        <?php elseif ($calc['status'] === 'SHORTAGE'): ?>
                            <span class="badge badge-shortage" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">&#9888; SHORTAGE DETECTED</span>
                        <?php else: ?>
                            <span class="badge badge-neutral" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">&#9711; NO CLASSES CONDUCTED</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Snapshot Metrics Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Conducted</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;"><?= $c ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Attended</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #065f46;"><?= $a ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Absent</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #991b1b;"><?= $calc['absent'] ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Current Attendance</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: <?= ($calc['status'] === 'SAFE') ? '#065f46' : (($calc['status'] === 'SHORTAGE') ? '#991b1b' : '#0f172a') ?>;">
                            <?= number_format($calc['current_percentage'], 2) ?>%
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Required Threshold</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #1e3a8a;"><?= number_format($req, 1) ?>%</div>
                    </div>
                </div>
            </div>

            <!-- SCENARIO 1: NO CLASSES CONDUCTED -->
            <?php if ($calc['status'] === 'NO CLASSES'): ?>
                <div class="calculator-box" style="background-color: #f8fafc; border-left: 4px solid #f59e0b;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #92400e; margin-bottom: 0.35rem;">
                        Initial Course State: No Classes Conducted
                    </h3>
                    <p style="font-size: 0.9rem; color: #475569; line-height: 1.6;">
                        <?= e($calc['message']) ?>
                    </p>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.75rem;">
                        As soon as the teacher holds classes, record your attendance in the <a href="attendance.php" style="font-weight: 600;">Attendance Log</a> to view real-time shortage and buffer analytics.
                    </p>
                </div>

            <!-- SCENARIO 2: 100% REQUIREMENT IMPOSSIBLE -->
            <?php elseif (!empty($calc['is_impossible_100'])): ?>
                <div class="calculator-box calc-shortage" style="border-left: 4px solid #ef4444;">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #991b1b; margin-bottom: 0.35rem;">
                        &#9888; 100% Attendance Requirement Cannot Be Restored
                    </h3>
                    <p style="font-size: 0.95rem; color: #7f1d1d; line-height: 1.6; margin-bottom: 0.75rem;">
                        <?= e($calc['message']) ?>
                    </p>
                    <div style="background-color: #ffffff; border: 1px solid #fecaca; border-radius: 6px; padding: 1rem; font-size: 0.85rem; color: #991b1b;">
                        <strong>Mathematical Proof:</strong> Since $Attendance\ Percentage = \frac{A + x}{C + x} \times 100$, and you have already missed $(C - A) = <?= $calc['absent'] ?>$ class(es), the numerator $(A + x)$ is strictly less than $(C + x)$ for every finite integer $x \ge 0$. Therefore, future attendance alone cannot achieve a 100.00% record.
                    </div>
                </div>

            <!-- SCENARIO 3: ATTENDANCE SHORTAGE (BELOW THRESHOLD) -->
            <?php elseif ($calc['status'] === 'SHORTAGE'): ?>
                <div class="calculator-box calc-shortage">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <span class="badge badge-shortage">Shortage Detected</span>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: #991b1b; margin-top: 0.35rem;">
                                Minimum Additional Classes Needed to Reach <?= number_format($req, 1) ?>%
                            </h3>
                            <p style="font-size: 0.875rem; color: #7f1d1d; margin-top: 0.25rem;">
                                You must attend the following consecutive classes without a single absence to reach the college minimum.
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <div class="calc-stat-big" style="color: #991b1b;">
                                <?= $calc['classes_needed'] ?>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #991b1b;">
                                Consecutive Classes Required
                            </div>
                        </div>
                    </div>

                    <!-- Mathematical Projection Comparison -->
                    <div class="projection-comparison">
                        <div class="proj-card">
                            <div class="proj-label">Current Attendance Standing</div>
                            <div class="proj-val" style="color: #991b1b;">
                                <?= number_format($calc['current_percentage'], 2) ?>%
                            </div>
                            <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
                                <strong><?= $a ?></strong> attended out of <strong><?= $c ?></strong> conducted
                            </div>
                        </div>

                        <div class="proj-card" style="border-color: #a7f3d0; background-color: #f0fdf4;">
                            <div class="proj-label" style="color: #065f46;">Projected Attendance (After <?= $calc['classes_needed'] ?> Classes)</div>
                            <div class="proj-val" style="color: #065f46;">
                                <?= number_format($calc['projected_percentage'], 2) ?>%
                            </div>
                            <div style="font-size: 0.825rem; color: #065f46; margin-top: 0.35rem;">
                                <strong><?= $calc['projected_attended'] ?></strong> attended out of <strong><?= $calc['projected_conducted'] ?></strong> conducted (&ge; <?= number_format($req, 1) ?>% required)
                            </div>
                        </div>
                    </div>

                    <!-- Formula Walkthrough Box -->
                    <div style="margin-top: 1.25rem; padding: 1rem 1.25rem; background-color: #ffffff; border: 1px solid #fecaca; border-radius: 6px; font-size: 0.825rem; color: #334155; line-height: 1.6;">
                        <div style="font-weight: 700; color: #991b1b; margin-bottom: 0.35rem;">
                            &#128290; Official Formula Applied:
                        </div>
                        <div>
                            <code>Minimum Additional Classes = &lceil;(R &times; C - A) / (1 - R)&rceil;</code>
                        </div>
                        <div style="margin-top: 0.35rem;">
                            Where: <strong>R</strong> = <?= ($req / 100.0) ?> (<?= number_format($req, 0) ?>%), 
                            <strong>C</strong> = <?= $c ?> conducted, 
                            <strong>A</strong> = <?= $a ?> attended.
                        </div>
                        <div style="margin-top: 0.25rem;">
                            Calculation: &lceil;(<?= ($req / 100.0) ?> &times; <?= $c ?> - <?= $a ?>) / (1 - <?= ($req / 100.0) ?>)&rceil; 
                            = &lceil;(<?= round(($req / 100.0) * $c, 2) ?> - <?= $a ?>) / <?= (1.0 - ($req / 100.0)) ?>&rceil; 
                            = <strong><?= $calc['classes_needed'] ?> classes</strong>.
                        </div>
                    </div>
                </div>

            <!-- SCENARIO 4: REQUIREMENT SATISFIED (SAFE) -->
            <?php else: ?>
                <div class="calculator-box calc-safe">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <span class="badge badge-safe">&#10004; Safe Standing</span>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: #065f46; margin-top: 0.35rem;">
                                Attendance Requirement Satisfied
                            </h3>
                            <p style="font-size: 0.875rem; color: #047857; margin-top: 0.25rem;">
                                Your attendance of <strong><?= number_format($calc['current_percentage'], 2) ?>%</strong> comfortably meets or exceeds the <?= number_format($req, 1) ?>% college criterion.
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <div class="calc-stat-big" style="color: #065f46;">
                                <?= $calc['buffer_missable_classes'] ?>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #065f46;">
                                Classes You Can Safely Miss
                            </div>
                        </div>
                    </div>

                    <!-- Buffer Details -->
                    <div class="projection-comparison">
                        <div class="proj-card">
                            <div class="proj-label">Current Standing</div>
                            <div class="proj-val" style="color: #065f46;">
                                <?= number_format($calc['current_percentage'], 2) ?>%
                            </div>
                            <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
                                <?= $a ?> attended / <?= $c ?> conducted
                            </div>
                        </div>

                        <div class="proj-card" style="border-color: #cbd5e1;">
                            <div class="proj-label">Projected if You Miss <?= $calc['buffer_missable_classes'] ?> Classes</div>
                            <div class="proj-val" style="color: #0f172a;">
                                <?= number_format($calc['projected_miss_pct'], 2) ?>%
                            </div>
                            <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
                                <?= $a ?> attended / <?= ($c + $calc['buffer_missable_classes']) ?> conducted (Remaining &ge; <?= number_format($req, 1) ?>%)
                            </div>
                        </div>
                    </div>

                    <!-- Buffer Formula Explanation -->
                    <div style="margin-top: 1.25rem; padding: 1rem 1.25rem; background-color: #ffffff; border: 1px solid #a7f3d0; border-radius: 6px; font-size: 0.825rem; color: #334155; line-height: 1.6;">
                        <div style="font-weight: 700; color: #065f46; margin-bottom: 0.35rem;">
                            &#128161; Attendance Buffer Formula Applied:
                        </div>
                        <div>
                            <code>Maximum Missable Classes = &lfloor;(A / R) - C&rfloor;</code>
                        </div>
                        <div style="margin-top: 0.25rem;">
                            Calculation: &lfloor;(<?= $a ?> / <?= ($req / 100.0) ?>) - <?= $c ?>&rfloor; 
                            = &lfloor;<?= round($a / ($req / 100.0), 2) ?> - <?= $c ?>&rfloor; 
                            = <strong><?= $calc['buffer_missable_classes'] ?> classes</strong>.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- All Enrolled Subjects Shortage Comparison Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">All Subjects Shortage Matrix</h3>
            <p class="card-subtitle">Quick comparison of recovery classes and missable buffer across your courses</p>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th style="text-align: center;">Conducted</th>
                        <th style="text-align: center;">Attended</th>
                        <th style="text-align: center;">Current %</th>
                        <th style="text-align: center;">Req %</th>
                        <th>Status</th>
                        <th style="text-align: center;">Recovery Needed</th>
                        <th style="text-align: center;">Buffer Missable</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $row): 
                        $rc = (int)$row['classes_conducted'];
                        $ra = (int)$row['classes_attended'];
                        $rreq = (float)$row['required_percentage'];
                        $rcalc = calculate_shortage($ra, $rc, $rreq);
                    ?>
                        <tr class="<?= ($rcalc['status'] === 'SHORTAGE') ? 'row-shortage' : '' ?>">
                            <td>
                                <div class="subject-info">
                                    <span class="subject-name"><?= e($row['subject_name']) ?></span>
                                    <span class="subject-meta">Code: <strong><?= e($row['subject_code']) ?></strong></span>
                                </div>
                            </td>
                            <td style="text-align: center;"><?= $rc ?></td>
                            <td style="text-align: center; color: #065f46; font-weight: 600;"><?= $ra ?></td>
                            <td style="text-align: center; font-weight: 700; color: <?= ($rcalc['status'] === 'SAFE') ? '#065f46' : (($rcalc['status'] === 'SHORTAGE') ? '#991b1b' : '#64748b') ?>;">
                                <?= number_format($rcalc['current_percentage'], 2) ?>%
                            </td>
                            <td style="text-align: center; font-weight: 600;"><?= number_format($rreq, 1) ?>%</td>
                            <td>
                                <?php if ($rcalc['status'] === 'SAFE'): ?>
                                    <span class="badge badge-safe">&#10004; SAFE</span>
                                <?php elseif ($rcalc['status'] === 'SHORTAGE'): ?>
                                    <span class="badge badge-shortage">&#9888; SHORTAGE</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">&#9711; NO CLASSES</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($rcalc['status'] === 'SHORTAGE'): ?>
                                    <?php if ($rcalc['is_impossible_100']): ?>
                                        <span style="font-size: 0.775rem; color: #991b1b; font-weight: 700;">Impossible</span>
                                    <?php else: ?>
                                        <span style="font-size: 1.05rem; font-weight: 800; color: #991b1b;">
                                            +<?= $rcalc['classes_needed'] ?>
                                        </span>
                                        <div style="font-size: 0.7rem; color: #7f1d1d;">classes</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($rcalc['status'] === 'SAFE' && $rc > 0): ?>
                                    <span style="font-size: 1.05rem; font-weight: 800; color: #065f46;">
                                        <?= $rcalc['buffer_missable_classes'] ?>
                                    </span>
                                    <div style="font-size: 0.7rem; color: #047857;">can miss</div>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="shortage_calculator.php?subject_id=<?= (int)$row['subject_id'] ?>" class="btn btn-secondary btn-sm">
                                    Analyze
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
