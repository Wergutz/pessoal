<?php
require __DIR__ . '/inc/lib.php';

$db = db();
$filtro = $_GET['time'] ?? '';
if ($filtro !== '' && !isset($db['times'][$filtro])) $filtro = '';
$comTabela = ($_GET['classificacao'] ?? '1') === '1';

// jogos agrupados por fase / rodada
$grupos = [];
$folgas = [];
$rodadaDe = [];
foreach ($db['jogos'] as $j) {
    if ($j['fase'] === 'grupo') {
        $folgas[$j['rodada']] ??= array_keys($db['times']);
        $folgas[$j['rodada']] = array_diff($folgas[$j['rodada']], [$j['casa'], $j['fora']]);
    }
    $chave = $j['fase'] === 'grupo' ? nome_fase($j) : 'Fase final';
    $rodadaDe[$chave] = $j['rodada'];
    if ($j['fase'] === 'grupo') $grupos[$chave] ??= [];
    if ($filtro && !in_array($filtro, [$j['casa'], $j['fora']], true)) continue;
    $grupos[$chave][] = $j;
}
$cl = classificacao($db);

function placar_txt(array $j): string
{
    if (!$j['encerrado']) return '<span class="x">x</span>';
    $s = '<b>' . (int)$j['gc'] . '</b> <span class="x">x</span> <b>' . (int)$j['gf'] . '</b>';
    if ($j['fase'] !== 'grupo' && $j['gc'] === $j['gf'] && $j['pc'] !== null) {
        $s .= '<div class="pen">(' . (int)$j['pc'] . ' x ' . (int)$j['pf'] . ' pên.)</div>';
    }
    return $s;
}

function data_curta(array $j): string
{
    if ($j['data'] === '') return '—';
    $ts = strtotime($j['data']);
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return $ts ? $dias[(int)date('w', $ts)] . ' ' . date('d/m', $ts) : e($j['data']);
}

