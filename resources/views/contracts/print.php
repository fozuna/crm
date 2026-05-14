<?php
use App\Core\View;

$title = (string) ($contract['title'] ?? 'Contrato');
$body = trim((string) ($contract['rendered_body'] ?? ''));
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= View::e((string) ($contract['contract_number'] ?? 'Contrato')) ?></title>
  <style>
    body { font-family: Arial, sans-serif; color:#1e293b; margin:40px; line-height:1.7; }
    .header { border-bottom: 4px solid #ee6c4d; padding-bottom: 16px; margin-bottom: 28px; }
    .title { font-size: 28px; font-weight: 700; color:#293241; }
    .meta { margin-top: 8px; color:#475569; font-size:14px; }
    .content { white-space: pre-line; font-size: 14px; }
    .actions { margin-bottom: 24px; }
    @media print { .actions { display:none; } body { margin: 18mm; } }
  </style>
</head>
<body>
  <div class="actions">
    <button onclick="window.print()">Imprimir</button>
  </div>
  <div class="header">
    <div class="title"><?= View::e($title) ?></div>
    <div class="meta"><?= View::e((string) ($contract['contract_number'] ?? '')) ?> · Proposta #<?= (int) ($contract['proposal_id'] ?? 0) ?></div>
  </div>
  <div class="content"><?= nl2br(View::e($body)) ?></div>
</body>
</html>
