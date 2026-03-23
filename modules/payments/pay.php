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

<style>
    .pay-ledger .page-head {
        border: 1px solid #d9e6fb;
        border-radius: 18px;
        background: linear-gradient(145deg, #ffffff, #f5f9ff);
        padding: 1rem 1.15rem;
        box-shadow: 0 20px 38px -34px rgba(17, 58, 114, 0.6);
    }

    .pay-ledger .soft-card {
        border: 1px solid #dbe7f8;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 22px 40px -34px rgba(20, 55, 102, 0.62);
        overflow: hidden;
        height: 100%;
    }

    .pay-ledger .soft-head {
        border-bottom: 1px solid #e3ecf9;
        background: linear-gradient(145deg, #f8fbff, #f2f7ff);
        padding: 0.9rem 1rem;
    }

    .pay-ledger .metric-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.4rem;
    }

    .pay-ledger .balance-box {
        border: 1px solid #d8e7fb;
        border-radius: 12px;
        background: #f8fbff;
        padding: 0.65rem 0.75rem;
    }

    .pay-ledger .or-pill {
        border-radius: 999px;
        padding: 0.25rem 0.58rem;
        font-size: 0.76rem;
        font-weight: 700;
        background: #6c7b8d;
        color: #fff;
    }
</style>

<div class="pay-ledger">
    <div class="page-head d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-cash-register text-success me-2"></i>Payment Ledger</h2>
            <small class="text-muted">Track all payment activity and settle remaining balances.</small>
        </div>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-circle-check me-1"></i> Payment successfully recorded.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="soft-card mb-4">
                <div class="soft-head">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Student Account</h5>
                </div>
                <div class="p-3 p-md-4">
                    <p class="mb-2"><strong>ID:</strong> <?= htmlspecialchars($enrollment['student_id']) ?></p>
                    <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($enrollment['last_name'] . ', ' . $enrollment['first_name']) ?></p>
                    <p class="mb-2"><strong>Program:</strong> <?= htmlspecialchars($enrollment['program_code']) ?></p>
                    <p class="mb-3"><strong>Term:</strong> <?= htmlspecialchars($enrollment['academic_year'] . ' - ' . $enrollment['semester_id']) ?></p>

                    <div class="metric-row">
                        <span>Total Assessed</span>
                        <strong>&#8369;<?= number_format($enrollment['assessed_amount'], 2) ?></strong>
                    </div>
                    <div class="metric-row">
                        <span>Total Paid</span>
                        <strong class="text-success">- &#8369;<?= number_format($total_paid, 2) ?></strong>
                    </div>

                    <div class="balance-box mt-3 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Current Balance</span>
                        <span class="fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">&#8369;<?= number_format(max(0, $balance), 2) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($balance > 0): ?>
                <div class="soft-card mb-4">
                    <div class="soft-head">
                        <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>New Payment</h5>
                    </div>
                    <div class="p-3 p-md-4">
                        <form method="POST" class="d-grid gap-2">
                            <div>
                                <label class="form-label small text-muted fw-semibold mb-1">O.R. Number <span class="text-danger">*</span></label>
                                <input type="text" name="or_number" class="form-control" placeholder="e.g. OR-100234" required>
                            </div>
                            <div>
                                <label class="form-label small text-muted fw-semibold mb-1">Amount (&#8369;) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" max="<?= $balance ?>" name="amount" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label small text-muted fw-semibold mb-1">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Partial Tuition, Cash, etc.">
                            </div>
                            <button type="submit" class="btn btn-success w-100 mt-1"><i class="fas fa-floppy-disk me-1"></i>Submit Payment</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success text-center">
                    <h5 class="mb-1"><i class="fas fa-star me-1"></i>Fully Paid</h5>
                    <p class="mb-0">This enrollment term is fully settled.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="soft-card">
                <div class="soft-head d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0"><i class="fas fa-clock-rotate-left me-2"></i>Payment History</h5>
                    <a href="print_ledger.php?enrollment_id=<?= $enrollment_id ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print me-1"></i>Print Ledger
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>O.R. Number</th>
                                <th>Amount</th>
                                <th>Remarks</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($p['payment_date'])) ?></td>
                                        <td><span class="or-pill"><?= htmlspecialchars($p['or_number']) ?></span></td>
                                        <td class="text-success fw-bold">&#8369;<?= number_format($p['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($p['remarks']) ?></td>
                                        <td class="text-center">
                                            <a href="print_receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No payments recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>


