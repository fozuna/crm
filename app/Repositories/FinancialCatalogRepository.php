<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinancialCatalogRepository
{
    public function categories(int $companyId): array
    {
        $stmt = DB::pdo()->prepare('SELECT id, name, color, active FROM financial_categories WHERE company_id = :company_id ORDER BY active DESC, name ASC');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function costCenters(int $companyId): array
    {
        $stmt = DB::pdo()->prepare('SELECT id, name, code, active FROM financial_cost_centers WHERE company_id = :company_id ORDER BY active DESC, name ASC');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function bankAccounts(int $companyId): array
    {
        $stmt = DB::pdo()->prepare('SELECT id, bank_name, account_name, branch_number, account_number, pix_key, active FROM financial_bank_accounts WHERE company_id = :company_id ORDER BY active DESC, account_name ASC');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
