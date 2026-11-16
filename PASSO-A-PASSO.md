> **Desatualizado:** não é mais preciso renomear o `.env.producao` nem abrir o
> `atualizar.php`. Veja `COMO-PUBLICAR.md` — basta enviar a pasta inteira pelo FTP.

# Colocar o StoqueOnline no ar — passo a passo

Site: **http://stoque.lfdev.tech/**
Banco: **u273888154_stoque**

São 5 passos. Leve uns 15 minutos.

---

# PASSO 1 — Conferir se os arquivos subiram completos

O File Manager da Hostinger **esconde arquivos que começam com ponto** por
padrão, e o sistema depende de dois deles. É o erro mais comum.

1. Entre no **hPanel → Arquivos → Gerenciador de Arquivos**
2. Abra a pasta do subdomínio `stoque.lfdev.tech`
3. No canto superior direito, clique em **Configurações** (ícone de engrenagem)
   e marque **"Mostrar arquivos ocultos (dotfiles)"**

Agora confira se a pasta tem **exatamente** isto:

```
.htaccess          <- arquivo oculto, ESSENCIAL
index.php
app/
config/
database/
public/
views/
composer.json
```

**Se o `.htaccess` não estiver lá, o site não vai funcionar.** Suba ele
manualmente de `C:\xampp\htdocs\stoqueonline\stoqueonline_v2\.htaccess`.

Confira também se existe `public/.htaccess` (outro arquivo oculto).

---

# PASSO 2 — Importar o banco de dados

O arquivo já está pronto no seu computador:

```
C:\xampp\htdocs\stoqueonline\stoqueonline_v2\storage\banco_stoque_hostinger.sql
```

São 5,16 MB com 56 usuários, 13 empresas e 49.249 itens de estoque.

1. hPanel → **Bancos de Dados → phpMyAdmin**
2. Clique em **Entrar no phpMyAdmin** na linha do banco `u273888154_stoque`
3. Na coluna da esquerda, **clique no nome do banco** `u273888154_stoque`
   (é importante selecionar o banco antes de importar)
4. Clique na aba **Importar**, no menu de cima
5. Em "Arquivo para importar", clique em **Escolher arquivo** e selecione
   o `banco_stoque_hostinger.sql`
6. Não mexa em mais nada. Role até o fim e clique em **Importar**

Aguarde. Ao terminar aparece uma mensagem verde de sucesso.

**Como saber se deu certo:** na coluna da esquerda devem aparecer
**10 tabelas** (`companies`, `contacts`, `inventory`, `inventory_batches`,
`login_attempts`, `payments`, `plans`, `user_licenses`, `user_logs`, `users`).
Clique em `inventory` e confira: deve mostrar cerca de 49.249 registros.

> **Se der erro de tamanho de arquivo:** rode no seu computador
> `php database/exportar_dados.php --max-mb=2` — ele divide em vários
> arquivos menores, que você importa na ordem (parte1, parte2...).

---

# PASSO 3 — Colocar o arquivo de configuração no servidor

Este é o arquivo que liga o sistema ao banco. Já está pronto no seu
computador, com os seus dados preenchidos:

```
C:\xampp\htdocs\stoqueonline\stoqueonline_v2\.env.producao
```

1. No **Gerenciador de Arquivos**, entre na pasta do subdomínio
2. Clique em **Carregar arquivos** e envie o `.env.producao`
3. Depois de enviado, **clique com o botão direito** nele → **Renomear**
4. Troque o nome para exatamente:  **`.env`**  (com o ponto na frente,
   e sem nenhuma extensão depois)

O arquivo vai sumir da lista se você tiver desmarcado "mostrar ocultos" —
é normal, ele continua lá.

> Se o Windows não deixar você enviar um arquivo começado com ponto,
> envie assim mesmo com o nome `.env.producao` e renomeie lá dentro,
> como descrito acima.

---

# PASSO 4 — Criar a pasta de logs

O sistema grava ali os erros de e-mail e de pagamento.

1. No Gerenciador de Arquivos, dentro da pasta do subdomínio,
   clique em **Nova pasta** e crie: `storage`
2. Entre em `storage` e crie outra pasta dentro: `logs`
3. Clique com o botão direito em `storage` → **Permissões** →
   deixe em **755** e marque **aplicar às subpastas**

---

# PASSO 5 — Conferir tudo pelo navegador

Abra este endereço (ele só funciona com a chave):

