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

$activePage = 'birds';
$formError = '';
$showAddModal = false;
$observationFormError = '';
$showObservationModal = false;
$transectFormError = '';
$showTransectModal = false;
$birdClassificationOptions = ['Aves', 'Piciformes', 'Picidae'];
$formValues = [
    'species_code' => '',
    'common_name' => '',
    'scientific_name' => '',
    'classification' => 'Aves',
    'iucn_status' => 'LC',
    'denr_status' => 'NL',
];

$observationFormValues = [
    'transect_id' => '',
    'species_id' => '',
    'number_of_individuals' => '1',
    'distance' => '0',
    'time_observed' => '',
    'sex' => '',
    'age' => '',
    'activity' => '',
    'food_species' => '',
    'remarks' => '',
];

$transectFormValues = [
    'transect_name' => '',
    'location' => '',
    'survey_date' => '',
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

$classificationInputMap = [
    'RR' => 'RR',
    'RESIDENT' => 'RR',
    'REGULAR' => 'RR',
    'RESIDENT / REGULAR' => 'RR',
    'E' => 'E',
    'ENDEMIC' => 'E',
    'M' => 'M',
    'MIGRATORY' => 'M',
    'I' => 'I',
    'INTRODUCED' => 'I',
    'NSS' => 'NSS',
    'NO SPECIAL STATUS' => 'NSS',
    'AVES' => 'NSS',
    'PICIFORMES' => 'NSS',
    'PICIDAE' => 'NSS',
];

$activeBirdTab = $_GET['tab'] ?? 'species';
if (!in_array($activeBirdTab, ['species', 'observations', 'transects'], true)) {
    $activeBirdTab = 'species';
}

function buildBirdsTabUrl(string $tab, array $params = []): string
{
    return 'birds.php?' . http_build_query(array_merge(['tab' => $tab], $params));
}

$itemsPerPage = 10;
$speciesPage = max(1, (int) ($_GET['species_page'] ?? 1));
$observationsPage = max(1, (int) ($_GET['observations_page'] ?? 1));
$transectsPage = max(1, (int) ($_GET['transects_page'] ?? 1));

$columnStmt = $pdo->query("SHOW COLUMNS FROM bird_species");
$availableColumns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
$availableColumnsMap = array_flip($availableColumns);

if (!isset($availableColumnsMap['classification_text'])) {
    try {
        $pdo->exec("ALTER TABLE bird_species ADD COLUMN classification_text VARCHAR(255) NULL AFTER classification");
        $availableColumnsMap['classification_text'] = true;
    } catch (PDOException $e) {
        // If schema update fails, form still works; display falls back to enum mapping.
    }
}

$transectOptionsStmt = $pdo->query("SELECT transect_id, transect_name FROM bird_transects ORDER BY transect_name ASC");
$transectOptions = $transectOptionsStmt->fetchAll();

$speciesOptionsStmt = $pdo->query("SELECT species_id, common_name FROM bird_species ORDER BY common_name ASC");
$speciesOptions = $speciesOptionsStmt->fetchAll();

$validTransectIds = array_flip(array_map(static fn($row) => (string) $row['transect_id'], $transectOptions));
$validSpeciesIds = array_flip(array_map(static fn($row) => (string) $row['species_id'], $speciesOptions));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'species';

    if ($formType === 'observation') {
        $activeBirdTab = 'observations';
        $observationFormValues['transect_id'] = trim($_POST['transect_id'] ?? '');
        $observationFormValues['species_id'] = trim($_POST['species_id'] ?? '');
        $observationFormValues['number_of_individuals'] = trim($_POST['number_of_individuals'] ?? '1');
        $observationFormValues['distance'] = trim($_POST['distance'] ?? '0');
        $observationFormValues['time_observed'] = trim($_POST['time_observed'] ?? '');
        $observationFormValues['sex'] = trim($_POST['sex'] ?? '');
        $observationFormValues['age'] = trim($_POST['age'] ?? '');
        $observationFormValues['activity'] = trim($_POST['activity'] ?? '');
        $observationFormValues['food_species'] = trim($_POST['food_species'] ?? '');
        $observationFormValues['remarks'] = trim($_POST['remarks'] ?? '');

        if (
            $observationFormValues['transect_id'] === '' ||
            $observationFormValues['species_id'] === '' ||
            $observationFormValues['number_of_individuals'] === '' ||
            $observationFormValues['time_observed'] === ''
        ) {
            $observationFormError = 'Please fill out all required observation fields.';
            $showObservationModal = true;
        } elseif (!isset($validTransectIds[$observationFormValues['transect_id']]) || !isset($validSpeciesIds[$observationFormValues['species_id']])) {
            $observationFormError = 'Selected transect or species is invalid.';
            $showObservationModal = true;
        } else {
            try {
                $insertObservationStmt = $pdo->prepare(
                    "INSERT INTO bird_observations (transect_id, species_id, number_of_individuals, distance, time_observed, sex, age, activity, food_species, remarks)
                     VALUES (:transect_id, :species_id, :number_of_individuals, :distance, :time_observed, :sex, :age, :activity, :food_species, :remarks)"
                );

                $insertObservationStmt->execute([
                    ':transect_id' => (int) $observationFormValues['transect_id'],
                    ':species_id' => (int) $observationFormValues['species_id'],
                    ':number_of_individuals' => (int) $observationFormValues['number_of_individuals'],
                    ':distance' => $observationFormValues['distance'],
                    ':time_observed' => $observationFormValues['time_observed'],
                    ':sex' => $observationFormValues['sex'],
                    ':age' => $observationFormValues['age'],
                    ':activity' => $observationFormValues['activity'],
                    ':food_species' => $observationFormValues['food_species'],
                    ':remarks' => $observationFormValues['remarks'],
                ]);

                $lastInsertId = (int) $pdo->lastInsertId();
                logAuditAction($pdo, 'INSERT', 'bird_observations', $lastInsertId);

                header('Location: birds.php?tab=observations&obs_added=1');
                exit;
            } catch (PDOException $e) {
                $observationFormError = 'Unable to add observation. Please verify the values.';
                $showObservationModal = true;
            }
        }
    } elseif ($formType === 'transect') {
        $activeBirdTab = 'transects';
        $transectFormValues['transect_name'] = trim($_POST['transect_name'] ?? '');
        $transectFormValues['location'] = trim($_POST['location'] ?? '');
        $transectFormValues['survey_date'] = trim($_POST['survey_date'] ?? '');

        if ($transectFormValues['transect_name'] === '' || $transectFormValues['location'] === '' || $transectFormValues['survey_date'] === '') {
            $transectFormError = 'Please fill out all required fields.';
            $showTransectModal = true;
        } else {
            try {
                // Check which columns are available in bird_transects table
                $transectColumnStmt = $pdo->query("SHOW COLUMNS FROM bird_transects");
                $transectColumns = array_map(static fn($col) => $col['Field'], $transectColumnStmt->fetchAll());
                $transectColumnsMap = array_flip($transectColumns);

                $insertValues = [
                    'transect_name' => $transectFormValues['transect_name'],
                    'location' => $transectFormValues['location'],
                    'survey_date' => $transectFormValues['survey_date'],
                    'created_by' => 'John Doe',
                    'last_edited_by' => 'John Doe',
                ];

                $insertColumns = [];
                $insertParams = [];

                foreach ($insertValues as $column => $value) {
                    if (isset($transectColumnsMap[$column])) {
                        $insertColumns[] = $column;
                        $insertParams[":$column"] = $value;
                    }
                }

                $sql = 'INSERT INTO bird_transects (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
                $insertStmt = $pdo->prepare($sql);
                $insertStmt->execute($insertParams);

                $lastInsertId = (int) $pdo->lastInsertId();
                logAuditAction($pdo, 'INSERT', 'bird_transects', $lastInsertId);

                header('Location: birds.php?tab=transects&transect_added=1');
                exit;
            } catch (PDOException $e) {
                $transectFormError = 'Unable to add transect. Please verify the values.';
                $showTransectModal = true;
            }
        }
    } else {
        $formValues['species_code'] = trim($_POST['species_code'] ?? '');
        $formValues['common_name'] = trim($_POST['common_name'] ?? '');
        $formValues['scientific_name'] = trim($_POST['scientific_name'] ?? '');
        $selectedClassification = trim($_POST['classification'] ?? 'Aves');
        if (!in_array($selectedClassification, $birdClassificationOptions, true)) {
            $selectedClassification = 'Aves';
        }
        $formValues['classification'] = $selectedClassification;
        $formValues['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
        $formValues['denr_status'] = trim($_POST['denr_status'] ?? 'NL');

        $classificationTypedValue = $formValues['classification'];
        $classificationForEnum = 'NSS';
        $classificationKey = strtoupper(preg_replace('/\s+/', ' ', $formValues['classification']));
        if (isset($classificationInputMap[$classificationKey])) {
            $classificationForEnum = $classificationInputMap[$classificationKey];
        }

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
                'classification' => $classificationForEnum,
                'classification_text' => $classificationTypedValue,
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
                    $sql = 'INSERT INTO bird_species (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
                    $insertStmt = $pdo->prepare($sql);
                    $insertStmt->execute($insertParams);

                    $lastInsertId = (int) $pdo->lastInsertId();
                    logAuditAction($pdo, 'INSERT', 'bird_species', $lastInsertId);

                    header('Location: birds.php?added=1');
                    exit;
                } catch (PDOException $e) {
                    $formError = 'Unable to add species. Please check if the species code already exists.';
                    $showAddModal = true;
                }
            }
        }
    }
}

