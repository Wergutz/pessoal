<?php
/**
 * Testes da Aventura Escoteira 2026.
 *
 * Roda sem dependencia externa, em banco temporario:
 *     php testes/executar.php
 */

declare(strict_types=1);

$bancoTeste = sys_get_temp_dir() . '/aventura-teste-' . getmypid() . '.sqlite';
foreach ([$bancoTeste, $bancoTeste . '-wal', $bancoTeste . '-shm'] as $lixo) {
    @unlink($lixo);
}

// bootstrap.php le a configuração de src/config.local.php. Os testes sempre
// rodam em banco próprio: se houver uma configuração local do desenvolvedor,
// ela e guardada de lado e devolvida no fim. Assim o teste nunca escreve no
// banco de trabalho nem le dados que não criou.
$configLocal = dirname(__DIR__) . '/src/config.local.php';
$configGuardada = is_file($configLocal) ? (string) file_get_contents($configLocal) : null;

file_put_contents(
    $configLocal,
    "<?php\n\nreturn " . var_export([
        'banco' => $bancoTeste,
        'fuso'  => 'America/Sao_Paulo',
        'debug' => true,
    ], true) . ";\n"
);

// Devolve a configuração do desenvolvedor mesmo se um teste abortar no meio.
register_shutdown_function(static function () use ($bancoTeste, $configLocal, $configGuardada): void {
    foreach ([$bancoTeste, $bancoTeste . '-wal', $bancoTeste . '-shm'] as $lixo) {
        @unlink($lixo);
    }
    if ($configGuardada === null) {
        @unlink($configLocal);
        return;
    }
    file_put_contents($configLocal, $configGuardada);
});

require dirname(__DIR__) . '/src/bootstrap.php';

// Confere que o bootstrap realmente pegou o banco de teste.
if (AVENTURA_CONFIG['banco'] !== $bancoTeste) {
    fwrite(STDERR, "Os testes não conseguiram isolar o banco. Abortando.\n");
    exit(1);
}

// ---------------------------------------------------------------- runner ---

$GLOBALS['passou'] = 0;
$GLOBALS['falhou'] = 0;
$GLOBALS['grupo'] = '';

function grupo(string $nome): void
{
    $GLOBALS['grupo'] = $nome;
    echo "\n\033[1m" . $nome . "\033[0m\n";
}

function verificar(string $descricao, bool $condicao, string $detalhe = ''): void
{
    if ($condicao) {
        $GLOBALS['passou']++;
        echo "  \033[32mok\033[0m   " . $descricao . "\n";
        return;
    }
    $GLOBALS['falhou']++;
    echo "  \033[31mFALHA\033[0m " . $descricao . ($detalhe !== '' ? "\n        " . $detalhe : '') . "\n";
}

function igual(string $descricao, mixed $esperado, mixed $obtido): void
{
    verificar(
        $descricao,
        $esperado === $obtido,
        'esperado: ' . var_export($esperado, true) . ' | obtido: ' . var_export($obtido, true)
    );
}

/** Ficha válida usada como base nos testes de inscrição. */
function ficha(array $sobrescrever = []): array
{
    return array_merge([
        'nome'                 => 'Ana Beatriz Souza',
        'nascimento'           => '2012-04-10',
        'ramo'                 => 'escoteiro',
        'grupo'                => '12/RS Tape',
        'cidade'               => 'Santa Cruz do Sul',
        'uf'                   => 'RS',
        'email'                => 'ana@example.com',
        'telefone'             => '(51) 99999-8888',
        'responsavel_nome'     => 'Carla Souza',
        'responsavel_telefone' => '51988887777',
        'tamanho_camisa'       => 'M',
        'restricao_alimentar'  => '',
        'observacoes_saude'    => '',
        'autoriza_imagem'      => '1',
    ], $sobrescrever);
}

// ------------------------------------------------------------- utilidades ---

grupo('Utilidades');

igual('data_br formata para o padrao brasileiro', '13/11/2026', data_br('2026-11-13'));
igual('data_br aceita data com hora', '13/11/2026', data_br('2026-11-13 08:00'));
igual('data_br devolve vazio para lixo', '', data_br('nao-e-data'));
igual('data_hora_br formata data e hora', '13/11/2026 08:30', data_hora_br('2026-11-13 08:30'));

verificar('data_valida aceita data real', data_valida('2026-02-28') !== null);
verificar('data_valida recusa 30 de fevereiro', data_valida('2026-02-30') === null);
verificar('data_valida recusa formato brasileiro', data_valida('28/02/2026') === null);

verificar('data_hora_valida aceita o formato do datetime-local',
    data_hora_valida('2026-11-13T08:30') !== null);
verificar('data_hora_valida recusa hora impossível',
    data_hora_valida('2026-11-13 25:00') === null);

igual('idade_em conta o aniversário já ocorrido', 14, idade_em('2012-04-10', '2026-11-13'));
igual('idade_em conta o aniversário ainda por vir', 13, idade_em('2012-04-10', '2026-03-13'));
igual('idade_em no dia exato do aniversário', 14, idade_em('2012-04-10', '2026-04-10'));
verificar('idade_em recusa nascimento no futuro', idade_em('2030-01-01', '2026-11-13') === null);

igual('telefone_br formata celular', '(51) 99999-8888', telefone_br('51999998888'));
igual('telefone_br formata fixo', '(51) 3333-4444', telefone_br('5133334444'));
igual('telefone_br devolve o original quando não reconhece', '123', telefone_br('123'));
igual('somente_digitos limpa a mascara', '51999998888', somente_digitos('(51) 99999-8888'));