```
http://stoque.lfdev.tech/diagnostico.php?token=TROQUE_POR_UMA_CHAVE_LONGA
```

Vai aparecer uma lista com bolinhas **verdes** (tudo certo), **amarelas**
(atenção) e **vermelhas** (problema).

### O que fazer se aparecer vermelho

| Item vermelho | O que significa | Como resolver |
|---|---|---|
| Conexão do banco | Senha ou nome do banco errados no `.env` | Confira o Passo 3 |
| Tabelas faltando | A importação não terminou | Refaça o Passo 2 |
| Reescrita de URL | O `.htaccess` não subiu | Volte ao Passo 1 |
| storage/logs gravável | Pasta não existe ou sem permissão | Refaça o Passo 4 |
| APP_DEBUG / APP_ENV | O `.env` não foi renomeado certo | Confira o Passo 3 |
| HTTPS ativo | Ainda não tem certificado | Veja o passo extra abaixo |

Se tudo estiver verde ou amarelo, **o site está no ar**. Abra:

```
http://stoque.lfdev.tech/
```

---

# DEPOIS QUE ESTIVER FUNCIONANDO

## 1. Trocar a senha do administrador (faça agora)

Entre em `http://stoque.lfdev.tech/login` com uma das contas de admin.
A conta padrão é `admin@stoqueonline.com.br` / `admin123` — **essa senha é
pública, está no código**. Troque imediatamente em "Esqueci a senha" ou
crie uma conta nova e remova a antiga.

As contas de administrador que vieram da v1 são estas — revise se todas
devem continuar com acesso total (em `/admin/usuarios`, botão do escudo):

- Lucas Fanti — ti.desenvolvimento.sistemas@gmail.com
- daniel amorim — daniel_amorimtj@hotmail.com
- Daniboy — daniel@benisarolamentos.com.br
- Reparo — reparobackup1@gmail.com
- Administrador StoqueOnline — admin@stoqueonline.com.br

## 2. Apagar a página de diagnóstico

Ela mostra detalhes da sua configuração. Depois de usar:

1. Apague o arquivo `public/diagnostico.php`
2. Abra o `.env` (botão direito → Editar) e apague a última linha,
   a que começa com `DIAGNOSTIC_TOKEN=`

## 3. Ativar o HTTPS (recomendado)

1. hPanel → **Segurança → SSL** → ative o certificado gratuito para
   `stoque.lfdev.tech`
2. Depois, edite o `.env` no servidor e troque **três linhas** de
   `http://` para `https://`:
   - `APP_URL`
   - `PICPAY_CALLBACK_URL`
   - `PICPAY_RETURN_URL`

Enquanto o site estiver em HTTP, as senhas trafegam sem criptografia.

## 4. Cadastrar o callback no PicPay

No painel do lojista PicPay, cadastre a URL de notificação:

```
http://stoque.lfdev.tech/pagamento/callback
```

(troque para `https://` depois do passo 3)

## 5. Testar o e-mail

Faça um cadastro de teste em `http://stoque.lfdev.tech/cadastro` com um
e-mail seu e veja se a mensagem de ativação chega. Se não chegar, o motivo
fica escrito em `storage/logs/mail-errors.log` (dá para abrir pelo
Gerenciador de Arquivos).

---

# COISAS IMPORTANTES QUE VOCÊ PRECISA SABER

**Senha do banco.** Use uma senha longa e aleatória, nunca derivada do nome do
usuário ou do banco — é a primeira coisa que um ataque automatizado tenta. Troque em hPanel → Bancos de Dados → ícone de
alterar senha, e atualize a linha `DB_PASS` no `.env`.

**Credenciais de e-mail e PicPay.** O `.env` está indo com a senha de app do
Gmail e os tokens do PicPay que já existiam. Eles circularam em arquivo
durante o desenvolvimento — gere novos quando puder e atualize o `.env`.

**Senhas dos clientes antigos continuam valendo.** A versão 1 guardava as
senhas em MD5. Cada cliente entra com a senha de sempre e o sistema converte
para o formato novo sozinho, sem ninguém precisar redefinir nada.

**Todos os clientes vão entrar sem plano ativo.** As licenças que vieram da
v1 já estavam vencidas. Eles caem na página de planos. Para liberar acesso
a alguém manualmente: `/admin/usuarios` → botão da chave 🔑 → escolher o
plano → Ativar Licença.
