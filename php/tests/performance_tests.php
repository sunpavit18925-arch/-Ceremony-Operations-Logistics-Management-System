<?php
/** Run after run_tests.php using the same test database connection settings. */
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('DB_DATABASE=pabpla_test');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/queries.php';
require_once __DIR__ . '/../includes/equipment.php';
require_once __DIR__ . '/../database/migrate_performance.php';

$checks = 0;
function check_performance(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo "[PASS] {$message}\n";
}

// DDL runs outside the transaction; repeated migration must retain existing data.
$countBefore = (int)$conn->query('SELECT COUNT(*) FROM documents')->fetchColumn();
apply_performance_indexes($conn);
apply_performance_indexes($conn);
check_performance((int)$conn->query('SELECT COUNT(*) FROM documents')->fetchColumn() === $countBefore,
    'Index migration is repeatable and preserves documents');

$conn->beginTransaction();
try {
    check_performance(fetch_for_document_ids($conn, 'INVALID SQL MUST NOT RUN', []) === [],
        'An empty page performs no related-data query');
    $insertDoc = $conn->prepare('INSERT INTO documents (doc_name, dpt_work_start, dpt_work_end) VALUES (?, ?, ?)');
    $ids = [];
    foreach ([['Current', '2026-11-19 09:00:00', '2026-11-19 17:00:00'],
        ['Touches boundary', '2026-11-18 09:00:00', '2026-11-19 09:00:00'],
        ['Outside range', '2026-11-20 09:00:00', '2026-11-20 17:00:00']] as $doc) {
        $insertDoc->execute($doc);
        $ids[] = (int)$conn->lastInsertId();
    }
    $page = [['id' => $ids[0]], ['id' => $ids[2]], ['id' => $ids[0]]];
    $rows = fetch_for_document_ids($conn, 'SELECT id FROM documents WHERE id IN ({document_ids}) ORDER BY id', $page);
    check_performance(array_map('intval', array_column($rows, 'id')) === [$ids[0], $ids[2]],
        'Related-data queries return only page IDs and deduplicate repeated IDs');

    $conn->exec("INSERT INTO users (fname, lname, role) VALUES ('PerfDriver', 'MixedCase', 'staff_vehicle')");
    $driverId = (int)$conn->lastInsertId();
    $assignment = $conn->prepare('INSERT INTO vehicle_assignments (job_id, driver_name) VALUES (?, ?)');
    foreach ($ids as $id) $assignment->execute([$id, 'perfdriver mixedcase']);
    $overlaps = fetch_driver_overlaps($conn, $ids[0], '2026-11-19 09:00:00', '2026-11-19 17:00:00');
    $expectedQuery = $conn->prepare("SELECT d.doc_name, d.dpt_work_start, d.dpt_work_end
        FROM vehicle_assignments va JOIN documents d ON va.job_id = d.id
        WHERE va.driver_name = ? AND d.id != ? AND d.dpt_work_start <= ? AND d.dpt_work_end >= ?");
    $expectedQuery->execute(['PerfDriver MixedCase', $ids[0], '2026-11-19 17:00:00', '2026-11-19 09:00:00']);
    check_performance(($overlaps[$driverId] ?? []) === $expectedQuery->fetchAll(PDO::FETCH_ASSOC),
        'Batched overlaps preserve inclusive boundaries, exclude current job and respect SQL collation');
    check_performance(count($overlaps[$driverId] ?? []) === 1,
        'A non-overlapping job is excluded');
    check_performance(fetch_driver_overlaps($conn, $ids[0], null, null) === [],
        'Missing job dates do not produce conflicts');

    $conn->exec("INSERT INTO equipments (equipment_code, equipment_name, total_quantity, available_quantity, unit) VALUES ('PERF-EQ-001', 'Synthetic performance fixture', 2, 2, 'item')");
    $borrowing = equipment_borrow($conn, $ids[0], 'PERF-EQ-001', 1, 'performance_test');
    check_performance($borrowing['success'], 'Test borrowing succeeds');
    $return = equipment_return($conn, (int)$borrowing['borrow_id'], $ids[0], $driverId, 'head_vehicle', 'Display name');
    check_performance($return['success'], 'Return accepts a display name without writing it into an integer column');
    $returnedBy = $conn->query('SELECT returned_by FROM equipment_borrowings WHERE id = ' . (int)$borrowing['borrow_id'])->fetchColumn();
    check_performance((int)$returnedBy === $driverId, 'Returned-by stores the numeric user ID');

    $insertLog = $conn->prepare("INSERT INTO system_logs (action_type, action_detail, performed_by, created_at) VALUES ('PERF_TEST', ?, 'synthetic', ?)");
    for ($i = 0; $i < 5000; $i++) $insertLog->execute(['Synthetic log ' . $i, date('Y-m-d H:i:s', 1700000000 + $i)]);
    $plan = $conn->query('EXPLAIN SELECT sl.*, d.doc_name, d.doc_number FROM system_logs sl LEFT JOIN documents d ON sl.job_id = d.id ORDER BY sl.created_at DESC, sl.id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
    $logPlan = array_values(array_filter($plan, fn($row) => $row['table'] === 'sl'))[0];
    check_performance($logPlan['key'] === 'idx_logs_created_id' && !str_contains($logPlan['Extra'], 'filesort'),
        'Real EXPLAIN uses the timestamp index without filesort for the log page');
    echo json_encode(['log_page_explain' => $plan], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
}
echo "Performance regression checks passed: {$checks}\n";