$protocolo = gerar_protocolo();
verificar('protocolo segue o formato AE26-XXXXXX',
    preg_match('/^AE26-[A-Z2-9]{6}$/', $protocolo) === 1, $protocolo);
verificar('protocolo evita caracteres ambiguos',
    preg_match('/[IO01]/', substr($protocolo, 5)) === 0, $protocolo);
verificar('protocolos gerados não se repetem em 200 tentativas',
    count(array_unique(array_map(static fn () => gerar_protocolo(), range(1, 200)))) === 200);

igual('e() escapa aspas e tags',
    '&lt;b&gt;&quot;oi&quot;&lt;/b&gt;', e('<b>"oi"</b>'));

// ---------------------------------------------------------- configurações ---

grupo('Configurações do evento');

igual('config_ler cai no padrao quando nada foi salvo',
    'Aventura Escoteira 2026', config_ler('evento_nome'));

config_gravar('evento_nome', 'Aventura Escoteira 2026 - Regional');
igual('config_gravar persiste e inválida o cache',
    'Aventura Escoteira 2026 - Regional', config_ler('evento_nome'));

config_gravar_varias(['evento_inicio' => '2026-11-13', 'evento_fim' => '2026-11-15']);
igual('evento_periodo resume o intervalo no mesmo mes', '13 a 15/11/2026', evento_periodo());

config_gravar_varias(['evento_inicio' => '2026-10-30', 'evento_fim' => '2026-11-01']);
igual('evento_periodo atravessa a virada do mes', '30/10 a 01/11/2026', evento_periodo());

config_gravar_varias(['evento_inicio' => '2026-11-13', 'evento_fim' => '2026-11-13']);
igual('evento_periodo de um único dia', '13/11/2026', evento_periodo());

config_gravar_varias(['evento_inicio' => '2026-11-13', 'evento_fim' => '2026-11-15']);

config_gravar_varias(['chave_inventada' => 'x']);
verificar('config_gravar_varias ignora chaves desconhecidas',
    !array_key_exists('chave_inventada', config_todas(true)));

// ------------------------------------------------------- validar inscrição ---

grupo('Validação da inscrição');

config_gravar_varias([
    'inscricoes_abertas' => '1',
    'inscricoes_ate'     => '2026-12-31',
    'vagas_total'        => '150',
    // As obrigatórias precisam estar preenchidas, senão as inscrições nem abrem.
    'evento_local'       => 'Campo Escola Regional',
    'evento_cidade'      => 'Santa Cruz do Sul',
    'evento_uf'          => 'RS',
    'contato_email'      => 'contato@example.com',
]);

$v = inscricao_validar(ficha());
igual('ficha completa passa sem erros', [], $v['erros']);
igual('telefone e normalizado para digitos', '51999998888', $v['dados']['telefone']);
igual('e-mail e normalizado para minusculas', 'ana@example.com',
    inscricao_validar(ficha(['email' => 'Ana@Example.COM']))['dados']['email']);
igual('UF e normalizada para maiusculas', 'RS',
    inscricao_validar(ficha(['uf' => 'rs']))['dados']['uf']);
igual('autoriza_imagem vira inteiro', 1, $v['dados']['autoriza_imagem']);

verificar('nome curto e recusado',
    isset(inscricao_validar(ficha(['nome' => 'Ana']))['erros']['nome']));
verificar('e-mail inválido e recusado',
    isset(inscricao_validar(ficha(['email' => 'ana@']))['erros']['email']));
verificar('telefone curto e recusado',
    isset(inscricao_validar(ficha(['telefone' => '9999']))['erros']['telefone']));
verificar('UF inexistente e recusada',
    isset(inscricao_validar(ficha(['uf' => 'XX']))['erros']['uf']));
verificar('nascimento no futuro e recusado',
    isset(inscricao_validar(ficha(['nascimento' => '2030-01-01']))['erros']['nascimento']));
verificar('ramo vazio e recusado',
    isset(inscricao_validar(ficha(['ramo' => '']))['erros']['ramo']));
verificar('tamanho de camisa desconhecido cai no M',
    inscricao_validar(ficha(['tamanho_camisa' => 'XXG']))['dados']['tamanho_camisa'] === 'M');
verificar('texto acima de 500 caracteres e recusado',
    isset(inscricao_validar(ficha(['restricao_alimentar' => str_repeat('a', 501)]))['erros']['restricao_alimentar']));

// Faixa etária: no evento de 13/11/2026 quem nasceu em 2012 tem 14 anos.
verificar('idade fora da faixa do ramo e recusada',
    isset(inscricao_validar(ficha(['ramo' => 'lobinho']))['erros']['ramo']));
igual('idade dentro da faixa do ramo passa', [],
    inscricao_validar(ficha(['nascimento' => '2017-01-05', 'ramo' => 'lobinho']))['erros']);

// Responsável: obrigatório so para quem for menor no início do evento.
$semResponsavel = ficha(['responsavel_nome' => '', 'responsavel_telefone' => '']);
verificar('menor de idade exige nome do responsável',
    isset(inscricao_validar($semResponsavel)['erros']['responsavel_nome']));
verificar('menor de idade exige telefone do responsável',
    isset(inscricao_validar($semResponsavel)['erros']['responsavel_telefone']));

$adulto = ficha([
    'nascimento' => '1990-05-20', 'ramo' => 'adulto',
    'responsavel_nome' => '', 'responsavel_telefone' => '',
]);
igual('adulto não precisa de responsável', [], inscricao_validar($adulto)['erros']);

