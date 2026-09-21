<?php
/**
 * Cadastro das patrulhas e distribuição dos inscritos.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $acao = post('acao');

    if ($acao === 'criar') {
        $resultado = patrulha_criar(
            post('nome'),
            post('cor', '#2f6f4f'),
            post('grito'),
            (int) post('vagas', '8')
        );
        if ($resultado['ok']) {
            recado('ok', 'Patrulha criada.');
        } else {
            recado('erro', implode(' ', $resultado['erros']));
        }
    } elseif ($acao === 'atualizar') {
        $ok = patrulha_atualizar(
            (int) post('id'),
            post('nome'),
            post('cor', '#2f6f4f'),
            post('grito'),
            (int) post('vagas', '8')
        );
        recado($ok ? 'ok' : 'erro', $ok
            ? 'Patrulha atualizada.'
            : 'Não foi possível atualizar (nome repetido ou dados inválidos).');
    } elseif ($acao === 'excluir') {
        patrulha_excluir((int) post('id'));
        recado('ok', 'Patrulha removida. Os inscritos dela ficaram sem patrulha.');
    } elseif ($acao === 'sugerir') {
        $criadas = 0;
        foreach (PATRULHAS_SUGERIDAS as $nome) {
            if (patrulha_criar($nome)['ok']) {
                $criadas++;
            }
            if ($criadas >= (int) post('quantidade', '6')) {
                break;
            }
        }
        recado('ok', $criadas . ' patrulha(s) criada(s).');
    } elseif ($acao === 'distribuir') {
        $movidos = patrulhas_distribuir();
        recado($movidos > 0 ? 'ok' : 'aviso', $movidos > 0
            ? $movidos . ' inscrito(s) distribuido(s) entre as patrulhas.'
            : 'Nada a distribuir: nenhum confirmado sem patrulha, ou nenhuma patrulha cadastrada.');
    }

    redirecionar('patrulhas.php');
}

$patrulhas = patrulhas_listar();
$semPatrulha = (int) db_valor(
    "SELECT COUNT(*) FROM inscricoes WHERE status = 'confirmada' AND patrulha_id IS NULL"
);

layout_topo('Patrulhas', 'painel', 'patrulhas.php');
?>

<h1>Patrulhas</h1>

<div class="grade grade-2">
    <form method="post" action="patrulhas.php" class="cartao">
        <?= csrf_campo() ?>
        <h2 style="margin-top:0">Nova patrulha</h2>
        <div class="campo">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" maxlength="40" required placeholder="Ex.: Lobo">
        </div>
        <div class="grade grade-3">
            <div class="campo">
                <label for="cor">Cor</label>
                <input type="color" id="cor" name="cor" value="#2f6f4f">
            </div>
            <div class="campo">
                <label for="vagas">Vagas</label>
                <input type="number" id="vagas" name="vagas" min="1" max="40" value="8">
            </div>
        </div>
        <div class="campo">
            <label for="grito">Grito da patrulha</label>
            <input type="text" id="grito" name="grito" maxlength="160">
        </div>
        <button type="submit" class="botao" name="acao" value="criar">Criar patrulha</button>
    </form>

    <div class="cartao">
        <h2 style="margin-top:0">Atalhos</h2>
        <form method="post" action="patrulhas.php">
            <?= csrf_campo() ?>
            <div class="campo">
                <label for="quantidade">Criar patrulhas com nomes sugeridos</label>
                <input type="number" id="quantidade" name="quantidade" min="1"
                       max="<?= count(PATRULHAS_SUGERIDAS) ?>" value="6">
                <span class="ajuda">Usa nomes de animais brasileiros; nomes já existentes são ignorados.</span>
            </div>
            <button type="submit" class="botao botao-secundario" name="acao" value="sugerir">Criar</button>
        </form>

        <hr style="border:0;border-top:1px solid var(--borda);margin:1.25rem 0">

        <form method="post" action="patrulhas.php">
            <?= csrf_campo() ?>
            <p>
                <strong><?= $semPatrulha ?></strong> inscrito(s) confirmado(s) sem patrulha.
                A distribuição equilibra o tamanho das patrulhas e evita juntar gente do mesmo
                grupo escoteiro sempre que possível.
            </p>
            <button type="submit" class="botao" name="acao" value="distribuir"
                    <?= $semPatrulha === 0 || $patrulhas === [] ? 'disabled' : '' ?>>
                Distribuir automaticamente
            </button>
        </form>
    </div>
</div>

<?php if ($patrulhas === []): ?>
    <div class="cartao vazio"><p>Nenhuma patrulha cadastrada ainda.</p></div>
<?php else: ?>
    <?php foreach ($patrulhas as $patrulha): ?>
        <?php $membros = patrulha_membros((int) $patrulha['id']); ?>
        <div class="cartao">
            <h2 style="margin-top:0">
                <span class="ponto-patrulha" style="background: <?= e((string) $patrulha['cor']) ?>"></span>
                <?= e((string) $patrulha['nome']) ?>
                <small>(<?= count($membros) ?>/<?= (int) $patrulha['vagas'] ?>)</small>
            </h2>
            <?php if ((string) $patrulha['grito'] !== ''): ?>
                <p><em>&ldquo;<?= e((string) $patrulha['grito']) ?>&rdquo;</em></p>
            <?php endif; ?>

            <?php if ($membros === []): ?>
                <p class="vazio">Nenhum inscrito nesta patrulha.</p>
            <?php else: ?>
                <div class="rolagem">
                    <table>
                        <thead><tr><th>Nome</th><th>Ramo</th><th>Grupo</th></tr></thead>
                        <tbody>
                        <?php foreach ($membros as $membro): ?>
                            <tr>
                                <td><a href="inscricao-detalhe.php?id=<?= (int) $membro['id'] ?>">
                                    <?= e((string) $membro['nome']) ?></a></td>
                                <td><?= e(ramo_rotulo((string) $membro['ramo'])) ?></td>
                                <td><?= e((string) $membro['grupo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <details style="margin-top:1rem">
                <summary>Editar patrulha</summary>
                <form method="post" action="patrulhas.php" style="margin-top:1rem">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $patrulha['id'] ?>">
                    <div class="grade grade-3">
                        <div class="campo">
                            <label for="nome-<?= (int) $patrulha['id'] ?>">Nome</label>
                            <input type="text" id="nome-<?= (int) $patrulha['id'] ?>" name="nome" maxlength="40"
                                   value="<?= e((string) $patrulha['nome']) ?>">
                        </div>
                        <div class="campo">
                            <label for="cor-<?= (int) $patrulha['id'] ?>">Cor</label>
                            <input type="color" id="cor-<?= (int) $patrulha['id'] ?>" name="cor"
                                   value="<?= e((string) $patrulha['cor']) ?>">
                        </div>
                        <div class="campo">
                            <label for="vagas-<?= (int) $patrulha['id'] ?>">Vagas</label>
                            <input type="number" id="vagas-<?= (int) $patrulha['id'] ?>" name="vagas"
                                   min="1" max="40" value="<?= (int) $patrulha['vagas'] ?>">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="grito-<?= (int) $patrulha['id'] ?>">Grito</label>
                        <input type="text" id="grito-<?= (int) $patrulha['id'] ?>" name="grito" maxlength="160"
                               value="<?= e((string) $patrulha['grito']) ?>">
                    </div>
                    <div class="acoes">
                        <button type="submit" class="botao" name="acao" value="atualizar">Salvar</button>
                        <button type="submit" class="botao botao-perigo" name="acao" value="excluir"
                                data-confirmar="Excluir a patrulha <?= e((string) $patrulha['nome']) ?>?">
                            Excluir
                        </button>
                    </div>
                </form>
            </details>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php layout_rodape('painel'); ?>
