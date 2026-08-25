<?php
include 'db_connect.php';
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: bats.php?tab=species'); exit; }
$formError = '';
$options = ['Mammalia', 'Chiroptera', 'Pteropodidae'];
$stmt = $pdo->prepare("SELECT * FROM bat_species WHERE species_id = :id");
$stmt->execute([':id'=>$id]);
$record = $stmt->fetch();
if (!$record) { header('Location: bats.php?tab=species'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $record['species_code'] = trim($_POST['species_code'] ?? '');
  $record['common_name'] = trim($_POST['common_name'] ?? '');
  $record['scientific_name'] = trim($_POST['scientific_name'] ?? '');
  $record['classification'] = trim($_POST['classification'] ?? 'Mammalia');
  $record['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
  $record['denr_status'] = trim($_POST['denr_status'] ?? 'NL');

  if ($record['species_code'] === '' || $record['common_name'] === '' || $record['scientific_name'] === '') {
    $formError = 'Please fill out required fields.';
  } else {
    $u = $pdo->prepare("UPDATE bat_species SET species_code=:species_code, common_name=:common_name, scientific_name=:scientific_name, classification=:classification, iucn_status=:iucn_status, denr_status=:denr_status WHERE species_id=:id");
    $u->execute([':species_code'=>$record['species_code'], ':common_name'=>$record['common_name'], ':scientific_name'=>$record['scientific_name'], ':classification'=>$record['classification'], ':iucn_status'=>$record['iucn_status'], ':denr_status'=>$record['denr_status'], ':id'=>$id]);
    header('Location: bats.php?tab=species');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bat Species</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php $activePage = 'bats'; include 'sidebar.php'; ?>
        <main class="flex-grow-1 main-content">
            <section class="birds-card">
                <div class="birds-card-head">
                    <h2>Edit Bat Species</h2>
                </div>

                <?php if($formError !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
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
                        <select id="classification" class="form-select" name="classification">
                            <?php foreach($options as $o): ?>
                                <option value="<?= htmlspecialchars($o) ?>" <?= ($record['classification'] === $o) ? 'selected' : '' ?>><?= htmlspecialchars($o) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iucnStatus">IUCN Status</label>
                        <input id="iucnStatus" class="form-control" name="iucn_status" value="<?= htmlspecialchars($record['iucn_status']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="denrStatus">DENR Status</label>
                        <input id="denrStatus" class="form-control" name="denr_status" value="<?= htmlspecialchars($record['denr_status']) ?>">
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-light" href="bats.php?tab=species">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Species</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
