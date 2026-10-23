<?php
/**
 * AttendEase — Subject-Wise Reports & Official Attendance Statement
 * Module 8: Filterable Academic Statement with Print-friendly Layout
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_student();

$pdo = get_db_connection();
$studentId = user_id();
$student = current_user();

// Filters from GET parameters
$filterSubjectId = (int)($_GET['subject_id'] ?? 0);
$filterStatus    = trim($_GET['status'] ?? 'all'); // 'all' | 'shortage' | 'safe'

// Base Query for student's enrolled courses and attendance
$sql = "
    SELECT 
        s.id AS subject_id,
        s.subject_code,
        s.subject_name,
        s.faculty_name,
        s.required_percentage,
        sem.semester_name,
        d.dept_code,
        d.dept_name,
        COALESCE(ar.classes_conducted, 0) AS classes_conducted,
        COALESCE(ar.classes_attended, 0) AS classes_attended,
        ar.last_updated
    FROM `student_subjects` ss
    INNER JOIN `subjects` s ON ss.subject_id = s.id
    INNER JOIN `departments` d ON s.department_id = d.id
    INNER JOIN `semesters` sem ON s.semester_id = sem.id
    LEFT JOIN `attendance_records` ar ON ar.student_id = :sid AND ar.subject_id = s.id
    WHERE ss.student_id = :sid2
";

$params = ['sid' => $studentId, 'sid2' => $studentId];

if ($filterSubjectId > 0) {
    $sql .= " AND s.id = :filter_subject";
    $params['filter_subject'] = $filterSubjectId;
}

$sql .= " ORDER BY s.subject_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawRows = $stmt->fetchAll();

// Process and filter by status
$reportRows = [];
$totalConducted = 0;
$totalAttended = 0;
$shortageSubjectsCount = 0;
$safeSubjectsCount = 0;

foreach ($rawRows as $row) {
    $c = (int)$row['classes_conducted'];
    $a = (int)$row['classes_attended'];
    $req = (float)$row['required_percentage'];
    $pct = calculate_percentage($a, $c);
    $status = get_attendance_status($a, $c, $req);
    $shortageData = calculate_shortage($a, $c, $req);

    $totalConducted += $c;
    $totalAttended += $a;
    if ($status === 'SHORTAGE') {
        $shortageSubjectsCount++;
    } elseif ($status === 'SAFE') {
        $safeSubjectsCount++;
    }

    // Apply status filter
    if ($filterStatus === 'shortage' && $status !== 'SHORTAGE') {
        continue;
    }
    if ($filterStatus === 'safe' && $status !== 'SAFE') {
        continue;
    }

    $reportRows[] = [
        'subject_code'   => $row['subject_code'],
        'subject_name'   => $row['subject_name'],
        'faculty_name'   => $row['faculty_name'],
        'conducted'      => $c,
        'attended'       => $a,
        'absent'         => max(0, $c - $a),
        'percentage'     => $pct,
        'required_pct'   => $req,
        'status'         => $status,
        'shortage_info'  => $shortageData,
        'last_updated'   => $row['last_updated'],
    ];
}

$overallPct = calculate_percentage($totalAttended, $totalConducted);
$generationDate = date('d F Y, h:i A');

// Fetch student's all enrolled subjects for the filter dropdown
$allEnrolledStmt = $pdo->prepare("
    SELECT s.id, s.subject_code, s.subject_name 
    FROM `student_subjects` ss 
    INNER JOIN `subjects` s ON ss.subject_id = s.id 
    WHERE ss.student_id = :sid 
    ORDER BY s.subject_code ASC
");
$allEnrolledStmt->execute(['sid' => $studentId]);
$allEnrolledSubjects = $allEnrolledStmt->fetchAll();

$page_title = 'Attendance Statement & Reports';
$active_nav = 'reports';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Print-Only Official University Header -->
<div class="print-header">
    <h1>ATTENDEASE &mdash; ACADEMIC ATTENDANCE STATEMENT</h1>
    <p>Official Institutional Attendance & Shortage Audit Transcript</p>
    <div style="margin-top: 0.75rem; font-size: 0.85rem; display: flex; justify-content: space-between; border-top: 1px solid #ccc; padding-top: 0.5rem;">
        <div><strong>Student Name:</strong> <?= e($student['name']) ?></div>
        <div><strong>Register Number:</strong> <?= e($student['register_number'] ?? 'N/A') ?></div>
        <div><strong>Department:</strong> <?= e($student['dept_code'] ?? 'N/A') ?></div>
        <div><strong>Semester:</strong> <?= e($student['semester_name'] ?? 'N/A') ?></div>
        <div><strong>Report Date:</strong> <?= e($generationDate) ?></div>
    </div>
</div>

<!-- On-Screen Filter Bar (Hidden in Print) -->
<div class="card no-print">
    <div class="card-header">
        <div>
            <h3 class="card-title">Attendance Report Filters</h3>
            <p class="card-subtitle">Filter statement by individual courses and compliance status</p>
        </div>
        <div class="card-actions">
            <!-- Instruction for printing with Ctrl+P (Zero JS) -->
            <span style="font-size: 0.85rem; color: #475569; margin-right: 0.5rem;">Press <strong>Ctrl + P</strong> to Print / Save PDF</span>
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="reports.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            <div style="flex: 1; min-width: 220px;">
                <label for="subject_id" style="font-size: 0.825rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Subject Filter:</label>
                <select id="subject_id" name="subject_id" class="form-control">
                    <option value="0">-- All Enrolled Courses --</option>
                    <?php foreach ($allEnrolledSubjects as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= ($filterSubjectId === (int)$es['id']) ? 'selected' : '' ?>>
                            <?= e($es['subject_code']) ?> &mdash; <?= e($es['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label for="status" style="font-size: 0.825rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Status Filter:</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= ($filterStatus === 'all') ? 'selected' : '' ?>>All Records</option>
                    <option value="shortage" <?= ($filterStatus === 'shortage') ? 'selected' : '' ?>>Shortage Subjects Only (&lt; Req)</option>
                    <option value="safe" <?= ($filterStatus === 'safe') ? 'selected' : '' ?>>Safe Subjects Only (&ge; Req)</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">&#128269; Apply Filter</button>
                <a href="reports.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Overall Summary Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Cumulative Attendance Summary</h3>
        <span style="font-size: 0.8rem; color: #64748b;">Generated on: <?= e($generationDate) ?></span>
    </div>
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem;">
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Student Name</div>
                <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;"><?= e($student['name']) ?></div>
                <div style="font-size: 0.8rem; color: #64748b;"><?= e($student['register_number'] ?? 'N/A') ?></div>
            </div>

            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Program / Dept</div>
                <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;"><?= e($student['dept_code'] ?? 'N/A') ?></div>
                <div style="font-size: 0.8rem; color: #64748b;"><?= e($student['semester_name'] ?? 'N/A') ?> &bull; <?= e($student['year_of_study'] ?? '1') ?> Year</div>
            </div>

            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Total Classes</div>
                <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;"><?= $totalAttended ?> / <?= $totalConducted ?></div>
                <div style="font-size: 0.8rem; color: #64748b;">Attended vs Conducted</div>
            </div>

            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Cumulative Percentage</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: <?= ($overallPct >= 75.0) ? '#065f46' : '#991b1b' ?>; margin-top: 0.1rem;">
                    <?= number_format($overallPct, 2) ?>%
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b;">Shortage Status</div>
                <div style="margin-top: 0.35rem;">
                    <?php if ($shortageSubjectsCount > 0): ?>
                        <span class="badge badge-shortage">&#9888; <?= $shortageSubjectsCount ?> Subject Shortage</span>
                    <?php else: ?>
                        <span class="badge badge-safe">&#10004; All Courses Safe</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Subject Report Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Subject-Wise Detailed Attendance Statement</h3>
            <p class="card-subtitle">Showing <?= count($reportRows) ?> course record(s)</p>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($reportRows)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128221;</div>
                <h4 class="empty-title">No Records Found</h4>
                <p class="empty-description">No subjects matched your selected filter criteria.</p>
                <a href="reports.php" class="btn btn-primary btn-sm">Clear Filter</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Course Details</th>
                            <th>Faculty</th>
                            <th style="text-align: center;">Conducted</th>
                            <th style="text-align: center;">Attended</th>
                            <th style="text-align: center;">Absent</th>
                            <th style="text-align: center;">Required %</th>
                            <th style="text-align: center;">Attendance %</th>
                            <th>Status</th>
                            <th>Recovery Plan / Buffer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportRows as $row): 
                            $si = $row['shortage_info'];
                        ?>
                            <tr class="<?= ($row['status'] === 'SHORTAGE') ? 'row-shortage' : '' ?>">
                                <td>
                                    <div class="subject-info">
                                        <span class="subject-name"><?= e($row['subject_name']) ?></span>
                                        <span class="subject-meta">Code: <strong><?= e($row['subject_code']) ?></strong></span>
                                    </div>
                                </td>
                                <td><?= e($row['faculty_name']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= $row['conducted'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: #065f46;"><?= $row['attended'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: #991b1b;"><?= $row['absent'] ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= number_format($row['required_pct'], 1) ?>%</td>
                                <td style="text-align: center;">
                                    <span style="font-weight: 800; font-size: 1rem; color: <?= ($row['status'] === 'SAFE') ? '#065f46' : (($row['status'] === 'SHORTAGE') ? '#991b1b' : '#64748b') ?>;">
                                        <?= number_format($row['percentage'], 2) ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'SAFE'): ?>
                                        <span class="badge badge-safe">&#10004; SAFE</span>
                                    <?php elseif ($row['status'] === 'SHORTAGE'): ?>
                                        <span class="badge badge-shortage">&#9888; SHORTAGE</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">&#9711; NO CLASSES</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'SHORTAGE'): ?>
                                        <?php if ($si['is_impossible_100']): ?>
                                            <span style="font-size: 0.8rem; color: #991b1b; font-weight: 700;">100% Unachievable</span>
                                        <?php else: ?>
                                            <div style="font-size: 0.85rem; font-weight: 700; color: #991b1b;">
                                                Attend <?= $si['classes_needed'] ?> consecutive classes
                                            </div>
                                            <div style="font-size: 0.725rem; color: #7f1d1d;">
                                                To reach <?= number_format($si['projected_percentage'], 2) ?>%
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($row['status'] === 'SAFE' && $row['conducted'] > 0): ?>
                                        <div style="font-size: 0.85rem; font-weight: 700; color: #065f46;">
                                            Can miss <?= $si['buffer_missable_classes'] ?> class(es)
                                        </div>
                                        <div style="font-size: 0.725rem; color: #047857;">
                                            Buffer remaining &ge; <?= number_format($row['required_pct'], 0) ?>%
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: #94a3b8;">Pending classes</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Print-Only Formal Academic Endorsement Signatures -->
<div class="print-signatures">
    <div class="sig-block">
        __________________________________<br>
        <strong>Student Signature</strong><br>
        <?= e($student['name']) ?>
    </div>
    <div class="sig-block">
        __________________________________<br>
        <strong>Faculty Advisor / Class Tutor</strong><br>
        Department of <?= e($student['dept_code'] ?? 'Engineering') ?>
    </div>
    <div class="sig-block">
        __________________________________<br>
        <strong>Head of Department (HoD)</strong><br>
        Institutional Seal
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
