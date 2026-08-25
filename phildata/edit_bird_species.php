<?php
include 'db_connect.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: birds.php?tab=species');
    exit;
}

$formError = '';

$columnStmt = $pdo->query("SHOW COLUMNS FROM bird_species");
$availableColumns = array_map(static fn($col) => $col['Field'], $columnStmt->fetchAll());
$availableColumnsMap = array_flip($availableColumns);

$selectColumns = ['species_id', 'species_code', 'common_name', 'scientific_name', 'classification', 'iucn_status'];
$selectColumns[] = isset($availableColumnsMap['classification_text']) ? 'classification_text' : 'NULL AS classification_text';
$selectColumns[] = isset($availableColumnsMap['denr_status']) ? 'denr_status' : 'NULL AS denr_status';

$stmt = $pdo->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM bird_species WHERE species_id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if (!$record) {
    header('Location: birds.php?tab=species');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record['species_code'] = trim($_POST['species_code'] ?? '');
    $record['common_name'] = trim($_POST['common_name'] ?? '');
    $record['scientific_name'] = trim($_POST['scientific_name'] ?? '');
    $record['classification_text'] = trim($_POST['classification'] ?? '');
    $record['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
    $record['denr_status'] = trim($_POST['denr_status'] ?? 'NL');

    if ($record['species_code'] === '' || $record['common_name'] === '' || $record['scientific_name'] === '') {
        $formError = 'Please fill out all required fields.';
    } else {
        $updateValues = [
            'species_code' => $record['species_code'],
            'common_name' => $record['common_name'],
            'scientific_name' => $record['scientific_name'],
            'classification' => 'NSS',
            'classification_text' => $record['classification_text'],
            'iucn_status' => $record['iucn_status'],
            'denr_status' => $record['denr_status'],
            'last_edited_by' => 'John Doe',
        ];

        $setParts = [];
        $params = [':id' => $id];

        foreach ($updateValues as $column => $value) {
            if (isset($availableColumnsMap[$column])) {
                $setParts[] = $column . ' = :' . $column;
                $params[':' . $column] = $value;
            }
        }

        $sql = 'UPDATE bird_species SET ' . implode(', ', $setParts) . ' WHERE species_id = :id';
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        header('Location: birds.php?tab=species');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bird Species</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php $activePage = 'birds'; include 'sidebar.php'; ?>
        <main class="flex-grow-1 main-content">
            <section class="birds-card">
                <div class="birds-card-head">
                    <h2>Edit Bird Species</h2>
                </div>

                <?php if ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($formError) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="edit-form">
                    <div class="mb-3">
                        <label class="form-label" for="speciesCode">Species Code</label>
                        <input id="speciesCode" class="form-control" name="species_code" value="<?= htmlspecialchars($record['species_code']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="commonName">Common Name</label>
                        <input id="commonName" class="form-control" name="common_name" value="<?= htmlspecialchars($record['common_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="scientificName">Scientific Name</label>
                        <input id="scientificName" class="form-control" name="scientific_name" value="<?= htmlspecialchars($record['scientific_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="classification">Classification</label>
                        <input id="classification" class="form-control" name="classification" value="<?= htmlspecialchars($record['classification_text'] ?: $record['classification']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iucnStatus">IUCN Status</label>
                        <select id="iucnStatus" class="form-select" name="iucn_status">
                            <option value="LC" <?= $record['iucn_status'] === 'LC' ? 'selected' : '' ?>>Least Concern (LC)</option>
                            <option value="NT" <?= $record['iucn_status'] === 'NT' ? 'selected' : '' ?>>Near Threatened (NT)</option>
                            <option value="VU" <?= $record['iucn_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable (VU)</option>
                            <option value="EN" <?= $record['iucn_status'] === 'EN' ? 'selected' : '' ?>>Endangered (EN)</option>
                            <option value="CR" <?= $record['iucn_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered (CR)</option>
                            <option value="DD" <?= $record['iucn_status'] === 'DD' ? 'selected' : '' ?>>Data Deficient (DD)</option>
                            <option value="NE" <?= $record['iucn_status'] === 'NE' ? 'selected' : '' ?>>Not Evaluated (NE)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="denrStatus">DENR Status</label>
                        <select id="denrStatus" class="form-select" name="denr_status">
                            <option value="NL" <?= $record['denr_status'] === 'NL' ? 'selected' : '' ?>>Not Listed (NL)</option>
                            <option value="OTS" <?= $record['denr_status'] === 'OTS' ? 'selected' : '' ?>>Other Threatened Species (OTS)</option>
                            <option value="VU" <?= $record['denr_status'] === 'VU' ? 'selected' : '' ?>>Vulnerable (VU)</option>
                            <option value="EN" <?= $record['denr_status'] === 'EN' ? 'selected' : '' ?>>Endangered (EN)</option>
                            <option value="CR" <?= $record['denr_status'] === 'CR' ? 'selected' : '' ?>>Critically Endangered (CR)</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-light" href="birds.php?tab=species">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Species</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
