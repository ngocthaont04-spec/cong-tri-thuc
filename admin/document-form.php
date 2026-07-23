<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doc = [
    'title' => '', 'category' => ($CATEGORIES[0] ?? 'Khác'), 'category2' => '',
    'author' => 'Cổng Tri Thức',
    'short_desc' => '', 'size' => '', 'shopee_url' => '',
    'drive_url' => '', 'type' => 'Tài liệu', 'status' => 1
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $doc = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Khác');
    $category2 = trim($_POST['category2'] ?? '');
    $author = trim($_POST['author'] ?? 'Cổng Tri Thức');
    $short_desc = trim($_POST['short_desc'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $shopee_url = trim($_POST['shopee_url'] ?? '');
    $drive_url = trim($_POST['drive_url'] ?? '');
    $type = $_POST['type'] ?? 'Tài liệu';
    $status = isset($_POST['status']) ? 1 : 0;

    if ($category2 === '' || $category2 === $category) {
        $category2 = '';
    }

    if ($title === '' || $drive_url === '') {
        $error = 'Tiêu đề và Link Google Drive là bắt buộc.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE documents SET
                title=?, category=?, category2=?, author=?, short_desc=?, size=?,
                shopee_url=?, drive_url=?, type=?, status=?,
                updated_at=CURRENT_TIMESTAMP
                WHERE id=?");
            $stmt->execute([$title, $category, $category2, $author, $short_desc, $size, $shopee_url, $drive_url, $type, $status, $id]);
            $savedId = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO documents
                (title, category, category2, author, short_desc, size, shopee_url, drive_url, type, status)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $category, $category2, $author, $short_desc, $size, $shopee_url, $drive_url, $type, $status]);
            $savedId = (int)$pdo->lastInsertId();
        }
        header('Location: documents.php?msg=saved&id=' . $savedId);
        exit;
    }
}

$page_title = 'Thêm / Sửa tài liệu';
require_once 'includes/header.php';
?>
