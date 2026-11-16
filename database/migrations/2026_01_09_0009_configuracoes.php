<?php

/**
 * Configurações editáveis pelo administrador.
 *
 * Antes, telefone, e-mail e endereço estavam escritos dentro das telas. Uma
 * troca de número exigia mexer em seis arquivos e subir tudo de novo — e era
 * fácil esquecer um. Agora ficam em banco, com uma tela no painel.
 *
 * Os valores são semeados apenas quando a chave ainda não existe, então rodar
 * a migração de novo não desfaz o que o administrador tiver ajustado.
 */

use App\Core\Esquema;

return [
    'descricao' => 'Configurações do site editáveis pelo administrador',

    'executar' => function (PDO $pdo, Esquema $e): array {
        $passos = [];

        $passos[] = $e->tabela('settings', "
            `chave` VARCHAR(64) NOT NULL PRIMARY KEY,
            `valor` TEXT NULL,
            `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");

        $padroes = [
            'contato_email'          => 'daniel_amorimtj@hotmail.com',
            'contato_telefone'       => '(11) 99527-0932',
            'contato_whatsapp'       => '5511995270932',
            'contato_endereco'       => 'Rua General Osório, 299 Sala 1 - Santa Ifigênia - São Paulo/SP',
            'contato_horario'        => 'Seg - Sex: 08:00 às 18:00',
        ];

        $inserir = $pdo->prepare(
            'INSERT INTO `settings` (`chave`, `valor`) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE `chave` = `chave`'
        );

        $novas = 0;
        foreach ($padroes as $chave => $valor) {
            $inserir->execute(['chave' => $chave, 'valor' => $valor]);
            $novas += $inserir->rowCount() > 0 ? 1 : 0;
        }

        if ($novas > 0) {
            $passos[] = "Valores padrão gravados em settings ({$novas} chave(s))";
        }

        return array_values(array_filter($passos));
    },
];
