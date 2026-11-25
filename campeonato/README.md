# Copa do Rei · Porto Alegre — sistema do campeonato

PHP 8+ puro, sem banco de dados (os dados ficam em `data/db.json`, criado no primeiro acesso a partir de `config.php`).

## Páginas
- `index.php` — classificação, fase final (semi/final), próximos jogos e artilharia
- `jogos.php` — tabela de jogos por rodada, com filtro por equipe
- `times.php` / `time.php?id=…` — equipes, jogos de cada equipe e elenco
- `login.php` — login da Coordenação ou de cada equipe
- `painel.php` — painel (equipe: jogos, elenco, senha · coordenação: resultados, datas/local, goleadores, equipes, escudos, senhas, configurações)

## Regulamento implementado
- 5 equipes, turno único todos contra todos (5 rodadas, 2 jogos por rodada, 1 equipe folga)
- Vitória 3, empate 1. Desempate: pontos, vitórias, saldo, gols pró, confronto direto
- 1º colocado vai direto à Final; 2º x 3º jogam a Semifinal (empate → pênaltis)
- Semifinal e Final são preenchidas automaticamente quando a fase de classificação termina

## Senhas iniciais (troque no primeiro acesso em "Minha senha")
| Usuário | Senha |
|---|---|
| Coordenação | coord2026 |
| Laranja Mecânica | laranja2026 |
| Patrícios | patricios2026 |
| Cães de Guerra | caes2026 |
| Red Socks | redsocks2026 |
| Bitcoin | bitcoin2026 |

A coordenação também pode redefinir a senha de qualquer equipe na aba "Equipes".

## Importante no deploy
Não sobrescreva `data/db.json` nem `uploads/` no servidor — eles guardam os resultados. Ambos estão no `.gitignore`.
