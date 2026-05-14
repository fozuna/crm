<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Core\Request;
use App\Repositories\ServiceRepository;
use App\Services\ProposalService;

function hasTable(PDO $pdo, string $t): bool
{
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    return $st && $st->fetch(PDO::FETCH_NUM) !== false;
}

try {
    $pdo = DB::pdo();
    foreach (['services','proposal_items','proposals','payment_methods','clients'] as $t) {
        if (!hasTable($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato', 'Cliente Serviços', 'cli@teste.com', '0', NOW())");
    $clientId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO payment_methods (name, type, active, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms, created_at)
               VALUES ('PIX', 'avista', 1, 0, 1, 30, 0, 0, NULL, NOW())");
    $pmId = (int) $pdo->lastInsertId();

    $repo = new ServiceRepository();
    $svcNormal = [
        'name' => 'Serviço Normal',
        'default_price' => 500.00,
        'active' => 1,
        'description' => str_repeat('A', 55),
        'is_bonus' => 0,
    ];
    $svcBonus = [
        'name' => 'Serviço Bônus',
        'default_price' => 400.00,
        'active' => 1,
        'description' => str_repeat('B', 55),
        'is_bonus' => 1,
    ];
    $idNormal = $repo->create($svcNormal);
    $idBonus = $repo->create($svcBonus);

    if (!$repo->existsByName('Serviço Normal')) {
        throw new RuntimeException('existsByName falhou.');
    }
    if ($repo->existsByName('Serviço Normal', $idNormal)) {
        throw new RuntimeException('existsByName com exceptId falhou.');
    }

    $repo->update($idNormal, array_merge($svcNormal, ['active' => 0]));
    $active = $repo->activeList(true);
    $ids = array_map(static fn($r) => (int) ($r['id'] ?? 0), $active);
    if (in_array($idNormal, $ids, true)) {
        throw new RuntimeException('Serviço inativo apareceu na lista ativa.');
    }
    if (!in_array($idBonus, $ids, true)) {
        throw new RuntimeException('Serviço bônus ativo não apareceu na lista ativa.');
    }

    $_POST = [
        'client_id' => (string) $clientId,
        'title' => 'Proposta com bônus',
        'description' => '',
        'notes' => '',
        'terms' => '',
        'payment_option_method_id' => [(string) $pmId],
        'payment_option_label' => [''],
        'payment_option_discount_percent' => ['0'],
        'payment_option_type' => ['avista'],
        'payment_option_installments_count' => ['1'],
        'payment_option_interval_days' => ['30'],
        'payment_option_has_down_payment' => ['0'],
        'payment_option_down_payment_percent' => ['0'],
        'payment_option_special_terms' => [''],
        'payment_selected_index' => '0',
        'delivery_start' => date('Y-m-d'),
        'delivery_end' => '',
        'penalty_terms' => '',
        'item_service_id' => [(string) $idBonus, (string) $idNormal],
        'item_description' => ['', ''],
        'item_qty' => ['1', '1'],
        'item_unit_price' => ['400', '500'],
    ];

    $req = new Request();
    $svc = new ProposalService();
    $payload = $svc->validatePayload($req);
    if (!is_array($payload)) {
        throw new RuntimeException('validatePayload falhou.');
    }
    $subtotal = (float) ($payload['subtotal'] ?? -1);
    if (abs($subtotal - 500.0) > 0.001) {
        throw new RuntimeException('Subtotal deveria ignorar bônus. Obtido: ' . $subtotal);
    }

    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    if (count($items) !== 2) {
        throw new RuntimeException('Items inválidos.');
    }
    if ((int) ($items[0]['is_bonus'] ?? 0) !== 1 || (float) ($items[0]['total'] ?? 0) !== 400.0) {
        throw new RuntimeException('Item bônus não marcado corretamente.');
    }
    if ((int) ($items[1]['is_bonus'] ?? 0) !== 0 || (float) ($items[1]['total'] ?? 0) !== 500.0) {
        throw new RuntimeException('Item normal não marcado corretamente.');
    }

    $pdo->rollBack();
    echo "OK\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

