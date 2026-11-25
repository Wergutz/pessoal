<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

$db = db();
topo('Equipes', 'times');
?>
<div class="grade-times">
<?php foreach ($db['times'] as $tid => $t): ?>
  <a class="card-time" href="time.php?id=<?= e($tid) ?>" style="--c1:<?= e($t['cor1']) ?>;--c2:<?= e($t['cor2']) ?>">
    <?= escudo($db, $tid, 'lg') ?>
    <strong><?= e($t['nome']) ?></strong>
    <span><?= count($t['jogadores']) ?> jogadores</span>
  </a>
<?php endforeach; ?>
</div>
<?php rodape(); ?>
