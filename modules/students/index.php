<?php
// modules/students/index.php
require_once '../../config/db.php';
include_once '../../includes/header.php';

// Handle Add Student Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? 'Other';
    $program_id = null; // No longer collected here
    $year_level_id = null; // Default to null (N/A)

    try {
        $pdo->beginTransaction();
        
        // Auto-generate Student ID: UAA-YYYY-NNNN
        $current_year = date('Y');
        $prefix = "UAA-{$current_year}-";
        
        // Find the highest sequence number for the current year
        $stmt_seq = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
        $stmt_seq->execute([$prefix . '%']);
        $last_id = $stmt_seq->fetchColumn();
        
        $seq = 1;
        if ($last_id) {
            $parts = explode('-', $last_id);
            if (isset($parts[2])) {
                $seq = (int)$parts[2] + 1;
            }
        }
        
        $student_id = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO students (student_id, first_name, last_name, gender, program_id, year_level_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Regular')");
        $stmt->execute([$student_id, $first_name, $last_name, $gender, $program_id, $year_level_id]);
        
        $pdo->commit();
        echo "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Student {$student_id} added successfully! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            echo "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-triangle'></i> Student ID already exists! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            echo "<div class='alert alert-danger alert-dismissible fade show'>Error adding student: " . $e->getMessage() . " <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// Handle Delete Student Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    $student_id_to_delete = $_POST['student_id'] ?? '';
    if ($student_id_to_delete) {
        try {
            $pdo->beginTransaction();

            // Find all enrollment IDs for this student
            $stmt = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ?");
            $stmt->execute([$student_id_to_delete]);
            $enrollment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($enrollment_ids)) {
                $inQuery = implode(',', array_fill(0, count($enrollment_ids), '?'));
                
                // Find all schedules associated with these enrollments
                $stmt = $pdo->prepare("SELECT schedule_id FROM enrollment_schedules WHERE enrollment_id IN ($inQuery)");
                $stmt->execute($enrollment_ids);
                $schedule_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Decrement enrolled_count for those schedules
                if (!empty($schedule_ids)) {
                    $schedInQuery = implode(',', array_fill(0, count($schedule_ids), '?'));
                    $pdo->prepare("UPDATE schedules SET enrolled_count = CASE WHEN enrolled_count > 0 THEN enrolled_count - 1 ELSE 0 END WHERE schedule_id IN ($schedInQuery)")->execute($schedule_ids);
                }

                // Delete enrollment schedules
                $pdo->prepare("DELETE FROM enrollment_schedules WHERE enrollment_id IN ($inQuery)")->execute($enrollment_ids);
            }

            // Delete payments
            $pdo->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$student_id_to_delete]);
            
            // Delete enrollments
            $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$student_id_to_delete]);

            // Finally delete the student
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$student_id_to_delete]);
            
            $pdo->commit();
            echo "<div class='alert alert-warning alert-dismissible fade show'><i class='fas fa-trash-alt'></i> Student {$student_id_to_delete} (and all related records) deleted successfully! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger alert-dismissible fade show'>Error deleting student: " . $e->getMessage() . " <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

$search = $_GET['search'] ?? '';
$filter_program = $_GET['program_id'] ?? '';
$filter_year = $_GET['year_level_id'] ?? '';

// Find the active/latest term to check current enrollment status
$latestTermStmt = $pdo->query("SELECT academic_year, semester_id FROM enrollments ORDER BY academic_year DESC, semester_id DESC LIMIT 1");
$latestTerm = $latestTermStmt->fetch();
$active_academic_year = $latestTerm ? $latestTerm['academic_year'] : '2025-2026';
$active_semester = $latestTerm ? $latestTerm['semester_id'] : 1;

// Fetch academic programs for the filter dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_code")->fetchAll();

