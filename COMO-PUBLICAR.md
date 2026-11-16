# Como publicar

Envie a pasta `stoqueonline_v2` **inteira** pelo FTP para
`public_html/stoque`, substituindo tudo. Só isso.

## Por que funciona sem mexer em nada

- **Configuração:** o sistema escolhe o arquivo pela máquina.
  - No seu computador (Windows/XAMPP) vale o `.env`.
  - Na Hostinger (Linux) vale o `.env.producao`.

  Os dois sobem juntos e um não atrapalha o outro. Os dois ficam bloqueados
  para acesso pela web pelo `.htaccess`.
- **Banco de dados:** a primeira visita depois do envio aplica sozinha as
  migrações novas de `database/migrations`. Não precisa abrir o
  `atualizar.php` (ele continua existindo, para quem quiser ver o histórico).

## O que mudar e onde

| Quero mudar…                             | Onde                                      |
|------------------------------------------|-------------------------------------------|
| algo do **site no ar** (senha, chave…)   | `.env.producao` no seu computador, e envie |
| algo do **site local** (XAMPP)           | `.env`                                     |

A tela **Painel Admin → Sistema** também edita o arquivo do servidor, mas o
próximo envio pelo FTP o substitui pelo `.env.producao` do computador.
Mudou pela tela? Faça a mesma mudança no arquivo do computador.

## Se der erro depois de enviar

- **"Access denied for user 'root'"**: o `.env.producao` não chegou ao
  servidor. Envie-o de novo para a raiz (`public_html/stoque/.env.producao`).
- **Migração que falhou**: aparece no log de erros do PHP no hPanel com o
  prefixo `[StoqueOnline]`. Depois de uma falha, o sistema espera 10 minutos
  antes de tentar de novo sozinho.