// Quem faz 18 anos antes do evento já conta como adulto.
$fazDezoitoAntes = ficha([
    'nascimento' => '2008-11-12', 'ramo' => 'adulto',
    'responsavel_nome' => '', 'responsavel_telefone' => '',
]);
igual('quem completa 18 um dia antes do evento dispensa responsável', [],
    inscricao_validar($fazDezoitoAntes)['erros']);

$fazDezoitoDepois = ficha([
    'nascimento' => '2008-11-14', 'ramo' => 'pioneiro',
    'responsavel_nome' => '', 'responsavel_telefone' => '',
]);
verificar('quem completa 18 um dia depois do evento ainda precisa de responsável',
    isset(inscricao_validar($fazDezoitoDepois)['erros']['responsavel_nome']));

// --------------------------------------------------------- criar inscrição ---

grupo('Gravacao de inscrições');

$dados = inscricao_validar(ficha())['dados'];
$protocolo1 = inscricao_criar($dados);
verificar('inscricao_criar devolve um protocolo válido',
    preg_match('/^AE26-[A-Z2-9]{6}$/', $protocolo1) === 1, $protocolo1);

$gravada = inscricao_por_protocolo($protocolo1);
verificar('inscrição gravada e recuperada pelo protocolo', $gravada !== null);
igual('nome gravado confere', 'Ana Beatriz Souza', (string) $gravada['nome']);
igual('status inicial e pendente', 'pendente', (string) $gravada['status']);

verificar('busca por protocolo ignora maiusculas/minusculas',
    inscricao_por_protocolo(strtolower($protocolo1)) !== null);
verificar('protocolo inexistente devolve null',
    inscricao_por_protocolo('AE26-ZZZZZZ') === null);

verificar('inscrição duplicada e detectada',
    inscricao_duplicada('ana beatriz souza', '2012-04-10'));
verificar('nome parecido com outra data não e duplicata',
    !inscricao_duplicada('Ana Beatriz Souza', '2013-04-10'));

$protocolo2 = inscricao_criar(inscricao_validar(ficha([
    'nome' => 'Bruno Carvalho Lima', 'nascimento' => '2011-08-02',
    'grupo' => '45/RS Ibirapuita', 'email' => 'bruno@example.com',
]))['dados']);
$protocolo3 = inscricao_criar(inscricao_validar(ficha([
    'nome' => 'Carla Mendes Rocha', 'nascimento' => '2010-01-30',
    'grupo' => '12/RS Tape', 'email' => 'carla@example.com',
]))['dados']);

igual('três inscrições gravadas', 3, inscricoes_contar_ativas());

// -------------------------------------------------------- status e filtros ---

grupo('Situação das inscrições');

$idAna = (int) inscricao_por_protocolo($protocolo1)['id'];
$idBruno = (int) inscricao_por_protocolo($protocolo2)['id'];
$idCarla = (int) inscricao_por_protocolo($protocolo3)['id'];

verificar('mudanca de status funciona', inscricao_mudar_status($idAna, 'confirmada'));
igual('status persistido', 'confirmada', (string) inscricao_por_id($idAna)['status']);
verificar('status inválido e recusado', !inscricao_mudar_status($idAna, 'inventado'));
verificar('status em id inexistente devolve false', !inscricao_mudar_status(99999, 'confirmada'));

inscricao_mudar_status($idBruno, 'confirmada');
inscricao_mudar_status($idCarla, 'cancelada');

igual('cancelada não ocupa vaga', 2, inscricoes_contar_ativas());

igual('filtro por status', 2, count(inscricoes_listar(['status' => 'confirmada'])));
igual('filtro por ramo', 3, count(inscricoes_listar(['ramo' => 'escoteiro'])));
igual('filtro por texto no nome', 1, count(inscricoes_listar(['busca' => 'Bruno'])));
igual('filtro por texto no grupo', 2, count(inscricoes_listar(['busca' => 'Tape'])));
igual('filtro por protocolo', 1, count(inscricoes_listar(['busca' => $protocolo2])));
igual('filtro sem resultado', 0, count(inscricoes_listar(['busca' => 'inexistente'])));
igual('status desconhecido e ignorado no filtro', 3, count(inscricoes_listar(['status' => 'xpto'])));

$resumo = inscricoes_resumo();
igual('resumo conta o total', 3, $resumo['total']);
igual('resumo conta confirmadas', 2, $resumo['por_status']['confirmada']);
igual('resumo conta canceladas', 1, $resumo['por_status']['cancelada']);
igual('resumo ignora canceladas por ramo', 2, $resumo['por_ramo']['escoteiro']);
igual('resumo conta grupos distintos', 2, $resumo['grupos']);

// -------------------------------------------------------- vagas e prazos ---

grupo('Abertura das inscrições');

verificar('inscrições abertas dentro do prazo', inscricoes_situacao()['aberta']);

config_gravar('vagas_total', '2');
$situacao = inscricoes_situacao();
verificar('vagas esgotadas fecham as inscrições', !$situacao['aberta']);
igual('motivo das vagas esgotadas', 'esgotadas', $situacao['motivo']);
igual('nenhuma vaga restante', 0, $situacao['vagas_restantes']);

config_gravar('vagas_total', '150');
igual('vagas restantes descontam as ativas', 148, inscricoes_situacao()['vagas_restantes']);

config_gravar('inscricoes_ate', '2020-01-01');
igual('prazo vencido fecha as inscrições', 'prazo', inscricoes_situacao()['motivo']);

