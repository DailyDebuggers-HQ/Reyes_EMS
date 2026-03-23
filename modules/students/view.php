<?php
// modules/students/view.php
// View Student Profile & Enrollment History
require_once '../../config/db.php';
include_once '../../includes/header.php';

$student_id = $_GET['student_id'] ?? '';

if (!$student_id) {
    echo "<div class='alert alert-danger mx-4 mt-4'>Student ID is required.</div>";
    include_once '../../includes/footer.php';
    exit;
}

// Fetch Student details
$stmt = $pdo->prepare("SELECT s.*, p.program_code, p.program_name
                       FROM students s
                       LEFT JOIN programs p ON s.program_id = p.program_id
                       WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    echo "<div class='alert alert-danger mx-4 mt-4'>Student not found.</div>";
    include_once '../../includes/footer.php';
    exit;
}

// Fetch Enrollment History
$enrollmentStmt = $pdo->prepare("
    SELECT e.*,
        (SELECT MAX(curr.year_level_id)
         FROM enrollment_schedules es
         JOIN schedules s ON es.schedule_id = s.schedule_id
         JOIN curriculum curr ON s.course_id = curr.course_id
         WHERE es.enrollment_id = e.enrollment_id AND curr.program_id = ?) as curr_year_level
    FROM enrollments e
    WHERE e.student_id = ?
    ORDER BY e.academic_year DESC,
             e.semester_id DESC
");
$enrollmentStmt->execute([$student['program_id'], $student_id]);
$enrollments = $enrollmentStmt->fetchAll();

$currentEnrollmentStmt = $pdo->prepare("
    SELECT e.enrollment_id, e.academic_year, e.semester_id, e.section, e.status,
           e.total_units, e.assessed_amount, e.enrollment_date,
           COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.enrollment_id = e.enrollment_id), 0) AS total_paid
    FROM enrollments e
    WHERE e.student_id = ? AND e.status <> 'Cancelled'
    ORDER BY e.enrollment_date DESC, e.enrollment_id DESC
    LIMIT 1
");
$currentEnrollmentStmt->execute([$student_id]);
$currentEnrollment = $currentEnrollmentStmt->fetch();

$active_tab = $_GET['tab'] ?? 'profile';
$allowed_tabs = ['profile', 'history', 'snapshot', 'status'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'profile';
}

$latest_year = date('Y');
if (count($enrollments) > 0) {
    $latest_year = (int)substr($enrollments[0]['academic_year'], 0, 4);
}

foreach ($enrollments as &$e) {
    if (!empty($e['curr_year_level'])) {
        $e['inferred_year_level'] = $e['curr_year_level'];
    } else {
        $offset = $latest_year - (int)substr($e['academic_year'], 0, 4);
        $e['inferred_year_level'] = max(1, (int)$student['year_level_id'] - $offset);
    }
}
unset($e);

function getOrdinal($number) {
    if (!is_numeric($number)) return $number;
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) return $number . 'th';
    return $number . $ends[$number % 10];
}

function getSemesterLabel($semester_id) {
    if ((int)$semester_id === 1) return '1st Semester';
    if ((int)$semester_id === 2) return '2nd Semester';
    if ((int)$semester_id === 3) return 'Summer';
    return 'N/A';
}
?>

