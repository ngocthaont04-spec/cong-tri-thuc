<?php
$page_title = 'Thêm / Sửa tài liệu';
require_once 'includes/header.php';

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

    // Tối đa 2 mục; mục 2 khác mục 1
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
?>

<div class="wrap">
    <h1><?= $id ? 'Sửa tài liệu' : 'Thêm tài liệu mới' ?></h1>

    <?php if ($error): ?>
        <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <table class="form-table">
            <tr>
                <th><label>Tiêu đề *</label></th>
                <td><input type="text" name="title" value="<?= htmlspecialchars($doc['title']) ?>" required></td>
            </tr>
            <tr>
                <th><label>Danh mục 1 *</label></th>
                <td>
                    <select name="category" required>
                        <?php foreach ($CATEGORIES as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($doc['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="color:#646970;margin-top:4px">Mỗi tài liệu tối đa <strong>2 danh mục</strong>. Quản lý tại <a href="categories.php">Danh mục</a>.</p>
                </td>
            </tr>
            <tr>
                <th><label>Danh mục 2</label></th>
                <td>
                    <select name="category2">
                        <option value="">— Không chọn —</option>
                        <?php foreach ($CATEGORIES as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($doc['category2'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="color:#646970;margin-top:4px">Tùy chọn. Nếu trùng danh mục 1 sẽ bỏ qua.</p>
                </td>
            </tr>
            <tr>
                <th><label>Loại</label></th>
                <td>
                    <select name="type">
                        <?php foreach ($TYPES as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($doc['type'] ?? '') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Tác giả</label></th>
                <td><input type="text" name="author" value="<?= htmlspecialchars($doc['author'] ?? '') ?>"></td>
            </tr>
            <tr>
                <th><label>Mô tả ngắn</label></th>
                <td><textarea name="short_desc"><?= htmlspecialchars($doc['short_desc'] ?? '') ?></textarea></td>
            </tr>
            <tr>
                <th><label>Dung lượng</label></th>
                <td><input type="text" name="size" value="<?= htmlspecialchars($doc['size'] ?? '') ?>" placeholder="VD: 1.2 GB"></td>
            </tr>
            <tr>
                <th><label>Link Shopee (affiliate)</label></th>
                <td><input type="url" name="shopee_url" value="<?= htmlspecialchars($doc['shopee_url'] ?? '') ?>"></td>
            </tr>
            <tr>
                <th><label>Link Google Drive *</label></th>
                <td><input type="url" name="drive_url" value="<?= htmlspecialchars($doc['drive_url'] ?? '') ?>" required></td>
            </tr>
            <tr>
                <th><label>Trạng thái</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="status" value="1" <?= !empty($doc['status']) ? 'checked' : '' ?>>
                        Hiển thị trên trang chủ
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?= $id ? 'Cập nhật' : 'Thêm tài liệu' ?></button>
            <a href="documents.php" class="button">Hủy</a>
        </p>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
