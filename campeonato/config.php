<?php
// ============================================================
//  CONFIGURAÇÃO INICIAL DO CAMPEONATO
//  Estes valores só são usados na PRIMEIRA execução, para criar
//  o arquivo data/db.json. Depois disso, tudo é editado pelo
//  painel da Coordenação (nomes, cores, escudos, jogadores, senhas).
// ============================================================

return [
    'nome_campeonato' => 'Copa do Rei · Porto Alegre',

    // Senha inicial da coordenação (TROQUE no primeiro acesso!)
    'senha_coordenacao' => 'coord2026',

    // Pontuação
    'pontos_vitoria' => 3,
    'pontos_empate'  => 1,

    // 5 equipes. Senha inicial de cada time
    // (TROQUE no primeiro acesso!)
    'times' => [
        'laranja'   => ['nome' => 'Laranja Mecânica', 'sigla' => 'LAR', 'cor1' => '#f07a1a', 'cor2' => '#111111', 'escudo' => 'assets/escudos/laranja.png',   'senha' => 'laranja2026'],
        'patricios' => ['nome' => 'Patrícios',        'sigla' => 'PAT', 'cor1' => '#2fa84f', 'cor2' => '#ffffff', 'escudo' => 'assets/escudos/patricios.png', 'senha' => 'patricios2026'],
        'caes'      => ['nome' => 'Cães de Guerra',   'sigla' => 'CDG', 'cor1' => '#111111', 'cor2' => '#ffffff', 'escudo' => 'assets/escudos/caes.png',      'senha' => 'caes2026'],
        'redsocks'  => ['nome' => 'Red Socks',        'sigla' => 'RED', 'cor1' => '#c8202f', 'cor2' => '#ffffff', 'escudo' => 'assets/escudos/redsocks.png',  'senha' => 'redsocks2026'],
        'bitcoin'   => ['nome' => 'Bitcoin',          'sigla' => 'BTC', 'cor1' => '#f2e600', 'cor2' => '#111111', 'escudo' => 'assets/escudos/bitcoin.png',   'senha' => 'bitcoin2026'],
    ],
];
