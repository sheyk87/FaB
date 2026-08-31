<?php
/**
 * Security & OWASP Top 10 Compliance Helpers
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

// Configure secure session parameters before starting
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Cookie lifetime 7 days
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Security Headers (OWASP recommendations)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

class Security {
    /**
     * Generate or retrieve CSRF token for the session
     */
    public static function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token from header or post body
     */
    public static function validateCsrfToken(?string $token = null): bool {
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Enforce CSRF check for state-mutating requests
     */
    public static function requireCsrf(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!self::validateCsrfToken()) {
                self::jsonResponse(['success' => false, 'error' => 'Invalid or missing CSRF token'], 403);
            }
        }
    }

    /**
     * Require authenticated user
     */
    public static function requireAuth(): array {
        if (empty($_SESSION['user_id'])) {
            self::jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Player',
            'display_name' => $_SESSION['display_name'] ?? 'Player'
        ];
    }

    /**
     * Get current user or null
     */
    public static function getCurrentUser(): ?array {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Player',
            'display_name' => $_SESSION['display_name'] ?? 'Player',
            'avatar' => $_SESSION['avatar'] ?? 'hero_default'
        ];
    }

    /**
     * Escape output for HTML context (XSS defense)
     */
    public static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize general string input
     */
    public static function sanitizeString(string $input, int $maxLength = 255): string {
        $trimmed = trim($input);
        $stripped = strip_tags($trimmed);
        return mb_substr($stripped, 0, $maxLength, 'UTF-8');
    }

    /**
     * Standard JSON response helper with proper HTTP code
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Get parsed JSON input body safely
     */
    public static function getJsonInput(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Simple IP-based / session rate limiter
     */
    public static function rateLimit(string $action, int $maxAttempts = 60, int $decaySeconds = 60): bool {
        $key = 'rate_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }

        if ($now - $_SESSION[$key]['start'] > $decaySeconds) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }

        $_SESSION[$key]['count']++;
        if ($_SESSION[$key]['count'] > $maxAttempts) {
            return false;
        }

        return true;
    }
}
