<?php
/**
 * Programação do evento.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $dados
 * @return array{ok: bool, erros: list<string>, id?: int}
 */
function atividade_salvar(array $dados, ?int $id = null): array
{
    $erros = [];

    $titulo    = trim((string) ($dados['titulo'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $local     = trim((string) ($dados['local'] ?? ''));
    $pontuavel = !empty($dados['pontuavel']) ? 1 : 0;
    $publicada = !empty($dados['publicada']) ? 1 : 0;

    if (mb_strlen($titulo) < 3) {
        $erros[] = 'Informe o titulo da atividade.';
    }

    $inicio = data_hora_valida((string) ($dados['inicio'] ?? ''));
    $fim    = data_hora_valida((string) ($dados['fim'] ?? ''));

    if ($inicio === null) {
        $erros[] = 'Informe a data e hora de início.';
    }
    if ($fim === null) {
        $erros[] = 'Informe a data e hora de término.';
    }
    if ($inicio !== null && $fim !== null && $fim <= $inicio) {
        $erros[] = 'O término precisa ser depois do início.';
    }
    if ($erros !== []) {
        return ['ok' => false, 'erros' => $erros];
    }

    $valores = [
        $titulo,
        mb_substr($descricao, 0, 1000),
        mb_substr($local, 0, 120),
        $inicio->format('Y-m-d H:i'),
        $fim->format('Y-m-d H:i'),
        $pontuavel,
        $publicada,
    ];

    if ($id === null) {
        $valores[] = agora();
        db_exec(
            'INSERT INTO atividades (titulo, descricao, local, inicio, fim, pontuavel, publicada, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            $valores
        );
        return ['ok' => true, 'erros' => [], 'id' => (int) db()->lastInsertId()];
    }

    $valores[] = $id;
    db_exec(
        'UPDATE atividades
            SET titulo = ?, descricao = ?, local = ?, inicio = ?, fim = ?, pontuavel = ?, publicada = ?
          WHERE id = ?',
        $valores
    );
    return ['ok' => true, 'erros' => [], 'id' => $id];
}

function atividade_excluir(int $id): bool
{
    return db_exec('DELETE FROM atividades WHERE id = ?', [$id])->rowCount() > 0;
}

/** @return array<string, mixed>|null */
function atividade_por_id(int $id): ?array
{
    return db_um('SELECT * FROM atividades WHERE id = ?', [$id]);
}

/** @return list<array<string, mixed>> */
function atividades_listar(bool $somentePublicadas = false): array
{
    $sql = 'SELECT * FROM atividades';
    if ($somentePublicadas) {
        $sql .= ' WHERE publicada = 1';
    }
    $sql .= ' ORDER BY inicio, id';

    return db_todos($sql);
}

/** @return list<array<string, mixed>> */
function atividades_pontuaveis(): array
{
    return db_todos('SELECT * FROM atividades WHERE pontuavel = 1 ORDER BY inicio, id');
}

/**
 * Programação agrupada por dia, pronta para a tela pública.
 *
 * @return array<string, list<array<string, mixed>>> chave = YYYY-MM-DD
 */
function atividades_por_dia(bool $somentePublicadas = true): array
{
    $agenda = [];
    foreach (atividades_listar($somentePublicadas) as $atividade) {
        $dia = substr((string) $atividade['inicio'], 0, 10);
        $agenda[$dia][] = $atividade;
    }
    return $agenda;
}

/** Hora no formato 08:30 a partir de 2026-11-13 08:30. */
function hora_de(string $dataHora): string
{
    return substr($dataHora, 11, 5);
}

/** Nome do dia da semana em portugues. */
function dia_semana_br(string $iso): string
{
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $d = data_valida(substr($iso, 0, 10));
    return $d === null ? '' : $dias[(int) $d->format('w')];
}
