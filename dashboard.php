<?php
// Dashboard index
require_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/header.php';

$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

$latestTermStmt = $pdo->query("SELECT academic_year, semester_id FROM enrollments ORDER BY academic_year DESC, semester_id DESC LIMIT 1");
$latestTerm = $latestTermStmt->fetch();
$active_academic_year = $latestTerm ? $latestTerm['academic_year'] : '2025-2026';
$active_semester = $latestTerm ? (int)$latestTerm['semester_id'] : 1;
$semesterLabel = $active_semester == 3 ? 'Summer' : $active_semester . ' Sem';
$todayLabel = date('M d, Y');

$totalEnrolledStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE academic_year = ? AND semester_id = ? AND status != 'Cancelled'");
$totalEnrolledStmt->execute([$active_academic_year, $active_semester]);
$totalEnrolled = (int)$totalEnrolledStmt->fetchColumn();

$totalPaymentsTermStmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN enrollments e ON p.enrollment_id = e.enrollment_id WHERE e.academic_year = ? AND e.semester_id = ? AND e.status != 'Cancelled'");
$totalPaymentsTermStmt->execute([$active_academic_year, $active_semester]);
$totalPaymentsTerm = (float)($totalPaymentsTermStmt->fetchColumn() ?: 0);

$totalAssessedStmt = $pdo->prepare("SELECT SUM(assessed_amount) FROM enrollments WHERE academic_year = ? AND semester_id = ? AND status != 'Cancelled'");
$totalAssessedStmt->execute([$active_academic_year, $active_semester]);
$totalAssessed = (float)($totalAssessedStmt->fetchColumn() ?: 0);
$collectionRate = $totalAssessed > 0 ? ($totalPaymentsTerm / $totalAssessed) * 100 : 0;

$recentRecordsStmt = $pdo->prepare("SELECT e.enrollment_id, e.student_id, e.total_units, e.status, e.enrollment_date, s.first_name, s.last_name, p.program_code FROM enrollments e JOIN students s ON e.student_id = s.student_id LEFT JOIN programs p ON e.program_id = p.program_id WHERE e.academic_year = ? AND e.semester_id = ? ORDER BY e.enrollment_date DESC LIMIT 8");
$recentRecordsStmt->execute([$active_academic_year, $active_semester]);
$recentRecords = $recentRecordsStmt->fetchAll();

$statusStmt = $pdo->prepare("SELECT status, COUNT(*) AS total_count FROM enrollments WHERE academic_year = ? AND semester_id = ? GROUP BY status ORDER BY total_count DESC");
$statusStmt->execute([$active_academic_year, $active_semester]);
$statusRows = $statusStmt->fetchAll();
$statusLabels = [];
$statusData = [];
foreach ($statusRows as $row) {
    $statusLabels[] = $row['status'] ?: 'Unknown';
    $statusData[] = (int)$row['total_count'];
}

$programStmt = $pdo->query("SELECT COALESCE(p.program_code, 'N/A') AS program_code, COUNT(*) AS total_count FROM students s LEFT JOIN programs p ON s.program_id = p.program_id GROUP BY p.program_code ORDER BY total_count DESC LIMIT 6");
$programRows = $programStmt->fetchAll();
$programLabels = [];
$programData = [];
foreach ($programRows as $row) {
    $programLabels[] = $row['program_code'];
    $programData[] = (int)$row['total_count'];
}

$monthCursor = new DateTime('first day of this month');
$monthCursor->modify('-5 months');
$monthLabels = [];
$enrollmentMap = [];
$paymentMap = [];
for ($i = 0; $i < 6; $i++) {
    $key = $monthCursor->format('Y-m');
    $monthLabels[] = $monthCursor->format('M Y');
    $enrollmentMap[$key] = 0;
    $paymentMap[$key] = 0;
    $monthCursor->modify('+1 month');
}

$enrollTrendStmt = $pdo->query("SELECT DATE_FORMAT(enrollment_date, '%Y-%m') AS month_key, COUNT(*) AS total_count FROM enrollments WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status != 'Cancelled' GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m') ORDER BY month_key ASC");
foreach ($enrollTrendStmt->fetchAll() as $row) {
    if (isset($enrollmentMap[$row['month_key']])) {
        $enrollmentMap[$row['month_key']] = (int)$row['total_count'];
    }
}

$paymentTrendStmt = $pdo->query("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_key, SUM(amount) AS total_amount FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month_key ASC");
foreach ($paymentTrendStmt->fetchAll() as $row) {
    if (isset($paymentMap[$row['month_key']])) {
        $paymentMap[$row['month_key']] = (float)$row['total_amount'];
    }
}

