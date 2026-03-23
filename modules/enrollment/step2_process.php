<?php
// modules/enrollment/step2_process.php
// Takes the selected subjects & schedules and calculates assessment
require_once '../../config/db.php';
include_once '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "<div class='alert alert-danger'>Invalid Request!</div>";
    include_once '../../includes/footer.php';
    exit;
}

$student_id = $_POST['student_id'] ?? '';
$acad_year = $_POST['acad_year'] ?? '';
$semester_id = $_POST['semester_id'] ?? '';
$program_id_selected = $_POST['program_id'] ?? null;
$year_level_selected = $_POST['year_level_id'] ?? null;
$section_selected = $_POST['section'] ?? '';
$selected_subjects = $_POST['subjects'] ?? [];

if (!$student_id || empty($selected_subjects)) {
    echo "<div class='alert alert-warning'>Please select at least one subject to enroll.</div>";
    echo "<a href='step1.php?student_id=$student_id' class='btn btn-primary'>Go Back</a>";
    include_once '../../includes/footer.php';
    exit;
}

// System Constants for Assessment
$TUITION_PER_UNIT = 500.00;
$MISC_FEE = 1500.00;
$LAB_FEE = 800.00; // per lab subject

$total_units = 0;
$total_tuition = 0;
$total_lab_fees = 0;
$enrollment_schedules = [];

