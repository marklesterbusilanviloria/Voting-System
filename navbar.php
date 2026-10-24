<?php
// =============================================================
//  SHARED NAVBAR PARTIAL
//  Usage:  $navActive = 'home';  require INCLUDES . 'navbar.php';
//  $navActive values: 'home' | 'dashboard' | 'about'
// =============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';


$user      = currentUser();
$role      = $user['role'];
$navActive = $navActive ?? '';

// Home is the same page for everyone
$homeUrl = BASE_URL .'index.php';

if ($role === 'student') {
    $dashboardUrl =BASE_URL . 'student/dashboard.php';
    $homeUrl = BASE_URL . 'student/home.php';
} else {
    $dashboardUrl = BASE_URL . 'guest/dashboard.php';
}

$aboutUrl = BASE_URL . 'about.php';

function navLink(string $href, string $label, string $active, string $key): string {
    $cls = ($active === $key) ? 'nav-link active' : 'nav-link';
    return "<a href=\"$href\" class=\"$cls\">$label</a>";
}

?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
<nav class="navbar">
    <a href="<?= $homeUrl ?>" class="nav-logo">
        <div class="logo-icon">
            <img src="<?php echo BASE_URL;?>assets/img/icons/logo.png" alt="logo" style="width:100%;height:100%;object-fit:contain;"
                 onerror="this.style.display='none'">
        </div>
        <div class="logo-text">COS ONLINE VOTING SYSTEM</div>
    </a>

    <!-- Desktop nav links (hidden on mobile) -->
    <div class="nav-links">
        <?= navLink($homeUrl,      'Home',      $navActive, 'home') ?>
        <?php if ($role === 'student'): ?>
            <?php /* Pending/rejected students see "My Account" instead of "Dashboard" */ ?>
            <?= navLink($dashboardUrl, 'Dashboard', $navActive, 'dashboard') ?>
        <?php elseif ($role === 'guest'): ?>
            <?= navLink($dashboardUrl, 'Dashboard', $navActive, 'dashboard') ?>
        <?php endif; ?>
        <?= navLink($aboutUrl, 'About', $navActive, 'about') ?>
    </div>

    <!-- Desktop auth (hidden on mobile) -->
    <?php if ($role === 'guest' || !isLoggedIn()): ?>
    <div class="nav-auth">
        <a href="<?= BASE_URL ?>login.php" class="btn-login-oval<?php if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === BASE_URL . 'login.php') echo ' active'; ?>" style="text-decoration: none; display: inline-block;">
            Log In
        </a>
    </div>

    <?php elseif ($role === 'student'): ?>
    <!-- STUDENT dropdown (desktop) -->
    <div class="user-menu-container">
        <div class="user-greeting" onclick="toggleUserMenu(event)">
            Hello, <span><?= htmlspecialchars($user['first_name']) ?></span>
            <span class="arrow-icon">▼</span>
        </div>
        <div class="user-dropdown" id="userDropdownMenu">
            <div class="dropdown-header">
                <h3><?= htmlspecialchars($user['name']) ?></h3>
                <p>Student ID: <?= htmlspecialchars($user['student_id']) ?></p>
            </div>
            <div class="divider"></div>
            <a href="<?= BASE_URL ?>student/account.php" class="update-link">My Account</a>
            <form method="POST" action="<?= BASE_URL ?>logout.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit" class="btn-logout-oval">Log Out</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hamburger button (mobile only) -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>
</nav>

<!-- Mobile dropdown panel (sits below the navbar) -->
<div class="nav-mobile-panel" id="navMobilePanel">
    <!-- Nav links -->
    <?= navLink($homeUrl,      'Home',      $navActive, 'home') ?>
    <?php if ($role === 'student'): ?>
        <?= navLink($dashboardUrl, 'Dashboard', $navActive, 'dashboard') ?>
    <?php elseif ($role === 'guest'): ?>
        <?= navLink($dashboardUrl, 'Dashboard', $navActive, 'dashboard') ?>
    <?php endif; ?>
    <?= navLink($aboutUrl, 'About', $navActive, 'about') ?>

    <div class="nav-mobile-divider"></div>

    <!-- Auth / user section in mobile panel -->
    <div class="nav-mobile-auth">
        <?php if ($role === 'guest' || !isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>login.php"
               class="btn-login-oval<?php if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === BASE_URL . 'login.php') echo ' active'; ?>"
               style="text-decoration:none;text-align:center;">
                Log In
            </a>
        <?php elseif ($role === 'student'): ?>
            <div class="nav-mobile-user-info">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <span>Student ID: <?= htmlspecialchars($user['student_id']) ?></span>
            </div>
            <a href="<?= BASE_URL ?>student/account.php" class="update-link">My Account</a>
            <form method="POST" action="<?= BASE_URL ?>logout.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit" class="btn-logout-oval">Log Out</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var hamburger = document.getElementById('navHamburger');
    var panel     = document.getElementById('navMobilePanel');

    hamburger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = panel.classList.toggle('open');
        hamburger.classList.toggle('open', isOpen);
        hamburger.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && !hamburger.contains(e.target)) {
            panel.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
        }
    });

    panel.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            panel.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>