<?php
require __DIR__ . '/inc/lib.php';
exigir_login('coord');

$db = db();
$id = $_GET['id'] ?? '';
$rodada = $_GET['rodada'] ?? '';

$jogos = array_values(array_filter($db['jogos'], function ($j) use ($id, $rodada) {
    if ($id !== '') return $j['id'] === $id;
    if ($rodada === 'todos') return true;
    if ($rodada === 'final') return $j['fase'] !== 'grupo';
    return $rodada !== '' && (string)$j['rodada'] === $rodada;
}));
if (!$jogos) {
    redirect('painel.php?aba=jogos');
}

const LINHAS_MIN = 22;

function data_extenso(string $d): string
{
    if ($d === '') return '____/____/________';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : e($d);
}

function bloco_time(array $db, array $j, string $lado): void
{
    $tid = $j[$lado];
    $t = $tid !== null ? $db['times'][$tid] : null;
    $jogadores = $t['jogadores'] ?? [];
    usort($jogadores, fn($a, $b) => [(int)($a['numero'] ?: 999), $a['nome']] <=> [(int)($b['numero'] ?: 999), $b['nome']]);
    $vazias = max(LINHAS_MIN - count($jogadores), 3);
    ?>
    <div class="equipe">
      <div class="equipe-topo">
        <?= escudo($db, $tid, 'md') ?>
        <div>
          <small><?= $lado === 'casa' ? 'Equipe A' : 'Equipe B' ?></small>
          <strong><?= $t ? e($t['nome']) : '______________________ (' . e(rotulo_vaga($j, $lado)) . ')' ?></strong>
        </div>
      </div>
      <table class="elenco">
        <thead><tr><th class="c-num">Nº</th><th>Jogador</th><th class="c-gol">Gols</th><th class="c-cart">CA</th><th class="c-cart">CV</th></tr></thead>
        <tbody>
          <?php foreach ($jogadores as $p): ?>
            <tr><td class="c-num"><?= e($p['numero']) ?></td><td><?= e($p['nome']) ?></td><td></td><td></td><td></td></tr>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < $vazias; $i++): ?>
            <tr><td class="c-num">&nbsp;</td><td></td><td></td><td></td><td></td></tr>
          <?php endfor; ?>
        </tbody>
      </table>
      <div class="linha-campo">Técnico / responsável: <span></span></div>
      <div class="assin">Assinatura do capitão</div>
    </div>
    <?php
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Súmula<?= count($jogos) === 1 ? ' ' . e($jogos[0]['id']) : 's' ?> · <?= e($db['nome']) ?></title>
<style>
  @page { size: A4 portrait; margin: 10mm; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 11px/1.3 Arial, Helvetica, sans-serif; color: #000; background: #e5e7eb; }
  .barra { position: sticky; top: 0; background: #111; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; z-index: 2; }
  .barra button { background: #f5c518; border: 0; padding: 8px 16px; font-weight: bold; border-radius: 6px; cursor: pointer; font-size: 14px; }
  .barra a { color: #f5c518; }
  .folha { width: 190mm; min-height: 277mm; margin: 12px auto; background: #fff; padding: 8mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); display: flex; flex-direction: column; }
  .cab { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
  .cab h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
  .cab h2 { margin: 2px 0 0; font-size: 13px; font-weight: normal; }
  .cab .num { font-size: 22px; font-weight: bold; border: 2px solid #000; padding: 2px 10px; }
  .info { display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 6px; margin-bottom: 8px; }
  .info div, .linha-campo { border-bottom: 1px solid #000; padding: 3px 0; }
  .info b { font-size: 9px; text-transform: uppercase; color: #333; display: block; }
  .placar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 10px; border: 2px solid #000; padding: 8px; margin-bottom: 8px; }
  .placar .t { font-weight: bold; font-size: 13px; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
  .placar .t.dir { justify-content: flex-end; text-align: right; }
  .placar table { border-collapse: collapse; }
  .placar td, .placar th { border: 1px solid #000; width: 44px; height: 26px; text-align: center; font-size: 9px; }
  .placar th { width: 70px; text-align: left; padding: 0 4px; background: #eee; }
  .equipes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .equipe-topo { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
  .equipe-topo small { display: block; font-size: 9px; text-transform: uppercase; color: #333; }
  .equipe-topo strong { font-size: 12px; text-transform: uppercase; }
  .escudo { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; flex: none; }
  .escudo-vazio { border: 1px dashed #999; color: #999; }
  .elenco { width: 100%; border-collapse: collapse; }
  .elenco th, .elenco td { border: 1px solid #000; padding: 0 4px; height: 19px; }
  .elenco th { background: #eee; font-size: 9px; text-transform: uppercase; }
  .c-num { width: 28px; text-align: center; }
  .c-gol { width: 52px; }
  .c-cart { width: 26px; }
  .linha-campo { margin-top: 8px; }
  .assin { margin-top: 22px; border-top: 1px solid #000; text-align: center; font-size: 9px; padding-top: 2px; }
  .obs { border: 1px solid #000; min-height: 60px; padding: 4px; margin-top: 8px; flex: 1; }
  .obs b, .rodape-assin b { font-size: 9px; text-transform: uppercase; }
  .rodape-assin { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 10px; }
  .rodape-assin div { border-top: 1px solid #000; margin-top: 26px; text-align: center; font-size: 9px; padding-top: 2px; }
  .legenda { font-size: 8.5px; color: #333; margin-top: 4px; }
  @media print {
    body { background: #fff; }
    .barra { display: none; }
    .folha { margin: 0; box-shadow: none; padding: 0; width: auto; min-height: 0; height: 277mm; page-break-after: always; break-after: page; }
    .folha:last-child { page-break-after: auto; break-after: auto; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
  @media screen and (max-width: 800px) { .folha { width: auto; margin: 8px; min-height: 0; } }
</style>
</head>
<body>
<div class="barra">
  <button onclick="window.print()">🖨️ Imprimir</button>
  <span><?= count($jogos) ?> súmula<?= count($jogos) > 1 ? 's' : '' ?></span>
  <a href="painel.php?aba=jogos">← Voltar ao painel</a>
</div>

<?php foreach ($jogos as $j): $mata = $j['fase'] !== 'grupo'; ?>
<section class="folha">
  <div class="cab">
    <div>
      <h1><?= e($db['nome']) ?></h1>
      <h2>Súmula de jogo · <?= e(nome_fase($j)) ?></h2>
    </div>
    <div class="num"><?= e($j['id']) ?></div>
  </div>

  <div class="info">
    <div><b>Data</b><?= data_extenso($j['data']) ?></div>
    <div><b>Horário</b><?= $j['hora'] !== '' ? e($j['hora']) : '____:____' ?></div>
    <div><b>Local</b><?= $j['local'] !== '' ? e($j['local']) : '&nbsp;' ?></div>
    <div><b>Início</b>____:____</div>
    <div><b>Término</b>____:____</div>
    <div><b>Árbitro</b>&nbsp;</div>
  </div>

  <div class="placar">
    <div class="t dir"><?= e(nome_time($db, $j['casa'], rotulo_vaga($j, 'casa'))) ?> <?= escudo($db, $j['casa'], 'md') ?></div>
    <table>
      <tr><th></th><td>A</td><td>B</td></tr>
      <tr><th>1º tempo</th><td></td><td></td></tr>
      <tr><th>Final</th><td></td><td></td></tr>
      <?php if ($mata): ?><tr><th>Pênaltis</th><td></td><td></td></tr><?php endif; ?>
    </table>
    <div class="t"><?= escudo($db, $j['fora'], 'md') ?> <?= e(nome_time($db, $j['fora'], rotulo_vaga($j, 'fora'))) ?></div>
  </div>

  <div class="equipes">
    <?php bloco_time($db, $j, 'casa'); ?>
    <?php bloco_time($db, $j, 'fora'); ?>
  </div>
  <div class="legenda">Gols: marque um traço por gol (ex.: | | ). CA = cartão amarelo · CV = cartão vermelho.<?= $mata ? ' Jogo eliminatório: em caso de empate, decisão por pênaltis.' : '' ?></div>

  <div class="obs"><b>Observações / ocorrências</b></div>

  <div class="rodape-assin">
    <div>Árbitro</div>
    <div>Mesário</div>
    <div>Coordenação</div>
  </div>
</section>
<?php endforeach; ?>
</body>
</html>
