<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// ------------------------------------------------------------
// Sessão
// ------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('campeonato');
    session_start();
}

const DB_FILE = __DIR__ . '/../data/db.json';
const UPLOAD_DIR = __DIR__ . '/../uploads';

function cfg(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/../config.php';
}

// ------------------------------------------------------------
// Banco de dados (arquivo JSON com trava)
// ------------------------------------------------------------
function db_load(): array
{
    if (!file_exists(DB_FILE)) {
        db_save(db_inicial());
    }
    $fp = fopen(DB_FILE, 'r');
    flock($fp, LOCK_SH);
    $json = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $db = json_decode($json ?: '', true);
    if (!is_array($db)) {
        die('Erro ao ler data/db.json');
    }
    return $db;
}

function db_save(array $db): void
{
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $fp = fopen(DB_FILE, 'c+');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/** Atualiza o banco de forma atômica: lê, aplica $fn e grava. */
function db_update(callable $fn): void
{
    $dir = dirname(DB_FILE);
    if (!file_exists(DB_FILE)) {
        db_load();
    }
    $fp = fopen(DB_FILE, 'c+');
    flock($fp, LOCK_EX);
    $db = json_decode(stream_get_contents($fp) ?: '', true);
    $fn($db);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function db_inicial(): array
{
    $c = cfg();
    $times = [];
    foreach ($c['times'] as $id => $t) {
        $times[$id] = [
            'nome'       => $t['nome'],
            'sigla'      => $t['sigla'],
            'cor1'       => $t['cor1'],
            'cor2'       => $t['cor2'],
            'escudo'     => $t['escudo'] ?? '',
            'senha_hash' => password_hash($t['senha'], PASSWORD_DEFAULT),
            'jogadores'  => [],
        ];
    }
    return [
        'nome'  => $c['nome_campeonato'],
        'coord' => ['senha_hash' => password_hash($c['senha_coordenacao'], PASSWORD_DEFAULT)],
        'times' => $times,
        'jogos' => gerar_tabela(array_keys($times)),
        'seq'   => 1,
    ];
}

/**
 * Todos contra todos (método do círculo). Com 5 equipes:
 * 5 rodadas, 2 jogos por rodada, 1 equipe folga em cada rodada.
 * Depois: Semifinal (2º x 3º) e Final (1º x vencedor da semi).
 */
function gerar_tabela(array $ids): array
{
    $lista = $ids;
    if (count($lista) % 2) {
        $lista[] = null; // folga
    }
    $n = count($lista);
    $jogos = [];
    $num = 1;
    for ($r = 0; $r < $n - 1; $r++) {
        for ($i = 0; $i < $n / 2; $i++) {
            $a = $lista[$i];
            $b = $lista[$n - 1 - $i];
            if ($a === null || $b === null) {
                continue;
            }
            // alterna mando para equilibrar
            if (($r + $i) % 2) {
                [$a, $b] = [$b, $a];
            }
            $jogos[] = novo_jogo('J' . $num++, 'grupo', $r + 1, $a, $b);
        }
        // rotaciona mantendo o primeiro fixo
        $fixo = array_shift($lista);
        $ult = array_pop($lista);
        array_unshift($lista, $ult);
        array_unshift($lista, $fixo);
    }
    $jogos[] = novo_jogo('SEMI', 'semi', null, null, null);
    $jogos[] = novo_jogo('FINAL', 'final', null, null, null);
    return $jogos;
}

function novo_jogo(string $id, string $fase, ?int $rodada, ?string $casa, ?string $fora): array
{
    return [
        'id' => $id, 'fase' => $fase, 'rodada' => $rodada,
        'casa' => $casa, 'fora' => $fora,
        'data' => '', 'hora' => '', 'local' => '',
        'gc' => null, 'gf' => null,       // gols casa / fora
        'pc' => null, 'pf' => null,       // pênaltis (só mata-mata)
        'encerrado' => false,
        'gols' => [],                     // [jogador_id => qtd]
        'obs' => '',
    ];
}

// ------------------------------------------------------------
// Classificação
// ------------------------------------------------------------
function classificacao(array $db): array
{
    $c = cfg();
    $tab = [];
    foreach ($db['times'] as $id => $t) {
        $tab[$id] = ['id' => $id, 'J' => 0, 'V' => 0, 'E' => 0, 'D' => 0, 'GP' => 0, 'GC' => 0, 'SG' => 0, 'P' => 0, 'forma' => []];
    }
    $h2h = []; // pontos em confronto direto
    foreach ($db['jogos'] as $j) {
        if ($j['fase'] !== 'grupo' || !$j['encerrado']) {
            continue;
        }
        $a = $j['casa'];
        $b = $j['fora'];
        $ga = (int)$j['gc'];
        $gb = (int)$j['gf'];
        foreach ([[$a, $ga, $gb, $b], [$b, $gb, $ga, $a]] as [$t, $pro, $contra, $adv]) {
            $tab[$t]['J']++;
            $tab[$t]['GP'] += $pro;
            $tab[$t]['GC'] += $contra;
            if ($pro > $contra) {
                $tab[$t]['V']++;
                $pts = $c['pontos_vitoria'];
                $tab[$t]['forma'][] = 'V';
            } elseif ($pro === $contra) {
                $tab[$t]['E']++;
                $pts = $c['pontos_empate'];
                $tab[$t]['forma'][] = 'E';
            } else {
                $tab[$t]['D']++;
                $pts = 0;
                $tab[$t]['forma'][] = 'D';
            }
            $tab[$t]['P'] += $pts;
            $h2h[$t][$adv] = ($h2h[$t][$adv] ?? 0) + $pts;
        }
    }
    foreach ($tab as &$l) {
        $l['SG'] = $l['GP'] - $l['GC'];
    }
    unset($l);

    $nomes = array_map(fn($t) => $t['nome'], $db['times']);
    usort($tab, function ($x, $y) use ($h2h, $nomes) {
        foreach (['P', 'V', 'SG', 'GP'] as $k) {
            if ($x[$k] !== $y[$k]) {
                return $y[$k] <=> $x[$k];
            }
        }
        $hx = $h2h[$x['id']][$y['id']] ?? 0;
        $hy = $h2h[$y['id']][$x['id']] ?? 0;
        if ($hx !== $hy) {
            return $hy <=> $hx;
        }
        return strcmp($nomes[$x['id']], $nomes[$y['id']]);
    });
    return $tab;
}

function fase_grupos_encerrada(array $db): bool
{
    foreach ($db['jogos'] as $j) {
        if ($j['fase'] === 'grupo' && !$j['encerrado']) {
            return false;
        }
    }
    return true;
}

function vencedor(array $j): ?string
{
    if (!$j['encerrado'] || $j['casa'] === null) {
        return null;
    }
    if ($j['gc'] > $j['gf']) return $j['casa'];
    if ($j['gf'] > $j['gc']) return $j['fora'];
    if ($j['pc'] !== null && $j['pf'] !== null && $j['pc'] !== $j['pf']) {
        return $j['pc'] > $j['pf'] ? $j['casa'] : $j['fora'];
    }
    return null;
}

/**
 * Preenche casa/fora da semi e da final a partir da classificação.
 * Só define os confrontos quando a fase de grupos termina.
 */
function resolver_mata_mata(array $db): array
{
    $ok = fase_grupos_encerrada($db);
    $cl = classificacao($db);
    $semiIdx = $finalIdx = null;
    foreach ($db['jogos'] as $i => $j) {
        if ($j['fase'] === 'semi') $semiIdx = $i;
        if ($j['fase'] === 'final') $finalIdx = $i;
    }
    if ($semiIdx !== null) {
        $db['jogos'][$semiIdx]['casa'] = $ok ? $cl[1]['id'] : null;
        $db['jogos'][$semiIdx]['fora'] = $ok ? $cl[2]['id'] : null;
    }
    if ($finalIdx !== null) {
        $db['jogos'][$finalIdx]['casa'] = $ok ? $cl[0]['id'] : null;
        $db['jogos'][$finalIdx]['fora'] = $semiIdx !== null ? vencedor($db['jogos'][$semiIdx]) : null;
    }
    return $db;
}

function rotulo_vaga(array $j, string $lado): string
{
    if ($j['fase'] === 'semi') return $lado === 'casa' ? '2º colocado' : '3º colocado';
    if ($j['fase'] === 'final') return $lado === 'casa' ? '1º colocado' : 'Vencedor da semifinal';
    return 'A definir';
}

function nome_fase(array $j): string
{
    return match ($j['fase']) {
        'semi'  => 'Semifinal',
        'final' => 'Final',
        default => 'Rodada ' . $j['rodada'],
    };
}

function artilharia(array $db): array
{
    $idx = [];
    foreach ($db['times'] as $tid => $t) {
        foreach ($t['jogadores'] as $p) {
            $idx[$p['id']] = ['nome' => $p['nome'], 'numero' => $p['numero'] ?? '', 'time' => $tid, 'gols' => 0];
        }
    }
    foreach ($db['jogos'] as $j) {
        if (!$j['encerrado']) continue;
        foreach ($j['gols'] as $pid => $q) {
            if (isset($idx[$pid])) $idx[$pid]['gols'] += (int)$q;
        }
    }
    $lista = array_values(array_filter($idx, fn($p) => $p['gols'] > 0));
    usort($lista, fn($a, $b) => [$b['gols'], $a['nome']] <=> [$a['gols'], $b['nome']]);
    return $lista;
}

function campeao(array $db): ?string
{
    foreach ($db['jogos'] as $j) {
        if ($j['fase'] === 'final') return vencedor($j);
    }
    return null;
}

/** Carrega o banco já com mata-mata resolvido (para exibição). */
function db(): array
{
    static $db = null;
    return $db ??= resolver_mata_mata(db_load());
}

// ------------------------------------------------------------
// Autenticação
// ------------------------------------------------------------
function usuario(): ?string
{
    return $_SESSION['user'] ?? null; // 'coord' | id do time
}

function eh_coord(): bool
{
    return usuario() === 'coord';
}

function exigir_login(?string $papel = null): void
{
    $u = usuario();
    if ($u === null || ($papel === 'coord' && $u !== 'coord')) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Sessão expirada. Volte e tente novamente.');
    }
}

function flash(?string $msg = null, string $tipo = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $tipo];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------
// Utilidades de exibição
// ------------------------------------------------------------
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function cor_valida(string $c, string $padrao): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $padrao;
}

