<?php
/**
 * Cabeçalho e rodapé compartilhados pelas telas públicas e do painel.
 */

declare(strict_types=1);

/** Prefixo para chegar na raiz da aplicacao a partir da área informada. */
function layout_base(string $area): string
{
    return in_array($area, ['painel', 'login'], true) ? '../' : '';
}

/**
 * Abre o HTML.
 *
 * @param 'público'|'painel'|'login' $area  'login' e o painel sem menu (entrar/instalar)
 */
function layout_topo(string $titulo, string $area = 'publico', string $paginaAtual = ''): void
{
    $config = config_todas();
    $base = layout_base($area);
    $nomeEvento = $config['evento_nome'];
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> &middot; <?= e($nomeEvento) ?></title>
<meta name="description" content="<?= e($nomeEvento . ' - ' . $config['evento_lema']) ?>">
<link rel="stylesheet" href="<?= e($base) ?>assets/estilo.css">
</head>
<body class="area-<?= e($area) ?>">
<header class="cabecalho">
    <div class="conteiner cabecalho-interno">
        <a class="marca" href="<?= e($base) ?>index.php">
            <span class="marca-flor" aria-hidden="true">&#9906;</span>
            <span>
                <strong><?= e($nomeEvento) ?></strong>
                <?php $subtitulo = evento_subtitulo(); ?>
                <?php if ($subtitulo !== ''): ?><small><?= e($subtitulo) ?></small><?php endif; ?>
            </span>
        </a>
        <nav class="menu">
            <?php if ($area === 'login'): ?>
                <a href="<?= e($base) ?>index.php">Voltar ao site do evento</a>
            <?php elseif ($area === 'painel'): ?>
                <?php
                $itens = [
                    'painel.php'     => 'Painel',
                    'inscricoes.php' => 'Inscrições',
                    'patrulhas.php'  => 'Patrulhas',
                    'atividades.php' => 'Programação',
                    'pontuacao.php'  => 'Pontuação',
                ];
                if (e_admin()) {
                    $itens['configuracoes.php'] = 'Configurações';
                }
                foreach ($itens as $arquivo => $rotulo): ?>
                    <a href="<?= e($arquivo) ?>"<?= $paginaAtual === $arquivo ? ' class="ativo"' : '' ?>><?= e($rotulo) ?></a>
                <?php endforeach; ?>
                <a href="sair.php" class="sair">Sair</a>
            <?php else: ?>
                <?php
                $itens = [
                    'index.php'       => 'O evento',
                    'programacao.php' => 'Programação',
                    'ranking.php'     => 'Ranking',
                    'consulta.php'    => 'Minha inscrição',
                    'inscricao.php'   => 'Inscreva-se',
                ];
                foreach ($itens as $arquivo => $rotulo): ?>
                    <a href="<?= e($arquivo) ?>"<?= $paginaAtual === $arquivo ? ' class="ativo"' : '' ?>><?= e($rotulo) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="conteiner">
<?php
    foreach (recados() as $recado) {
        printf(
            '<div class="recado recado-%s">%s</div>',
            e($recado['tipo']),
            e($recado['texto'])
        );
    }
}

function layout_rodape(string $area = 'publico'): void
{
    $config = config_todas();
    $base = layout_base($area);
    ?>
</main>
<footer class="rodape">
    <div class="conteiner rodape-interno">
        <?php $local = evento_local_completo(); ?>
        <p><strong><?= e($config['evento_nome']) ?></strong><?= $local !== '' ? ' &middot; ' . e($local) : '' ?></p>
        <p>
            <?php if ($config['contato_email'] !== ''): ?>
                Contato: <a href="mailto:<?= e($config['contato_email']) ?>"><?= e($config['contato_email']) ?></a>
            <?php endif; ?>
            <?php if ($config['contato_whatsapp'] !== ''): ?>
                &middot; WhatsApp: <?= e(telefone_br($config['contato_whatsapp'])) ?>
            <?php endif; ?>
        </p>
        <?php if ($area === 'publico'): ?>
            <p class="rodape-equipe"><a href="admin/index.php">Área da equipe</a></p>
        <?php endif; ?>
    </div>
</footer>
<script src="<?= e($base) ?>assets/app.js" defer></script>
</body>
</html>
<?php
}

/** Mostra o erro de um campo de formulario. */
function erro_campo(array $erros, string $campo): string
{
    if (!isset($erros[$campo])) {
        return '';
    }
    return '<span class="erro-campo">' . e($erros[$campo]) . '</span>';
}

/** Marca a classe de erro no campo. */
function classe_campo(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? ' com-erro' : '';
}
