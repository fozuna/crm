<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

final class CompanyContext
{
    public static function currentCompanyId(): int
    {
        $id = (int) Session::get('company_id', 1);
        return $id > 0 ? $id : 1;
    }
}
