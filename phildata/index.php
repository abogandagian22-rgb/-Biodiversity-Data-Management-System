
<?php
include 'db_connect.php';

function countRows(PDO $pdo, string $tableName): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM {$tableName}");
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0);
    } catch (PDOException $e) {
        // If a table does not exist yet, default to zero instead of breaking the dashboard.
        return 0;
    }
}

function countRowsFromCandidates(PDO $pdo, array $tableNames): int
{
    foreach ($tableNames as $tableName) {
        $count = countRows($pdo, $tableName);
        if ($count > 0) {
            return $count;
        }
    }

    // If all are zero/missing, return the first valid table's count (likely zero).
    foreach ($tableNames as $tableName) {
        try {
            return countRows($pdo, $tableName);
        } catch (PDOException $e) {
            // Continue until a valid table is found.
        }
    }

    return 0;
}

$birds = countRows($pdo, 'bird_species');
$bats = countRows($pdo, 'bat_species');
$flora = countRowsFromCandidates($pdo, ['flora_tawi', 'flora', 'flora_records']);
$transect = countRows($pdo, 'bird_transects');

function actionBadgeClass(string $action): string
{
    $value = strtoupper($action);
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

function actionVerb(string $action): string
{
    $value = strtoupper($action);
    if ($value === 'INSERT') {
        return 'inserted';
    }
    if ($value === 'UPDATE') {
        return 'updated';
    }
    if ($value === 'ARCHIVE') {
        return 'archived';
    }
    return strtolower($action);
}

function getRecentUploadedRecords(PDO $pdo, int $limit = 5): array
{
    $records = [];

    // Get recent bird species - order by species_id DESC to get newest first
    try {
           $stmt = $pdo->prepare('SELECT species_id, common_name, scientific_name FROM bird_species ORDER BY species_id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $row) {
            if (!empty($row['common_name'])) {
                $records[] = [
                    'badge' => 'Bird',
                    'name' => trim($row['common_name']),
                    'sub' => trim($row['scientific_name'] ?? ''),
                        'created_by' => 'System',
                    'date' => date('n/j/Y'),
                    'sort_id' => (int) $row['species_id'],
                ];
            }
        }
    } catch (Exception $e) {
        // Log but continue
    }

    // Get recent bat species
    try {
           $stmt = $pdo->prepare('SELECT species_id, common_name, scientific_name FROM bat_species ORDER BY species_id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $row) {
            if (!empty($row['common_name'])) {
                $records[] = [
                    'badge' => 'Bat',
                    'name' => trim($row['common_name']),
                    'sub' => trim($row['scientific_name'] ?? ''),
                        'created_by' => 'System',
                    'date' => date('n/j/Y'),
                    'sort_id' => (int) $row['species_id'],
                ];
            }
        }
    } catch (Exception $e) {
        // Log but continue
    }

    // Get recent flora
    try {
           $stmt = $pdo->prepare('SELECT record_id, local_name, scientific_name FROM flora_tawi ORDER BY record_id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $row) {
            if (!empty($row['local_name'])) {
                $records[] = [
                    'badge' => 'Flora',
                    'name' => trim($row['local_name']),
                    'sub' => trim($row['scientific_name'] ?? ''),
                        'created_by' => 'System',
                    'date' => date('n/j/Y'),
                    'sort_id' => (int) $row['record_id'],
                ];
            }
        }
    } catch (Exception $e) {
        // Log but continue
    }

    // Sort by sort_id DESC (highest IDs = newest records first)
    usort($records, function($a, $b) {
        return (int)$b['sort_id'] - (int)$a['sort_id'];
    });

    // Remove sort_id and limit
    $final = [];
    foreach ($records as $record) {
        unset($record['sort_id']);
        $final[] = $record;
        if (count($final) >= $limit) break;
    }

    return $final;
}

$recentActivities = [
    [
        'message' => 'John Doe inserted a record in birds_species',
        'timestamp' => '3/15/2026, 4:30:00 PM',
        'action' => 'INSERT',
        'pill_class' => 'audit-pill-insert',
    ],
    [
        'message' => 'Jane Smith inserted a record in birds_species',
        'timestamp' => '3/14/2026, 6:15:00 PM',
        'action' => 'INSERT',
        'pill_class' => 'audit-pill-insert',
    ],
    [
        'message' => 'John Doe updated a record in flora',
        'timestamp' => '3/10/2026, 4:30:00 PM',
        'action' => 'UPDATE',
        'pill_class' => 'audit-pill-update',
    ],
    [
        'message' => 'John Doe archived a record in birds_species',
        'timestamp' => '3/1/2026, 8:00:00 PM',
        'action' => 'ARCHIVE',
        'pill_class' => 'audit-pill-archive',
    ],
];

try {
    $auditStmt = $pdo->query(
        "SELECT al.action_type, al.table_name, al.action_time, u.username
         FROM audit_logs al
         LEFT JOIN users u ON u.user_id = al.user_id
            ORDER BY al.log_id DESC
            LIMIT 10"
    );
    $auditRows = $auditStmt->fetchAll();

    if (count($auditRows) > 0) {
        $recentActivities = [];
        foreach ($auditRows as $row) {
            $action = strtoupper((string) ($row['action_type'] ?? ''));
            $username = trim((string) ($row['username'] ?? ''));
            $tableName = (string) ($row['table_name'] ?? 'record');
            $timeValue = (string) ($row['action_time'] ?? '');

            $formattedTime = $timeValue;
            if ($timeValue !== '' && strtotime($timeValue) !== false) {
                $formattedTime = date('n/j/Y, g:i:s A', strtotime($timeValue));
            }

            $recentActivities[] = [
                'message' => ($username !== '' ? $username : 'System') . ' ' . actionVerb($action) . ' a record in ' . $tableName,
                'timestamp' => $formattedTime,
                'action' => $action,
                'pill_class' => actionBadgeClass($action),
            ];
        }
    }
} catch (PDOException $e) {
    // Keep fallback entries when audit tables are unavailable.
}

$recentUploadedRecords = getRecentUploadedRecords($pdo, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

<body>

<div class="d-flex">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content p-4 w-100">

        <h2>Dashboard</h2>
        <p class="text-muted">Overview of Philippine biodiversity records</p>

        <!-- Cards -->
        <div class="row g-3">

            <div class="col-md-3">
                <div class="card-box">
                    <h6>Total Bird Records</h6>
                    <h3><?= $birds ?></h3>
                    <small>Species documented</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-box">
                    <h6>Total Bat Records</h6>
                    <h3><?= $bats ?></h3>
                    <small>Species documented</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-box">
                    <h6>Total Flora Records</h6>
                    <h3><?= $flora ?></h3>
                    <small>Species documented</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-box">
                    <h6>Total Transect Records</h6>
                    <h3><?= $transect ?></h3>
                    <small>Surveys conducted</small>
                </div>
            </div>

        </div>

        <!-- Search -->
        <div class="card mt-4 p-3">
            <h5>Global Search</h5>
            <input type="text" class="form-control" placeholder="Search by name...">
        </div>

        <!-- Bottom Section -->
        <div class="row mt-4">

            <div class="col-md-6">
                <div class="card p-3 recent-activity-card">
                    <h5>Recent Activity</h5>
                    <div class="recent-activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="mb-3 pb-2 border-bottom">
                                <p class="mb-1"><?= htmlspecialchars($activity['message']) ?></p>
                                <small class="text-muted d-block mb-2"><?= htmlspecialchars($activity['timestamp']) ?></small>
                                <span class="audit-pill <?= htmlspecialchars($activity['pill_class']) ?>"><?= htmlspecialchars($activity['action']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card p-3">
                    <h5>Recent Records</h5>
                    <?php if (count($recentUploadedRecords) === 0): ?>
                        <p class="text-muted mb-0">No recent uploads yet.</p>
                    <?php else: ?>
                        <div class="recent-records-list">
                            <?php foreach ($recentUploadedRecords as $record): ?>
                                <div class="recent-record-item">
                                    <div class="recent-record-item-top">
                                        <span class="recent-record-badge"><?= htmlspecialchars($record['badge']) ?></span>
                                        <span class="recent-record-date"><?= htmlspecialchars($record['date']) ?></span>
                                    </div>
                                    <h6 class="recent-record-title"><?= htmlspecialchars($record['name']) ?></h6>
                                    <?php if ($record['sub'] !== ''): ?>
                                        <p class="recent-record-sub"><em><?= htmlspecialchars($record['sub']) ?></em></p>
                                    <?php endif; ?>
                                    <p class="recent-record-created mb-0">Created by: <?= htmlspecialchars($record['created_by']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

</body>
</html>