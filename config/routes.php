<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BrandingController;
use App\Controllers\ClientController;
use App\Controllers\ContractController;
use App\Controllers\CompanyProfileApiController;
use App\Controllers\CompanyProfileController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\LeadApiController;
use App\Controllers\LeadController;
use App\Controllers\PaymentMethodController;
use App\Controllers\ProposalController;
use App\Controllers\ProjectController;
use App\Controllers\FinanceController;
use App\Controllers\AuditController;
use App\Controllers\ReportController;
use App\Controllers\ProjectApiController;
use App\Controllers\FinanceApiController;
use App\Controllers\FinanceRevenueApiController;
use App\Controllers\FinanceMaintenanceApiController;
use App\Controllers\DashboardFinanceApiController;
use App\Controllers\AuditApiController;
use App\Controllers\MaintenanceController;
use App\Controllers\ManualController;
use App\Controllers\ServiceController;
use App\Controllers\ServiceOrderApprovalController;
use App\Controllers\ServiceApiController;
use App\Controllers\ServiceOrderController;
use App\Controllers\FinancialModuleController;
use App\Controllers\FinancialModuleApiController;
use App\Core\Router;
use App\Middleware\RoleMiddleware;

return static function (Router $router, array $mw): void {
    $auth = $mw['auth'];
    $admin = $mw['admin'];
    $csrf = $mw['csrf'];

    $pm = new RoleMiddleware(['admin', 'pm']);
    $finance = new RoleMiddleware(['admin', 'finance']);
    $auditor = new RoleMiddleware(['admin', 'auditor', 'finance', 'pm']);
    $financeView = new RoleMiddleware(['admin', 'finance', 'auditor']);

    $router->get('/install', [new InstallController(), 'show']);
    $router->post('/install', [new InstallController(), 'store']);

    $router->get('/login', [new AuthController(), 'showLogin']);
    $router->post('/login', [new AuthController(), 'login'], [$csrf]);
    $router->post('/logout', [new AuthController(), 'logout'], [$csrf, $auth]);

    $router->get('/', [new DashboardController(), 'index'], [$auth]);
    $router->get('/dashboard', [new DashboardController(), 'index'], [$auth]);

    $router->get('/branding', [new BrandingController(), 'edit'], [$auth]);
    $router->post('/branding', [new BrandingController(), 'update'], [$auth, $csrf]);

    $router->get('/manual', [new ManualController(), 'index'], [$auth]);

    $router->get('/servicos', [new ServiceController(), 'index'], [$auth, $pm]);
    $router->get('/servicos/novo', [new ServiceController(), 'create'], [$auth, $pm]);
    $router->post('/servicos', [new ServiceController(), 'store'], [$auth, $pm, $csrf]);
    $router->get('/servicos/{id}/editar', [new ServiceController(), 'edit'], [$auth, $pm]);
    $router->post('/servicos/{id}', [new ServiceController(), 'update'], [$auth, $pm, $csrf]);

    $router->get('/ordens-servico', [new ServiceOrderController(), 'index'], [$auth, $auditor]);
    $router->get('/ordens-servico/relatorios', [new ServiceOrderController(), 'reports'], [$auth, $auditor]);
    $router->get('/ordens-servico/nova', [new ServiceOrderController(), 'create'], [$auth, $pm]);
    $router->post('/ordens-servico', [new ServiceOrderController(), 'store'], [$auth, $pm, $csrf]);
    $router->get('/ordens-servico/{id}', [new ServiceOrderController(), 'show'], [$auth, $auditor]);
    $router->get('/ordens-servico/{id}/editar', [new ServiceOrderController(), 'edit'], [$auth, $auditor]);
    $router->post('/ordens-servico/{id}', [new ServiceOrderController(), 'update'], [$auth, $pm, $csrf]);
    $router->get('/ordens-servico/{id}/faturar', [new ServiceOrderController(), 'billing'], [$auth, $auditor]);
    $router->post('/ordens-servico/{id}/faturar', [new ServiceOrderController(), 'invoice'], [$auth, $pm, $csrf]);
    $router->post('/ordens-servico/{id}/reparcelar', [new ServiceOrderController(), 'reparcel'], [$auth, $pm, $csrf]);
    $router->post('/ordens-servico/{id}/status', [new ServiceOrderController(), 'updateStatus'], [$auth, $pm, $csrf]);
    $router->post('/ordens-servico/{id}/cancelar', [new ServiceOrderController(), 'cancel'], [$auth, $pm, $csrf]);
    $router->post('/ordens-servico/{id}/excluir', [new ServiceOrderController(), 'destroy'], [$auth, $pm, $csrf]);
    $router->post('/ordens-servico/{id}/aprovacao/gerar', [new ServiceOrderApprovalController(), 'generate'], [$auth, $pm, $csrf]);
    $router->get('/ordens-servico/{id}/aprovacao/comprovante', [new ServiceOrderApprovalController(), 'proof'], [$auth, $auditor]);
    $router->get('/ordens-servico/{id}/pdf', [new ServiceOrderController(), 'pdf'], [$auth, $auditor]);
    $router->get('/ordens-servico/{id}/anexos/{attachmentId}', [new ServiceOrderController(), 'attachment'], [$auth, $auditor]);
    $router->post('/ordens-servico/{id}/anexos/{attachmentId}/excluir', [new ServiceOrderController(), 'deleteAttachment'], [$auth, $pm, $csrf]);

    $router->get('/os/aprovacao/{publicId}', [new ServiceOrderApprovalController(), 'showPublic']);
    $router->post('/os/aprovacao/{publicId}', [new ServiceOrderApprovalController(), 'submitPublic']);

    $router->get('/maintenance/db-upgrade/check', [new MaintenanceController(), 'checkDbUpgrade'], [$auth, $admin]);
    $router->post('/maintenance/db-upgrade/start', [new MaintenanceController(), 'startDbUpgrade'], [$auth, $admin, $csrf]);
    $router->get('/maintenance/db-upgrade/status/{jobId}', [new MaintenanceController(), 'dbUpgradeStatus'], [$auth, $admin]);

    $router->get('/maintenance/db-reset/plan', [new MaintenanceController(), 'dbResetPlan'], [$auth, $admin]);
    $router->post('/maintenance/db-reset/start', [new MaintenanceController(), 'startDbReset'], [$auth, $admin, $csrf]);
    $router->get('/maintenance/db-reset/status/{jobId}', [new MaintenanceController(), 'dbResetStatus'], [$auth, $admin]);

    $router->get('/empresa', [new CompanyProfileController(), 'edit'], [$auth, $admin]);
    $router->post('/empresa', [new CompanyProfileController(), 'update'], [$auth, $admin, $csrf]);
    $router->get('/empresa/auditoria', [new CompanyProfileController(), 'audit'], [$auth, $admin]);
    $router->get('/empresa/logo/{variant}', [new CompanyProfileController(), 'logo'], [$auth]);
    $router->get('/empresa/ativo/{asset}', [new CompanyProfileController(), 'asset']);

    $router->get('/api/company-profile', [new CompanyProfileApiController(), 'show'], [$auth, $admin]);
    $router->add('PUT', '/api/company-profile', [new CompanyProfileApiController(), 'upsert'], [$auth, $admin, $csrf]);
    $router->add('DELETE', '/api/company-profile', [new CompanyProfileApiController(), 'destroy'], [$auth, $admin, $csrf]);
    $router->get('/api/company-profile/audit', [new CompanyProfileApiController(), 'audit'], [$auth, $admin]);
    $router->post('/api/company-profile/logo', [new CompanyProfileApiController(), 'uploadLogo'], [$auth, $admin, $csrf]);

    $router->get('/pagamentos', [new PaymentMethodController(), 'index'], [$auth]);
    $router->get('/pagamentos/novo', [new PaymentMethodController(), 'create'], [$auth]);
    $router->post('/pagamentos', [new PaymentMethodController(), 'store'], [$auth, $csrf]);
    $router->get('/pagamentos/{id}/editar', [new PaymentMethodController(), 'edit'], [$auth]);
    $router->post('/pagamentos/{id}', [new PaymentMethodController(), 'update'], [$auth, $csrf]);
    $router->post('/pagamentos/{id}/excluir', [new PaymentMethodController(), 'destroy'], [$auth, $csrf]);

    $router->get('/clientes', [new ClientController(), 'index'], [$auth]);
    $router->get('/clientes/novo', [new ClientController(), 'create'], [$auth]);
    $router->post('/clientes', [new ClientController(), 'store'], [$auth, $csrf]);
    $router->get('/clientes/{id}', [new ClientController(), 'show'], [$auth]);
    $router->get('/clientes/{id}/logo', [new ClientController(), 'logo'], [$auth]);
    $router->get('/clientes/{id}/editar', [new ClientController(), 'edit'], [$auth]);
    $router->post('/clientes/{id}', [new ClientController(), 'update'], [$auth, $csrf]);
    $router->post('/clientes/{id}/excluir', [new ClientController(), 'destroy'], [$auth, $csrf]);
    $router->post('/clientes/{id}/interacoes', [new ClientController(), 'addInteraction'], [$auth, $csrf]);

    $router->get('/leads', [new LeadController(), 'index'], [$auth, $pm]);
    $router->get('/leads/novo', [new LeadController(), 'create'], [$auth, $pm]);
    $router->post('/leads', [new LeadController(), 'store'], [$auth, $pm, $csrf]);
    $router->get('/leads/{id}/editar', [new LeadController(), 'edit'], [$auth, $pm]);
    $router->post('/leads/{id}', [new LeadController(), 'update'], [$auth, $pm, $csrf]);
    $router->post('/leads/{id}/interacoes', [new LeadController(), 'addInteraction'], [$auth, $pm, $csrf]);

    $router->get('/propostas', [new ProposalController(), 'index'], [$auth]);
    $router->get('/propostas/nova', [new ProposalController(), 'create'], [$auth]);
    $router->post('/propostas', [new ProposalController(), 'store'], [$auth, $csrf]);
    $router->get('/propostas/{id}', [new ProposalController(), 'show'], [$auth]);
    $router->get('/propostas/{id}/editar', [new ProposalController(), 'edit'], [$auth]);
    $router->post('/propostas/{id}', [new ProposalController(), 'update'], [$auth, $csrf]);
    $router->post('/propostas/{id}/status', [new ProposalController(), 'updateStatus'], [$auth, $csrf]);
    $router->post('/propostas/{id}/converter', [new ProposalController(), 'convertToProject'], [$auth, $csrf]);
    $router->get('/propostas/{id}/preview', [new ProposalController(), 'preview'], [$auth]);
    $router->post('/propostas/{id}/pdf', [new ProposalController(), 'generatePdf'], [$auth, $csrf]);
    $router->get('/propostas/{id}/docs/{docId}', [new ProposalController(), 'doc'], [$auth]);
    $router->get('/propostas/{id}/pdf', [new ProposalController(), 'pdf'], [$auth]);
    $router->post('/propostas/{proposalId}/contrato/gerar', [new ContractController(), 'generateFromProposal'], [$auth, $pm, $csrf]);

    $router->get('/contratos', [new ContractController(), 'index'], [$auth, $auditor]);
    $router->get('/contratos/relatorios', [new ContractController(), 'reports'], [$auth, $auditor]);
    $router->get('/contratos/templates', [new ContractController(), 'templates'], [$auth, $pm]);
    $router->post('/contratos/templates/{id}', [new ContractController(), 'updateTemplate'], [$auth, $pm, $csrf]);
    $router->get('/contratos/{id}', [new ContractController(), 'show'], [$auth, $auditor]);
    $router->get('/contratos/{id}/imprimir', [new ContractController(), 'printable'], [$auth, $auditor]);
    $router->get('/contratos/{id}/pdf', [new ContractController(), 'pdf'], [$auth, $auditor]);
    $router->get('/contratos/{id}/versoes/{versionId}/pdf', [new ContractController(), 'versionPdf'], [$auth, $auditor]);
    $router->post('/contratos/{id}/assinar', [new ContractController(), 'markSigned'], [$auth, $pm, $csrf]);
    $router->post('/contratos/{id}/vigencia', [new ContractController(), 'markEffective'], [$auth, $pm, $csrf]);
    $router->post('/contratos/{id}/enviar-assinatura', [new ContractController(), 'sendForSignature'], [$auth, $pm, $csrf]);

    $router->get('/projetos', [new ProjectController(), 'index'], [$auth, $auditor]);
    $router->get('/projetos/{id}', [new ProjectController(), 'show'], [$auth, $auditor]);
    $router->get('/projetos/{id}/editar', [new ProjectController(), 'edit'], [$auth, $pm]);
    $router->post('/projetos/{id}', [new ProjectController(), 'update'], [$auth, $pm, $csrf]);
    $router->post('/projetos/{id}/tarefas', [new ProjectController(), 'addTask'], [$auth, $pm, $csrf]);
    $router->post('/projetos/{id}/tarefas/{taskId}', [new ProjectController(), 'updateTask'], [$auth, $pm, $csrf]);
    $router->post('/projetos/{id}/tarefas/{taskId}/excluir', [new ProjectController(), 'deleteTask'], [$auth, $pm, $csrf]);
    $router->post('/projetos/{id}/marcos', [new ProjectController(), 'addMilestone'], [$auth, $pm, $csrf]);
    $router->post('/projetos/{id}/marcos/{milestoneId}/excluir', [new ProjectController(), 'deleteMilestone'], [$auth, $pm, $csrf]);

    $router->get('/projetos/{id}/financeiro', [new FinanceController(), 'project'], [$auth, $finance]);
    $router->post('/projetos/{id}/financeiro/{installmentId}/pagar', [new FinanceController(), 'pay'], [$auth, $finance, $csrf]);
    $router->post('/projetos/{id}/financeiro/{installmentId}/cancelar', [new FinanceController(), 'cancel'], [$auth, $finance, $csrf]);
    $router->post('/projetos/{id}/financeiro/{installmentId}/reabrir', [new FinanceController(), 'reopen'], [$auth, $finance, $csrf]);
    $router->post('/projetos/{id}/financeiro/cancelamentos/{requestId}/aprovar', [new FinanceController(), 'approveCancel'], [$auth, $admin, $csrf]);
    $router->post('/projetos/{id}/financeiro/cancelamentos/{requestId}/rejeitar', [new FinanceController(), 'rejectCancel'], [$auth, $admin, $csrf]);

    $router->get('/auditoria', [new AuditController(), 'index'], [$auth, $auditor]);
    $router->get('/relatorios/financeiro', [new ReportController(), 'finance'], [$auth, $auditor]);
    $router->get('/relatorios/financeiro/export/pdf', [new ReportController(), 'financeExportPdf'], [$auth, $auditor]);
    $router->get('/relatorios/financeiro/export/excel', [new ReportController(), 'financeExportExcel'], [$auth, $auditor]);

    $router->get('/financeiro/dashboard', [new FinancialModuleController(), 'dashboard'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis', [new FinancialModuleController(), 'index'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/novo', [new FinancialModuleController(), 'create'], [$auth, $finance]);
    $router->post('/financeiro/recebiveis', [new FinancialModuleController(), 'store'], [$auth, $finance, $csrf]);
    $router->get('/financeiro/recebiveis/{id}', [new FinancialModuleController(), 'show'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/{id}/editar', [new FinancialModuleController(), 'edit'], [$auth, $finance]);
    $router->post('/financeiro/recebiveis/{id}', [new FinancialModuleController(), 'update'], [$auth, $finance, $csrf]);
    $router->post('/financeiro/recebiveis/{id}/excluir', [new FinancialModuleController(), 'destroy'], [$auth, $finance, $csrf]);
    $router->post('/financeiro/recebiveis/{id}/duplicar', [new FinancialModuleController(), 'duplicate'], [$auth, $finance, $csrf]);
    $router->post('/financeiro/recebiveis/{id}/renegociar', [new FinancialModuleController(), 'renegotiate'], [$auth, $finance, $csrf]);
    $router->post('/financeiro/recebiveis/{id}/baixa', [new FinancialModuleController(), 'receipt'], [$auth, $finance, $csrf]);
    $router->post('/financeiro/recebiveis/{id}/estornar', [new FinancialModuleController(), 'reverse'], [$auth, $finance, $csrf]);
    $router->get('/financeiro/recebiveis/{id}/imprimir', [new FinancialModuleController(), 'printable'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/{id}/recibos/{receiptId}/preview', [new FinancialModuleController(), 'receiptPreview'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/{id}/recibos/{receiptId}/pdf', [new FinancialModuleController(), 'receiptPdf'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/{id}/pdf', [new FinancialModuleController(), 'pdf'], [$auth, $financeView]);
    $router->get('/financeiro/recebiveis/{id}/comprovantes/{receiptId}', [new FinancialModuleController(), 'receiptFile'], [$auth, $financeView]);
    $router->get('/financeiro/relatorios', [new FinancialModuleController(), 'reports'], [$auth, $financeView]);
    $router->get('/financeiro/relatorios/export/csv', [new FinancialModuleController(), 'exportCsv'], [$auth, $financeView]);
    $router->get('/financeiro/relatorios/export/excel', [new FinancialModuleController(), 'exportExcel'], [$auth, $financeView]);
    $router->get('/financeiro/relatorios/export/pdf', [new FinancialModuleController(), 'exportPdf'], [$auth, $financeView]);

    $router->get('/api/projects', [new ProjectApiController(), 'index'], [$auth, $auditor]);
    $router->get('/api/projects/{id}', [new ProjectApiController(), 'show'], [$auth, $auditor]);
    $router->post('/api/proposals/{proposalId}/convert', [new ProjectApiController(), 'convertFromProposal'], [$auth, $pm, $csrf]);
    $router->post('/api/projects/{id}/recalc', [new ProjectApiController(), 'recalcProgress'], [$auth, $pm, $csrf]);
    $router->post('/api/projects/{id}/tasks/{taskId}/status', [new ProjectApiController(), 'updateTaskStatus'], [$auth, $pm, $csrf]);

    $router->get('/api/leads/kanban', [new LeadApiController(), 'kanban'], [$auth, $pm]);
    $router->get('/api/leads/{id}', [new LeadApiController(), 'show'], [$auth, $pm]);
    $router->post('/api/leads/{id}/stage', [new LeadApiController(), 'move'], [$auth, $pm, $csrf]);
    $router->post('/api/leads/{id}/convert', [new LeadApiController(), 'convert'], [$auth, $pm, $csrf]);

    $router->get('/api/projects/{id}/installments', [new FinanceApiController(), 'listProjectInstallments'], [$auth, $finance]);
    $router->get('/api/installments/{id}/payments', [new FinanceApiController(), 'listPayments'], [$auth, $finance]);
    $router->post('/api/installments/{id}/payments', [new FinanceApiController(), 'addPayment'], [$auth, $finance, $csrf]);
    $router->post('/api/installments/{id}/cancel', [new FinanceApiController(), 'cancel'], [$auth, $finance, $csrf]);
    $router->post('/api/installments/{id}/reopen', [new FinanceApiController(), 'reopen'], [$auth, $finance, $csrf]);

    $router->get('/api/finance/revenues/metrics', [new FinanceRevenueApiController(), 'metrics'], [$auth, $financeView]);
    $router->get('/api/finance/revenues/cashflow', [new FinanceRevenueApiController(), 'cashflow'], [$auth, $financeView]);
    $router->get('/api/finance/revenues/installments', [new FinanceRevenueApiController(), 'installments'], [$auth, $financeView]);
    $router->get('/api/finance/revenues/payments', [new FinanceRevenueApiController(), 'payments'], [$auth, $financeView]);

    $router->get('/api/dashboard/finance', [new DashboardFinanceApiController(), 'show'], [$auth, $financeView]);

    $router->get('/api/financial/receivables', [new FinancialModuleApiController(), 'index'], [$auth, $financeView]);
    $router->get('/api/financial/clients/{clientId}/approved-projects', [new FinancialModuleApiController(), 'approvedProjectsByClient'], [$auth, $financeView]);
    $router->get('/api/financial/receivables/{id}', [new FinancialModuleApiController(), 'show'], [$auth, $financeView]);
    $router->post('/api/financial/receivables', [new FinancialModuleApiController(), 'store'], [$auth, $finance, $csrf]);
    $router->add('PUT', '/api/financial/receivables/{id}', [new FinancialModuleApiController(), 'update'], [$auth, $finance, $csrf]);
    $router->add('DELETE', '/api/financial/receivables/{id}', [new FinancialModuleApiController(), 'destroy'], [$auth, $finance, $csrf]);
    $router->post('/api/financial/receivables/{id}/receipt', [new FinancialModuleApiController(), 'receipt'], [$auth, $finance, $csrf]);
    $router->post('/api/financial/receivables/{id}/reverse', [new FinancialModuleApiController(), 'reverse'], [$auth, $finance, $csrf]);
    $router->get('/api/financial/dashboard', [new FinancialModuleApiController(), 'dashboard'], [$auth, $financeView]);
    $router->get('/api/financial/reports', [new FinancialModuleApiController(), 'reports'], [$auth, $financeView]);

    $router->get('/api/finance/installments/{id}', [new FinanceMaintenanceApiController(), 'show'], [$auth, $financeView]);
    $router->post('/api/finance/installments/{id}/update', [new FinanceMaintenanceApiController(), 'update'], [$auth, $financeView, $csrf]);
    $router->post('/api/finance/installments/{id}/delete', [new FinanceMaintenanceApiController(), 'delete'], [$auth, $financeView, $csrf]);
    $router->post('/api/finance/installments/{id}/pay', [new FinanceMaintenanceApiController(), 'pay'], [$auth, $financeView, $csrf]);
    $router->post('/api/finance/installments/{id}/advance', [new FinanceMaintenanceApiController(), 'advance'], [$auth, $financeView, $csrf]);

    $router->get('/api/audit', [new AuditApiController(), 'index'], [$auth, $auditor]);

    $router->get('/api/services', [new ServiceApiController(), 'index'], [$auth, $pm]);
    $router->get('/api/services/{id}', [new ServiceApiController(), 'show'], [$auth, $pm]);
};
