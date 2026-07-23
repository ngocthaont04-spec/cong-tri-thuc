<?php
$page_title = 'Dashboard';
require_once 'includes/header.php';

// Migration nhẹ nếu thiếu cột
try {
    $cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('shopee_clicks', $colNames, true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN shopee_clicks INTEGER DEFAULT 0");
    }
} catch (Exception $e) {}

$total = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$active = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 1")->fetchColumn();
$hidden = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 0")->fetchColumn();
$by_type = $pdo->query("SELECT type, COUNT(*) as cnt FROM documents GROUP BY type")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalShopee = (int)$pdo->query("SELECT COALESCE(SUM(shopee_clicks), 0) FROM documents")->fetchColumn();
$topShopee = $pdo->query("
    SELECT id, title, category, COALESCE(shopee_clicks, 0) as shopee_clicks, status
    FROM documents
    ORDER BY COALESCE(shopee_clicks, 0) DESC, id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="wrap">
    <h1>Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Tổng tài liệu</h3>
            <div class="number"><?= (int)$total ?></div>
        </div>
        <div class="stat-card">
            <h3>Đang hiện</h3>
            <div class="number"><?= (int)$active ?></div>
        </div>
        <div class="stat-card">
            <h3>Đang ẩn</h3>
            <div class="number"><?= (int)$hidden ?></div>
        </div>
        <div class="stat-card">
            <h3>Khóa học</h3>
            <div class="number"><?= (int)($by_type['Khóa học'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <h3>Click Shopee (tổng)</h3>
            <div class="number"><?= number_format($totalShopee) ?></div>
        </div>
    </div>

    <p>
        <a href="document-form.php" class="button button-primary">+ Thêm tài liệu mới</a>
        <a href="documents.php" class="button">Quản lý tất cả tài liệu</a>
        <a href="reports.php" class="button">Link hỏng</a>
    </p>

    <h2 style="font-size:16px;margin:24px 0 10px;font-weight:600">Click link Shopee theo từng bài</h2>
    <p style="color:#646970;font-size:13px;margin:0 0 12px">
        Chỉ admin xem được. Số được cộng mỗi khi người dùng bấm “Ghé Shopee” trên web.
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="50">ID</th>
                <th>Tiêu đề</th>
                <th width="120">Danh mục</th>
                <th width="100">Click Shopee</th>
                <th width="80">Trạng thái</th>
                <th width="120">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topShopee)): ?>
                <tr><td colspan="6">Chưa có dữ liệu.</td></tr>
            <?php else: ?>
                <?php foreach ($topShopee as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td>
                            <strong style="color:#c2410c">🛒 <?= number_format((int)$row['shopee_clicks']) ?></strong>
                        </td>
                        <td>
                            <?php if ((int)$row['status'] === 1): ?>
                                <span style="color:#00a32a">● Hiện</span>
                            <?php else: ?>
                                <span style="color:#d63638">● Ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="button" href="document-form.php?id=<?= (int)$row['id'] ?>">Sửa</a>
                            <a class="button" href="<?= htmlspecialchars(public_doc_url($row['id'], $row['title'])) ?>" target="_blank">Xem</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