// Build query
$sql = "SELECT s.*, p.program_code, p.program_name,
        (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id AND e.status != 'Cancelled') as is_currently_enrolled,
        (SELECT section FROM enrollments e WHERE e.student_id = s.student_id AND e.status != 'Cancelled' ORDER BY enrollment_date DESC LIMIT 1) as latest_section,
        (SELECT semester_id FROM enrollments e WHERE e.student_id = s.student_id AND e.status != 'Cancelled' ORDER BY enrollment_date DESC LIMIT 1) as latest_semester,
        (SELECT enrollment_id FROM enrollments e WHERE e.student_id = s.student_id AND e.status != 'Cancelled' ORDER BY enrollment_date DESC LIMIT 1) as latest_enrollment_id
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.program_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_program) {
    $sql .= " AND s.program_id = ?";
    $params[] = $filter_program;
}
if ($filter_year) {
    $sql .= " AND s.year_level_id = ?";
    $params[] = $filter_year;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$totalShown = count($students);
$enrolledShown = 0;
$regularShown = 0;

foreach ($students as $studentItem) {
    if ((int)($studentItem['is_currently_enrolled'] ?? 0) > 0) {
        $enrolledShown++;
    }
    if (($studentItem['status'] ?? '') === 'Regular') {
        $regularShown++;
    }
}

function formatSectionLabel(array $student): string {
    $rawSection = trim((string)($student['latest_section'] ?? ''));
    if ($rawSection !== '' && strtoupper($rawSection) !== 'X') {
        $normalized = strtoupper($rawSection);
        return $normalized === 'A' || $normalized === 'B' ? $normalized : $rawSection;
    }

    $id = (string)($student['student_id'] ?? '');
    $lastDigit = null;
    for ($i = strlen($id) - 1; $i >= 0; $i--) {
        if (ctype_digit($id[$i])) {
            $lastDigit = (int)$id[$i];
            break;
        }
    }

    if ($lastDigit === null) {
        return 'A';
    }

    return $lastDigit % 2 === 0 ? 'A' : 'B';
}
?>

<style>
    .students-page .hero-panel {
        border: 1px solid #dfe8f5;
        border-radius: 18px;
        background: linear-gradient(145deg, #ffffff, #f8fbff);
        box-shadow: 0 26px 42px -36px rgba(20, 51, 91, 0.55);
    }

    .students-page .hero-chip {
        padding: 0.65rem 0.75rem;
        border: 1px solid #e3ebf7;
        border-radius: 12px;
        background: #fff;
    }

    .students-page .hero-chip .value {
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1;
    }

    .students-page .hero-chip .label {
        font-size: 0.8rem;
        color: #728399;
        margin-top: 0.2rem;
    }

    .students-page .table td,
    .students-page .table th {
        white-space: nowrap;
    }

    .students-page .name-cell {
        min-width: 220px;
    }

    .students-page .id-pill {
        background: #eef4ff;
        color: #1f59b7;
        border: 1px solid #d9e6ff;
        border-radius: 999px;
        padding: 0.35rem 0.58rem;
        font-weight: 700;
        font-size: 0.79rem;
    }

    .students-page .section-pill {
        background: #eef9f2;
        color: #1f8f53;
        border: 1px solid #d4f0df;
        border-radius: 999px;
        padding: 0.28rem 0.52rem;
        font-weight: 700;
        font-size: 0.78rem;
    }

    .students-page .action-set {
        min-width: 180px;
    }

    .students-page .action-set .btn {
        margin: 0.12rem;
    }

    .students-page .masterlist-filter .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.2rem;
    }

    .students-page .masterlist-filter .form-control,
    .students-page .masterlist-filter .form-select {
        min-height: 36px;
        font-size: 0.88rem;
        border-radius: 10px;
        padding-top: 0.28rem;
        padding-bottom: 0.28rem;
        padding-left: 0.62rem;
        padding-right: 0.62rem;
    }

    .students-page .masterlist-filter .btn {
        min-height: 36px;
        font-size: 0.84rem;
    }
</style>

<div class="students-page">
    <div class="hero-panel p-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-6">
                <h2 class="mb-1"><i class="fas fa-users me-2 text-primary"></i>Student Directory</h2>
                <p class="text-muted mb-0">Professional masterlist for student profiles, enrollment actions, and payment routing.</p>
            </div>
            <div class="col-lg-6">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="hero-chip text-center">
                            <div class="value"><?= $totalShown ?></div>
                            <div class="label">Results</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="hero-chip text-center">
                            <div class="value"><?= $enrolledShown ?></div>
                            <div class="label">Enrolled</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="hero-chip text-center">
                            <div class="value"><?= $regularShown ?></div>
                            <div class="label">Regular</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h5 class="mb-1">Student Masterlist</h5>
                    <p class="text-muted mb-0">Search by ID/name and narrow by program or year level.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-primary-subtle text-primary">SY <?= htmlspecialchars($active_academic_year) ?> | <?= $active_semester == 3 ? 'Summer' : $active_semester . ' Sem' ?></span>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newStudentModal"><i class="fas fa-plus me-1"></i>New Student</button>
                </div>
            </div>

            <form method="GET" class="row g-2 align-items-end masterlist-filter">
                <div class="col-lg-4">
                    <label class="form-label small text-muted mb-1">Keyword</label>
                    <input type="text" name="search" class="form-control" placeholder="Search ID or full name" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-lg-3">
                    <label class="form-label small text-muted mb-1">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['program_id'] ?>" <?= $filter_program == $prog['program_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prog['program_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Year Level</label>
                    <select name="year_level_id" class="form-select">
                        <option value="">All Years</option>
                        <option value="1" <?= $filter_year == '1' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= $filter_year == '2' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= $filter_year == '3' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4" <?= $filter_year == '4' ? 'selected' : '' ?>>4th Year</option>
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sliders me-2"></i>Apply</button>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Gender</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalShown > 0): ?>
                        <?php $seq = 1; foreach ($students as $student): ?>
                            <tr>
                                <td><?= $seq++ ?></td>
                                <td><span class="id-pill"><?= htmlspecialchars($student['student_id'] ?? '') ?></span></td>
                                <td class="name-cell fw-bold"><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                <td><?= htmlspecialchars(empty(trim((string)($student['gender'] ?? ''))) ? 'N/A' : $student['gender']) ?></td>
                                <td><?= htmlspecialchars(empty(trim($student['program_code'] ?? '')) ? 'N/A' : $student['program_code']) ?></td>
                                <td><?= htmlspecialchars(empty(trim($student['year_level_id'] ?? '')) ? 'N/A' : $student['year_level_id']) ?></td>
                                <td><span class="section-pill"><?= htmlspecialchars(formatSectionLabel($student)) ?></span></td>
                                <td><?= htmlspecialchars(empty(trim($student['latest_semester'] ?? '')) ? 'N/A' : ($student['latest_semester'] == 3 ? 'Summer' : $student['latest_semester'] . ' Sem')) ?></td>
                                <td>
                                    <?php
                                        $badgeClass = 'bg-success';
                                        if (($student['status'] ?? '') === 'Irregular') {
                                            $badgeClass = 'bg-warning text-dark';
                                        }
                                        if (($student['status'] ?? '') === 'Dropped') {
                                            $badgeClass = 'bg-danger';
                                        }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($student['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="action-set d-inline-flex flex-wrap justify-content-end">
                                        <a href="view.php?student_id=<?= urlencode($student['student_id']) ?>" class="btn btn-sm btn-outline-primary" title="Open Profile"><i class="fas fa-eye me-1"></i>Profile</a>

                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Student"><i class="fas fa-trash me-1"></i>Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">No students found matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add New Student Modal -->
<div class="modal fade" id="newStudentModal" tabindex="-1" aria-labelledby="newStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-header">
                    <h5 class="modal-title" id="newStudentModalLabel"><i class="fas fa-user-plus"></i> Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        <i class="fas fa-info-circle"></i> Student ID will be automatically generated as <strong>UAA-<?= date('Y') ?>-XXXX</strong>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
