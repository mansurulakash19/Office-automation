<?php
// includes/db.php — connects to MySQL and gives us 5 small helper functions.
//
// Every query uses PREPARED STATEMENTS: the SQL has "?" placeholders and the values
// are passed separately. This is what protects the system from SQL injection.
//
//   db_all($sql, $params)    -> many rows   (array of arrays)
//   db_one($sql, $params)    -> one row     (array or null)
//   db_value($sql, $params)  -> first column of first row (a single value)
//   db_exec($sql, $params)   -> runs UPDATE/DELETE, returns number of changed rows
//   db_insert($sql, $params) -> runs INSERT, returns the new row's id

require_once __DIR__ . '/../config/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // errors become exceptions

function db(): mysqli {
    static $conn = null;               // keep one connection for the whole request
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+06:00'");   // Bangladesh time
        } catch (mysqli_sql_exception $ex) {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Database connection failed: " . $ex->getMessage() . "\n");
                exit(1);
            }
            http_response_code(500);
            echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:520px;margin:12vh auto;padding:0 20px">'
               . '<h2>Database not ready</h2><p>A2Z Central could not connect to the database.</p>'
               . '<p>1. Make sure <b>MySQL</b> is running in XAMPP.<br>2. Open <a href="setup.php">setup.php</a> to install the database.<br>'
               . '3. Check the settings in <code>config/config.php</code>.</p>'
               . '<p style="color:#888;font-size:13px">Details: ' . htmlspecialchars($ex->getMessage()) . '</p></body>';
            exit;
        }
    }
    return $conn;
}

// Runs a prepared statement. Types are detected automatically from the PHP values.
function db_run(string $sql, array $params = []): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if (count($params) > 0) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) { $types .= 'i'; }
            elseif (is_float($p)) { $types .= 'd'; }
            else { $types .= 's'; }
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array {
    $stmt = db_run($sql, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_one(string $sql, array $params = []): ?array {
    $rows = db_all($sql, $params);
    return count($rows) > 0 ? $rows[0] : null;
}

function db_value(string $sql, array $params = []) {
    $row = db_one($sql, $params);
    if ($row === null) { return null; }
    return array_values($row)[0];
}

function db_exec(string $sql, array $params = []): int {
    $stmt = db_run($sql, $params);
    $changed = $stmt->affected_rows;
    $stmt->close();
    return $changed;
}

function db_insert(string $sql, array $params = []): int {
    $stmt = db_run($sql, $params);
    $id = $stmt->insert_id;
    $stmt->close();
    return (int)$id;
}
