<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\PaymentMethodRepository;

final class PaymentMethodController
{
    public function index(Request $request): void
    {
        $methods = (new PaymentMethodRepository())->all();
        View::render('payment_methods/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'methods' => $methods,
        ]);
    }

    public function create(Request $request): void
    {
        View::render('payment_methods/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'method' => null,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request);
        if ($data === null) {
            View::render('payment_methods/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'method' => $request->allPost(),
                'error' => 'Preencha os campos obrigatórios e revise as regras de parcelamento/desconto.',
            ]);
            return;
        }

        (new PaymentMethodRepository())->create($data);
        Response::redirect($request->basePath() . '/pagamentos');
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $method = (new PaymentMethodRepository())->find($id);
        if ($method === null) {
            http_response_code(404);
            echo 'Forma de pagamento não encontrada.';
            return;
        }

        View::render('payment_methods/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'method' => $method,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = (new PaymentMethodRepository())->find($id);
        if ($existing === null) {
            http_response_code(404);
            echo 'Forma de pagamento não encontrada.';
            return;
        }

        $data = $this->validate($request);
        if ($data === null) {
            View::render('payment_methods/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'method' => array_merge($request->allPost(), ['id' => $id]),
                'error' => 'Preencha os campos obrigatórios e revise as regras de parcelamento/desconto.',
            ]);
            return;
        }

        (new PaymentMethodRepository())->update($id, $data);
        Response::redirect($request->basePath() . '/pagamentos');
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        (new PaymentMethodRepository())->delete($id);
        Response::redirect($request->basePath() . '/pagamentos');
    }

    private function validate(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        $type = trim((string) $request->input('type', ''));
        $active = (string) $request->input('active', '1');
        $discountPercent = (float) str_replace(',', '.', (string) $request->input('discount_percent', '0'));
        $installmentsCount = (int) $request->input('installments_count', 1);
        $intervalDays = (int) $request->input('interval_days', 30);
        $hasDown = (string) $request->input('has_down_payment', '0');
        $downPercent = (float) str_replace(',', '.', (string) $request->input('down_payment_percent', '0'));
        $specialTerms = trim((string) $request->input('special_terms', ''));

        if ($name === '' || !in_array($type, ['avista', 'parcelado'], true)) {
            return null;
        }

        $activeInt = ($active === '1' || $active === 'on') ? 1 : 0;
        $discountPercent = max(0.0, min(100.0, $discountPercent));

        $hasDownInt = ($hasDown === '1' || $hasDown === 'on') ? 1 : 0;
        $downPercent = max(0.0, min(100.0, $downPercent));

        if ($type === 'avista') {
            $installmentsCount = 1;
            $intervalDays = 30;
            $hasDownInt = 0;
            $downPercent = 0.0;
        }

        if ($type === 'parcelado') {
            if ($installmentsCount < 1 || $installmentsCount > 48) {
                return null;
            }
            if ($intervalDays < 1 || $intervalDays > 365) {
                return null;
            }
            if ($hasDownInt === 0) {
                $downPercent = 0.0;
            }
        }

        return [
            'name' => $name,
            'type' => $type,
            'active' => $activeInt,
            'discount_percent' => $discountPercent,
            'installments_count' => $installmentsCount,
            'interval_days' => $intervalDays,
            'has_down_payment' => $hasDownInt,
            'down_payment_percent' => $downPercent,
            'special_terms' => $specialTerms === '' ? null : $specialTerms,
        ];
    }
}

