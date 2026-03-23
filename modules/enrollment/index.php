<?php
// modules/enrollment/index.php
// Enrollment Dashboard - New Intake Layout
require_once '../../config/db.php';
include_once '../../includes/header.php';
?>

<style>
    .enrollment-intake .intro-card {
        position: relative;
        border: 1px solid #d9e6fb;
        border-radius: 22px;
        background: linear-gradient(145deg, #ffffff, #f4f8ff);
        padding: 1.4rem;
        overflow: hidden;
        box-shadow: 0 24px 44px -36px rgba(18, 61, 122, 0.65);
    }

    .enrollment-intake .intro-card::after {
        content: '';
        position: absolute;
        right: -50px;
        top: -50px;
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(13, 110, 253, 0.2), rgba(13, 110, 253, 0));
        pointer-events: none;
    }

    .enrollment-intake .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.28rem 0.62rem;
        color: #0d4aa6;
        background: #e8f1ff;
    }

    .enrollment-intake .workflow-chip {
        border: 1px dashed #c8d8f6;
        border-radius: 999px;
        background: #fff;
        color: #3a587e;
        font-size: 0.76rem;
        font-weight: 700;
        padding: 0.26rem 0.6rem;
    }

    .enrollment-intake .action-card {
        border: 1px solid #e1ebfb;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 20px 38px -34px rgba(24, 56, 102, 0.58);
        height: 100%;
    }

    .enrollment-intake .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        font-size: 1.15rem;
        color: #0d6efd;
        background: linear-gradient(145deg, #e8f1ff, #f3f8ff);
        border: 1px solid #d8e6ff;
    }

    .enrollment-intake .term-preview {
        min-height: 24px;
        font-size: 0.88rem;
        font-weight: 700;
    }
</style>

<div class="enrollment-intake">
    <div class="intro-card mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <span class="eyebrow"><i class="fas fa-sparkles"></i> Enrollment Hub</span>
                <h2 class="mt-2 mb-1"><i class="fas fa-user-plus text-primary me-2"></i>Start New Student Enrollment</h2>
                <p class="text-muted mb-0">Use direct intake for quick enrollment, or open the masterlist for assisted selection.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="workflow-chip">1. Validate term</span>
                <span class="workflow-chip">2. Select student</span>
                <span class="workflow-chip">3. Build schedule</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="action-card p-4 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="action-icon"><i class="fas fa-id-card"></i></div>
                    <div>
                        <h4 class="mb-1">Direct Intake</h4>
                        <p class="text-muted mb-0">Enter the term code and student ID to proceed directly to enrollment step 1.</p>
                    </div>
                </div>

                <form action="step1.php" method="GET" class="row g-2">
                    <div class="col-md-5">
                        <label for="term_code" class="form-label small text-muted fw-semibold mb-1">Term Code</label>
                        <input
                            type="text"
                            name="term_code"
                            id="term_code"
                            class="form-control"
                            placeholder="e.g. 251"
                            required
                            title="251=2024-2025 1st Sem, 252=2nd Sem, 250=Summer"
                            onkeyup="previewTerm(this.value)"
                        >
                    </div>
                    <div class="col-md-7">
                        <label for="student_id" class="form-label small text-muted fw-semibold mb-1">Student ID</label>
                        <input
                            type="text"
                            name="student_id"
                            id="student_id"
                            class="form-control"
                            placeholder="e.g. 2025-001"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <div id="term_preview" class="term-preview text-primary"></div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-play me-2"></i>Proceed To Step 1
                        </button>
                        <a href="<?= BASE_PATH ?>modules/enrollment/records.php" class="btn btn-outline-secondary">
                            <i class="fas fa-folder-open me-2"></i>View Enrollment Records
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="action-card p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <h4 class="mb-1">Masterlist Assisted Flow</h4>
                        <p class="text-muted mb-0">Search, filter, and confirm student details first before starting enrollment.</p>
                    </div>
                </div>

                <div class="mt-2 small text-muted">
                    Recommended for enrollees with incomplete profile data or uncertain program/year details.
                </div>

                <div class="mt-auto pt-4 d-grid gap-2">
                    <a href="<?= BASE_PATH ?>modules/students/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i>Open Student Masterlist
                    </a>
                    <a href="<?= BASE_PATH ?>modules/students/index.php?status=Active" class="btn btn-light border">
                        <i class="fas fa-filter me-2"></i>Open Active Students
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewTerm(code) {
    const preview = document.getElementById('term_preview');
    if (code.length === 3) {
        const yrPrefix = parseInt(code.substring(0, 2), 10);
        const termSuffix = code.substring(2, 3);

        if (isNaN(yrPrefix)) {
            preview.innerHTML = '<span class="text-danger">Invalid term code</span>';
            return;
        }

        const endYear = 2000 + yrPrefix;
        const startYear = endYear - 1;
        const acadYear = startYear + '-' + endYear;

        let semesterId = '';
        if (termSuffix === '1') semesterId = '1st Semester';
        else if (termSuffix === '2') semesterId = '2nd Semester';
        else if (termSuffix === '0') semesterId = 'Summer';
        else {
            preview.innerHTML = '<span class="text-danger">Invalid term suffix</span>';
            return;
        }

        preview.innerHTML = `<i class="fas fa-check-circle me-1"></i>SY ${acadYear} | ${semesterId}`;
    } else {
        preview.innerHTML = '';
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>