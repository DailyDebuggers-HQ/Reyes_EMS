<?php
require_once "../../config/db.php";
include_once "../../includes/header.php";

$student_id = $_GET["student_id"] ?? "";

if (!$student_id) {
    echo "<div class=\"alert alert-danger mx-4 mt-4\">Student ID is required.</div>";
    include_once "../../includes/footer.php";
    exit;
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_student") {
    $first_name = $_POST["first_name"] ?? "";
    $last_name = $_POST["last_name"] ?? "";
    $gender = $_POST["gender"] ?? null;
    $birthdate = !empty($_POST["birthdate"]) ? $_POST["birthdate"] : null;
    $contact_number = $_POST["contact_number"] ?? null;
    $email = $_POST["email"] ?? null;

    try {
        $stmt = $pdo->prepare("UPDATE students SET first_name=?, last_name=?, gender=?, birthdate=?, contact_number=?, email=? WHERE student_id=?");
        $stmt->execute([$first_name, $last_name, $gender, $birthdate, $contact_number, $email, $student_id]);
        echo "<div class=\"alert alert-success alert-dismissible fade show mx-4 mt-4\"><i class=\"fas fa-check-circle\"></i> Student updated successfully! <a href=\"view.php?student_id=".urlencode($student_id)."\" class=\"alert-link\">View Profile</a><button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";
    } catch (PDOException $e) {
        echo "<div class=\"alert alert-danger mx-4 mt-4\">Error updating student: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Fetch Student details
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    echo "<div class=\"alert alert-danger mx-4 mt-4\">Student not found.</div>";
    include_once "../../includes/footer.php";
    exit;
}

// Fetch Programs for Dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_code")->fetchAll();

?>
<style>
    .student-edit .hero-edit {
        border: 1px solid #dfe8f5;
        border-radius: 18px;
        background: linear-gradient(145deg, #ffffff, #f8fbff);
        box-shadow: 0 24px 38px -34px rgba(20, 51, 91, 0.55);
    }

    .student-edit .edit-avatar {
        width: 82px;
        height: 82px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: #fff;
        background: linear-gradient(145deg, #0d6efd, #49a1ff);
        font-size: 1.8rem;
    }

    .student-edit .form-panel {
        border: 1px solid #dfe8f5;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 22px 36px -32px rgba(22, 49, 84, 0.5);
    }

    .student-edit .field-label {
        font-size: 0.82rem;
        color: #6f8096;
        font-weight: 700;
    }
</style>

<div class="student-edit">
    <div class="hero-edit p-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="edit-avatar"><i class="fas fa-user-pen"></i></div>
                <div>
                    <h2 class="mb-1">Edit Student Profile</h2>
                    <p class="text-muted mb-0">Update core student information for <strong><?= htmlspecialchars($student["student_id"]) ?></strong>.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Masterlist</a>
                <a href="view.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-outline-primary"><i class="fas fa-eye me-2"></i>View Profile</a>
            </div>
        </div>
    </div>

    <div class="form-panel p-4 mb-4">
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_student">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="field-label">Student ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student["student_id"]) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select Gender</option>
                        <option value="Male" <?= $student["gender"] == "Male" ? "selected" : "" ?>>Male</option>
                        <option value="Female" <?= $student["gender"] == "Female" ? "selected" : "" ?>>Female</option>
                        <option value="Other" <?= $student["gender"] == "Other" ? "selected" : "" ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Birthdate</label>
                    <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($student["birthdate"] ?? "") ?>">
                </div>

                <div class="col-md-6">
                    <label class="field-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($student["first_name"]) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="field-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($student["last_name"]) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="field-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($student["contact_number"] ?? "") ?>" placeholder="09XXXXXXXXX">
                </div>
                <div class="col-md-6">
                    <label class="field-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student["email"] ?? "") ?>" placeholder="name@example.com">
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="view.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>

