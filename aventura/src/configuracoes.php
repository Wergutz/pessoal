<?php
/**
 * Dados do evento guardados na tabela configurações.
 *
 * Os valores abaixo são os padroes de fabrica; a equipe ajusta tudo
 * pela tela Configurações do painel.
 */

declare(strict_types=1);

/**
 * Estado de fabrica: nada de dados inventados.
 *
 * O sistema nasce sem evento definido e com as inscrições fechadas. Assim
 * nenhuma pagina publica mostra data, local ou valor que a equipe ainda não
 * decidiu, e ninguem se inscreve num evento que ainda não existe.
 *
 * As faixas etarias vem preenchidas com as dos Escoteiros do Brasil apenas
 * como ponto de partida: sao editaveis, e a conferencia pode ser desligada.
 */
const CONFIG_PADRAO = [
    'evento_nome'         => 'Aventura Escoteira 2026',
    'evento_lema'         => '',
    'evento_inicio'       => '',
    'evento_fim'          => '',
    'evento_local'        => '',
    'evento_cidade'       => '',
    'evento_uf'           => '',
    'evento_valor'        => '',
    'inscricoes_abertas'  => '0',
    'inscricoes_ate'      => '',
    'vagas_total'         => '0',
    'contato_email'       => '',
    'contato_whatsapp'    => '',
    'texto_apresentacao'  => '',
    'texto_o_que_levar'   => '',
    'validar_idade'       => '1',
    'ramos_ativos'        => 'lobinho,escoteiro,senior,pioneiro,adulto',
    'ramo_lobinho_min'    => '6',
    'ramo_lobinho_max'    => '10',
    'ramo_escoteiro_min'  => '11',
    'ramo_escoteiro_max'  => '14',
    'ramo_senior_min'     => '15',
    'ramo_senior_max'     => '17',
    'ramo_pioneiro_min'   => '18',
    'ramo_pioneiro_max'   => '21',
    'ramo_adulto_min'     => '18',
    'ramo_adulto_max'     => '120',
];

/**
 * Configurações que precisam estar preenchidas antes de abrir as inscrições.
 *
 * @var array<string, string>
 */
const CONFIG_OBRIGATORIAS = [
    'evento_inicio'  => 'data de início do evento',
    'evento_fim'     => 'data de término do evento',
    'evento_local'   => 'local do evento',
    'evento_cidade'  => 'cidade',
    'evento_uf'      => 'UF',
    'inscricoes_ate' => 'prazo final de inscrição',
    'contato_email'  => 'e-mail de contato',
];

/** Le uma configuração, caindo no padrao quando ainda não foi salva. */
function config_ler(string $chave): string
{
    $cache = config_todas();
    return $cache[$chave] ?? '';
}

/**
 * Todas as configurações, com os padroes preenchidos.
 *
 * @return array<string, string>
 */
function config_todas(bool $recarregar = false): array
{
    static $cache = null;
    if ($cache !== null && !$recarregar) {
        return $cache;
    }

    $salvas = [];
    foreach (db_todos('SELECT chave, valor FROM configuracoes') as $linha) {
        $salvas[(string) $linha['chave']] = (string) $linha['valor'];
    }

    $cache = array_merge(CONFIG_PADRAO, $salvas);
    return $cache;
}

/** Grava uma configuração. */
function config_gravar(string $chave, string $valor): void
{
    db_exec(
        'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
         ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor',
        [$chave, $valor]
    );
    config_todas(true);
}

/** @param array<string, string> $valores */
function config_gravar_varias(array $valores): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($valores as $chave => $valor) {
            if (array_key_exists($chave, CONFIG_PADRAO)) {
                db_exec(
                    'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                     ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor',
                    [$chave, $valor]
                );
            }
        }
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }
    config_todas(true);
}

/**
 * Situação das inscrições agora: abertas, encerradas, esgotadas ou fechadas.
 *
 * @return array{aberta: bool, motivo: string, vagas_restantes: int}
 */
function inscricoes_situacao(): array
{
    $config = config_todas();
    $total = (int) $config['vagas_total'];
    $ocupadas = inscricoes_contar_ativas();

    // vagas_total = 0 significa sem limite.
    $restantes = $total > 0 ? max(0, $total - $ocupadas) : PHP_INT_MAX;

    // Rede de segurança: enquanto faltar configuração, as inscrições não
    // abrem nem por engano, porque a ficha mostraria dados que não existem.
    if (config_pendencias() !== []) {
        return ['aberta' => false, 'motivo' => 'sem_configuracao', 'vagas_restantes' => $restantes];
    }

    if ($config['inscricoes_abertas'] !== '1') {
        return ['aberta' => false, 'motivo' => 'fechadas', 'vagas_restantes' => $restantes];
    }

    $prazo = data_valida($config['inscricoes_ate']);
    if ($prazo !== null && $prazo->format('Y-m-d') < date('Y-m-d')) {
        return ['aberta' => false, 'motivo' => 'prazo', 'vagas_restantes' => $restantes];
    }

    if ($total > 0 && $restantes === 0) {
        return ['aberta' => false, 'motivo' => 'esgotadas', 'vagas_restantes' => 0];
    }

    return ['aberta' => true, 'motivo' => '', 'vagas_restantes' => $restantes];
}

