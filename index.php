<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K. Reyes Institute of Technology (KRIT) | Enrollment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --surface: #ffffff;
            --line: #e6ecf5;
            --text: #162338;
            --muted: #6f7e92;
            --primary: #0d6efd;
            --bg: #f5f8fd;
            --primary-dark: #0a58ca;
        }

        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 0%, #ffffff, transparent 35%),
                radial-gradient(circle at 90% 20%, #eaf2ff, transparent 35%),
                var(--bg);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line);
            overflow: visible;
            box-shadow: 0 8px 20px -18px rgba(16, 39, 69, 0.5);
        }

        .navbar .container {
            min-height: 56px;
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
        }

        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.2px;
            color: #10213a;
            position: relative;
            display: inline-flex;
            align-items: center;
            padding-left: 156px;
            line-height: 1.15;
            padding-top: 0;
            padding-bottom: 0;
            text-decoration: none;
            transition: opacity 0.2s ease;
        }

        .navbar-brand:hover {
            opacity: 0.95;
        }

        .navbar-brand img {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-22%);
            width: 136px;
            height: 136px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 18px 32px -18px rgba(13, 43, 82, 0.65);
        }

        .brand-title {
            font-size: 1.02rem;
            font-weight: 800;
            color: #162b48;
            line-height: 1;
        }

        .brand-subtitle {
            font-size: 0.72rem;
            color: #6a7e98;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-top: 0.2rem;
            display: block;
        }

        .nav-login-btn {
            border-radius: 12px;
            padding: 0.58rem 0.95rem;
            font-weight: 700;
            font-size: 0.93rem;
            box-shadow: 0 14px 24px -18px rgba(13, 110, 253, 0.8);
            background: linear-gradient(145deg, var(--primary), #3f95ff);
            border-color: var(--primary);
        }

        .nav-login-btn:hover {
            background: linear-gradient(145deg, var(--primary-dark), #2f86f0);
            border-color: var(--primary-dark);
        }

        .hero {
            padding: 7.3rem 0 4.5rem;
        }

        .hero-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 26px;
            box-shadow: 0 32px 70px -45px rgba(12, 34, 65, 0.55);
            padding: 2.2rem;
            overflow: hidden;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 700;
            border-radius: 999px;
            padding: 0.32rem 0.62rem;
            font-size: 0.77rem;
        }

        .hero h1 {
            font-size: clamp(2rem, 6vw, 3.4rem);
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .hero p {
            color: var(--muted);
            font-size: 1rem;
            max-width: 52ch;
        }

        .btn-hero {
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-weight: 700;
        }

        .metric-strip {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .metric-box {
            background: #f8fbff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0.95rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .metric-box:hover {
            transform: translateY(-2px);
            border-color: #d6e4fb;
        }

        .metric-box h3 {
            font-size: 1.35rem;
            margin: 0;
            font-weight: 800;
        }

        .metric-box p {
            margin: 0;
            color: var(--muted);
            font-size: 0.88rem;
        }

        .hero-visual {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid var(--line);
            box-shadow: 0 22px 48px -35px rgba(20, 45, 76, 0.55);
            min-height: 330px;
            background: #dfe9f8;
        }

        .hero-visual img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .hero-overlay {
            position: absolute;
            inset: auto 12px 12px 12px;
            background: rgba(11, 30, 56, 0.72);
            color: #eef5ff;
            border: 1px solid rgba(152, 191, 245, 0.45);
            border-radius: 14px;
            padding: 0.78rem 0.85rem;
            backdrop-filter: blur(3px);
        }

        .hero-overlay .title {
            font-weight: 800;
            font-size: 0.9rem;
            margin-bottom: 0.15rem;
            letter-spacing: 0.01em;
        }

        .hero-overlay .desc {
            font-size: 0.78rem;
            color: #d5e5ff;
            margin: 0;
        }

        .feature {
            padding: 4.25rem 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .section-title h2 {
            font-weight: 800;
            margin-bottom: 0.4rem;
        }

        .section-title p {
            color: var(--muted);
            margin: 0;
        }

        .feature-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            height: 100%;
            padding: 1.4rem;
            box-shadow: 0 20px 45px -38px rgba(24, 46, 75, 0.45);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
            border-color: #d6e4fb;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: var(--primary);
            background: #edf3ff;
            margin-bottom: 0.9rem;
        }

        .feature-card h5 {
            font-weight: 800;
        }

        .feature-card p {
            color: var(--muted);
            margin: 0;
        }

        .footer {
            border-top: 1px solid var(--line);
            padding: 1rem 0;
            color: var(--muted);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 767px) {
            .navbar .container {
                min-height: 54px;
            }

            .navbar-brand {
                padding-left: 108px;
            }

            .navbar-brand img {
                width: 95px;
                height: 95px;
                top: -10px;
            }

            .brand-title {
                font-size: 0.92rem;
            }

            .brand-subtitle {
                font-size: 0.64rem;
            }

            .hero {
                padding-top: 6.4rem;
            }

            .metric-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="assets/img/logo.png" alt="K. Reyes Institute of Technology (KRIT)">
                <span>
                    <span class="brand-title">K. Reyes Institute of Technology (KRIT)</span>
                    <span class="brand-subtitle">Enrollment Management System</span>
                </span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="login.php" class="btn btn-primary nav-login-btn"><i class="fas fa-right-to-bracket me-2"></i>Portal Login</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-card">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <span class="hero-chip bg-primary-subtle text-primary mb-3"><i class="fas fa-shield-check"></i>Modernized Enrollment Platform</span>
                            <h1>Clean. Fast. Reliable Student Management.</h1>
                            <p class="mt-3 mb-4">A single system for enrollment workflows, student records, payment tracking, and report generation built for daily operations.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="login.php" class="btn btn-primary btn-hero">Open Dashboard</a>
                                <a href="#capabilities" class="btn btn-outline-primary btn-hero">View Capabilities</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="hero-visual">
                                <img src="assets/img/hero.png" alt="Campus and enrollment operations">
                                <div class="hero-overlay">
                                    <div class="title">Built for Registrar and Finance Teams</div>
                                    <p class="desc">From admission to payments, all core workflows are connected in one platform.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="metric-strip">
                        <div class="metric-box">
                            <h3>24/7</h3>
                            <p>System Availability</p>
                        </div>
                        <div class="metric-box">
                            <h3>All-in-1</h3>
                            <p>Enrollment + Payments</p>
                        </div>
                        <div class="metric-box">
                            <h3>Realtime</h3>
                            <p>Record Tracking</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="feature" id="capabilities">
            <div class="container">
                <div class="section-title">
                    <h2>Core Capabilities</h2>
                    <p>Everything your registrar and finance teams need to stay organized.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="fas fa-user-plus"></i></div>
                            <h5>Enrollment Flow</h5>
                            <p>Step-based enrollment and subject assignment with term-aware processing.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="fas fa-users"></i></div>
                            <h5>Student Profiles</h5>
                            <p>Manage student records, statuses, and enrollment history in one place.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <h5>Payment Tracking</h5>
                            <p>Handle assessments, payment logs, balances, and official receipts.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="fas fa-folder-open"></i></div>
                            <h5>Records Access</h5>
                            <p>Review term-based enrollment records quickly from the dashboard and records module.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span>K. Reyes Institute of Technology (KRIT) Enrollment Management System</span>
            <a href="login.php" class="text-decoration-none">Go to Portal</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
