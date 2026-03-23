<?php
// modules/programs/index.php
// Programs listing and quick view
require_once '../../config/db.php';
include_once '../../includes/header.php';

$stmt = $pdo->query("SELECT * FROM programs ORDER BY program_code");
$programs = $stmt->fetchAll();
$totalPrograms = count($programs);

$departmentCounts = [];
foreach ($programs as $item) {
    $deptName = trim($item['department'] ?? '') ?: 'General';
    if (!isset($departmentCounts[$deptName])) {
        $departmentCounts[$deptName] = 0;
    }
    $departmentCounts[$deptName]++;
}

$totalDepartments = count($departmentCounts);
?>

<style>
    .programs-page .hero-shell {
        border: 1px solid #dce8f8;
        border-radius: 22px;
        background: linear-gradient(145deg, #ffffff, #f4f9ff);
        box-shadow: 0 24px 44px -36px rgba(23, 64, 122, 0.65);
        padding: 1.2rem;
    }

    .programs-page .hero-chip {
        border: 1px solid #dce8f9;
        border-radius: 14px;
        background: #fff;
        text-align: center;
        padding: 0.68rem 0.78rem;
        height: 100%;
    }

    .programs-page .hero-chip .value {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1a4fa7;
        line-height: 1;
    }

    .programs-page .hero-chip .label {
        margin-top: 0.24rem;
        font-size: 0.76rem;
        font-weight: 700;
        color: #70839f;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }

    .programs-page .hero-new-btn {
        min-height: 44px;
    }

    .programs-page .program-list {
        border: 1px solid #d6e5fa;
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
    }

    .programs-page .program-strip {
        display: grid;
        grid-template-columns: minmax(120px, 150px) 1fr auto;
        gap: 1rem;
        align-items: center;
        padding: 0.95rem 1rem;
        border-bottom: 1px solid #e8effa;
        background: linear-gradient(90deg, #f7fbff 0, #ffffff 55%);
    }

    .programs-page .program-strip:last-child {
        border-bottom: none;
    }

    .programs-page .program-strip:hover {
        background: linear-gradient(90deg, #edf5ff 0, #fbfdff 55%);
    }

    .programs-page .program-code {
        font-size: 1.2rem;
        font-weight: 800;
        color: #1260dd;
        margin-bottom: 0.25rem;
    }

    .programs-page .program-name {
        color: #22344d;
        font-size: 1rem;
        margin-bottom: 0.38rem;
    }

    .programs-page .dept-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.38rem;
        border-radius: 999px;
        border: 1px solid #d7e5fb;
        background: #f3f8ff;
        color: #355883;
        font-size: 0.76rem;
        font-weight: 700;
        padding: 0.28rem 0.58rem;
    }

    .programs-page .program-meta {
        min-width: 0;
    }

    .programs-page .program-action {
        min-width: 152px;
        text-align: center;
    }

    @media (max-width: 767.98px) {
        .programs-page .hero-shell .col-lg-5 .row {
            row-gap: 0.55rem;
        }

        .programs-page .program-strip {
            grid-template-columns: 1fr;
            gap: 0.65rem;
        }

        .programs-page .program-action {
            width: 100%;
        }
    }
</style>

<div class="programs-page">
    <div class="hero-shell mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-7">
                <h2 class="mb-1"><i class="fas fa-book-open-reader text-primary me-2"></i>Academic Programs</h2>
                <p class="text-muted mb-0">Manage degree offerings, monitor department coverage, and open each curriculum quickly.</p>
            </div>
            <div class="col-lg-5">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="hero-chip">
                            <div class="value"><?= $totalPrograms ?></div>
                            <div class="label">Programs</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="hero-chip">
                            <div class="value"><?= $totalDepartments ?></div>
                            <div class="label">Departments</div>
                        </div>
                    </div>
                    <div class="col-4 d-grid">
                        <button class="btn btn-success btn-sm hero-new-btn"><i class="fas fa-plus me-1"></i>New Program</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totalPrograms > 0): ?>
        <div class="program-list">
            <?php foreach ($programs as $prog): ?>
                <div class="program-strip">
                    <div class="program-code mb-0"><?= htmlspecialchars($prog['program_code']) ?></div>
                    <div class="program-meta">
                        <div class="program-name"><?= htmlspecialchars($prog['program_name']) ?></div>
                        <span class="dept-badge"><i class="fas fa-building-columns"></i><?= htmlspecialchars(trim($prog['department'] ?? '') ?: 'General') ?></span>
                    </div>
                    <a href="view.php?id=<?= $prog['program_id'] ?>" class="btn btn-sm btn-outline-primary program-action">
                        <i class="fas fa-sitemap me-1"></i>View Curriculum
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No programs defined yet in the programs table.</div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?>
