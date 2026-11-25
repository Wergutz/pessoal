<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

exigir_login();
$u = usuario();
$coord = eh_coord();
$aba = $_GET['aba'] ?? ($coord ? 'jogos' : 'inicio');

// ============================================================
// AÇÕES (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    $volta = $_POST['volta'] ?? 'painel.php';
    if (!preg_match('/^painel\.php(\?[\w=&%.-]*)?$/', $volta)) $volta = 'painel.php';

    try {
        switch ($acao) {
            // ---------------- Trocar a própria senha ----------------
            case 'senha':
                $db = db_load();
                $hash = $coord ? $db['coord']['senha_hash'] : $db['times'][$u]['senha_hash'];
                $nova = (string)($_POST['nova'] ?? '');
                if (!password_verify((string)($_POST['atual'] ?? ''), $hash)) throw new RuntimeException('Senha atual incorreta.');
                if (mb_strlen($nova) < 6) throw new RuntimeException('A nova senha precisa ter pelo menos 6 caracteres.');
                if ($nova !== ($_POST['confirma'] ?? '')) throw new RuntimeException('A confirmação não confere.');
                db_update(function (&$db) use ($coord, $u, $nova) {
                    $h = password_hash($nova, PASSWORD_DEFAULT);
                    if ($coord) $db['coord']['senha_hash'] = $h; else $db['times'][$u]['senha_hash'] = $h;
                });
                flash('Senha alterada com sucesso.');
                break;

            // ---------------- Elenco ----------------
            case 'elenco':
                $tid = $coord ? ($_POST['time'] ?? '') : $u;
                $db = db_load();
                if (!isset($db['times'][$tid])) throw new RuntimeException('Equipe inválida.');
                if (!$coord && !empty($db['elenco_bloqueado'])) throw new RuntimeException('A coordenação bloqueou a edição dos elencos.');
                db_update(function (&$db) use ($tid) {
                    $lista = [];
                    foreach ($_POST['jog'] ?? [] as $pid => $p) {
                        if (!empty($p['remover']) || trim($p['nome'] ?? '') === '') continue;
                        $lista[] = ['id' => (string)$pid, 'nome' => mb_substr(trim($p['nome']), 0, 60),
                            'numero' => mb_substr(trim($p['numero'] ?? ''), 0, 3), 'posicao' => mb_substr(trim($p['posicao'] ?? ''), 0, 20)];
                    }
                    // manter apenas IDs que já eram deste time
                    $validos = array_column($db['times'][$tid]['jogadores'], 'id');
                    $lista = array_values(array_filter($lista, fn($p) => in_array($p['id'], $validos, true)));
                    // novos: um por linha -> "10 - Nome - Posição" ou "Nome"
                    foreach (preg_split('/\R/', (string)($_POST['novos'] ?? '')) as $linha) {
                        $linha = trim($linha);
                        if ($linha === '') continue;
                        $partes = array_map('trim', preg_split('/\s*[-;|\t]\s*/', $linha));
                        $num = '';
                        if (preg_match('/^\d{1,3}$/', $partes[0]) && count($partes) > 1) $num = array_shift($partes);
                        elseif (preg_match('/^(\d{1,3})\s+(.+)$/', $partes[0], $m)) { $num = $m[1]; $partes[0] = $m[2]; }
                        $lista[] = ['id' => 'p' . ($db['seq']++), 'nome' => mb_substr($partes[0], 0, 60),
                            'numero' => $num, 'posicao' => mb_substr($partes[1] ?? '', 0, 20)];
                    }
                    $db['times'][$tid]['jogadores'] = $lista;
                });
                flash('Elenco salvo.');
                break;

            // ---------------- Resultado / agenda de jogo ----------------
            case 'jogo':
                if (!$coord) throw new RuntimeException('Sem permissão.');
                $id = $_POST['id'] ?? '';
                $dbv = db();
                $jogo = null;
                foreach ($dbv['jogos'] as $j) if ($j['id'] === $id) $jogo = $j;
                if (!$jogo) throw new RuntimeException('Jogo não encontrado.');

                $num = fn($k) => ($_POST[$k] ?? '') === '' ? null : max(0, (int)$_POST[$k]);
                $gc = $num('gc'); $gf = $num('gf'); $pc = $num('pc'); $pf = $num('pf');
                $enc = !empty($_POST['encerrado']);
                if ($enc) {
                    if ($jogo['casa'] === null || $jogo['fora'] === null) throw new RuntimeException('Os participantes deste jogo ainda não estão definidos.');
                    if ($gc === null || $gf === null) throw new RuntimeException('Informe o placar para encerrar o jogo.');
                    if ($jogo['fase'] !== 'grupo' && $gc === $gf && ($pc === null || $pf === null || $pc === $pf))
                        throw new RuntimeException('Jogo eliminatório empatado: informe o placar dos pênaltis (com vencedor).');
                }
                if ($jogo['fase'] === 'grupo' || $gc !== $gf) { $pc = $pf = null; }

                // gols por jogador
                $gols = [];
                $soma = [$jogo['casa'] => 0, $jogo['fora'] => 0];
                foreach (array_filter([$jogo['casa'], $jogo['fora']]) as $tid) {
                    foreach ($dbv['times'][$tid]['jogadores'] as $p) {
                        $q = (int)($_POST['gol'][$p['id']] ?? 0);
                        if ($q > 0) { $gols[$p['id']] = $q; $soma[$tid] += $q; }
                    }
                }
                if ($jogo['casa'] && $soma[$jogo['casa']] > (int)$gc) throw new RuntimeException('Há mais gols marcados por jogadores do mandante do que no placar.');
                if ($jogo['fora'] && $soma[$jogo['fora']] > (int)$gf) throw new RuntimeException('Há mais gols marcados por jogadores do visitante do que no placar.');

                db_update(function (&$db) use ($id, $gc, $gf, $pc, $pf, $enc, $gols) {
                    foreach ($db['jogos'] as &$j) {
                        if ($j['id'] !== $id) continue;
                        $j['data']  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data'] ?? '') ? $_POST['data'] : '';
                        $j['hora']  = preg_match('/^\d{2}:\d{2}$/', $_POST['hora'] ?? '') ? $_POST['hora'] : '';
                        $j['local'] = mb_substr(trim($_POST['local'] ?? ''), 0, 80);
                        $j['obs']   = mb_substr(trim($_POST['obs'] ?? ''), 0, 200);
                        $j['gc'] = $gc; $j['gf'] = $gf; $j['pc'] = $pc; $j['pf'] = $pf;
                        $j['encerrado'] = $enc;
                        $j['gols'] = $gols;
                    }
                });
                flash('Jogo ' . $id . ' salvo.');
                $volta = 'painel.php?aba=jogos';
                break;

            // ---------------- Dados da equipe (coordenação) ----------------
            case 'time':
                if (!$coord) throw new RuntimeException('Sem permissão.');
                $tid = $_POST['time'] ?? '';
                $db = db_load();
                if (!isset($db['times'][$tid])) throw new RuntimeException('Equipe inválida.');
                $arquivo = salvar_imagem('escudo', $tid);
                $novaSenha = (string)($_POST['nova_senha'] ?? '');
                if ($novaSenha !== '' && mb_strlen($novaSenha) < 6) throw new RuntimeException('A senha da equipe precisa ter pelo menos 6 caracteres.');
                db_update(function (&$db) use ($tid, $arquivo, $novaSenha) {
                    $t = &$db['times'][$tid];
                    $nome = trim($_POST['nome'] ?? '');
                    if ($nome !== '') $t['nome'] = mb_substr($nome, 0, 40);
                    $sigla = trim($_POST['sigla'] ?? '');
                    if ($sigla !== '') $t['sigla'] = mb_strtoupper(mb_substr($sigla, 0, 4));
                    $t['cor1'] = cor_valida($_POST['cor1'] ?? '', $t['cor1']);
                    $t['cor2'] = cor_valida($_POST['cor2'] ?? '', $t['cor2']);
                    $antigo = $t['escudo'];
                    if ($arquivo) $t['escudo'] = $arquivo;
                    elseif (!empty($_POST['remover_escudo'])) $t['escudo'] = '';
                    if ($antigo && $antigo !== $t['escudo']) apagar_upload($antigo);
                    if ($novaSenha !== '') $t['senha_hash'] = password_hash($novaSenha, PASSWORD_DEFAULT);
                });
                flash('Equipe atualizada.');
                break;

            // ---------------- Configurações gerais ----------------
            case 'config':
                if (!$coord) throw new RuntimeException('Sem permissão.');
                $logo = salvar_imagem('logo', 'logo');
                db_update(function (&$db) use ($logo) {
                    $antigo = $db['logo'] ?? '';
                    if ($logo) $db['logo'] = $logo;
                    elseif (!empty($_POST['remover_logo'])) $db['logo'] = '';
                    if ($antigo && $antigo !== ($db['logo'] ?? '')) apagar_upload($antigo);
                    $n = trim($_POST['nome'] ?? '');
                    if ($n !== '') $db['nome'] = mb_substr($n, 0, 60);
                    $db['elenco_bloqueado'] = !empty($_POST['elenco_bloqueado']);
                });
                flash('Configurações salvas.');
                break;

            case 'reiniciar':
                if (!$coord) throw new RuntimeException('Sem permissão.');
                if (($_POST['confirmar'] ?? '') !== 'REINICIAR') throw new RuntimeException('Digite REINICIAR para confirmar.');
                db_update(function (&$db) {
                    $db['jogos'] = gerar_tabela(array_keys($db['times']));
                });
                flash('Tabela reiniciada: todos os resultados foram apagados.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'erro');
    }
    redirect($volta);
}

// ============================================================
// TELAS
// ============================================================
$db = db();
$abas = $coord
    ? ['jogos' => 'Jogos e resultados', 'equipes' => 'Equipes', 'config' => 'Configurações', 'senha' => 'Minha senha']
    : ['inicio' => 'Meus jogos', 'elenco' => 'Elenco', 'senha' => 'Minha senha'];

topo('Painel', 'painel');
?>
<section class="painel-topo">
  <?php if ($coord): ?>
    <h1>🛡️ Coordenação</h1>
  <?php else: $t = $db['times'][$u]; ?>
    <div class="hero-time mini" style="--c1:<?= e($t['cor1']) ?>;--c2:<?= e($t['cor2']) ?>">
      <?= escudo($db, $u, 'lg') ?><div><small>Painel da equipe</small><h1><?= e($t['nome']) ?></h1></div>
    </div>
  <?php endif; ?>
  <nav class="abas">
    <?php foreach ($abas as $k => $txt): ?>
      <a href="painel.php?aba=<?= $k ?>" class="<?= $aba === $k || ($aba === 'jogo' && $k === 'jogos') || ($aba === 'elenco' && $k === 'equipes' && $coord) ? 'ativo' : '' ?>"><?= $txt ?></a>
    <?php endforeach; ?>
  </nav>
</section>

<?php
// ------------------------------------------------------------
// Tela: minha senha
// ------------------------------------------------------------
if ($aba === 'senha'): ?>
  <section class="card estreito">
    <h2>Alterar senha</h2>
    <form method="post">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="senha"><input type="hidden" name="volta" value="painel.php?aba=senha">
      <label>Senha atual <input type="password" name="atual" required autocomplete="current-password"></label>
      <label>Nova senha <input type="password" name="nova" required minlength="6" autocomplete="new-password"></label>
      <label>Confirmar nova senha <input type="password" name="confirma" required minlength="6" autocomplete="new-password"></label>
      <button class="btn">Salvar</button>
    </form>
  </section>

<?php
// ------------------------------------------------------------
// Tela: elenco (time edita o seu; coordenação edita qualquer um)
// ------------------------------------------------------------
elseif ($aba === 'elenco'):
    $tid = $coord ? ($_GET['time'] ?? '') : $u;
    if (!isset($db['times'][$tid])) { echo '<p>Equipe inválida.</p>'; rodape(); exit; }
    $t = $db['times'][$tid];
    $bloq = !$coord && !empty($db['elenco_bloqueado']);
    $voltaEl = 'painel.php?aba=elenco' . ($coord ? '&time=' . $tid : '');
?>
  <section class="card">
    <h2>Elenco — <?= e($t['nome']) ?> <span class="muted">(<?= count($t['jogadores']) ?> jogadores)</span></h2>
    <?php if ($bloq): ?><div class="aviso aviso-erro">A edição de elencos está bloqueada pela coordenação.</div><?php endif; ?>
    <form method="post">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="elenco"><input type="hidden" name="time" value="<?= e($tid) ?>"><input type="hidden" name="volta" value="<?= e($voltaEl) ?>">
      <?php if ($t['jogadores']): ?>
      <div class="tabela-wrap">
      <table class="tabela form-tabela">
        <thead><tr><th>Nº</th><th class="esq">Nome</th><th class="esq">Posição</th><th>Remover</th></tr></thead>
        <tbody>
        <?php foreach ($t['jogadores'] as $p): $pid = e($p['id']); ?>
          <tr>
            <td><input class="in-num" name="jog[<?= $pid ?>][numero]" value="<?= e($p['numero']) ?>" maxlength="3" <?= $bloq ? 'disabled' : '' ?>></td>
            <td><input name="jog[<?= $pid ?>][nome]" value="<?= e($p['nome']) ?>" maxlength="60" <?= $bloq ? 'disabled' : '' ?>></td>
            <td><input name="jog[<?= $pid ?>][posicao]" value="<?= e($p['posicao']) ?>" maxlength="20" list="posicoes" <?= $bloq ? 'disabled' : '' ?>></td>
            <td><input type="checkbox" name="jog[<?= $pid ?>][remover]" value="1" <?= $bloq ? 'disabled' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
      <?php if (!$bloq): ?>
        <label>Adicionar jogadores (um por linha — <code>número - nome - posição</code>, ou só o nome)
          <textarea name="novos" rows="6" placeholder="10 - João Silva - Meia&#10;1 - Carlos - Goleiro&#10;Pedro Souza"></textarea>
        </label>
        <button class="btn">Salvar elenco</button>
      <?php endif; ?>
      <datalist id="posicoes"><option>Goleiro</option><option>Zagueiro</option><option>Lateral</option><option>Volante</option><option>Meia</option><option>Atacante</option></datalist>
    </form>
  </section>
  <?php if ($coord): ?><a class="mais" href="painel.php?aba=equipes">← Voltar às equipes</a><?php endif; ?>

<?php
// ------------------------------------------------------------
// Tela: início do time
// ------------------------------------------------------------
elseif ($aba === 'inicio' && !$coord):
    $cl = classificacao($db);
    foreach ($cl as $i => $l) if ($l['id'] === $u) { $pos = $i + 1; $linha = $l; }
?>
  <div class="stats">
    <div><strong><?= $pos ?>º</strong><span>Posição</span></div>
    <div><strong><?= $linha['P'] ?></strong><span>Pontos</span></div>
    <div><strong><?= $linha['V'] ?>-<?= $linha['E'] ?>-<?= $linha['D'] ?></strong><span>V-E-D</span></div>
    <div><strong><?= $linha['SG'] ?></strong><span>Saldo</span></div>
  </div>
  <section class="card">
    <h2>Jogos da equipe</h2>
    <?php foreach ($db['jogos'] as $j) if (in_array($u, [$j['casa'], $j['fora']], true)) card_jogo($db, $j, $u); ?>
    <?php if ($pos > 3 && fase_grupos_encerrada($db)): ?><p class="muted">A equipe não se classificou para a fase final.</p><?php endif; ?>
  </section>

<?php
// ------------------------------------------------------------
// Tela: lista de jogos (coordenação)
// ------------------------------------------------------------
elseif ($aba === 'jogos' && $coord): ?>
  <section class="card">
    <h2>Jogos e resultados</h2>
    <p class="muted pequeno">Clique em um jogo para lançar data, local, placar e goleadores. Semifinal e Final são preenchidas automaticamente ao fim da fase de classificação.</p>
    <div class="filtros imprimir">
      <span class="muted pequeno">🖨️ Imprimir súmulas:</span>
      <?php foreach (array_unique(array_filter(array_column($db['jogos'], 'rodada'))) as $r): ?>
        <a class="chip" href="sumula.php?rodada=<?= $r ?>" target="_blank">Rodada <?= $r ?></a>
      <?php endforeach; ?>
      <a class="chip" href="sumula.php?rodada=final" target="_blank">Semi + Final</a>
      <a class="chip" href="sumula.php?rodada=todos" target="_blank">Todas</a>
    </div>
    <div class="tabela-wrap">
    <table class="tabela lista-jogos">
      <thead><tr><th>Jogo</th><th class="esq">Fase</th><th class="esq">Data</th><th class="dir">Mandante</th><th>Placar</th><th class="esq">Visitante</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($db['jogos'] as $j): ?>
        <tr>
          <td><?= e($j['id']) ?></td>
          <td class="esq"><?= e(nome_fase($j)) ?></td>
          <td class="esq"><?= data_br($j['data']) ?> <?= e($j['hora']) ?></td>
          <td class="dir"><?= e(nome_time($db, $j['casa'], rotulo_vaga($j, 'casa'))) ?></td>
          <td class="nowrap"><?= placar($j) ?></td>
          <td class="esq"><?= e(nome_time($db, $j['fora'], rotulo_vaga($j, 'fora'))) ?></td>
          <td><?= $j['encerrado'] ? '<span class="tag tag-ok">Encerrado</span>' : '<span class="tag">Pendente</span>' ?></td>
          <td class="nowrap"><a class="btn btn-sm" href="painel.php?aba=jogo&id=<?= e($j['id']) ?>">Editar</a> <a class="btn btn-sm btn-sec" href="sumula.php?id=<?= e($j['id']) ?>" target="_blank" title="Imprimir súmula">🖨️ Súmula</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>

<?php
// ------------------------------------------------------------
// Tela: editar um jogo (coordenação)
// ------------------------------------------------------------
elseif ($aba === 'jogo' && $coord):
    $j = null;
    foreach ($db['jogos'] as $x) if ($x['id'] === ($_GET['id'] ?? '')) $j = $x;
    if (!$j) { echo '<p>Jogo não encontrado.</p>'; rodape(); exit; }
    $definido = $j['casa'] !== null && $j['fora'] !== null;
    $mata = $j['fase'] !== 'grupo';
?>
  <section class="card">
    <h2><?= e($j['id']) ?> · <?= e(nome_fase($j)) ?></h2>
    <h3 class="confronto"><?= escudo($db, $j['casa']) ?> <?= e(nome_time($db, $j['casa'], rotulo_vaga($j, 'casa'))) ?> <span class="vs">x</span> <?= e(nome_time($db, $j['fora'], rotulo_vaga($j, 'fora'))) ?> <?= escudo($db, $j['fora']) ?></h3>
    <?php if (!$definido): ?><div class="aviso">Os participantes serão definidos automaticamente. Você já pode cadastrar data e local.</div><?php endif; ?>
    <form method="post">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="jogo"><input type="hidden" name="id" value="<?= e($j['id']) ?>"><input type="hidden" name="volta" value="painel.php?aba=jogo&id=<?= e($j['id']) ?>">
      <div class="linha-form">
        <label>Data <input type="date" name="data" value="<?= e($j['data']) ?>"></label>
        <label>Hora <input type="time" name="hora" value="<?= e($j['hora']) ?>"></label>
        <label class="flex2">Local <input name="local" value="<?= e($j['local']) ?>" maxlength="80"></label>
      </div>
      <?php if ($definido): ?>
        <div class="linha-form placar-form">
          <label><?= e($db['times'][$j['casa']]['sigla']) ?> <input type="number" min="0" name="gc" value="<?= e((string)$j['gc']) ?>"></label>
          <span class="vs">x</span>
          <label><?= e($db['times'][$j['fora']]['sigla']) ?> <input type="number" min="0" name="gf" value="<?= e((string)$j['gf']) ?>"></label>
        </div>
        <?php if ($mata): ?>
          <div class="linha-form placar-form">
            <label>Pênaltis <?= e($db['times'][$j['casa']]['sigla']) ?> <input type="number" min="0" name="pc" value="<?= e((string)$j['pc']) ?>"></label>
            <span class="vs">x</span>
            <label>Pênaltis <?= e($db['times'][$j['fora']]['sigla']) ?> <input type="number" min="0" name="pf" value="<?= e((string)$j['pf']) ?>"></label>
          </div>
          <p class="muted pequeno">Pênaltis só são considerados se o jogo terminar empatado.</p>
        <?php endif; ?>

        <h3>Goleadores</h3>
        <div class="grade-2">
          <?php foreach ([$j['casa'], $j['fora']] as $tid): $t = $db['times'][$tid]; ?>
            <div>
              <h4><?= escudo($db, $tid, 'xs') ?> <?= e($t['nome']) ?></h4>
              <?php if (!$t['jogadores']): ?>
                <p class="muted pequeno">Elenco não cadastrado.</p>
              <?php else: foreach ($t['jogadores'] as $p): ?>
                <label class="gol-linha"><span><?= e($p['numero']) ? e($p['numero']) . ' · ' : '' ?><?= e($p['nome']) ?></span>
                  <input type="number" min="0" max="20" name="gol[<?= e($p['id']) ?>]" value="<?= (int)($j['gols'][$p['id']] ?? 0) ?: '' ?>" placeholder="0"></label>
              <?php endforeach; endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="muted pequeno">Gols contra podem ser deixados sem jogador.</p>
      <?php endif; ?>
      <label>Observação <input name="obs" value="<?= e($j['obs']) ?>" maxlength="200" placeholder="Ex.: W.O., jogo adiado..."></label>
      <?php if ($definido): ?>
        <label class="check"><input type="checkbox" name="encerrado" value="1" <?= $j['encerrado'] ? 'checked' : '' ?>> Jogo encerrado (conta na classificação)</label>
      <?php endif; ?>
      <div class="acoes"><button class="btn">Salvar</button> <a href="sumula.php?id=<?= e($j['id']) ?>" target="_blank">🖨️ Imprimir súmula</a> <a href="painel.php?aba=jogos">Cancelar</a></div>
    </form>
  </section>

<?php
// ------------------------------------------------------------
// Tela: equipes (coordenação)
// ------------------------------------------------------------
elseif ($aba === 'equipes' && $coord): ?>
  <div class="grade-2">
  <?php foreach ($db['times'] as $tid => $t): ?>
    <section class="card">
      <h2><?= escudo($db, $tid, 'sm') ?> <?= e($t['nome']) ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="time"><input type="hidden" name="time" value="<?= e($tid) ?>"><input type="hidden" name="volta" value="painel.php?aba=equipes">
        <div class="linha-form">
          <label class="flex2">Nome <input name="nome" value="<?= e($t['nome']) ?>" maxlength="40" required></label>
          <label>Sigla <input name="sigla" value="<?= e($t['sigla']) ?>" maxlength="4" required></label>
        </div>
        <div class="linha-form">
          <label>Cor principal <input type="color" name="cor1" value="<?= e($t['cor1']) ?>"></label>
          <label>Cor secundária <input type="color" name="cor2" value="<?= e($t['cor2']) ?>"></label>
        </div>
        <label>Escudo (PNG/JPG/WEBP, até 2 MB) <input type="file" name="escudo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
        <?php if ($t['escudo']): ?><label class="check"><input type="checkbox" name="remover_escudo" value="1"> Remover escudo atual</label><?php endif; ?>
        <label>Nova senha da equipe <span class="muted">(deixe vazio para manter)</span> <input type="text" name="nova_senha" minlength="6" autocomplete="off"></label>
        <div class="acoes"><button class="btn">Salvar</button> <a href="painel.php?aba=elenco&time=<?= e($tid) ?>">Elenco (<?= count($t['jogadores']) ?>) →</a></div>
      </form>
    </section>
  <?php endforeach; ?>
  </div>

<?php
// ------------------------------------------------------------
// Tela: configurações (coordenação)
// ------------------------------------------------------------
elseif ($aba === 'config' && $coord): ?>
  <section class="card estreito">
    <h2>Configurações</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="config"><input type="hidden" name="volta" value="painel.php?aba=config">
      <?php if ($lg = logo_url($db)): ?><img class="logo-prev" src="<?= e($lg) ?>" alt="Logo atual"><?php endif; ?>
      <label>Logo oficial do campeonato (PNG, JPG ou WEBP, até 3 MB) <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
      <?php if (!empty($db['logo'])): ?><label class="check"><input type="checkbox" name="remover_logo" value="1"> Remover logo enviado</label><?php endif; ?>
      <label>Nome do campeonato <input name="nome" value="<?= e($db['nome']) ?>" maxlength="60"></label>
      <label class="check"><input type="checkbox" name="elenco_bloqueado" value="1" <?= !empty($db['elenco_bloqueado']) ? 'checked' : '' ?>> Bloquear edição de elencos pelas equipes</label>
      <button class="btn">Salvar</button>
    </form>
  </section>
  <section class="card estreito perigo">
    <h2>Reiniciar tabela</h2>
    <p>Gera a tabela de jogos novamente e <strong>apaga todos os resultados</strong>. Equipes e elencos são mantidos.</p>
    <form method="post">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="reiniciar"><input type="hidden" name="volta" value="painel.php?aba=config">
      <label>Digite <code>REINICIAR</code> para confirmar <input name="confirmar" autocomplete="off"></label>
      <button class="btn btn-perigo">Reiniciar</button>
    </form>
  </section>

<?php else: redirect('painel.php'); endif; ?>

<?php rodape(); ?>
