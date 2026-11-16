<?php

/**
 * =====================================================================
 * StoqueOnline v2 - Entrada na raiz do projeto
 * =====================================================================
 *
 * O front controller oficial é public/index.php, e o .htaccess da raiz
 * encaminha as requisições para lá automaticamente.
 *
 * Este arquivo existe como rede de segurança: se o domínio apontar para a
 * raiz do projeto e o mod_rewrite estiver indisponível, o site continua
 * abrindo normalmente ao acessar http://seudominio/.
 *
 * A forma recomendada de publicação continua sendo apontar o domínio
 * diretamente para a pasta public/.
 */

require __DIR__ . '/public/index.php';
