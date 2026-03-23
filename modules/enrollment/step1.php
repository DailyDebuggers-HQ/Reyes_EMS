<?php
// modules/enrollment/step1.php
// Enrollment Step 1 & 2 & 3: Select Term, Load Student, Choose Subjects
require_once '../../config/db.php';
include_once '../../includes/header.php';

$student_id = $_GET['student_id'] ?? '';
$term_code = $_GET['term_code'] ?? '';

// Find the active/latest term
$latestTermStmt = $pdo->query("SELECT academic_year, semester_id FROM enrollments ORDER BY academic_year DESC, semester_id DESC LIMIT 1");
$latestTerm = $latestTermStmt->fetch();
$default_acad_year = $latestTerm ? $latestTerm['academic_year'] : '2025-2026';
$default_semester = $latestTerm ? $latestTerm['semester_id'] : 1;

// Default fallback if no term code
$acad_year = $default_acad_year;
$semester_id = $default_semester;

if (!empty($term_code) && strlen($term_code) === 3) {
    // Parse term code (e.g. 271 -> 27 and 1)
    $yr_prefix = substr($term_code, 0, 2); // e.g. '27'
    $term_suffix = substr($term_code, 2, 1); // e.g. '1'
    
    // Calculate academic year
    $end_year = 2000 + (int)$yr_prefix; // e.g. 2027
    $start_year = $end_year - 1;        // e.g. 2026
    $acad_year = $start_year . '-' . $end_year; // 2026-2027
    
    // Determine Semester
    if ($term_suffix === '1') {
        $semester_id = 1;
    } elseif ($term_suffix === '2') {
        $semester_id = 2;
    } elseif ($term_suffix === '0') {
        $semester_id = 3;
    }
} else if (isset($_GET['acad_year']) && isset($_GET['semester_id'])) {
    $acad_year = $_GET['acad_year'];
    $semester_id = $_GET['semester_id'];
}

if (!$student_id) {
    echo "<div class='alert alert-danger'>Student ID is required!</div>";
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
    echo "<div class='alert alert-danger'>Student not found!</div>";
    include_once '../../includes/footer.php';
    exit;
}

// Ensure program exists - REMOVED since we now choose it on the fly during enrollment step 1

$force_edit = isset($_GET['force_edit']) ? true : false;

// Check if already enrolled in this specific term
$checkEnrolled = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND academic_year = ? AND semester_id = ? AND status != 'Cancelled'");
$checkEnrolled->execute([$student_id, $acad_year, $semester_id]);
$already_enrolled = $checkEnrolled->fetch();

// Auto-Load subjects from curriculum based on Student's Program & Year Level & selected Term
$selected_program = $_GET['program_id'] ?? $student['program_id'];
$selected_year_level = $_GET['year_level_id'] ?? $student['year_level_id'];
$selected_section = $_GET['section'] ?? 'X';

