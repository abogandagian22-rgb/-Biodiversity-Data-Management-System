<?php
include 'db_connect.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: flora.php'); exit; }
$formError = '';
$stmt = $pdo->prepare("SELECT * FROM flora_tawi WHERE record_id=:id");
$stmt->execute([':id'=>$id]);
$r = $stmt->fetch();
if (!$r) { header('Location: flora.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $r['local_name'] = trim($_POST['local_name'] ?? '');
 $r['scientific_name'] = trim($_POST['scientific_name'] ?? '');
 $r['family_name'] = trim($_POST['family_name'] ?? '');
 $r['iucn_status'] = trim($_POST['iucn_status'] ?? 'LC');
 $r['denr_status'] = trim($_POST['denr_status'] ?? 'NL');
 $r['remarks'] = trim($_POST['remarks'] ?? '');
 if($r['local_name'] === '' || $r['scientific_name'] === '' || $r['family_name'] === ''){
  $formError = 'Please fill out required fields.';
 } else {
  $u = $pdo->prepare("UPDATE flora_tawi SET local_name=:local_name, scientific_name=:scientific_name, family_name=:family_name, iucn_status=:iucn_status, denr_status=:denr_status, remarks=:remarks WHERE record_id=:id");
  $u->execute([':local_name'=>$r['local_name'], ':scientific_name'=>$r['scientific_name'], ':family_name'=>$r['family_name'], ':iucn_status'=>$r['iucn_status'], ':denr_status'=>$r['denr_status'], ':remarks'=>$r['remarks'], ':id'=>$id]);
  header('Location: flora.php');
  exit;
 }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Flora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php $activePage = 'flora'; include 'sidebar.php'; ?>
        <main class="flex-grow-1 main-content">
            <section class="birds-card">
                <div class="birds-card-head">
                    <h2>Edit Flora Record</h2>
                </div>

                <?php if($formError !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>

                <form method="post" class="edit-form">
                    <div class="mb-3">
                        <label class="form-label" for="localName">Local Name</label>
                        <input id="localName" class="form-control" name="local_name" value="<?= htmlspecialchars($r['local_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="scientificName">Scientific Name</label>
                        <input id="scientificName" class="form-control" name="scientific_name" value="<?= htmlspecialchars($r['scientific_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="familyName">Family Name</label>
                        <input id="familyName" class="form-control" name="family_name" value="<?= htmlspecialchars($r['family_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iucnStatus">IUCN Status</label>
                        <input id="iucnStatus" class="form-control" name="iucn_status" value="<?= htmlspecialchars($r['iucn_status']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="denrStatus">DENR Status</label>
                        <input id="denrStatus" class="form-control" name="denr_status" value="<?= htmlspecialchars($r['denr_status']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" class="form-control" rows="3" name="remarks"><?= htmlspecialchars($r['remarks']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-light" href="flora.php">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Flora</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