config_gravar('inscricoes_ate', '2026-12-31');
config_gravar('inscricoes_abertas', '0');
igual('chave fechada tem prioridade', 'fechadas', inscricoes_situacao()['motivo']);
config_gravar('inscricoes_abertas', '1');
verificar('inscrições reabertas', inscricoes_situacao()['aberta']);

// ------------------------------------------------------------- patrulhas ---

grupo('Patrulhas');

$lobo = patrulha_criar('Lobo', '#8b4513', 'Uivo!', 8);
verificar('patrulha criada', $lobo['ok']);
verificar('nome repetido e recusado', !patrulha_criar('lobo')['ok']);
verificar('nome vazio e recusado', !patrulha_criar('')['ok']);
verificar('número de vagas absurdo e recusado', !patrulha_criar('Tucano', '#000000', '', 99)['ok']);

$falcao = patrulha_criar('Falcão', '#24567c', '', 8);
$onca = patrulha_criar('Onça', 'cor-invalida', '', 8);
verificar('cor inválida cai no padrao',
    (string) patrulha_por_id($onca['id'])['cor'] === '#2f6f4f');

igual('três patrulhas cadastradas', 3, count(patrulhas_listar()));

verificar('patrulha atualizada', patrulha_atualizar($onca['id'], 'Onça Pintada', '#111111', 'Grrr', 10));
igual('nome atualizado', 'Onça Pintada', (string) patrulha_por_id($onca['id'])['nome']);
verificar('atualizacao com nome de outra patrulha e recusada',
    !patrulha_atualizar($onca['id'], 'Lobo', '#111111', '', 8));

verificar('inscrito movido para patrulha', inscricao_definir_patrulha($idAna, $lobo['id']));
igual('patrulha do inscrito confere', 'Lobo', (string) inscricao_por_id($idAna)['patrulha_nome']);
verificar('patrulha inexistente e recusada', !inscricao_definir_patrulha($idAna, 99999));
verificar('inscrito pode sair da patrulha', inscricao_definir_patrulha($idAna, null));
verificar('inscrito ficou sem patrulha', inscricao_por_id($idAna)['patrulha_id'] === null);

igual('filtro por sem patrulha', 3, count(inscricoes_listar(['patrulha' => 'sem'])));
inscricao_definir_patrulha($idAna, $lobo['id']);
igual('filtro por patrulha específica', 1,
    count(inscricoes_listar(['patrulha' => (string) $lobo['id']])));

// Distribuição automática.
inscricao_definir_patrulha($idAna, null);
$distribuidos = patrulhas_distribuir();
igual('so os confirmados sem patrulha são distribuidos', 2, $distribuidos);
verificar('Ana recebeu patrulha', inscricao_por_id($idAna)['patrulha_id'] !== null);
verificar('Bruno recebeu patrulha', inscricao_por_id($idBruno)['patrulha_id'] !== null);
verificar('cancelada continua sem patrulha', inscricao_por_id($idCarla)['patrulha_id'] === null);
verificar('inscritos do mesmo grupo ficam em patrulhas diferentes',
    (int) inscricao_por_id($idAna)['patrulha_id'] !== (int) inscricao_por_id($idBruno)['patrulha_id']);
igual('rodar de novo não move ninguem', 0, patrulhas_distribuir());

// Exclusão solta os membros em vez de apagar as inscrições.
$patrulhaDeAna = (int) inscricao_por_id($idAna)['patrulha_id'];
verificar('patrulha excluida', patrulha_excluir($patrulhaDeAna));
verificar('inscrição sobrevive a exclusão da patrulha', inscricao_por_id($idAna) !== null);
verificar('inscrição ficou sem patrulha', inscricao_por_id($idAna)['patrulha_id'] === null);

// ------------------------------------------------------------ atividades ---

grupo('Programação');

$trilha = atividade_salvar([
    'titulo' => 'Trilha da Mata', 'descricao' => 'Percurso de 6 km com bússola.',
    'local' => 'Trilha norte', 'inicio' => '2026-11-14T08:00', 'fim' => '2026-11-14T12:00',
    'pontuavel' => '1', 'publicada' => '1',
]);
verificar('atividade criada', $trilha['ok']);

$fogo = atividade_salvar([
    'titulo' => 'Fogo de Conselho', 'inicio' => '2026-11-14T20:00', 'fim' => '2026-11-14T22:00',
    'publicada' => '1',
]);
$abertura = atividade_salvar([
    'titulo' => 'Abertura', 'inicio' => '2026-11-13T19:00', 'fim' => '2026-11-13T20:00',
    'publicada' => '1',
]);
$rascunho = atividade_salvar([
    'titulo' => 'Oficina surpresa', 'inicio' => '2026-11-15T09:00', 'fim' => '2026-11-15T10:00',
]);

verificar('titulo curto e recusado',
    !atividade_salvar(['titulo' => 'ab', 'inicio' => '2026-11-14T08:00', 'fim' => '2026-11-14T09:00'])['ok']);
verificar('término antes do início e recusado',
    !atividade_salvar(['titulo' => 'Invertida', 'inicio' => '2026-11-14T10:00', 'fim' => '2026-11-14T09:00'])['ok']);
verificar('término igual ao início e recusado',
    !atividade_salvar(['titulo' => 'Instantanea', 'inicio' => '2026-11-14T10:00', 'fim' => '2026-11-14T10:00'])['ok']);
