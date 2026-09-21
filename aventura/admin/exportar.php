<?php
/**
 * Exporta as inscrições filtradas em CSV (separador ponto e virgula,
 * que e o que o Excel em pt-BR abre direto).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

$inscricoes = inscricoes_listar([
    'busca'    => get('busca'),
    'status'   => get('status'),
    'ramo'     => get('ramo'),
    'patrulha' => get('patrulha'),
]);

$arquivo = 'inscricoes-aventura-' . date('Y-m-d-Hi') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: no-store');

$saida = fopen('php://output', 'wb');

// BOM para o Excel reconhecer o UTF-8.
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, [
    'Protocolo', 'Nome', 'Nascimento', 'Idade no evento', 'Ramo', 'Grupo', 'Cidade', 'UF',
    'E-mail', 'Telefone', 'Responsável', 'Telefone do responsável', 'Camisa',
    'Restrição alimentar', 'Observações de saúde', 'Autoriza imagem', 'Situação',
    'Patrulha', 'Enviada em',
], ';', '"', '');

$referencia = config_ler('evento_inicio');

foreach ($inscricoes as $i) {
    fputcsv($saida, [
        $i['protocolo'],
        $i['nome'],
        data_br((string) $i['nascimento']),
        idade_em((string) $i['nascimento'], $referencia) ?? '',
        ramo_rotulo((string) $i['ramo']),
        $i['grupo'],
        $i['cidade'],
        $i['uf'],
        $i['email'],
        telefone_br((string) $i['telefone']),
        $i['responsavel_nome'],
        telefone_br((string) $i['responsavel_telefone']),
        $i['tamanho_camisa'],
        $i['restricao_alimentar'],
        $i['observacoes_saude'],
        (int) $i['autoriza_imagem'] === 1 ? 'Sim' : 'Não',
        status_rotulo((string) $i['status']),
        $i['patrulha_nome'] ?? '',
        data_hora_br((string) $i['criado_em']),
    ], ';', '"', '');
}

fclose($saida);
