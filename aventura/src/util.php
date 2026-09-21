<?php
/**
 * Funções auxiliares de uso geral.
 */

declare(strict_types=1);

/** Escapa texto para saida em HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Data/hora atual no formato gravado no banco. */
function agora(): string
{
    return date('Y-m-d H:i:s');
}

/** Le um campo do POST já sem espacos nas pontas. */
function post(string $campo, string $padrao = ''): string
{
    $valor = $_POST[$campo] ?? $padrao;
    return is_string($valor) ? trim($valor) : $padrao;
}

/** Le um campo do GET já sem espacos nas pontas. */
function get(string $campo, string $padrao = ''): string
{
    $valor = $_GET[$campo] ?? $padrao;
    return is_string($valor) ? trim($valor) : $padrao;
}

/** Redireciona e encerra. */
function redirecionar(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

/** Guarda um aviso para exibir na próxima página. */
function recado(string $tipo, string $texto): void
{
    $_SESSION['recados'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/**
 * Devolve e limpa os recados pendentes.
 *
 * @return list<array{tipo: string, texto: string}>
 */
function recados(): array
{
    $lista = $_SESSION['recados'] ?? [];
    unset($_SESSION['recados']);
    return $lista;
}

/** Formata 2026-11-13 como 13/11/2026. */
function data_br(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
    return $d === false ? '' : $d->format('d/m/Y');
}

/** Formata 2026-11-13 08:30 como 13/11/2026 08:30. */
function data_hora_br(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    $d = data_hora_valida($iso);
    return $d === null ? '' : $d->format('d/m/Y H:i');
}

/** Interpreta uma data YYYY-MM-DD, devolvendo null se inválida. */
function data_valida(string $iso): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if ($d === false || $d->format('Y-m-d') !== $iso) {
        return null;
    }
    return $d;
}

/** Interpreta uma data/hora, aceitando o formato do input datetime-local. */
function data_hora_valida(string $valor): ?DateTimeImmutable
{
    $valor = str_replace('T', ' ', trim($valor));
    if (strlen($valor) === 16) {
        $valor .= ':00';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor);
    if ($d === false || $d->format('Y-m-d H:i:s') !== $valor) {
        return null;
    }
    return $d;
}

/** Idade completa em anos numa data de referencia. */
function idade_em(string $nascimento, string $referencia): ?int
{
    $nasc = data_valida($nascimento);
    $ref  = data_valida(substr($referencia, 0, 10));
    if ($nasc === null || $ref === null || $nasc > $ref) {
        return null;
    }
    return (int) $nasc->diff($ref)->y;
}

/** Deixa so os digitos de um telefone/documento. */
function somente_digitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

/** Formata (51) 99999-9999 a partir dos digitos. */
function telefone_br(string $valor): string
{
    $d = somente_digitos($valor);
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $valor;
}

/** Gera um protocolo legível, ex.: AE26-7K3QD9. */
function gerar_protocolo(string $prefixo = 'AE26'): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem I, O, 0 e 1
    $sufixo = '';
    for ($i = 0; $i < 6; $i++) {
        $sufixo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $prefixo . '-' . $sufixo;
}

/** IP do visitante, para registro simples de origem. */
function ip_visitante(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
}
