<?php
/** Add performance indexes to an existing database without resetting its data. */
function apply_performance_indexes(PDO $conn): void
{
    $indexes = [
        'documents' => [
            'idx_documents_received' => '`received_date`, `id`',
            'idx_documents_recipient_received' => '`sent_to`, `received_date`, `id`',
        ],
        'document_assignments' => [
            'idx_doc_assign_user_doc_ack' => '`user_id`, `doc_id`, `is_acknowledged`',
        ],
        'system_logs' => [
            'idx_logs_created_id' => '`created_at`, `id`',
            'idx_logs_action_created_id' => '`action_type`, `created_at`, `id`',
            'idx_logs_job_action_id' => '`job_id`, `action_type`, `id`',
        ],
        'vehicle_assignments' => [
            'idx_vassign_driver_job' => '`driver_name`, `job_id`',
        ],
    ];
    foreach ($indexes as $table => $definitions) {
        $existing = array_column($conn->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
        foreach ($definitions as $name => $columns) {
            if (!in_array($name, $existing, true)) {
                $conn->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columns})");
            }
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('CLI only');
    }
    require_once __DIR__ . '/../includes/db.php';
    apply_performance_indexes(get_db());
    echo "Performance indexes are up to date.\n";
}
