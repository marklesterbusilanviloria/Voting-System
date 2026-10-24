<?php
// =============================================================
//  AUTH HELPERS  —  include this at the top of every page
// =============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
}
// FORCE BROWSER TO NEVER CACHE THESE PAGES
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// ---- Guards ------------------------------------------------

/** Redirect to login if not logged in at all. */
function requireLogin(string $redirect = ''): void {
    if (empty($_SESSION['user_id'])) {
        header("Location: " . (BASE_URL ?? '/') . ltrim($redirect ?: 'login.php', '/'));
        exit;
    }
}

/** Redirect if logged-in user is not an admin. */
function requireAdmin(string $redirect = ''): void {
    requireLogin($redirect);
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: " . (BASE_URL ?? '/') . ltrim($redirect ?: 'login.php', '/'));
        exit;
    }
}

/**
 * Redirect if logged-in user is not a student.
 */
function requireStudent(string $redirect = ''): void {
    requireLogin($redirect);
    if (($_SESSION['role'] ?? '') !== 'student') {
        header("Location: " . (BASE_URL ?? '/') . ltrim($redirect ?: 'login.php', '/'));
        exit;
    }

    $status = $_SESSION['status'] ?? '';

    
}

/** Redirect already-logged-in users away from login/register pages. */
function redirectIfLoggedIn(): void {
    if (!empty($_SESSION['user_id'])) {
        $base   = BASE_URL ?? '/';
        $role   = $_SESSION['role']   ?? 'student';

        if ($role === 'admin') {
            header("Location: {$base}admin/dashboard.php");
        } elseif ($role === 'student') {
            header("Location: {$base}student/dashboard.php");
        } else {
            header("Location: {$base}login.php");
        }
        exit;
    }
}

// ---- Convenience getters -----------------------------------

function currentUser(): array {
    return [
        'id'         => $_SESSION['user_id']   ?? null,
        'name'       => $_SESSION['full_name'] ?? 'Guest',
        'first_name' => $_SESSION['first_name'] ?? 'Guest',
        'student_id' => $_SESSION['student_id'] ?? '',
        'role'       => $_SESSION['role']       ?? 'guest',
        'status'     => $_SESSION['status']     ?? '',
        'has_voted'  => $_SESSION['has_voted']  ?? 0,
    ];
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// ---- Session writer ----------------------------------------

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['full_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['status']     = $user['status'];
    $_SESSION['has_voted']  = $user['has_voted'];
}

// ---- CSRF helpers ------------------------------------------

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ---- Flash messages ----------------------------------------

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}