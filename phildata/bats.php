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

$activePage = 'bats';
$formError = '';
$showAddModal = false;
$measurementFormError = '';
$showMeasurementModal = false;
$batClassificationOptions = ['Mammalia', 'Chiroptera', 'Pteropodidae'];
$formValues = [
    'species_code' => '',
    'common_name' => '',
    'scientific_name' => '',
    'classification' => 'Mammalia',
    'iucn_status' => 'LC',
    'denr_status' => 'NL',
];

$measurementFormValues = [
    'species_id' => '',
    'sex' => '',
    'age' => '',
    'forearm' => '0',
    'hindfoot' => '0',
    'ear' => '0',
    'tail' => '0',
    'total_length' => '0',
    'weight' => '0',
    'net_line' => '',
    'remarks' => '',
];

$classificationLabels = [
    'RR' => 'Resident / Regular',
    'E' => 'Endemic',
    'M' => 'Migratory',
    'I' => 'Introduced',
    'NSS' => 'No Special Status',
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

$activeBatTab = $_GET['tab'] ?? 'species';
if (!in_array($activeBatTab, ['species', 'measurements'], true)) {
    $activeBatTab = 'species';
}

function buildBatsTabUrl(string $tab, array $params = []): string
{
    return 'bats.php?' . http_build_query(array_merge(['tab' => $tab], $params));
}

$itemsPerPage = 10;
$speciesPage = max(1, (int) ($_GET['species_page'] ?? 1));
$measurementsPage = max(1, (int) ($_GET['measurements_page'] ?? 1));

$columnStmt = $pdo->query("SHOW COLUMNS FROM bat_species");
$availableColumns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
$availableColumnsMap = array_flip($availableColumns);

$batSpeciesOptionsStmt = $pdo->query("SELECT species_id, common_name FROM bat_species ORDER BY common_name ASC");
$batSpeciesOptions = $batSpeciesOptionsStmt->fetchAll();
$validBatSpeciesIds = array_flip(array_map(static fn($row) => (string) $row['species_id'], $batSpeciesOptions));

try {
    $classificationColumnStmt = $pdo->query("SHOW COLUMNS FROM bat_species LIKE 'classification'");
    $classificationColumn = $classificationColumnStmt->fetch();
    if ($classificationColumn && strpos(strtolower((string) $classificationColumn['Type']), 'varchar(20)') !== false) {
        $pdo->exec("ALTER TABLE bat_species MODIFY classification VARCHAR(255) NULL");
    }
} catch (PDOException $e) {
    // Continue without failing if schema change is not permitted.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'species';
    if ($formType === 'measurement') {
        $activeBatTab = 'measurements';
        $measurementFormValues['species_id'] = trim($_POST['species_id'] ?? '');
        $measurementFormValues['sex'] = trim($_POST['sex'] ?? '');
        $measurementFormValues['age'] = trim($_POST['age'] ?? '');
        $measurementFormValues['forearm'] = trim($_POST['forearm'] ?? '0');
        $measurementFormValues['hindfoot'] = trim($_POST['hindfoot'] ?? '0');
        $measurementFormValues['ear'] = trim($_POST['ear'] ?? '0');
        $measurementFormValues['tail'] = trim($_POST['tail'] ?? '0');
        $measurementFormValues['total_length'] = trim($_POST['total_length'] ?? '0');
        $measurementFormValues['weight'] = trim($_POST['weight'] ?? '0');
        $measurementFormValues['net_line'] = trim($_POST['net_line'] ?? '');
        $measurementFormValues['remarks'] = trim($_POST['remarks'] ?? '');

        if ($measurementFormValues['species_id'] === '') {
            $measurementFormError = 'Please select a species.';
            $showMeasurementModal = true;
        } elseif (!isset($validBatSpeciesIds[$measurementFormValues['species_id']])) {
            $measurementFormError = 'Invalid species selected.';
            $showMeasurementModal = true;
        } else {
            try {
                $insertMeasurementStmt = $pdo->prepare(
                    "INSERT INTO bats_measurements (species_id, sex, age, forearm, hindfoot, ear, tail, total_length, weight, net_line, remarks)
                     VALUES (:species_id, :sex, :age, :forearm, :hindfoot, :ear, :tail, :total_length, :weight, :net_line, :remarks)"
                );

                $insertMeasurementStmt->execute([
                    ':species_id' => (int) $measurementFormValues['species_id'],
                    ':sex' => $measurementFormValues['sex'],
                    ':age' => $measurementFormValues['age'],
                    ':forearm' => (float) $measurementFormValues['forearm'],
                    ':hindfoot' => (float) $measurementFormValues['hindfoot'],
                    ':ear' => (float) $measurementFormValues['ear'],
                    ':tail' => (float) $measurementFormValues['tail'],
                    ':total_length' => (float) $measurementFormValues['total_length'],
                    ':weight' => (float) $measurementFormValues['weight'],
                    ':net_line' => $measurementFormValues['net_line'],
                    ':remarks' => $measurementFormValues['remarks'],
                ]);

                header('Location: bats.php?tab=measurements&measurement_added=1');
                exit;
            } catch (PDOException $e) {
                $measurementFormError = 'Unable to add measurement. Please verify your values.';
                $showMeasurementModal = true;
            }
        }
    } else {
        $formValues['species_code'] = trim($_POST['species_code'] ?? '');
        $formValues['common_name'] = trim($_POST['common_name'] ?? '');
        $formValues['scientific_name'] = trim($_POST['scientific_name'] ?? '');

        $selectedClassification = trim($_POST['classification'] ?? 'Mammalia');
        if (!in_array($selectedClassification, $batClassificationOptions, true)) {
            $selectedClassification = 'Mammalia';
        }
        $formValues['classification'] = $selectedClassification;
        $formValues['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
        $formValues['denr_status'] = trim($_POST['denr_status'] ?? 'NL');

        if (
            $formValues['species_code'] === '' ||
            $formValues['common_name'] === '' ||
            $formValues['scientific_name'] === '' ||
            $formValues['classification'] === ''
        ) {
            $formError = 'Please fill out all required fields.';
            $showAddModal = true;
        } elseif (!isset($iucnLabels[$formValues['iucn_status']]) || !isset($denrLabels[$formValues['denr_status']])) {
            $formError = 'Invalid status value selected.';
            $showAddModal = true;
        } else {
            $insertValues = [
                'species_code' => $formValues['species_code'],
                'common_name' => $formValues['common_name'],
                'scientific_name' => $formValues['scientific_name'],
                'classification' => $formValues['classification'],
                'iucn_status' => $formValues['iucn_status'],
                'denr_status' => $formValues['denr_status'],
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

            if (count($insertColumns) < 5) {
                $formError = 'Database table is missing required columns for insert.';
                $showAddModal = true;
            } else {
                try {
                    $placeholders = implode(', ', array_keys($insertParams));
                    $sql = 'INSERT INTO bat_species (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
                    $insertStmt = $pdo->prepare($sql);
                    $insertStmt->execute($insertParams);

                    $lastInsertId = (int) $pdo->lastInsertId();
                    logAuditAction($pdo, 'INSERT', 'bat_species', $lastInsertId);

                    header('Location: bats.php?tab=species&added=1');
                    exit;
                } catch (PDOException $e) {
                    $formError = 'Unable to add bat species. Please check if the species code already exists.';
                    $showAddModal = true;
                }
            }
        }
    }
}

$selectColumns = ['species_id', 'species_code', 'common_name', 'scientific_name', 'classification', 'iucn_status'];

if (isset($availableColumnsMap['denr_status'])) {
    $selectColumns[] = 'denr_status';
} else {
    $selectColumns[] = 'NULL AS denr_status';
}

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

$speciesTotalCountStmt = $pdo->query('SELECT COUNT(*) FROM bat_species');
$speciesTotalCount = (int) $speciesTotalCountStmt->fetchColumn();
$speciesTotalPages = $speciesTotalCount > 0 ? (int) ceil($speciesTotalCount / $itemsPerPage) : 0;
$speciesPage = $speciesTotalPages > 0 ? min($speciesPage, $speciesTotalPages) : 1;
$speciesOffset = ($speciesPage - 1) * $itemsPerPage;
$speciesQuery = 'SELECT ' . implode(', ', $selectColumns) . ' FROM bat_species ORDER BY species_id DESC LIMIT ' . (int) $itemsPerPage . ' OFFSET ' . (int) $speciesOffset;
$stmt = $pdo->query($speciesQuery);
$species = $stmt->fetchAll();

$measurements = [];
if ($activeBatTab === 'measurements') {
    $measurementTotalCountStmt = $pdo->query('SELECT COUNT(*) FROM bats_measurements');
    $measurementTotalCount = (int) $measurementTotalCountStmt->fetchColumn();
    $measurementTotalPages = $measurementTotalCount > 0 ? (int) ceil($measurementTotalCount / $itemsPerPage) : 0;
    $measurementsPage = $measurementTotalPages > 0 ? min($measurementsPage, $measurementTotalPages) : 1;
    $measurementOffset = ($measurementsPage - 1) * $itemsPerPage;

    $measurementSql = "
        SELECT
            bm.bat_id,
            bs.common_name AS species_name,
            bm.sex,
            bm.age,
            bm.forearm,
            bm.hindfoot,
            bm.ear,
            bm.tail,
            bm.total_length,
            bm.weight,
            bm.net_line,
            'John Doe' AS created_by
        FROM bats_measurements bm
        LEFT JOIN bat_species bs ON bs.species_id = bm.species_id
        ORDER BY bm.bat_id DESC
        LIMIT " . (int) $itemsPerPage . " OFFSET " . (int) $measurementOffset . "
    ";

    $measurementStmt = $pdo->query($measurementSql);
    $measurements = $measurementStmt->fetchAll();

    if (count($measurements) === 0) {
        $measurements = [
            [
                'species_name' => "Geoffrey's Rousette",
                'sex' => 'Male',
                'age' => 'Adult',
                'forearm' => '82.5',
                'hindfoot' => '18.2',
                'ear' => '22.1',
                'tail' => '15',
                'total_length' => '145',
                'weight' => '95.5',
                'net_line' => 'Line 1',
                'created_by' => 'John Doe',
            ],
            [
                'species_name' => 'Philippine Dawn Bat',
                'sex' => 'Female',
                'age' => 'Adult',
                'forearm' => '75.3',
                'hindfoot' => '16.8',
                'ear' => '20.5',
                'tail' => '12',
                'total_length' => '132',
                'weight' => '78.2',
                'net_line' => 'Line 2',
                'created_by' => 'Jane Smith',
            ],
        ];
    }
}

function statusBadgeClass($status)
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
    <title>Bat Species Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4">
            <h1 class="mb-1">Bats Records</h1>
            <p class="text-muted mb-0">Manage bat species and measurement data</p>
        </div>

        <div class="records-tabs mb-4">
            <button class="tab-pill <?= $activeBatTab === 'species' ? 'active' : '' ?>" type="button" onclick="window.location.href='bats.php?tab=species'">Species</button>
            <button class="tab-pill <?= $activeBatTab === 'measurements' ? 'active' : '' ?>" type="button" onclick="window.location.href='bats.php?tab=measurements'">Measurements</button>
        </div>

        <?php if ($activeBatTab === 'species'): ?>
        <section class="birds-card">
            <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Bat species added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Bat species archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Bat Species</h2>
                <button class="btn btn-success add-species-btn" id="openAddSpeciesModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Species
                </button>
            </div>

            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchInput" placeholder="Search species..." onkeyup="searchTable()">
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="speciesTable">
                    <thead>
                        <tr>
                            <th>Species Code</th>
                            <th>Common Name</th>
                            <th>Scientific Name</th>
                            <th>Classification</th>
                            <th>IUCN Status</th>
                            <th>DENR Status</th>
                            <th>Created By</th>
                            <th>Last Edited By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($species as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['species_code']) ?></td>
                            <td><?= htmlspecialchars($row['common_name']) ?></td>
                            <td><em><?= htmlspecialchars($row['scientific_name']) ?></em></td>
                            <td><?= htmlspecialchars($classificationLabels[$row['classification']] ?? ($row['classification'] ?? 'N/A')) ?></td>
                            <td>
                                <span class="status-badge <?= statusBadgeClass($row['iucn_status']) ?>">
                                    <?= htmlspecialchars($iucnLabels[$row['iucn_status']] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge badge-neutral"><?= htmlspecialchars($denrLabels[$row['denr_status']] ?? 'N/A') ?></span>
                            </td>
                            <td><?= htmlspecialchars(($row['created_by'] ?? '') !== '' ? $row['created_by'] : 'N/A') ?></td>
                            <td><?= htmlspecialchars(($row['last_edited_by'] ?? '') !== '' ? $row['last_edited_by'] : 'N/A') ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if (isset($row['species_id']) && (int) $row['species_id'] > 0): ?>
                                        <a href="edit_bat_species.php?id=<?= urlencode((string) $row['species_id']) ?>" class="action-link" aria-label="Edit record"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=bat_species&id=<?= urlencode((string) $row['species_id']) ?>" class="action-link" aria-label="Archive record" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($speciesTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $speciesPage) ?> of <?= htmlspecialchars((string) $speciesTotalPages) ?></small>
                    <nav aria-label="Bat species pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $speciesPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $speciesPage <= 1 ? '#' : htmlspecialchars(buildBatsTabUrl('species', ['species_page' => $speciesPage - 1])) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $speciesPage >= $speciesTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $speciesPage >= $speciesTotalPages ? '#' : htmlspecialchars(buildBatsTabUrl('species', ['species_page' => $speciesPage + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
        <?php elseif ($activeBatTab === 'measurements'): ?>
        <section class="birds-card">
            <?php if (isset($_GET['measurement_added']) && $_GET['measurement_added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Bat measurement added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Bat measurement archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($measurementFormError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($measurementFormError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Bat Measurements</h2>
                <button class="btn btn-success add-species-btn" id="openMeasurementModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Measurement
                </button>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="speciesTable">
                    <thead>
                        <tr>
                            <th>Species</th>
                            <th>Sex</th>
                            <th>Age</th>
                            <th>Forearm</th>
                            <th>Hindfoot</th>
                            <th>Ear</th>
                            <th>Tail</th>
                            <th>Total Length</th>
                            <th>Weight</th>
                            <th>Net Line</th>
                            <th>Created By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($measurements as $index => $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['species_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['sex'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['age'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['forearm']) ? $row['forearm'] . ' mm' : 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['hindfoot']) ? $row['hindfoot'] . ' mm' : 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['ear']) ? $row['ear'] . ' mm' : 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['tail']) ? $row['tail'] . ' mm' : 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['total_length']) ? $row['total_length'] . ' mm' : 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['weight']) ? $row['weight'] . ' g' : 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['net_line'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['created_by'] ?? (($index % 2 === 0) ? 'John Doe' : 'Jane Smith')) ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if (isset($row['bat_id']) && (int) $row['bat_id'] > 0): ?>
                                        <a href="edit_bat_measurement.php?id=<?= urlencode((string) $row['bat_id']) ?>" class="action-link" aria-label="Edit measurement"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=bat_measurement&id=<?= urlencode((string) $row['bat_id']) ?>" class="action-link" aria-label="Archive measurement" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($measurementTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $measurementsPage) ?> of <?= htmlspecialchars((string) $measurementTotalPages) ?></small>
                    <nav aria-label="Bat measurements pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $measurementsPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $measurementsPage <= 1 ? '#' : htmlspecialchars(buildBatsTabUrl('measurements', ['measurements_page' => $measurementsPage - 1])) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $measurementsPage >= $measurementTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $measurementsPage >= $measurementTotalPages ? '#' : htmlspecialchars(buildBatsTabUrl('measurements', ['measurements_page' => $measurementsPage + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <?php if ($activeBatTab === 'species'): ?>
    <div class="species-modal-backdrop" id="speciesModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="speciesModal" role="dialog" aria-modal="true" aria-labelledby="speciesModalTitle" aria-hidden="true">
        <div class="species-modal-dialog">
            <div class="species-modal-head">
                <h3 id="speciesModalTitle">Add Bat Species</h3>
                <button type="button" class="modal-close-btn" id="closeSpeciesModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="bats.php" method="post">
                <input type="hidden" name="form_type" value="species">
                <div class="mb-3">
                    <label for="modalSpeciesCode" class="form-label">Species Code</label>
                    <input type="text" class="form-control" id="modalSpeciesCode" name="species_code" value="<?= htmlspecialchars($formValues['species_code']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="modalCommonName" class="form-label">Common Name</label>
                    <input type="text" class="form-control" id="modalCommonName" name="common_name" value="<?= htmlspecialchars($formValues['common_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="modalScientificName" class="form-label">Scientific Name</label>
                    <input type="text" class="form-control" id="modalScientificName" name="scientific_name" value="<?= htmlspecialchars($formValues['scientific_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="modalClassification" class="form-label">Classification</label>
                    <select class="form-select" id="modalClassification" name="classification" required>
                        <?php foreach ($batClassificationOptions as $classificationOption): ?>
                            <option value="<?= htmlspecialchars($classificationOption) ?>" <?= $formValues['classification'] === $classificationOption ? 'selected' : '' ?>>
                                <?= htmlspecialchars($classificationOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modalIucnStatus" class="form-label">IUCN Status</label>
                    <select class="form-select" id="modalIucnStatus" name="iucn_status">
                        <option value="LC" <?= $formValues['iucn_status'] === 'LC' ? 'selected' : '' ?>>Least Concern (LC)</option>
                        <option value="NT" <?= $formValues['iucn_status'] === 'NT' ? 'selected' : '' ?>>Near Threatened (NT)</option>
                        <option value="VU" <?= $formValues['iucn_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable (VU)</option>
                        <option value="EN" <?= $formValues['iucn_status'] === 'EN' ? 'selected' : '' ?>>Endangered (EN)</option>
                        <option value="CR" <?= $formValues['iucn_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered (CR)</option>
                        <option value="DD" <?= $formValues['iucn_status'] === 'DD' ? 'selected' : '' ?>>Data Deficient (DD)</option>
                        <option value="NE" <?= $formValues['iucn_status'] === 'NE' ? 'selected' : '' ?>>Not Evaluated (NE)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="modalDenrStatus" class="form-label">DENR Status</label>
                    <select class="form-select" id="modalDenrStatus" name="denr_status">
                        <option value="NL" <?= $formValues['denr_status'] === 'NL' ? 'selected' : '' ?>>Not Listed (NL)</option>
                        <option value="OTS" <?= $formValues['denr_status'] === 'OTS' ? 'selected' : '' ?>>Other Threatened Species (OTS)</option>
                        <option value="VU" <?= $formValues['denr_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable (VU)</option>
                        <option value="EN" <?= $formValues['denr_status'] === 'EN' ? 'selected' : '' ?>>Endangered (EN)</option>
                        <option value="CR" <?= $formValues['denr_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered (CR)</option>
                    </select>
                </div>

                <div class="species-modal-actions">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelSpeciesModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Species</button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($activeBatTab === 'measurements'): ?>
    <div class="species-modal-backdrop" id="measurementModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="measurementModal" role="dialog" aria-modal="true" aria-labelledby="measurementModalTitle" aria-hidden="true">
        <div class="species-modal-dialog bat-measurement-modal-dialog">
            <div class="species-modal-head">
                <h3 id="measurementModalTitle">Add Bat Measurement</h3>
                <button type="button" class="modal-close-btn" id="closeMeasurementModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="bats.php?tab=measurements" method="post">
                <input type="hidden" name="form_type" value="measurement">

                <div class="mb-3">
                    <label for="measurementSpecies" class="form-label">Species</label>
                    <select id="measurementSpecies" class="form-select" name="species_id" required>
                        <option value="">Select species</option>
                        <?php foreach ($batSpeciesOptions as $speciesOption): ?>
                            <option value="<?= htmlspecialchars((string) $speciesOption['species_id']) ?>" <?= $measurementFormValues['species_id'] === (string) $speciesOption['species_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($speciesOption['common_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label for="measurementSex" class="form-label">Sex</label>
                        <input id="measurementSex" type="text" class="form-control" name="sex" placeholder="e.g., Male, Female" value="<?= htmlspecialchars($measurementFormValues['sex']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="measurementAge" class="form-label">Age</label>
                        <input id="measurementAge" type="text" class="form-control" name="age" placeholder="e.g., Adult, Juvenile" value="<?= htmlspecialchars($measurementFormValues['age']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="measurementForearm" class="form-label">Forearm (mm)</label>
                        <input id="measurementForearm" type="number" step="0.1" class="form-control" name="forearm" value="<?= htmlspecialchars($measurementFormValues['forearm']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="measurementHindfoot" class="form-label">Hindfoot (mm)</label>
                        <input id="measurementHindfoot" type="number" step="0.1" class="form-control" name="hindfoot" value="<?= htmlspecialchars($measurementFormValues['hindfoot']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="measurementEar" class="form-label">Ear (mm)</label>
                        <input id="measurementEar" type="number" step="0.1" class="form-control" name="ear" value="<?= htmlspecialchars($measurementFormValues['ear']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="measurementTail" class="form-label">Tail (mm)</label>
                        <input id="measurementTail" type="number" step="0.1" class="form-control" name="tail" value="<?= htmlspecialchars($measurementFormValues['tail']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="measurementTotalLength" class="form-label">Total Length (mm)</label>
                        <input id="measurementTotalLength" type="number" step="0.1" class="form-control" name="total_length" value="<?= htmlspecialchars($measurementFormValues['total_length']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="measurementWeight" class="form-label">Weight (g)</label>
                        <input id="measurementWeight" type="number" step="0.1" class="form-control" name="weight" value="<?= htmlspecialchars($measurementFormValues['weight']) ?>">
                    </div>
                </div>

                <div class="mt-2 mb-3">
                    <label for="measurementNetLine" class="form-label">Net Line</label>
                    <input id="measurementNetLine" type="text" class="form-control" name="net_line" placeholder="e.g., Line 1, Line 2" value="<?= htmlspecialchars($measurementFormValues['net_line']) ?>">
                </div>

                <div class="mb-3">
                    <label for="measurementRemarks" class="form-label">Remarks</label>
                    <textarea id="measurementRemarks" class="form-control" name="remarks" rows="3"><?= htmlspecialchars($measurementFormValues['remarks']) ?></textarea>
                </div>

                <div class="species-modal-actions">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelMeasurementModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Measurement</button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>
</div>
<script>
function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue, found;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("speciesTable");
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

const openAddSpeciesModalBtn = document.getElementById("openAddSpeciesModal");
const speciesModal = document.getElementById("speciesModal");
const speciesModalBackdrop = document.getElementById("speciesModalBackdrop");
const closeSpeciesModalBtn = document.getElementById("closeSpeciesModal");
const cancelSpeciesModalBtn = document.getElementById("cancelSpeciesModal");

function openSpeciesModal() {
    if (!speciesModal || !speciesModalBackdrop) {
        return;
    }

    speciesModal.classList.add("is-open");
    speciesModalBackdrop.classList.add("is-open");
    speciesModal.setAttribute("aria-hidden", "false");
    speciesModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeSpeciesModal() {
    if (!speciesModal || !speciesModalBackdrop) {
        return;
    }

    speciesModal.classList.remove("is-open");
    speciesModalBackdrop.classList.remove("is-open");
    speciesModal.setAttribute("aria-hidden", "true");
    speciesModalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

if (openAddSpeciesModalBtn && speciesModal && speciesModalBackdrop && closeSpeciesModalBtn && cancelSpeciesModalBtn) {
    openAddSpeciesModalBtn.addEventListener("click", openSpeciesModal);
    closeSpeciesModalBtn.addEventListener("click", closeSpeciesModal);
    cancelSpeciesModalBtn.addEventListener("click", closeSpeciesModal);
    speciesModalBackdrop.addEventListener("click", closeSpeciesModal);
}

document.addEventListener("keydown", function(event) {
    if (speciesModal && event.key === "Escape" && speciesModal.classList.contains("is-open")) {
        closeSpeciesModal();
    }
});

const openMeasurementModalBtn = document.getElementById("openMeasurementModal");
const measurementModal = document.getElementById("measurementModal");
const measurementModalBackdrop = document.getElementById("measurementModalBackdrop");
const closeMeasurementModalBtn = document.getElementById("closeMeasurementModal");
const cancelMeasurementModalBtn = document.getElementById("cancelMeasurementModal");

function openMeasurementModal() {
    if (!measurementModal || !measurementModalBackdrop) {
        return;
    }

    measurementModal.classList.add("is-open");
    measurementModalBackdrop.classList.add("is-open");
    measurementModal.setAttribute("aria-hidden", "false");
    measurementModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeMeasurementModal() {
    if (!measurementModal || !measurementModalBackdrop) {
        return;
    }

    measurementModal.classList.remove("is-open");
    measurementModalBackdrop.classList.remove("is-open");
    measurementModal.setAttribute("aria-hidden", "true");
    measurementModalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

if (openMeasurementModalBtn && measurementModal && measurementModalBackdrop && closeMeasurementModalBtn && cancelMeasurementModalBtn) {
    openMeasurementModalBtn.addEventListener("click", openMeasurementModal);
    closeMeasurementModalBtn.addEventListener("click", closeMeasurementModal);
    cancelMeasurementModalBtn.addEventListener("click", closeMeasurementModal);
    measurementModalBackdrop.addEventListener("click", closeMeasurementModal);
}

document.addEventListener("keydown", function(event) {
    if (measurementModal && event.key === "Escape" && measurementModal.classList.contains("is-open")) {
        closeMeasurementModal();
    }
});

<?php if ($showAddModal): ?>
openSpeciesModal();
<?php endif; ?>

<?php if ($showMeasurementModal): ?>
openMeasurementModal();
<?php endif; ?>
</script>
</body>
</html>
