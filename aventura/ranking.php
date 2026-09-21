<?php
/**
 * Quadro de pontuação das patrulhas.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$ranking = pontuacao_ranking();
$atividades = atividades_pontuaveis();
$matriz = pontuacao_matriz();

layout_topo('Ranking', 'publico', 'ranking.php');
?>

<h1>Quadro de pontuação</h1>
<p>Classificação das patrulhas da <?= e(config_ler('evento_nome')) ?>.</p>

<?php if ($ranking === []): ?>
    <div class="cartao vazio">
        <p>As patrulhas ainda não foram formadas. O quadro aparece assim que a equipe montar as equipes.</p>
    </div>
<?php else: ?>

    <div class="podio">
        <?php foreach (array_slice($ranking, 0, 3) as $item): ?>
            <div class="podio-item" style="border-top-color: <?= e($item['cor']) ?>">
                <div class="posicao"><?= (int) $item['posicao'] ?>&ordm; lugar</div>
                <div class="nome"><?= e($item['nome']) ?></div>
                <div class="pontos"><?= (int) $item['total'] ?></div>
                <div class="posicao"><?= (int) $item['provas'] ?> prova(s)</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cartao rolagem">
        <table>
            <thead>
                <tr>
                    <th class="numero">#</th>
                    <th>Patrulha</th>
                    <?php foreach ($atividades as $atividade): ?>
                        <th class="numero" title="<?= e((string) $atividade['titulo']) ?>">
                            <?= e(mb_strimwidth((string) $atividade['titulo'], 0, 14, '...')) ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="numero">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $item): ?>
                    <tr>
                        <td class="numero"><?= (int) $item['posicao'] ?></td>
                        <td>
                            <span class="ponto-patrulha" style="background: <?= e($item['cor']) ?>"></span>
                            <?= e($item['nome']) ?>
                            <small>(<?= (int) $item['membros'] ?>)</small>
                        </td>
                        <?php foreach ($atividades as $atividade): ?>
                            <?php $nota = $matriz[$item['id']][(int) $atividade['id']] ?? null; ?>
                            <td class="numero"><?= $nota === null ? '&ndash;' : (int) $nota['pontos'] ?></td>
                        <?php endforeach; ?>
                        <td class="numero"><strong><?= (int) $item['total'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($atividades === []): ?>
        <p class="vazio">Nenhuma atividade pontuável cadastrada ainda.</p>
    <?php endif; ?>
<?php endif; ?>

<?php layout_rodape('publico'); ?>
