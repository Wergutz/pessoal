<?php
/**
 * Programação pública do evento.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$agenda = atividades_por_dia(true);

layout_topo('Programação', 'publico', 'programacao.php');
?>

<h1>Programação</h1>
<p><?= e(config_ler('evento_nome')) ?> &middot; <?= e(evento_periodo()) ?></p>

<?php if ($agenda === []): ?>
    <div class="cartao vazio">
        <p>A programação ainda esta sendo montada pela equipe. Volte em breve.</p>
    </div>
<?php else: ?>
    <?php foreach ($agenda as $dia => $atividades): ?>
        <section class="dia">
            <div class="dia-titulo">
                <?= e(data_br($dia)) ?> <small>&middot; <?= e(dia_semana_br($dia)) ?></small>
            </div>
            <?php foreach ($atividades as $atividade): ?>
                <article class="bloco">
                    <div class="horario">
                        <?= e(hora_de((string) $atividade['inicio'])) ?><br>
                        <small><?= e(hora_de((string) $atividade['fim'])) ?></small>
                    </div>
                    <div class="detalhe">
                        <strong><?= e((string) $atividade['titulo']) ?></strong>
                        <?php if ((int) $atividade['pontuavel'] === 1): ?>
                            <span class="etiqueta etiqueta-confirmada">vale pontos</span>
                        <?php endif; ?>
                        <?php if ((string) $atividade['local'] !== ''): ?>
                            <div class="local">Local: <?= e((string) $atividade['local']) ?></div>
                        <?php endif; ?>
                        <?php if ((string) $atividade['descricao'] !== ''): ?>
                            <p><?= nl2br(e((string) $atividade['descricao'])) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php layout_rodape('publico'); ?>
