# Aventura Escoteira 2026

Sistema de inscrições, programação e pontuação para o evento
**Aventura Escoteira 2026**.

Roda em PHP puro com banco SQLite — sem Composer, sem banco externo e sem
serviço extra, porque o destino é a hospedagem compartilhada que já serve o
resto do site.

## O que o sistema faz

**Site público**

| Página | Para quê |
| --- | --- |
| `index.php` | Apresentação do evento, contagem regressiva, vagas restantes |
| `inscricao.php` | Ficha de inscrição com validação completa |
| `consulta.php` | Consulta da própria inscrição pelo protocolo |
| `programacao.php` | Programação por dia, só com o que a equipe publicou |
| `ranking.php` | Quadro de pontuação das patrulhas |

**Área da equipe** (`admin/`, exige login)

| Página | Para quê |
| --- | --- |
| `painel.php` | Números do evento: confirmados, pendentes, vagas, camisas, restrições |
| `inscricoes.php` | Filtrar, confirmar/cancelar em lote, mover de patrulha, exportar CSV |
| `inscricao-detalhe.php` | Ficha completa, inclusive saúde e alimentação |
| `patrulhas.php` | Criar patrulhas e distribuir os confirmados automaticamente |
| `atividades.php` | Montar a programação e marcar o que vale pontos |
| `pontuacao.php` | Lançar pontos por atividade e ver a classificação |
| `configuracoes.php` | Dados do evento, abertura das inscrições e equipe (só administrador) |

### Regras que o sistema aplica sozinho

- **Idade x ramo.** A idade considerada é a do primeiro dia do evento, não a
  de hoje. Quem não cabe na faixa do ramo escolhido não consegue se inscrever.
  As faixas são **editáveis por ramo** em Configurações, dá para desativar os
  ramos que não participam, e a conferência inteira pode ser desligada. As
  faixas vêm preenchidas com as dos Escoteiros do Brasil só como ponto de
  partida. Sem data de evento definida, a conferência não roda — o sistema
  não reprova ninguém por um evento que ainda não tem data.
- **Responsável.** Obrigatório apenas para quem ainda será menor de 18 anos
  na data do evento.
- **Vagas e prazo.** As inscrições fecham sozinhas quando o prazo vence ou as
  vagas acabam. As vagas são reconferidas na hora de gravar, não só ao abrir
  a página, então duas pessoas não ocupam a mesma última vaga. Total de vagas
  `0` significa sem limite.
- **Duplicidade.** Mesmo nome e mesma data de nascimento não entram duas vezes.
- **Distribuição de patrulhas.** Equilibra o tamanho das patrulhas e evita
  juntar gente do mesmo grupo escoteiro, que é o esperado num evento regional.
- **Empates no ranking.** Dividem a colocação (1, 2, 2, 4).
- **Cancelar ≠ excluir.** Cancelar libera a vaga e preserva o histórico.
  Excluir de verdade só o administrador faz.

## O sistema nasce sem evento definido

Não há data, local, valor nem texto embutido no código. Um sistema recém
instalado fica assim, de propósito:

- as **inscrições nascem fechadas** e não abrem nem se alguém marcar
  *Abertas* — enquanto faltar configuração obrigatória, a regra de abertura
  reprova;
- o **site público** não mostra data, local ou valor: exibe um aviso neutro
  de que as informações serão publicadas em breve;
- o **painel** lista exatamente o que falta preencher, com link para a tela
  de Configurações.

Assim nada vai ao ar com informação inventada, e a equipe preenche no ritmo
que quiser. O que é obrigatório para abrir inscrições: datas de início e
término, local, cidade, UF, prazo de inscrição e e-mail de contato.

## Instalação

1. Publique a pasta `aventura/` no servidor.
2. Abra `https://SEU-DOMINIO/instalar.php` e crie o usuário administrador.
   O banco e as tabelas são criados no primeiro acesso.
3. Entre em **Configurações** e preencha as informações do evento. O painel
   mostra o que ainda falta.

`instalar.php` se recusa a rodar depois que existe um usuário, então pode
ficar no servidor sem risco.

### Configuração por ambiente (opcional)

Só é necessária para mudar o caminho do banco ou ligar a exibição de erros:

```bash
cp src/config.exemplo.php src/config.local.php
```

