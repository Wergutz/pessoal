<?php
/**
 * Consulta pública de inscrição pelo protocolo.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$config = config_todas();
$protocolo = get('protocolo');
$nova = get('nova') === '1';
$inscricao = null;
$naoEncontrada = false;

if ($protocolo !== '') {
    $inscricao = inscricao_por_protocolo($protocolo);
    $naoEncontrada = $inscricao === null;
}

layout_topo('Minha inscrição', 'publico', 'consulta.php');
?>

<?php if ($inscricao !== null && $nova): ?>
    <div class="recado recado-ok">
        <strong>Inscrição recebida!</strong> Anote o protocolo abaixo &mdash; ele e a forma de
        consultar a situação da sua inscrição.
    </div>
<?php endif; ?>

<h1>Minha inscrição</h1>

<form method="get" action="consulta.php" class="cartao">
    <div class="filtros">
        <div class="campo">
            <label for="protocolo">Protocolo</label>
            <input type="search" id="protocolo" name="protocolo" placeholder="AE26-XXXXXX"
                   value="<?= e($protocolo) ?>" autocomplete="off">
        </div>
        <button type="submit" class="botao">Consultar</button>
    </div>
</form>

<?php if ($naoEncontrada): ?>
    <div class="recado recado-erro">
        Nenhuma inscrição encontrada com o protocolo <strong><?= e($protocolo) ?></strong>.
        Confira o código ou fale com a equipe
        <?php if ($config['contato_email'] !== ''): ?>
            pelo e-mail <a href="mailto:<?= e($config['contato_email']) ?>"><?= e($config['contato_email']) ?></a>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($inscricao !== null): ?>
    <?php
    $status = (string) $inscricao['status'];
    $idade = idade_em((string) $inscricao['nascimento'], $config['evento_inicio']);
    ?>
    <div class="cartao">
        <p class="protocolo"><?= e((string) $inscricao['protocolo']) ?></p>

        <div class="grade grade-2" style="margin-top:1.25rem">
            <dl class="lista-dados">
                <dt>Participante</dt>
                <dd><?= e((string) $inscricao['nome']) ?></dd>

                <dt>Ramo</dt>
                <dd><?= e(ramo_rotulo((string) $inscricao['ramo'])) ?>
                    <?php if ($idade !== null): ?>
                        <small>(<?= $idade ?> anos no início do evento)</small>
                    <?php endif; ?>
                </dd>

                <dt>Grupo escoteiro</dt>
                <dd><?= e((string) $inscricao['grupo']) ?> &mdash;
                    <?= e((string) $inscricao['cidade']) ?>/<?= e((string) $inscricao['uf']) ?></dd>
            </dl>

            <dl class="lista-dados">
                <dt>Situação</dt>
                <dd><span class="etiqueta etiqueta-<?= e($status) ?>"><?= e(status_rotulo($status)) ?></span></dd>

                <dt>Patrulha</dt>
                <dd><?= $inscricao['patrulha_nome'] !== null
                        ? e((string) $inscricao['patrulha_nome'])
                        : '<small>a definir pela equipe</small>' ?></dd>

                <dt>Inscrição enviada em</dt>
                <dd><?= e(data_hora_br((string) $inscricao['criado_em'])) ?></dd>
            </dl>
        </div>

        <?php if ($status === 'pendente'): ?>
            <div class="recado recado-aviso">
                Sua inscrição esta registrada e aguarda a confirmação do pagamento da taxa de
                <strong>R$ <?= e($config['evento_valor']) ?></strong> pela equipe organizadora.
            </div>
        <?php elseif ($status === 'confirmada'): ?>
            <div class="recado recado-ok">
                Inscrição confirmada. Nos vemos em <?= e($config['evento_local']) ?>,
                <?= e(evento_periodo()) ?>. Sempre alerta!
            </div>
        <?php else: ?>
            <div class="recado recado-erro">
                Esta inscrição foi cancelada. Se isso não esta certo, fale com a equipe organizadora.
            </div>
        <?php endif; ?>

        <div class="acoes">
            <a class="botao botao-secundario" href="programacao.php">Programação</a>
            <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
        </div>
    </div>
<?php endif; ?>

<?php layout_rodape('publico'); ?>
