<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

$db = db();
$tid = $_GET['id'] ?? '';
if (!isset($db['times'][$tid])) {
    redirect('times.php');
}
$t = $db['times'][$tid];
$cl = classificacao($db);
$pos = 0;
$linha = null;
foreach ($cl as $i => $l) {
    if ($l['id'] === $tid) { $pos = $i + 1; $linha = $l; }
}
$jogos = array_filter($db['jogos'], fn($j) => in_array($tid, [$j['casa'], $j['fora']], true));

// gols por jogador
$gols = [];
foreach ($db['jogos'] as $j) {
    if (!$j['encerrado']) continue;
    foreach ($j['gols'] as $pid => $q) $gols[$pid] = ($gols[$pid] ?? 0) + (int)$q;
}
$jogadores = $t['jogadores'];
usort($jogadores, fn($a, $b) => [(int)($a['numero'] ?: 999), $a['nome']] <=> [(int)($b['numero'] ?: 999), $b['nome']]);

topo($t['nome'], 'times');
?>
<section class="hero-time" style="--c1:<?= e($t['cor1']) ?>;--c2:<?= e($t['cor2']) ?>">
  <?= escudo($db, $tid, 'xl') ?>
  <div>
    <h1><?= e($t['nome']) ?></h1>
    <p><?= $pos ?>º lugar · <?= $linha['P'] ?> pts · <?= $linha['V'] ?>V <?= $linha['E'] ?>E <?= $linha['D'] ?>D · SG <?= $linha['SG'] ?></p>
  </div>
</section>

<div class="grade-2">
  <section class="card">
    <h2>Jogos da equipe <a class="btn btn-sm btn-sec imprimir-btn" href="imprimir.php?time=<?= e($tid) ?>" target="_blank">🖨️ Imprimir</a></h2>
    <?php foreach ($jogos as $j) card_jogo($db, $j, $tid); ?>
    <?php if (!$jogos): ?><p class="muted">Nenhum jogo.</p><?php endif; ?>
  </section>

  <section class="card">
    <h2>Elenco</h2>
    <?php if (!$jogadores): ?>
      <p class="muted">Elenco ainda não cadastrado.</p>
    <?php else: ?>
      <table class="tabela compacta">
        <thead><tr><th>Nº</th><th class="esq">Jogador</th><th class="esq">Posição</th><th>Gols</th></tr></thead>
        <tbody>
        <?php foreach ($jogadores as $p): ?>
          <tr>
            <td><span class="camisa" style="background:<?= e($t['cor1']) ?>;color:<?= e($t['cor2']) ?>"><?= e($p['numero'] ?? '') ?></span></td>
            <td class="esq"><?= e($p['nome']) ?></td>
            <td class="esq muted"><?= e($p['posicao'] ?? '') ?></td>
            <td><?= $gols[$p['id']] ?? 0 ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
<?php rodape(); ?>