// Fetch bird species from the database
$selectColumns = ['species_id', 'species_code', 'common_name', 'scientific_name', 'classification', 'iucn_status'];

if (isset($availableColumnsMap['classification_text'])) {
    $selectColumns[] = 'classification_text';
} else {
    $selectColumns[] = 'NULL AS classification_text';
}

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

$query = 'SELECT ' . implode(', ', $selectColumns) . ' FROM bird_species';
$speciesTotalCountStmt = $pdo->query('SELECT COUNT(*) FROM bird_species');
$speciesTotalCount = (int) $speciesTotalCountStmt->fetchColumn();
$speciesTotalPages = $speciesTotalCount > 0 ? (int) ceil($speciesTotalCount / $itemsPerPage) : 0;
$speciesPage = $speciesTotalPages > 0 ? min($speciesPage, $speciesTotalPages) : 1;
$speciesOffset = ($speciesPage - 1) * $itemsPerPage;
$speciesPagedQuery = $query . ' ORDER BY species_id DESC LIMIT ' . (int) $itemsPerPage . ' OFFSET ' . (int) $speciesOffset;
$stmt = $pdo->query($speciesPagedQuery);

$species = $stmt->fetchAll();

$observations = [];
if ($activeBirdTab === 'observations') {
    $observationTotalCountStmt = $pdo->query('SELECT COUNT(*) FROM bird_observations');
    $observationTotalCount = (int) $observationTotalCountStmt->fetchColumn();
    $observationTotalPages = $observationTotalCount > 0 ? (int) ceil($observationTotalCount / $itemsPerPage) : 0;
    $observationsPage = $observationTotalPages > 0 ? min($observationsPage, $observationTotalPages) : 1;
    $observationOffset = ($observationsPage - 1) * $itemsPerPage;

    $observationSql = "
        SELECT
            bo.observation_id,
            bo.transect_id,
            bo.species_id,
            bt.transect_name,
            bs.common_name,
            bo.number_of_individuals,
            bo.distance,
            bo.time_observed,
            bo.sex,
            bo.age,
            bo.activity
        FROM bird_observations bo
        LEFT JOIN bird_transects bt ON bt.transect_id = bo.transect_id
        LEFT JOIN bird_species bs ON bs.species_id = bo.species_id
        ORDER BY bo.observation_id DESC
        LIMIT " . (int) $itemsPerPage . " OFFSET " . (int) $observationOffset . "
    ";

    $observationStmt = $pdo->query($observationSql);
    $observations = $observationStmt->fetchAll();

    if (count($observations) === 0) {
        $observations = [
            [
                'transect_name' => 'Mt. Makiling Trail A',
                'observation_id' => null,
                'common_name' => 'Philippine Pygmy Woodpecker',
                'number_of_individuals' => '2',
                'distance' => '15.5',
                'time_observed' => '06:30:00',
                'sex' => 'Male',
                'age' => 'Adult',
                'activity' => 'Foraging',
                'created_by' => 'John Doe',
            ],
            [
                'transect_name' => 'Mt. Makiling Trail A',
                'observation_id' => null,
                'common_name' => 'Tarictic Hornbill',
                'number_of_individuals' => '4',
                'distance' => '25',
                'time_observed' => '07:15:00',
                'sex' => 'Mixed',
                'age' => 'Adult',
                'activity' => 'Flying',
                'created_by' => 'Jane Smith',
            ],
        ];
    }
}

