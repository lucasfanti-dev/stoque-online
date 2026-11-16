> **Desatualizado:** não é mais preciso renomear o `.env.producao` nem abrir o
> `atualizar.php`. Veja `COMO-PUBLICAR.md` — basta enviar a pasta inteira pelo FTP.

# StoqueOnline v2 — Publicação na Hostinger

Guia na ordem de execução para colocar o sistema no ar em
**http://stoque.lfdev.tech/**.

---

## Visão geral

| O que sobe | Para onde |
|---|---|
| Todo o conteúdo de `stoqueonline_v2/` | Pasta do subdomínio na Hostinger |
| Banco `bearing_v2` (56 usuários, 49 mil itens) | Banco MySQL criado no hPanel |

O sistema funciona nas duas formas de publicação:

1. **Recomendada** — domínio apontando para a pasta `public/`
2. **Alternativa** — domínio apontando para a raiz do projeto
   (o `.htaccess` e o `index.php` da raiz encaminham para `public/`)

---

## Passo 1 — Criar o banco no hPanel

`hPanel → Bancos de Dados → MySQL`

Anote os quatro dados gerados (o nome do banco e do usuário vêm com prefixo,
tipo `u123456789_stoque`):

```
DB_NAME = ____________________
DB_USER = ____________________
DB_PASS = ____________________
DB_HOST = localhost        (para o site)
```

Se quiser enviar os dados direto da sua máquina, ative também
`hPanel → Bancos de Dados → MySQL Remoto`, liberando o seu IP. A Hostinger
vai informar um host externo (algo como `srv1234.hstgr.io`) — é esse que entra
no `.env.producao` durante a migração.

---

## Passo 2 — Migrar os dados

Existem dois caminhos. **Escolha um.**

### Caminho A — Envio direto (precisa do MySQL Remoto liberado)

```bash
copy .env.producao.example .env.producao
```

Preencha `DB_HOST` (host remoto), `DB_NAME`, `DB_USER` e `DB_PASS`, e rode:

```bash
php database/enviar_para_hostinger.php --testar
```

Se a conexão passar, envie tudo:

```bash
php database/enviar_para_hostinger.php
```

O script cria as tabelas, copia todos os registros e confere as contagens de
origem e destino no final, tabela por tabela.

### Caminho B — Arquivo .sql pelo phpMyAdmin

```bash
php database/exportar_dados.php
```

Gera `storage/stoqueonline_AAAA-MM-DD_HHMMSS.sql` (cerca de 5 MB).
No hPanel: `Bancos de Dados → phpMyAdmin → selecione o banco → aba Importar`.

O arquivo não contém `CREATE DATABASE` nem `USE`: ele é importado dentro do
banco que você já selecionou.

---

## Passo 3 — Preparar o `.env` de produção

Copie `.env.producao.example` para `.env.producao`, preencha e **renomeie para
`.env` depois de subir para o servidor**.

O que não pode ficar errado:

| Variável | Valor |
|---|---|
| `APP_URL` | `http://stoque.lfdev.tech` (sem barra no final) |
| `APP_DEBUG` | `false` |
| `APP_ENV` | `production` |
| `DB_HOST` | `localhost` (o site roda no mesmo servidor do banco) |
| `MAIL_HOST` | SMTP real — sem isso ninguém ativa conta nem recupera senha |
| `PICPAY_SELLER_TOKEN` | obrigatório, senão o webhook fica desativado |

> **Credenciais novas:** a senha de app do Gmail e os tokens do PicPay que
> estão no `.env` de desenvolvimento circularam em arquivo. Gere novos antes
> de publicar.

---

## Passo 4 — Enviar os arquivos

Suba **todo o conteúdo** da pasta `stoqueonline_v2` (inclusive `.htaccess`,
`index.php` e as pastas `app/`, `config/`, `database/`, `views/`, `public/`).

Não precisa subir: `storage/` (é criada sozinha) nem os arquivos `.sql`.

Depois de enviar:

- confirme que existe a pasta `storage/logs` com permissão de escrita (755)
- renomeie `.env.producao` para `.env`
- apague `.env.producao.example` do servidor (opcional, mas mais limpo)

### Sobre o document root (recomendado fazer)

Hoje o site funciona com o domínio apontando para a raiz do projeto, e o
`.htaccess` bloqueia `app/`, `config/`, `database/`, `views/` e `storage/`.
Isso depende do `.htaccess` continuar valendo.

**O jeito mais seguro** é apontar o subdomínio direto para a pasta `public/`.
Aí o código, o `.env` e os backups ficam *fisicamente* fora da web — nenhuma
regra de servidor precisa dar certo para protegê-los.

Como fazer, sem risco de derrubar o site:

1. hPanel → **Domínios → Subdomínios**
2. Na linha de `stoque.lfdev.tech`, edite a **pasta do documento**
3. Troque de `public_html/stoque` para `public_html/stoque/public`
4. Abra `https://stoque.lfdev.tech/` e confira que carrega normalmente

Não é preciso mexer em nenhum arquivo: o `APP_URL` continua o mesmo e a
aplicação se adapta sozinha às duas formas. Se algo sair errado, basta voltar
a pasta anterior no mesmo lugar.

Depois da troca, confirme no diagnóstico que os itens de "Segurança"
continuam bloqueados.

---

## Passo 5 — Conferir tudo pelo navegador

Com `DIAGNOSTIC_TOKEN` preenchido no `.env`, abra:

```
http://stoque.lfdev.tech/diagnostico.php?token=SUA_CHAVE
```