function escudo(array $db, ?string $tid, string $tam = 'md'): string
{
    if ($tid === null || !isset($db['times'][$tid])) {
        return '<span class="escudo escudo-' . $tam . ' escudo-vazio">?</span>';
    }
    $t = $db['times'][$tid];
    if ($t['escudo'] !== '') {
        return '<img class="escudo escudo-' . $tam . '" src="' . e($t['escudo']) . '" alt="' . e($t['nome']) . '">';
    }
    return '<span class="escudo escudo-' . $tam . '" style="background:' . e($t['cor1']) . ';color:' . e($t['cor2']) . '">'
        . e(mb_substr($t['sigla'], 0, 3)) . '</span>';
}

/** Logo do campeonato: o enviado pelo painel ou o padrão em assets/. */
function logo_url(array $db, bool $impressao = false): string
{
    if (!empty($db['logo'])) return $db['logo'];
    // logo padrão: texto branco para o site (fundo preto), texto preto para impressão
    $arq = $impressao ? 'assets/logo-print.png' : 'assets/logo.png';
    return file_exists(__DIR__ . '/../' . $arq) ? $arq : '';
}

/** Salva uma imagem enviada em uploads/ e devolve o caminho relativo (ou null se nada foi enviado). */
function salvar_imagem(string $campo, string $prefixo): ?string
{
    if (empty($_FILES[$campo]['name'])) return null;
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio da imagem.');
    if ($_FILES[$campo]['size'] > 3 * 1024 * 1024) throw new RuntimeException('Imagem muito grande (máx. 3 MB).');
    $info = @getimagesize($_FILES[$campo]['tmp_name']);
    $ext = match ($info['mime'] ?? '') { 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', default => null };
    if (!$ext) throw new RuntimeException('Formato de imagem não aceito (use PNG, JPG, WEBP ou GIF).');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    $arquivo = $prefixo . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], UPLOAD_DIR . '/' . $arquivo)) throw new RuntimeException('Não foi possível salvar a imagem.');
    return 'uploads/' . $arquivo;
}

function apagar_upload(string $caminho): void
{
    if (str_starts_with($caminho, 'uploads/')) @unlink(UPLOAD_DIR . '/' . basename($caminho));
}

function nome_time(array $db, ?string $tid, string $padrao = 'A definir'): string
{
    return $tid !== null && isset($db['times'][$tid]) ? $db['times'][$tid]['nome'] : $padrao;
}

function data_br(string $d): string
{
    if ($d === '') return '';
    $ts = strtotime($d);
    if (!$ts) return e($d);
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return $dias[(int)date('w', $ts)] . ', ' . date('d/m', $ts);
}

function placar(array $j): string
{
    if (!$j['encerrado']) return '<span class="vs">x</span>';
    $s = '<strong>' . (int)$j['gc'] . '</strong> <span class="vs">x</span> <strong>' . (int)$j['gf'] . '</strong>';
    if ($j['pc'] !== null && $j['pf'] !== null && $j['fase'] !== 'grupo' && $j['gc'] === $j['gf']) {
        $s .= '<div class="penaltis">(' . (int)$j['pc'] . ' x ' . (int)$j['pf'] . ' pên.)</div>';
    }
    return $s;
}