// Validate and process selected schedules
foreach ($selected_subjects as $course_id) {
    if (isset($_POST['schedule_' . $course_id])) {
        $schedule_id = $_POST['schedule_' . $course_id];
        
        // Fetch course and schedule details
        $stmt = $pdo->prepare("
            SELECT c.course_code, c.course_name, c.total_units, c.units_lab, s.*
            FROM schedules s
            JOIN courses c ON s.course_id = c.course_id
            WHERE s.schedule_id = ?
        ");
        $stmt->execute([$schedule_id]);
        $row = $stmt->fetch();
        
        if ($row) {
            $total_units += $row['total_units'];
            if ($row['units_lab'] > 0) {
                $total_lab_fees += $LAB_FEE;
            }
            $enrollment_schedules[] = $row;
        }
    }
}

$total_tuition = $total_units * $TUITION_PER_UNIT;
$total_assessment = $total_tuition + $MISC_FEE + $total_lab_fees;

// Fetch Student details for display
$stuStmt = $pdo->prepare("SELECT s.*, p.program_code FROM students s JOIN programs p ON s.program_id = p.program_id WHERE s.student_id = ?");
$stuStmt->execute([$student_id]);
$student = $stuStmt->fetch();

// Quick Verification: Duplicate Enrollment Check
$force_edit = isset($_POST['force_edit']) ? true : false;
$checkEntry = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND academic_year = ? AND semester_id = ?");
$checkEntry->execute([$student_id, $acad_year, $semester_id]);
if ($checkEntry->rowCount() > 0 && !$force_edit) {
    echo "<div class='alert alert-danger'><h3><i class='fas fa-exclamation-circle'></i> Already Enrolled</h3><p>This student is already enrolled or pending for $acad_year - " . ($semester_id == 3 ? 'Summer' : htmlspecialchars($semester_id) . ' Semester') . ".</p></div>";
    echo "<a href='" . BASE_PATH . "modules/enrollment/records.php' class='btn btn-primary'>View Records</a>";
    include_once '../../includes/footer.php';
    exit;
}

?>

<style>
    .enroll-step2 .flow-track {
        border: 1px solid #dbe6f7;
        border-radius: 16px;
        background: linear-gradient(145deg, #ffffff, #f8fbff);
        padding: 0.9rem 1rem;
    }

    .enroll-step2 .flow-pill {
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.86rem;
        padding: 0.34rem 0.84rem;
        background: #6f7c8c;
        color: #fff;
    }

    .enroll-step2 .flow-pill.active {
        background: linear-gradient(145deg, #0d6efd, #368eff);
    }

    .enroll-step2 .soft-card {
        border: 1px solid #dbe7f8;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 22px 40px -34px rgba(20, 55, 102, 0.62);
        overflow: hidden;
        height: 100%;
    }

    .enroll-step2 .soft-head {
        border-bottom: 1px solid #e3ecf9;
        background: linear-gradient(145deg, #f8fbff, #f2f7ff);
        padding: 0.9rem 1rem;
    }

    .enroll-step2 .fees-table td {
        padding: 0.4rem 0;
    }

    .enroll-step2 .amount-due {
        border-top: 1px dashed #cde0ff;
        margin-top: 0.5rem;
        padding-top: 0.6rem;
        font-weight: 800;
        font-size: 1.55rem;
        color: #123e82;
    }
</style>

<div class="enroll-step2">
    <div class="flow-track d-flex justify-content-between align-items-center gap-2 mb-4">
        <span class="flow-pill"><i class="fas fa-check me-1"></i>1. Term & Subjects</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill active">2. Assessment Check</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill">3. Confirmation</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="soft-card">
                <div class="soft-head d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0"><i class="fas fa-table-list me-2"></i>Class Schedule Confirmation</h5>
                    <span class="badge bg-primary-subtle text-primary">Total Units: <?= number_format($total_units, 2) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th class="text-center">Units</th>
                                <th class="text-center">Section</th>
                                <th>Schedule & Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollment_schedules as $es): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($es['course_code']) ?></td>
                                    <td><?= htmlspecialchars($es['course_name']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($es['total_units']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($es['section_code']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($es['days']) ?> <?= htmlspecialchars($es['time_start']) ?>-<?= htmlspecialchars($es['time_end']) ?></div>
                                        <small class="text-muted">Room: <?= htmlspecialchars($es['room']) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Modify Subjects</a>
                    <strong>Total Enrolled Units: <?= number_format($total_units, 2) ?></strong>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="soft-card">
                <div class="soft-head">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Fee Assessment</h5>
                </div>
                <div class="p-3 p-md-4">
                    <table class="table table-borderless fees-table mb-2">
                        <tr>
                            <td>Tuition (&#8369;<?= number_format($TUITION_PER_UNIT, 2) ?> x <?= number_format($total_units, 2) ?>)</td>
                            <td class="text-end fw-semibold">&#8369;<?= number_format($total_tuition, 2) ?></td>
                        </tr>
                        <tr>
                            <td>Miscellaneous Fee</td>
                            <td class="text-end fw-semibold">&#8369;<?= number_format($MISC_FEE, 2) ?></td>
                        </tr>
                        <?php if ($total_lab_fees > 0): ?>
                            <tr>
                                <td>Laboratory Fees</td>
                                <td class="text-end fw-semibold">&#8369;<?= number_format($total_lab_fees, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <div class="amount-due d-flex justify-content-between align-items-center">
                        <span>Total Due</span>
                        <span>&#8369;<?= number_format($total_assessment, 2) ?></span>
                    </div>

                    <form method="POST" action="step3_save.php" class="mt-4 d-grid gap-2">
                        <?php if ($force_edit): ?>
                            <input type="hidden" name="force_edit" value="1">
                        <?php endif; ?>
                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($acad_year) ?>">
                        <input type="hidden" name="semester_id" value="<?= htmlspecialchars($semester_id) ?>">
                        <input type="hidden" name="total_units" value="<?= htmlspecialchars($total_units) ?>">
                        <input type="hidden" name="assessed_amount" value="<?= htmlspecialchars($total_assessment) ?>">
                        <input type="hidden" name="program_id" value="<?= htmlspecialchars($program_id_selected ?? '') ?>">
                        <input type="hidden" name="year_level_id" value="<?= htmlspecialchars($year_level_selected ?? '') ?>">
                        <input type="hidden" name="section" value="<?= htmlspecialchars($section_selected ?? '') ?>">
                        <?php foreach ($enrollment_schedules as $es): ?>
                            <input type="hidden" name="schedule_ids[]" value="<?= htmlspecialchars($es['schedule_id']) ?>">
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-circle-check me-1"></i>Confirm Enrollment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
