<?php
/**
 * Conexao SQLite e criacao do schema.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $caminho = AVENTURA_CONFIG['banco'];
    $pasta = dirname($caminho);
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        throw new RuntimeException('Não foi possível criar a pasta do banco: ' . $pasta);
    }

    $novo = !is_file($caminho);

    $pdo = new PDO('sqlite:' . $caminho, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    if ($novo) {
        @chmod($caminho, 0664);
    }

    db_migrar($pdo);

    return $pdo;
}

/**
 * Aplica o schema. E idempotente, entao pode rodar a cada requisicao.
 */
function db_migrar(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql não encontrado.');
    }
    $pdo->exec($sql);
}

/**
 * Executa uma consulta preparada e devolve o statement.
 *
 * @param array<string|int, mixed> $parametros
 */
function db_exec(string $sql, array $parametros = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($parametros);
    return $st;
}

/**
 * @param array<string|int, mixed> $parametros
 * @return array<string, mixed>|null
 */
function db_um(string $sql, array $parametros = []): ?array
{
    $linha = db_exec($sql, $parametros)->fetch();
    return $linha === false ? null : $linha;
}

/**
 * @param array<string|int, mixed> $parametros
 * @return list<array<string, mixed>>
 */
function db_todos(string $sql, array $parametros = []): array
{
    return db_exec($sql, $parametros)->fetchAll();
}

/**
 * @param array<string|int, mixed> $parametros
 */
function db_valor(string $sql, array $parametros = []): mixed
{
    $valor = db_exec($sql, $parametros)->fetchColumn();
    return $valor === false ? null : $valor;
}
