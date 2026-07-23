<?php
$page_title = 'Tài liệu';
require_once 'includes/header.php';

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
// tie-break ổn định
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

// Nếu sort theo likes — sắp xếp lại trên PHP
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

/** Link sort cột: click đổi chiều */
function sort_link($key, $label, $currentSort, $currentDir, $search) {
    $nextDir = 'asc';
    $arrow = '';
    if ($currentSort === $key) {
        $nextDir = $currentDir === 'asc' ? 'desc' : 'asc';
        $arrow = $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
    $q = [
        'sort' => $key,
        'dir' => $currentSort === $key ? $nextDir : 'asc',
    ];
    if ($search !== '') $q['s'] = $search;
    $href = 'documents.php?' . http_build_query($q);
    $cls = $currentSort === $key ? 'sorted' : '';
    return '<a class="sort-col ' . $cls . '" href="' . htmlspecialchars($href) . '" title="Sắp xếp theo ' . htmlspecialchars($label) . '">'
        . htmlspecialchars($label) . '<span class="sort-arrow">' . $arrow . '</span></a>';
}

$baseQ = [];
if ($search !== '') $baseQ['s'] = $search;
if ($sort) $baseQ['sort'] = $sort;
if ($dir) $baseQ['dir'] = $dir;
$queryKeep = http_build_query($baseQ);
$queryKeep = $queryKeep ? '&' . $queryKeep : '';
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

<div class="wrap">
    <h1 class="wp-heading-inline">Tài liệu</h1>
    <a href="document-form.php" class="button button-primary" style="margin-left:10px;">Thêm mới</a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['msg'])): ?>
        <div class="notice notice-success">
            <?php
            if ($_GET['msg'] === 'deleted') echo 'Đã xóa tài liệu.';
            if ($_GET['msg'] === 'toggled') echo 'Đã cập nhật trạng thái.';
            if ($_GET['msg'] === 'saved') {
                echo 'Đã lưu tài liệu thành công.';
                if ($shareUrl): ?>
                    <p style="margin:8px 0 0">
                        <strong>Link chia sẻ:</strong>
                        <a href="<?= htmlspecialchars($shareUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($shareUrl) ?></a>
                        <button type="button" class="button" style="margin-left:8px" onclick="navigator.clipboard.writeText(<?= json_encode($shareUrl) ?>);this.textContent='Đã copy!'">Copy link</button>
                    </p>
                <?php endif;
            }
            ?>
        </div>
    <?php endif; ?>

    <form method="get" class="tablenav">
        <?php if ($sort): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
        <?php if ($dir): ?><input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>"><?php endif; ?>
        <div class="search-box">
            <input type="search" name="s" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm tài liệu...">
            <button type="submit" class="button">Tìm kiếm</button>
        </div>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="50"><?= sort_link('id', 'ID', $sort, $dir, $search) ?></th>
                <th><?= sort_link('title', 'Tiêu đề', $sort, $dir, $search) ?></th>
                <th width="110"><?= sort_link('category', 'Danh mục', $sort, $dir, $search) ?></th>
                <th width="80"><?= sort_link('type', 'Loại', $sort, $dir, $search) ?></th>
                <th width="100"><?= sort_link('created', 'Ngày đăng', $sort, $dir, $search) ?></th>
                <th width="70"><?= sort_link('likes', 'Thích', $sort, $dir, $search) ?></th>
                <th width="80"><?= sort_link('views', 'Quan tâm', $sort, $dir, $search) ?></th>
                <th width="70"><?= sort_link('status', 'Trạng thái', $sort, $dir, $search) ?></th>
                <th width="130">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($docs)): ?>
                <tr><td colspan="9">Chưa có tài liệu nào.</td></tr>
            <?php else: ?>
                <?php foreach ($docs as $doc): ?>
                    <?php
                      $viewsReal = (int)($doc['views'] ?? 0);
                      $viewsShow = $viewsReal * 123;
                      $likes = $likeCounts[(int)$doc['id']] ?? 0;
                      $created = $doc['created_at'] ?? '';
                      try {
                          $createdFmt = $created ? date('d/m/Y H:i', strtotime($created)) : '—';
                      } catch (Exception $e) {
                          $createdFmt = $created ?: '—';
                      }
                      $docId = (int)$doc['id'];
                    ?>
                    <tr>
                        <td><strong><?= $docId ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($doc['title']) ?></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="document-form.php?id=<?= $docId ?>">Sửa</a> | </span>
                                <span><a href="<?= htmlspecialchars(public_doc_url($docId, $doc['title'] ?? '')) ?>" target="_blank">Link public</a> | </span>
                                <span class="delete"><a href="?action=delete&id=<?= $docId . $queryKeep ?>" onclick="return confirm('Bạn chắc chắn muốn xóa?')">Xóa</a></span>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($doc['category']) ?>
                            <?php if (!empty($doc['category2'])): ?>
                                <br><span style="color:#646970"><?= htmlspecialchars($doc['category2']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($doc['type']) ?></td>
                        <td><?= htmlspecialchars($createdFmt) ?></td>
                        <td>❤️ <?= number_format($likes) ?></td>
                        <td>
                            <?= number_format($viewsShow, 0, ',', '.') ?>
                            <div class="row-actions" style="visibility:visible;color:#787c82;">(thật: <?= $viewsReal ?>)</div>
                        </td>
                        <td>
                            <?php if ($doc['status']): ?>
                                <span style="color:#00a32a;">● Hiện</span>
                            <?php else: ?>
                                <span style="color:#d63638;">● Ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="document-form.php?id=<?= $docId ?>" class="button">Sửa</a>
                            <a href="?action=toggle&id=<?= $docId . $queryKeep ?>" class="button"><?= $doc['status'] ? 'Ẩn' : 'Hiện' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
