<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = App\Core\DB::pdo();
$proposalId = 58;

$queries = [
    'proposal' => 'SELECT p.*, c.name AS client_name, c.company AS client_company
                   FROM proposals p
                   JOIN clients c ON c.id = p.client_id
                   WHERE p.id = :proposal_id',
    'items' => 'SELECT *
                FROM proposal_items
                WHERE proposal_id = :proposal_id
                ORDER BY id ASC',
    'milestones' => 'SELECT *
                     FROM proposal_milestones
                     WHERE proposal_id = :proposal_id
                     ORDER BY id ASC',
    'project' => 'SELECT *
                  FROM projects
                  WHERE proposal_id = :proposal_id
                  ORDER BY id DESC',
    'installments' => 'SELECT *
                       FROM finance_installments
                       WHERE proposal_id = :proposal_id
                       ORDER BY installment_no ASC, id ASC',
    'receivables' => 'SELECT far.*
                      FROM financial_accounts_receivable far
                      INNER JOIN projects pr ON pr.id = far.project_id
                      WHERE pr.proposal_id = :proposal_id
                      ORDER BY far.installment_number ASC, far.id ASC',
    'payments' => 'SELECT fp.*
                   FROM finance_payments fp
                   INNER JOIN finance_installments fi ON fi.id = fp.installment_id
                   WHERE fi.proposal_id = :proposal_id
                   ORDER BY fp.id ASC',
    'receipts' => 'SELECT fr.*
                   FROM financial_receipts fr
                   INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                   INNER JOIN projects pr ON pr.id = far.project_id
                   WHERE pr.proposal_id = :proposal_id
                   ORDER BY fr.id ASC',
];

foreach ($queries as $label => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    echo '===== ' . $label . " =====\n";
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
