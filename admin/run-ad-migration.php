<?php
/**
 * Ad System Database Migration
 * Runs the SQL migration to create all required tables
 */

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';

if (!getCurrentUser() || getCurrentUser()['role'] !== 'admin') {
    die('Unauthorized. Admin access required.');
}

try {
    global $pdo;
    
    // Read SQL migration file
    $sqlFile = __DIR__ . '/../database/migrations/create_ad_system_tables.sql';
    if (!file_exists($sqlFile)) {
        die('Migration file not found: ' . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($stmt) => !empty($stmt) && !str_starts_with(trim($stmt), '--')
    );
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Ad system database tables created successfully!',
        'statements_executed' => $executed,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