<style>
    .student-view .profile-hero {
        border: 1px solid #dfe8f5;
        border-radius: 18px;
        background: linear-gradient(145deg, #ffffff, #f7fbff);
        box-shadow: 0 24px 38px -34px rgba(20, 51, 91, 0.55);
    }

    .student-view .profile-avatar {
        width: 88px;
        height: 88px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: linear-gradient(145deg, #0d6efd, #49a1ff);
        color: #fff;
        font-size: 2rem;
    }

    .student-view .info-item {
        border: 1px solid #e6edf8;
        border-radius: 12px;
        padding: 0.72rem 0.82rem;
        background: #fff;
        height: 100%;
    }

    .student-view .info-label {
        color: #738399;
        font-size: 0.8rem;
        margin-bottom: 0.15rem;
    }

    .student-view .semester-card {
        border: 1px solid #dde7f4;
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 20px 36px -34px rgba(22, 49, 84, 0.5);
    }

    .student-view .semester-top {
        padding: 0.95rem 1rem;
        background: #f8fbff;
        border-bottom: 1px solid #e4ecf8;
    }

    .student-view .course-tag {
        background: #edf3ff;
        color: #2f5dbe;
        border-radius: 999px;
        padding: 0.32rem 0.58rem;
        font-weight: 700;
        font-size: 0.76rem;
    }

    .student-view .hero-actions .btn,
    .student-view .section-tabs .btn {
        padding: 0.34rem 0.66rem;
        font-size: 0.86rem;
        white-space: nowrap;
    }
</style>

<div class="student-view">
    <div class="profile-hero p-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar"><i class="fas fa-user-graduate"></i></div>
                <div>
                    <h2 class="mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($student['student_id']) ?></span>
                        <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($student['program_code'] ?: 'No Program') ?></span>
                        <span class="badge bg-success-subtle text-success"><?= htmlspecialchars($student['status']) ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 hero-actions">
                <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
                <a href="edit.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen me-2"></i>Edit Profile</a>
                <a href="<?= BASE_PATH ?>modules/enrollment/step1.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-sm btn-primary"><i class="fas fa-check-circle me-2"></i>New Enrollment</a>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-2 p-md-3">
            <div class="d-flex flex-wrap gap-2 section-tabs">
                <a href="view.php?student_id=<?= urlencode($student_id) ?>&tab=profile" class="btn btn-sm <?= $active_tab === 'profile' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-id-card me-2"></i>Personal Info
                </a>
                <a href="view.php?student_id=<?= urlencode($student_id) ?>&tab=history" class="btn btn-sm <?= $active_tab === 'history' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-clock-rotate-left me-2"></i>Enrollment History
                </a>
                <a href="view.php?student_id=<?= urlencode($student_id) ?>&tab=status" class="btn btn-sm <?= $active_tab === 'status' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-user-check me-2"></i>Enrollment Status
                </a>
                <a href="view.php?student_id=<?= urlencode($student_id) ?>&tab=snapshot" class="btn btn-sm <?= $active_tab === 'snapshot' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-chart-simple me-2"></i>Academic Snapshot
                </a>
            </div>
        </div>
    </div>

    <?php if ($active_tab === 'profile'): ?>
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>Personal Information</h5></div>
                    <div class="card-body bg-light">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Student ID</div><div class="fw-semibold"><?= htmlspecialchars($student['student_id']) ?></div></div></div>
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Full Name</div><div class="fw-semibold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div></div></div>
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Gender</div><div class="fw-semibold"><?= htmlspecialchars($student['gender'] ?? 'N/A') ?></div></div></div>
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Birthdate</div><div class="fw-semibold"><?= htmlspecialchars($student['birthdate'] ?? 'N/A') ?></div></div></div>
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Contact Number</div><div class="fw-semibold"><?= htmlspecialchars($student['contact_number'] ?? 'N/A') ?></div></div></div>
                            <div class="col-md-6"><div class="info-item"><div class="info-label">Email Address</div><div class="fw-semibold"><?= htmlspecialchars($student['email'] ?? 'N/A') ?></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-graduation-cap me-2 text-primary"></i>Academic Details</h5></div>
                    <div class="card-body bg-light d-grid gap-3">
                        <div class="info-item"><div class="info-label">Program Name</div><div class="fw-semibold"><?= htmlspecialchars($student['program_name'] ?: 'N/A') ?></div></div>
                        <div class="info-item"><div class="info-label">Program Code</div><div class="fw-semibold"><?= htmlspecialchars($student['program_code'] ?: 'N/A') ?></div></div>
                        <div class="info-item"><div class="info-label">Current Year</div><div class="fw-semibold"><?= htmlspecialchars($student['year_level_id'] ?: 'N/A') ?></div></div>
                        <div class="info-item"><div class="info-label">Section</div><div class="fw-semibold"><?= htmlspecialchars($student['section'] ?? 'X') ?></div></div>
                        <div class="info-item"><div class="info-label">Status</div><div class="fw-semibold"><?= htmlspecialchars($student['status']) ?></div></div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <?php if ($active_tab === 'snapshot'): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="info-item"><div class="info-label">Program Name</div><div class="fw-semibold"><?= htmlspecialchars($student['program_name'] ?: 'N/A') ?></div></div></div>
            <div class="col-md-3"><div class="info-item"><div class="info-label">Current Year</div><div class="fw-semibold"><?= htmlspecialchars($student['year_level_id'] ?: 'N/A') ?></div></div></div>
            <div class="col-md-3"><div class="info-item"><div class="info-label">Section</div><div class="fw-semibold"><?= htmlspecialchars($student['section'] ?? 'X') ?></div></div></div>
            <div class="col-md-3"><div class="info-item"><div class="info-label">Enrollment Records</div><div class="fw-semibold"><?= count($enrollments) ?></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body bg-light">
                <h5 class="mb-3"><i class="fas fa-circle-info me-2 text-primary"></i>Summary</h5>
                <p class="mb-0 text-muted">Use the <strong>Enrollment History</strong> section to review semester-by-semester subjects and print COR for each term.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab === 'status'): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-check me-2 text-primary"></i>Enrollment Status</h5>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($currentEnrollment && ($currentEnrollment['status'] ?? '') === 'Enrolled'): ?>
                        <span class="badge bg-success">Currently Enrolled</span>
                    <?php elseif ($currentEnrollment && ($currentEnrollment['status'] ?? '') === 'Pending'): ?>
                        <span class="badge bg-warning text-dark">Pending Enrollment</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Not Enrolled</span>
                    <?php endif; ?>

                    <?php if ($currentEnrollment): ?>
                        <a href="<?= BASE_PATH ?>modules/payments/pay.php?enrollment_id=<?= urlencode((string)$currentEnrollment['enrollment_id']) ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-money-bill-wave me-1"></i>Pay
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body bg-light">
                <?php if ($currentEnrollment): ?>
                    <?php $balance = max(0, (float)($currentEnrollment['assessed_amount'] ?? 0) - (float)($currentEnrollment['total_paid'] ?? 0)); ?>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="info-item"><div class="info-label">Term</div><div class="fw-semibold"><?= htmlspecialchars($currentEnrollment['academic_year']) ?> - <?= getSemesterLabel($currentEnrollment['semester_id']) ?></div></div></div>
                        <div class="col-md-4"><div class="info-item"><div class="info-label">Section</div><div class="fw-semibold"><?= htmlspecialchars($currentEnrollment['section'] ?? 'N/A') ?></div></div></div>
                        <div class="col-md-4"><div class="info-item"><div class="info-label">Date Enrolled</div><div class="fw-semibold"><?= htmlspecialchars($currentEnrollment['enrollment_date'] ?? 'N/A') ?></div></div></div>

                        <div class="col-md-3"><div class="info-item"><div class="info-label">Total Units</div><div class="fw-semibold"><?= htmlspecialchars(number_format((float)($currentEnrollment['total_units'] ?? 0), 0)) ?></div></div></div>
                        <div class="col-md-3"><div class="info-item"><div class="info-label">Assessed Amount</div><div class="fw-semibold">PHP <?= htmlspecialchars(number_format((float)($currentEnrollment['assessed_amount'] ?? 0), 2)) ?></div></div></div>
                        <div class="col-md-3"><div class="info-item"><div class="info-label">Total Paid</div><div class="fw-semibold text-success">PHP <?= htmlspecialchars(number_format((float)($currentEnrollment['total_paid'] ?? 0), 2)) ?></div></div></div>
                        <div class="col-md-3"><div class="info-item"><div class="info-label">Balance</div><div class="fw-semibold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">PHP <?= htmlspecialchars(number_format($balance, 2)) ?></div></div></div>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No active enrollment record found for this student.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab === 'history'): ?>
    <div class="card">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <h5 class="mb-0"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Enrollment History</h5>

            <?php if (count($enrollments) > 0): ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">View:</span>
                    <select class="form-select form-select-sm" id="semesterSelect" onchange="showSemester(this.value)">
                        <?php foreach ($enrollments as $enrollment): ?>
                            <option value="<?= $enrollment['enrollment_id'] ?>">
                                <?= getOrdinal($enrollment['inferred_year_level']) ?> Year - <?= $enrollment['semester_id'] == 1 ? '1st' : ($enrollment['semester_id'] == 2 ? '2nd' : 'Summer') ?> Sem - SY <?= htmlspecialchars($enrollment['academic_year']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="all">All Semesters</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body bg-light">
            <?php if (count($enrollments) > 0): ?>
                <div id="enrollmentHistoryContainer">
                    <?php foreach ($enrollments as $index => $enrollment):
                        $enr_id = $enrollment['enrollment_id'];

                        $subStmt = $pdo->prepare("
                            SELECT c.course_code, c.course_name, c.total_units
                            FROM enrollment_schedules es
                            JOIN schedules s ON es.schedule_id = s.schedule_id
                            JOIN courses c ON s.course_id = c.course_id
                            WHERE es.enrollment_id = ?
                        ");
                        $subStmt->execute([$enr_id]);
                        $subjects = $subStmt->fetchAll();

                        $statusBadge = 'text-success border-success';
                        if (($enrollment['status'] ?? '') == 'Pending') {
                            $statusBadge = 'text-warning border-warning';
                        }
                        if (($enrollment['status'] ?? '') == 'Cancelled') {
                            $statusBadge = 'text-danger border-danger';
                        }
                    ?>
                        <div class="semester_id-card mb-4" id="sem_<?= $enr_id ?>" <?= $index !== 0 ? 'style="display:none;"' : '' ?>>
                            <div class="semester-card">
                                <div class="semester-top d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge bg-primary"><?= getOrdinal($enrollment['inferred_year_level']) ?> Year</span>
                                        <span class="fw-semibold"><?= $enrollment['semester_id'] == 1 ? '1st' : ($enrollment['semester_id'] == 2 ? '2nd' : 'Summer') ?> Semester</span>
                                        <span class="text-muted small">SY <?= htmlspecialchars($enrollment['academic_year']) ?></span>
                                    </div>
                                    <a href="<?= BASE_PATH ?>modules/enrollment/print_cor.php?enrollment_id=<?= $enr_id ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>Print COR</a>
                                </div>

                                <div class="table-responsive p-3">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Subject</th>
                                                <th class="text-center">Units</th>
                                                <th class="text-center">Grade</th>
                                                <th>Remarks</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($subjects) > 0): ?>
                                                <?php foreach ($subjects as $i => $sub): ?>
                                                    <tr>
                                                        <td><?= $i + 1 ?></td>
                                                        <td><span class="course-tag"><?= htmlspecialchars($sub['course_code']) ?></span></td>
                                                        <td class="fw-semibold"><?= htmlspecialchars($sub['course_name']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars(number_format($sub['total_units'], 0)) ?></td>

                                                        <?php if ($index === 0): ?>
                                                            <td class="text-center text-muted fw-semibold">--</td>
                                                            <td class="text-muted">Ongoing</td>
                                                            <td class="text-center"><span class="badge bg-primary-subtle text-primary">Taking</span></td>
                                                        <?php else: ?>
                                                            <td class="text-center"><span class="badge bg-success-subtle text-success">1.25</span></td>
                                                            <td class="text-muted">Excellent</td>
                                                            <td class="text-center"><span class="badge bg-light border <?= $statusBadge ?>">Passed</span></td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">No subjects recorded.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="px-3 pb-3 text-end">
                                    <span class="text-muted">Semester Total Units:</span>
                                    <span class="fw-bold text-primary ms-1"><?= htmlspecialchars(number_format($enrollment['total_units'], 0)) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5>No Enrollment History</h5>
                    <p class="text-muted">This student has no recorded enrollments yet.</p>
                    <a href="<?= BASE_PATH ?>modules/enrollment/step1.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-primary mt-2">Start Enrollment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function showSemester(enrId) {
    const cards = document.querySelectorAll('.semester_id-card');
    cards.forEach(card => {
        if (enrId === 'all' || card.id === 'sem_' + enrId) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include_once '../../includes/footer.php'; ?>
