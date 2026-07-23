<?php
$page_title = 'Danh mục';
require_once 'includes/header.php';

// Xóa
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    header('Location: categories.php?msg=deleted');
    exit;
}

// Ẩn/hiện
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE categories SET status = 1 - status WHERE id = ?")->execute([$id]);
    header('Location: categories.php?msg=toggled');
    exit;
}

$error = '';
// Thêm / Sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '📁');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;

    if ($name === '') {
        $error = 'Tên danh mục không được để trống.';
    } else {
        try {
            if ($id > 0) {
                // Đổi tên category trên documents nếu tên đổi
                $old = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                $old->execute([$id]);
                $oldName = $old->fetchColumn();
                $pdo->prepare("UPDATE categories SET name=?, icon=?, sort_order=?, status=? WHERE id=?")
                    ->execute([$name, $icon ?: '📁', $sort_order, $status, $id]);
                if ($oldName && $oldName !== $name) {
                    $pdo->prepare("UPDATE documents SET category = ? WHERE category = ?")
                        ->execute([$name, $oldName]);
                    $pdo->prepare("UPDATE documents SET category2 = ? WHERE category2 = ?")
                        ->execute([$name, $oldName]);
                }
            } else {
                $pdo->prepare("INSERT INTO categories (name, icon, sort_order, status) VALUES (?,?,?,?)")
                    ->execute([$name, $icon ?: '📁', $sort_order, $status]);
            }
            header('Location: categories.php?msg=saved');
            exit;
        } catch (Exception $e) {
            $error = 'Không lưu được (tên có thể bị trùng).';
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$cats = $pdo->query("SELECT c.*,
    (SELECT COUNT(*) FROM documents d WHERE d.category = c.name OR d.category2 = c.name) as doc_count
    FROM categories c ORDER BY c.sort_order ASC, c.id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Danh mục (thư mục)</h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['msg'])): ?>
        <div class="notice notice-success">
            <?php
            if ($_GET['msg'] === 'deleted') echo 'Đã xóa danh mục.';
            if ($_GET['msg'] === 'toggled') echo 'Đã cập nhật trạng thái.';
            if ($_GET['msg'] === 'saved') echo 'Đã lưu danh mục.';
            ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:24px;align-items:start">
        <div>
            <h2 style="font-size:16px;margin-bottom:12px"><?= $edit ? 'Sửa danh mục' : 'Thêm danh mục' ?></h2>
            <form method="post">
                <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                <table class="form-table">
                    <tr>
                        <th><label>Tên *</label></th>
                        <td><input type="text" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="VD: Ngoại ngữ"></td>
                    </tr>
                    <tr>
                        <th><label>Icon (emoji)</label></th>
                        <td><input type="text" name="icon" value="<?= htmlspecialchars($edit['icon'] ?? '📁') ?>" style="max-width:80px"></td>
                    </tr>
                    <tr>
                        <th><label>Thứ tự</label></th>
                        <td><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>" style="max-width:100px"></td>
                    </tr>
                    <tr>
                        <th><label>Hiển thị</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="status" value="1" <?= !isset($edit['status']) || !empty($edit['status']) ? 'checked' : '' ?>>
                                Hiện trên web
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?= $edit ? 'Cập nhật' : 'Thêm danh mục' ?></button>
                    <?php if ($edit): ?>
                        <a href="categories.php" class="button">Hủy</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <div>
            <h2 style="font-size:16px;margin-bottom:12px">Danh sách</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50">TT</th>
                        <th width="50">Icon</th>
                        <th>Tên</th>
                        <th width="70">Bài</th>
                        <th width="70">TT</th>
                        <th width="160">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cats)): ?>
                        <tr><td colspan="6">Chưa có danh mục.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cats as $c): ?>
                            <tr>
                                <td><?= (int)$c['sort_order'] ?></td>
                                <td style="font-size:18px"><?= htmlspecialchars($c['icon']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                                    <?php if (!(int)$c['status']): ?>
                                        <span style="color:#d63638"> (ẩn)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$c['doc_count'] ?></td>
                                <td><?= (int)$c['status'] ? 'Hiện' : 'Ẩn' ?></td>
                                <td>
                                    <a class="button" href="?edit=<?= (int)$c['id'] ?>">Sửa</a>
                                    <a class="button" href="?action=toggle&id=<?= (int)$c['id'] ?>"><?= (int)$c['status'] ? 'Ẩn' : 'Hiện' ?></a>
                                    <a class="button" href="?action=delete&id=<?= (int)$c['id'] ?>" onclick="return confirm('Xóa danh mục này? Tài liệu vẫn giữ tên cũ.')">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
