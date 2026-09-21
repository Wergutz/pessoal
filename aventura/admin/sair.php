<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

sessao_encerrar();
recado('ok', 'Sessão encerrada.');
redirecionar('index.php');
