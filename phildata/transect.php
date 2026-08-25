<?php
include 'db_connect.php';

// Include archive logging function for audit trail
function logAuditAction(PDO $pdo, string $actionType, string $tableName, int $recordId): bool
{
    try {
        // Ensure audit_logs table exists
        try {
            $pdo->query("SELECT 1 FROM audit_logs LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                action_type VARCHAR(50),
                table_name VARCHAR(100),
                record_id INT,
                user_id INT NULL,
                action_time DATETIME,
                KEY (action_type),
                KEY (table_name),
                KEY (record_id)
            )");
        }

        $columnStmt = $pdo->query("SHOW COLUMNS FROM audit_logs");
        $columns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
        $columnsMap = array_flip($columns);

        $userId = null;
        if (isset($columnsMap['user_id'])) {
            try {
                $userStmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
                $firstUserId = $userStmt->fetchColumn();
                if ($firstUserId !== false) {
                    $userId = (int) $firstUserId;
                }
            } catch (PDOException $e) {
                $userId = null;
            }
        }

        $insertValues = [
            'action_type' => strtoupper($actionType),
            'table_name' => $tableName,
            'record_id' => $recordId,
            'user_id' => $userId,
            'action_time' => date('Y-m-d H:i:s'),
        ];

        $insertColumns = [];
        $insertParams = [];

        foreach ($insertValues as $column => $value) {
            if (isset($columnsMap[$column])) {
                $insertColumns[] = $column;
                if ($value === null) {
                    $insertParams[':' . $column] = null;
                } else {
                    $insertParams[':' . $column] = $value;
                }
            }
        }

        if (count($insertColumns) > 0) {
            $sql = 'INSERT INTO audit_logs (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($insertParams);
            return $result;
        }
        return false;
    } catch (PDOException $e) {
        // Log error for debugging
        error_log('Audit log error: ' . $e->getMessage());
        return false;
    }
}

$activePage = 'transects';
$formError = '';
$showAddModal = false;
$formValues = [
    'transect_name' => '',
    'location' => '',
    'survey_date' => '',
];

$columnStmt = $pdo->query("SHOW COLUMNS FROM bird_transects");
$availableColumns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
$availableColumnsMap = array_flip($availableColumns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['transect_name'] = trim($_POST['transect_name'] ?? '');
    $formValues['location'] = trim($_POST['location'] ?? '');
    $formValues['survey_date'] = trim($_POST['survey_date'] ?? '');

    if ($formValues['transect_name'] === '' || $formValues['location'] === '' || $formValues['survey_date'] === '') {
        $formError = 'Please fill out all required fields.';
        $showAddModal = true;
    } else {
        $insertValues = [
            'transect_name' => $formValues['transect_name'],
            'location' => $formValues['location'],
            'survey_date' => $formValues['survey_date'],
            'created_by' => 'John Doe',
            'last_edited_by' => 'John Doe',
        ];

        $insertColumns = [];
        $insertParams = [];

        foreach ($insertValues as $column => $value) {
            if (isset($availableColumnsMap[$column])) {
                $insertColumns[] = $column;
                $insertParams[":$column"] = $value;
            }
        }

        try {
            $sql = 'INSERT INTO bird_transects (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute($insertParams);

            $lastInsertId = (int) $pdo->lastInsertId();
            logAuditAction($pdo, 'INSERT', 'bird_transects', $lastInsertId);

            header('Location: transect.php?added=1');
            exit;
        } catch (PDOException $e) {
            $formError = 'Unable to add transect. Please verify your values.';
            $showAddModal = true;
        }
    }
}

$selectColumns = ['transect_id', 'transect_name', 'location', 'survey_date'];

if (isset($availableColumnsMap['created_by'])) {
    $selectColumns[] = 'created_by';
} else {
    $selectColumns[] = 'NULL AS created_by';
}

if (isset($availableColumnsMap['last_edited_by'])) {
    $selectColumns[] = 'last_edited_by';
} else {
    $selectColumns[] = 'NULL AS last_edited_by';
}

$stmt = $pdo->query('SELECT ' . implode(', ', $selectColumns) . ' FROM bird_transects ORDER BY transect_id DESC');
$transects = $stmt->fetchAll();