$titulo = $filtro ? 'Jogos — ' . $db['times'][$filtro]['nome'] : 'Tabela de jogos';
$q = fn(array $p) => 'imprimir.php?' . http_build_query(array_filter(['time' => $filtro, 'classificacao' => $comTabela ? '1' : '0'] + $p, fn($v) => $v !== ''));
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · <?= e($db['nome']) ?></title>
<style>
  @page { size: A4 portrait; margin: 12mm; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 11.5px/1.35 Arial, Helvetica, sans-serif; color: #000; background: #e5e7eb; }
  .barra { position: sticky; top: 0; background: #111; color: #fff; padding: 10px 16px; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; z-index: 2; font-size: 14px; }
  .barra button { background: #f5c518; border: 0; padding: 8px 16px; font-weight: bold; border-radius: 6px; cursor: pointer; font-size: 14px; }
  .barra a { color: #f5c518; }
  .barra select { padding: 6px; border-radius: 6px; font-size: 14px; }
  .barra label { display: flex; gap: 6px; align-items: center; }
  .folha { width: 186mm; margin: 12px auto; background: #fff; padding: 10mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
  .cab { display: flex; align-items: center; gap: 14px; border-bottom: 3px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
  .cab img { height: 62px; width: auto; }
  .cab h1 { margin: 0; font-size: 19px; text-transform: uppercase; }
  .cab h2 { margin: 2px 0 0; font-size: 13px; font-weight: normal; }
  .cab .data { margin-left: auto; font-size: 9px; color: #555; text-align: right; }
  h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; background: #111; color: #fff; padding: 3px 8px; margin: 9px 0 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  h3.mata { background: #b8900a; }
  table { width: 100%; border-collapse: collapse; }
  table.jogos { table-layout: fixed; }
  td, th { border-bottom: 1px solid #bbb; padding: 5px 6px; vertical-align: middle; }
  .jogos td { height: 25px; padding-top: 3px; padding-bottom: 3px; }
  .jogos .c-casa, .jogos .c-fora { overflow: hidden; white-space: nowrap; }
  .c-id { width: 44px; font-weight: bold; color: #444; }
  .c-data { width: 70px; white-space: nowrap; }
  .c-hora { width: 44px; }
  .c-local { width: 110px; color: #333; font-size: 10px; }
  .c-casa { text-align: right; font-weight: bold; }
  .c-fora { font-weight: bold; }
  .c-placar { width: 74px; text-align: center; font-size: 14px; white-space: nowrap; }
  .x { color: #777; font-size: 11px; }
  .pen { font-size: 8.5px; color: #444; }
  .t { display: inline-flex; align-items: center; gap: 6px; }
  .c-casa .t { flex-direction: row-reverse; }
  .escudo { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; font-size: 6px; font-weight: bold; flex: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .escudo-vazio { border: 1px dashed #999; color: #999; }
  .vaga { font-style: italic; font-weight: normal; color: #555; }
  .destaque { background: #fff7d1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .folga { font-size: 9.5px; color: #555; padding: 3px 6px; }
  .folga-time { font-size: 11px; font-style: italic; padding: 7px 6px; border-bottom: 1px solid #bbb; }
  .classif th { font-size: 9px; text-transform: uppercase; color: #444; text-align: center; border-bottom: 2px solid #000; }
  .classif td { text-align: center; padding-top: 3px; padding-bottom: 3px; }
  .classif .esq { text-align: left; }
  .classif tr.p1 td:first-child { background: #f5c518; font-weight: bold; }
  .classif tr.p2 td:first-child, .classif tr.p3 td:first-child { background: #bcd0ff; font-weight: bold; }
  .classif td:first-child { -webkit-print-color-adjust: exact; print-color-adjust: exact; width: 26px; }
  .regra { font-size: 9px; color: #444; margin-top: 4px; }
  .duas { display: grid; grid-template-columns: 1fr; gap: 0; }
  @media print {
    body { background: #fff; }
    .barra { display: none; }
    .folha { margin: 0; box-shadow: none; padding: 0; width: auto; }
    h3, tr { break-inside: avoid; page-break-inside: avoid; }
    h3 { break-after: avoid; page-break-after: avoid; }
  }
  @media screen and (max-width: 800px) { .folha { width: auto; margin: 8px; padding: 12px; overflow-x: auto; } .c-local { display: none; } }
</style>
</head>
<body>
<div class="barra">
  <button onclick="window.print()">🖨️ Imprimir</button>
  <label>Equipe:
    <select onchange="location.href=this.value">
      <option value="<?= e($q(['time' => ''])) ?>">Todas</option>
      <?php foreach ($db['times'] as $tid => $t): ?>
        <option value="<?= e($q(['time' => $tid])) ?>" <?= $filtro === $tid ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><input type="checkbox" <?= $comTabela ? 'checked' : '' ?> onchange="location.href=this.checked ? '<?= e($q(['classificacao' => '1'])) ?>' : '<?= e($q(['classificacao' => '0'])) ?>'"> Incluir classificação</label>
  <a href="jogos.php<?= $filtro ? '?time=' . e($filtro) : '' ?>">← Voltar</a>
</div>

<div class="folha">
  <div class="cab">
    <?php if ($lg = logo_url($db, true)): ?><img src="<?= e($lg) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($db['nome']) ?></h1>
      <h2><?= e($titulo) ?></h2>
    </div>
    <div class="data">Atualizado em<br><?= date('d/m/Y H:i') ?></div>
  </div>

  <?php if ($comTabela): ?>
    <h3>Classificação</h3>
    <table class="classif">
      <thead><tr><th>#</th><th class="esq">Equipe</th><th>P</th><th>J</th><th>V</th><th>E</th><th>D</th><th>GP</th><th>GC</th><th>SG</th></tr></thead>
      <tbody>
      <?php foreach ($cl as $i => $l): ?>
        <tr class="p<?= $i + 1 ?> <?= $filtro === $l['id'] ? 'destaque' : '' ?>">
          <td><?= $i + 1 ?></td>
          <td class="esq"><span class="t"><?= escudo($db, $l['id'], 'xs') ?> <?= e($db['times'][$l['id']]['nome']) ?></span></td>
          <td><b><?= $l['P'] ?></b></td><td><?= $l['J'] ?></td><td><?= $l['V'] ?></td><td><?= $l['E'] ?></td><td><?= $l['D'] ?></td>
          <td><?= $l['GP'] ?></td><td><?= $l['GC'] ?></td><td><?= $l['SG'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="regra">1º colocado vai direto para a Final · 2º x 3º disputam a Semifinal · Desempate: pontos, vitórias, saldo, gols pró, confronto direto.</div>
  <?php endif; ?>

  <?php foreach ($grupos as $nome => $lista): $mata = $nome === 'Fase final'; $r = $rodadaDe[$nome]; ?>
    <h3 class="<?= $mata ? 'mata' : '' ?>"><?= e($nome) ?></h3>
    <table class="jogos">
      <colgroup><col style="width:46px"><col style="width:72px"><col style="width:44px"><col><col style="width:78px"><col><col style="width:112px"></colgroup>
      <?php foreach ($lista as $j): ?>
        <tr>
          <td class="c-id"><?= e($j['id']) ?></td>
          <td class="c-data"><?= data_curta($j) ?></td>
          <td class="c-hora"><?= $j['hora'] !== '' ? e($j['hora']) : '' ?></td>
          <td class="c-casa"><span class="t"><?= escudo($db, $j['casa'], 'xs') ?> <?= $j['casa'] ? e(nome_time($db, $j['casa'])) : '<span class="vaga">' . e(rotulo_vaga($j, 'casa')) . '</span>' ?></span></td>
          <td class="c-placar"><?= placar_txt($j) ?></td>
          <td class="c-fora"><span class="t"><?= escudo($db, $j['fora'], 'xs') ?> <?= $j['fora'] ? e(nome_time($db, $j['fora'])) : '<span class="vaga">' . e(rotulo_vaga($j, 'fora')) . '</span>' ?></span></td>
          <td class="c-local"><?= e($j['local']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$lista): ?>
      <div class="folga folga-time">Folga — <?= e(nome_time($db, $filtro)) ?> não joga nesta rodada.</div>
    <?php elseif ($r && !$filtro && !empty($folgas[$r])): $f = reset($folgas[$r]); ?>
      <div class="folga">Folga: <?= e(nome_time($db, $f)) ?></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
</body>
</html>
