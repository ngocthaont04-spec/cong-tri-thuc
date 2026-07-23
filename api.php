<?php
// api.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$dbFile = __DIR__ . '/data/tramtrithuc.db';
if (!file_exists($dbFile)) {
    echo json_encode([]);
    exit;
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Migrations nhẹ
$cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
if (!in_array('views', $colNames, true)) {
    $pdo->exec("ALTER TABLE documents ADD COLUMN views INTEGER DEFAULT 0");
}
if (!in_array('sort_order', $colNames, true)) {
    $pdo->exec("ALTER TABLE documents ADD COLUMN sort_order INTEGER DEFAULT 0");
}
if (!in_array('category2', $colNames, true)) {
    $pdo->exec("ALTER TABLE documents ADD COLUMN category2 TEXT DEFAULT ''");
}
if (!in_array('shopee_clicks', $colNames, true)) {
    $pdo->exec("ALTER TABLE documents ADD COLUMN shopee_clicks INTEGER DEFAULT 0");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    icon TEXT DEFAULT '📁',
    sort_order INTEGER DEFAULT 0,
    status INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_likes (
    user_id INTEGER NOT NULL,
    document_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, document_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS link_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    note TEXT DEFAULT '',
    reporter TEXT DEFAULT '',
    status INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? 'list';
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;
    if (!empty($input['action'])) $action = $input['action'];
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT
            d.id,
            d.title,
            d.category,
            COALESCE(d.category2, '') as category2,
            d.author,
            d.short_desc as shortDesc,
            d.size,
            d.shopee_url as shopeeUrl,
            d.type,
            d.votes,
            COALESCE(d.views, 0) as views,
            COALESCE(d.sort_order, 0) as sortOrder,
            d.status,
            d.created_at as createdAt,
            d.updated_at as updatedAt,
            (SELECT COUNT(*) FROM user_likes ul WHERE ul.document_id = d.id) as likes
        FROM documents d
        WHERE d.status = 1
        ORDER BY d.id DESC");
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docs as &$d) {
        $d['likes'] = (int)$d['likes'];
        $d['views'] = (int)$d['views'];
        $d['sortOrder'] = (int)$d['sortOrder'];
        $d['category2'] = trim($d['category2'] ?? '');
        $cats = [$d['category']];
        if ($d['category2'] !== '' && $d['category2'] !== $d['category']) {
            $cats[] = $d['category2'];
        }
        $d['categories'] = $cats;
    }
    unset($d);
    echo json_encode($docs);
    exit;
}

if ($action === 'categories') {
    $hasCats = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($hasCats > 0) {
        $rows = $pdo->query("SELECT name, icon, sort_order FROM categories WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = [];
    }

    // Đếm bài theo cả category + category2
    $counts = [];
    $docsCats = $pdo->query("SELECT category, COALESCE(category2,'') as category2 FROM documents WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docsCats as $row) {
        $c1 = $row['category'];
        $c2 = trim($row['category2'] ?? '');
        if ($c1 !== '') $counts[$c1] = ($counts[$c1] ?? 0) + 1;
        if ($c2 !== '' && $c2 !== $c1) $counts[$c2] = ($counts[$c2] ?? 0) + 1;
    }

    $out = [];
    if ($rows) {
        foreach ($rows as $r) {
            $out[] = [
                'name' => $r['name'],
                'icon' => $r['icon'] ?: '📁',
                'count' => $counts[$r['name']] ?? 0,
                'sortOrder' => (int)$r['sort_order'],
            ];
        }
        foreach ($counts as $name => $count) {
            $found = false;
            foreach ($out as $o) {
                if ($o['name'] === $name) { $found = true; break; }
            }
            if (!$found) {
                $out[] = ['name' => $name, 'icon' => '📁', 'count' => $count, 'sortOrder' => 9999];
            }
        }
    } else {
        foreach ($counts as $name => $count) {
            $out[] = ['name' => $name, 'icon' => '📁', 'count' => $count, 'sortOrder' => 0];
        }
    }
    echo json_encode($out);
    exit;
}

if ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid id']);
        exit;
    }
    $pdo->prepare("UPDATE documents SET views = COALESCE(views, 0) + 1 WHERE id = ? AND status = 1")->execute([$id]);
    $stmt = $pdo->prepare("SELECT COALESCE(views, 0) as views,
        (SELECT COUNT(*) FROM user_likes ul WHERE ul.document_id = documents.id) as likes
        FROM documents WHERE id = ? AND status = 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['ok' => true, 'views' => (int)$row['views'], 'likes' => (int)$row['likes']]);
    } else {
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

if ($action === 'get_link') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT drive_url FROM documents WHERE id = ? AND status = 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['driveUrl' => $row['drive_url']]);
    } else {
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

// Đếm click Shopee (ghi nhận khi user bấm; chỉ admin xem thống kê)
if ($action === 'shopee_click') {
    $id = (int)($input['document_id'] ?? $_POST['document_id'] ?? $_GET['document_id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    // Không lọc status — vẫn đếm nếu bài đang ẩn
    $upd = $pdo->prepare("UPDATE documents SET shopee_clicks = COALESCE(shopee_clicks, 0) + 1 WHERE id = ?");
    $upd->execute([$id]);
    if ($upd->rowCount() < 1) {
        // Thử tạo cột nếu thiếu rồi update lại
        try {
            $pdo->exec("ALTER TABLE documents ADD COLUMN shopee_clicks INTEGER DEFAULT 0");
            $pdo->prepare("UPDATE documents SET shopee_clicks = COALESCE(shopee_clicks, 0) + 1 WHERE id = ?")->execute([$id]);
        } catch (Exception $e) {}
    }
    $stmt = $pdo->prepare("SELECT COALESCE(shopee_clicks, 0) as clicks FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['ok' => true, 'clicks' => (int)$row['clicks'], 'id' => $id]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Not found', 'id' => $id]);
    }
    exit;
}

// Báo cáo link hỏng (ai cũng gửi được; chỉ admin xem danh sách)
if ($action === 'report_broken') {
    $id = (int)($input['document_id'] ?? $_GET['document_id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));
    $reporter = trim((string)($input['reporter'] ?? ''));
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Thiếu tài liệu']);
        exit;
    }
    $chk = $pdo->prepare("SELECT id, title FROM documents WHERE id = ?");
    $chk->execute([$id]);
    $doc = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        echo json_encode(['ok' => false, 'error' => 'Không tìm thấy tài liệu']);
        exit;
    }
    // Tránh spam: cùng doc trong 10 phút
    $dup = $pdo->prepare("SELECT id FROM link_reports WHERE document_id = ? AND status = 0 AND created_at >= datetime('now', '-10 minutes') LIMIT 1");
    $dup->execute([$id]);
    if ($dup->fetch()) {
        echo json_encode(['ok' => true, 'message' => 'Báo cáo đã được ghi nhận gần đây. Cảm ơn bạn!']);
        exit;
    }
    if (mb_strlen($note) > 500) $note = mb_substr($note, 0, 500);
    if (mb_strlen($reporter) > 120) $reporter = mb_substr($reporter, 0, 120);
    $pdo->prepare("INSERT INTO link_reports (document_id, note, reporter) VALUES (?,?,?)")
        ->execute([$id, $note, $reporter]);
    echo json_encode(['ok' => true, 'message' => 'Đã gửi báo cáo link hỏng. Cảm ơn bạn!']);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
