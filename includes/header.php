<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_PATH . "login.php");
    exit;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isActive = static function (string $needle) use ($requestPath): string {
    return strpos($requestPath, $needle) !== false ? 'active' : '';
};

$links = [
    ['href' => BASE_PATH . 'dashboard.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'key' => '/dashboard.php'],
    ['href' => BASE_PATH . 'modules/students/index.php', 'icon' => 'fa-users', 'label' => 'Students', 'key' => '/modules/students/'],
    ['href' => BASE_PATH . 'modules/enrollment/index.php', 'icon' => 'fa-user-plus', 'label' => 'Enrollment', 'key' => '/modules/enrollment/'],
    ['href' => BASE_PATH . 'modules/programs/index.php', 'icon' => 'fa-book-open', 'label' => 'Programs', 'key' => '/modules/programs/'],
    ['href' => BASE_PATH . 'modules/payments/index.php', 'icon' => 'fa-money-bill-wave', 'label' => 'Payments', 'key' => '/modules/payments/'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K. Reyes Institute of Technology (KRIT)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_PATH ?>assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-topbar">
            <nav class="navbar navbar-expand-xl top-nav w-100">
                <div class="container-fluid p-0">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavigation" aria-controls="topNavigation" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="topNavigation">
                        <ul class="navbar-nav mx-auto mb-2 mb-xl-0 app-nav-links">
                            <?php foreach ($links as $link): ?>
                                <li class="nav-item">
                                    <a href="<?= $link['href'] ?>" class="nav-link <?= $isActive($link['key']) ?>">
                                        <i class="fas <?= $link['icon'] ?> me-2"></i><?= $link['label'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="d-flex justify-content-end">
                            <a href="<?= BASE_PATH ?>logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
                                <i class="fas fa-right-from-bracket me-1"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <main class="app-content">

