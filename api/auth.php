<?php
/**
 * Auth API Endpoint
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/setup.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'session';

$pdo = Database::getConnection();

// Session / CSRF inquiry
if ($action === 'session') {
    $user = Security::getCurrentUser();
    $csrf = Security::getCsrfToken();

    Security::jsonResponse([
        'success' => true,
        'authenticated' => ($user !== null),
        'user' => $user,
        'csrf_token' => $csrf
    ]);
}

// Guest / Quick Play Login
if ($action === 'guest' && $method === 'POST') {
    Security::requireCsrf();

    $guestName = 'Hero_' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $username = strtolower($guestName);
    $passHash = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO `users` (`username`, `password_hash`, `display_name`, `avatar`, `is_online`, `last_seen`)
        VALUES (:u, :p, :d, 'hero_default', 1, NOW())
    ");
    $stmt->execute([':u' => $username, ':p' => $passHash, ':d' => $guestName]);
    $userId = (int)$pdo->lastInsertId();

    // Give guest starter decks
    createStarterDecks($pdo, $userId, $userId);

    // Regenerate session for fixation prevention
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['display_name'] = $guestName;
    $_SESSION['avatar'] = 'hero_default';

    Security::jsonResponse([
        'success' => true,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'display_name' => $guestName,
            'avatar' => 'hero_default'
        ],
        'csrf_token' => Security::getCsrfToken()
    ]);
}

// User Registration
if ($action === 'register' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $displayName = trim($input['display_name'] ?? $username);

    if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        Security::jsonResponse(['success' => false, 'error' => 'Username must be 3-30 alphanumeric characters.'], 400);
    }
    if (strlen($password) < 6) {
        Security::jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);
    }

    $chk = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = :u LIMIT 1");
    $chk->execute([':u' => $username]);
    if ($chk->fetch()) {
        Security::jsonResponse(['success' => false, 'error' => 'Username already taken.'], 409);
    }

    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("
        INSERT INTO `users` (`username`, `password_hash`, `display_name`, `avatar`, `is_online`, `last_seen`)
        VALUES (:u, :p, :d, 'hero_default', 1, NOW())
    ");
    $ins->execute([
        ':u' => $username,
        ':p' => $passHash,
        ':d' => Security::sanitizeString($displayName, 50)
    ]);
    $userId = (int)$pdo->lastInsertId();

    createStarterDecks($pdo, $userId, $userId);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['display_name'] = $displayName;
    $_SESSION['avatar'] = 'hero_default';

    Security::jsonResponse([
        'success' => true,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'display_name' => $displayName,
            'avatar' => 'hero_default'
        ],
        'csrf_token' => Security::getCsrfToken()
    ]);
}

// User Login
if ($action === 'login' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        Security::jsonResponse(['success' => false, 'error' => 'Invalid username or password.'], 401);
    }

    $pdo->prepare("UPDATE `users` SET `is_online` = 1, `last_seen` = NOW() WHERE `id` = :id")
        ->execute([':id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['avatar'] = $user['avatar'];

    Security::jsonResponse([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'avatar' => $user['avatar']
        ],
        'csrf_token' => Security::getCsrfToken()
    ]);
}

// User Logout
if ($action === 'logout' && $method === 'POST') {
    Security::requireCsrf();

    if (!empty($_SESSION['user_id'])) {
        $pdo->prepare("UPDATE `users` SET `is_online` = 0 WHERE `id` = :id")
            ->execute([':id' => $_SESSION['user_id']]);
        $pdo->prepare("DELETE FROM `matchmaking_queue` WHERE `user_id` = :id")
            ->execute([':id' => $_SESSION['user_id']]);
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    Security::jsonResponse(['success' => true, 'message' => 'Logged out successfully.']);
}

Security::jsonResponse(['success' => false, 'error' => 'Action not found'], 404);
