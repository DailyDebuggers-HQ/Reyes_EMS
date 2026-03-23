<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - K. Reyes Institute of Technology (KRIT)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --surface: #ffffff;
            --line: #e4ebf5;
            --text: #162438;
            --muted: #718197;
            --primary: #0d6efd;
            --primary-soft: #edf3ff;
            --success-soft: #eaf7ef;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', 'Segoe UI', Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 10%, #ffffff, transparent 30%),
                radial-gradient(circle at 85% 20%, #e9f2ff, transparent 35%),
                #f4f7fb;
        }

        .login-wrapper {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.2rem;
        }

        .login-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 28px 65px -38px rgba(22, 36, 56, 0.45);
            width: 100%;
            max-width: 460px;
            padding: 2.2rem;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, #0d6efd, #58a8ff);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .login-logo {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 16px 28px -20px rgba(17, 44, 78, 0.55);
            margin-bottom: 0.6rem;
        }

        .login-kicker {
            display: inline-block;
            background: var(--success-soft);
            color: #18794e;
            border: 1px solid #cdebd8;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.3rem 0.6rem;
            letter-spacing: 0.04em;
            margin-bottom: 0.65rem;
            text-transform: uppercase;
        }

        .logo-container h2 {
            color: var(--text);
            font-weight: 800;
            margin: 0;
            letter-spacing: 0.2px;
            font-size: 1.45rem;
            line-height: 1.2;
        }

        .logo-container p {
            margin-top: 0.45rem;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .auth-kicker {
            margin-top: 0.35rem;
            font-size: 0.76rem;
            font-weight: 700;
            color: #6a7f9d;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .input-group {
            margin-bottom: 0.9rem;
        }

        .form-label-lite {
            font-size: 0.8rem;
            color: #667890;
            font-weight: 700;
            margin-bottom: 0.35rem;
            display: inline-block;
        }

        .input-group-text,
        .form-control {
            min-height: 48px;
            border-color: #d8e2ef;
        }

        .input-group-text {
            border-radius: 12px 0 0 12px;
            background: var(--primary-soft);
            color: var(--primary);
            border-right: none;
        }

        .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: #9bc0ff;
            box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.15);
        }

        .btn-login {
            background: linear-gradient(145deg, #0d6efd, #3f95ff);
            color: #fff;
            font-weight: 700;
            border-radius: 12px;
            padding: 0.75rem;
            width: 100%;
            border: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 18px 24px -20px rgba(13, 110, 253, 0.7);
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .back-link a:hover {
            color: var(--primary);
        }

        .login-card .divider {
            height: 1px;
            background: linear-gradient(90deg, rgba(130, 160, 200, 0), rgba(130, 160, 200, 0.45), rgba(130, 160, 200, 0));
            margin: 0.9rem 0 1.1rem;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-container">
                <img src="assets/img/logo.png" alt="K. Reyes Institute of Technology (KRIT)" class="login-logo">
                <span class="login-kicker">Secure Access</span>
                <h2>K. Reyes Institute of Technology (KRIT)</h2>
                <div class="auth-kicker">Enrollment Management Portal</div>
                <p>Sign in to continue to the enrollment system</p>
            </div>

            <div class="divider"></div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger text-center py-2">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <label class="form-label-lite">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
                </div>

                <label class="form-label-lite">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>

                <button type="submit" class="btn btn-login mt-2"><i class="fas fa-right-to-bracket me-2"></i>Sign In</button>
            </form>

            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left me-1"></i>Back to Homepage</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>