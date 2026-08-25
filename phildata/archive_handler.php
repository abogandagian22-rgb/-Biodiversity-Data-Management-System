<?php
include 'db_connect.php';

function logAuditAction(PDO $pdo, string $actionType, string $tableName, int $recordId): void
{
    try {
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
            'record_id' => (string) $recordId,
            'user_id' => $userId,
            'action_time' => date('Y-m-d H:i:s'),
        ];

        $insertColumns = [];
        $insertParams = [];

        foreach ($insertValues as $column => $value) {
            if (isset($columnsMap[$column])) {
                $insertColumns[] = $column;
                $insertParams[':' . $column] = $value;
            }
        }

        if (count($insertColumns) > 0) {
            $sql = 'INSERT INTO audit_logs (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertParams);
        }
    } catch (PDOException $e) {
        // Skip audit write when audit_logs table/columns are unavailable.
    }
}

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (!$type || $id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameters');
}

try {
    switch ($type) {
        case 'bird_species':
            // Get the record
            $stmt = $pdo->prepare('SELECT * FROM bird_species WHERE species_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                // Try to insert into archive table or create archive_bird_species if it doesn't exist
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bird_species SELECT * FROM bird_species WHERE species_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    // Table might not exist, create it
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_bird_species LIKE bird_species');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bird_species SELECT * FROM bird_species WHERE species_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                // Delete from original table
                $deleteStmt = $pdo->prepare('DELETE FROM bird_species WHERE species_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'bird_species', $id);
            }
            header('Location: archive.php?tab=birds&archived=1');
            break;

        case 'bird_observation':
            $stmt = $pdo->prepare('SELECT * FROM bird_observations WHERE observation_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bird_observations SELECT * FROM bird_observations WHERE observation_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_bird_observations LIKE bird_observations');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bird_observations SELECT * FROM bird_observations WHERE observation_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM bird_observations WHERE observation_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'bird_observations', $id);
            }
            header('Location: archive.php?tab=birds&archived=1');
            break;

        case 'bat_species':
            $stmt = $pdo->prepare('SELECT * FROM bat_species WHERE species_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bat_species SELECT * FROM bat_species WHERE species_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_bat_species LIKE bat_species');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bat_species SELECT * FROM bat_species WHERE species_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM bat_species WHERE species_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'bat_species', $id);
            }
            header('Location: archive.php?tab=bats&archived=1');
            break;

        case 'bat_measurement':
            $stmt = $pdo->prepare('SELECT * FROM bats_measurements WHERE bat_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bats_measurements SELECT * FROM bats_measurements WHERE bat_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_bats_measurements LIKE bats_measurements');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_bats_measurements SELECT * FROM bats_measurements WHERE bat_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM bats_measurements WHERE bat_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'bats_measurements', $id);
            }
            header('Location: archive.php?tab=bats&archived=1');
            break;

        case 'flora':
            $stmt = $pdo->prepare('SELECT * FROM flora_tawi WHERE record_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_flora SELECT * FROM flora_tawi WHERE record_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_flora LIKE flora_tawi');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_flora SELECT * FROM flora_tawi WHERE record_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM flora_tawi WHERE record_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'flora_tawi', $id);
            }
            header('Location: archive.php?tab=flora&archived=1');
            break;

        case 'transect':
            $stmt = $pdo->prepare('SELECT * FROM bird_transects WHERE transect_id = :id');
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if ($record) {
                try {
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_transects SELECT * FROM bird_transects WHERE transect_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                } catch (PDOException $e) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS archive_transects LIKE bird_transects');
                    $archiveStmt = $pdo->prepare('INSERT INTO archive_transects SELECT * FROM bird_transects WHERE transect_id = :id');
                    $archiveStmt->execute([':id' => $id]);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM bird_transects WHERE transect_id = :id');
                $deleteStmt->execute([':id' => $id]);

                logAuditAction($pdo, 'ARCHIVE', 'bird_transects', $id);
            }
            header('Location: archive.php?tab=transect&archived=1');
            break;

        default:
            header('HTTP/1.1 400 Bad Request');
            exit('Unknown type');
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error archiving record: ' . htmlspecialchars($e->getMessage()));
}
