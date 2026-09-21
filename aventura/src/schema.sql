-- Schema da Aventura Escoteira 2026.
-- Todo comando e IF NOT EXISTS: o arquivo pode rodar a cada requisicao.

CREATE TABLE IF NOT EXISTS usuarios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nome       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    papel      TEXT NOT NULL DEFAULT 'equipe',   -- admin | equipe
    ativo      INTEGER NOT NULL DEFAULT 1,
    criado_em  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS configuracoes (
    chave TEXT PRIMARY KEY,
    valor TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS patrulhas (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    nome      TEXT NOT NULL UNIQUE,
    cor       TEXT NOT NULL DEFAULT '#2f6f4f',
    grito     TEXT NOT NULL DEFAULT '',
    vagas     INTEGER NOT NULL DEFAULT 8,
    criado_em TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS inscricoes (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    protocolo             TEXT NOT NULL UNIQUE,
    nome                  TEXT NOT NULL,
    nascimento            TEXT NOT NULL,          -- YYYY-MM-DD
    ramo                  TEXT NOT NULL,
    grupo                 TEXT NOT NULL,
    cidade                TEXT NOT NULL,
    uf                    TEXT NOT NULL,
    email                 TEXT NOT NULL,
    telefone              TEXT NOT NULL,
    responsavel_nome      TEXT NOT NULL DEFAULT '',
    responsavel_telefone  TEXT NOT NULL DEFAULT '',
    tamanho_camisa        TEXT NOT NULL DEFAULT 'M',
    restricao_alimentar   TEXT NOT NULL DEFAULT '',
    observacoes_saude     TEXT NOT NULL DEFAULT '',
    autoriza_imagem       INTEGER NOT NULL DEFAULT 0,
    status                TEXT NOT NULL DEFAULT 'pendente', -- pendente|confirmada|cancelada
    patrulha_id           INTEGER REFERENCES patrulhas(id) ON DELETE SET NULL,
    ip                    TEXT NOT NULL DEFAULT '',
    criado_em             TEXT NOT NULL,
    atualizado_em         TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_inscricoes_status   ON inscricoes(status);
CREATE INDEX IF NOT EXISTS idx_inscricoes_patrulha ON inscricoes(patrulha_id);
CREATE INDEX IF NOT EXISTS idx_inscricoes_criado   ON inscricoes(criado_em);

CREATE TABLE IF NOT EXISTS atividades (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo     TEXT NOT NULL,
    descricao  TEXT NOT NULL DEFAULT '',
    local      TEXT NOT NULL DEFAULT '',
    inicio     TEXT NOT NULL,                     -- YYYY-MM-DD HH:MM
    fim        TEXT NOT NULL,
    pontuavel  INTEGER NOT NULL DEFAULT 0,
    publicada  INTEGER NOT NULL DEFAULT 1,
    criado_em  TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_atividades_inicio ON atividades(inicio);

CREATE TABLE IF NOT EXISTS pontuacoes (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    patrulha_id  INTEGER NOT NULL REFERENCES patrulhas(id) ON DELETE CASCADE,
    atividade_id INTEGER NOT NULL REFERENCES atividades(id) ON DELETE CASCADE,
    pontos       INTEGER NOT NULL DEFAULT 0,
    observacao   TEXT NOT NULL DEFAULT '',
    criado_em    TEXT NOT NULL,
    UNIQUE (patrulha_id, atividade_id)
);
