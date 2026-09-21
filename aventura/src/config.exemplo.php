<?php
/**
 * Configuração opcional por ambiente.
 *
 * Copie este arquivo para src/config.local.php e ajuste o que precisar.
 * Tudo que não for informado usa o padrao definido em src/bootstrap.php.
 * src/config.local.php não vai para o repositorio (ver .gitignore).
 */

return [
    // Caminho do arquivo SQLite. Guardar fora do public_html e mais seguro;
    // o padrao abaixo fica dentro da aplicacao e e protegido pelo .htaccess.
    'banco' => __DIR__ . '/../data/aventura.sqlite',

    // Fuso usado para gravar e exibir datas.
    'fuso' => 'America/Sao_Paulo',

    // true apenas em ambiente local: mostra os erros na tela.
    'debug' => false,
];
