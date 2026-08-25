<?php
include 'db_connect.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: birds.php?tab=observations');
    exit;
}

$formError = '';

$transects = $pdo->query("SELECT transect_id, transect_name FROM bird_transects ORDER BY transect_name")->fetchAll();
$speciesOptions = $pdo->query("SELECT species_id, common_name FROM bird_species ORDER BY common_name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM bird_observations WHERE observation_id = :id");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if (!$record) {
    header('Location: birds.php?tab=observations');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record['transect_id'] = (int) ($_POST['transect_id'] ?? 0);
    $record['species_id'] = (int) ($_POST['species_id'] ?? 0);
    $record['number_of_individuals'] = (int) ($_POST['number_of_individuals'] ?? 0);
    $record['distance'] = trim($_POST['distance'] ?? '');
    $record['time_observed'] = trim($_POST['time_observed'] ?? '');
    $record['sex'] = trim($_POST['sex'] ?? '');
    $record['age'] = trim($_POST['age'] ?? '');
    $record['activity'] = trim($_POST['activity'] ?? '');
    $record['food_species'] = trim($_POST['food_species'] ?? '');
    $record['remarks'] = trim($_POST['remarks'] ?? '');

    if ($record['transect_id'] <= 0 || $record['species_id'] <= 0 || $record['number_of_individuals'] <= 0 || $record['time_observed'] === '') {
        $formError = 'Please fill out required fields.';
    } else {
        $updateStmt = $pdo->prepare("UPDATE bird_observations SET transect_id=:transect_id, species_id=:species_id, number_of_individuals=:number_of_individuals, distance=:distance, time_observed=:time_observed, sex=:sex, age=:age, activity=:activity, food_species=:food_species, remarks=:remarks WHERE observation_id=:id");
        $updateStmt->execute([
            ':transect_id' => $record['transect_id'],
            ':species_id' => $record['species_id'],
            ':number_of_individuals' => $record['number_of_individuals'],
            ':distance' => $record['distance'],
            ':time_observed' => $record['time_observed'],
            ':sex' => $record['sex'],
            ':age' => $record['age'],
            ':activity' => $record['activity'],
            ':food_species' => $record['food_species'],
            ':remarks' => $record['remarks'],
            ':id' => $id,
        ]);

        header('Location: birds.php?tab=observations');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bird Observation</title>
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
                    <h2>Edit Bird Observation</h2>
                </div>

                <?php if ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($formError) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="edit-form">
                    <div class="observation-fields-grid">
                        <div>
                            <label class="form-label" for="transectId">Transect</label>
                            <select id="transectId" class="form-select" name="transect_id" required>
                                <?php foreach ($transects as $t): ?>
                                    <option value="<?= (int) $t['transect_id'] ?>" <?= (int) $record['transect_id'] === (int) $t['transect_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['transect_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="speciesId">Species</label>
                            <select id="speciesId" class="form-select" name="species_id" required>
                                <?php foreach ($speciesOptions as $s): ?>
                                    <option value="<?= (int) $s['species_id'] ?>" <?= (int) $record['species_id'] === (int) $s['species_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['common_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="individuals">No. of Individuals</label>
                            <input id="individuals" class="form-control" type="number" name="number_of_individuals" value="<?= htmlspecialchars((string) $record['number_of_individuals']) ?>" required>
                        </div>

                        <div>
                            <label class="form-label" for="distance">Distance</label>
                            <input id="distance" class="form-control" name="distance" value="<?= htmlspecialchars($record['distance']) ?>">
                        </div>

                        <div>
                            <label class="form-label" for="timeObserved">Time</label>
                            <input id="timeObserved" class="form-control" type="time" name="time_observed" value="<?= htmlspecialchars(substr((string) $record['time_observed'], 0, 5)) ?>" required>
                        </div>

                        <div>
                            <label class="form-label" for="sex">Sex</label>
                            <input id="sex" class="form-control" name="sex" value="<?= htmlspecialchars($record['sex']) ?>">
                        </div>

                        <div>
                            <label class="form-label" for="age">Age</label>
                            <input id="age" class="form-control" name="age" value="<?= htmlspecialchars($record['age']) ?>">
                        </div>

                        <div>
                            <label class="form-label" for="activity">Activity</label>
                            <input id="activity" class="form-control" name="activity" value="<?= htmlspecialchars($record['activity']) ?>">
                        </div>

                        <div>
                            <label class="form-label" for="foodSpecies">Food Species</label>
                            <input id="foodSpecies" class="form-control" name="food_species" value="<?= htmlspecialchars($record['food_species']) ?>">
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="remarks">Remarks</label>
                            <textarea id="remarks" class="form-control" name="remarks" rows="3"><?= htmlspecialchars($record['remarks']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions mt-3">
                        <a class="btn btn-light" href="birds.php?tab=observations">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Observation</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