`src/config.local.php` não vai para o repositório. Sem ele, valem os padrões
de `src/bootstrap.php`.

## Banco de dados

SQLite em `data/aventura.sqlite`, criado sozinho a partir de `src/schema.sql`.
O schema é todo `IF NOT EXISTS`, então roda a cada requisição sem efeito
colateral e sem passo de migração manual.

**O backup é copiar esse arquivo.** Vale fazer isso antes e depois do evento.

A pasta `data/` está protegida por `.htaccess` e pelo `.htaccess` da raiz, e
o banco nunca vai para o Git. Se preferir, aponte `banco` em
`src/config.local.php` para fora do `public_html`.

## Testes

```bash
php testes/executar.php
```

224 verificações sem dependência externa, em banco temporário próprio. Se
você tiver um `src/config.local.php`, ele é guardado e devolvido ao final —
os testes nunca tocam no seu banco de trabalho.

Cobrem validação da ficha, faixas etárias configuráveis, exigência de
responsável, o estado de sistema recém instalado, vagas e prazos, filtros,
distribuição de patrulhas, empates no ranking, autenticação, CSRF, limite de
envios e resistência a SQL injection e XSS. Há também uma
varredura que reprova qualquer literal SQL que tenha ganhado acento por
engano — erro fácil de cometer num código em português e que só apareceria
em produção.

## Segurança

- Toda consulta usa *prepared statement*; nada de SQL montado com concatenação
  de entrada do usuário.
- Toda saída passa por `e()` (`htmlspecialchars`).
- Senhas com `password_hash`/`password_verify` e *rehash* automático.
- Token CSRF em todo formulário que grava algo.
- Formulário público com campo-armadilha e limite de envios por sessão.
- Sessão com cookie `HttpOnly` + `SameSite=Lax`, e `session_regenerate_id`
  ao entrar e ao sair.
- O retorno pós-login só aceita caminhos internos, então não dá para usar o
  login como redirecionador para fora do site.
- Dados de saúde e alimentação aparecem só para a equipe, nunca na consulta
  pública.

## Publicação

`.github/workflows/deploy-aventura.yml` tem dois jobs.

**Testes** rodam sempre: a cada push ou pull request que toque em `aventura/`.

**Publicar** não roda sozinho, porque o domínio ainda não foi definido. Ele só
executa por *Run workflow* com a opção `publicar` marcada, e antes de enviar
qualquer coisa confere se o destino foi configurado — se não foi, falha com
uma mensagem dizendo o que falta, sem tocar no servidor.

Para ligar quando o domínio estiver definido, em
*Settings › Secrets and variables › Actions*:

| Onde | Nome | Exemplo |
| --- | --- | --- |
| Variable | `AVENTURA_REMOTE_HOST` | `147.93.38.158` |
| Variable | `AVENTURA_REMOTE_USER` | `u278289683` |
| Variable | `AVENTURA_REMOTE_PORT` | `65002` (padrão `22`) |
| Variable | `AVENTURA_TARGET` | `~/domains/SEU-DOMINIO/public_html/` |
| Secret | `SSH_PRIVATE_KEY` | a chave privada |

Para publicar a cada push na `main` depois disso, troque o `if:` do job
`deploy` por `if: github.ref == 'refs/heads/main'`.

O rsync roda com `--delete`, mas `/data/*.sqlite*` está em `EXCLUDE`: o banco
no servidor sobrevive a cada publicação.

## Estrutura

```
aventura/
├── index.php  inscricao.php  consulta.php  programacao.php  ranking.php
├── instalar.php
├── admin/      área da equipe
├── src/        regras de negócio (uma responsabilidade por arquivo)
├── assets/     estilo.css e app.js
├── data/       banco SQLite (fora do Git, bloqueado no servidor)
└── testes/     executar.php
```

`src/` separa por assunto: `inscricoes.php`, `patrulhas.php`, `atividades.php`,
`pontuacao.php`, `configuracoes.php`, mais `db.php`, `seguranca.php`,
`util.php` e `layout.php`. As páginas cuidam de tela e requisição; as regras
ficam em `src/`, que é o que os testes exercitam.

## Requisitos

PHP 8.0 ou mais novo com `pdo_sqlite` (padrão na Hostinger). Sem Composer,
sem `node_modules`, sem serviço externo. O JavaScript é progressivo: tudo
funciona com ele desligado.
