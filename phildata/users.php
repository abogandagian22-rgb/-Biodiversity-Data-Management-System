<?php
include 'db_connect.php';

$activePage = 'users';
$itemsPerPage = 10;
$usersPage = max(1, (int) ($_GET['page'] ?? 1));

$stmt = $pdo->query("SELECT username, email, created_at FROM users ORDER BY user_id ASC");
$users = $stmt->fetchAll();

if (count($users) === 0) {
    $users = [
        ['username' => 'johndoe', 'email' => 'john.doe@philbio.ph', 'created_at' => '2025-01-15 00:00:00'],
        ['username' => 'janesmith', 'email' => 'jane.smith@philbio.ph', 'created_at' => '2025-02-20 00:00:00'],
        ['username' => 'researcher1', 'email' => 'researcher1@philbio.ph', 'created_at' => '2025-03-01 00:00:00'],
    ];
}

$usersTotalCount = count($users);
$usersTotalPages = $usersTotalCount > 0 ? (int) ceil($usersTotalCount / $itemsPerPage) : 0;
$usersPage = $usersTotalPages > 0 ? min($usersPage, $usersTotalPages) : 1;
$usersOffset = ($usersPage - 1) * $itemsPerPage;
$users = array_slice($users, $usersOffset, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4 d-flex align-items-center gap-2">
            <h1 class="mb-1">Users</h1>
            <span class="header-admin-pill">Admin Only</span>
        </div>
        <p class="text-muted mb-4">Manage system users and access</p>

        <section class="birds-card">
            <div class="birds-card-head">
                <h2>System Users</h2>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                            <td><?= htmlspecialchars($row['email'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $createdAt = $row['created_at'] ?? '';
                                if (is_string($createdAt) && strtotime($createdAt) !== false) {
                                    echo htmlspecialchars(date('n/j/Y', strtotime($createdAt)));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><span class="user-status-pill">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($usersTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $usersPage) ?> of <?= htmlspecialchars((string) $usersTotalPages) ?></small>
                    <nav aria-label="Users pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $usersPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $usersPage <= 1 ? '#' : htmlspecialchars('users.php?page=' . ($usersPage - 1)) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $usersPage >= $usersTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $usersPage >= $usersTotalPages ? '#' : htmlspecialchars('users.php?page=' . ($usersPage + 1)) ?>">Next</a>
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