/** Texto amigável para cada motivo de inscrição fechada. */
function inscricoes_motivo_texto(string $motivo): string
{
    return match ($motivo) {
        'prazo'            => 'O prazo de inscrição terminou em ' . data_br(config_ler('inscricoes_ate')) . '.',
        'esgotadas'        => 'As vagas desta edição se esgotaram. Fale com a equipe para entrar na lista de espera.',
        'fechadas'         => 'As inscrições ainda não foram abertas. Acompanhe os canais do grupo.',
        'sem_configuracao' => 'As informações do evento ainda estão sendo definidas. Em breve abriremos as inscrições.',
        default            => 'Inscrições indisponíveis no momento.',
    };
}

/** Periodo do evento em texto, ex.: 13 a 15/11/2026. */
function evento_periodo(): string
{
    $inicio = data_valida(config_ler('evento_inicio'));
    $fim = data_valida(config_ler('evento_fim'));

    if ($inicio === null) {
        return '';
    }
    if ($fim === null || $inicio->format('Y-m-d') === $fim->format('Y-m-d')) {
        return $inicio->format('d/m/Y');
    }
    if ($inicio->format('m/Y') === $fim->format('m/Y')) {
        return $inicio->format('d') . ' a ' . $fim->format('d/m/Y');
    }
    return $inicio->format('d/m') . ' a ' . $fim->format('d/m/Y');
}

/** Dias que faltam para o evento comecar (negativo depois que passa). */
function evento_dias_restantes(): ?int
{
    $inicio = data_valida(config_ler('evento_inicio'));
    if ($inicio === null) {
        return null;
    }
    $hoje = new DateTimeImmutable(date('Y-m-d'));
    return (int) $hoje->diff($inicio)->format('%r%a');
}

/**
 * O que ainda falta configurar antes de o evento poder receber inscrições.
 *
 * @return array<string, string> chave => nome amigável do que falta
 */
function config_pendencias(): array
{
    $config = config_todas();
    $faltando = [];

    foreach (CONFIG_OBRIGATORIAS as $chave => $descricao) {
        if (trim($config[$chave] ?? '') === '') {
            $faltando[$chave] = $descricao;
        }
    }

    return $faltando;
}

/** true quando o evento ja tem o minimo preenchido. */
function evento_configurado(): bool
{
    return config_pendencias() === [];
}

/**
 * Linha de apoio do cabeçalho: periodo e cidade, sem sobrar separador
 * quando alguma das partes ainda não foi preenchida.
 */
function evento_subtitulo(): string
{
    $config = config_todas();
    $partes = [];

    $periodo = evento_periodo();
    if ($periodo !== '') {
        $partes[] = $periodo;
    }

    $cidade = trim($config['evento_cidade']);
    $uf = trim($config['evento_uf']);
    if ($cidade !== '' && $uf !== '') {
        $partes[] = $cidade . '/' . $uf;
    } elseif ($cidade !== '') {
        $partes[] = $cidade;
    }

    return implode(' · ', $partes);
}

/** Local completo, ex.: "Campo Escola, Santa Cruz do Sul/RS". Vazio se nada definido. */
function evento_local_completo(): string
{
    $config = config_todas();
    $partes = array_filter([
        trim($config['evento_local']),
        trim($config['evento_cidade']) !== '' && trim($config['evento_uf']) !== ''
            ? trim($config['evento_cidade']) . '/' . trim($config['evento_uf'])
            : trim($config['evento_cidade']),
    ], static fn (string $parte): bool => $parte !== '');

    return implode(', ', $partes);
}

/** Vagas como texto, tratando 0 como sem limite definido. */
function vagas_texto(int $restantes): string
{
    return $restantes === PHP_INT_MAX ? 'sem limite' : (string) $restantes;
}

/** true quando a conferência de idade x ramo esta ligada. */
function validar_idade_ligado(): bool
{
    return config_ler('validar_idade') === '1';
}

/**
 * Ramos habilitados, com a faixa etária configurada de cada um.
 *
 * @return array<string, array{rotulo: string, min: int, max: int}>
 */
function ramos_disponiveis(): array
{
    $ativos = array_filter(array_map('trim', explode(',', config_ler('ramos_ativos'))));
    $config = config_todas();
    $lista = [];

    foreach (RAMOS as $codigo => $ramo) {
        if (!in_array($codigo, $ativos, true)) {
            continue;
        }
        $lista[$codigo] = [
            'rotulo' => $ramo['rotulo'],
            'min'    => (int) ($config['ramo_' . $codigo . '_min'] ?? $ramo['min']),
            'max'    => (int) ($config['ramo_' . $codigo . '_max'] ?? $ramo['max']),
        ];
    }

    return $lista;
}
