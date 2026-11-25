<?php
declare(strict_types=1);

function topo(string $titulo, string $ativo = ''): void
{
    $db = db();
    $u = usuario();
    $links = [
        'index'  => ['index.php', 'Classificação'],
        'jogos'  => ['jogos.php', 'Jogos'],
        'times'  => ['times.php', 'Equipes'],
    ];
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · <?= e($db['nome']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<header class="topo">
  <div class="topo-in">
    <a class="marca" href="index.php">⚽ <?= e($db['nome']) ?></a>
    <nav>
      <?php foreach ($links as $k => [$href, $txt]): ?>
        <a href="<?= $href ?>" class="<?= $ativo === $k ? 'ativo' : '' ?>"><?= $txt ?></a>
      <?php endforeach; ?>
      <?php if ($u === null): ?>
        <a href="login.php" class="btn-login <?= $ativo === 'login' ? 'ativo' : '' ?>">Entrar</a>
      <?php else: ?>
        <a href="painel.php" class="btn-login <?= $ativo === 'painel' ? 'ativo' : '' ?>">
          <?= $u === 'coord' ? 'Coordenação' : e($db['times'][$u]['sigla'] ?? 'Painel') ?>
        </a>
        <a href="logout.php">Sair</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="conteudo">
<?php
    if ($f = flash()) {
        echo '<div class="aviso aviso-' . e($f[1]) . '">' . e($f[0]) . '</div>';
    }
}

function rodape(): void
{
    ?>
</main>
<footer class="rodape">Sistema todos contra todos · 1º vai direto à final · 2º x 3º disputam a outra vaga</footer>
</body>
</html>
<?php
}

function card_jogo(array $db, array $j, ?string $destaque = null): void
{
    $cls = 'jogo' . ($j['encerrado'] ? ' encerrado' : '') . ($j['fase'] !== 'grupo' ? ' mata' : '');
    $res = '';
    if ($destaque && $j['encerrado'] && in_array($destaque, [$j['casa'], $j['fora']], true)) {
        $meu = $j['casa'] === $destaque ? $j['gc'] : $j['gf'];
        $dele = $j['casa'] === $destaque ? $j['gf'] : $j['gc'];
        $v = vencedor($j);
        $res = $v === $destaque ? 'V' : ($v === null && $meu === $dele ? 'E' : 'D');
    }
    ?>
    <div class="<?= $cls ?>">
      <div class="jogo-info">
        <span class="fase"><?= e(nome_fase($j)) ?></span>
        <?php if ($j['data'] || $j['hora']): ?>
          <span><?= data_br($j['data']) ?> <?= e($j['hora']) ?></span>
        <?php else: ?>
          <span class="muted">Data a definir</span>
        <?php endif; ?>
        <?php if ($j['local']): ?><span class="muted">📍 <?= e($j['local']) ?></span><?php endif; ?>
        <?php if ($res): ?><span class="forma forma-<?= $res ?>"><?= $res ?></span><?php endif; ?>
      </div>
      <div class="jogo-linha">
        <a class="lado casa" href="<?= $j['casa'] ? 'time.php?id=' . e($j['casa']) : '#' ?>">
          <span class="tnome"><?= e(nome_time($db, $j['casa'], rotulo_vaga($j, 'casa'))) ?></span>
          <?= escudo($db, $j['casa']) ?>
        </a>
        <div class="placar"><?= placar($j) ?></div>
        <a class="lado fora" href="<?= $j['fora'] ? 'time.php?id=' . e($j['fora']) : '#' ?>">
          <?= escudo($db, $j['fora']) ?>
          <span class="tnome"><?= e(nome_time($db, $j['fora'], rotulo_vaga($j, 'fora'))) ?></span>
        </a>
      </div>
      <?php if ($j['encerrado'] && $j['gols']): ?>
        <div class="goleadores"><?= lista_gols($db, $j) ?></div>
      <?php endif; ?>
      <?php if ($j['obs']): ?><div class="obs"><?= e($j['obs']) ?></div><?php endif; ?>
    </div>
    <?php
}

function lista_gols(array $db, array $j): string
{
    $porTime = [$j['casa'] => [], $j['fora'] => []];
    foreach ([$j['casa'], $j['fora']] as $tid) {
        if ($tid === null) continue;
        foreach ($db['times'][$tid]['jogadores'] as $p) {
            $q = (int)($j['gols'][$p['id']] ?? 0);
            if ($q > 0) {
                $porTime[$tid][] = e($p['nome']) . ($q > 1 ? " ($q)" : '');
            }
        }
    }
    $c = $porTime[$j['casa']] ?? [];
    $f = $porTime[$j['fora']] ?? [];
    return '<span>' . ($c ? '⚽ ' . implode(', ', $c) : '') . '</span>'
         . '<span>' . ($f ? implode(', ', $f) . ' ⚽' : '') . '</span>';
}
