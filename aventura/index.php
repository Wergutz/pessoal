<?php
/**
 * Pagina inicial do evento.
 *
 * Enquanto a equipe não preencher as informações basicas, a pagina mostra
 * um aviso neutro em vez de inventar data, local ou valor.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$config = config_todas();
$situacao = inscricoes_situacao();
$configurado = evento_configurado();
$dias = evento_dias_restantes();
$resumo = inscricoes_resumo();
$local = evento_local_completo();
$periodo = evento_periodo();

layout_topo('O evento', 'publico', 'index.php');
?>

<section class="heroi">
    <?php if ($config['evento_lema'] !== ''): ?>
        <p class="lema"><?= e($config['evento_lema']) ?></p>
    <?php endif; ?>

    <h1><?= e($config['evento_nome']) ?></h1>

    <?php if ($periodo !== '' || $local !== ''): ?>
        <p>
            <?php if ($periodo !== ''): ?><strong><?= e($periodo) ?></strong><?php endif; ?>
            <?php if ($periodo !== '' && $local !== ''): ?> &middot; <?php endif; ?>
            <?= e($local) ?>
        </p>
    <?php endif; ?>

    <?php if ($config['texto_apresentacao'] !== ''): ?>
        <p><?= nl2br(e($config['texto_apresentacao'])) ?></p>
    <?php elseif (!$configurado): ?>
        <p>Estamos preparando a próxima aventura. As informações de data, local
           e inscrição serão publicadas aqui em breve.</p>
    <?php endif; ?>

    <div class="acoes">
        <?php if ($situacao['aberta']): ?>
            <a class="botao" href="inscricao.php">Fazer minha inscrição</a>
        <?php endif; ?>
        <a class="botao botao-secundario" href="programacao.php">Ver a programação</a>
        <a class="botao botao-secundario" href="consulta.php">Consultar inscrição</a>
    </div>
</section>

<?php if (!$situacao['aberta']): ?>
    <div class="recado recado-aviso">
        <?= e(inscricoes_motivo_texto($situacao['motivo'])) ?>
    </div>
<?php endif; ?>

<?php if ($configurado || $resumo['total'] > 0): ?>
    <div class="grade grade-4">
        <?php if ($dias !== null): ?>
            <div class="indicador">
                <span class="numero"><?= $dias > 0 ? $dias : 0 ?></span>
                <span class="rotulo"><?= $dias < 0 ? 'evento realizado' : 'dias para o evento' ?></span>
            </div>
        <?php endif; ?>
        <div class="indicador">
            <span class="numero"><?= (int) ($resumo['por_status']['confirmada'] + $resumo['por_status']['pendente']) ?></span>
            <span class="rotulo">inscritos até agora</span>
        </div>
        <div class="indicador">
            <span class="numero"><?= (int) $resumo['grupos'] ?></span>
            <span class="rotulo">grupos escoteiros</span>
        </div>
        <?php if ((int) $config['vagas_total'] > 0): ?>
            <div class="indicador">
                <span class="numero"><?= e(vagas_texto($situacao['vagas_restantes'])) ?></span>
                <span class="rotulo">vagas disponíveis</span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grade grade-2" style="margin-top:1.5rem">
    <div class="cartao">
        <h2 style="margin-top:0">Como participar</h2>
        <ol>
            <li>Preencha a ficha de inscrição com os dados do participante.</li>
            <li>Guarde o protocolo que aparece ao final &mdash; ele serve para consultar a inscrição.</li>
            <?php if ($config['evento_valor'] !== ''): ?>
                <li>Faça o pagamento da taxa de <strong>R$ <?= e($config['evento_valor']) ?></strong>
                    conforme orientação enviada pela equipe.</li>
            <?php else: ?>
                <li>Siga as orientações de pagamento enviadas pela equipe organizadora.</li>
            <?php endif; ?>
            <li>A inscrição passa para <em>confirmada</em> assim que a equipe registra o pagamento.</li>
            <li>Na chegada ao campo, apresente o protocolo e a autorização assinada.</li>
        </ol>
        <?php if ($config['inscricoes_ate'] !== ''): ?>
            <p class="ajuda">Prazo de inscrição:
               <strong><?= e(data_br($config['inscricoes_ate'])) ?></strong>.</p>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <?php if ($config['texto_o_que_levar'] !== ''): ?>
            <h2 style="margin-top:0">O que levar</h2>
            <p><?= nl2br(e($config['texto_o_que_levar'])) ?></p>
        <?php endif; ?>

        <h2<?= $config['texto_o_que_levar'] === '' ? ' style="margin-top:0"' : '' ?>>Quem pode participar</h2>
        <ul>
            <?php foreach (ramos_disponiveis() as $codigo => $ramo): ?>
                <li><?= e($ramo['rotulo']) ?>
                    <?php if (validar_idade_ligado()): ?>
                        <?php $faixa = $ramo['max'] >= 100
                            ? $ramo['min'] . ' anos ou mais'
                            : $ramo['min'] . ' a ' . $ramo['max'] . ' anos'; ?>
                        <small>(<?= e($faixa) ?>)</small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php layout_rodape('publico'); ?>
