<?php
/**
 * Ficha completa de uma inscrição.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

$id = (int) get('id');
$inscricao = $id > 0 ? inscricao_por_id($id) : null;

if ($inscricao === null) {
    recado('erro', 'Inscrição não encontrada.');
    redirecionar('inscricoes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $acao = post('acao');

    if ($acao === 'excluir') {
        if (!e_admin()) {
            http_response_code(403);
            exit('Somente administradores podem excluir inscrições.');
        }
        inscricao_excluir($id);
        recado('ok', 'Inscrição excluida definitivamente.');
        redirecionar('inscricoes.php');
    }

    if ($acao === 'salvar') {
        $status = post('status');
        if (inscricao_mudar_status($id, $status)) {
            recado('ok', 'Situação atualizada para ' . status_rotulo($status) . '.');
        }

        $patrulha = post('patrulha_id');
        inscricao_definir_patrulha($id, $patrulha === '' ? null : (int) $patrulha);

        redirecionar('inscricao-detalhe.php?id=' . $id);
    }
}

$patrulhas = patrulhas_listar();
$idade = idade_em((string) $inscricao['nascimento'], config_ler('evento_inicio'));

layout_topo('Inscrição ' . (string) $inscricao['protocolo'], 'painel', 'inscricoes.php');
?>

<p><a href="inscricoes.php">&larr; Voltar para as inscrições</a></p>

<h1><?= e((string) $inscricao['nome']) ?></h1>
<p class="protocolo"><?= e((string) $inscricao['protocolo']) ?></p>

<div class="grade grade-2" style="margin-top:1.25rem">
    <div class="cartao">
        <h2 style="margin-top:0">Dados do participante</h2>
        <dl class="lista-dados">
            <dt>Nascimento</dt>
            <dd><?= e(data_br((string) $inscricao['nascimento'])) ?>
                <?php if ($idade !== null): ?><small>(<?= $idade ?> anos no evento)</small><?php endif; ?></dd>

            <dt>Ramo</dt>
            <dd><?= e(ramo_rotulo((string) $inscricao['ramo'])) ?></dd>

            <dt>Grupo escoteiro</dt>
            <dd><?= e((string) $inscricao['grupo']) ?> &mdash;
                <?= e((string) $inscricao['cidade']) ?>/<?= e((string) $inscricao['uf']) ?></dd>

            <dt>Tamanho da camisa</dt>
            <dd><?= e((string) $inscricao['tamanho_camisa']) ?></dd>

            <dt>Uso de imagem</dt>
            <dd><?= (int) $inscricao['autoriza_imagem'] === 1 ? 'Autorizado' : 'Não autorizado' ?></dd>
        </dl>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0">Contato</h2>
        <dl class="lista-dados">
            <dt>E-mail</dt>
            <dd><a href="mailto:<?= e((string) $inscricao['email']) ?>"><?= e((string) $inscricao['email']) ?></a></dd>

            <dt>Telefone</dt>
            <dd><?= e(telefone_br((string) $inscricao['telefone'])) ?></dd>

            <?php if ((string) $inscricao['responsavel_nome'] !== ''): ?>
                <dt>Responsável</dt>
                <dd><?= e((string) $inscricao['responsavel_nome']) ?><br>
                    <?= e(telefone_br((string) $inscricao['responsavel_telefone'])) ?></dd>
            <?php endif; ?>

            <dt>Enviada em</dt>
            <dd><?= e(data_hora_br((string) $inscricao['criado_em'])) ?></dd>

            <dt>Última alteração</dt>
            <dd><?= e(data_hora_br((string) $inscricao['atualizado_em'])) ?></dd>
        </dl>
    </div>
</div>

<div class="cartao">
    <h2 style="margin-top:0">Saúde e alimentacao</h2>
    <dl class="lista-dados">
        <dt>Restrições alimentares</dt>
        <dd><?= (string) $inscricao['restricao_alimentar'] !== ''
                ? nl2br(e((string) $inscricao['restricao_alimentar']))
                : '<small>nenhuma informada</small>' ?></dd>

        <dt>Observações de saúde</dt>
        <dd><?= (string) $inscricao['observacoes_saude'] !== ''
                ? nl2br(e((string) $inscricao['observacoes_saude']))
                : '<small>nenhuma informada</small>' ?></dd>
    </dl>
</div>

<form method="post" action="inscricao-detalhe.php?id=<?= $id ?>" class="cartao">
    <?= csrf_campo() ?>
    <h2 style="margin-top:0">Situação e patrulha</h2>
    <div class="filtros">
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <?php foreach (STATUS_INSCRICAO as $codigo => $rotulo): ?>
                    <option value="<?= e($codigo) ?>"
                        <?= (string) $inscricao['status'] === $codigo ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="patrulha_id">Patrulha</label>
            <select id="patrulha_id" name="patrulha_id">
                <option value="">Sem patrulha</option>
                <?php foreach ($patrulhas as $patrulha): ?>
                    <option value="<?= (int) $patrulha['id'] ?>"
                        <?= (int) $inscricao['patrulha_id'] === (int) $patrulha['id'] ? ' selected' : '' ?>>
                        <?= e((string) $patrulha['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="botao" name="acao" value="salvar">Salvar</button>
    </div>
</form>

<?php if (e_admin()): ?>
    <form method="post" action="inscricao-detalhe.php?id=<?= $id ?>" class="cartao">
        <?= csrf_campo() ?>
        <h2 style="margin-top:0">Zona de risco</h2>
        <p>Excluir apaga o registro para sempre. Para tirar alguem da lista sem perder o histórico,
           prefira marcar a inscrição como <em>cancelada</em>.</p>
        <button type="submit" class="botao botao-perigo" name="acao" value="excluir"
                data-confirmar="Excluir definitivamente a inscrição de <?= e((string) $inscricao['nome']) ?>?">
            Excluir inscrição
        </button>
    </form>
<?php endif; ?>

<?php layout_rodape('painel'); ?>
