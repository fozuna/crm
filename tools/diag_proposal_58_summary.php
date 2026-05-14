<?php
declare(strict_types=1);
require 'c:/laragon/www/crmtraxter/gestor/app/bootstrap.php';
$pdo = App\Core\DB::pdo();
$proposalId = 58;
$proposalStmt = $pdo->prepare('SELECT id, client_id, title, status, subtotal, discount_percent, discount_amount, total, payment_method_id, payment_selected_index, payment_snapshot, payment_options, delivery_start, delivery_end, converted_project, created_at FROM proposals WHERE id = :id');
$proposalStmt->execute([':id' => $proposalId]);
$proposal = $proposalStmt->fetch();
$projectStmt = $pdo->prepare('SELECT id, proposal_id, client_id, title, status, workflow_phase, total, created_at FROM projects WHERE proposal_id = :id ORDER BY id DESC LIMIT 1');
$projectStmt->execute([':id' => $proposalId]);
$project = $projectStmt->fetch();
$itemsStmt = $pdo->prepare('SELECT id, service_id, description, qty, unit_price, total, is_bonus, catalog_price FROM proposal_items WHERE proposal_id = :id ORDER BY id ASC');
$itemsStmt->execute([':id' => $proposalId]);
$items = $itemsStmt->fetchAll();
$instStmt = $pdo->prepare('SELECT id, installment_no, amount, due_date, status, paid_amount FROM finance_installments WHERE proposal_id = :id ORDER BY installment_no ASC, id ASC');
$instStmt->execute([':id' => $proposalId]);
$installments = $instStmt->fetchAll();

$activeReceivables = [];
$deletedReceivables = [];
$enterpriseReports = [];
$legacyReportRows = [];
$legacyMetrics = [];

if ($project) {
    $projectId = (int) $project['id'];

    $activeStmt = $pdo->prepare('SELECT id, source_installment_id, installment_number, total_installments, title, original_amount, remaining_amount, due_date, competence_date, status, deleted_at FROM financial_accounts_receivable WHERE project_id = :project_id AND deleted_at IS NULL ORDER BY installment_number ASC, id ASC');
    $activeStmt->execute([':project_id' => $projectId]);
    $activeReceivables = $activeStmt->fetchAll();

    $deletedStmt = $pdo->prepare('SELECT id, source_installment_id, installment_number, total_installments, title, original_amount, remaining_amount, due_date, competence_date, status, deleted_at FROM financial_accounts_receivable WHERE project_id = :project_id AND deleted_at IS NOT NULL ORDER BY installment_number ASC, id ASC');
    $deletedStmt->execute([':project_id' => $projectId]);
    $deletedReceivables = $deletedStmt->fetchAll();

    $from = (string) ($installments[0]['due_date'] ?? date('Y-m-01'));
    $to = (string) ($installments[count($installments) - 1]['due_date'] ?? date('Y-m-t'));
    $companyId = 1;

    $enterpriseReports = (new App\Repositories\FinancialEnterpriseReportRepository())->reports($companyId, [
        'project_id' => $projectId,
        'from' => $from,
        'to' => $to,
    ]);

    $legacyRepo = new App\Repositories\FinanceRevenueRepository();
    $legacyReportRows = $legacyRepo->listInstallments([
        'project_id' => $projectId,
        'from' => $from,
        'to' => $to,
    ], 1, 50);
    $legacyMetrics = $legacyRepo->metrics([
        'project_id' => $projectId,
        'from' => $from,
        'to' => $to,
    ]);
}

$data = [
    'proposal' => $proposal,
    'items' => $items,
    'project' => $project,
    'installments' => $installments,
    'receivables' => [
        'active_count' => count($activeReceivables),
        'deleted_count' => count($deletedReceivables),
        'active' => $activeReceivables,
        'deleted' => $deletedReceivables,
    ],
    'enterprise_reports' => [
        'receivables_count' => count((array) ($enterpriseReports['receivables'] ?? [])),
        'receivables' => $enterpriseReports['receivables'] ?? [],
        'projected_cashflow' => $enterpriseReports['projected_cashflow'] ?? [],
        'project_performance' => $enterpriseReports['project_performance'] ?? [],
        'aging_list' => $enterpriseReports['aging_list'] ?? [],
    ],
    'legacy_reports' => [
        'installments_count' => count((array) ($legacyReportRows['rows'] ?? [])),
        'installments' => $legacyReportRows['rows'] ?? [],
        'metrics' => $legacyMetrics,
    ],
];
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/diag_proposal_58_summary.json', (string) $json);
echo "OK\n";
