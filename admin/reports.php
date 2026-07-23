<?php
$page_title = 'Báo cáo link hỏng';
require_once 'includes/header.php';

// Chỉ admin (đã require_login trong header)

// Đánh dấu xử lý
if (isset($_GET['action']) && $_GET['action'] === 'resolve' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE link_reports SET status = 1 WHERE id = ?")->execute([$id]);
    header('Location: reports.php?msg=resolved');
    exit;
}

// Xóa báo cáo
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM link_reports WHERE id = ?")->execute([$id]);
    header('Location: reports.php?msg=deleted');
    exit;
}

// Đảm bảo bảng tồn tại
$pdo->exec("CREATE TABLE IF NOT EXISTS link_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    note TEXT DEFAULT '',
    reporter TEXT DEFAULT '',
    status INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$filter = $_GET['f'] ?? 'open'; // open | done | all
$where = '';
if ($filter === 'open') $where = 'WHERE r.status = 0';
elseif ($filter === 'done') $where = 'WHERE r.status = 1';

$reports = $pdo->query("
    SELECT r.*, d.title, d.drive_url, d.status as doc_status
    FROM link_reports r
    LEFT JOIN documents d ON d.id = r.document_id
    $where
    ORDER BY r.status ASC, r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$openCount = (int)$pdo->query("SELECT COUNT(*) FROM link_reports WHERE status = 0")->fetchColumn();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Báo cáo link hỏng</h1>
    <?php if ($openCount > 0): ?>
        <span class="button" style="margin-left:10px;background:#d63638;border-color:#d63638;color:#fff;pointer-events:none">
            <?= $openCount ?> chờ xử lý
        </span>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if (isset($_GET['msg'])): ?>
        <div class="notice notice-success">
            <?php
            if ($_GET['msg'] === 'resolved') echo 'Đã đánh dấu xử lý.';
            if ($_GET['msg'] === 'deleted') echo 'Đã xóa báo cáo.';
            ?>
        </div>
    <?php endif; ?>

    <p style="margin-bottom:12px">
        <a class="button <?= $filter === 'open' ? 'button-primary' : '' ?>" href="?f=open">Chưa xử lý</a>
        <a class="button <?= $filter === 'done' ? 'button-primary' : '' ?>" href="?f=done">Đã xử lý</a>
        <a class="button <?= $filter === 'all' ? 'button-primary' : '' ?>" href="?f=all">Tất cả</a>
    </p>

    <p style="color:#646970;font-size:13px">Chỉ tài khoản admin mới xem được trang này. Người dùng gửi báo cáo bằng icon ⚠️ trên web.</p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="50">ID</th>
                <th>Tài liệu</th>
                <th width="160">Thời gian</th>
                <th>Ghi chú</th>
                <th width="100">Người báo</th>
                <th width="90">TT</th>
                <th width="180">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="7">Chưa có báo cáo nào.</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td>
                            <strong>#<?= (int)$r['document_id'] ?></strong>
                            <?= htmlspecialchars($r['title'] ?? '(đã xóa)') ?>
                            <?php if (!empty($r['drive_url'])): ?>
                                <div class="row-actions" style="visibility:visible">
                                    <a href="<?= htmlspecialchars($r['drive_url']) ?>" target="_blank">Mở Drive</a>
                                    ·
                                    <a href="document-form.php?id=<?= (int)$r['document_id'] ?>">Sửa bài</a>
                                    ·
                                    <a href="<?= htmlspecialchars(public_doc_url($r['document_id'], $r['title'] ?? '')) ?>" target="_blank">Link web</a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                        <td><?= $r['note'] !== '' ? htmlspecialchars($r['note']) : '<span style="color:#8c8f94">—</span>' ?></td>
                        <td><?= $r['reporter'] !== '' ? htmlspecialchars($r['reporter']) : 'Ẩn danh' ?></td>
                        <td>
                            <?php if ((int)$r['status'] === 0): ?>
                                <span style="color:#d63638">● Chờ</span>
                            <?php else: ?>
                                <span style="color:#00a32a">● Xong</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$r['status'] === 0): ?>
                                <a class="button button-primary" href="?action=resolve&id=<?= (int)$r['id'] ?>&f=<?= htmlspecialchars($filter) ?>">Đã xử lý</a>
                            <?php endif; ?>
                            <a class="button" href="?action=delete&id=<?= (int)$r['id'] ?>&f=<?= htmlspecialchars($filter) ?>" onclick="return confirm('Xóa báo cáo này?')">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
