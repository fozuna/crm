<?php
use App\Core\UI;
use App\Core\View;

$receivable = is_array($receivable ?? null) ? $receivable : [];
$receipts = is_array($receipts ?? null) ? $receipts : [];
$client = trim((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '—')));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Impressao da Conta a Receber</title>
  <style>
    body{font-family:Arial,sans-serif;color:#0f172a;margin:32px}
    h1,h2{margin:0 0 12px}
    .muted{color:#475569}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0}
    .card{border:1px solid #cbd5e1;border-radius:12px;padding:16px}
    table{width:100%;border-collapse:collapse;margin-top:20px}
    th,td{border:1px solid #cbd5e1;padding:10px;text-align:left;font-size:12px}
    th{background:#f8fafc}
    .icon-print{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#0f172a;cursor:pointer}
    .icon-print svg{width:18px;height:18px}
    @media print{.no-print{display:none} body{margin:0;padding:16px}}
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:16px">
    <button class="icon-print" onclick="window.print()" aria-label="Imprimir" title="Imprimir">
      <?= UI::icon('print', 'w-5 h-5') ?>
    </button>
  </div>

  <h1>Conta a Receber #<?= (int) ($receivable['id'] ?? 0) ?></h1>
  <div class="muted">Cliente: <?= View::e($client) ?> | Projeto: <?= View::e((string) ($receivable['project_title'] ?? '—')) ?></div>

  <div class="grid">
    <div class="card">
      <h2>Resumo</h2>
      <div><strong>Titulo:</strong> <?= View::e((string) ($receivable['title'] ?? '')) ?></div>
      <div><strong>Status:</strong> <?= View::e((string) ($receivable['status'] ?? '')) ?></div>
      <div><strong>Vencimento:</strong> <?= View::e((string) ($receivable['due_date'] ?? '')) ?></div>
      <div><strong>Competencia:</strong> <?= View::e((string) ($receivable['competence_date'] ?? '')) ?></div>
    </div>
    <div class="card">
      <h2>Valores</h2>
      <div><strong>Original:</strong> R$ <?= number_format((float) ($receivable['original_amount'] ?? 0), 2, ',', '.') ?></div>
      <div><strong>Recebido:</strong> R$ <?= number_format((float) ($receivable['received_amount'] ?? 0), 2, ',', '.') ?></div>
      <div><strong>Saldo:</strong> R$ <?= number_format((float) ($receivable['remaining_amount'] ?? 0), 2, ',', '.') ?></div>
      <div><strong>Parcela:</strong> <?= (int) ($receivable['installment_number'] ?? 1) ?>/<?= (int) ($receivable['total_installments'] ?? 1) ?></div>
    </div>
  </div>

  <div class="card">
    <h2>Observacoes</h2>
    <div><?= nl2br(View::e((string) ($receivable['notes'] ?? '—'))) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Data</th>
        <th>Valor</th>
        <th>Juros</th>
        <th>Multa</th>
        <th>Desconto</th>
        <th>Metodo</th>
        <th>Situacao</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($receipts as $row): ?>
        <tr>
          <td><?= View::e((string) ($row['payment_date'] ?? '')) ?></td>
          <td>R$ <?= number_format((float) ($row['amount_received'] ?? 0), 2, ',', '.') ?></td>
          <td>R$ <?= number_format((float) ($row['interest_amount'] ?? 0), 2, ',', '.') ?></td>
          <td>R$ <?= number_format((float) ($row['fine_amount'] ?? 0), 2, ',', '.') ?></td>
          <td>R$ <?= number_format((float) ($row['discount_amount'] ?? 0), 2, ',', '.') ?></td>
          <td><?= View::e((string) ($row['payment_method'] ?? '—')) ?></td>
          <td><?= ($row['reversed_at'] ?? null) !== null ? 'Estornado' : 'Ativo' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($receipts) === 0): ?>
        <tr><td colspan="7">Nenhum recebimento registrado.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
