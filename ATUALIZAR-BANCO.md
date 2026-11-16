> **Desatualizado:** não é mais preciso renomear o `.env.producao` nem abrir o
> `atualizar.php`. Veja `COMO-PUBLICAR.md` — basta enviar a pasta inteira pelo FTP.

# Como atualizar o banco de dados

O sistema agora controla sozinho o que já foi aplicado no banco. Você abre
uma página, ela mostra o que falta, você clica e pronto.

---

## Configuração (só uma vez)

Você subiu os arquivos sem o `.env`, então o `.env` do servidor ainda não tem
a chave da página de atualização. Precisa adicionar **uma linha**.

1. hPanel → **Gerenciador de Arquivos**
2. Na pasta do subdomínio, marque **"Mostrar arquivos ocultos"** (engrenagem
   no canto superior direito), se ainda não estiver marcado
3. Clique com o botão direito no `.env` → **Editar**
4. Vá até o fim do arquivo e acrescente:

```
UPDATE_TOKEN=SUA_CHAVE
```

5. Salvar.

> Essa chave pode ficar no `.env` para sempre. A página só executa quando a
> chave da URL bate com a do arquivo; sem isso, ela responde como página
> inexistente.

---

## Atualizar agora

Abra no navegador:

```
https://stoque.lfdev.tech/atualizar.php?token=SUA_CHAVE
```

A página mostra quantas atualizações estão pendentes e quais são. Clique em
**Aplicar atualizações agora**.

Ao terminar, ela lista exatamente o que foi feito — por exemplo:

```
Fotos dos produtos, vinculadas ao código da peça   (43 ms)
   + tabela product_photos criada
```

E mostra as tabelas do banco com a contagem de linhas, para você conferir
que nada foi perdido.

---

## Daqui em diante, o processo é sempre o mesmo

1. Subo arquivos novos para você
2. Você abre essa mesma URL
3. Se disser **"Banco em dia"**, não precisa fazer nada
4. Se aparecer algo pendente, clica em aplicar

**Pode abrir quantas vezes quiser.** O sistema guarda o histórico do que já
rodou e nunca aplica a mesma coisa duas vezes. Abrir a página sem clicar no
botão também não altera nada: é só consulta.

---

## Se der erro

A página mostra a mensagem exata do problema. O que já tinha sido aplicado
antes do erro **permanece**, e a migração que falhou não fica registrada —
ou seja, depois de corrigir, é só abrir a página de novo que ela continua
exatamente de onde parou.

Erro mais comum: dados do banco errados no `.env`. A própria página avisa
quais campos conferir.

---

## Para quem tem linha de comando

Mesma coisa, pelo terminal ou pelo cron:

```bash
php database/atualizar.php --conferir   # só mostra o que falta
php database/atualizar.php              # aplica
```

---

## O que já está incluído

Estas são as 8 atualizações que existem hoje. No seu caso, todas já estão
aplicadas (foram feitas à mão nas etapas anteriores) — o sistema vai
reconhecer isso e apenas registrar o histórico, sem mexer em nada:

| Migração | O que faz |
|---|---|
| `0001_estrutura_base` | Tabelas originais do sistema |
| `0002_tokens_com_expiracao` | Separa token de ativação e de senha, com prazo |
| `0003_redes_sociais` | Campos de redes sociais da empresa |
| `0004_conciliacao_pagamentos` | Vínculo pagamento ↔ licença (evita duplicidade) |
| `0005_tentativas_de_login` | Tabela de bloqueio por força bruta |
| `0006_indices_de_performance` | Índices que aceleram a busca |
| `0007_fotos_dos_produtos` | Tabela das fotos |
| `0008_higienizacao` | Ajustes nos dados vindos da v1 |

---

## Uma observação sobre segurança

A pasta `database/` inteira é bloqueada pela web — conferi que os arquivos de
migração retornam **403**. Só a página `public/atualizar.php` é acessível, e
ainda assim apenas com a chave correta.

Ainda assim, vale o hábito: **faça um backup antes de aplicar atualizações
grandes**. O script de manutenção já gera backup automático se você agendou
no cron; se não, dá para baixar um pelo phpMyAdmin (aba Exportar).