verificar('data inválida e recusada',
    !atividade_salvar(['titulo' => 'Sem data', 'inicio' => 'amanha', 'fim' => 'depois'])['ok']);

igual('quatro atividades cadastradas', 4, count(atividades_listar(false)));
igual('rascunho fica fora da lista pública', 3, count(atividades_listar(true)));
igual('so a trilha e pontuável', 1, count(atividades_pontuaveis()));

$listadas = atividades_listar(false);
igual('atividades saem em ordem cronológica', 'Abertura', (string) $listadas[0]['titulo']);

$porDia = atividades_por_dia(true);
igual('programação pública agrupada em dois dias', 2, count($porDia));
igual('primeiro dia e o da abertura', '2026-11-13', array_key_first($porDia));
igual('segundo dia tem duas atividades', 2, count($porDia['2026-11-14']));

igual('hora_de extrai a hora', '08:00', hora_de('2026-11-14 08:00'));
igual('dia_semana_br nomeia o dia', 'Sábado', dia_semana_br('2026-11-14'));

$edicao = atividade_salvar([
    'titulo' => 'Trilha da Mata Atlântica', 'inicio' => '2026-11-14T08:00',
    'fim' => '2026-11-14T12:30', 'pontuavel' => '1', 'publicada' => '1',
], $trilha['id']);
verificar('atividade editada', $edicao['ok']);
igual('titulo atualizado', 'Trilha da Mata Atlântica',
    (string) atividade_por_id($trilha['id'])['titulo']);
igual('edição não cria registro novo', 4, count(atividades_listar(false)));

// ------------------------------------------------------------- pontuação ---

grupo('Pontuação');

// Repoe a terceira patrulha, excluida no grupo anterior.
patrulha_criar('Tucano', '#f2b705', '', 8);

$patrulhas = patrulhas_listar();
$pa = (int) $patrulhas[0]['id'];
$pb = (int) $patrulhas[1]['id'];

verificar('pontuação lancada', pontuacao_lancar($pa, $trilha['id'], 100, 'Chegou primeiro')['ok']);
verificar('pontuação da segunda patrulha', pontuacao_lancar($pb, $trilha['id'], 80)['ok']);

verificar('atividade não pontuável e recusada',
    !pontuacao_lancar($pa, $fogo['id'], 50)['ok']);
verificar('patrulha inexistente e recusada',
    !pontuacao_lancar(99999, $trilha['id'], 50)['ok']);
verificar('atividade inexistente e recusada',
    !pontuacao_lancar($pa, 99999, 50)['ok']);
verificar('pontuação fora do limite e recusada',
    !pontuacao_lancar($pa, $trilha['id'], 5000)['ok']);
verificar('pontuação negativa e aceita (penalidade)',
    pontuacao_lancar($pb, $trilha['id'], -10)['ok']);

$matriz = pontuacao_matriz();
igual('relancar substitui em vez de somar', -10, $matriz[$pb][$trilha['id']]['pontos']);
igual('observação guardada', 'Chegou primeiro', $matriz[$pa][$trilha['id']]['observacao']);

pontuacao_lancar($pb, $trilha['id'], 80);

$ranking = pontuacao_ranking();
igual('ranking lista todas as patrulhas', count(patrulhas_listar()), count($ranking));
igual('líder tem mais pontos', $pa, $ranking[0]['id']);
igual('líder na primeira posicao', 1, $ranking[0]['posicao']);
igual('total do líder', 100, $ranking[0]['total']);
igual('segundo colocado', 80, $ranking[1]['total']);
igual('patrulha sem pontos fica zerada', 0, $ranking[2]['total']);

// Empate: mesma soma e mesmo número de provas dividem a colocação.
pontuacao_lancar($pb, $trilha['id'], 100);
$empate = pontuacao_ranking();
igual('empate divide a primeira posicao', 1, $empate[0]['posicao']);
igual('segundo empatado também e primeiro', 1, $empate[1]['posicao']);
igual('próxima colocação pula para 3', 3, $empate[2]['posicao']);

verificar('pontuação removida', pontuacao_remover($pb, $trilha['id']));
igual('remocao zera o total', 0, pontuacao_ranking()[1]['total']);
verificar('remover o que não existe devolve false', !pontuacao_remover($pb, $trilha['id']));

// Excluir a atividade leva junto as pontuações dela.
pontuacao_lancar($pa, $trilha['id'], 70);
atividade_excluir($trilha['id']);
igual('pontuações somem com a atividade', 0, count(pontuacao_matriz()));

// ------------------------------------------------------------- segurança ---

grupo('Usuários e segurança');

igual('banco comeca sem usuários', 0, usuarios_total());

$admin = usuario_criar('Gelson Wergutz', 'chefe@example.com', 'senhaforte123', 'admin');
verificar('administrador criado', $admin['ok']);
igual('agora existe um usuário', 1, usuarios_total());

verificar('e-mail repetido e recusado',
    !usuario_criar('Outro', 'chefe@example.com', 'senhaforte123')['ok']);
verificar('e-mail repetido ignora maiusculas',
    !usuario_criar('Outro', 'CHEFE@example.com', 'senhaforte123')['ok']);
verificar('senha curta e recusada',
    !usuario_criar('Curto', 'curto@example.com', '1234')['ok']);
verificar('e-mail inválido e recusado',
    !usuario_criar('Inválido', 'sem-arroba', 'senhaforte123')['ok']);
verificar('papel desconhecido e recusado',
    !usuario_criar('Papel', 'papel@example.com', 'senhaforte123', 'presidente')['ok']);

