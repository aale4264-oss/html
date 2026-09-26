<?php

declare(strict_types=1);

function toFloat(string $value): float
{
    $value = trim($value);
    if (str_contains($value, ',')) {
        $value = str_replace(',', '.', str_replace('.', '', $value));
    }
    return (float)$value;
}

function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function percent(float $value): string
{
    return number_format($value * 100, 6, ',', '.') . '%';
}

function rateFromInput(float $rate, string $unit): array
{
    $rateDecimal = $rate / 100;

    return match ($unit) {
        'daily' => [
            'daily' => $rateDecimal,
            'monthly' => pow(1 + $rateDecimal, 30) - 1,
            'yearly' => pow(1 + $rateDecimal, 360) - 1,
        ],
        'yearly' => [
            'daily' => pow(1 + $rateDecimal, 1 / 360) - 1,
            'monthly' => pow(1 + $rateDecimal, 1 / 12) - 1,
            'yearly' => $rateDecimal,
        ],
        default => [
            'daily' => pow(1 + $rateDecimal, 1 / 30) - 1,
            'monthly' => $rateDecimal,
            'yearly' => pow(1 + $rateDecimal, 12) - 1,
        ],
    };
}

$pv = toFloat((string)($_POST['pv'] ?? ''));
$inputRate = toFloat((string)($_POST['rate'] ?? ''));
$rateUnit = (string)($_POST['rate_unit'] ?? 'monthly');
$n = (int)($_POST['term'] ?? 0);
$termUnit = (string)($_POST['term_unit'] ?? 'months');
$termLabels = ['days' => 'dias', 'months' => 'meses', 'years' => 'anos'];
$termSingular = ['days' => 'dia', 'months' => 'mês', 'years' => 'ano'];
$error = null;
$result = null;

if ($pv <= 0 || $inputRate < 0 || $n <= 0 || !in_array($rateUnit, ['daily', 'monthly', 'yearly'], true) || !in_array($termUnit, ['days', 'months', 'years'], true)) {
    $error = 'Preencha os campos com valores válidos. O valor presente e o prazo devem ser maiores que zero.';
} else {
    $rates = rateFromInput($inputRate, $rateUnit);
    $i = $rates[$termUnit === 'days' ? 'daily' : ($termUnit === 'years' ? 'yearly' : 'monthly')];
    $factor = pow(1 + $i, $n);

    // PMT = PV * (1 + i)^n * i / ((1 + i)^n - 1)
    // Para juros zero, a parcela é PV / n.
    $pmt = $i == 0.0 ? $pv / $n : $pv * $factor * $i / ($factor - 1);
    $total = $pmt * $n;

    $result = [
        'pv' => $pv,
        'pmt' => $pmt,
        'total' => $total,
        'interest' => $total - $pv,
        'n' => $n,
        'termUnit' => $termUnit,
        'rates' => $rates,
    ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultado — FinanCálculo</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="shell">
  <section class="hero compact-hero">
    <div class="eyebrow"><span class="eyebrow-dot"></span> Resultado da simulação</div>
    <h1>Veja o custo total<br>com clareza.</h1>
    <p class="hero-copy">Confira a parcela, o total de juros e as taxas equivalentes para o cenário informado.</p>
  </section>

  <?php if ($error): ?>
    <section class="panel form-panel">
      <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <a class="primary-button button-link" href="index.html">← Voltar para a calculadora</a>
    </section>
  <?php else: ?>
    <section class="workspace">
      <div class="panel result-panel has-result">
        <div class="panel-heading result-heading"><div><p class="section-kicker light">02 · Resultado</p><h2>Sua simulação</h2></div><div class="check-badge">✓</div></div>
        <div class="main-result"><span>Parcela por <?= $termSingular[$result['termUnit']] ?></span><strong><?= money($result['pmt']) ?></strong><small>valor estimado com taxa equivalente</small></div>
        <div class="result-list">
          <div><span>Valor financiado</span><strong><?= money($result['pv']) ?></strong></div>
          <div><span>Total ao final</span><strong><?= money($result['total']) ?></strong></div>
          <div><span>Total de juros</span><strong class="accent-text"><?= money($result['interest']) ?></strong></div>
          <div><span>Prazo</span><strong><?= $result['n'] . ' ' . $termLabels[$result['termUnit']] ?></strong></div>
        </div>
        <div class="conversion-box">
          <div class="conversion-title">Taxas equivalentes</div>
          <div class="conversion-grid">
            <span><small>Ao dia</small><b><?= percent($result['rates']['daily']) ?></b></span>
            <span><small>Ao mês</small><b><?= percent($result['rates']['monthly']) ?></b></span>
            <span><small>Ao ano</small><b><?= percent($result['rates']['yearly']) ?></b></span>
          </div>
        </div>
      </div>

      <div class="panel form-panel summary-panel">
        <p class="section-kicker">Resumo do cálculo</p>
        <h2>Fórmula utilizada</h2>
        <div class="formula-highlight">PMT = PV × (1 + i)<sup>n</sup> × i<br>÷ ((1 + i)<sup>n</sup> − 1)</div>
        <p class="summary-text">O prazo <strong>n</strong> foi considerado em <strong><?= $termLabels[$result['termUnit']] ?></strong> e a taxa <strong>i</strong> foi convertida para o mesmo período.</p>
        <a class="primary-button button-link" href="index.html">Nova simulação <span>→</span></a>
      </div>
    </section>
  <?php endif; ?>

  <footer><span>FinanCálculo</span><span>Estimativa matemática · Não substitui uma proposta de crédito</span></footer>
</main>
</body>
</html>
