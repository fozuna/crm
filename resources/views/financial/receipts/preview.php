<?php
use App\Core\UI;
use App\Core\View;

$receivable = is_array($receivable ?? null) ? $receivable : [];
$receipt = is_array($receipt ?? null) ? $receipt : [];
$branding = is_array($branding ?? null) ? $branding : [];

$companyName = trim((string) ($branding['company_name'] ?? 'TRAXTER')) ?: 'TRAXTER';
$logoPath = trim((string) ($branding['logo_path'] ?? ''));
$logoPreviewSrc = trim((string) ($branding['logo_preview_src'] ?? ''));
$clientName = trim((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? 'Cliente nao informado')));
$serviceDescription = trim((string) ($receivable['description'] ?? ''));
if (trim((string) ($receivable['project_title'] ?? '')) !== '') {
    $serviceDescription = 'Projeto: ' . trim((string) $receivable['project_title']) . ($serviceDescription !== '' ? ' | ' . $serviceDescription : '');
}
if ($serviceDescription === '') {
    $serviceDescription = trim((string) ($receivable['title'] ?? 'Recebimento financeiro sem descricao detalhada.'));
}

$formatDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return 'Nao informada';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('d/m/Y', $ts);
};

$netAmount = (float) (($receipt['amount_received'] ?? 0) + ($receipt['interest_amount'] ?? 0) + ($receipt['fine_amount'] ?? 0) - ($receipt['discount_amount'] ?? 0));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview do Recibo de Pagamento</title>
  <style>
    :root {
      --primary: <?= View::e((string) ($branding['primary_color'] ?? '#293241')) ?>;
      --accent: <?= View::e((string) ($branding['accent_color'] ?? '#0ea5a4')) ?>;
      --surface: #f8fafc;
      --paper: #ffffff;
      --border: #cbd5e1;
      --text: #111827;
      --muted: #475569;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; background: #e5e7eb; color: var(--text); }
    .toolbar { max-width: 980px; margin: 24px auto 0; display: flex; gap: 12px; justify-content: flex-end; padding: 0 16px; }
    .toolbar a, .toolbar button {
      border: 1px solid var(--border); background: var(--paper); color: var(--text); width: 44px; height: 44px; border-radius: 12px; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    }
    .toolbar .primary { background: var(--primary); color: #fff; border-color: var(--primary); }
    .toolbar svg { width: 18px; height: 18px; }
    .page {
      width: 210mm; min-height: 297mm; margin: 16px auto 32px; background: var(--paper); box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
      padding: 18mm 16mm 18mm;
    }
    .header {
      background: var(--primary); color: #fff; border-radius: 18px; padding: 18px 22px; position: relative; overflow: hidden;
    }
    .header::after {
      content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 6px; background: var(--accent);
    }
    .header-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
    .header-brand { max-width: 64%; }
    .header-title { font-size: 28px; font-weight: 700; letter-spacing: .04em; }
    .header-subtitle { margin-top: 8px; font-size: 14px; color: rgba(255,255,255,.82); }
    .header-meta { text-align: right; font-size: 13px; line-height: 1.7; }
    .logo-slot {
      width: 148px; height: 52px; display: flex; align-items: center; justify-content: flex-start;
      margin-bottom: 12px;
    }
    .logo {
      max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block;
    }
    .logo-fallback {
      font-size: 11px; color: rgba(255,255,255,.72); letter-spacing: .04em; text-transform: uppercase;
      border: 1px dashed rgba(255,255,255,.32); border-radius: 10px; padding: 8px 10px;
    }
    .section { margin-top: 18px; }
    .card {
      border: 1px solid var(--border); border-radius: 16px; background: var(--surface); padding: 18px;
    }
    .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .label { font-size: 11px; letter-spacing: .05em; color: var(--muted); text-transform: uppercase; font-weight: 700; }
    .value-strong { margin-top: 8px; font-size: 22px; font-weight: 700; line-height: 1.35; }
    .value-body { margin-top: 8px; font-size: 14px; line-height: 1.65; color: var(--text); white-space: pre-line; }
    .details-title { font-size: 18px; font-weight: 700; color: var(--primary); margin-bottom: 14px; }
    .details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 20px; }
    .detail-item strong { display: block; color: var(--muted); font-size: 12px; margin-bottom: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .detail-item span { font-size: 14px; line-height: 1.55; }
    .statement { border: 1px solid var(--border); border-radius: 16px; padding: 18px; background: #fff; }
    .statement p { margin: 0; font-size: 15px; line-height: 1.85; text-align: justify; }
    .footer { margin-top: 44px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; }
    .signature { padding-top: 38px; border-top: 1px solid var(--border); font-size: 13px; color: var(--muted); }
    .print-note { margin-top: 24px; font-size: 12px; color: var(--muted); }
    @media (max-width: 900px) {
      .page { width: auto; min-height: auto; margin: 8px; padding: 16px; }
      .header-top, .grid-3, .grid-2, .details-grid, .footer { grid-template-columns: 1fr; display: grid; }
      .header-meta { text-align: left; }
    }
    @media print {
      body { background: #fff; }
      .toolbar { display: none; }
      .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 14mm 12mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a href="<?= View::e($base . '/financeiro/recebiveis/' . (int) ($receivable['id'] ?? 0)) ?>" aria-label="Voltar" title="Voltar">
      <?= UI::icon('arrow-left', 'w-5 h-5') ?>
    </a>
    <a class="primary" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) ($receivable['id'] ?? 0) . '/recibos/' . (int) ($receipt['id'] ?? 0) . '/pdf') ?>" aria-label="Baixar PDF" title="Baixar PDF">
      <?= UI::icon('download', 'w-5 h-5') ?>
    </a>
    <button type="button" onclick="window.print()" aria-label="Imprimir" title="Imprimir">
      <?= UI::icon('print', 'w-5 h-5') ?>
    </button>
  </div>

  <main class="page">
    <section class="header">
      <div class="header-top">
        <div class="header-brand">
          <div class="logo-slot">
            <?php if ($logoPreviewSrc !== ''): ?>
              <img class="logo" src="<?= View::e($logoPreviewSrc) ?>" alt="Logo da empresa <?= View::e($companyName) ?>">
            <?php elseif ($logoPath !== '' && is_file($logoPath)): ?>
              <div class="logo-fallback">Logo indisponivel no preview</div>
            <?php endif; ?>
          </div>
          <div class="header-title">RECIBO DE PAGAMENTO</div>
          <div class="header-subtitle">Comprovante profissional de recebimento financeiro</div>
        </div>
        <div class="header-meta">
          <div><strong><?= View::e($companyName) ?></strong></div>
          <div>Recibo #<?= (int) ($receipt['id'] ?? 0) ?></div>
          <div>Conta #<?= (int) ($receivable['id'] ?? 0) ?></div>
          <div>Data do pagamento: <?= View::e($formatDate((string) ($receipt['payment_date'] ?? ''))) ?></div>
        </div>
      </div>
    </section>

    <section class="section card">
      <div class="label">Nome do cliente</div>
      <div class="value-strong"><?= View::e($clientName !== '' ? $clientName : 'Cliente nao informado') ?></div>
      <div class="label" style="margin-top:18px">Servico que gerou aquele recebimento</div>
      <div class="value-body"><?= View::e($serviceDescription) ?></div>
    </section>

    <section class="section grid-3">
      <div class="card">
        <div class="label">Valor recebido</div>
        <div class="value-strong" style="color:var(--accent)">R$ <?= number_format((float) ($receipt['amount_received'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="card">
        <div class="label">Juros / multa</div>
        <div class="value-strong">R$ <?= number_format((float) (($receipt['interest_amount'] ?? 0) + ($receipt['fine_amount'] ?? 0)), 2, ',', '.') ?></div>
      </div>
      <div class="card">
        <div class="label">Desconto</div>
        <div class="value-strong">R$ <?= number_format((float) ($receipt['discount_amount'] ?? 0), 2, ',', '.') ?></div>
      </div>
    </section>

    <section class="section card">
      <div class="details-title">Detalhes do pagamento</div>
      <div class="details-grid">
        <div class="detail-item"><strong>Documento</strong><span><?= View::e((string) (($receivable['invoice_number'] ?? '') !== '' ? $receivable['invoice_number'] : ('Conta #' . (int) ($receivable['id'] ?? 0)))) ?></span></div>
        <div class="detail-item"><strong>Data do pagamento</strong><span><?= View::e($formatDate((string) ($receipt['payment_date'] ?? ''))) ?></span></div>
        <div class="detail-item"><strong>Metodo</strong><span><?= View::e((string) ($receipt['payment_method'] ?? 'Nao informado')) ?></span></div>
        <div class="detail-item"><strong>Projeto</strong><span><?= View::e((string) ($receivable['project_title'] ?? '—')) ?></span></div>
        <div class="detail-item"><strong>Referencia da transacao</strong><span><?= View::e((string) ($receipt['transaction_reference'] ?? '—')) ?></span></div>
        <div class="detail-item"><strong>Competencia</strong><span><?= View::e($formatDate((string) ($receivable['competence_date'] ?? ''))) ?></span></div>
        <div class="detail-item"><strong>Banco</strong><span><?= View::e(trim((string) (($receivable['bank_name'] ?? '') . ' ' . ($receivable['account_name'] ?? ''))) ?: '—') ?></span></div>
        <div class="detail-item"><strong>Referencia interna</strong><span><?= View::e((string) ($receivable['external_reference'] ?? '—')) ?></span></div>
      </div>
    </section>

    <section class="section statement">
      <div class="details-title">Declaracao de recebimento</div>
      <p>Recebemos de <strong><?= View::e($clientName !== '' ? $clientName : 'cliente nao informado') ?></strong> a importancia de <strong>R$ <?= number_format($netAmount, 2, ',', '.') ?></strong>, referente ao servico <strong><?= View::e($serviceDescription) ?></strong>.</p>
      <?php if (trim((string) ($receipt['observation'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Observacoes:</strong> <?= View::e((string) $receipt['observation']) ?></p>
      <?php endif; ?>
    </section>

    <section class="footer">
      <div class="signature">Assinatura do cliente</div>
      <div class="signature"><?= View::e($companyName) ?></div>
    </section>

    <div class="print-note">Recibo gerado em <?= date('d/m/Y H:i') ?>. Este preview mantem o mesmo conteudo estrutural do PDF para validacao antes do download final.</div>
  </main>
</body>
</html>