$senhaHash = (string) db_valor('SELECT senha_hash FROM usuarios WHERE id = ?', [$admin['id']]);
verificar('senha não e guardada em texto puro', $senhaHash !== 'senhaforte123');
verificar('senha e guardada com hash verificavel',
    password_verify('senhaforte123', $senhaHash));

verificar('autenticacao com senha certa',
    usuario_autenticar('chefe@example.com', 'senhaforte123') !== null);
verificar('autenticacao ignora maiusculas do e-mail',
    usuario_autenticar('Chefe@Example.com', 'senhaforte123') !== null);
verificar('senha errada e rejeitada',
    usuario_autenticar('chefe@example.com', 'errada') === null);
verificar('usuário inexistente e rejeitado',
    usuario_autenticar('ninguem@example.com', 'senhaforte123') === null);

db_exec('UPDATE usuarios SET ativo = 0 WHERE id = ?', [$admin['id']]);
verificar('usuário desativado não entra',
    usuario_autenticar('chefe@example.com', 'senhaforte123') === null);
db_exec('UPDATE usuarios SET ativo = 1 WHERE id = ?', [$admin['id']]);

// CSRF depende de sessão; no CLI simulamos o array de sessão.
$_SESSION = [];
$token = csrf_token();
verificar('token csrf tem tamanho util', strlen($token) === 64);
igual('token e estavel dentro da sessão', $token, csrf_token());

$_POST['_csrf'] = $token;
verificar('token correto passa', csrf_valido());
$_POST['_csrf'] = 'errado';
verificar('token errado não passa', !csrf_valido());
$_POST['_csrf'] = '';
verificar('token vazio não passa', !csrf_valido());
unset($_POST['_csrf']);
verificar('post sem token não passa', !csrf_valido());

// Limite de envios.
$_SESSION['limites'] = [];
$permitidos = 0;
for ($i = 0; $i < 10; $i++) {
    $permitidos += limite_envio_ok('teste', 3, 600) ? 1 : 0;
}
igual('limite de envios corta no máximo configurado', 3, $permitidos);
verificar('outra chave tem o proprio limite', limite_envio_ok('outra-chave', 3, 600));

// ------------------------------------------------------- injecao de SQL ---

grupo('Resistencia a entradas maliciosas');

$sql = "'; DROP TABLE inscricoes; --";
$antes = inscricoes_contar_ativas();
inscricoes_listar(['busca' => $sql]);
igual('busca com SQL não derruba a tabela', $antes, inscricoes_contar_ativas());
verificar('consulta por protocolo com SQL não explode',
    inscricao_por_protocolo($sql) === null);

$xss = '<script>alert(1)</script>';
$comXss = inscricao_validar(ficha([
    'nome' => 'Diego ' . $xss, 'nascimento' => '2011-03-03', 'email' => 'diego@example.com',
]));
$protocoloXss = inscricao_criar($comXss['dados']);
$recuperada = inscricao_por_protocolo($protocoloXss);
verificar('HTML e guardado como texto',
    str_contains((string) $recuperada['nome'], '<script>'));
verificar('HTML sai escapado na tela',
    !str_contains(e((string) $recuperada['nome']), '<script>'));

// ------------------------------------------- sistema recem-instalado ---

grupo('Sistema recém-instalado');

// Estado de fabrica: nada de evento inventado.
igual('nenhuma data de evento de fábrica', '', CONFIG_PADRAO['evento_inicio']);
igual('nenhum local de fábrica', '', CONFIG_PADRAO['evento_local']);
igual('nenhum valor de fábrica', '', CONFIG_PADRAO['evento_valor']);
igual('nenhum contato de fábrica', '', CONFIG_PADRAO['contato_email']);
igual('inscrições nascem fechadas', '0', CONFIG_PADRAO['inscricoes_abertas']);
igual('sem limite de vagas de fábrica', '0', CONFIG_PADRAO['vagas_total']);

// Zera a configuração para simular a primeira execução.
db_exec('DELETE FROM configuracoes');
config_todas(true);

$pendencias = config_pendencias();
verificar('sistema novo acusa pendências', $pendencias !== []);
verificar('evento_configurado() e falso no começo', !evento_configurado());

foreach (array_keys(CONFIG_OBRIGATORIAS) as $obrigatoria) {
    verificar('pendência acusada: ' . $obrigatoria, isset($pendencias[$obrigatoria]));
}

$situacao = inscricoes_situacao();
verificar('sistema não configurado não abre inscrições', !$situacao['aberta']);
igual('motivo e a falta de configuração', 'sem_configuracao', $situacao['motivo']);

// Mesmo mandando abrir, a falta de configuração tem a ultima palavra.
config_gravar('inscricoes_abertas', '1');
igual('abrir na mão não vence a configuração pendente',
    'sem_configuracao', inscricoes_situacao()['motivo']);

// Sem data de evento, as telas não inventam texto.
igual('período vazio sem datas', '', evento_periodo());
igual('subtítulo vazio sem dados', '', evento_subtitulo());
igual('local vazio sem dados', '', evento_local_completo());
verificar('sem data não ha contagem regressiva', evento_dias_restantes() === null);

// Preenchendo uma a uma, a lista de pendências encolhe ate zerar.
$restantes = count(CONFIG_OBRIGATORIAS);
$exemplos = [
    'evento_inicio'  => '2026-11-13',
    'evento_fim'     => '2026-11-15',
    'evento_local'   => 'Campo Escola Regional',
    'evento_cidade'  => 'Santa Cruz do Sul',
    'evento_uf'      => 'RS',
    'inscricoes_ate' => '2026-10-30',
    'contato_email'  => 'contato@example.com',
];
foreach ($exemplos as $chave => $valor) {
    config_gravar($chave, $valor);
    $restantes--;
    igual('pendências restantes após preencher ' . $chave,
        $restantes, count(config_pendencias()));
}

