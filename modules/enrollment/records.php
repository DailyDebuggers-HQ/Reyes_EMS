<?php
// modules/enrollment/records.php
// To view all historical enrollments
require_once '../../config/db.php';
include_once '../../includes/header.php';

$term = $_GET['term'] ?? '2025-2026';
$sem = $_GET['sem'] ?? 1;

$stmt = $pdo->prepare("
    SELECT e.*, s.first_name, s.last_name, p.program_code
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE e.academic_year = ? AND e.semester_id = ?
    ORDER BY e.enrollment_date DESC
");
$stmt->execute([$term, $sem]);
$records = $stmt->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h2><i class="fas fa-folder-open text-primary"></i> Enrollment Records</h2>
        <p class="text-muted">View all enrolled students for the specific term.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body bg-light pb-0">
        <form method="GET" class="row">
            <div class="col-md-3 mb-3">
                <label>Academic Year</label>
                <input type="text" name="term" class="form-control" value="<?= htmlspecialchars($term) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label>Semester</label>
                <select name="sem" class="form-select">
                    <option value=1 <?= $sem==1?'selected':'' ?>>1st Sem</option>
                    <option value=2 <?= $sem==2?'selected':'' ?>>2nd Sem</option>
                    <option value=3 <?= $sem==3?'selected':'' ?>>Summer</option>
                </select>
            </div>
            <div class="col-md-3 mb-3 mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Load Records</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Program</th>
                    <th>Total Units</th>
                    <th>Assessment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($records) > 0): ?>
                    <?php foreach($records as $rec): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($rec['student_id']) ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($rec['last_name'] . ', ' . $rec['first_name']) ?></td>
                            <td><?= htmlspecialchars($rec['program_code']) ?></td>
                            <td><?= htmlspecialchars($rec['total_units']) ?></td>
                            <td>₱<?= number_format($rec['assessed_amount'], 2) ?></td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($rec['status']) ?></span></td>
                            <td>
                                <a href="print_cor.php?id=<?= $rec['enrollment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-print"></i> COR
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4">No enrollment records found for <?= htmlspecialchars("$term $sem") ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>