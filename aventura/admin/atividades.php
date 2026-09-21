<?php
/**
 * Montagem da programação.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $acao = post('acao');

    if ($acao === 'excluir') {
        atividade_excluir((int) post('id'));
        recado('ok', 'Atividade removida.');
        redirecionar('atividades.php');
    }

    if ($acao === 'salvar') {
        $id = post('id') !== '' ? (int) post('id') : null;
        $resultado = atividade_salvar($_POST, $id);

        if ($resultado['ok']) {
            recado('ok', $id === null ? 'Atividade criada.' : 'Atividade atualizada.');
            redirecionar('atividades.php');
        }
        recado('erro', implode(' ', $resultado['erros']));
        redirecionar('atividades.php' . ($id !== null ? '?editar=' . $id : ''));
    }
}

$editarId = (int) get('editar');
$editando = $editarId > 0 ? atividade_por_id($editarId) : null;
$atividades = atividades_listar(false);

$inicioPadrao = config_ler('evento_inicio') . 'T08:00';
$fimPadrao = config_ler('evento_inicio') . 'T09:00';

layout_topo('Programação', 'painel', 'atividades.php');
?>

<h1>Programação</h1>

<form method="post" action="atividades.php" class="cartao">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $editando !== null ? (int) $editando['id'] : '' ?>">
    <h2 style="margin-top:0"><?= $editando !== null ? 'Editar atividade' : 'Nova atividade' ?></h2>

    <div class="campo">
        <label for="titulo">Titulo *</label>
        <input type="text" id="titulo" name="titulo" maxlength="120" required
               value="<?= e($editando !== null ? (string) $editando['titulo'] : '') ?>">
    </div>

    <div class="grade grade-3">
        <div class="campo">
            <label for="inicio">Início *</label>
            <input type="datetime-local" id="inicio" name="inicio" required
                   value="<?= e($editando !== null
                        ? str_replace(' ', 'T', (string) $editando['inicio'])
                        : $inicioPadrao) ?>">
        </div>
        <div class="campo">
            <label for="fim">Término *</label>
            <input type="datetime-local" id="fim" name="fim" required
                   value="<?= e($editando !== null
                        ? str_replace(' ', 'T', (string) $editando['fim'])
                        : $fimPadrao) ?>">
        </div>
        <div class="campo">
            <label for="local">Local</label>
            <input type="text" id="local" name="local" maxlength="120"
                   value="<?= e($editando !== null ? (string) $editando['local'] : '') ?>">
        </div>
    </div>

    <div class="campo">
        <label for="descricao">Descricao</label>
        <textarea id="descricao" name="descricao" maxlength="1000"><?= e($editando !== null
            ? (string) $editando['descricao'] : '') ?></textarea>
    </div>

    <div class="campo caixa-marcacao">
        <input type="checkbox" id="pontuavel" name="pontuavel" value="1"
               <?= $editando !== null && (int) $editando['pontuavel'] === 1 ? 'checked' : '' ?>>
        <label for="pontuavel">Vale pontos para o ranking das patrulhas</label>
    </div>

    <div class="campo caixa-marcacao">
        <input type="checkbox" id="publicada" name="publicada" value="1"
               <?= $editando === null || (int) $editando['publicada'] === 1 ? 'checked' : '' ?>>
        <label for="publicada">Visível na programação pública</label>
    </div>

    <div class="acoes">
        <button type="submit" class="botao" name="acao" value="salvar">
            <?= $editando !== null ? 'Salvar alterações' : 'Adicionar a programação' ?>
        </button>
        <?php if ($editando !== null): ?>
            <a class="botao botao-secundario" href="atividades.php">Cancelar edição</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($atividades === []): ?>
    <div class="cartao vazio"><p>Nenhuma atividade cadastrada.</p></div>
<?php else: ?>
    <div class="cartao rolagem">
        <table>
            <thead>
                <tr>
                    <th>Quando</th><th>Atividade</th><th>Local</th>
                    <th>Pontua</th><th>Pública</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atividades as $atividade): ?>
                    <tr>
                        <td><?= e(data_br((string) $atividade['inicio'])) ?><br>
                            <small><?= e(hora_de((string) $atividade['inicio'])) ?>
                                &ndash; <?= e(hora_de((string) $atividade['fim'])) ?></small></td>
                        <td><strong><?= e((string) $atividade['titulo']) ?></strong>
                            <?php if ((string) $atividade['descricao'] !== ''): ?>
                                <br><small><?= e(mb_strimwidth((string) $atividade['descricao'], 0, 90, '...')) ?></small>
                            <?php endif; ?></td>
                        <td><?= e((string) $atividade['local']) ?></td>
                        <td><?= (int) $atividade['pontuavel'] === 1 ? 'Sim' : '&ndash;' ?></td>
                        <td><?= (int) $atividade['publicada'] === 1 ? 'Sim' : 'Rascunho' ?></td>
                        <td>
                            <a class="botao botao-secundario botao-mini"
                               href="atividades.php?editar=<?= (int) $atividade['id'] ?>">Editar</a>
                            <form method="post" action="atividades.php" style="display:inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= (int) $atividade['id'] ?>">
                                <button type="submit" class="botao botao-perigo botao-mini" name="acao" value="excluir"
                                        data-confirmar="Excluir <?= e((string) $atividade['titulo']) ?>? As pontuações ligadas a ela também serão apagadas.">
                                    Excluir
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php layout_rodape('painel'); ?>
