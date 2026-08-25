<?php
include 'db_connect.php';

$activePage = 'audit';
$itemsPerPage = 10;
$auditPage = max(1, (int) ($_GET['page'] ?? 1));

$auditRows = [];

try {
    $auditColumnStmt = $pdo->query("SHOW COLUMNS FROM audit_logs");
    $auditColumns = array_map(static fn($col) => $col['Field'], $auditColumnStmt->fetchAll());
    $auditColumnsMap = array_flip($auditColumns);

    $hasUsersTable = false;
    try {
        $usersCheckStmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $hasUsersTable = (bool) $usersCheckStmt->fetch();
    } catch (PDOException $e) {
        $hasUsersTable = false;
    }

    $selectParts = [];
    $selectParts[] = isset($auditColumnsMap['log_id']) ? 'al.log_id' : '0 AS log_id';
    $selectParts[] = isset($auditColumnsMap['action_type']) ? 'al.action_type' : "'' AS action_type";
    $selectParts[] = isset($auditColumnsMap['table_name']) ? 'al.table_name' : "'' AS table_name";
    $selectParts[] = isset($auditColumnsMap['record_id']) ? 'al.record_id' : "'' AS record_id";
    $selectParts[] = isset($auditColumnsMap['action_time']) ? 'al.action_time' : 'NULL AS action_time';

    if ($hasUsersTable && isset($auditColumnsMap['user_id'])) {
        $selectParts[] = 'u.username AS username';
        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM audit_logs al LEFT JOIN users u ON u.user_id = al.user_id ORDER BY ' . (isset($auditColumnsMap['log_id']) ? 'al.log_id' : 'al.action_time') . ' DESC';
    } else {
        $selectParts[] = "'System' AS username";
        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM audit_logs al ORDER BY ' . (isset($auditColumnsMap['log_id']) ? 'al.log_id' : 'al.action_time') . ' DESC';
    }

    $stmt = $pdo->query($sql);
    $auditRows = $stmt->fetchAll();
} catch (PDOException $e) {
    $auditRows = [];
}

$auditTotalCount = count($auditRows);
$auditTotalPages = $auditTotalCount > 0 ? (int) ceil($auditTotalCount / $itemsPerPage) : 0;
$auditPage = $auditTotalPages > 0 ? min($auditPage, $auditTotalPages) : 1;
$auditOffset = ($auditPage - 1) * $itemsPerPage;
$auditRows = array_slice($auditRows, $auditOffset, $itemsPerPage);

function actionBadgeClass($action)
{
    $value = strtoupper((string) $action);
    if ($value === 'INSERT') {
        return 'audit-pill-insert';
    }
    if ($value === 'UPDATE') {
        return 'audit-pill-update';
    }
    if ($value === 'ARCHIVE') {
        return 'audit-pill-archive';
    }
    return 'audit-pill-default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4 d-flex align-items-center gap-2">
            <h1 class="mb-1">Audit Logs</h1>
            <span class="header-admin-pill">Admin Only</span>
        </div>
        <p class="text-muted mb-4">Track all system actions and changes</p>

        <section class="birds-card">
            <div class="birds-card-head mb-3">
                <h2>Activity Log</h2>
                <div class="d-flex gap-2">
                    <select class="form-select filter-select" aria-label="All Tables">
                        <option>All Tables</option>
                    </select>
                    <select class="form-select filter-select" aria-label="All Actions">
                        <option>All Actions</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="auditTable">
                    <thead>
                        <tr>
                            <th style="width:50px;"></th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($auditRows) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No activity log records yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($auditRows as $row): ?>
                        <tr>
                            <td><i class="bi bi-chevron-right"></i></td>
                            <td><strong><?= htmlspecialchars($row['username'] ?? 'System') ?></strong></td>
                            <td><span class="audit-pill <?= actionBadgeClass($row['action_type']) ?>"><?= htmlspecialchars(strtoupper((string) $row['action_type'])) ?></span></td>
                            <td><span class="table-tag"><?= htmlspecialchars($row['table_name'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars((string) ($row['record_id'] ?? 'N/A')) ?></td>
                            <td>
                                <?php
                                $timeValue = $row['action_time'] ?? '';
                                if (is_string($timeValue) && strtotime($timeValue) !== false) {
                                    echo htmlspecialchars(date('n/j/Y, g:i:s A', strtotime($timeValue)));
                                } else {
                                    echo htmlspecialchars((string) $timeValue);
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($auditTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $auditPage) ?> of <?= htmlspecialchars((string) $auditTotalPages) ?></small>
                    <nav aria-label="Audit pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $auditPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $auditPage <= 1 ? '#' : htmlspecialchars('audit_logs.php?page=' . ($auditPage - 1)) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $auditPage >= $auditTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $auditPage >= $auditTotalPages ? '#' : htmlspecialchars('audit_logs.php?page=' . ($auditPage + 1)) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