verificar('evento configurado no final', evento_configurado());
verificar('agora as inscrições abrem', inscricoes_situacao()['aberta']);
igual('subtítulo montado', '13 a 15/11/2026 · Santa Cruz do Sul/RS', evento_subtitulo());
igual('local completo montado', 'Campo Escola Regional, Santa Cruz do Sul/RS', evento_local_completo());

// Campo obrigatório so com espaços continua pendente.
config_gravar('evento_local', '   ');
verificar('espaço em branco não conta como preenchido',
    isset(config_pendencias()['evento_local']));
config_gravar('evento_local', 'Campo Escola Regional');

// vagas_total = 0 significa sem limite, não "esgotado".
config_gravar('vagas_total', '0');
$semLimite = inscricoes_situacao();
verificar('sem limite de vagas mantem as inscrições abertas', $semLimite['aberta']);
igual('vagas sem limite aparecem como texto', 'sem limite',
    vagas_texto($semLimite['vagas_restantes']));
config_gravar('vagas_total', '150');
igual('com limite, o texto e o número', '147', vagas_texto(inscricoes_situacao()['vagas_restantes']));

// ------------------------------------------------- ramos configuraveis ---

grupo('Ramos configuráveis');

igual('todos os ramos ativos de fábrica', count(RAMOS), count(ramos_disponiveis()));

// Faixa etária editada muda o que a ficha aceita.
config_gravar_varias(['ramo_escoteiro_min' => '11', 'ramo_escoteiro_max' => '14']);
igual('faixa padrão aceita quem tem 14 anos no evento', [],
    inscricao_validar(ficha(['nome' => 'Elisa Prado Nunes', 'nascimento' => '2012-04-10']))['erros']);

config_gravar_varias(['ramo_escoteiro_min' => '11', 'ramo_escoteiro_max' => '13']);
verificar('faixa reduzida passa a recusar os mesmos 14 anos',
    isset(inscricao_validar(ficha(['nascimento' => '2012-04-10']))['erros']['ramo']));

config_gravar_varias(['ramo_escoteiro_min' => '10', 'ramo_escoteiro_max' => '16']);
igual('faixa ampliada volta a aceitar', [],
    inscricao_validar(ficha(['nome' => 'Elisa Prado Nunes', 'nascimento' => '2012-04-10']))['erros']);

// A conferência de idade pode ser desligada por completo.
config_gravar_varias(['ramo_escoteiro_min' => '11', 'ramo_escoteiro_max' => '14']);
verificar('com a conferência ligada, idade errada e recusada',
    isset(inscricao_validar(ficha(['ramo' => 'lobinho']))['erros']['ramo']));

config_gravar('validar_idade', '0');
verificar('conferência desligada e reconhecida', !validar_idade_ligado());
igual('com a conferência desligada, qualquer idade passa no ramo', [],
    inscricao_validar(ficha(['nome' => 'Fabio Reis Antunes', 'ramo' => 'lobinho']))['erros']);

// Mesmo desligada, a regra do responsável continua valendo.
verificar('responsável continua obrigatório com a conferência desligada',
    isset(inscricao_validar(ficha([
        'ramo' => 'lobinho', 'responsavel_nome' => '', 'responsavel_telefone' => '',
    ]))['erros']['responsavel_nome']));
config_gravar('validar_idade', '1');

// Ramo desativado some da ficha e deixa de ser aceito.
config_gravar('ramos_ativos', 'escoteiro,senior');
igual('só os ramos ativos aparecem', 2, count(ramos_disponiveis()));
verificar('ramo ativo continua aceito',
    !isset(inscricao_validar(ficha(['nome' => 'Gabriel Luz Moraes']))['erros']['ramo']));
verificar('ramo desativado e recusado',
    isset(inscricao_validar(ficha(['ramo' => 'lobinho', 'nascimento' => '2017-01-05']))['erros']['ramo']));
config_gravar('ramos_ativos', 'lobinho,escoteiro,senior,pioneiro,adulto');

// Sem data de evento, ninguem e reprovado por idade de ramo, mas o
// responsável continua sendo exigido pela idade de hoje.
config_gravar('evento_inicio', '');
igual('sem data do evento, o ramo não e conferido', [],
    inscricao_validar(ficha(['nome' => 'Helena Dias Vargas', 'ramo' => 'lobinho']))['erros']);
verificar('sem data do evento, menor ainda precisa de responsável',
    isset(inscricao_validar(ficha([
        'responsavel_nome' => '', 'responsavel_telefone' => '',
    ]))['erros']['responsavel_nome']));
config_gravar('evento_inicio', '2026-11-13');

// ------------------------------------------- protecao em subpasta ---

grupo('Proteção de pastas em qualquer caminho');

// A aplicação fica numa subpasta do domínio (…/aventuraescoteira26), então
// nenhuma regra do .htaccess pode estar ancorada na raiz do site: o mesmo
// arquivo precisa proteger src/, data/ e testes/ nos dois cenários.
$htaccess = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');

verificar('.htaccess não ancora regra na raiz do site',
    !str_contains($htaccess, 'RedirectMatch 404 ^/('),
    'uma regra "^/" só funcionaria com a aplicação na raiz do domínio');