$chartStatusLabels = json_encode($statusLabels);
$chartStatusData = json_encode($statusData, JSON_NUMERIC_CHECK);
$chartProgramLabels = json_encode($programLabels);
$chartProgramData = json_encode($programData, JSON_NUMERIC_CHECK);
$chartMonthLabels = json_encode($monthLabels);
$chartEnrollmentSeries = json_encode(array_values($enrollmentMap), JSON_NUMERIC_CHECK);
$chartPaymentSeries = json_encode(array_values($paymentMap), JSON_NUMERIC_CHECK);
$chartStatusColors = json_encode(['#0d6efd', '#20c997', '#ffc107', '#dc3545', '#6f42c1', '#6c757d']);
?>

<style>
    .dashboard-page {
        --dash-primary: #0d6efd;
        --dash-text: #1f2f46;
        --dash-muted: #6d7f99;
        --dash-border: #dfe9f8;
        color: #1f2f46;
    }

    .dashboard-page .hero-band {
        border: 1px solid #dce8f8;
        border-radius: 20px;
        background: linear-gradient(145deg, #ffffff, #f5f9ff);
        box-shadow: 0 20px 40px -34px rgba(20, 51, 91, 0.55);
    }

    .dashboard-page .hero-title {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.2rem;
    }

    .dashboard-page .hero-subtitle {
        color: #647a97;
        margin-bottom: 0;
    }

    .dashboard-page .hero-meta {
        color: #647a97;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .dashboard-page .metric-tile {
        border: 1px solid #e2ebf9;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff, #fbfdff);
        padding: 0.95rem 1rem;
        height: 100%;
    }

    .dashboard-page .metric-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        margin-bottom: 0.25rem;
    }

    .dashboard-page .metric-icon {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eaf2ff;
        color: var(--dash-primary);
        font-size: 0.84rem;
        flex-shrink: 0;
    }

    .dashboard-page .metric-label {
        color: #6d7f99;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.3rem;
    }

    .dashboard-page .metric-value {
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.01em;
    }

    .dashboard-page .chart-card {
        border: 1px solid var(--dash-border);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 36px -34px rgba(21, 48, 85, 0.46);
        height: auto;
        overflow: hidden;
    }

    .dashboard-page .chart-card .card-header {
        border-bottom: 1px solid #e3ebf8;
        background: linear-gradient(180deg, #fbfdff, #f2f7ff);
        padding: 0.75rem 1rem;
    }

    .dashboard-page .chart-card .card-header h5 {
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .dashboard-page .records-link-btn {
        font-size: 0.76rem;
        font-weight: 700;
        padding: 0.3rem 0.65rem;
    }

    .dashboard-page .chart-body {
        padding: 1rem;
        min-height: 290px;
    }

    .dashboard-page .mini-chart-card {
        height: 100%;
    }

    .dashboard-page .mini-chart-body {
        min-height: 260px;
    }

    .dashboard-page .logs-column {
        display: flex;
    }

    .dashboard-page .logs-card {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
    }

    .dashboard-page .records-log-wrap {
        flex: 1;
        max-height: none;
        overflow: auto;
        padding: 0.75rem;
        background: linear-gradient(180deg, #fbfdff, #f7faff);
    }

    .dashboard-page .records-log-wrap::-webkit-scrollbar {
        width: 8px;
    }

    .dashboard-page .records-log-wrap::-webkit-scrollbar-thumb {
        background: #c6d8f4;
        border-radius: 999px;
    }

    .dashboard-page .record-log-item {
        border: 1px solid #e2ebf9;
        border-radius: 12px;
        padding: 0.65rem 0.72rem;
        background: #fff;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }

    .dashboard-page .record-log-item:hover {
        border-color: #cddcf5;
        transform: translateY(-1px);
    }

    .dashboard-page .record-log-item + .record-log-item {
        margin-top: 0.6rem;
    }

    .dashboard-page .log-name {
        font-weight: 700;
        color: #22344c;
        line-height: 1.2;
    }

    .dashboard-page .log-id {
        color: #73859c;
        font-size: 0.75rem;
        margin-top: 0.15rem;
    }

    .dashboard-page .log-date {
        color: #72839b;
        font-size: 0.78rem;
        white-space: nowrap;
    }

    .dashboard-page .log-meta {
        color: #5e728d;
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }

    .dashboard-page .log-meta-row {
        margin-top: 0.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.6rem;
    }

    .dashboard-page .log-avatar {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, #edf3ff, #d9e8ff);
        color: var(--dash-primary);
        flex-shrink: 0;
    }

</style>

<div class="dashboard-page">
    <div class="hero-band p-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="hero-title">Dashboard Overview</h2>
                <p class="hero-subtitle">Academic term performance snapshot for enrollment, collection, and records.</p>
            </div>
            <div class="text-lg-end">
                <div class="hero-meta mb-2">As Of <?= htmlspecialchars($todayLabel) ?></div>
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <span class="badge bg-primary-subtle text-primary">SY <?= htmlspecialchars($active_academic_year) ?></span>
                <span class="badge bg-success-subtle text-success"><?= $semesterLabel ?></span>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-tile">
                    <div class="metric-head">
                        <div class="metric-label">Registered Students</div>
                        <span class="metric-icon"><i class="fas fa-users"></i></span>
                    </div>
                    <div class="metric-value"><?= $totalStudents ?></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-tile">
                    <div class="metric-head">
                        <div class="metric-label">Enrolled This Term</div>
                        <span class="metric-icon"><i class="fas fa-user-check"></i></span>
                    </div>
                    <div class="metric-value text-primary"><?= $totalEnrolled ?></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-tile">
                    <div class="metric-head">
                        <div class="metric-label">Payments This Term</div>
                        <span class="metric-icon"><i class="fas fa-wallet"></i></span>
                    </div>
                    <div class="metric-value text-success">&#8369;<?= number_format($totalPaymentsTerm, 2) ?></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-tile">
                    <div class="metric-head">
                        <div class="metric-label">Collection Rate</div>
                        <span class="metric-icon"><i class="fas fa-percentage"></i></span>
                    </div>
                    <div class="metric-value text-info"><?= number_format($collectionRate, 1) ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="chart-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>6-Month Trends</h5>
                    <span class="text-muted small">Enrollments and Payments</span>
                </div>
                <div class="chart-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="chart-card mini-chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Term Status Mix</h5>
                        </div>
                        <div class="chart-body mini-chart-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card mini-chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>Program Distribution</h5>
                        </div>
                        <div class="chart-body mini-chart-body">
                            <canvas id="programChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 logs-column">
            <div class="chart-card logs-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-folder-open me-2 text-primary"></i>Enrollment Logs</h5>
                    <a href="<?= BASE_PATH ?>modules/enrollment/records.php?term=<?= urlencode($active_academic_year) ?>&sem=<?= urlencode($active_semester) ?>" class="btn btn-sm btn-outline-primary records-link-btn">
                        Open Full Records
                    </a>
                </div>
                <div class="records-log-wrap">
                    <?php if (count($recentRecords) > 0): ?>
                        <?php foreach ($recentRecords as $record): ?>
                            <?php
                                $statusBadge = 'bg-success';
                                if (($record['status'] ?? '') === 'Pending') {
                                    $statusBadge = 'bg-warning text-dark';
                                }
                                if (($record['status'] ?? '') === 'Cancelled') {
                                    $statusBadge = 'bg-danger';
                                }
                            ?>
                            <div class="record-log-item d-flex gap-2 align-items-start">
                                <div class="log-avatar"><i class="fas fa-user"></i></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="log-name"><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?></div>
                                            <div class="log-id">ID <?= htmlspecialchars($record['student_id']) ?></div>
                                        </div>
                                        <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($record['status']) ?></span>
                                    </div>
                                    <div class="log-meta-row">
                                        <div class="log-meta">
                                            <?= htmlspecialchars($record['program_code'] ?: 'N/A') ?>
                                            • <?= htmlspecialchars(number_format((float)$record['total_units'], 0)) ?> units
                                        </div>
                                        <div class="log-date"><?= htmlspecialchars(date('M d, Y', strtotime($record['enrollment_date']))) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">No records found for the active term.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const monthLabels = <?= $chartMonthLabels ?>;
    const enrollmentSeries = <?= $chartEnrollmentSeries ?>;
    const paymentSeries = <?= $chartPaymentSeries ?>;
    const statusLabels = <?= $chartStatusLabels ?>;
    const statusSeries = <?= $chartStatusData ?>;
    const programLabels = <?= $chartProgramLabels ?>;
    const programSeries = <?= $chartProgramData ?>;
    const statusColors = <?= $chartStatusColors ?>;

    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Enrollments',
                        data: enrollmentSeries,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.14)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.32,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        fill: true
                    },
                    {
                        type: 'bar',
                        label: 'Payments (PHP)',
                        data: paymentSeries,
                        backgroundColor: 'rgba(25, 135, 84, 0.28)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Enrollments' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            callback: (value) => 'P' + Number(value).toLocaleString()
                        },
                        title: { display: true, text: 'Payments (PHP)' }
                    }
                }
            }
        });
    }

    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels.length ? statusLabels : ['No Data'],
                datasets: [{
                    data: statusSeries.length ? statusSeries : [1],
                    backgroundColor: statusSeries.length ? statusColors : ['#cfd8e3']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    const programCtx = document.getElementById('programChart');
    if (programCtx) {
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: programLabels.length ? programLabels : ['No Data'],
                datasets: [{
                    label: 'Students',
                    data: programSeries.length ? programSeries : [0],
                    backgroundColor: '#7fb3ff',
                    borderColor: '#0d6efd',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
