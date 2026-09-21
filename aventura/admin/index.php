<?php
/**
 * Login da equipe organizadora.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (usuarios_total() === 0) {
    redirecionar('../instalar.php');
}

if (usuario_logado() !== null) {
    redirecionar('painel.php');
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    if (!limite_envio_ok('login', 8, 300)) {
        $erro = 'Muitas tentativas seguidas. Aguarde alguns minutos.';
    } else {
        $email = post('email');
        $usuario = usuario_autenticar($email, (string) ($_POST['senha'] ?? ''));

        if ($usuario === null) {
            $erro = 'E-mail ou senha inválidos.';
        } else {
            sessao_abrir($usuario);
            $retorno = get('retorno');
            // So aceita caminhos internos do painel, nunca URLs externas.
            $destino = preg_match('/^[a-z0-9_-]+\.php(\?[\w=&%.-]*)?$/i', $retorno) === 1
                ? $retorno
                : 'painel.php';
            redirecionar($destino);
        }
    }
}

layout_topo('Área da equipe', 'login');
?>

<div class="caixa-login">
    <div class="cartao">
        <h1>Área da equipe</h1>
        <p><?= e(config_ler('evento_nome')) ?></p>

        <?php if ($erro !== ''): ?>
            <div class="recado recado-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php<?= get('retorno') !== ''
            ? '?retorno=' . e(rawurlencode(get('retorno'))) : '' ?>">
            <?= csrf_campo() ?>
            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="username"
                       value="<?= e($email) ?>">
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required autocomplete="current-password">
            </div>
            <button type="submit" class="botao">Entrar</button>
        </form>

        <p style="margin-top:1rem"><a href="../index.php">Voltar ao site do evento</a></p>
    </div>
</div>

<?php layout_rodape('login'); ?>
