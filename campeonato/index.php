<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

$db = db();
$cl = classificacao($db);
$art = artilharia($db);
$semi = $final = null;
$proximos = [];
foreach ($db['jogos'] as $j) {
    if ($j['fase'] === 'semi') $semi = $j;
    if ($j['fase'] === 'final') $final = $j;
    if (!$j['encerrado'] && $j['fase'] === 'grupo') $proximos[] = $j;
}
$camp = campeao($db);

topo('Classificação', 'index');
?>

<?php if ($camp): ?>
  <section class="campeao" style="--c1:<?= e($db['times'][$camp]['cor1']) ?>;--c2:<?= e($db['times'][$camp]['cor2']) ?>">
    <?= escudo($db, $camp, 'lg') ?>
    <div><small>🏆 CAMPEÃO</small><h1><?= e($db['times'][$camp]['nome']) ?></h1></div>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Classificação <a class="btn btn-sm btn-sec imprimir-btn" href="imprimir.php" target="_blank">🖨️ Imprimir</a></h2>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead>
      <tr><th>#</th><th class="esq">Equipe</th><th title="Pontos">P</th><th title="Jogos">J</th><th title="Vitórias">V</th><th title="Empates">E</th><th title="Derrotas">D</th><th title="Gols pró">GP</th><th title="Gols contra">GC</th><th title="Saldo de gols">SG</th><th class="ocultar-mob">Últimos</th></tr>
    </thead>
    <tbody>
    <?php foreach ($cl as $i => $l): $t = $db['times'][$l['id']]; ?>
      <tr class="pos-<?= $i + 1 ?>">
        <td><span class="pos"><?= $i + 1 ?></span></td>
        <td class="esq"><a class="time-link" href="time.php?id=<?= e($l['id']) ?>"><?= escudo($db, $l['id'], 'sm') ?> <?= e($t['nome']) ?></a></td>
        <td><strong><?= $l['P'] ?></strong></td>
        <td><?= $l['J'] ?></td><td><?= $l['V'] ?></td><td><?= $l['E'] ?></td><td><?= $l['D'] ?></td>
        <td><?= $l['GP'] ?></td><td><?= $l['GC'] ?></td><td><?= $l['SG'] > 0 ? '+' : '' ?><?= $l['SG'] ?></td>
        <td class="ocultar-mob"><?php foreach (array_slice($l['forma'], -3) as $f): ?><span class="forma forma-<?= $f ?>"><?= $f ?></span><?php endforeach; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="legenda">
    <span><i class="leg leg-1"></i> Classificado direto para a Final</span>
    <span><i class="leg leg-23"></i> Disputam a Semifinal</span>
  </div>
  <p class="muted pequeno">Critérios de desempate: pontos, vitórias, saldo de gols, gols pró, confronto direto.</p>
</section>

<section class="card">
  <h2>Fase final</h2>
  <div class="chave">
    <div class="chave-col">
      <h3>Semifinal</h3>
      <?php card_jogo($db, $semi); ?>
    </div>
    <div class="chave-seta">➜</div>
    <div class="chave-col">
      <h3>Final</h3>
      <?php card_jogo($db, $final); ?>
    </div>
  </div>
</section>

<div class="grade-2">
  <section class="card">
    <h2>Próximos jogos</h2>
    <?php if (!$proximos): ?>
      <p class="muted">Fase de classificação encerrada.</p>
    <?php else: foreach (array_slice($proximos, 0, 4) as $j) card_jogo($db, $j); endif; ?>
    <a class="mais" href="jogos.php">Ver tabela completa →</a>
  </section>

  <section class="card">
    <h2>Artilharia</h2>
    <?php if (!$art): ?>
      <p class="muted">Nenhum gol registrado ainda.</p>
    <?php else: ?>
      <table class="tabela compacta">
        <tbody>
        <?php foreach (array_slice($art, 0, 10) as $i => $p): ?>
          <tr><td><?= $i + 1 ?></td><td class="esq"><?= escudo($db, $p['time'], 'xs') ?> <?= e($p['nome']) ?> <span class="muted">· <?= e($db['times'][$p['time']]['sigla']) ?></span></td><td><strong><?= $p['gols'] ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<?php rodape(); ?>
