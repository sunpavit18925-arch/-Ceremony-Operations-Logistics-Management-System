<?php
/** Fetch related rows only for the documents on the current page. */
function fetch_for_document_ids(PDO $conn, string $sql, array $documents): array
{
    if (!$documents) return [];
    $ids = array_values(array_unique(array_map('intval', array_column($documents, 'id'))));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(str_replace('{document_ids}', $placeholders, $sql));
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Match names in SQL so existing database collation rules remain unchanged. */
function fetch_driver_overlaps(PDO $conn, int $jobId, ?string $start, ?string $end): array
{
    if (!$start || !$end) return [];
    $stmt = $conn->prepare("SELECT u.id AS driver_id, d.doc_name, d.dpt_work_start, d.dpt_work_end
        FROM vehicle_assignments va
        JOIN documents d ON va.job_id = d.id
        JOIN users u ON va.driver_name = CONCAT(u.fname, ' ', u.lname)
        WHERE u.role IN ('head_vehicle', 'staff_vehicle') AND d.id != ?
          AND d.dpt_work_start <= ? AND d.dpt_work_end >= ?");
    $stmt->execute([$jobId, $end, $start]);
    $byDriver = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['driver_id'];
        unset($row['driver_id']);
        $byDriver[$id][] = $row;
    }
    return $byDriver;
}
