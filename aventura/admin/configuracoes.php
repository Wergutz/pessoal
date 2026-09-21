<?php
/**
 * Dados do evento e equipe organizadora. Restrito ao papel admin.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$usuario = exigir_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $acao = post('acao');

    if ($acao === 'evento') {
        $valores = [];

        // Caixas de marcação não chegam no POST quando estão desmarcadas,
        // então o valor delas é decidido aqui, não pelo laço abaixo.
        $valores['inscricoes_abertas'] = post('inscricoes_abertas') === '1' ? '1' : '0';
        $valores['validar_idade'] = post('validar_idade') === '1' ? '1' : '0';
        $valores['ramos_ativos'] = implode(',', array_map(
            'strval',
            (array) ($_POST['ramos'] ?? [])
        ));

        foreach (array_keys(CONFIG_PADRAO) as $chave) {
            if (array_key_exists($chave, $valores)) {
                continue;
            }
            if (array_key_exists($chave, $_POST)) {
                $valores[$chave] = mb_substr(post($chave), 0, 2000);
            }
        }

        // Campo em branco e valido: o evento pode estar sendo montado aos poucos.
        // So o que foi preenchido e conferido.
        $erros = [];
        foreach (['evento_inicio', 'evento_fim', 'inscricoes_ate'] as $chave) {
            if (($valores[$chave] ?? '') !== '' && data_valida($valores[$chave]) === null) {
                $erros[] = 'Data inválida em ' . $chave . '.';
            }
        }
        if (($valores['evento_inicio'] ?? '') !== '' && ($valores['evento_fim'] ?? '') !== ''
            && $valores['evento_fim'] < $valores['evento_inicio']) {
            $erros[] = 'O término do evento não pode ser antes do início.';
        }
        if (($valores['vagas_total'] ?? '') !== '' && !ctype_digit($valores['vagas_total'])) {
            $erros[] = 'O total de vagas deve ser um número inteiro.';
        }
        if (($valores['evento_uf'] ?? '') !== ''
            && !in_array(strtoupper($valores['evento_uf']), UFS, true)) {
            $erros[] = 'UF do evento inválida.';
        }

        // Faixas etárias: min <= max e dentro de limites sensatos.
        foreach (array_keys(RAMOS) as $codigo) {
            $min = $valores['ramo_' . $codigo . '_min'] ?? null;
            $max = $valores['ramo_' . $codigo . '_max'] ?? null;
            if ($min === null || $max === null) {
                continue;
            }
            if (!ctype_digit($min) || !ctype_digit($max) || (int) $min > (int) $max
                || (int) $max > 120) {
                $erros[] = 'Faixa etária inválida em ' . ramo_rotulo($codigo) . '.';
            }
        }

        // Ramos ativos: só códigos conhecidos, e ao menos um.
        $ativos = array_values(array_intersect(
            array_map('trim', explode(',', $valores['ramos_ativos'] ?? '')),
            array_keys(RAMOS)
        ));
        if ($ativos === []) {
            $erros[] = 'Escolha ao menos um ramo participante.';
        } else {
            $valores['ramos_ativos'] = implode(',', $ativos);
        }

        if ($erros === []) {
            $valores['evento_uf'] = strtoupper($valores['evento_uf'] ?? '');
            config_gravar_varias($valores);
            recado('ok', 'Configurações salvas.');
        } else {
            recado('erro', implode(' ', $erros));
        }
    } elseif ($acao === 'novo_usuario') {
        $resultado = usuario_criar(
            post('nome'),
            post('email'),
            (string) ($_POST['senha'] ?? ''),
            post('papel') === 'admin' ? 'admin' : 'equipe'
        );
        recado($resultado['ok'] ? 'ok' : 'erro', $resultado['ok']
            ? 'Usuário criado.'
            : implode(' ', $resultado['erros']));
    } elseif ($acao === 'desativar_usuario') {
        $alvo = (int) post('id');
        if ($alvo === $usuario['id']) {
            recado('erro', 'Você não pode desativar o proprio usuário.');
        } else {
            db_exec('UPDATE usuarios SET ativo = 0 WHERE id = ?', [$alvo]);
            recado('ok', 'Usuário desativado.');
        }
    } elseif ($acao === 'ativar_usuario') {
        db_exec('UPDATE usuarios SET ativo = 1 WHERE id = ?', [(int) post('id')]);
        recado('ok', 'Usuário reativado.');
    } elseif ($acao === 'trocar_senha') {
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');

        if (usuario_autenticar($usuario['email'], $atual) === null) {
            recado('erro', 'Senha atual incorreta.');
        } elseif (mb_strlen($nova) < 8) {
            recado('erro', 'A nova senha precisa ter ao menos 8 caracteres.');
        } else {
            db_exec('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [
                password_hash($nova, PASSWORD_DEFAULT),
                $usuario['id'],
            ]);
            recado('ok', 'Senha alterada.');
        }
    }

    redirecionar('configuracoes.php');
}

$config = config_todas();
$usuarios = db_todos('SELECT id, nome, email, papel, ativo, criado_em FROM usuarios ORDER BY nome');

layout_topo('Configurações', 'painel', 'configuracoes.php');
?>

<h1>Configurações</h1>

<?php $pendencias = config_pendencias(); ?>
<?php if ($pendencias !== []): ?>
    <div class="recado recado-aviso">
        <strong>Falta definir:</strong> <?= e(implode(', ', $pendencias)) ?>.
        Enquanto isso, as inscrições permanecem fechadas e o site público não
        mostra data, local nem valor.
    </div>
<?php else: ?>
    <div class="recado recado-ok">
        O evento está configurado. Use <em>Situação</em>, logo abaixo, para
        abrir as inscrições quando quiser.
    </div>
<?php endif; ?>

<form method="post" action="configuracoes.php" class="cartao">
    <?= csrf_campo() ?>
    <h2 style="margin-top:0">O evento</h2>

    <div class="grade grade-2">
        <div class="campo">
            <label for="evento_nome">Nome do evento</label>
            <input type="text" id="evento_nome" name="evento_nome" maxlength="120"
                   value="<?= e($config['evento_nome']) ?>">
        </div>
        <div class="campo">
            <label for="evento_lema">Lema</label>
            <input type="text" id="evento_lema" name="evento_lema" maxlength="120"
                   value="<?= e($config['evento_lema']) ?>">
        </div>
    </div>

    <div class="grade grade-4">
        <div class="campo">
            <label for="evento_inicio">Início</label>
            <input type="date" id="evento_inicio" name="evento_inicio" value="<?= e($config['evento_inicio']) ?>">
        </div>
        <div class="campo">
            <label for="evento_fim">Término</label>
            <input type="date" id="evento_fim" name="evento_fim" value="<?= e($config['evento_fim']) ?>">
        </div>
        <div class="campo">
            <label for="evento_valor">Taxa (R$)</label>
            <input type="text" id="evento_valor" name="evento_valor" maxlength="20"
                   value="<?= e($config['evento_valor']) ?>">
        </div>
        <div class="campo">
            <label for="vagas_total">Total de vagas</label>
            <input type="number" id="vagas_total" name="vagas_total" min="0" max="10000"
                   value="<?= e($config['vagas_total']) ?>">
            <span class="ajuda">0 = sem limite de vagas.</span>
        </div>
    </div>

    <div class="grade grade-3">
        <div class="campo">
            <label for="evento_local">Local</label>
            <input type="text" id="evento_local" name="evento_local" maxlength="120"
                   value="<?= e($config['evento_local']) ?>">
        </div>
        <div class="campo">
            <label for="evento_cidade">Cidade</label>
            <input type="text" id="evento_cidade" name="evento_cidade" maxlength="80"
                   value="<?= e($config['evento_cidade']) ?>">
        </div>
        <div class="campo">
            <label for="evento_uf">UF</label>
            <select id="evento_uf" name="evento_uf">
                <option value="">A definir</option>
                <?php foreach (UFS as $uf): ?>
                    <option value="<?= e($uf) ?>"<?= $config['evento_uf'] === $uf ? ' selected' : '' ?>>
                        <?= e($uf) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h2>Inscrições</h2>
    <div class="grade grade-3">
        <div class="campo">
            <label for="inscricoes_abertas">Situação</label>
            <select id="inscricoes_abertas" name="inscricoes_abertas">
                <option value="1"<?= $config['inscricoes_abertas'] === '1' ? ' selected' : '' ?>>Abertas</option>
                <option value="0"<?= $config['inscricoes_abertas'] !== '1' ? ' selected' : '' ?>>Fechadas</option>
            </select>
        </div>
        <div class="campo">
            <label for="inscricoes_ate">Prazo final</label>
            <input type="date" id="inscricoes_ate" name="inscricoes_ate" value="<?= e($config['inscricoes_ate']) ?>">
        </div>
    </div>

    <h2>Ramos participantes</h2>
    <p class="ajuda">
        Desmarque os ramos que não participam desta edição e ajuste as faixas
        etárias conforme a sua realidade. A idade considerada é a do primeiro
        dia do evento.
    </p>

    <div class="campo caixa-marcacao">
        <input type="checkbox" id="validar_idade" name="validar_idade" value="1"
               <?= $config['validar_idade'] === '1' ? 'checked' : '' ?>>
        <label for="validar_idade">
            Conferir a idade contra a faixa do ramo na hora da inscrição
            <span class="ajuda">Desligado, o sistema aceita qualquer idade em qualquer ramo.</span>
        </label>
    </div>

    <?php $ativos = explode(',', $config['ramos_ativos']); ?>
    <div class="rolagem">
        <table>
            <thead>
                <tr><th>Participa</th><th>Ramo</th><th class="numero">Idade mínima</th><th class="numero">Idade máxima</th></tr>
            </thead>
            <tbody>
            <?php foreach (RAMOS as $codigo => $ramo): ?>
                <tr>
                    <td>
                        <input type="checkbox" id="ramo-<?= e($codigo) ?>" name="ramos[]"
                               value="<?= e($codigo) ?>"
                               <?= in_array($codigo, $ativos, true) ? 'checked' : '' ?>>
                    </td>
                    <td><label for="ramo-<?= e($codigo) ?>"><?= e($ramo['rotulo']) ?></label></td>
                    <td class="numero" style="max-width:120px">
                        <input type="number" name="ramo_<?= e($codigo) ?>_min" min="0" max="120"
                               value="<?= e($config['ramo_' . $codigo . '_min']) ?>"
                               aria-label="Idade mínima do <?= e($ramo['rotulo']) ?>">
                    </td>
                    <td class="numero" style="max-width:120px">
                        <input type="number" name="ramo_<?= e($codigo) ?>_max" min="0" max="120"
                               value="<?= e($config['ramo_' . $codigo . '_max']) ?>"
                               aria-label="Idade máxima do <?= e($ramo['rotulo']) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Contato</h2>
    <div class="grade grade-2">
        <div class="campo">
            <label for="contato_email">E-mail</label>
            <input type="email" id="contato_email" name="contato_email" maxlength="160"
                   value="<?= e($config['contato_email']) ?>">
        </div>
        <div class="campo">
            <label for="contato_whatsapp">WhatsApp</label>
            <input type="tel" id="contato_whatsapp" name="contato_whatsapp" data-mascara="telefone"
                   value="<?= e($config['contato_whatsapp']) ?>">
        </div>
    </div>

    <h2>Textos do site</h2>
    <div class="campo">
        <label for="texto_apresentacao">Apresentacao</label>
        <textarea id="texto_apresentacao" name="texto_apresentacao" maxlength="2000"><?= e($config['texto_apresentacao']) ?></textarea>
    </div>
    <div class="campo">
        <label for="texto_o_que_levar">O que levar</label>
        <textarea id="texto_o_que_levar" name="texto_o_que_levar" maxlength="2000"><?= e($config['texto_o_que_levar']) ?></textarea>
    </div>

    <button type="submit" class="botao" name="acao" value="evento">Salvar configurações</button>
</form>

<div class="grade grade-2">
    <div class="cartao">
        <h2 style="margin-top:0">Equipe</h2>
        <div class="rolagem">
            <table>
                <thead><tr><th>Nome</th><th>Papel</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $membro): ?>
                    <tr>
                        <td><?= e((string) $membro['nome']) ?><br>
                            <small><?= e((string) $membro['email']) ?></small></td>
                        <td><?= (string) $membro['papel'] === 'admin' ? 'Administrador' : 'Equipe' ?>
                            <?php if ((int) $membro['ativo'] === 0): ?>
                                <br><span class="etiqueta etiqueta-cancelada">inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $membro['id'] !== $usuario['id']): ?>
                                <form method="post" action="configuracoes.php" style="display:inline">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $membro['id'] ?>">
                                    <?php if ((int) $membro['ativo'] === 1): ?>
                                        <button type="submit" class="botao botao-secundario botao-mini"
                                                name="acao" value="desativar_usuario"
                                                data-confirmar="Desativar <?= e((string) $membro['nome']) ?>?">
                                            Desativar</button>
                                    <?php else: ?>
                                        <button type="submit" class="botao botao-secundario botao-mini"
                                                name="acao" value="ativar_usuario">Reativar</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <small>você</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post" action="configuracoes.php" style="margin-top:1.25rem">
            <?= csrf_campo() ?>
            <h3>Novo membro</h3>
            <div class="campo">
                <label for="novo_nome">Nome</label>
                <input type="text" id="novo_nome" name="nome" required>
            </div>
            <div class="campo">
                <label for="novo_email">E-mail</label>
                <input type="email" id="novo_email" name="email" required>
            </div>
            <div class="grade grade-2">
                <div class="campo">
                    <label for="nova_senha">Senha inicial</label>
                    <input type="password" id="nova_senha" name="senha" minlength="8" required
                           autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="papel">Papel</label>
                    <select id="papel" name="papel">
                        <option value="equipe">Equipe</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="botao botao-secundario" name="acao" value="novo_usuario">Criar usuário</button>
        </form>
    </div>

    <form method="post" action="configuracoes.php" class="cartao">
        <?= csrf_campo() ?>
        <h2 style="margin-top:0">Minha senha</h2>
        <div class="campo">
            <label for="senha_atual">Senha atual</label>
            <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
        </div>
        <div class="campo">
            <label for="senha_nova">Nova senha (mínimo 8 caracteres)</label>
            <input type="password" id="senha_nova" name="senha_nova" minlength="8" required
                   autocomplete="new-password">
        </div>
        <button type="submit" class="botao" name="acao" value="trocar_senha">Trocar senha</button>
    </form>
</div>

<?php layout_rodape('painel'); ?>
