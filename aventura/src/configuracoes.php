<?php
/**
 * Dados do evento guardados na tabela configurações.
 *
 * Os valores abaixo são os padroes de fabrica; a equipe ajusta tudo
 * pela tela Configurações do painel.
 */

declare(strict_types=1);

const CONFIG_PADRAO = [
    'evento_nome'         => 'Aventura Escoteira 2026',
    'evento_lema'         => 'Sempre Alerta para a próxima trilha',
    'evento_inicio'       => '2026-11-13',
    'evento_fim'          => '2026-11-15',
    'evento_local'        => 'Campo Escola Regional',
    'evento_cidade'       => 'Santa Cruz do Sul',
    'evento_uf'           => 'RS',
    'evento_valor'        => '120,00',
    'inscricoes_abertas'  => '1',
    'inscricoes_ate'      => '2026-10-30',
    'vagas_total'         => '150',
    'contato_email'       => 'contato@eletronicagw.com.br',
    'contato_whatsapp'    => '',
    'texto_apresentacao'  => 'Três dias de campo, trilha, especialidades e muita vida ao ar livre. '
                           . 'A Aventura Escoteira reune patrulhas de toda a região para desafios em equipe, '
                           . 'fogo de conselho e o melhor do método escoteiro.',
    'texto_o_que_levar'   => 'Uniforme completo, mochila cargueira, saco de dormir, isolante térmico, '
                           . 'lanterna, caneco e prato, capa de chuva, repelente e protetor solar.',
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
    $restantes = max(0, $total - $ocupadas);

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
        'prazo'      => 'O prazo de inscrição terminou em ' . data_br(config_ler('inscricoes_ate')) . '.',
        'esgotadas'  => 'As vagas desta edição se esgotaram. Fale com a equipe para entrar na lista de espera.',
        'fechadas'   => 'As inscrições ainda não foram abertas. Acompanhe os canais do grupo.',
        default      => 'Inscrições indisponíveis no momento.',
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
