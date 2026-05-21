<?php
/**
 * Database Migration Runner
 * Executes the ad system schema migration
 * Access: /admin/execute-db-migration.php (admin only)
 */

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';

// Verify admin access
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Unauthorized. Admin access required.');
}

header('Content-Type: application/json');

try {
    global $pdo;
    
    // Read migration SQL file
    $sqlFile = __DIR__ . '/../database/migrations/RequiredDatabase-db-fix.sql';
    
    if (!file_exists($sqlFile)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Migration file not found: ' . $sqlFile
        ]);
        exit;
    }
    
    $sqlContent = file_get_contents($sqlFile);
    
    // Split statements by semicolon
    $statements = array_filter(
        array_map('trim', explode(";\n", $sqlContent)),
        fn($stmt) => !empty($stmt) && !str_starts_with(trim($stmt), '--')
    );
    
    $results = [
        'total_statements' => count($statements),
        'executed' => 0,
        'skipped' => 0,
        'errors' => [],
        'tables_created' => [],
        'start_time' => date('Y-m-d H:i:s'),
    ];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        if (empty($statement)) {
            $results['skipped']++;
            continue;
        }
        
        try {
            $pdo->exec($statement . ';');
            $results['executed']++;
            
            // Track created tables
            if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $statement, $matches)) {
                $results['tables_created'][] = $matches[1];
            }
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            
            // Ignore certain non-critical errors
            if (
                strpos($errorMsg, 'already exists') !== false ||
                strpos($errorMsg, 'Duplicate key name') !== false ||
                strpos($errorMsg, 'UNIQUE constraint failed') !== false
            ) {
                $results['skipped']++;
            } else {
                $results['errors'][] = [
                    'statement' => substr($statement, 0, 150) . '...',
                    'error' => $errorMsg,
                    'code' => $e->getCode()
                ];
            }
        }
    }
    
    $results['end_time'] = date('Y-m-d H:i:s');
    $results['success'] = count($results['errors']) === 0;
    
    // Log migration result
    error_log(json_encode([
        'event' => 'database_migration_executed',
        'admin_id' => $user['id'] ?? $user['user_id'] ?? 'unknown',
        'results' => $results
    ]));
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