A página confere automaticamente: versão do PHP e extensões, conexão e
tabelas do banco, contagem de registros, permissões de escrita, SMTP
alcançável, tokens do PicPay, bloqueio dos arquivos sensíveis e se a
reescrita de URL está funcionando.

**Apague `public/diagnostico.php` e a linha `DIAGNOSTIC_TOKEN` quando terminar.**

---

## Passo 6 — Últimos ajustes antes de divulgar

- [ ] Entrar com o administrador e **trocar a senha padrão**
- [ ] Revisar quem continua administrador (a base migrada tem **5 contas
      com acesso total**: Lucas Fanti, daniel amorim, Daniboy, Reparo e o
      Administrador StoqueOnline)
- [ ] Fazer um cadastro de teste e confirmar que o e-mail de ativação chega
- [ ] Testar "Esqueci a senha"
- [ ] Cadastrar no painel do PicPay a URL de callback:
      `http://stoque.lfdev.tech/pagamento/callback`
- [ ] Ativar o SSL gratuito no hPanel e trocar `APP_URL`,
      `PICPAY_CALLBACK_URL` e `PICPAY_RETURN_URL` para `https://`

---

## Depois no ar

**Senhas dos usuários antigos:** a v1 usava MD5. Os hashes vieram como estão e
cada conta é convertida para bcrypt sozinha no primeiro login — ninguém
precisa redefinir senha.

**Licenças:** todas as licenças vindas da v1 já estavam vencidas. Os clientes
antigos entram sem plano ativo e caem na página de planos. O administrador
concede licença manual pelo botão 🔑 em `/admin/usuarios`.

**Teste grátis:** 42 usuários migrados estão marcados como elegíveis ao teste
gratuito mesmo já tendo sido clientes. Se não quiser isso:

```sql
UPDATE users SET first_access_free = 0 WHERE created_at < '2026-01-01';
```

**Acompanhamento:**

- `/admin/pagamentos` — o cartão "Pagos sem licença" deve ficar em zero
- `/admin/logs` — filtre por `login_failed` e `login_blocked_rate_limit`
- `storage/logs/mail-errors.log` e `storage/logs/payments.log`

---

## Backup automático (importante)

Hoje não existe nenhuma rotina de backup. O script `database/manutencao.php`
resolve isso e mais um pouco: gera o backup compactado, encerra licenças
vencidas, limpa tokens expirados e apaga registros antigos.

No backup real da sua base, o arquivo saiu de **5,16 MB para 665 KB**
compactado, e a restauração foi conferida (56 usuários, 49.249 itens).

### Agendar na Hostinger

hPanel → **Avançado → Cron Jobs** → Criar novo:

- **Frequência:** uma vez por dia (ex.: 03:00)
- **Comando:**

```
/usr/bin/php /home/u273888154/domains/lfdev.tech/public_html/stoque/database/manutencao.php
```

> Confira o caminho real no Gerenciador de Arquivos. Se o subdomínio for
> apontado para `public/`, o caminho do script **não muda** — ele continua em
> `.../stoque/database/manutencao.php`.

Os backups ficam em `storage/backups/`, mantendo os 7 mais recentes.
Essa pasta é bloqueada para a web (um backup contém os hashes de senha de
todos os usuários).

**Baixe uma cópia para fora do servidor de vez em quando.** Backup que mora
no mesmo lugar do site não protege contra perda da conta na hospedagem.

### Opções

```bash
php database/manutencao.php                  # tudo
php database/manutencao.php --sem-backup     # só a limpeza
php database/manutencao.php --backups=14     # guarda 14 backups
php database/manutencao.php --dias-log=365   # guarda 1 ano de auditoria
```

---

## Scripts disponíveis

| Comando | O que faz |
|---|---|
| `php database/manutencao.php` | Backup + limpezas (agende no cron) |
| `php database/exportar_dados.php` | Gera o `.sql` para importar no phpMyAdmin |
| `php database/enviar_para_hostinger.php --testar` | Testa a conexão remota |
| `php database/enviar_para_hostinger.php` | Copia estrutura e dados para a hospedagem |
| `php database/upgrade.php` | Atualiza a estrutura de um banco já existente |
| `php database/migrate.php` | Importa os dados da v1 (já foi executado) |

Todos rodam apenas por linha de comando — pela web retornam 403.

---

## Desempenho da busca

Medições feitas em uma base simulada de **594 mil itens** (12x a atual), para
saber a partir de quando vale mexer:

| Consulta | Antes | Agora |
|---|---|---|
| Começa com | 30 ms | 30 ms |
| **Exata** | **1.780 ms** | **1 ms** |
| Contém | 1.556 ms | 276 ms |

A busca **exata** fazia varredura da tabela inteira por causa de uma condição
redundante (`OR product = ...` numa coluna sem índice). Conferido em 80
códigos reais: o resultado devolvido é idêntico ao de antes.

A busca **contém** (`%texto%`) não consegue usar índice para filtrar — isso
vale para qualquer banco. O índice de cobertura `idx_inventory_busca` faz o
MySQL varrer só o índice em vez da tabela, o que deu 5,6x.

**FULLTEXT não serve para este caso** e foi descartado após teste: ele indexa
palavras inteiras, e um código como `62052RSHC3` é uma palavra só. Buscar
`*2RS*` no meio devolve zero resultados. O MariaDB da Hostinger também não
tem o analisador *ngram*, que seria a única alternativa.

**Quando revisitar:** se a base passar de ~2 milhões de itens e a busca
"Contém" incomodar, o caminho é uma tabela auxiliar de trigramas.
