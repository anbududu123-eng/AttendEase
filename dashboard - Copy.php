<?php
/**
 * AttendEase — Student Dashboard
 * Module 2: Academic Statistics, Overall Attendance, and Subject Summary
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();
$student = current_user();

// Retrieve all enrolled subjects and their latest attendance records for this student
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
    LEFT JOIN `attendance_records` ar ON ar.student_id = :student_id AND ar.subject_id = s.id
    WHERE ss.student_id = :student_id2
    ORDER BY s.subject_code ASC
");
$stmt->execute(['student_id' => $studentId, 'student_id2' => $studentId]);
$records = $stmt->fetchAll();

// Aggregation variables
$totalConducted = 0;
$totalAttended = 0;
$shortageCount = 0;
$safeCount = 0;
$noClassesCount = 0;
$subjectSummaries = [];

foreach ($records as $row) {
    $c = (int)$row['classes_conducted'];
    $a = (int)$row['classes_attended'];
    $req = (float)$row['required_percentage'];
    $absent = max(0, $c - $a);

    $pct = calculate_percentage($a, $c);
    $status = get_attendance_status($a, $c, $req);

    $totalConducted += $c;
    $totalAttended += $a;

    if ($status === 'SHORTAGE') {
        $shortageCount++;
    } elseif ($status === 'SAFE') {
        $safeCount++;
    } else {
        $noClassesCount++;
    }

    $shortageData = calculate_shortage($a, $c, $req);

    $subjectSummaries[] = [
        'id'            => (int)$row['subject_id'],
        'code'          => $row['subject_code'],
        'name'          => $row['subject_name'],
        'faculty'       => $row['faculty_name'],
        'conducted'     => $c,
        'attended'      => $a,
        'absent'        => $absent,
        'percentage'    => $pct,
        'required_pct'  => $req,
        'status'        => $status,
        'shortage_info' => $shortageData,
        'last_updated'  => $row['last_updated'] ? date('d M Y, h:i A', strtotime($row['last_updated'])) : 'Never',
    ];
}

$overallPct = calculate_percentage($totalAttended, $totalConducted);
$overallStatus = ($totalConducted === 0) ? 'NO CLASSES' : (($overallPct >= 75.0) ? 'SAFE' : 'SHORTAGE');

$page_title = 'Student Dashboard';
$active_nav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Student Profile Information Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                    <?= e($student['name']) ?>
                </h2>
                <div style="font-size: 0.875rem; color: #64748b; display: flex; flex-wrap: wrap; gap: 0.85rem;">
                    <span><strong>Register No:</strong> <?= e($student['register_number'] ?? 'N/A') ?></span>
                    <span>&bull;</span>
                    <span><strong>Department:</strong> <?= e($student['dept_name'] ?? 'Not Assigned') ?> (<?= e($student['dept_code'] ?? '') ?>)</span>
                    <span>&bull;</span>
                    <span><strong>Academic:</strong> <?= e($student['year_of_study'] ?? '1') ?> Year &bull; <?= e($student['semester_name'] ?? 'Semester 1') ?></span>
                </div>
            </div>
            <div>
                <a href="attendance.php" class="btn btn-primary btn-sm">&#9997; Update Attendance</a>
                <a href="reports.php" class="btn btn-secondary btn-sm">&#128442; View Report</a>
            </div>
        </div>
    </div>
</div>

<?php if ($shortageCount > 0): ?>
    <div class="alert alert-error">
        <span class="alert-icon">&#9888;</span>
        <div class="alert-content">
            <div class="alert-title">Attendance Shortage Warning</div>
            You currently have an attendance shortage in <strong><?= $shortageCount ?></strong> subject(s). Please review your required recovery classes using the <a href="shortage_calculator.php" style="color: #991b1b; font-weight: 700; text-decoration: underline;">Shortage Calculator</a> to avoid exam disqualification.
        </div>
    </div>
<?php endif; ?>

<!-- Five Statistics Metric Cards -->
<div class="stats-grid">
    <div class="stat-card <?= ($overallStatus === 'SAFE') ? 'stat-safe' : (($overallStatus === 'SHORTAGE') ? 'stat-shortage' : 'stat-neutral') ?>">
        <div class="stat-header">
            <span class="stat-title">Overall Attendance</span>
            <div class="stat-icon">&#128202;</div>
        </div>
        <div class="stat-value"><?= number_format($overallPct, 2) ?>%</div>
        <div class="stat-footer">
            <?php if ($overallStatus === 'SAFE'): ?>
                <span class="badge badge-safe">SAFE (75%+ met)</span>
            <?php elseif ($overallStatus === 'SHORTAGE'): ?>
                <span class="badge badge-shortage">SHORTAGE (Below 75%)</span>
            <?php else: ?>
                <span class="badge badge-neutral">NO CLASSES</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card stat-primary">
        <div class="stat-header">
            <span class="stat-title">Total Conducted</span>
            <div class="stat-icon">&#128197;</div>
        </div>
        <div class="stat-value"><?= $totalConducted ?></div>
        <div class="stat-footer">Across all <?= count($subjectSummaries) ?> enrolled courses</div>
    </div>

    <div class="stat-card stat-safe">
        <div class="stat-header">
            <span class="stat-title">Total Attended</span>
            <div class="stat-icon">&#10004;</div>
        </div>
        <div class="stat-value"><?= $totalAttended ?></div>
        <div class="stat-footer">Total missed: <?= max(0, $totalConducted - $totalAttended) ?> classes</div>
    </div>

    <div class="stat-card stat-safe">
        <div class="stat-header">
            <span class="stat-title">Safe Subjects</span>
            <div class="stat-icon">&#127891;</div>
        </div>
        <div class="stat-value"><?= $safeCount ?></div>
        <div class="stat-footer">Meeting requirements</div>
    </div>

    <div class="stat-card <?= ($shortageCount > 0) ? 'stat-shortage' : 'stat-neutral' ?>">
        <div class="stat-header">
            <span class="stat-title">Shortage Subjects</span>
            <div class="stat-icon">&#9888;</div>
        </div>
        <div class="stat-value"><?= $shortageCount ?></div>
        <div class="stat-footer">Requiring attendance recovery</div>
    </div>
</div>

<!-- Subject-Wise Attendance Summary Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Subject-Wise Attendance Summary</h3>
            <p class="card-subtitle">Real-time attendance percentages, requirements, and compliance status</p>
        </div>
        <div class="card-actions">
            <a href="shortage_calculator.php" class="btn btn-secondary btn-sm">&#128293; Shortage Analysis</a>
            <a href="what_if_calculator.php" class="btn btn-secondary btn-sm">&#129300; What-If Simulator</a>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($subjectSummaries)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128218;</div>
                <h4 class="empty-title">No Enrolled Subjects Found</h4>
                <p class="empty-description">You are not currently enrolled in any subjects for this semester. Click below to browse and enroll in your department courses.</p>
                <a href="subjects.php" class="btn btn-primary">Enroll in Subjects</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th style="text-align: center;">Conducted</th>
                            <th style="text-align: center;">Attended</th>
                            <th style="text-align: center;">Absent</th>
                            <th>Required %</th>
                            <th style="min-width: 170px;">Attendance Progress</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectSummaries as $subj): ?>
                            <tr class="<?= ($subj['status'] === 'SHORTAGE') ? 'row-shortage' : '' ?>">
                                <td>
                                    <div class="subject-info">
                                        <span class="subject-name"><?= e($subj['name']) ?></span>
                                        <span class="subject-meta">Code: <strong><?= e($subj['code']) ?></strong></span>
                                    </div>
                                </td>
                                <td><?= e($subj['faculty']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= $subj['conducted'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: #065f46;"><?= $subj['attended'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: #991b1b;"><?= $subj['absent'] ?></td>
                                <td style="font-weight: 600;"><?= number_format($subj['required_pct'], 1) ?>%</td>
                                <td>
                                    <div class="progress-container">
                                        <div class="progress-meta">
                                            <span><?= number_format($subj['percentage'], 2) ?>%</span>
                                            <span style="font-size: 0.7rem; color: #94a3b8;">Req: <?= number_format($subj['required_pct'], 0) ?>%</span>
                                        </div>
                                        <div class="progress-track">
                                            <?php 
                                            $barWidth = min(100.0, max(0.0, $subj['percentage']));
                                            $barClass = ($subj['status'] === 'SAFE') ? 'bar-safe' : (($subj['status'] === 'SHORTAGE') ? 'bar-shortage' : 'bar-neutral');
                                            ?>
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $barWidth ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($subj['status'] === 'SAFE'): ?>
                                        <span class="badge badge-safe">&#10004; SAFE</span>
                                    <?php elseif ($subj['status'] === 'SHORTAGE'): ?>
                                        <span class="badge badge-shortage">&#9888; SHORTAGE</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">&#9711; NO CLASSES</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="shortage_calculator.php?subject_id=<?= $subj['id'] ?>" class="btn btn-secondary btn-sm" title="Shortage Calculator">
                                        Calculator
                                    </a>
                                    <a href="what_if_calculator.php?subject_id=<?= $subj['id'] ?>" class="btn btn-secondary btn-sm" title="What-If Simulator">
                                        What-If
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
