<?php
// ============================================================
//  includes/rate_limiter.php
//  Provides progressive delay + temporary lockout via DB.
// ============================================================

class RateLimiter {

    // ── Tuneable constants ───────────────────────────────────

    /** Max attempts before lockout kicks in */
    const MAX_ATTEMPTS = 6;

    /** How many minutes the account/IP stays locked */
    const LOCKOUT_MINUTES = 15;

    /** Attempts 4-5 → sleep this many seconds (soft throttle) */
    const SOFT_THROTTLE_SECONDS = 5;

    /** Attempts 6+ → sleep this many seconds right before locking */
    const HARD_THROTTLE_SECONDS = 10;

    /** Clean up records older than this many minutes (house-keeping) */
    const CLEANUP_OLDER_THAN_MINUTES = 60;

    // ────────────────────────────────────────────────────────

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Call this at the TOP of a form handler (before any DB auth checks).
     *
     * @param string $action     e.g. 'login' or 'vote'
     * @param string $identifier Optional secondary key (student_id).
     *                           Use '' if you only want IP-based limiting.
     *
     * @return void  — exits with HTTP 429 + JSON error if locked/throttled.
     *                 Sleeps (throttles) but continues if in soft-throttle zone.
     */
    public function check(string $action, string $identifier = ''): void {
        $ip     = $this->getClientIp();
        $record = $this->getRecord($ip, $action, $identifier);

        if ($record === null) {
            // First time we've seen this IP+action+identifier — nothing to do yet.
            return;
        }

        // ── Already locked out? ──────────────────────────────
        if ($record['locked_until'] !== null) {
            $lockedUntil = new DateTime($record['locked_until']);
            $now         = new DateTime();

            if ($now < $lockedUntil) {
                $remaining = $now->diff($lockedUntil);
                $minutes   = $remaining->i;
                $seconds   = $remaining->s;
                $this->respondLocked("Too many attempts. Try again in {$minutes}m {$seconds}s.");
            }

            // Lock has expired — reset the record so they can try again fresh.
            $this->resetRecord($ip, $action, $identifier);
            return;
        }

        // ── Soft throttle zone (attempts 4–5) ────────────────
        if ($record['attempts'] >= 4 && $record['attempts'] < self::MAX_ATTEMPTS) {
            sleep(self::SOFT_THROTTLE_SECONDS);
        }

        // ── Hard throttle then lock (attempts >= MAX) ────────
        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            sleep(self::HARD_THROTTLE_SECONDS);
            $this->lockRecord($ip, $action, $identifier);
            $this->respondLocked(
                "Too many failed attempts. You are locked out for " .
                self::LOCKOUT_MINUTES . " minutes."
            );
        }
    }

    /**
     * Call this when an attempt FAILS (wrong password, duplicate vote, etc.).
     * Increments the attempt counter.
     *
     * @param string $action
     * @param string $identifier
     */
    public function recordFailure(string $action, string $identifier = ''): void {
        $ip     = $this->getClientIp();
        $record = $this->getRecord($ip, $action, $identifier);

        if ($record === null) {
            // First failure — insert a new record.
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (ip_address, action, identifier, attempts, last_attempt)
                VALUES (:ip, :action, :identifier, 1, NOW())
            ");
            $stmt->execute([
                ':ip'         => $ip,
                ':action'     => $action,
                ':identifier' => $identifier,
            ]);
        } else {
            // Subsequent failure — increment attempts.
            $stmt = $this->pdo->prepare("
                UPDATE rate_limits
                SET    attempts     = attempts + 1,
                       last_attempt = NOW()
                WHERE  ip_address   = :ip
                  AND  action       = :action
                  AND  identifier   = :identifier
            ");
            $stmt->execute([
                ':ip'         => $ip,
                ':action'     => $action,
                ':identifier' => $identifier,
            ]);
        }

        // Trigger lockout immediately if this failure hits the ceiling.
        $updated = $this->getRecord($ip, $action, $identifier);
        if ($updated && $updated['attempts'] >= self::MAX_ATTEMPTS) {
            $this->lockRecord($ip, $action, $identifier);
        }

        $this->cleanup();
    }

    /**
     * Call this when an attempt SUCCEEDS.
     * Clears the rate-limit record so the counter resets cleanly.
     *
     * @param string $action
     * @param string $identifier
     */
    public function recordSuccess(string $action, string $identifier = ''): void {
        $this->resetRecord($this->getClientIp(), $action, $identifier);
    }

    // ── Private helpers ──────────────────────────────────────

    private function getRecord(string $ip, string $action, string $identifier): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM rate_limits
            WHERE  ip_address  = :ip
              AND  action      = :action
              AND  identifier  = :identifier
            LIMIT 1
        ");
        $stmt->execute([':ip' => $ip, ':action' => $action, ':identifier' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function lockRecord(string $ip, string $action, string $identifier): void {
        $stmt = $this->pdo->prepare("
            UPDATE rate_limits
            SET    locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)
            WHERE  ip_address   = :ip
              AND  action       = :action
              AND  identifier   = :identifier
        ");
        $stmt->execute([
            ':minutes'    => self::LOCKOUT_MINUTES,
            ':ip'         => $ip,
            ':action'     => $action,
            ':identifier' => $identifier,
        ]);
    }

    private function resetRecord(string $ip, string $action, string $identifier): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits
            WHERE  ip_address  = :ip
              AND  action      = :action
              AND  identifier  = :identifier
        ");
        $stmt->execute([':ip' => $ip, ':action' => $action, ':identifier' => $identifier]);
    }

    /** Remove stale unlocked records older than CLEANUP_OLDER_THAN_MINUTES */
    private function cleanup(): void {
        // Only run ~5% of the time to keep overhead low
        if (rand(1, 100) > 5) return;

        $this->pdo->prepare("
            DELETE FROM rate_limits
            WHERE  locked_until IS NULL
              AND  last_attempt < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ")->execute([':minutes' => self::CLEANUP_OLDER_THAN_MINUTES]);
    }

    /** Returns client IP, respects common proxy headers */
    private function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare (useful when you host online)
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can be a comma-separated list — take the first
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0'; // fallback
    }

    /** Send a 429 response and halt execution */
    private function respondLocked(string $message): never {
        http_response_code(429);

        // If the request expects JSON (AJAX), respond with JSON
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) &&
                     str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
        } else {
            // Plain HTML fallback — style it to match your theme if you like
            echo "<!DOCTYPE html><html><head><title>Too Many Attempts</title></head><body>";
            echo "<h2>⚠️ " . htmlspecialchars($message) . "</h2>";
            echo "<p><a href='/'>Go back</a></p>";
            echo "</body></html>";
        }

        exit;
    }
}