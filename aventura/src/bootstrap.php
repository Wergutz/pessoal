<?php
/**
 * Ponto de entrada comum a todas as páginas.
 *
 * Carrega configuração, abre a sessão, prepara o banco e disponibiliza
 * os modulos da aplicacao.
 */

declare(strict_types=1);

if (defined('AVENTURA_INICIADA')) {
    return;
}
define('AVENTURA_INICIADA', true);

define('AVENTURA_RAIZ', dirname(__DIR__));

$padrao = [
    'banco' => AVENTURA_RAIZ . '/data/aventura.sqlite',
    'fuso'  => 'America/Sao_Paulo',
    'debug' => false,
];

$local = __DIR__ . '/config.local.php';
$config = is_file($local) ? array_merge($padrao, (array) require $local) : $padrao;

define('AVENTURA_CONFIG', $config);

date_default_timezone_set($config['fuso']);
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', $config['debug'] ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('aventura_sessao');
    session_start();
}

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/configuracoes.php';
require_once __DIR__ . '/inscricoes.php';
require_once __DIR__ . '/patrulhas.php';
require_once __DIR__ . '/atividades.php';
require_once __DIR__ . '/pontuacao.php';
require_once __DIR__ . '/layout.php';
