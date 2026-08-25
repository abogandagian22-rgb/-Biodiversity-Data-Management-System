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

$activePage = 'flora';
$itemsPerPage = 10;
$floraPage = max(1, (int) ($_GET['page'] ?? 1));
$formError = '';
$showAddModal = false;
$formValues = [
    'local_name' => '',
    'scientific_name' => '',
    'family_name' => '',
    'iucn_status' => 'LC',
    'denr_status' => 'NL',
    'remarks' => '',
];

$iucnLabels = [
    'EX' => 'Extinct (EX)',
    'EW' => 'Extinct in the Wild (EW)',
    'CR' => 'Critically Endangered (CR)',
    'EN' => 'Endangered (EN)',
    'VU' => 'Vulnerable (VU)',
    'NT' => 'Near Threatened (NT)',
    'LC' => 'Least Concern (LC)',
    'DD' => 'Data Deficient (DD)',
    'NE' => 'Not Evaluated (NE)',
];

$denrLabels = [
    'CR' => 'Critically Endangered',
    'EN' => 'Endangered',
    'VU' => 'Vulnerable',
    'OTS' => 'Other Threatened Species',
    'NL' => 'Not Listed',
];

$columnStmt = $pdo->query("SHOW COLUMNS FROM flora_tawi");
$availableColumns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
$availableColumnsMap = array_flip($availableColumns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['local_name'] = trim($_POST['local_name'] ?? '');
    $formValues['scientific_name'] = trim($_POST['scientific_name'] ?? '');
    $formValues['family_name'] = trim($_POST['family_name'] ?? '');
    $formValues['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
    $formValues['denr_status'] = trim($_POST['denr_status'] ?? 'NL');
    $formValues['remarks'] = trim($_POST['remarks'] ?? '');

    if ($formValues['local_name'] === '' || $formValues['scientific_name'] === '' || $formValues['family_name'] === '') {
        $formError = 'Please fill out all required fields.';
        $showAddModal = true;
    } elseif (!isset($iucnLabels[$formValues['iucn_status']]) || !isset($denrLabels[$formValues['denr_status']])) {
        $formError = 'Invalid status value selected.';
        $showAddModal = true;
    } else {
        $insertValues = [
            'local_name' => $formValues['local_name'],
            'scientific_name' => $formValues['scientific_name'],
            'family_name' => $formValues['family_name'],
            'iucn_status' => $formValues['iucn_status'],
            'denr_status' => $formValues['denr_status'],
            'remarks' => $formValues['remarks'],
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
            $sql = 'INSERT INTO flora_tawi (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute($insertParams);

            $lastInsertId = (int) $pdo->lastInsertId();
            logAuditAction($pdo, 'INSERT', 'flora_tawi', $lastInsertId);

            header('Location: flora.php?added=1');
            exit;
        } catch (PDOException $e) {
            $formError = 'Unable to add flora record. Please verify your values.';
            $showAddModal = true;
        }
    }
}

$selectColumns = ['record_id', 'local_name', 'scientific_name', 'family_name', 'iucn_status', 'denr_status', 'remarks'];

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

$stmt = $pdo->query('SELECT ' . implode(', ', $selectColumns) . ' FROM flora_tawi');
$floraRecords = $stmt->fetchAll();

if (count($floraRecords) === 0) {
    $floraRecords = [
        [
            'local_name' => 'Narra',
            'scientific_name' => 'Pterocarpus indicus',
            'family_name' => 'Fabaceae',
            'iucn_status' => 'EN',
            'denr_status' => 'EN',
            'remarks' => 'National tree of the Philippines',
            'created_by' => 'John Doe',
            'last_edited_by' => 'John Doe',
        ],
        [
            'local_name' => 'Molave',
            'scientific_name' => 'Vitex parviflora',
            'family_name' => 'Lamiaceae',
            'iucn_status' => 'VU',
            'denr_status' => 'OTS',
            'remarks' => 'Important timber tree',
            'created_by' => 'Jane Smith',
            'last_edited_by' => 'Jane Smith',
        ],
        [
            'local_name' => 'Almaciga',
            'scientific_name' => 'Agathis philippinensis',
            'family_name' => 'Araucariaceae',
            'iucn_status' => 'VU',
            'denr_status' => 'OTS',
            'remarks' => 'Source of Manila copal resin',
            'created_by' => 'John Doe',
            'last_edited_by' => 'John Doe',
        ],
    ];
}

$floraTotalCount = count($floraRecords);
$floraTotalPages = $floraTotalCount > 0 ? (int) ceil($floraTotalCount / $itemsPerPage) : 0;
$floraPage = $floraTotalPages > 0 ? min($floraPage, $floraTotalPages) : 1;
$floraOffset = ($floraPage - 1) * $itemsPerPage;
$floraRecords = array_slice($floraRecords, $floraOffset, $itemsPerPage);

function floraStatusBadgeClass($status)
{
    $value = strtoupper((string) $status);
    if ($value === 'CR' || $value === 'EN') {
        return 'badge-critical';
    }
    if ($value === 'VU' || $value === 'NT') {
        return 'badge-vulnerable';
    }
    return 'badge-neutral';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Flora Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4">
            <h1 class="mb-1">Flora Records</h1>
            <p class="text-muted mb-0">Manage flora species data</p>
        </div>

        <section class="birds-card">
            <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Flora record added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Flora record archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Flora Species</h2>
                <button class="btn btn-success add-species-btn" id="openFloraModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Flora
                </button>
            </div>

            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchInput" placeholder="Search flora..." onkeyup="searchTable()">
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="floraTable">
                    <thead>
                        <tr>
                            <th>Local Name</th>
                            <th>Scientific Name</th>
                            <th>Family Name</th>
                            <th>IUCN Status</th>
                            <th>DENR Status</th>
                            <th>Remarks</th>
                            <th>Created By</th>
                            <th>Last Edited By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($floraRecords as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['local_name'] ?? 'N/A') ?></strong></td>
                            <td><em><?= htmlspecialchars($row['scientific_name'] ?? 'N/A') ?></em></td>
                            <td><?= htmlspecialchars($row['family_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= floraStatusBadgeClass($row['iucn_status']) ?>">
                                    <?= htmlspecialchars($iucnLabels[$row['iucn_status']] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge badge-neutral"><?= htmlspecialchars($denrLabels[$row['denr_status']] ?? 'N/A') ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['remarks'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(($row['created_by'] ?? '') !== '' ? $row['created_by'] : 'John Doe') ?></td>
                            <td><?= htmlspecialchars(($row['last_edited_by'] ?? '') !== '' ? $row['last_edited_by'] : 'John Doe') ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if (isset($row['record_id']) && (int) $row['record_id'] > 0): ?>
                                        <a href="edit_flora.php?id=<?= urlencode((string) $row['record_id']) ?>" class="action-link" aria-label="Edit flora record"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=flora&id=<?= urlencode((string) $row['record_id']) ?>" class="action-link" aria-label="Archive flora" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($floraTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $floraPage) ?> of <?= htmlspecialchars((string) $floraTotalPages) ?></small>
                    <nav aria-label="Flora pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $floraPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $floraPage <= 1 ? '#' : htmlspecialchars('flora.php?page=' . ($floraPage - 1)) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $floraPage >= $floraTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $floraPage >= $floraTotalPages ? '#' : htmlspecialchars('flora.php?page=' . ($floraPage + 1)) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="species-modal-backdrop" id="floraModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="floraModal" role="dialog" aria-modal="true" aria-labelledby="floraModalTitle" aria-hidden="true">
        <div class="species-modal-dialog">
            <div class="species-modal-head">
                <h3 id="floraModalTitle">Add Flora Record</h3>
                <button type="button" class="modal-close-btn" id="closeFloraModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="flora.php" method="post">
                <div class="mb-3">
                    <label for="floraLocalName" class="form-label">Local Name</label>
                    <input id="floraLocalName" type="text" class="form-control" name="local_name" value="<?= htmlspecialchars($formValues['local_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="floraScientificName" class="form-label">Scientific Name</label>
                    <input id="floraScientificName" type="text" class="form-control" name="scientific_name" value="<?= htmlspecialchars($formValues['scientific_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="floraFamilyName" class="form-label">Family Name</label>
                    <input id="floraFamilyName" type="text" class="form-control" name="family_name" value="<?= htmlspecialchars($formValues['family_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="floraIucnStatus" class="form-label">IUCN Status</label>
                    <select id="floraIucnStatus" class="form-select" name="iucn_status">
                        <option value="LC" <?= $formValues['iucn_status'] === 'LC' ? 'selected' : '' ?>>Least Concern (LC)</option>
                        <option value="NT" <?= $formValues['iucn_status'] === 'NT' ? 'selected' : '' ?>>Near Threatened (NT)</option>
                        <option value="VU" <?= $formValues['iucn_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable (VU)</option>
                        <option value="EN" <?= $formValues['iucn_status'] === 'EN' ? 'selected' : '' ?>>Endangered (EN)</option>
                        <option value="CR" <?= $formValues['iucn_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered (CR)</option>
                        <option value="DD" <?= $formValues['iucn_status'] === 'DD' ? 'selected' : '' ?>>Data Deficient (DD)</option>
                        <option value="NE" <?= $formValues['iucn_status'] === 'NE' ? 'selected' : '' ?>>Not Evaluated (NE)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="floraDenrStatus" class="form-label">DENR Status</label>
                    <select id="floraDenrStatus" class="form-select" name="denr_status">
                        <option value="NL" <?= $formValues['denr_status'] === 'NL' ? 'selected' : '' ?>>Least Concern</option>
                        <option value="OTS" <?= $formValues['denr_status'] === 'OTS' ? 'selected' : '' ?>>Threatened</option>
                        <option value="VU" <?= $formValues['denr_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable</option>
                        <option value="EN" <?= $formValues['denr_status'] === 'EN' ? 'selected' : '' ?>>Endangered</option>
                        <option value="CR" <?= $formValues['denr_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="floraRemarks" class="form-label">Remarks</label>
                    <textarea id="floraRemarks" class="form-control" name="remarks" rows="3"><?= htmlspecialchars($formValues['remarks']) ?></textarea>
                </div>

                <div class="species-modal-actions">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelFloraModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Flora</button>
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
    table = document.getElementById("floraTable");
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

const openFloraModalBtn = document.getElementById("openFloraModal");
const floraModal = document.getElementById("floraModal");
const floraModalBackdrop = document.getElementById("floraModalBackdrop");
const closeFloraModalBtn = document.getElementById("closeFloraModal");
const cancelFloraModalBtn = document.getElementById("cancelFloraModal");

function openFloraModal() {
    floraModal.classList.add("is-open");
    floraModalBackdrop.classList.add("is-open");
    floraModal.setAttribute("aria-hidden", "false");
    floraModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeFloraModal() {
    floraModal.classList.remove("is-open");
    floraModalBackdrop.classList.remove("is-open");
    floraModal.setAttribute("aria-hidden", "true");
    floraModalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

if (openFloraModalBtn && floraModal && floraModalBackdrop && closeFloraModalBtn && cancelFloraModalBtn) {
    openFloraModalBtn.addEventListener("click", openFloraModal);
    closeFloraModalBtn.addEventListener("click", closeFloraModal);
    cancelFloraModalBtn.addEventListener("click", closeFloraModal);
    floraModalBackdrop.addEventListener("click", closeFloraModal);
}

document.addEventListener("keydown", function(event) {
    if (floraModal && event.key === "Escape" && floraModal.classList.contains("is-open")) {
        closeFloraModal();
    }
});

<?php if ($showAddModal): ?>
openFloraModal();
<?php endif; ?>
</script>
</body>
</html>
