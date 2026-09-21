<?php
/**
 * Pontuação das patrulhas nas atividades pontuáveis.
 */

declare(strict_types=1);

/**
 * Lanca ou atualiza a nota de uma patrulha numa atividade.
 *
 * @return array{ok: bool, erros: list<string>}
 */
function pontuacao_lancar(int $patrulhaId, int $atividadeId, int $pontos, string $observacao = ''): array
{
    $erros = [];

    if (patrulha_por_id($patrulhaId) === null) {
        $erros[] = 'Patrulha não encontrada.';
    }
    $atividade = atividade_por_id($atividadeId);
    if ($atividade === null) {
        $erros[] = 'Atividade não encontrada.';
    } elseif ((int) $atividade['pontuavel'] !== 1) {
        $erros[] = 'Esta atividade não vale pontos.';
    }
    if ($pontos < -1000 || $pontos > 1000) {
        $erros[] = 'A pontuação deve ficar entre -1000 e 1000.';
    }
    if ($erros !== []) {
        return ['ok' => false, 'erros' => $erros];
    }

    db_exec(
        'INSERT INTO pontuacoes (patrulha_id, atividade_id, pontos, observacao, criado_em)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT(patrulha_id, atividade_id)
         DO UPDATE SET pontos = excluded.pontos, observacao = excluded.observacao, criado_em = excluded.criado_em',
        [$patrulhaId, $atividadeId, $pontos, mb_substr(trim($observacao), 0, 200), agora()]
    );

    return ['ok' => true, 'erros' => []];
}

function pontuacao_remover(int $patrulhaId, int $atividadeId): bool
{
    return db_exec(
        'DELETE FROM pontuacoes WHERE patrulha_id = ? AND atividade_id = ?',
        [$patrulhaId, $atividadeId]
    )->rowCount() > 0;
}

/**
 * Notas lancadas, indexadas por patrulha e atividade.
 *
 * @return array<int, array<int, array{pontos: int, observação: string}>>
 */
function pontuacao_matriz(): array
{
    $matriz = [];
    foreach (db_todos('SELECT patrulha_id, atividade_id, pontos, observacao FROM pontuacoes') as $linha) {
        $matriz[(int) $linha['patrulha_id']][(int) $linha['atividade_id']] = [
            'pontos'     => (int) $linha['pontos'],
            'observacao' => (string) $linha['observacao'],
        ];
    }
    return $matriz;
}

/**
 * Classificação geral das patrulhas.
 *
 * Empates recebem a mesma colocação e a próxima posicao pula o equivalente
 * (1, 2, 2, 4), como manda a leitura usual de um quadro de pontuação.
 *
 * @return list<array{id: int, nome: string, cor: string, membros: int,
 *                    total: int, provas: int, posicao: int}>
 */
function pontuacao_ranking(): array
{
    $linhas = db_todos(
        "SELECT p.id, p.nome, p.cor,
                (SELECT COUNT(*) FROM inscricoes i
                  WHERE i.patrulha_id = p.id AND i.status <> 'cancelada') AS membros,
                COALESCE((SELECT SUM(pt.pontos) FROM pontuacoes pt WHERE pt.patrulha_id = p.id), 0) AS total,
                (SELECT COUNT(*) FROM pontuacoes pt WHERE pt.patrulha_id = p.id) AS provas
         FROM patrulhas p"
    );

    usort($linhas, static function (array $a, array $b): int {
        $porTotal = (int) $b['total'] <=> (int) $a['total'];
        if ($porTotal !== 0) {
            return $porTotal;
        }
        // Mais provas disputadas com a mesma soma vem antes; depois, ordem alfabética.
        $porProvas = (int) $b['provas'] <=> (int) $a['provas'];
        return $porProvas !== 0 ? $porProvas : strcasecmp((string) $a['nome'], (string) $b['nome']);
    });

    $ranking = [];
    $posicao = 0;
    $anterior = null;
    foreach ($linhas as $indice => $linha) {
        $chave = [(int) $linha['total'], (int) $linha['provas']];
        if ($chave !== $anterior) {
            $posicao = $indice + 1;
            $anterior = $chave;
        }
        $ranking[] = [
            'id'      => (int) $linha['id'],
            'nome'    => (string) $linha['nome'],
            'cor'     => (string) $linha['cor'],
            'membros' => (int) $linha['membros'],
            'total'   => (int) $linha['total'],
            'provas'  => (int) $linha['provas'],
            'posicao' => $posicao,
        ];
    }

    return $ranking;
}
