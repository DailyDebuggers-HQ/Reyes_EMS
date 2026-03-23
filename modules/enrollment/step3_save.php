<?php
// modules/enrollment/step3_save.php
// Saves the enrollment into the database and updates schedule slots
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $acad_year = $_POST['acad_year'] ?? '';
    $semester_id = $_POST['semester_id'] ?? '';
    $total_units = (float)($_POST['total_units'] ?? 0);
    $assessed_amount = (float)($_POST['assessed_amount'] ?? 0);
    $program_id = !empty($_POST['program_id']) ? $_POST['program_id'] : null;
    $year_level_id = !empty($_POST['year_level_id']) ? $_POST['year_level_id'] : null;
    $section = !empty($_POST['section']) ? $_POST['section'] : null;
    $schedule_ids = $_POST['schedule_ids'] ?? [];
    $force_edit = isset($_POST['force_edit']) ? true : false;

    if (empty($schedule_ids)) {
        header("Location: step3_save.php?error=" . urlencode("No schedules provided. Ensure you selected subjects.") . "&student_id=" . urlencode($student_id));
        exit;
    }

    try {
        // Begin Transaction
        $pdo->beginTransaction();
        
        $updStu = $pdo->prepare("UPDATE students SET program_id = ?, year_level_id = ? WHERE student_id = ?");
        $updStu->execute([$program_id ?: null, $year_level_id, $student_id]);
        
        // Check if existing
        $checkStmt = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND academic_year = ? AND semester_id = ? AND status != 'Cancelled'");
        $checkStmt->execute([$student_id, $acad_year, $semester_id]);
        $existing_id = $checkStmt->fetchColumn();

        if ($existing_id && !$force_edit) {
            throw new Exception("Student is already enrolled for this term.");
        }

        if ($existing_id) {
            // Decrement old capacity
            $oldScheds = $pdo->prepare("SELECT schedule_id FROM enrollment_schedules WHERE enrollment_id = ?");
            $oldScheds->execute([$existing_id]);
            $old_schedule_ids = $oldScheds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($old_schedule_ids)) {
                $inQuery = implode(',', array_fill(0, count($old_schedule_ids), '?'));
                $pdo->prepare("UPDATE schedules SET enrolled_count = GREATEST(0, enrolled_count - 1) WHERE schedule_id IN ($inQuery)")->execute($old_schedule_ids);
                // Delete old links
                $pdo->prepare("DELETE FROM enrollment_schedules WHERE enrollment_id = ?")->execute([$existing_id]);
            }

            // Update existing enrollment
            $stmt = $pdo->prepare("UPDATE enrollments SET total_units = ?, assessed_amount = ?, program_id = ?, section = ?, status = 'Enrolled' WHERE enrollment_id = ?");
            $stmt->execute([$total_units, $assessed_amount, $program_id, $section, $existing_id]);
            $enrollment_id = $existing_id;
        } else {
            // Insert new enrollment
            $stmt = $pdo->prepare("
                INSERT INTO enrollments (student_id, academic_year, semester_id, status, total_units, assessed_amount, program_id, section) 
                VALUES (?, ?, ?, 'Enrolled', ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $acad_year, $semester_id, $total_units, $assessed_amount, $program_id, $section]);
            $enrollment_id = $pdo->lastInsertId();
        }

        // Link subjects (schedules) to enrollment and increment enrolled_count
        $insertLinkStmt = $pdo->prepare("INSERT INTO enrollment_schedules (enrollment_id, schedule_id) VALUES (?, ?)");
        $updateSchedStmt = $pdo->prepare("UPDATE schedules SET enrolled_count = enrolled_count + 1 WHERE schedule_id = ?");

        foreach ($schedule_ids as $sch_id) {
            $insertLinkStmt->execute([$enrollment_id, $sch_id]);
            $updateSchedStmt->execute([$sch_id]);
        }

        // Commit Transaction
        $pdo->commit();

        // Redirect on success to prevent ERR_CACHE_MISS on refresh
        header("Location: step3_save.php?success=1&enrollment_id=" . urlencode($enrollment_id) . "&student_id=" . urlencode($student_id));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: step3_save.php?error=" . urlencode($e->getMessage()) . "&student_id=" . urlencode($student_id));
        exit;
    }
}

