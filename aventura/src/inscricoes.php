<?php
/**
 * Regras de inscrição: validação, gravacao e consultas.
 */

declare(strict_types=1);

/** Ramos dos Escoteiros do Brasil com a faixa etária de cada um. */
const RAMOS = [
    'lobinho'   => ['rotulo' => 'Ramo Lobinho',   'min' => 6,  'max' => 10],
    'escoteiro' => ['rotulo' => 'Ramo Escoteiro', 'min' => 11, 'max' => 14],
    'senior'    => ['rotulo' => 'Ramo Sênior',    'min' => 15, 'max' => 17],
    'pioneiro'  => ['rotulo' => 'Ramo Pioneiro',  'min' => 18, 'max' => 21],
    'adulto'    => ['rotulo' => 'Adulto / Escotista', 'min' => 18, 'max' => 120],
];

const STATUS_INSCRICAO = [
    'pendente'   => 'Pendente',
    'confirmada' => 'Confirmada',
    'cancelada'  => 'Cancelada',
];

const TAMANHOS_CAMISA = ['PP', 'P', 'M', 'G', 'GG', 'XG'];

const UFS = [
    'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
    'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
];

/** Rotulo de um ramo, ou o proprio código quando desconhecido. */
function ramo_rotulo(string $ramo): string
{
    return RAMOS[$ramo]['rotulo'] ?? $ramo;
}

function status_rotulo(string $status): string
{
    return STATUS_INSCRICAO[$status] ?? $status;
}

/**
 * Válida os dados de uma inscrição.
 *
 * Devolve os campos já normalizados junto com a lista de erros, para que
 * o formulario possa ser reexibido sem perder o que a pessoa digitou.
 *
 * @param array<string, mixed> $dados
 * @return array{dados: array<string, mixed>, erros: array<string, string>}
 */
