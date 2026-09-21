<?php
/**
 * Lista e gestão das inscrições.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $acao = post('acao');
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

    if ($ids === []) {
        recado('aviso', 'Selecione ao menos uma inscrição.');
    } elseif (isset(STATUS_INSCRICAO[$acao])) {
        $alteradas = 0;
        foreach ($ids as $id) {
            $alteradas += inscricao_mudar_status($id, $acao) ? 1 : 0;
        }
        recado('ok', $alteradas . ' inscrição(oes) marcada(s) como ' . status_rotulo($acao) . '.');
    } elseif ($acao === 'patrulha') {
        $patrulhaId = post('patrulha_id');
        $destino = $patrulhaId === '' ? null : (int) $patrulhaId;
        $movidas = 0;
        foreach ($ids as $id) {
            $movidas += inscricao_definir_patrulha($id, $destino) ? 1 : 0;
        }
        recado('ok', $movidas . ' inscrição(oes) atualizada(s).');
    } else {
        recado('erro', 'Ação desconhecida.');
    }

    redirecionar('inscricoes.php?' . http_build_query([
        'busca'    => post('filtro_busca'),
        'status'   => post('filtro_status'),
        'ramo'     => post('filtro_ramo'),
        'patrulha' => post('filtro_patrulha'),
    ]));
}

$filtros = [
    'busca'    => get('busca'),
    'status'   => get('status'),
    'ramo'     => get('ramo'),
    'patrulha' => get('patrulha'),
];

$inscricoes = inscricoes_listar($filtros);
$patrulhas = patrulhas_listar();

layout_topo('Inscrições', 'painel', 'inscricoes.php');
?>

<h1>Inscrições <small>(<?= count($inscricoes) ?>)</small></h1>

<form method="get" action="inscricoes.php" class="cartao">
    <div class="filtros">
        <div class="campo">
            <label for="busca">Buscar</label>
            <input type="search" id="busca" name="busca" placeholder="nome, protocolo, grupo, e-mail"
                   value="<?= e($filtros['busca']) ?>">
        </div>
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="">Todas</option>
                <?php foreach (STATUS_INSCRICAO as $codigo => $rotulo): ?>
                    <option value="<?= e($codigo) ?>"<?= $filtros['status'] === $codigo ? ' selected' : '' ?>>
                        <?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="ramo">Ramo</label>
            <select id="ramo" name="ramo">
                <option value="">Todos</option>
                <?php foreach (RAMOS as $codigo => $ramo): ?>
                    <option value="<?= e($codigo) ?>"<?= $filtros['ramo'] === $codigo ? ' selected' : '' ?>>
                        <?= e($ramo['rotulo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="patrulha">Patrulha</label>
            <select id="patrulha" name="patrulha">
                <option value="">Todas</option>
                <option value="sem"<?= $filtros['patrulha'] === 'sem' ? ' selected' : '' ?>>Sem patrulha</option>
                <?php foreach ($patrulhas as $patrulha): ?>
                    <option value="<?= (int) $patrulha['id'] ?>"
                        <?= $filtros['patrulha'] === (string) $patrulha['id'] ? ' selected' : '' ?>>
                        <?= e((string) $patrulha['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="botao">Filtrar</button>
        <a class="botao botao-secundario" href="inscricoes.php">Limpar</a>
        <a class="botao botao-secundario"
           href="exportar.php?<?= e(http_build_query($filtros)) ?>">Exportar CSV</a>
    </div>
</form>

<?php if ($inscricoes === []): ?>
    <div class="cartao vazio"><p>Nenhuma inscrição encontrada com esses filtros.</p></div>
<?php else: ?>
<form method="post" action="inscricoes.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="filtro_busca" value="<?= e($filtros['busca']) ?>">
    <input type="hidden" name="filtro_status" value="<?= e($filtros['status']) ?>">
    <input type="hidden" name="filtro_ramo" value="<?= e($filtros['ramo']) ?>">
    <input type="hidden" name="filtro_patrulha" value="<?= e($filtros['patrulha']) ?>">

    <div class="cartao rolagem">
        <table id="tabela-inscricoes">
            <thead>
                <tr>
                    <th><input type="checkbox" id="marcar-todos" aria-label="Selecionar todos"></th>
                    <th>Protocolo</th>
                    <th>Nome</th>
                    <th>Ramo</th>
                    <th>Grupo</th>
                    <th>Patrulha</th>
                    <th>Situação</th>
                    <th>Enviada</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inscricoes as $inscricao): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $inscricao['id'] ?>"
                                   aria-label="Selecionar <?= e((string) $inscricao['nome']) ?>"></td>
                        <td><a href="inscricao-detalhe.php?id=<?= (int) $inscricao['id'] ?>">
                            <?= e((string) $inscricao['protocolo']) ?></a></td>
                        <td>
                            <?= e((string) $inscricao['nome']) ?>
                            <?php if ((string) $inscricao['restricao_alimentar'] !== ''): ?>
                                <span title="Possui restrição alimentar" aria-label="Possui restrição alimentar">&#9888;</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(ramo_rotulo((string) $inscricao['ramo'])) ?></td>
                        <td><?= e((string) $inscricao['grupo']) ?><br>
                            <small><?= e((string) $inscricao['cidade']) ?>/<?= e((string) $inscricao['uf']) ?></small></td>
                        <td><?= $inscricao['patrulha_nome'] !== null
                                ? e((string) $inscricao['patrulha_nome']) : '<small>&ndash;</small>' ?></td>
                        <td><span class="etiqueta etiqueta-<?= e((string) $inscricao['status']) ?>">
                            <?= e(status_rotulo((string) $inscricao['status'])) ?></span></td>
                        <td><small><?= e(data_hora_br((string) $inscricao['criado_em'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cartao">
        <h3 style="margin-top:0">Ações para os selecionados</h3>
        <div class="filtros">
            <button type="submit" class="botao" name="acao" value="confirmada">Confirmar</button>
            <button type="submit" class="botao botao-secundario" name="acao" value="pendente">Marcar pendente</button>
            <button type="submit" class="botao botao-perigo" name="acao" value="cancelada"
                    data-confirmar="Cancelar as inscrições selecionadas?">Cancelar</button>
            <div class="campo">
                <label for="patrulha_id">Mover para patrulha</label>
                <select id="patrulha_id" name="patrulha_id">
                    <option value="">Sem patrulha</option>
                    <?php foreach ($patrulhas as $patrulha): ?>
                        <option value="<?= (int) $patrulha['id'] ?>"><?= e((string) $patrulha['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="botao botao-secundario" name="acao" value="patrulha">Mover</button>
        </div>
    </div>
</form>

<script>
document.getElementById('marcar-todos').addEventListener('change', function () {
    var marcado = this.checked;
    document.querySelectorAll('#tabela-inscricoes tbody input[name="ids[]"]').forEach(function (caixa) {
        if (!caixa.closest('tr').hidden) { caixa.checked = marcado; }
    });
});
</script>
<?php endif; ?>

<?php layout_rodape('painel'); ?>
