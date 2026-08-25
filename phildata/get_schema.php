<?php
include 'db_connect.php';

$tables = ['bird_species', 'bat_species', 'flora_tawi'];

echo "<pre>";
foreach ($tables as $table) {
    echo "=== $table ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        foreach ($columns as $col) {
            echo $col['Field'] . " (" . $col['Type'] . ")" . ($col['Null'] === 'NO' ? ' NOT NULL' : ' NULL') . ($col['Key'] === 'PRI' ? ' PRIMARY KEY' : '') . "\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
echo "</pre>";
?>
