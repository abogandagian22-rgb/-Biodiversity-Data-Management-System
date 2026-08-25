<?php
include 'db_connect.php';

$activePage = 'archive';
$archiveTab = $_GET['tab'] ?? 'birds';
if (!in_array($archiveTab, ['birds', 'bats', 'flora', 'transect'], true)) {
    $archiveTab = 'birds';
}

$itemsPerPage = 10;
$archivePage = max(1, (int) ($_GET['page'] ?? 1));

$records = [];

try {
    if ($archiveTab === 'birds') {
        // Try to get archived bird species
        try {
            $stmt = $pdo->query('SELECT species_id, species_code, common_name, scientific_name FROM archive_bird_species ORDER BY species_code');
            $speciesRecords = $stmt->fetchAll();
            foreach ($speciesRecords as $row) {
                $records[] = [
                    'id' => $row['species_id'],
                    'type' => 'bird_species',
                    'name' => $row['common_name'],
                    'code' => $row['species_code'],
                    'scientific' => $row['scientific_name'],
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }

        // Try to get archived bird observations
        try {
            $stmt = $pdo->query('SELECT observation_id FROM archive_bird_observations ORDER BY observation_id DESC');
            $obsRecords = $stmt->fetchAll();
            foreach ($obsRecords as $row) {
                $records[] = [
                    'id' => $row['observation_id'],
                    'type' => 'bird_observation',
                    'name' => 'Bird Observation',
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    } elseif ($archiveTab === 'bats') {
        // Try to get archived bat species
        try {
            $stmt = $pdo->query('SELECT species_id, species_code, common_name, scientific_name FROM archive_bat_species ORDER BY species_code');
            $speciesRecords = $stmt->fetchAll();
            foreach ($speciesRecords as $row) {
                $records[] = [
                    'id' => $row['species_id'],
                    'type' => 'bat_species',
                    'name' => $row['common_name'],
                    'code' => $row['species_code'],
                    'scientific' => $row['scientific_name'],
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }

        // Try to get archived bat measurements
        try {
            $stmt = $pdo->query('SELECT bat_id FROM archive_bats_measurements ORDER BY bat_id DESC');
            $measRecords = $stmt->fetchAll();
            foreach ($measRecords as $row) {
                $records[] = [
                    'id' => $row['bat_id'],
                    'type' => 'bat_measurement',
                    'name' => 'Bat Measurement',
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    } elseif ($archiveTab === 'flora') {
        // Try to get archived flora records
        try {
            $stmt = $pdo->query('SELECT record_id, local_name, scientific_name FROM archive_flora ORDER BY local_name');
            $floraRecords = $stmt->fetchAll();
            foreach ($floraRecords as $row) {
                $records[] = [
                    'id' => $row['record_id'],
                    'type' => 'flora',
                    'name' => $row['local_name'],
                    'scientific' => $row['scientific_name'],
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    } elseif ($archiveTab === 'transect') {
        // Try to get archived transects
        try {
            $stmt = $pdo->query('SELECT transect_id, transect_name, location FROM archive_transects ORDER BY transect_name');
            $transectRecords = $stmt->fetchAll();
            foreach ($transectRecords as $row) {
                $records[] = [
                    'id' => $row['transect_id'],
                    'type' => 'transect',
                    'name' => $row['transect_name'],
                    'location' => $row['location'],
                ];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    }
} catch (Exception $e) {
    $records = [];
}

$archiveTotalCount = count($records);
$archiveTotalPages = $archiveTotalCount > 0 ? (int) ceil($archiveTotalCount / $itemsPerPage) : 0;
$archivePage = $archiveTotalPages > 0 ? min($archivePage, $archiveTotalPages) : 1;
$archiveOffset = ($archivePage - 1) * $itemsPerPage;
$records = array_slice($records, $archiveOffset, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Archive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex app-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content birds-page p-4 w-100">
        <div class="page-head mb-4">
            <h1 class="mb-1">Archive</h1>
            <p class="text-muted mb-0">View archived biodiversity records (Read-only)</p>
        </div>

        <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
            <div class="alert alert-success mb-4" role="alert">
                Record archived successfully!
            </div>
        <?php endif; ?>

        <div class="records-tabs mb-4">
            <button class="tab-pill <?= $archiveTab === 'birds' ? 'active' : '' ?>" type="button" onclick="window.location.href='archive.php?tab=birds'">Birds Archive</button>
            <button class="tab-pill <?= $archiveTab === 'bats' ? 'active' : '' ?>" type="button" onclick="window.location.href='archive.php?tab=bats'">Bats Archive</button>
            <button class="tab-pill <?= $archiveTab === 'flora' ? 'active' : '' ?>" type="button" onclick="window.location.href='archive.php?tab=flora'">Flora Archive</button>
            <button class="tab-pill <?= $archiveTab === 'transect' ? 'active' : '' ?>" type="button" onclick="window.location.href='archive.php?tab=transect'">Transect Archive</button>
        </div>

        <section class="birds-card">
            <div class="birds-card-head">
                <h2><?= htmlspecialchars(ucfirst($archiveTab) . ' Archive') ?></h2>
            </div>

            <div class="table-wrap">
                <table class="table align-middle mb-0" id="archiveTable">
                    <thead>
                        <tr>
                            <th>Source Table</th>
                            <th>Record Data</th>
                            <th>Created By</th>
                            <th>Last Edited By</th>
                            <th>Archived By</th>
                            <th>Archived Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $row): ?>
                            <tr>
                                <td><span class="table-tag"><?= htmlspecialchars(str_replace('_', ' ', $row['type'])) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                    <?php if (isset($row['scientific'])): ?>
                                        <div><em><?= htmlspecialchars($row['scientific']) ?></em></div>
                                    <?php endif; ?>
                                    <?php if (isset($row['code'])): ?>
                                        <small class="text-muted">Code: <?= htmlspecialchars($row['code']) ?></small>
                                    <?php endif; ?>
                                    <?php if (isset($row['location'])): ?>
                                        <small class="text-muted">Location: <?= htmlspecialchars($row['location']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>—</td>
                                <td>—</td>
                                <td><strong>John Doe</strong></td>
                                <td><?= date('n/j/Y') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No archived records yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($archiveTotalCount > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Page <?= htmlspecialchars((string) $archivePage) ?> of <?= htmlspecialchars((string) $archiveTotalPages) ?></small>
                    <nav aria-label="Archive pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $archivePage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $archivePage <= 1 ? '#' : htmlspecialchars('archive.php?tab=' . urlencode($archiveTab) . '&page=' . ($archivePage - 1)) ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $archivePage >= $archiveTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $archivePage >= $archiveTotalPages ? '#' : htmlspecialchars('archive.php?tab=' . urlencode($archiveTab) . '&page=' . ($archivePage + 1)) ?>">Next</a>
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