if (!$selected_program) {
    // Default to the first program in the system if the student is entirely new
    $fallbackProg = $pdo->query("SELECT program_id FROM programs LIMIT 1")->fetchColumn();
    $selected_program = $fallbackProg;
}
if ($semester_id == 3) {
    // For Summer, fetch ALL courses in the program so students can retake/add failed subjects
    $currStmt = $pdo->prepare("
        SELECT c.*, MIN(cr.curriculum_id) as curriculum_id
        FROM curriculum cr
        JOIN courses c ON cr.course_id = c.course_id
        WHERE cr.program_id = ?
        GROUP BY c.course_id
    ");
    $currStmt->execute([$selected_program]);
} else {
    $currStmt = $pdo->prepare("
        SELECT c.*, cr.curriculum_id
        FROM curriculum cr
        JOIN courses c ON cr.course_id = c.course_id
        WHERE cr.program_id = ?
          AND cr.year_level_id = ?
          AND cr.semester_id = ?
    ");
    $currStmt->execute([$selected_program, $selected_year_level, $semester_id]);
}

$curriculum_subjects = $currStmt->fetchAll();

// We also need available schedules for these subjects so the user can select a section
// We will AJAX this or just load the data all at once. Since enrollment is step based, loading all section options here is nice.

?>

<style>
    .enroll-step1 .flow-track {
        border: 1px solid #dbe6f7;
        border-radius: 16px;
        background: linear-gradient(145deg, #ffffff, #f8fbff);
        padding: 0.9rem 1rem;
    }

    .enroll-step1 .flow-pill {
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.86rem;
        padding: 0.34rem 0.84rem;
        background: #6f7c8c;
        color: #fff;
    }

    .enroll-step1 .flow-pill.active {
        background: linear-gradient(145deg, #0d6efd, #368eff);
    }

    .enroll-step1 .glass-card {
        border: 1px solid #dbe7f8;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 22px 40px -34px rgba(20, 55, 102, 0.62);
        overflow: hidden;
        height: 100%;
    }

    .enroll-step1 .glass-head {
        border-bottom: 1px solid #e3ecf9;
        background: linear-gradient(145deg, #f8fbff, #f2f7ff);
        padding: 0.9rem 1rem;
    }

    .enroll-step1 .student-meta dt {
        color: #5f7290;
        font-weight: 700;
    }

    .enroll-step1 .student-meta dd {
        font-weight: 600;
        margin-bottom: 0;
    }

    .enroll-step1 .term-hint {
        border: 1px solid #cbe1ff;
        border-radius: 12px;
        background: #edf5ff;
        color: #1f4677;
        font-size: 0.9rem;
        font-weight: 600;
        padding: 0.68rem 0.78rem;
    }

    .enroll-step1 .subject-table thead th {
        font-size: 0.82rem;
        letter-spacing: 0.2px;
        text-transform: uppercase;
        color: #587096;
        background: #f7faff;
    }

    .enroll-step1 .subject-table td {
        font-size: 0.94rem;
    }

    .enroll-step1 .sched-line {
        font-size: 0.84rem;
        color: #2d4768;
        font-weight: 600;
    }

    .enroll-step1 .enrolled-warning {
        border: 1px solid #f4d58b;
        border-radius: 16px;
        background: linear-gradient(145deg, #fffaf0, #fff7df);
        padding: 1rem;
    }
</style>

<div class="enroll-step1">
    <div class="flow-track d-flex justify-content-between align-items-center gap-2 mb-4">
        <span class="flow-pill active">1. Term & Subjects</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill">2. Assessment</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill">3. Confirmation</span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="glass-card">
                <div class="glass-head">
                    <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Student Target</h5>
                </div>
                <div class="p-3 p-md-4">
                    <?php
                        $statusClass = $student['status'] === 'Irregular' ? 'bg-warning text-dark' : 'bg-success';
                    ?>
                    <dl class="row g-3 student-meta mb-0">
                        <dt class="col-4">ID</dt>
                        <dd class="col-8"><span class="badge bg-secondary"><?= htmlspecialchars($student['student_id']) ?></span></dd>

                        <dt class="col-4">Name</dt>
                        <dd class="col-8"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></dd>

                        <dt class="col-4">Program</dt>
                        <dd class="col-8"><?= htmlspecialchars($student['program_name'] ?? 'N/A') ?></dd>

                        <dt class="col-4">Year Level</dt>
                        <dd class="col-8"><?= htmlspecialchars($student['year_level_id']) ?></dd>

                        <dt class="col-4">Status</dt>
                        <dd class="col-8"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($student['status']) ?></span></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card">
                <div class="glass-head">
                    <h5 class="mb-0"><i class="fas fa-calendar-days me-2"></i>Academic Term Builder</h5>
                </div>
                <div class="p-3 p-md-4">
                    <form method="GET" action="step1.php" class="row g-3 align-items-end">
                        <?php if ($force_edit): ?>
                            <input type="hidden" name="force_edit" value="1">
                        <?php endif; ?>
                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">

                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold mb-1">Academic Year</label>
                            <input type="text" name="acad_year" class="form-control" value="<?= htmlspecialchars($acad_year) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold mb-1">Semester</label>
                            <select name="semester_id" class="form-select" onchange="this.form.submit()">
                                <option value="1" <?= $semester_id == 1 ? 'selected' : '' ?>>1st Sem</option>
                                <option value="2" <?= $semester_id == 2 ? 'selected' : '' ?>>2nd Sem</option>
                                <option value="3" <?= $semester_id == 3 ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold mb-1">Year Level</label>
                            <select name="year_level_id" class="form-select" onchange="this.form.submit()">
                                <option value="1" <?= $selected_year_level == '1' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $selected_year_level == '2' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $selected_year_level == '3' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $selected_year_level == '4' ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small text-muted fw-semibold mb-1">Program</label>
                            <select name="program_id" class="form-select" onchange="this.form.submit()">
                                <?php
                                    $progStmt = $pdo->query("SELECT program_id, program_code FROM programs");
                                    $all_programs = $progStmt->fetchAll();
                                    foreach ($all_programs as $p):
                                ?>
                                    <option value="<?= $p['program_id'] ?>" <?= $selected_program == $p['program_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['program_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold mb-1">Section</label>
                            <select name="section" class="form-select" onchange="this.form.submit()">
                                <option value="X" <?= $selected_section == 'X' ? 'selected' : '' ?>>X</option>
                                <option value="Y" <?= $selected_section == 'Y' ? 'selected' : '' ?>>Y</option>
                            </select>
                        </div>

                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-rotate me-1"></i>Refresh</button>
                        </div>
                    </form>

                    <div class="term-hint mt-3">
                        <i class="fas fa-circle-info me-1"></i>
                        Showing subjects for <strong>Year <?= htmlspecialchars($selected_year_level) ?> - <?= $semester_id == 3 ? 'Summer' : htmlspecialchars($semester_id) . ' Semester' ?></strong> based on curriculum.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($already_enrolled && !$force_edit): ?>
        <div class="enrolled-warning mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h5 class="mb-1 text-warning-emphasis"><i class="fas fa-triangle-exclamation me-2"></i>Already Enrolled</h5>
                    <p class="mb-0">This student is already officially enrolled for <strong><?= $semester_id == 3 ? 'Summer' : htmlspecialchars($semester_id) . ' Semester' ?>, S.Y. <?= htmlspecialchars($acad_year) ?></strong>.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_PATH ?>modules/students/view.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-eye me-1"></i>View Profile
                    </a>
                    <a href="step1.php?student_id=<?= urlencode($student_id) ?>&acad_year=<?= urlencode($acad_year) ?>&semester_id=<?= urlencode($semester_id) ?>&program_id=<?= urlencode($selected_program) ?>&year_level_id=<?= urlencode($selected_year_level) ?>&section=<?= urlencode($selected_section) ?>&force_edit=1" class="btn btn-sm btn-warning">
                        <i class="fas fa-pen-to-square me-1"></i>Force Modify Subjects
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form action="step2_process.php" method="POST" id="enrollmentForm" <?= ($already_enrolled && !$force_edit) ? 'style="display:none;"' : '' ?>>
        <?php if ($force_edit): ?>
            <input type="hidden" name="force_edit" value="1">
        <?php endif; ?>
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($acad_year) ?>">
        <input type="hidden" name="semester_id" value="<?= htmlspecialchars($semester_id) ?>">
        <input type="hidden" name="program_id" value="<?= htmlspecialchars($selected_program) ?>">
        <input type="hidden" name="year_level_id" value="<?= htmlspecialchars($selected_year_level) ?>">
        <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section) ?>">

        <div class="glass-card mb-3">
            <div class="glass-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Curriculum Subject Grid</h5>
                <span class="badge bg-primary-subtle text-primary">Selected Units: <span id="totalUnitsBadge">0.00</span></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle subject-table">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 52px;"><input type="checkbox" id="checkAll" class="form-check-input" checked></th>
                            <th>Course Code</th>
                            <th>Course Description</th>
                            <th class="text-center">Units</th>
                            <th>Schedule / Section</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($curriculum_subjects) > 0): ?>
                            <?php foreach ($curriculum_subjects as $subject): ?>
                                <?php
                                    $target_section = current(explode('-', $selected_section));
                                    if ($target_section !== 'X' && $target_section !== 'Y') {
                                        $target_section = 'X';
                                    }

                                    $schedStmt = $pdo->prepare("SELECT s.*, t.first_name, t.last_name FROM schedules s LEFT JOIN teachers t ON s.teacher_id = t.teacher_id WHERE s.course_id = ? AND s.academic_year = ? AND s.semester_id = ? AND s.section_code = ?");
                                    $schedStmt->execute([$subject['course_id'], $acad_year, $semester_id, $target_section]);
                                    $schedules = $schedStmt->fetchAll();

                                    if (count($schedules) === 0 && $semester_id == 3) {
                                        $insertSched = $pdo->prepare("INSERT INTO schedules (course_id, academic_year, semester_id, days, time_start, time_end, room, capacity, section_code) VALUES (?, ?, ?, 'TBA', '08:00:00', '11:00:00', 'TBA', 40, ?)");
                                        $insertSched->execute([$subject['course_id'], $acad_year, $semester_id, $target_section]);
                                        $schedStmt->execute([$subject['course_id'], $acad_year, $semester_id, $target_section]);
                                        $schedules = $schedStmt->fetchAll();
                                    }

                                    if (count($schedules) === 0) {
                                        continue;
                                    }

                                    $sched = $schedules[0];
                                    $disp_section = $selected_section;
                                    $label = "{$disp_section} | {$sched['days']} " . date('h:i A', strtotime($sched['time_start'])) . " - " . date('h:i A', strtotime($sched['time_end'])) . " | Room: {$sched['room']}";
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <input
                                            type="checkbox"
                                            class="form-check-input subject-checkbox"
                                            name="subjects[]"
                                            value="<?= $subject['course_id'] ?>"
                                            data-units="<?= $subject['total_units'] ?>"
                                            checked
                                        >
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($subject['course_code']) ?></td>
                                    <td><?= htmlspecialchars($subject['course_name']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($subject['total_units']) ?></td>
                                    <td>
                                        <input type="hidden" name="schedule_<?= $subject['course_id'] ?>" value="<?= $sched['schedule_id'] ?>">
                                        <span class="sched-line"><?= htmlspecialchars($label) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No subjects found in curriculum for this term.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 d-flex justify-content-end border-top">
                <button type="submit" class="btn btn-success" id="btnNext" <?= count($curriculum_subjects) == 0 ? 'disabled' : '' ?>>
                    Proceed to Assessment <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    const totalUnitsBadge = document.getElementById('totalUnitsBadge');

    function calculateTotalUnits() {
        let total = 0;
        checkboxes.forEach(function (cb) {
            if (cb.checked) {
                total += parseFloat(cb.getAttribute('data-units'));
            }
        });
        if (totalUnitsBadge) {
            totalUnitsBadge.innerText = total.toFixed(2);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
            calculateTotalUnits();
        });
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', calculateTotalUnits);
    });

    calculateTotalUnits();
});
</script>

<?php include_once '../../includes/footer.php'; ?>


