<?php
declare(strict_types=1);
require 'c:/laragon/www/crmtraxter/gestor/app/bootstrap.php';
$pdo = App\Core\DB::pdo();
$sql = "SELECT far.project_id, pr.proposal_id, COUNT(*) AS receivable_count, COUNT(DISTINCT far.source_installment_id) AS source_count, SUM(CASE WHEN far.deleted_at IS NULL THEN 1 ELSE 0 END) AS active_count
FROM financial_accounts_receivable far
INNER JOIN projects pr ON pr.id = far.project_id
WHERE far.source_installment_id IS NOT NULL
GROUP BY far.project_id, pr.proposal_id
HAVING COUNT(*) <> COUNT(DISTINCT far.source_installment_id)
ORDER BY pr.proposal_id ASC";
$rows = $pdo->query($sql)->fetchAll();
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