$transects = [];
if ($activeBirdTab === 'transects') {
    $transectTotalCountStmt = $pdo->query('SELECT COUNT(*) FROM bird_transects');
    $transectTotalCount = (int) $transectTotalCountStmt->fetchColumn();
    $transectTotalPages = $transectTotalCount > 0 ? (int) ceil($transectTotalCount / $itemsPerPage) : 0;
    $transectsPage = $transectTotalPages > 0 ? min($transectsPage, $transectTotalPages) : 1;
    $transectOffset = ($transectsPage - 1) * $itemsPerPage;

    $transectColumnStmt = $pdo->query("SHOW COLUMNS FROM bird_transects");
    $transectColumns = array_map(static fn($col) => $col['Field'], $transectColumnStmt->fetchAll());
    $transectColumnsMap = array_flip($transectColumns);

    $transectSelectColumns = [
        'transect_id',
        'transect_name',
        isset($transectColumnsMap['location']) ? 'location' : 'NULL AS location',
        isset($transectColumnsMap['survey_date']) ? 'survey_date' : 'NULL AS survey_date',
        isset($transectColumnsMap['created_by']) ? 'created_by' : 'NULL AS created_by',
    ];

    $transectSql = 'SELECT ' . implode(', ', $transectSelectColumns) . ' FROM bird_transects ORDER BY transect_id DESC LIMIT ' . (int) $itemsPerPage . ' OFFSET ' . (int) $transectOffset;

    $transectStmt = $pdo->query($transectSql);
    $transects = $transectStmt->fetchAll();
}

