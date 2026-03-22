<?php
// modules/payments/pay.php
// Individual payment page showing history and accepting new payment
require_once '../../config/db.php';
include_once '../../includes/header.php';

$enrollment_id = $_GET['enrollment_id'] ?? '';

if (!$enrollment_id) {
    die('Invalid enrollment ID.');
}

// Fetch Enrollment and Student Info
$stmt = $pdo->prepare("
    SELECT e.*, s.first_name, s.last_name, p.program_code 
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE e.enrollment_id = ?
");
$stmt->execute([$enrollment_id]);
$enrollment = $stmt->fetch();

if (!$enrollment) die('Enrollment not found.');

// Fetch existing payments
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE enrollment_id = ? ORDER BY payment_date DESC");
$payStmt->execute([$enrollment_id]);
$payments = $payStmt->fetchAll();

$total_paid = 0;
foreach($payments as $p) $total_paid += $p['amount'];

$balance = $enrollment['assessed_amount'] - $total_paid;

// Process new payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = (float)$_POST['amount'];
    $or_number = $_POST['or_number'];
    $remarks = $_POST['remarks'] ?? '';

    if ($amount > 0 && $amount <= $balance) {
        $ins = $pdo->prepare("INSERT INTO payments (student_id, enrollment_id, or_number, amount, remarks) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$enrollment['student_id'], $enrollment_id, $or_number, $amount, $remarks]);
        
        header("Location: pay.php?enrollment_id=$enrollment_id&success=1");
        exit;
    } else {
        $error = "Invalid amount. Ensure amount is greater than 0 and does not exceed balance.";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h2><i class="fas fa-cash-register text-success"></i> Payment Ledger</h2>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> Payment successfully recorded!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Account Summary -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Student Account</h5>
            </div>
            <div class="card-body">
                <p><strong>ID:</strong> <?= htmlspecialchars($enrollment['student_id']) ?></p>
                <p><strong>Name:</strong> <?= htmlspecialchars($enrollment['last_name'] . ', ' . $enrollment['first_name']) ?></p>
                <p><strong>Program:</strong> <?= htmlspecialchars($enrollment['program_code']) ?></p>
                <p><strong>Term:</strong> <?= htmlspecialchars($enrollment['academic_year'] . ' - ' . $enrollment['semester_id']) ?></p>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Assessed:</span>
                    <span class="fw-bold">â‚±<?= number_format($enrollment['assessed_amount'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Paid:</span>
                    <span class="text-success fw-bold">- â‚±<?= number_format($total_paid, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mt-3 p-2 bg-light border rounded">
                    <span class="fs-5">Current Balance:</span>
                    <span class="fs-5 <?= $balance > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
                        â‚±<?= number_format(max(0, $balance), 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($balance > 0): ?>
            <!-- Add Payment Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> New Payment</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>O.R. Number <span class="text-danger">*</span></label>
                            <input type="text" name="or_number" class="form-control" placeholder="e.g. OR-100234" required>
                        </div>
                        <div class="mb-3">
                            <label>Amount (â‚±) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" max="<?= $balance ?>" name="amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Remarks</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Partial Tuition, Cash, etc.">
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Submit Payment</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success text-center">
                <h5><i class="fas fa-star"></i> Fully Paid</h5>
                <p class="mb-0">This enrollment term is fully settled.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment History Table -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history"></i> Payment History</h5>
                <a href="print_ledger.php?enrollment_id=<?= $enrollment_id ?>" target="_blank" class="btn btn-sm btn-outline-light"><i class="fas fa-print"></i> Print Ledger</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>O.R. Number</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                              <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($payments) > 0): ?>
                            <?php foreach($payments as $p): ?>
                                <tr>
                                    <td><?= date('M d, Y h:i A', strtotime($p['payment_date'])) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['or_number']) ?></span></td>
                                    <td class="text-success fw-bold">â‚±<?= number_format($p['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($p['remarks']) ?></td>
                                      <td><a href="print_receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Receipt"><i class="fas fa-print"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No payments recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>


