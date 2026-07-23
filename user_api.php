<?php
// user_api.php — tài khoản học viên (email + mật khẩu)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();

$dbFile = __DIR__ . '/data/tramtrithuc.db';
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_likes (
        user_id INTEGER NOT NULL,
        document_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, document_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_saves (
        user_id INTEGER NOT NULL,
        document_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, document_id)
    )");
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = $json;
    } else {
        $input = $_POST;
    }
}
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

function json_out($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_user_id() {
    return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function require_user() {
    $id = current_user_id();
    if ($id <= 0) {
        json_out(['ok' => false, 'error' => 'Chưa đăng nhập', 'auth' => false]);
    }
    return $id;
}

function user_payload(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return null;

    $likes = $pdo->prepare("SELECT document_id FROM user_likes WHERE user_id = ?");
    $likes->execute([$userId]);
    $likeIds = array_map('intval', $likes->fetchAll(PDO::FETCH_COLUMN));

    $saves = $pdo->prepare("SELECT document_id FROM user_saves WHERE user_id = ?");
    $saves->execute([$userId]);
    $saveIds = array_map('intval', $saves->fetchAll(PDO::FETCH_COLUMN));

    return [
        'id' => (int)$u['id'],
        'email' => $u['email'],
        'name' => $u['name'] ?: explode('@', $u['email'])[0],
        'likedIds' => $likeIds,
        'savedIds' => $saveIds,
    ];
}

// --- actions ---

if ($action === 'me') {
    $uid = current_user_id();
    if ($uid <= 0) {
        json_out(['ok' => true, 'user' => null]);
    }
    json_out(['ok' => true, 'user' => user_payload($pdo, $uid)]);
}

if ($action === 'register') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';
    $name = trim($input['name'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'error' => 'Email không hợp lệ (dùng Gmail hoặc email thật).']);
    }
    if (strlen($password) < 6) {
        json_out(['ok' => false, 'error' => 'Mật khẩu tối thiểu 6 ký tự.']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (?,?,?)");
        $stmt->execute([$email, $hash, $name]);
        $uid = (int)$pdo->lastInsertId();
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $email;
        json_out(['ok' => true, 'user' => user_payload($pdo, $uid)]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => 'Email đã được đăng ký.']);
    }
}

if ($action === 'login') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        json_out(['ok' => false, 'error' => 'Email hoặc mật khẩu không đúng.']);
    }
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['user_email'] = $email;
    json_out(['ok' => true, 'user' => user_payload($pdo, (int)$row['id'])]);
}

if ($action === 'logout') {
    unset($_SESSION['user_id'], $_SESSION['user_email']);
    json_out(['ok' => true]);
}

if ($action === 'toggle_like') {
    $uid = require_user();
    $docId = (int)($input['document_id'] ?? $_GET['document_id'] ?? 0);
    if ($docId <= 0) json_out(['ok' => false, 'error' => 'Thiếu document_id']);

    $check = $pdo->prepare("SELECT 1 FROM user_likes WHERE user_id = ? AND document_id = ?");
    $check->execute([$uid, $docId]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM user_likes WHERE user_id = ? AND document_id = ?")->execute([$uid, $docId]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO user_likes (user_id, document_id) VALUES (?,?)")->execute([$uid, $docId]);
        $liked = true;
    }
    json_out(['ok' => true, 'liked' => $liked, 'user' => user_payload($pdo, $uid)]);
}

if ($action === 'toggle_save') {
    $uid = require_user();
    $docId = (int)($input['document_id'] ?? $_GET['document_id'] ?? 0);
    if ($docId <= 0) json_out(['ok' => false, 'error' => 'Thiếu document_id']);

    $check = $pdo->prepare("SELECT 1 FROM user_saves WHERE user_id = ? AND document_id = ?");
    $check->execute([$uid, $docId]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM user_saves WHERE user_id = ? AND document_id = ?")->execute([$uid, $docId]);
        $saved = false;
    } else {
        $pdo->prepare("INSERT INTO user_saves (user_id, document_id) VALUES (?,?)")->execute([$uid, $docId]);
        $saved = true;
    }
    json_out(['ok' => true, 'saved' => $saved, 'user' => user_payload($pdo, $uid)]);
}

if ($action === 'my_library') {
    $uid = require_user();
    $type = $_GET['type'] ?? 'saves'; // saves | likes

    if ($type === 'likes') {
        $stmt = $pdo->prepare("SELECT d.id, d.title, d.category, d.author, d.short_desc as shortDesc,
            d.size, d.type, COALESCE(d.views,0) as views
            FROM user_likes ul
            JOIN documents d ON d.id = ul.document_id AND d.status = 1
            WHERE ul.user_id = ?
            ORDER BY ul.created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT d.id, d.title, d.category, d.author, d.short_desc as shortDesc,
            d.size, d.type, COALESCE(d.views,0) as views
            FROM user_saves us
            JOIN documents d ON d.id = us.document_id AND d.status = 1
            WHERE us.user_id = ?
            ORDER BY us.created_at DESC");
    }
    $stmt->execute([$uid]);
    json_out(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

json_out(['ok' => false, 'error' => 'Invalid action']);
