<?php
include 'db_connect.php';
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: bats.php?tab=measurements'); exit; }
$formError='';
$speciesOptions = $pdo->query("SELECT species_id, common_name FROM bat_species ORDER BY common_name")->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM bats_measurements WHERE bat_id=:id");
$stmt->execute([':id'=>$id]);
$r = $stmt->fetch();
if (!$r) { header('Location: bats.php?tab=measurements'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $r['species_id'] = (int)($_POST['species_id'] ?? 0);
  $r['sex'] = trim($_POST['sex'] ?? '');
  $r['age'] = trim($_POST['age'] ?? '');
  $r['forearm'] = (float)($_POST['forearm'] ?? 0);
  $r['hindfoot'] = (float)($_POST['hindfoot'] ?? 0);
  $r['ear'] = (float)($_POST['ear'] ?? 0);
  $r['tail'] = (float)($_POST['tail'] ?? 0);
  $r['total_length'] = (float)($_POST['total_length'] ?? 0);
  $r['weight'] = (float)($_POST['weight'] ?? 0);
  $r['net_line'] = trim($_POST['net_line'] ?? '');
  $r['remarks'] = trim($_POST['remarks'] ?? '');

  if ($r['species_id'] <= 0) {
    $formError = 'Please select species.';
  } else {
    $u = $pdo->prepare("UPDATE bats_measurements SET species_id=:species_id, sex=:sex, age=:age, forearm=:forearm, hindfoot=:hindfoot, ear=:ear, tail=:tail, total_length=:total_length, weight=:weight, net_line=:net_line, remarks=:remarks WHERE bat_id=:id");
    $u->execute([':species_id'=>$r['species_id'], ':sex'=>$r['sex'], ':age'=>$r['age'], ':forearm'=>$r['forearm'], ':hindfoot'=>$r['hindfoot'], ':ear'=>$r['ear'], ':tail'=>$r['tail'], ':total_length'=>$r['total_length'], ':weight'=>$r['weight'], ':net_line'=>$r['net_line'], ':remarks'=>$r['remarks'], ':id'=>$id]);
    header('Location: bats.php?tab=measurements');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bat Measurement</title>
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
                    <h2>Edit Bat Measurement</h2>
                </div>

                <?php if($formError !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>

                <form method="post" class="edit-form">
                    <div class="mb-3">
                        <label class="form-label" for="speciesId">Species</label>
                        <select id="speciesId" class="form-select" name="species_id" required>
                            <?php foreach($speciesOptions as $s): ?>
                                <option value="<?= (int)$s['species_id'] ?>" <?= ((int)$r['species_id'] === (int)$s['species_id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['common_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="sex">Sex</label>
                            <input id="sex" class="form-control" name="sex" value="<?= htmlspecialchars($r['sex']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="age">Age</label>
                            <input id="age" class="form-control" name="age" value="<?= htmlspecialchars($r['age']) ?>">
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col">
                            <label class="form-label" for="forearm">Forearm</label>
                            <input id="forearm" class="form-control" type="number" step="0.1" name="forearm" value="<?= htmlspecialchars((string)$r['forearm']) ?>">
                        </div>
                        <div class="col">
                            <label class="form-label" for="hindfoot">Hindfoot</label>
                            <input id="hindfoot" class="form-control" type="number" step="0.1" name="hindfoot" value="<?= htmlspecialchars((string)$r['hindfoot']) ?>">
                        </div>
                        <div class="col">
                            <label class="form-label" for="ear">Ear</label>
                            <input id="ear" class="form-control" type="number" step="0.1" name="ear" value="<?= htmlspecialchars((string)$r['ear']) ?>">
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col">
                            <label class="form-label" for="tail">Tail</label>
                            <input id="tail" class="form-control" type="number" step="0.1" name="tail" value="<?= htmlspecialchars((string)$r['tail']) ?>">
                        </div>
                        <div class="col">
                            <label class="form-label" for="totalLength">Total Length</label>
                            <input id="totalLength" class="form-control" type="number" step="0.1" name="total_length" value="<?= htmlspecialchars((string)$r['total_length']) ?>">
                        </div>
                        <div class="col">
                            <label class="form-label" for="weight">Weight</label>
                            <input id="weight" class="form-control" type="number" step="0.1" name="weight" value="<?= htmlspecialchars((string)$r['weight']) ?>">
                        </div>
                    </div>

                    <div class="mb-3 mt-2">
                        <label class="form-label" for="netLine">Net Line</label>
                        <input id="netLine" class="form-control" name="net_line" value="<?= htmlspecialchars($r['net_line']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" class="form-control" rows="3" name="remarks"><?= htmlspecialchars($r['remarks']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-light" href="bats.php?tab=measurements">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Measurement</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
