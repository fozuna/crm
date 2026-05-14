<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$legacy = new App\Repositories\FinanceRevenueRepository();
$enterprise = new App\Repositories\FinancialEnterpriseReportRepository();
$filters = ['project_id' => 55, 'from' => '2026-05-01', 'to' => '2026-11-30'];
$data = [
  'legacy_installments' => $legacy->listInstallments($filters, 1, 200),
  'enterprise_reports' => $enterprise->reports(1, $filters),
];
file_put_contents(__DIR__ . '/diag_proposal_58_reports.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "OK\n";