// Handling PRG pattern
include_once '../../includes/header.php';
$success = isset($_GET['success']) && $_GET['success'] == 1;
$error_message = $_GET['error'] ?? '';
$enrollment_id = $_GET['enrollment_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$acad_year = '';
$semester_id = '';

if ($success && $enrollment_id) {
    $stmt = $pdo->prepare("SELECT academic_year, semester_id FROM enrollments WHERE enrollment_id = ?");
    $stmt->execute([$enrollment_id]);
    $row = $stmt->fetch();
    if ($row) {
        $acad_year = $row['academic_year'];
        $semester_id = $row['semester_id'];
    }
}
?>

<style>
    .enroll-step3 .flow-track {
        border: 1px solid #dbe6f7;
        border-radius: 16px;
        background: linear-gradient(145deg, #ffffff, #f8fbff);
        padding: 0.9rem 1rem;
    }

    .enroll-step3 .flow-pill {
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.86rem;
        padding: 0.34rem 0.84rem;
        background: #6f7c8c;
        color: #fff;
    }

    .enroll-step3 .flow-pill.active {
        background: linear-gradient(145deg, #198754, #2da96d);
    }

    .enroll-step3 .result-card {
        border-radius: 22px;
        border: 1px solid #cfe7dc;
        background: linear-gradient(145deg, #ffffff, #f8fefb);
        box-shadow: 0 26px 46px -36px rgba(18, 92, 54, 0.62);
    }

    .enroll-step3 .result-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        margin: 0 auto;
        font-size: 2rem;
        color: #fff;
        background: linear-gradient(145deg, #198754, #2cac6e);
    }

    .enroll-step3 .fail-card {
        border-radius: 18px;
        border: 1px solid #f4c7ce;
        background: linear-gradient(145deg, #fff7f8, #fff2f4);
    }
</style>

<div class="enroll-step3">
    <div class="flow-track d-flex justify-content-between align-items-center gap-2 mb-4">
        <span class="flow-pill"><i class="fas fa-check me-1"></i>1. Term & Subjects</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill"><i class="fas fa-check me-1"></i>2. Assessment Check</span>
        <i class="fas fa-arrow-right text-muted"></i>
        <span class="flow-pill active"><i class="fas fa-flag-checkered me-1"></i>3. Confirmed</span>
    </div>

    <div class="row justify-content-center mt-4">
        <div class="col-lg-8">
            <?php if (isset($success) && $success): ?>
                <div class="result-card text-center p-4 p-md-5">
                    <div class="result-icon mb-3"><i class="fas fa-check"></i></div>
                    <h2 class="text-success mb-2">Enrollment Successful</h2>
                    <p class="lead mb-0">
                        Student <strong><?= htmlspecialchars($student_id) ?></strong> is now enrolled for
                        <strong><?= htmlspecialchars($acad_year) ?> - <?= $semester_id == 3 ? 'Summer' : htmlspecialchars($semester_id) . ' Semester' ?></strong>.
                    </p>

                    <div class="d-flex justify-content-center flex-wrap gap-2 mt-4">
                        <a href="print_cor.php?id=<?= $enrollment_id ?>" class="btn btn-warning" target="_blank">
                            <i class="fas fa-print me-1"></i>Print COR
                        </a>
                        <a href="<?= BASE_PATH ?>modules/payments/pay.php?enrollment_id=<?= urlencode($enrollment_id) ?>" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave me-1"></i>Proceed to Payment
                        </a>
                    </div>

                    <div class="mt-3">
                        <a href="<?= BASE_PATH ?>dashboard.php" class="text-muted">Return to Dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="fail-card p-4">
                    <h4 class="text-danger mb-2"><i class="fas fa-circle-xmark me-2"></i>Enrollment Failed</h4>
                    <p class="mb-2">An error occurred while saving the enrollment data. No changes were committed.</p>
                    <div class="small text-danger-emphasis mb-3"><?= htmlspecialchars($error_message) ?></div>
                    <a href="step1.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-danger btn-sm">Start Over</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