function inscricao_validar(array $dados): array
{
    $erros = [];

    $limpo = [
        'nome'                 => trim((string) ($dados['nome'] ?? '')),
        'nascimento'           => trim((string) ($dados['nascimento'] ?? '')),
        'ramo'                 => trim((string) ($dados['ramo'] ?? '')),
        'grupo'                => trim((string) ($dados['grupo'] ?? '')),
        'cidade'               => trim((string) ($dados['cidade'] ?? '')),
        'uf'                   => strtoupper(trim((string) ($dados['uf'] ?? ''))),
        'email'                => mb_strtolower(trim((string) ($dados['email'] ?? ''))),
        'telefone'             => somente_digitos((string) ($dados['telefone'] ?? '')),
        'responsavel_nome'     => trim((string) ($dados['responsavel_nome'] ?? '')),
        'responsavel_telefone' => somente_digitos((string) ($dados['responsavel_telefone'] ?? '')),
        'tamanho_camisa'       => strtoupper(trim((string) ($dados['tamanho_camisa'] ?? 'M'))),
        'restricao_alimentar'  => trim((string) ($dados['restricao_alimentar'] ?? '')),
        'observacoes_saude'    => trim((string) ($dados['observacoes_saude'] ?? '')),
        'autoriza_imagem'      => !empty($dados['autoriza_imagem']) ? 1 : 0,
    ];

    if (mb_strlen($limpo['nome']) < 5) {
        $erros['nome'] = 'Informe o nome completo.';
    } elseif (mb_strlen($limpo['nome']) > 120) {
        $erros['nome'] = 'Nome muito longo (máximo 120 caracteres).';
    }

    $nascimento = data_valida($limpo['nascimento']);
    if ($nascimento === null) {
        $erros['nascimento'] = 'Informe uma data de nascimento válida.';
    } elseif ($nascimento->format('Y-m-d') >= date('Y-m-d')) {
        $erros['nascimento'] = 'A data de nascimento precisa estar no passado.';
    }

    if (!isset(RAMOS[$limpo['ramo']])) {
        $erros['ramo'] = 'Escolha o ramo.';
    } elseif ($nascimento !== null) {
        $idade = idade_em($limpo['nascimento'], config_ler('evento_inicio'));
        $faixa = RAMOS[$limpo['ramo']];
        if ($idade !== null && ($idade < $faixa['min'] || $idade > $faixa['max'])) {
            $erros['ramo'] = sprintf(
                'Com %d anos na data do evento, a idade não corresponde ao %s (%d a %d anos).',
                $idade,
                $faixa['rotulo'],
                $faixa['min'],
                $faixa['max']
            );
        }
    }

    if (mb_strlen($limpo['grupo']) < 3) {
        $erros['grupo'] = 'Informe o grupo escoteiro.';
    }
    if (mb_strlen($limpo['cidade']) < 2) {
        $erros['cidade'] = 'Informe a cidade.';
    }
    if (!in_array($limpo['uf'], UFS, true)) {
        $erros['uf'] = 'Escolha a UF.';
    }
    if (!filter_var($limpo['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if (strlen($limpo['telefone']) < 10 || strlen($limpo['telefone']) > 11) {
        $erros['telefone'] = 'Informe o telefone com DDD.';
    }
    if (!in_array($limpo['tamanho_camisa'], TAMANHOS_CAMISA, true)) {
        $limpo['tamanho_camisa'] = 'M';
    }

    // Menores de idade na data do evento precisam de responsável.
    $idade_evento = $nascimento !== null
        ? idade_em($limpo['nascimento'], config_ler('evento_inicio'))
        : null;

    if ($idade_evento !== null && $idade_evento < 18) {
        if (mb_strlen($limpo['responsavel_nome']) < 5) {
            $erros['responsavel_nome'] = 'Participante menor de idade: informe o responsável.';
        }
        $tel = strlen($limpo['responsavel_telefone']);
        if ($tel < 10 || $tel > 11) {
            $erros['responsavel_telefone'] = 'Informe o telefone do responsável com DDD.';
        }
    }

    foreach (['restricao_alimentar', 'observacoes_saude'] as $campo) {
        if (mb_strlen($limpo[$campo]) > 500) {
            $erros[$campo] = 'Texto muito longo (máximo 500 caracteres).';
        }
    }

    return ['dados' => $limpo, 'erros' => $erros];
}

/**
 * Grava uma inscrição já validada e devolve o protocolo gerado.
 *
 * @param array<string, mixed> $dados
 */
function inscricao_criar(array $dados): string
{
    $agora = agora();

    // Colisao de protocolo e improvavel, mas o retry deixa a gravacao previsível.
    for ($tentativa = 0; $tentativa < 5; $tentativa++) {
        $protocolo = gerar_protocolo();
        try {
            db_exec(
                'INSERT INTO inscricoes (
                    protocolo, nome, nascimento, ramo, grupo, cidade, uf, email, telefone,
                    responsavel_nome, responsavel_telefone, tamanho_camisa,
                    restricao_alimentar, observacoes_saude, autoriza_imagem,
                    status, ip, criado_em, atualizado_em
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $protocolo,
                    $dados['nome'],
                    $dados['nascimento'],
                    $dados['ramo'],
                    $dados['grupo'],
                    $dados['cidade'],
                    $dados['uf'],
                    $dados['email'],
                    $dados['telefone'],
                    $dados['responsavel_nome'],
                    $dados['responsavel_telefone'],
                    $dados['tamanho_camisa'],
                    $dados['restricao_alimentar'],
                    $dados['observacoes_saude'],
                    $dados['autoriza_imagem'],
                    'pendente',
                    ip_visitante(),
                    $agora,
                    $agora,
                ]
            );
            return $protocolo;
        } catch (PDOException $erro) {
            if (!str_contains($erro->getMessage(), 'UNIQUE constraint failed: inscricoes.protocolo')) {
                throw $erro;
            }
        }
    }

    throw new RuntimeException('Não foi possível gerar um protocolo único.');
}

/** Evita que a mesma pessoa se inscreva duas vezes por engano. */
function inscricao_duplicada(string $nome, string $nascimento): bool
{
    return db_valor(
        "SELECT 1 FROM inscricoes
         WHERE lower(nome) = lower(?) AND nascimento = ? AND status <> 'cancelada'",
        [$nome, $nascimento]
    ) !== null;
}

/** @return array<string, mixed>|null */
function inscricao_por_protocolo(string $protocolo): ?array
{
    return db_um(
        'SELECT i.*, p.nome AS patrulha_nome
         FROM inscricoes i
         LEFT JOIN patrulhas p ON p.id = i.patrulha_id
         WHERE upper(i.protocolo) = upper(?)',
        [trim($protocolo)]
    );
}

/** @return array<string, mixed>|null */
function inscricao_por_id(int $id): ?array
{
    return db_um(
        'SELECT i.*, p.nome AS patrulha_nome
         FROM inscricoes i
         LEFT JOIN patrulhas p ON p.id = i.patrulha_id
         WHERE i.id = ?',
        [$id]
    );
}

/**
 * Lista inscrições com filtros opcionais.
 *
 * @param array{busca?: string, status?: string, ramo?: string, patrulha?: string} $filtros
 * @return list<array<string, mixed>>
 */
function inscricoes_listar(array $filtros = []): array
{
    $sql = 'SELECT i.*, p.nome AS patrulha_nome
            FROM inscricoes i
            LEFT JOIN patrulhas p ON p.id = i.patrulha_id
            WHERE 1 = 1';
    $parametros = [];

    $busca = trim((string) ($filtros['busca'] ?? ''));
    if ($busca !== '') {
        $sql .= ' AND (i.nome LIKE ? OR i.protocolo LIKE ? OR i.grupo LIKE ? OR i.email LIKE ?)';
        $curinga = '%' . $busca . '%';
        array_push($parametros, $curinga, $curinga, $curinga, $curinga);
    }

    $status = (string) ($filtros['status'] ?? '');
    if (isset(STATUS_INSCRICAO[$status])) {
        $sql .= ' AND i.status = ?';
        $parametros[] = $status;
    }

    $ramo = (string) ($filtros['ramo'] ?? '');
    if (isset(RAMOS[$ramo])) {
        $sql .= ' AND i.ramo = ?';
        $parametros[] = $ramo;
    }

    $patrulha = (string) ($filtros['patrulha'] ?? '');
    if ($patrulha === 'sem') {
        $sql .= ' AND i.patrulha_id IS NULL';
    } elseif (ctype_digit($patrulha)) {
        $sql .= ' AND i.patrulha_id = ?';
        $parametros[] = (int) $patrulha;
    }

    $sql .= ' ORDER BY i.criado_em DESC, i.id DESC';

    return db_todos($sql, $parametros);
}

/** Inscrições que ocupam vaga (tudo que não foi cancelado). */
function inscricoes_contar_ativas(): int
{
    return (int) db_valor("SELECT COUNT(*) FROM inscricoes WHERE status <> 'cancelada'");
}

/** Muda o status de uma inscrição. */
function inscricao_mudar_status(int $id, string $status): bool
{
    if (!isset(STATUS_INSCRICAO[$status])) {
        return false;
    }
    $st = db_exec(
        'UPDATE inscricoes SET status = ?, atualizado_em = ? WHERE id = ?',
        [$status, agora(), $id]
    );
    return $st->rowCount() > 0;
}

/** Move a inscrição para uma patrulha (ou tira dela, com null). */
function inscricao_definir_patrulha(int $id, ?int $patrulhaId): bool
{
    if ($patrulhaId !== null && db_valor('SELECT 1 FROM patrulhas WHERE id = ?', [$patrulhaId]) === null) {
        return false;
    }
    $st = db_exec(
        'UPDATE inscricoes SET patrulha_id = ?, atualizado_em = ? WHERE id = ?',
        [$patrulhaId, agora(), $id]
    );
    return $st->rowCount() > 0;
}

function inscricao_excluir(int $id): bool
{
    return db_exec('DELETE FROM inscricoes WHERE id = ?', [$id])->rowCount() > 0;
}

/**
 * Numeros do painel.
 *
 * @return array{total: int, por_status: array<string, int>, por_ramo: array<string, int>,
 *               grupos: int, camisas: array<string, int>, restrições: int, sem_patrulha: int}
 */
function inscricoes_resumo(): array
{
    $por_status = array_fill_keys(array_keys(STATUS_INSCRICAO), 0);
    foreach (db_todos('SELECT status, COUNT(*) AS total FROM inscricoes GROUP BY status') as $linha) {
        $por_status[(string) $linha['status']] = (int) $linha['total'];
    }

    $por_ramo = array_fill_keys(array_keys(RAMOS), 0);
    foreach (
        db_todos("SELECT ramo, COUNT(*) AS total FROM inscricoes
                  WHERE status <> 'cancelada' GROUP BY ramo") as $linha
    ) {
        $por_ramo[(string) $linha['ramo']] = (int) $linha['total'];
    }

    $camisas = array_fill_keys(TAMANHOS_CAMISA, 0);
    foreach (
        db_todos("SELECT tamanho_camisa, COUNT(*) AS total FROM inscricoes
                  WHERE status <> 'cancelada' GROUP BY tamanho_camisa") as $linha
    ) {
        $camisas[(string) $linha['tamanho_camisa']] = (int) $linha['total'];
    }

    return [
        'total'        => array_sum($por_status),
        'por_status'   => $por_status,
        'por_ramo'     => $por_ramo,
        'grupos'       => (int) db_valor("SELECT COUNT(DISTINCT lower(grupo)) FROM inscricoes
                                          WHERE status <> 'cancelada'"),
        'camisas'      => $camisas,
        'restricoes'   => (int) db_valor("SELECT COUNT(*) FROM inscricoes
                                          WHERE status <> 'cancelada' AND restricao_alimentar <> ''"),
        'sem_patrulha' => (int) db_valor("SELECT COUNT(*) FROM inscricoes
                                          WHERE status = 'confirmada' AND patrulha_id IS NULL"),
    ];
}
