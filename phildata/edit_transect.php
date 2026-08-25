<?php
include 'db_connect.php';
$id = (int)($_GET['id'] ?? 0);
if($id <= 0){ header('Location: transect.php'); exit; }
$formError = '';
$stmt = $pdo->prepare("SELECT * FROM bird_transects WHERE transect_id=:id");
$stmt->execute([':id'=>$id]);
$r = $stmt->fetch();
if(!$r){ header('Location: transect.php'); exit; }
if($_SERVER['REQUEST_METHOD'] === 'POST'){
 $r['transect_name'] = trim($_POST['transect_name'] ?? '');
 $r['location'] = trim($_POST['location'] ?? '');
 $r['survey_date'] = trim($_POST['survey_date'] ?? '');
 if($r['transect_name'] === '' || $r['location'] === '' || $r['survey_date'] === ''){
  $formError = 'Please fill out required fields.';
 } else {
  $u = $pdo->prepare("UPDATE bird_transects SET transect_name=:transect_name, location=:location, survey_date=:survey_date WHERE transect_id=:id");
  $u->execute([':transect_name'=>$r['transect_name'], ':location'=>$r['location'], ':survey_date'=>$r['survey_date'], ':id'=>$id]);
  header('Location: transect.php');
  exit;
 }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php $activePage = 'transects'; include 'sidebar.php'; ?>
        <main class="flex-grow-1 main-content">
            <section class="birds-card">
                <div class="birds-card-head">
                    <h2>Edit Transect</h2>
                </div>

                <?php if($formError !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
                <?php endif; ?>

                <form method="post" class="edit-form">
                    <div class="mb-3">
                        <label class="form-label" for="transectName">Transect Name</label>
                        <input id="transectName" class="form-control" name="transect_name" value="<?= htmlspecialchars($r['transect_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="location">Location</label>
                        <input id="location" class="form-control" name="location" value="<?= htmlspecialchars($r['location']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="surveyDate">Survey Date</label>
                        <input id="surveyDate" class="form-control" type="date" name="survey_date" value="<?= htmlspecialchars($r['survey_date']) ?>" required>
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-light" href="transect.php">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Transect</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
