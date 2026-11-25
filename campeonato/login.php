<?php
require __DIR__ . '/inc/lib.php';
require __DIR__ . '/inc/layout.php';

$db = db();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $quem = $_POST['quem'] ?? '';
    $senha = (string)($_POST['senha'] ?? '');

    // proteção simples contra força bruta
    $_SESSION['tentativas'] = ($_SESSION['tentativas'] ?? 0) + 1;
    if ($_SESSION['tentativas'] > 8) {
        sleep(2);
    }

    $hash = $quem === 'coord' ? $db['coord']['senha_hash'] : ($db['times'][$quem]['senha_hash'] ?? null);
    if ($hash && password_verify($senha, $hash)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $quem;
        $_SESSION['tentativas'] = 0;
        redirect('painel.php');
    }
    $erro = 'Usuário ou senha incorretos.';
}

topo('Entrar', 'login');
?>
<section class="card login-card">
  <h2>Acesso restrito</h2>
  <?php if ($erro): ?><div class="aviso aviso-erro"><?= e($erro) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_campo() ?>
    <label>Entrar como
      <select name="quem" required>
        <option value="coord">🛡️ Coordenação</option>
        <?php foreach ($db['times'] as $tid => $t): ?>
          <option value="<?= e($tid) ?>" <?= ($_POST['quem'] ?? '') === $tid ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Senha
      <input type="password" name="senha" required autocomplete="current-password">
    </label>
    <button class="btn">Entrar</button>
  </form>
</section>
<?php rodape(); ?>
