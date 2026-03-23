<?php
// modules/payments/index.php
// Student payment search and assessment overview
require_once '../../config/db.php';
include_once '../../includes/header.php';

$search = $_GET['search'] ?? '';

// Basic query to fetch enrollments and their payment status
$sql = "
    SELECT e.*, s.first_name, s.last_name, p.program_code,
           COALESCE((SELECT SUM(amount) FROM payments WHERE enrollment_id = e.enrollment_id), 0) as total_paid
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE e.status = 'Enrolled'
";

$params = [];
if ($search) {
    $sql .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY e.enrollment_date DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

$totalRecords = count($enrollments);
$totalAssessed = 0.0;
$totalPaidAll = 0.0;
$totalBalanceAll = 0.0;
$fullyPaidCount = 0;

foreach ($enrollments as $entry) {
    $assessed = (float)($entry['assessed_amount'] ?? 0);
    $paid = (float)($entry['total_paid'] ?? 0);
    $balance = max(0, $assessed - $paid);

    $totalAssessed += $assessed;
    $totalPaidAll += $paid;
    $totalBalanceAll += $balance;

    if ($balance <= 0) {
        $fullyPaidCount++;
    }
}

$withBalanceCount = max(0, $totalRecords - $fullyPaidCount);

?>

<style>
    .payments-page .hero-panel {
        border: 1px solid #dfe8f6;
        border-radius: 20px;
        background: linear-gradient(145deg, #ffffff, #f6fbff);
        box-shadow: 0 24px 42px -34px rgba(20, 51, 91, 0.55);
    }

    .payments-page .metric-chip {
        border: 1px solid #e3ebf8;
        border-radius: 14px;
        background: #fff;
        padding: 0.8rem 0.9rem;
        height: 100%;
    }

    .payments-page .metric-chip .label {
        color: #6d7f98;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.2rem;
    }

    .payments-page .metric-chip .value {
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .payments-page .search-shell {
        border: 1px solid #e1e9f7;
        border-radius: 16px;
        background: #fff;
    }

    .payments-page .id-pill {
        background: #eef4ff;
        color: #1f59b7;
        border: 1px solid #d8e6ff;
        border-radius: 999px;
        padding: 0.32rem 0.56rem;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .payments-page .status-pill {
        border-radius: 999px;
        padding: 0.28rem 0.55rem;
        font-size: 0.76rem;
        font-weight: 700;
    }

    .payments-page .status-paid {
        color: #0d7a49;
        border: 1px solid #bce7d1;
        background: #eaf8f1;
    }

    .payments-page .status-balance {
        color: #9a6515;
        border: 1px solid #f5dfb0;
        background: #fff7e6;
    }

    .payments-page .table td,
    .payments-page .table th {
        white-space: nowrap;
    }
</style>

<div class="payments-page">
    <div class="hero-panel p-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
                <h2 class="mb-1"><i class="fas fa-wallet me-2 text-primary"></i>Payment Hub</h2>
                <p class="text-muted mb-0">Track assessments, balances, and payment completion across enrolled students.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary">Enrollment Payment Monitoring</span>
        </div>

        <div class="row g-2">
            <div class="col-6 col-lg-3">
                <div class="metric-chip">
                    <div class="label">Student Records</div>
                    <div class="value"><?= $totalRecords ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-chip">
                    <div class="label">Total Assessed</div>
                    <div class="value text-primary">&#8369;<?= number_format($totalAssessed, 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-chip">
                    <div class="label">Total Paid</div>
                    <div class="value text-success">&#8369;<?= number_format($totalPaidAll, 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-chip">
                    <div class="label">Open Balance</div>
                    <div class="value text-warning">&#8369;<?= number_format($totalBalanceAll, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card search-shell mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-7 col-lg-6">
                    <label class="form-label small text-muted mb-1">Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Student ID, first name, or last name" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Search</button>
                </div>
                <div class="col-md-2 col-lg-2 d-grid">
                    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-rotate-left me-2"></i>Reset</a>
                </div>
                <div class="col-lg-2 text-lg-end text-muted small">
                    Fully Paid: <strong><?= $fullyPaidCount ?></strong><br>
                    With Balance: <strong><?= $withBalanceCount ?></strong>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Enrolled Student Payments</h5>
            <span class="text-muted small">Showing latest 50 records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Term</th>
                            <th>Assessed</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalRecords > 0): ?>
                            <?php foreach ($enrollments as $row): ?>
                                <?php
                                    $balance = max(0, (float)$row['assessed_amount'] - (float)$row['total_paid']);
                                    $statusClass = $balance <= 0 ? 'status-paid' : 'status-balance';
                                    $statusLabel = $balance <= 0 ? 'Fully Paid' : 'With Balance';
                                ?>
                                <tr>
                                    <td><span class="id-pill"><?= htmlspecialchars($row['student_id']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                    <td><?= htmlspecialchars($row['program_code'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['academic_year'] . ' - ' . ($row['semester_id'] == 3 ? 'Summer' : $row['semester_id'] . ' Sem')) ?></td>
                                    <td>&#8369;<?= number_format((float)$row['assessed_amount'], 2) ?></td>
                                    <td class="text-success fw-semibold">&#8369;<?= number_format((float)$row['total_paid'], 2) ?></td>
                                    <td class="fw-semibold text-warning">&#8369;<?= number_format($balance, 2) ?></td>
                                    <td><span class="status-pill <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td class="text-end">
                                        <a href="pay.php?enrollment_id=<?= $row['enrollment_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-coins me-1"></i>Manage Payments
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 d-block"></i>
                                    No enrolled students found for payment tracking.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