function iucnBadgeClass($status)
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
    <title>Bird Species Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4">
            <h1 class="mb-1">Birds Records</h1>
            <p class="text-muted mb-0">Manage bird species and observation data</p>
        </div>

        <div class="records-tabs mb-4">
            <button class="tab-pill <?= $activeBirdTab === 'species' ? 'active' : '' ?>" type="button" onclick="window.location.href='birds.php?tab=species'">Species</button>
            <button class="tab-pill <?= $activeBirdTab === 'observations' ? 'active' : '' ?>" type="button" onclick="window.location.href='birds.php?tab=observations'">Observations</button>
            <button class="tab-pill <?= $activeBirdTab === 'transects' ? 'active' : '' ?>" type="button" onclick="window.location.href='birds.php?tab=transects'">Transects</button>
        </div>

        <?php if ($activeBirdTab === 'species'): ?>
        <section class="birds-card">
            <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Bird species added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Bird species archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Bird Species</h2>
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
                            <td><?= htmlspecialchars(trim((string) ($row['classification_text'] ?? '')) !== '' ? $row['classification_text'] : ($classificationLabels[$row['classification']] ?? ($row['classification'] ?? 'N/A'))) ?></td>
                            <td>
                                <span class="status-badge <?= iucnBadgeClass($row['iucn_status']) ?>">
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
                                        <a href="edit_bird_species.php?id=<?= urlencode((string) $row['species_id']) ?>" class="action-link" aria-label="Edit record"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=bird_species&id=<?= urlencode((string) $row['species_id']) ?>" class="action-link" aria-label="Archive record" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
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
                    <nav aria-label="Species pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $speciesPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $speciesPage <= 1 ? '#' : htmlspecialchars(buildBirdsTabUrl('species', ['species_page' => $speciesPage - 1])) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $speciesPage >= $speciesTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $speciesPage >= $speciesTotalPages ? '#' : htmlspecialchars(buildBirdsTabUrl('species', ['species_page' => $speciesPage + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
        <?php elseif ($activeBirdTab === 'observations'): ?>
        <section class="birds-card">
            <?php if (isset($_GET['obs_added']) && $_GET['obs_added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Bird observation added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
                <div class="alert alert-info" role="alert">
                    Bird observation archived successfully.
                </div>
            <?php endif; ?>

            <?php if ($observationFormError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($observationFormError) ?>
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Bird Observations</h2>
                <button class="btn btn-success add-species-btn" id="openObservationModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Observation
                </button>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="speciesTable">
                    <thead>
                        <tr>
                            <th>Transect</th>
                            <th>Species</th>
                            <th>Individuals</th>
                            <th>Distance (m)</th>
                            <th>Time</th>
                            <th>Sex</th>
                            <th>Age</th>
                            <th>Activity</th>
                            <th>Created By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($observations as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['transect_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['common_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['number_of_individuals'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['distance'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(isset($row['time_observed']) ? substr((string) $row['time_observed'], 0, 5) : 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['sex'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['age'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['activity'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['created_by'] ?? 'John Doe') ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if (isset($row['observation_id']) && (int) $row['observation_id'] > 0): ?>
                                        <a href="edit_bird_observation.php?id=<?= urlencode((string) $row['observation_id']) ?>" class="action-link" aria-label="Edit observation"><i class="bi bi-pencil-square"></i></a>
                                        <a href="archive_handler.php?type=bird_observation&id=<?= urlencode((string) $row['observation_id']) ?>" class="action-link" aria-label="Archive observation" onclick="return confirm('Archive this record?')"><i class="bi bi-archive"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($observationTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $observationsPage) ?> of <?= htmlspecialchars((string) $observationTotalPages) ?></small>
                    <nav aria-label="Observations pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $observationsPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $observationsPage <= 1 ? '#' : htmlspecialchars(buildBirdsTabUrl('observations', ['observations_page' => $observationsPage - 1])) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $observationsPage >= $observationTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $observationsPage >= $observationTotalPages ? '#' : htmlspecialchars(buildBirdsTabUrl('observations', ['observations_page' => $observationsPage + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="birds-card">
            <?php if (isset($_GET['transect_added']) && $_GET['transect_added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Transect added successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                <div class="alert alert-success" role="alert">
                    Transect added successfully.
                </div>
            <?php endif; ?>

            <div class="birds-card-head">
                <h2>Bird Transects</h2>
                <button class="btn btn-success add-species-btn" id="openAddTransectModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    Add Transect
                </button>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="speciesTable">
                    <thead>
                        <tr>
                            <th>Transect Name</th>
                            <th>Location</th>
                            <th>Survey Date</th>
                            <th>Created By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transects) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No transect records found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transects as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['transect_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['location'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['survey_date'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(($row['created_by'] ?? '') !== '' ? $row['created_by'] : 'John Doe') ?></td>
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($transectTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $transectsPage) ?> of <?= htmlspecialchars((string) $transectTotalPages) ?></small>
                    <nav aria-label="Transects pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $transectsPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $transectsPage <= 1 ? '#' : htmlspecialchars(buildBirdsTabUrl('transects', ['transects_page' => $transectsPage - 1])) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $transectsPage >= $transectTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $transectsPage >= $transectTotalPages ? '#' : htmlspecialchars(buildBirdsTabUrl('transects', ['transects_page' => $transectsPage + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <?php if ($activeBirdTab === 'species'): ?>
    <div class="species-modal-backdrop" id="speciesModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="speciesModal" role="dialog" aria-modal="true" aria-labelledby="speciesModalTitle" aria-hidden="true">
        <div class="species-modal-dialog">
            <div class="species-modal-head">
                <h3 id="speciesModalTitle">Add Bird Species</h3>
                <button type="button" class="modal-close-btn" id="closeSpeciesModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="birds.php" method="post">
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
                        <?php foreach ($birdClassificationOptions as $classificationOption): ?>
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

    <?php if ($activeBirdTab === 'observations'): ?>
    <div class="species-modal-backdrop" id="observationModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="observationModal" role="dialog" aria-modal="true" aria-labelledby="observationModalTitle" aria-hidden="true">
        <div class="species-modal-dialog observation-modal-dialog">
            <div class="species-modal-head">
                <h3 id="observationModalTitle">Add Bird Observation</h3>
                <button type="button" class="modal-close-btn" id="closeObservationModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form class="species-form" action="birds.php?tab=observations" method="post">
                <input type="hidden" name="form_type" value="observation">

                <div class="observation-fields-grid">
                    <div>
                        <label for="obsTransect" class="form-label">Transect</label>
                        <select id="obsTransect" class="form-select" name="transect_id" required>
                            <option value="">Select transect</option>
                            <?php foreach ($transectOptions as $transect): ?>
                                <option value="<?= htmlspecialchars((string) $transect['transect_id']) ?>" <?= $observationFormValues['transect_id'] === (string) $transect['transect_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($transect['transect_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="obsSpecies" class="form-label">Species</label>
                        <select id="obsSpecies" class="form-select" name="species_id" required>
                            <option value="">Select species</option>
                            <?php foreach ($speciesOptions as $speciesOption): ?>
                                <option value="<?= htmlspecialchars((string) $speciesOption['species_id']) ?>" <?= $observationFormValues['species_id'] === (string) $speciesOption['species_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($speciesOption['common_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="obsIndividuals" class="form-label">Individuals</label>
                        <input id="obsIndividuals" type="number" min="1" class="form-control" name="number_of_individuals" value="<?= htmlspecialchars($observationFormValues['number_of_individuals']) ?>" required>
                    </div>

                    <div>
                        <label for="obsDistance" class="form-label">Distance (m)</label>
                        <input id="obsDistance" type="text" class="form-control" name="distance" value="<?= htmlspecialchars($observationFormValues['distance']) ?>" required>
                    </div>

                    <div>
                        <label for="obsTime" class="form-label">Time Observed</label>
                        <input id="obsTime" type="time" class="form-control" name="time_observed" value="<?= htmlspecialchars($observationFormValues['time_observed']) ?>" required>
                    </div>

                    <div></div>

                    <div>
                        <label for="obsSex" class="form-label">Sex</label>
                        <input id="obsSex" type="text" class="form-control" name="sex" placeholder="e.g, Male, Female" value="<?= htmlspecialchars($observationFormValues['sex']) ?>">
                    </div>

                    <div>
                        <label for="obsAge" class="form-label">Age</label>
                        <input id="obsAge" type="text" class="form-control" name="age" placeholder="e.g, Adult, Juvenile" value="<?= htmlspecialchars($observationFormValues['age']) ?>">
                    </div>

                    <div class="full-width">
                        <label for="obsActivity" class="form-label">Activity</label>
                        <input id="obsActivity" type="text" class="form-control" name="activity" placeholder="e.g, Foraging, Flying" value="<?= htmlspecialchars($observationFormValues['activity']) ?>">
                    </div>

                    <div class="full-width">
                        <label for="obsFoodSpecies" class="form-label">Food Species</label>
                        <input id="obsFoodSpecies" type="text" class="form-control" name="food_species" placeholder="e.g, Insecta, Ficus sp." value="<?= htmlspecialchars($observationFormValues['food_species']) ?>">
                    </div>

                    <div class="full-width">
                        <label for="obsRemarks" class="form-label">Remarks</label>
                        <textarea id="obsRemarks" class="form-control" name="remarks" rows="3"><?= htmlspecialchars($observationFormValues['remarks']) ?></textarea>
                    </div>
                </div>

                <div class="species-modal-actions mt-3">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelObservationModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Observation</button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($activeBirdTab === 'transects'): ?>
    <div class="species-modal-backdrop" id="transectModalBackdrop" aria-hidden="true"></div>
    <section class="species-modal" id="transectModal" role="dialog" aria-modal="true" aria-labelledby="transectModalTitle" aria-hidden="true">
        <div class="species-modal-dialog">
            <div class="species-modal-head">
                <h3 id="transectModalTitle">Add Transect</h3>
                <button type="button" class="modal-close-btn" id="closeTransectModal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <?php if ($transectFormError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($transectFormError) ?>
                </div>
            <?php endif; ?>

            <form class="species-form" action="birds.php?tab=transects" method="post">
                <input type="hidden" name="form_type" value="transect">
                <div class="mb-3">
                    <label for="transectName" class="form-label">Transect Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="transectName" name="transect_name" value="<?= htmlspecialchars($transectFormValues['transect_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="transectLocation" class="form-label">Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="transectLocation" name="location" placeholder="e.g, Laguna, Philippines (14.1333deg N, 121.2167deg E)" value="<?= htmlspecialchars($transectFormValues['location']) ?>" required>
                </div>

                <div class="mb-4">
                    <label for="transectSurveyDate" class="form-label">Survey Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="transectSurveyDate" name="survey_date" value="<?= htmlspecialchars($transectFormValues['survey_date']) ?>" required>
                </div>

                <div class="species-modal-actions">
                    <button type="button" class="btn btn-light btn-cancel" id="cancelTransectModal">Cancel</button>
                    <button type="submit" class="btn btn-success add-species-btn">Add Transect</button>
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

const openObservationModalBtn = document.getElementById("openObservationModal");
const observationModal = document.getElementById("observationModal");
const observationModalBackdrop = document.getElementById("observationModalBackdrop");
const closeObservationModalBtn = document.getElementById("closeObservationModal");
const cancelObservationModalBtn = document.getElementById("cancelObservationModal");

function openObservationModal() {
    if (!observationModal || !observationModalBackdrop) {
        return;
    }

    observationModal.classList.add("is-open");
    observationModalBackdrop.classList.add("is-open");
    observationModal.setAttribute("aria-hidden", "false");
    observationModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeObservationModal() {
    if (!observationModal || !observationModalBackdrop) {
        return;
    }

    observationModal.classList.remove("is-open");
    observationModalBackdrop.classList.remove("is-open");
    observationModal.setAttribute("aria-hidden", "true");
    observationModalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

if (openObservationModalBtn && observationModal && observationModalBackdrop && closeObservationModalBtn && cancelObservationModalBtn) {
    openObservationModalBtn.addEventListener("click", openObservationModal);
    closeObservationModalBtn.addEventListener("click", closeObservationModal);
    cancelObservationModalBtn.addEventListener("click", closeObservationModal);
    observationModalBackdrop.addEventListener("click", closeObservationModal);
}

document.addEventListener("keydown", function(event) {
    if (observationModal && event.key === "Escape" && observationModal.classList.contains("is-open")) {
        closeObservationModal();
    }
});

const openTransectModalBtn = document.getElementById("openAddTransectModal");
const transectModal = document.getElementById("transectModal");
const transectModalBackdrop = document.getElementById("transectModalBackdrop");
const closeTransectModalBtn = document.getElementById("closeTransectModal");
const cancelTransectModalBtn = document.getElementById("cancelTransectModal");

function openTransectModal() {
    if (!transectModal || !transectModalBackdrop) {
        return;
    }

    transectModal.classList.add("is-open");
    transectModalBackdrop.classList.add("is-open");
    transectModal.setAttribute("aria-hidden", "false");
    transectModalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeTransectModal() {
    if (!transectModal || !transectModalBackdrop) {
        return;
    }

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
openSpeciesModal();
<?php endif; ?>

<?php if ($showObservationModal): ?>
openObservationModal();
<?php endif; ?>

<?php if ($showTransectModal): ?>
openTransectModal();
<?php endif; ?>
</script>
</body>
</html>
