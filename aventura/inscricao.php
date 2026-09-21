<?php
/**
 * Ficha pública de inscrição.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$config = config_todas();
$situacao = inscricoes_situacao();

$erros = [];
$dados = [
    'nome' => '', 'nascimento' => '', 'ramo' => '', 'grupo' => '', 'cidade' => '',
    'uf' => $config['evento_uf'], 'email' => '', 'telefone' => '',
    'responsavel_nome' => '', 'responsavel_telefone' => '', 'tamanho_camisa' => 'M',
    'restricao_alimentar' => '', 'observacoes_saude' => '', 'autoriza_imagem' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $situacao['aberta']) {
    exigir_csrf();

    // Campo invisivel que so um robo preenche.
    if (post('site') !== '') {
        recado('erro', 'Não foi possível enviar a inscrição.');
        redirecionar('inscricao.php');
    }

    if (!limite_envio_ok('inscricao', 5, 600)) {
        recado('erro', 'Muitos envios seguidos. Aguarde alguns minutos e tente de novo.');
        redirecionar('inscricao.php');
    }

    $validacao = inscricao_validar($_POST);
    $dados = $validacao['dados'];
    $erros = $validacao['erros'];

    if ($erros === [] && inscricao_duplicada($dados['nome'], $dados['nascimento'])) {
        $erros['nome'] = 'Já existe uma inscrição com esse nome e data de nascimento. '
                       . 'Use a página "Minha inscrição" para consultar.';
    }

    // Revalida as vagas no momento da gravacao, não so ao abrir a página.
    if ($erros === []) {
        $situacao = inscricoes_situacao();
        if (!$situacao['aberta']) {
            recado('erro', inscricoes_motivo_texto($situacao['motivo']));
            redirecionar('inscricao.php');
        }
    }

    if ($erros === []) {
        $protocolo = inscricao_criar($dados);
        redirecionar('consulta.php?protocolo=' . rawurlencode($protocolo) . '&nova=1');
    }

    recado('erro', 'Confira os campos destacados abaixo.');
}

layout_topo('Inscrição', 'publico', 'inscricao.php');
?>

<h1>Ficha de inscrição</h1>
<?php
$detalhes = array_filter([
    evento_periodo(),
    $config['evento_valor'] !== '' ? 'taxa de R$ ' . $config['evento_valor'] : '',
], static fn (string $parte): bool => $parte !== '');
?>
<p>
    <?= e($config['evento_nome']) ?><?= $detalhes !== []
        ? ' &middot; ' . e(implode(' · ', $detalhes)) : '' ?>
</p>

<?php if (!$situacao['aberta']): ?>
    <div class="recado recado-aviso"><?= e(inscricoes_motivo_texto($situacao['motivo'])) ?></div>
    <p><a class="botao botao-secundario" href="index.php">Voltar para o início</a></p>
<?php else: ?>

<form method="post" action="inscricao.php" novalidate>
    <?= csrf_campo() ?>
    <div class="armadilha" aria-hidden="true">
        <label for="site">Não preencha este campo</label>
        <input type="text" id="site" name="site" tabindex="-1" autocomplete="off">
    </div>

    <fieldset>
        <legend>Participante</legend>

        <div class="campo<?= classe_campo($erros, 'nome') ?>">
            <label for="nome">Nome completo *</label>
            <input type="text" id="nome" name="nome" maxlength="120" required
                   value="<?= e((string) $dados['nome']) ?>">
            <?= erro_campo($erros, 'nome') ?>
        </div>

        <div class="grade grade-3">
            <div class="campo<?= classe_campo($erros, 'nascimento') ?>">
                <label for="nascimento">Data de nascimento *</label>
                <input type="date" id="nascimento" name="nascimento" required
                       value="<?= e((string) $dados['nascimento']) ?>">
                <?= erro_campo($erros, 'nascimento') ?>
            </div>

            <div class="campo<?= classe_campo($erros, 'ramo') ?>">
                <label for="ramo">Ramo *</label>
                <select id="ramo" name="ramo" required>
                    <option value="">Escolha...</option>
                    <?php foreach (ramos_disponiveis() as $codigo => $ramo): ?>
                        <option value="<?= e($codigo) ?>"<?= $dados['ramo'] === $codigo ? ' selected' : '' ?>>
                            <?= e($ramo['rotulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= erro_campo($erros, 'ramo') ?>
                <?php if (validar_idade_ligado() && config_ler('evento_inicio') !== ''): ?>
                    <span class="ajuda">A idade considerada é a do primeiro dia do evento.</span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="tamanho_camisa">Tamanho da camisa</label>
                <select id="tamanho_camisa" name="tamanho_camisa">
                    <?php foreach (TAMANHOS_CAMISA as $tamanho): ?>
                        <option value="<?= e($tamanho) ?>"<?= $dados['tamanho_camisa'] === $tamanho ? ' selected' : '' ?>>
                            <?= e($tamanho) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Grupo escoteiro</legend>
        <div class="grade grade-3">
            <div class="campo<?= classe_campo($erros, 'grupo') ?>">
                <label for="grupo">Grupo escoteiro *</label>
                <input type="text" id="grupo" name="grupo" maxlength="120" required
                       placeholder="Ex.: 12/RS Tape" value="<?= e((string) $dados['grupo']) ?>">
                <?= erro_campo($erros, 'grupo') ?>
            </div>
            <div class="campo<?= classe_campo($erros, 'cidade') ?>">
                <label for="cidade">Cidade *</label>
                <input type="text" id="cidade" name="cidade" maxlength="80" required
                       value="<?= e((string) $dados['cidade']) ?>">
                <?= erro_campo($erros, 'cidade') ?>
            </div>
            <div class="campo<?= classe_campo($erros, 'uf') ?>">
                <label for="uf">UF *</label>
                <select id="uf" name="uf" required>
                    <?php foreach (UFS as $uf): ?>
                        <option value="<?= e($uf) ?>"<?= $dados['uf'] === $uf ? ' selected' : '' ?>><?= e($uf) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= erro_campo($erros, 'uf') ?>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Contato</legend>
        <div class="grade grade-2">
            <div class="campo<?= classe_campo($erros, 'email') ?>">
                <label for="email">E-mail *</label>
                <input type="email" id="email" name="email" maxlength="160" required
                       value="<?= e((string) $dados['email']) ?>">
                <?= erro_campo($erros, 'email') ?>
            </div>
            <div class="campo<?= classe_campo($erros, 'telefone') ?>">
                <label for="telefone">Telefone com DDD *</label>
                <input type="tel" id="telefone" name="telefone" data-mascara="telefone" required
                       placeholder="(51) 99999-9999" value="<?= e(telefone_br((string) $dados['telefone'])) ?>">
                <?= erro_campo($erros, 'telefone') ?>
            </div>
        </div>
    </fieldset>

    <fieldset id="bloco-responsavel"
              data-referencia="<?= e($config['evento_inicio'] !== '' ? $config['evento_inicio'] : date('Y-m-d')) ?>">
        <legend>Responsável (obrigatório para menores de 18 anos)</legend>
        <div class="grade grade-2">
            <div class="campo<?= classe_campo($erros, 'responsavel_nome') ?>">
                <label for="responsavel_nome">Nome do responsável</label>
                <input type="text" id="responsavel_nome" name="responsavel_nome" maxlength="120"
                       value="<?= e((string) $dados['responsavel_nome']) ?>">
                <?= erro_campo($erros, 'responsavel_nome') ?>
            </div>
            <div class="campo<?= classe_campo($erros, 'responsavel_telefone') ?>">
                <label for="responsavel_telefone">Telefone do responsável</label>
                <input type="tel" id="responsavel_telefone" name="responsavel_telefone" data-mascara="telefone"
                       placeholder="(51) 99999-9999"
                       value="<?= e(telefone_br((string) $dados['responsavel_telefone'])) ?>">
                <?= erro_campo($erros, 'responsavel_telefone') ?>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Saúde e alimentacao</legend>
        <div class="campo<?= classe_campo($erros, 'restricao_alimentar') ?>">
            <label for="restricao_alimentar">Restrições alimentares</label>
            <textarea id="restricao_alimentar" name="restricao_alimentar" maxlength="500"
                      placeholder="Alergias, intolerâncias, dieta vegetariana..."><?= e((string) $dados['restricao_alimentar']) ?></textarea>
            <?= erro_campo($erros, 'restricao_alimentar') ?>
        </div>
        <div class="campo<?= classe_campo($erros, 'observacoes_saude') ?>">
            <label for="observacoes_saude">Observações de saúde</label>
            <textarea id="observacoes_saude" name="observacoes_saude" maxlength="500"
                      placeholder="Medicacao de uso continuo, condicoes que a equipe precisa saber..."><?= e((string) $dados['observacoes_saude']) ?></textarea>
            <?= erro_campo($erros, 'observacoes_saude') ?>
            <span class="ajuda">Estas informacoes ficam visiveis apenas para a equipe organizadora.</span>
        </div>
        <div class="campo caixa-marcacao">
            <input type="checkbox" id="autoriza_imagem" name="autoriza_imagem" value="1"
                   <?= $dados['autoriza_imagem'] ? 'checked' : '' ?>>
            <label for="autoriza_imagem">
                Autorizo o uso de imagem em fotos e vídeos do evento para divulgação institucional.
            </label>
        </div>
    </fieldset>

    <div class="acoes">
        <button type="submit" class="botao">Enviar inscrição</button>
        <a class="botao botao-secundario" href="index.php">Cancelar</a>
    </div>
</form>

<?php endif; ?>

<?php layout_rodape('publico'); ?>