if (count($transects) === 0) {
    $transects = [
        [
            'transect_name' => 'Mt. Makiling Trail A',
            'location' => 'Laguna, Philippines (14.1333deg N, 121.2167deg E)',
            'survey_date' => '2026-03-15',
            'created_by' => 'John Doe',
            'last_edited_by' => 'John Doe',
        ],
        [
            'transect_name' => 'Mt. Pulag Summit',
            'location' => 'Benguet, Philippines (16.5967deg N, 120.8906deg E)',
            'survey_date' => '2026-03-10',
            'created_by' => 'Jane Smith',
            'last_edited_by' => 'Jane Smith',
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transect Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4">
            <h1 class="mb-1">Transect Records</h1>
            <p class="text-muted mb-0">Manage transect survey data</p>
        </div>

        <section class="birds-card">
            <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Transect added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Transect archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Transects</h2>
                <button class="btn btn-success add-species-btn" id="openTransectModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Transect
                </button>
            </div>

            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchInput" placeholder="Search transects..." onkeyup="searchTable()">
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="transectTable">
                    <thead>
                        <tr>
                            <th>Transect Name</th>
                            <th>Location</th>
                            <th>Survey Date</th>
                            <th>Created By</th>
                            <th>Last Edited By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transects as $index => $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['transect_name'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($row['location'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $dateValue = $row['survey_date'] ?? '';
                                if ($dateValue) {
                                    $formattedDate = date('n/j/Y', strtotime((string) $dateValue));
                                    echo htmlspecialchars($formattedDate);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars(($row['created_by'] ?? '') !== '' ? $row['created_by'] : (($index % 2 === 0) ? 'John Doe' : 'Jane Smith')) ?></td>
                            <td><?= htmlspecialchars(($row['last_edited_by'] ?? '') !== '' ? $row['last_edited_by'] : (($index % 2 === 0) ? 'John Doe' : 'Jane Smith')) ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if (isset($row['transect_id']) && (int) $row['transect_id'] > 0): ?>
                                        <a href="edit_transect.php?id=<?= urlencode((string) $row['transect_id']) ?>" class="action-link" aria-label="Edit transect"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=transect&id=<?= urlencode((string) $row['transect_id']) ?>" class="action-link" aria-label="Archive transect" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="species-modal-backdrop" id="transectModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="transectModal" role="dialog" aria-modal="true" aria-labelledby="transectModalTitle" aria-hidden="true">
        <div class="species-modal-dialog">
            <div class="species-modal-head">
                <h3 id="transectModalTitle">Add Transect</h3>
                <button type="button" class="modal-close-btn" id="closeTransectModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="transect.php" method="post">
                <div class="mb-3">
                    <label for="transectName" class="form-label">Transect Name</label>
                    <input id="transectName" type="text" class="form-control" name="transect_name" value="<?= htmlspecialchars($formValues['transect_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="transectLocation" class="form-label">Location</label>
                    <input id="transectLocation" type="text" class="form-control" name="location" placeholder="e.g., City, Province (Coordinates)" value="<?= htmlspecialchars($formValues['location']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="transectSurveyDate" class="form-label">Survey Date</label>
                    <input id="transectSurveyDate" type="date" class="form-control" name="survey_date" value="<?= htmlspecialchars($formValues['survey_date']) ?>" required>
                </div>

                <div class="species-modal-actions">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelTransectModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Transect</button>
                </div>
            </form>
        </div>
    </section>
</div>
<script>
function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue, found;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("transectTable");
    tr = table.getElementsByTagName("tr");
    for (i = 1; i < tr.length; i++) {
        tr[i].style.display = "none";
        td = tr[i].getElementsByTagName("td");
        found = false;
        for (j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        if (found) tr[i].style.display = "";
    }
}

const openTransectModalBtn = document.getElementById("openTransectModal");
const transectModal = document.getElementById("transectModal");
const transectModalBackdrop = document.getElementById("transectModalBackdrop");
const closeTransectModalBtn = document.getElementById("closeTransectModal");
const cancelTransectModalBtn = document.getElementById("cancelTransectModal");

function openTransectModal() {
    transectModal.classList.add("is-open");
    transectModalBackdrop.classList.add("is-open");
    transectModal.setAttribute("aria-hidden", "false");
    transectModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeTransectModal() {
    transectModal.classList.remove("is-open");
    transectModalBackdrop.classList.remove("is-open");
    transectModal.setAttribute("aria-hidden", "true");
    transectModalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

if (openTransectModalBtn && transectModal && transectModalBackdrop && closeTransectModalBtn && cancelTransectModalBtn) {
    openTransectModalBtn.addEventListener("click", openTransectModal);
    closeTransectModalBtn.addEventListener("click", closeTransectModal);
    cancelTransectModalBtn.addEventListener("click", closeTransectModal);
    transectModalBackdrop.addEventListener("click", closeTransectModal);
}

document.addEventListener("keydown", function(event) {
    if (transectModal && event.key === "Escape" && transectModal.classList.contains("is-open")) {
        closeTransectModal();
    }
});

<?php if ($showAddModal): ?>
openTransectModal();
<?php endif; ?>
</script>
</body>
</html>
