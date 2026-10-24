<?php
// =============================================================
//  includes/admin_sidebar.php
// =============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$currentUser = currentUser();
?>

<style>
/* ─────────────────────────────────────────────────────────────
   ADMIN SIDEBAR  —  matches the public navbar dark-green style
───────────────────────────────────────────────────────────── */
.sidebar {
    width: 270px;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;

    /* Same dark-green gradient used in the public navbar hero */
    background: linear-gradient(160deg, #1a3d26 0%, #2d5a3d 55%, #3a6b4a 100%);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.18);
    z-index: 100;
    overflow: hidden;
}

/* subtle noise/texture overlay so it doesn't look flat */
.sidebar::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 20% 0%, rgba(255,255,255,0.07) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 100%, rgba(0,0,0,0.15) 0%, transparent 60%);
    pointer-events: none;
}

/* ── Logo / brand ─────────────────────────────────────────── */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 28px 24px 22px;
    text-decoration: none;
    position: relative;
}

.sidebar-brand-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    backdrop-filter: blur(4px);
}

.sidebar-brand-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar-brand-text {
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 0.85rem;
    color: #ffffff;
    letter-spacing: 0.3px;
    line-height: 1.25;
    text-transform: uppercase;
}

/* divider under brand */
.sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.12);
    margin: 0 24px 20px;
}

/* ── User profile strip ───────────────────────────────────── */
.sidebar-profile {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 0 10px 24px;
    position: relative;
}

.sidebar-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 1.1rem;
    color: #fff;
}

.sidebar-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar-user-name {
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 0.88rem;
    color: #ffffff;
    line-height: 1.3;
}

.sidebar-user-role {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.58);
    font-weight: 600;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

/* ── Nav links ────────────────────────────────────────────── */
.sidebar-nav {
    flex: 1;
    padding: 0 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 10px;
    text-decoration: none;
    font-family: 'Geist', sans-serif;
    font-weight: 600;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.72);
    transition: background 0.18s, color 0.18s, transform 0.15s;
    position: relative;
    letter-spacing: 0.1px;
}

.sidebar-nav a .nav-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    opacity: 0.85;
    transition: opacity 0.18s;
}

.sidebar-nav a:hover {
    background: rgba(255,255,255,0.10);
    color: #ffffff;
    transform: translateX(3px);
}

.sidebar-nav a:hover .nav-icon {
    opacity: 1;
}

/* Active state — pill highlight matching the public "Home" nav button */
.sidebar-nav a.active {
    background: rgba(255,255,255,0.18);
    color: #ffffff;
    font-weight: 700;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
}

.sidebar-nav a.active .nav-icon {
    opacity: 1;
}

/* small left accent bar on active item */
.sidebar-nav a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: #ffffff;
    border-radius: 0 3px 3px 0;
    opacity: 0.7;
}

/* ── Logout section ───────────────────────────────────────── */
.sidebar-footer {
    padding: 20px 14px 28px;
    border-top: 1px solid rgba(255,255,255,0.12);
    position: relative;
}

.sidebar-logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.13);
    color: rgba(255,255,255,0.7);
    font-family: 'Geist', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.18s, color 0.18s, transform 0.15s;
    letter-spacing: 0.1px;
}

.sidebar-logout-btn:hover {
    background: rgba(220, 38, 38, 0.22);
    border-color: rgba(220, 38, 38, 0.35);
    color: #fca5a5;
    transform: translateX(2px);
}

.sidebar-logout-btn .nav-icon {
    width: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
</style>

<!-- Sidebar overlay (mobile: tap outside to close) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar toggle button (mobile only) -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="false">
    <span></span>
    <span></span>
    <span></span>
</button>

<aside class="sidebar" id="adminSidebar">

    <!-- Brand -->
    <a href="/admin/dashboard.php" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <img src="/assets/img/icons/logo.png"
                 alt="COS Logo"
                 onerror="this.parentElement.innerHTML='🗳️'">
        </div>
        <span class="sidebar-brand-text">COS Online<br>Voting System</span>
    </a>

    <div class="sidebar-divider"></div>

  

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php"
           class="<?= ($sidebarActive ?? '') === 'dashboard' ? 'active' : '' ?>">
           <span><img src = /assets/img/icons/dashboard.png></span>
            Dashboard
        </a>
        <a href="/admin/accounts.php"
           class="<?= ($sidebarActive ?? '') === 'accounts' ? 'active' : '' ?>">
           <span><img src = /assets/img/icons/manageAccount.png></span>
            Manage Accounts
        </a>
        <a href="/admin/elections.php"
           class="<?= ($sidebarActive ?? '') === 'elections' ? 'active' : '' ?>">
           <span><img src = /assets/img/icons/election.png></span>
            Manage Elections
        </a>
        <a href="/admin/candidates.php"
           class="<?= ($sidebarActive ?? '') === 'candidates' ? 'active' : '' ?>">
           <span><img src = /assets/img/icons/addCandidate.png></span>
            Manage Candidates
        </a>
    </nav>

    <!-- Logout -->
     
    <div class="sidebar-footer">
          <!-- User profile -->
    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php
            $initials = strtoupper(mb_substr($currentUser['first_name'] ?? 'A', 0, 1));
            $photo    = $currentUser['profile_pic'] ?? null;
            if ($photo):
            ?>
                <img src="/<?= htmlspecialchars(ltrim($photo, '/')) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars($initials) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($currentUser['last_name'] ?? 'System Admin') ?></div>
            <div class="sidebar-user-role">Admin ID: <?= htmlspecialchars($currentUser['student_id'] ?? 'ADM-0000') ?></div>
        </div>
    </div>
        <form method="POST" action="/logout.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit"  class = "sidebar-logout-btn">Log Out</button>
            </form>
        <!-- <a href="logout.php" class="sidebar-logout-btn">
            Log Out
        </a> -->
    </div>

</aside>

<script>
(function () {
    var toggle   = document.getElementById('sidebarToggle');
    var sidebar  = document.getElementById('adminSidebar');
    var overlay  = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        toggle.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        toggle.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
})();
</script>