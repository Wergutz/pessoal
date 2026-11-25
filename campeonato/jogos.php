<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

$db = db();
$filtro = $_GET['time'] ?? '';
if ($filtro !== '' && !isset($db['times'][$filtro])) $filtro = '';

$grupos = [];
foreach ($db['jogos'] as $j) {
    if ($filtro && !in_array($filtro, [$j['casa'], $j['fora']], true)) continue;
    $grupos[nome_fase($j)][] = $j;
}

// quem folga em cada rodada
$folgas = [];
foreach ($db['jogos'] as $j) {
    if ($j['fase'] !== 'grupo') continue;
    $folgas[$j['rodada']] ??= array_keys($db['times']);
    $folgas[$j['rodada']] = array_diff($folgas[$j['rodada']], [$j['casa'], $j['fora']]);
}

topo('Jogos', 'jogos');
?>
<section class="card">
  <h2>Tabela de jogos</h2>
  <div class="filtros">
    <a href="jogos.php" class="chip <?= $filtro === '' ? 'ativo' : '' ?>">Todos</a>
    <?php foreach ($db['times'] as $tid => $t): ?>
      <a href="jogos.php?time=<?= e($tid) ?>" class="chip <?= $filtro === $tid ? 'ativo' : '' ?>"><?= escudo($db, $tid, 'xs') ?> <?= e($t['nome']) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach ($grupos as $nome => $lista): ?>
  <section class="card">
    <h2><?= e($nome) ?></h2>
    <?php foreach ($lista as $j) card_jogo($db, $j, $filtro ?: null); ?>
    <?php
      $r = $lista[0]['rodada'];
      if ($r && !$filtro && !empty($folgas[$r])):
          $f = reset($folgas[$r]); ?>
      <p class="muted pequeno">Folga: <?= e(nome_time($db, $f)) ?></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if ($filtro && !$grupos): ?>
  <p class="muted">Nenhum jogo.</p>
<?php endif; ?>

<?php rodape(); ?>
