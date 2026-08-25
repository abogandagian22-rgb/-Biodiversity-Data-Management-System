<?php
include 'db_connect.php';

// Fetch bird observations from the database
$stmt = $pdo->query("SELECT * FROM bird_observations");
$observations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Bird Observations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Bird Observations</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>
                    observation_id<br>
                    <small class="text-muted">Unique observation record ID</small>
                </th>
                <th>
                    transect_id<br>
                    <small class="text-muted">Links to transect where observation was recorded</small>
                </th>
                <th>
                    species_id<br>
                    <small class="text-muted">Links to bird species table</small>
                </th>
                <th>
                    number_of_individuals<br>
                    <small class="text-muted">Count of birds observed</small>
                </th>
                <th>
                    distance<br>
                    <small class="text-muted">Distance from observer</small>
                </th>
                <th>
                    time_observed<br>
                    <small class="text-muted">Time of sighting</small>
                </th>
                <th>
                    sex<br>
                    <small class="text-muted">Sex of individual (if identified)</small>
                </th>
                <th>
                    age<br>
                    <small class="text-muted">Age class (juvenile/adult)</small>
                </th>
                <th>
                    activity<br>
                    <small class="text-muted">Behavior observed (feeding, flying, etc.)</small>
                </th>
                <th>
                    food_species<br>
                    <small class="text-muted">Food or plant species associated</small>
                </th>
                <th>
                    remarks<br>
                    <small class="text-muted">Additional field notes</small>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($observations as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['observation_id']) ?></td>
                <td><?= htmlspecialchars($row['transect_id']) ?></td>
                <td><?= htmlspecialchars($row['species_id']) ?></td>
                <td><?= htmlspecialchars($row['number_of_individuals']) ?></td>
                <td><?= htmlspecialchars($row['distance']) ?></td>
                <td><?= htmlspecialchars($row['time_observed']) ?></td>
                <td><?= htmlspecialchars($row['sex']) ?></td>
                <td><?= htmlspecialchars($row['age']) ?></td>
                <td><?= htmlspecialchars($row['activity']) ?></td>
                <td><?= htmlspecialchars($row['food_species']) ?></td>
                <td><?= htmlspecialchars($row['remarks']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
