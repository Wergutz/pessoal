<?php
/**
 * Página inicial do evento.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$config = config_todas();
$situacao = inscricoes_situacao();
$dias = evento_dias_restantes();
$resumo = inscricoes_resumo();

layout_topo('O evento', 'publico', 'index.php');
?>

<section class="heroi">
    <p class="lema"><?= e($config['evento_lema']) ?></p>
    <h1><?= e($config['evento_nome']) ?></h1>
    <p>
        <strong><?= e(evento_periodo()) ?></strong> &middot;
        <?= e($config['evento_local']) ?>, <?= e($config['evento_cidade']) ?>/<?= e($config['evento_uf']) ?>
    </p>
    <p><?= e($config['texto_apresentacao']) ?></p>
    <div class="acoes">
        <?php if ($situacao['aberta']): ?>
            <a class="botao" href="inscricao.php">Fazer minha inscrição</a>
        <?php else: ?>
            <span class="botao" aria-disabled="true" style="opacity:.6">Inscrições encerradas</span>
        <?php endif; ?>
        <a class="botao botao-secundario" href="programacao.php">Ver a programação</a>
    </div>
</section>

<div class="grade grade-4">
    <div class="indicador">
        <span class="numero"><?= $dias === null ? '-' : ($dias > 0 ? $dias : 0) ?></span>
        <span class="rotulo"><?= $dias !== null && $dias < 0 ? 'evento realizado' : 'dias para o evento' ?></span>
    </div>
    <div class="indicador">
        <span class="numero"><?= (int) ($resumo['por_status']['confirmada'] + $resumo['por_status']['pendente']) ?></span>
        <span class="rotulo">inscritos até agora</span>
    </div>
    <div class="indicador">
        <span class="numero"><?= (int) $resumo['grupos'] ?></span>
        <span class="rotulo">grupos escoteiros</span>
    </div>
    <div class="indicador">
        <span class="numero"><?= (int) $situacao['vagas_restantes'] ?></span>
        <span class="rotulo">vagas disponíveis</span>
    </div>
</div>

<?php if (!$situacao['aberta']): ?>
    <div class="recado recado-aviso" style="margin-top:1.25rem">
        <?= e(inscricoes_motivo_texto($situacao['motivo'])) ?>
    </div>
<?php endif; ?>

<div class="grade grade-2" style="margin-top:1.5rem">
    <div class="cartao">
        <h2 style="margin-top:0">Como participar</h2>
        <ol>
            <li>Preencha a ficha de inscrição com os dados do participante.</li>
            <li>Guarde o protocolo que aparece ao final &mdash; ele serve para consultar a inscrição.</li>
            <li>Faca o pagamento da taxa de <strong>R$ <?= e($config['evento_valor']) ?></strong> conforme
                orientação enviada pela equipe.</li>
            <li>A inscrição passa para <em>confirmada</em> assim que a equipe registra o pagamento.</li>
            <li>Na chegada ao campo, apresente o protocolo e a autorização assinada.</li>
        </ol>
        <p class="ajuda">Prazo de inscrição: <strong><?= e(data_br($config['inscricoes_ate'])) ?></strong>.</p>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0">O que levar</h2>
        <p><?= e($config['texto_o_que_levar']) ?></p>
        <h3>Ramos participantes</h3>
        <ul>
            <?php foreach (RAMOS as $codigo => $ramo): ?>
                <li><?= e($ramo['rotulo']) ?>
                    <small>(<?= (int) $ramo['min'] ?> a <?= $codigo === 'adulto' ? '+' : (int) $ramo['max'] ?> anos)</small>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php layout_rodape('publico'); ?>
