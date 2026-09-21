<?php
/**
 * Lancamento de pontos por patrulha e atividade.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $atividadeId = (int) post('atividade_id');
    $pontos = (array) ($_POST['pontos'] ?? []);
    $observacoes = (array) ($_POST['observacao'] ?? []);

    $lancados = 0;
    $erros = [];

    foreach ($pontos as $patrulhaId => $valor) {
        $patrulhaId = (int) $patrulhaId;
        $valor = is_string($valor) ? trim($valor) : '';

        if ($valor === '') {
            pontuacao_remover($patrulhaId, $atividadeId);
            continue;
        }
        if (!preg_match('/^-?\d{1,4}$/', $valor)) {
            $erros[] = 'Pontuação inválida para a patrulha #' . $patrulhaId . '.';
            continue;
        }

        $observacao = (string) ($observacoes[$patrulhaId] ?? '');
        $resultado = pontuacao_lancar($patrulhaId, $atividadeId, (int) $valor, $observacao);

        if ($resultado['ok']) {
            $lancados++;
        } else {
            $erros = array_merge($erros, $resultado['erros']);
        }
    }

    if ($erros !== []) {
        recado('erro', implode(' ', array_unique($erros)));
    }
    recado('ok', $lancados . ' lancamento(s) gravado(s).');

    redirecionar('pontuacao.php?atividade=' . $atividadeId);
}

$atividades = atividades_pontuaveis();
$patrulhas = patrulhas_listar();
$matriz = pontuacao_matriz();
$ranking = pontuacao_ranking();

$atividadeId = (int) get('atividade');
if ($atividadeId === 0 && $atividades !== []) {
    $atividadeId = (int) $atividades[0]['id'];
}
$atividade = $atividadeId > 0 ? atividade_por_id($atividadeId) : null;

layout_topo('Pontuação', 'painel', 'pontuacao.php');
?>

<h1>Pontuação</h1>

<?php if ($atividades === []): ?>
    <div class="cartao vazio">
        <p>Nenhuma atividade marcada como pontuável.
           <a href="atividades.php">Cadastre uma na programação</a> e marque a opção
           <em>vale pontos</em>.</p>
    </div>
<?php elseif ($patrulhas === []): ?>
    <div class="cartao vazio">
        <p>Nenhuma patrulha cadastrada. <a href="patrulhas.php">Crie as patrulhas</a> antes de lancar pontos.</p>
    </div>
<?php else: ?>

    <form method="get" action="pontuacao.php" class="cartao">
        <div class="filtros">
            <div class="campo" style="min-width:280px">
                <label for="atividade">Atividade</label>
                <select id="atividade" name="atividade" onchange="this.form.submit()">
                    <?php foreach ($atividades as $item): ?>
                        <option value="<?= (int) $item['id'] ?>"
                            <?= $atividadeId === (int) $item['id'] ? ' selected' : '' ?>>
                            <?= e(data_br((string) $item['inicio'])) ?>
                            <?= e(hora_de((string) $item['inicio'])) ?> &middot;
                            <?= e((string) $item['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="botao botao-secundario">Trocar</button>
        </div>
    </form>

    <?php if ($atividade !== null): ?>
        <form method="post" action="pontuacao.php" class="cartao">
            <?= csrf_campo() ?>
            <input type="hidden" name="atividade_id" value="<?= (int) $atividade['id'] ?>">
            <h2 style="margin-top:0"><?= e((string) $atividade['titulo']) ?></h2>
            <p><?= e(data_hora_br((string) $atividade['inicio'])) ?>
               &ndash; <?= e(hora_de((string) $atividade['fim'])) ?>
               <?php if ((string) $atividade['local'] !== ''): ?>
                   &middot; <?= e((string) $atividade['local']) ?>
               <?php endif; ?>
            </p>

            <div class="rolagem">
                <table>
                    <thead>
                        <tr><th>Patrulha</th><th class="numero">Pontos</th><th>Observação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patrulhas as $patrulha): ?>
                            <?php
                            $id = (int) $patrulha['id'];
                            $nota = $matriz[$id][(int) $atividade['id']] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <span class="ponto-patrulha" style="background: <?= e((string) $patrulha['cor']) ?>"></span>
                                    <?= e((string) $patrulha['nome']) ?>
                                    <small>(<?= (int) $patrulha['membros'] ?>)</small>
                                </td>
                                <td class="numero" style="max-width:110px">
                                    <input type="number" name="pontos[<?= $id ?>]" min="-1000" max="1000"
                                           value="<?= $nota === null ? '' : (int) $nota['pontos'] ?>"
                                           aria-label="Pontos da patrulha <?= e((string) $patrulha['nome']) ?>">
                                </td>
                                <td>
                                    <input type="text" name="observacao[<?= $id ?>]" maxlength="200"
                                           value="<?= e($nota === null ? '' : $nota['observacao']) ?>"
                                           aria-label="Observação da patrulha <?= e((string) $patrulha['nome']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="acoes">
                <button type="submit" class="botao">Gravar pontuação</button>
                <span class="ajuda">Deixar o campo de pontos em branco apaga o lancamento da patrulha.</span>
            </div>
        </form>
    <?php endif; ?>

    <div class="cartao rolagem">
        <h2 style="margin-top:0">Classificação geral</h2>
        <table>
            <thead>
                <tr>
                    <th class="numero">#</th><th>Patrulha</th>
                    <?php foreach ($atividades as $item): ?>
                        <th class="numero" title="<?= e((string) $item['titulo']) ?>">
                            <?= e(mb_strimwidth((string) $item['titulo'], 0, 14, '...')) ?></th>
                    <?php endforeach; ?>
                    <th class="numero">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $linha): ?>
                    <tr>
                        <td class="numero"><?= (int) $linha['posicao'] ?></td>
                        <td><span class="ponto-patrulha" style="background: <?= e($linha['cor']) ?>"></span>
                            <?= e($linha['nome']) ?></td>
                        <?php foreach ($atividades as $item): ?>
                            <?php $nota = $matriz[$linha['id']][(int) $item['id']] ?? null; ?>
                            <td class="numero" title="<?= e($nota['observacao'] ?? '') ?>">
                                <?= $nota === null ? '&ndash;' : (int) $nota['pontos'] ?></td>
                        <?php endforeach; ?>
                        <td class="numero"><strong><?= (int) $linha['total'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="acoes">
            <a class="botao botao-secundario" href="../ranking.php">Ver o quadro público</a>
        </div>
    </div>
<?php endif; ?>

<?php layout_rodape('painel'); ?>
