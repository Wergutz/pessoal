<?php
/**
 * Sessão da equipe organizadora, CSRF e limite de envios.
 */

declare(strict_types=1);

/** Token CSRF da sessão atual. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo pronto para colar dentro do <form>. */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Confere o token enviado no POST. */
function csrf_valido(): bool
{
    $enviado = $_POST['_csrf'] ?? '';
    return is_string($enviado)
        && $enviado !== ''
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

/** Interrompe a requisicao quando o token não confere. */
function exigir_csrf(): void
{
    if (!csrf_valido()) {
        http_response_code(400);
        exit('Sessão expirada. Volte, atualize a página e envie novamente.');
    }
}

/**
 * Cria o primeiro usuário ou mais um membro da equipe.
 *
 * @return array{ok: bool, erros: list<string>, id?: int}
 */
function usuario_criar(string $nome, string $email, string $senha, string $papel = 'equipe'): array
{
    $erros = [];
    $nome = trim($nome);
    $email = mb_strtolower(trim($email));

    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe o nome completo.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if (mb_strlen($senha) < 8) {
        $erros[] = 'A senha precisa ter ao menos 8 caracteres.';
    }
    if (!in_array($papel, ['admin', 'equipe'], true)) {
        $erros[] = 'Papel inválido.';
    }
    if ($erros === [] && db_valor('SELECT 1 FROM usuarios WHERE email = ?', [$email]) !== null) {
        $erros[] = 'Já existe um usuário com esse e-mail.';
    }
    if ($erros !== []) {
        return ['ok' => false, 'erros' => $erros];
    }

    db_exec(
        'INSERT INTO usuarios (nome, email, senha_hash, papel, ativo, criado_em)
         VALUES (?, ?, ?, ?, 1, ?)',
        [$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $papel, agora()]
    );

    return ['ok' => true, 'erros' => [], 'id' => (int) db()->lastInsertId()];
}

/** Quantos usuários ativos existem (usado pelo instalador). */
function usuarios_total(): int
{
    return (int) db_valor('SELECT COUNT(*) FROM usuarios');
}

/**
 * Confere e-mail e senha.
 *
 * @return array<string, mixed>|null
 */
function usuario_autenticar(string $email, string $senha): ?array
{
    $email = mb_strtolower(trim($email));
    $usuario = db_um('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [$email]);

    if ($usuario === null) {
        // Gasta o mesmo tempo de um hash real para não vazar a existencia do e-mail.
        password_verify($senha, '$2y$10$usuarioinexistenteusuarioinexistenteusuarioinexiste');
        return null;
    }
    if (!password_verify($senha, (string) $usuario['senha_hash'])) {
        return null;
    }
    if (password_needs_rehash((string) $usuario['senha_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [
            password_hash($senha, PASSWORD_DEFAULT),
            $usuario['id'],
        ]);
    }

    return $usuario;
}

/** @param array<string, mixed> $usuario */
function sessao_abrir(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'    => (int) $usuario['id'],
        'nome'  => (string) $usuario['nome'],
        'email' => (string) $usuario['email'],
        'papel' => (string) $usuario['papel'],
    ];
}

function sessao_encerrar(): void
{
    unset($_SESSION['usuario']);
    session_regenerate_id(true);
}

/** @return array{id: int, nome: string, email: string, papel: string}|null */
function usuario_logado(): ?array
{
    $u = $_SESSION['usuario'] ?? null;
    return is_array($u) ? $u : null;
}

function e_admin(): bool
{
    return (usuario_logado()['papel'] ?? '') === 'admin';
}

/** Redireciona para o login quando ninguem esta autenticado. */
function exigir_login(): array
{
    $usuario = usuario_logado();
    if ($usuario === null) {
        // Guarda apenas "arquivo.php?query": e o único formato que o login
        // aceita de volta, o que impede redirecionar para fora do site.
        $destino = basename($_SERVER['REQUEST_URI'] ?? 'painel.php');
        redirecionar('index.php?retorno=' . rawurlencode($destino));
    }
    return $usuario;
}

/** Bloqueia páginas restritas ao papel admin. */
function exigir_admin(): array
{
    $usuario = exigir_login();
    if ($usuario['papel'] !== 'admin') {
        http_response_code(403);
        exit('Esta área e restrita aos administradores.');
    }
    return $usuario;
}

/**
 * Limite simples por sessão para evitar envios repetidos do formulario público.
 *
 * @param int $maximo   envios permitidos na janela
 * @param int $janela   tamanho da janela em segundos
 */
function limite_envio_ok(string $chave, int $maximo = 5, int $janela = 600): bool
{
    $agora = time();
    $registros = $_SESSION['limites'][$chave] ?? [];
    $registros = array_values(array_filter(
        $registros,
        static fn ($momento) => is_int($momento) && $momento > $agora - $janela
    ));

    if (count($registros) >= $maximo) {
        $_SESSION['limites'][$chave] = $registros;
        return false;
    }

    $registros[] = $agora;
    $_SESSION['limites'][$chave] = $registros;
    return true;
}