// Extrai o padrão de bloqueio do próprio arquivo e conferre o comportamento.
$achou = preg_match('/RedirectMatch\s+404\s+(\S+)/', $htaccess, $m) === 1;
verificar('.htaccess declara a regra de bloqueio', $achou);

if ($achou) {
    $padrao = '#' . $m[1] . '#';

    $bloquear = [
        '/src/db.php',
        '/data/aventura.sqlite',
        '/testes/executar.php',
        '/aventuraescoteira26/src/db.php',
        '/aventuraescoteira26/data/aventura.sqlite',
        '/aventuraescoteira26/testes/executar.php',
        '/qualquer/nivel/src/seguranca.php',
    ];
    foreach ($bloquear as $url) {
        verificar('bloqueia ' . $url, preg_match($padrao, $url) === 1);
    }

    $liberar = [
        '/index.php',
        '/aventuraescoteira26/',
        '/aventuraescoteira26/index.php',
        '/aventuraescoteira26/inscricao.php',
        '/aventuraescoteira26/admin/painel.php',
        '/aventuraescoteira26/assets/estilo.css',
    ];
    foreach ($liberar as $url) {
        verificar('libera ' . $url, preg_match($padrao, $url) !== 1);
    }
}

// Cada pasta sensível tambem se protege sozinha, caso o .htaccess da
// aplicação não seja lido (AllowOverride desligado numa delas, por exemplo).
foreach (['src', 'data', 'testes'] as $pasta) {
    $arquivo = dirname(__DIR__) . '/' . $pasta . '/.htaccess';
    verificar($pasta . '/ tem .htaccess próprio', is_file($arquivo));
    verificar($pasta . '/ nega o acesso',
        is_file($arquivo) && str_contains((string) file_get_contents($arquivo), 'Require all denied'));
}

// Todo link gerado precisa ser relativo: caminho absoluto quebraria a
// aplicação assim que ela sair da raiz do domínio.
$absolutos = [];
foreach (array_merge(
    glob(dirname(__DIR__) . '/*.php') ?: [],
    glob(dirname(__DIR__) . '/admin/*.php') ?: [],
    glob(dirname(__DIR__) . '/src/*.php') ?: []
) as $fonte) {
    $conteudo = (string) file_get_contents($fonte);
    if (preg_match('/(href|action|src)\s*=\s*"\/(?!\/)/i', $conteudo) === 1) {
        $absolutos[] = basename($fonte);
    }
}
verificar('nenhum link absoluto no HTML', $absolutos === [], implode(', ', $absolutos));

// O mesmo vale para os redirecionamentos do servidor.
$redirecionamentos = [];
foreach (array_merge(
    glob(dirname(__DIR__) . '/*.php') ?: [],
    glob(dirname(__DIR__) . '/admin/*.php') ?: []
) as $fonte) {
    if (preg_match('/redirecionar\(\s*[\'"]\//', (string) file_get_contents($fonte)) === 1) {
        $redirecionamentos[] = basename($fonte);
    }
}
verificar('nenhum redirecionamento absoluto', $redirecionamentos === [],
    implode(', ', $redirecionamentos));

// --------------------------------------------------- integridade do SQL ---

grupo('Integridade do codigo');

// Acento dentro de um literal SQL quebra a consulta em tempo de execucao,
// e nem sempre existe um teste que passe por ela. Esta varredura garante
// que nenhuma consulta ganhou acento por engano.
$sqlAcentuado = [];
$fontes = array_merge(
    glob(dirname(__DIR__) . '/src/*.php') ?: [],
    glob(dirname(__DIR__) . '/admin/*.php') ?: [],
    glob(dirname(__DIR__) . '/*.php') ?: []
);

foreach ($fontes as $fonte) {
    foreach (token_get_all((string) file_get_contents($fonte)) as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $miolo = substr($token[1], 1, -1);
        $pareceSql = preg_match(
            '/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|PRAGMA|ALTER|DROP|FROM|WHERE'
            . '|VALUES|CONFLICT|ORDER\s+BY|GROUP\s+BY|LIMIT|OFFSET|JOIN|SET|INTO)\b/i',
            $miolo
        );
        if ($pareceSql === 1 && preg_match('/[^\x00-\x7F]/', $miolo) === 1) {
            $sqlAcentuado[] = basename($fonte) . ':' . $token[2];
        }
    }
}

verificar('nenhum literal SQL contem acento', $sqlAcentuado === [],
    implode(', ', $sqlAcentuado));

// As colunas do banco tambem precisam continuar em ASCII.
$colunas = [];
foreach (['inscricoes', 'patrulhas', 'atividades', 'pontuacoes', 'usuarios', 'configuracoes'] as $tabela) {
    foreach (db_todos('PRAGMA table_info(' . $tabela . ')') as $coluna) {
        $colunas[] = (string) $coluna['name'];
    }
}
verificar('todas as colunas do banco sao ASCII',
    $colunas !== [] && preg_grep('/[^\x00-\x7F]/', $colunas) === []);

// --------------------------------------------------------------- placar ---

echo "\n" . str_repeat('-', 56) . "\n";
printf(
    "%d teste(s): \033[32m%d passaram\033[0m, %s%d falharam\033[0m\n",
    $GLOBALS['passou'] + $GLOBALS['falhou'],
    $GLOBALS['passou'],
    $GLOBALS['falhou'] > 0 ? "\033[31m" : "\033[32m",
    $GLOBALS['falhou']
);

exit($GLOBALS['falhou'] > 0 ? 1 : 0);
