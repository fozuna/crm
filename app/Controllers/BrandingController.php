<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\ProposalBrandingRepository;

final class BrandingController
{
    public function edit(Request $request): void
    {
        $branding = (new ProposalBrandingRepository())->get();
        View::render('branding/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'branding' => $branding,
            'migrated' => true,
        ]);
    }

    public function update(Request $request): void
    {
        Response::redirect($request->basePath() . '/empresa');
    }
}
