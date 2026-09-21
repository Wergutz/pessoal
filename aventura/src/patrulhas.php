<?php
/**
 * Patrulhas do evento e distribuição dos inscritos.
 */

declare(strict_types=1);

/** Sugestoes de nome usadas na criacao rapida de patrulhas. */
const PATRULHAS_SUGERIDAS = [
    'Lobo', 'Falcão', 'Onça', 'Tucano', 'Jaguar', 'Águia',
    'Tatu', 'Arara', 'Sucuri', 'Coruja', 'Capivara', 'Bugio',
];

/**
 * @return array{ok: bool, erros: list<string>, id?: int}
 */
function patrulha_criar(string $nome, string $cor = '#2f6f4f', string $grito = '', int $vagas = 8): array
{
    $nome = trim($nome);
    $erros = [];

    if (mb_strlen($nome) < 2) {
        $erros[] = 'Informe o nome da patrulha.';
    } elseif (mb_strlen($nome) > 40) {
        $erros[] = 'Nome de patrulha muito longo.';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
        $cor = '#2f6f4f';
    }
    if ($vagas < 1 || $vagas > 40) {
        $erros[] = 'Número de vagas deve ficar entre 1 e 40.';
    }
    if ($erros === [] && db_valor('SELECT 1 FROM patrulhas WHERE lower(nome) = lower(?)', [$nome]) !== null) {
        $erros[] = 'Já existe uma patrulha com esse nome.';
    }
    if ($erros !== []) {
        return ['ok' => false, 'erros' => $erros];
    }

    db_exec(
        'INSERT INTO patrulhas (nome, cor, grito, vagas, criado_em) VALUES (?, ?, ?, ?, ?)',
        [$nome, $cor, mb_substr(trim($grito), 0, 160), $vagas, agora()]
    );

    return ['ok' => true, 'erros' => [], 'id' => (int) db()->lastInsertId()];
}

function patrulha_atualizar(int $id, string $nome, string $cor, string $grito, int $vagas): bool
{
    $nome = trim($nome);
    if (mb_strlen($nome) < 2 || $vagas < 1 || $vagas > 40) {
        return false;
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
        $cor = '#2f6f4f';
    }
    $conflito = db_valor(
        'SELECT 1 FROM patrulhas WHERE lower(nome) = lower(?) AND id <> ?',
        [$nome, $id]
    );
    if ($conflito !== null) {
        return false;
    }

    return db_exec(
        'UPDATE patrulhas SET nome = ?, cor = ?, grito = ?, vagas = ? WHERE id = ?',
        [$nome, $cor, mb_substr(trim($grito), 0, 160), $vagas, $id]
    )->rowCount() > 0;
}

/** Remove a patrulha; os inscritos ficam sem patrulha (ON DELETE SET NULL). */
function patrulha_excluir(int $id): bool
{
    return db_exec('DELETE FROM patrulhas WHERE id = ?', [$id])->rowCount() > 0;
}

/** @return array<string, mixed>|null */
function patrulha_por_id(int $id): ?array
{
    return db_um('SELECT * FROM patrulhas WHERE id = ?', [$id]);
}

/**
 * Patrulhas com quantos confirmados cada uma já tem.
 *
 * @return list<array<string, mixed>>
 */
function patrulhas_listar(): array
{
    return db_todos(
        "SELECT p.*,
                (SELECT COUNT(*) FROM inscricoes i
                  WHERE i.patrulha_id = p.id AND i.status <> 'cancelada') AS membros
         FROM patrulhas p
         ORDER BY p.nome"
    );
}

/** @return list<array<string, mixed>> */
function patrulha_membros(int $id): array
{
    return db_todos(
        "SELECT * FROM inscricoes
         WHERE patrulha_id = ? AND status <> 'cancelada'
         ORDER BY nome",
        [$id]
    );
}

/**
 * Distribui automaticamente os confirmados sem patrulha.
 *
 * Alterna as patrulhas comecando pela mais vazia e mantem cada inscrito
 * separado de quem veio do mesmo grupo escoteiro sempre que possível,
 * que e o que se espera de uma patrulha de evento regional.
 *
 * @return int quantos inscritos foram distribuidos
 */
function patrulhas_distribuir(): int
{
    $patrulhas = patrulhas_listar();
    if ($patrulhas === []) {
        return 0;
    }

    $ocupacao = [];
    $grupos = [];
    foreach ($patrulhas as $p) {
        $id = (int) $p['id'];
        $ocupacao[$id] = (int) $p['membros'];
        $grupos[$id] = [];
        foreach (patrulha_membros($id) as $membro) {
            $grupos[$id][] = mb_strtolower((string) $membro['grupo']);
        }
    }

    $pendentes = db_todos(
        "SELECT id, grupo FROM inscricoes
         WHERE status = 'confirmada' AND patrulha_id IS NULL
         ORDER BY criado_em"
    );
    if ($pendentes === []) {
        return 0;
    }

    $vagas = [];
    foreach ($patrulhas as $p) {
        $vagas[(int) $p['id']] = (int) $p['vagas'];
    }

    $movidos = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($pendentes as $inscrito) {
            $grupo = mb_strtolower((string) $inscrito['grupo']);

            // Candidatas: as com menos gente; entre elas, as que ainda não
            // tem ninguem desse grupo escoteiro.
            $ids = array_keys($ocupacao);
            usort($ids, static function (int $a, int $b) use ($ocupacao, $grupos, $grupo, $vagas): int {
                $cheiaA = $ocupacao[$a] >= $vagas[$a] ? 1 : 0;
                $cheiaB = $ocupacao[$b] >= $vagas[$b] ? 1 : 0;
                if ($cheiaA !== $cheiaB) {
                    return $cheiaA <=> $cheiaB;
                }
                $repeteA = in_array($grupo, $grupos[$a], true) ? 1 : 0;
                $repeteB = in_array($grupo, $grupos[$b], true) ? 1 : 0;
                if ($repeteA !== $repeteB) {
                    return $repeteA <=> $repeteB;
                }
                return $ocupacao[$a] <=> $ocupacao[$b];
            });

            $escolhida = $ids[0];
            db_exec(
                'UPDATE inscricoes SET patrulha_id = ?, atualizado_em = ? WHERE id = ?',
                [$escolhida, agora(), (int) $inscrito['id']]
            );
            $ocupacao[$escolhida]++;
            $grupos[$escolhida][] = $grupo;
            $movidos++;
        }
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    return $movidos;
}
