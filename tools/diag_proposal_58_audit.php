<?php
declare(strict_types=1);
require 'c:/laragon/www/crmtraxter/gestor/app/bootstrap.php';
$pdo = App\Core\DB::pdo();
$proposalId = 58;
$queries = [
'proposal_header' => 'SELECT p.id, p.client_id, c.name AS client_name, c.company, p.title, p.status, p.subtotal, p.discount_percent, p.discount_amount, p.total, p.payment_method_id, pm.name AS payment_method_name, pm.type AS payment_method_type, pm.has_down_payment, pm.down_payment_percent, pm.installments_count, pm.interval_days, p.payment_selected_index, p.delivery_start, p.delivery_end, p.created_at FROM proposals p JOIN clients c ON c.id = p.client_id LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.id = :proposal_id',
'items' => 'SELECT id, service_id, description, qty, unit_price, total, is_bonus, catalog_price FROM proposal_items WHERE proposal_id = :proposal_id ORDER BY id ASC',
'milestones' => 'SELECT id, title, due_date, notes FROM proposal_milestones WHERE proposal_id = :proposal_id ORDER BY id ASC'
];
foreach ($queries as $label => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
    $stmt->execute();
    echo '===== ' . $label . " =====\n";
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
