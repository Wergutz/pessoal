<?php
/**
 * Painel com o panorama do evento.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$usuario = exigir_login();
$config = config_todas();
$resumo = inscricoes_resumo();
$situacao = inscricoes_situacao();
$dias = evento_dias_restantes();
$patrulhas = patrulhas_listar();
$ranking = pontuacao_ranking();

$ultimas = array_slice(inscricoes_listar(), 0, 8);

layout_topo('Painel', 'painel', 'painel.php');
?>

<h1>Ola, <?= e(explode(' ', $usuario['nome'])[0]) ?>!</h1>
<?php $pendencias = config_pendencias(); ?>

<?php if ($pendencias !== []): ?>
    <div class="recado recado-aviso">
        <strong>O evento ainda não está configurado.</strong>
        Falta definir: <?= e(implode(', ', $pendencias)) ?>.
        As inscrições ficam fechadas até lá, para o site não mostrar
        informação que ainda não foi decidida.
        <?php if (e_admin()): ?>
            <br><a href="configuracoes.php">Configurar agora</a>
        <?php else: ?>
            <br>Peça a um administrador para preencher em Configurações.
        <?php endif; ?>
    </div>
<?php endif; ?>

<p>
    <?= e($config['evento_nome']) ?><?= evento_periodo() !== ''
        ? ' &middot; ' . e(evento_periodo()) : '' ?> &middot;
    <?php if ($dias === null): ?>
        data ainda não definida
    <?php elseif ($dias > 0): ?>
        faltam <strong><?= $dias ?></strong> dias
    <?php elseif ($dias === 0): ?>
        <strong>e hoje!</strong>
    <?php else: ?>
        evento realizado
    <?php endif; ?>
</p>

<div class="grade grade-4">
    <div class="indicador">
        <span class="numero"><?= (int) $resumo['por_status']['confirmada'] ?></span>
        <span class="rotulo">confirmadas</span>
    </div>
    <div class="indicador">
        <span class="numero"><?= (int) $resumo['por_status']['pendente'] ?></span>
        <span class="rotulo">pendentes</span>
    </div>
    <div class="indicador">
        <span class="numero"><?= e(vagas_texto($situacao['vagas_restantes'])) ?></span>
        <span class="rotulo"><?= (int) $config['vagas_total'] > 0
            ? 'vagas restantes de ' . (int) $config['vagas_total']
            : 'vagas (total não definido)' ?></span>
    </div>
    <div class="indicador">
        <span class="numero"><?= (int) $resumo['grupos'] ?></span>
        <span class="rotulo">grupos escoteiros</span>
    </div>
</div>

<?php if ($resumo['sem_patrulha'] > 0): ?>
    <div class="recado recado-aviso" style="margin-top:1rem">
        <strong><?= (int) $resumo['sem_patrulha'] ?></strong> inscrito(s) confirmado(s) ainda sem patrulha.
        <a href="patrulhas.php">Distribuir agora</a>.
    </div>
<?php endif; ?>

<?php if (!$situacao['aberta'] && $situacao['motivo'] !== 'sem_configuracao'): ?>
    <div class="recado recado-aviso" style="margin-top:1rem">
        Inscrições fechadas: <?= e(inscricoes_motivo_texto($situacao['motivo'])) ?>
    </div>
<?php endif; ?>

<div class="grade grade-2">
    <div class="cartao">
        <h2 style="margin-top:0">Inscritos por ramo</h2>
        <table>
            <tbody>
            <?php foreach (ramos_disponiveis() as $codigo => $ramo): ?>
                <tr>
                    <td><?= e($ramo['rotulo']) ?></td>
                    <td class="numero"><strong><?= (int) ($resumo['por_ramo'][$codigo] ?? 0) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0">Logistica</h2>
        <table>
            <tbody>
                <tr>
                    <td>Restrições alimentares registradas</td>
                    <td class="numero"><strong><?= (int) $resumo['restricoes'] ?></strong></td>
                </tr>
                <?php foreach ($resumo['camisas'] as $tamanho => $quantidade): ?>
                    <tr>
                        <td>Camisas tamanho <?= e((string) $tamanho) ?></td>
                        <td class="numero"><strong><?= (int) $quantidade ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grade grade-2">
    <div class="cartao">
        <h2 style="margin-top:0">Últimas inscrições</h2>
        <?php if ($ultimas === []): ?>
            <p class="vazio">Nenhuma inscrição recebida ainda.</p>
        <?php else: ?>
            <div class="rolagem">
                <table>
                    <thead>
                        <tr><th>Protocolo</th><th>Nome</th><th>Ramo</th><th>Situação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas as $inscricao): ?>
                            <tr>
                                <td><a href="inscricao-detalhe.php?id=<?= (int) $inscricao['id'] ?>">
                                    <?= e((string) $inscricao['protocolo']) ?></a></td>
                                <td><?= e((string) $inscricao['nome']) ?></td>
                                <td><?= e(ramo_rotulo((string) $inscricao['ramo'])) ?></td>
                                <td><span class="etiqueta etiqueta-<?= e((string) $inscricao['status']) ?>">
                                    <?= e(status_rotulo((string) $inscricao['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="acoes"><a class="botao botao-secundario" href="inscricoes.php">Ver todas</a></div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0">Pontuação</h2>
        <?php if ($ranking === []): ?>
            <p class="vazio">Nenhuma patrulha cadastrada. <a href="patrulhas.php">Criar patrulhas</a>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th class="numero">#</th><th>Patrulha</th><th class="numero">Membros</th><th class="numero">Pontos</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($ranking, 0, 6) as $item): ?>
                        <tr>
                            <td class="numero"><?= (int) $item['posicao'] ?></td>
                            <td><span class="ponto-patrulha" style="background: <?= e($item['cor']) ?>"></span>
                                <?= e($item['nome']) ?></td>
                            <td class="numero"><?= (int) $item['membros'] ?></td>
                            <td class="numero"><strong><?= (int) $item['total'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="acoes"><a class="botao botao-secundario" href="pontuacao.php">Lancar pontos</a></div>
        <?php endif; ?>
    </div>
</div>

<?php layout_rodape('painel'); ?>
