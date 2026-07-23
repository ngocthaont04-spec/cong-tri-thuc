<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Xử lý xóa
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
    $qs = $_GET;
    unset($qs['action'], $qs['id']);
    $qs['msg'] = 'deleted';
    header('Location: documents.php?' . http_build_query($qs));
    exit;
}

// Xử lý ẩn/hiện
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE documents SET status = 1 - status, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    $qs = $_GET;
    unset($qs['action'], $qs['id']);
    $qs['msg'] = 'toggled';
    header('Location: documents.php?' . http_build_query($qs));
    exit;
}

$search = trim($_GET['s'] ?? '');

// Sắp xếp theo cột (mặc định: ID)
$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'desc');
if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

$sortMap = [
    'id'       => 'id',
    'title'    => 'title COLLATE NOCASE',
    'category' => 'category COLLATE NOCASE',
    'type'     => 'type COLLATE NOCASE',
    'created'  => 'created_at',
    'views'    => 'COALESCE(views, 0)',
    'status'   => 'status',
];
if (!isset($sortMap[$sort])) $sort = 'id';

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE title LIKE ? OR category LIKE ? OR category2 LIKE ? OR author LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$orderSql = $sortMap[$sort] . ' ' . strtoupper($dir);
if ($sort !== 'id') {
    $orderSql .= ', id DESC';
}

$stmt = $pdo->prepare("SELECT * FROM documents $where ORDER BY $orderSql");
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Đếm like công khai
$likeCounts = [];
try {
    $rows = $pdo->query("SELECT document_id, COUNT(*) as c FROM user_likes GROUP BY document_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $likeCounts[(int)$r['document_id']] = (int)$r['c'];
    }
} catch (Exception $e) {}

// Cột shopee_clicks
try {
    $cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('shopee_clicks', array_column($cols, 'name'), true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN shopee_clicks INTEGER DEFAULT 0");
    }
} catch (Exception $e) {}

// Nếu sort theo likes
if ($sort === 'likes') {
    usort($docs, function ($a, $b) use ($likeCounts, $dir) {
        $la = $likeCounts[(int)$a['id']] ?? 0;
        $lb = $likeCounts[(int)$b['id']] ?? 0;
        if ($la === $lb) return (int)$b['id'] - (int)$a['id'];
        return $dir === 'asc' ? ($la - $lb) : ($lb - $la);
    });
}

$savedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$shareUrl = '';
if ($savedId > 0) {
    $t = '';
    foreach ($docs as $d) {
        if ((int)$d['id'] === $savedId) { $t = $d['title']; break; }
    }
    if ($t === '') {
        try {
            $st = $pdo->prepare("SELECT title FROM documents WHERE id = ?");
            $st->execute([$savedId]);
            $t = (string)$st->fetchColumn();
        } catch (Exception $e) {}
    }
    $shareUrl = public_doc_url($savedId, $t);
}

$page_title = 'Tài liệu';
require_once 'includes/header.php';
?>
<style>
a.sort-col {
    color: #1d2327;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    white-space: nowrap;
}
a.sort-col:hover { color: #2271b1; }
a.sort-col.sorted { color: #2271b1; font-weight: 600; }
a.sort-col .sort-arrow {
    font-size: 11px;
    color: #2271b1;
    min-width: 12px;
}
th .sort-hint {
    display: block;
    font-size: 10px;
    font-weight: 400;
    color: #8c8f94;
    margin-top: 2px;
}
</style>
