<?php
/**
 * Instalação inicial: cria o primeiro usuário da equipe.
 *
 * A página so funciona enquanto não existe nenhum usuário cadastrado.
 * Depois disso ela passa a recusar qualquer acesso, entao pode ficar no
 * servidor sem risco. Novos membros da equipe são criados pelo painel.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

if (usuarios_total() > 0) {
    http_response_code(403);
    layout_topo('Instalação', 'publico');
    echo '<div class="cartao"><h1>Instalação já concluida</h1>'
       . '<p>O sistema já possui usuários cadastrados. Acesse a '
       . '<a href="admin/index.php">área da equipe</a>.</p></div>';
    layout_rodape('publico');
    exit;
}

$erros = [];
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $nome = post('nome');
    $email = post('email');
    $senha = $_POST['senha'] ?? '';
    $confirmacao = $_POST['senha_confirmacao'] ?? '';

    if ($senha !== $confirmacao) {
        $erros[] = 'As senhas não conferem.';
    }

    if ($erros === []) {
        $resultado = usuario_criar($nome, $email, (string) $senha, 'admin');
        if ($resultado['ok']) {
            recado('ok', 'Usuário criado. Entre e preencha as informações do evento em Configurações — '
                       . 'o sistema começa sem data, local nem valor definidos.');
            redirecionar('admin/index.php');
        }
        $erros = $resultado['erros'];
    }
}

layout_topo('Instalação', 'publico');
?>

<div class="caixa-login">
    <div class="cartao">
        <h1>Primeiro acesso</h1>
        <p>Crie o usuário administrador da <?= e(config_ler('evento_nome')) ?>.</p>

        <?php foreach ($erros as $erro): ?>
            <div class="recado recado-erro"><?= e($erro) ?></div>
        <?php endforeach; ?>

        <form method="post" action="instalar.php">
            <?= csrf_campo() ?>
            <div class="campo">
                <label for="nome">Seu nome</label>
                <input type="text" id="nome" name="nome" required value="<?= e($nome) ?>">
            </div>
            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required value="<?= e($email) ?>">
            </div>
            <div class="campo">
                <label for="senha">Senha (mínimo 8 caracteres)</label>
                <input type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password">
            </div>
            <div class="campo">
                <label for="senha_confirmacao">Repita a senha</label>
                <input type="password" id="senha_confirmacao" name="senha_confirmacao" required minlength="8"
                       autocomplete="new-password">
            </div>
            <button type="submit" class="botao">Criar administrador</button>
        </form>
    </div>
</div>

<?php layout_rodape('publico'); ?>
