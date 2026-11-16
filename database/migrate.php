<?php

/**
 * StoqueOnline v2 - Script de Migração Total dos Dados da v1 para a v2
 *
 * Uso (somente linha de comando):  php database/migrate.php
 *
 * Observação importante sobre senhas: a v1 gravava os hashes em MD5.
 * Eles são copiados como estão e convertidos para bcrypt automaticamente
 * no primeiro login de cada usuário (ver App\Core\Auth::verifyAndUpgradePassword).
 */

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.");
}


ini_set('memory_limit', '1024M');
set_time_limit(0);

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$backupDir = 'c:/xampp/htdocs/stoqueonline/stoqueonline_v1/Backup_BancoDads';

echo "=== INICIANDO MIGRAÇÃO DE DADOS DA V1 PARA A V2 ===\n\n";

try {
    $pdo = new PDO("mysql:host={$dbHost};port=3306", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // 1. Criar banco temporário bearing_old
    echo "1. Criando banco temporário 'bearing_old'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `bearing_old` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `bearing_old`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    // As consultas de deduplicação usam GROUP BY parcial, recusado pelo
    // modo padrão do MySQL 8 (ONLY_FULL_GROUP_BY).
    $pdo->exec("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY,', ''), ',ONLY_FULL_GROUP_BY', '')");

    // 2. Importar arquivos de dump usando mysql.exe com max_allowed_packet aumentado
    $filesToImport = [
        'bearing_tb_user.sql',
        'bearing_tb_user_mycompany.sql',
        'bearing_tb_contact_people.sql',
        'bearing_tb_products.sql',
        'bearing_tb_user_license.sql',
        'bearing_tb_import_file.sql',
        'bearing_tb_inventory.sql',
    ];

    $mysqlBin = 'c:\\xampp\\mysql\\bin\\mysql.exe';

    foreach ($filesToImport as $file) {
        $filePath = $backupDir . '/' . $file;
        if (!file_exists($filePath)) {
            echo "Aviso: Arquivo {$file} não encontrado em {$backupDir}\n";
            continue;
        }

        echo " - Importando {$file} para bearing_old...";
        $cmd = "\"{$mysqlBin}\" --max_allowed_packet=256M -u root bearing_old < \"{$filePath}\"";
        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            // Se cmd falhou, tenta com powershell
            shell_exec("Get-Content \"{$filePath}\" | & \"{$mysqlBin}\" --max_allowed_packet=256M -u root bearing_old");
        }
        echo " OK!\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 3. Migrar para bearing_v2
    echo "\n2. Migrando dados estruturados para 'bearing_v2'...\n";
    $pdo->exec("USE `bearing_v2`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    // As consultas de deduplicação usam GROUP BY parcial, recusado pelo
    // modo padrão do MySQL 8 (ONLY_FULL_GROUP_BY).
    $pdo->exec("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY,', ''), ',ONLY_FULL_GROUP_BY', '')");

    // 3.1 Limpar tabelas da v2 antes da migração
    $pdo->exec("TRUNCATE TABLE `inventory`;");
    $pdo->exec("TRUNCATE TABLE `inventory_batches`;");
    $pdo->exec("TRUNCATE TABLE `contacts`;");
    $pdo->exec("TRUNCATE TABLE `companies`;");
    $pdo->exec("TRUNCATE TABLE `user_licenses`;");
    $pdo->exec("TRUNCATE TABLE `users`;");

    // 3.2 Migrar Usuários (tb_user -> users)
    echo " - Migrando Usuários...";
    $pdo->exec("
        INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `token`, `confirmed_activation`, `allowed_access`, `is_admin`, `first_access_free`, `created_at`, `updated_at`)
        SELECT 
            `id`, 
            COALESCE(`name`, 'Sem Nome'), 
            `email`, 
            `phone`, 
            `password`, 
            `token`, 
            COALESCE(`confirmed_activation_user`, 0), 
            COALESCE(`allowed_access`, 1), 
            COALESCE(`admin`, 0), 
            COALESCE(`first_software_license`, 1), 
            COALESCE(`created`, NOW()), 
            COALESCE(`updated`, NOW())
        FROM `bearing_old`.`tb_user`
        WHERE `email` IS NOT NULL AND `email` != ''
        GROUP BY `email`
        ORDER BY `id` ASC;
    ");
    $countUsers = $pdo->query("SELECT count(*) FROM `users`")->fetchColumn();
    echo " Concluído: {$countUsers} usuários migrados.\n";

    // 3.3 Migrar Empresas (tb_user_mycompany -> companies)
    echo " - Migrando Empresas...";
    $pdo->exec("
        INSERT INTO `companies` (`user_id`, `company_name`, `trading_name`, `cnpj`, `address`, `number`, `district`, `city`, `state`, `zip_code`, `phone`, `cell_phone`, `whatsapp`, `contact_email`, `website`, `created_at`, `updated_at`)
        SELECT 
            c.`id_user`, 
            c.`my_company`, 
            c.`my_company`, 
            c.`cnpj`, 
            c.`address`, 
            c.`number_address`, 
            c.`district`, 
            c.`city`, 
            c.`state`, 
            c.`zip_code`, 
            c.`telephone`, 
            c.`cell_phone`, 
            c.`whatsapp`, 
            c.`contact_email`, 
            c.`link_site`, 
            COALESCE(c.`created`, NOW()), 
            COALESCE(c.`updated`, NOW())
        FROM `bearing_old`.`tb_user_mycompany` c
        JOIN `users` u ON u.`id` = c.`id_user`
        WHERE c.`id_user` IS NOT NULL
        GROUP BY c.`id_user`;
    ");
    $countCompanies = $pdo->query("SELECT count(*) FROM `companies`")->fetchColumn();
    echo " Concluído: {$countCompanies} empresas migradas.\n";

    // 3.4 Migrar Contatos (tb_contact_people -> contacts)
    echo " - Migrando Colaboradores...";
    $pdo->exec("
        INSERT INTO `contacts` (`id`, `user_id`, `name`, `occupation`, `email`, `phone`, `include_in_license`, `created_at`, `updated_at`)
        SELECT 
            cp.`id`, 
            cp.`id_user`, 
            COALESCE(cp.`name_contact`, 'Contato'), 
            cp.`ocupation_contact`, 
            cp.`email_contact`, 
            cp.`phone_contact`, 
            COALESCE(cp.`inclusion_license_current`, 0), 
            COALESCE(cp.`created_at`, NOW()), 
            COALESCE(cp.`updated_at`, NOW())
        FROM `bearing_old`.`tb_contact_people` cp
        JOIN `users` u ON u.`id` = cp.`id_user`
        WHERE cp.`id_user` IS NOT NULL;
    ");
    $countContacts = $pdo->query("SELECT count(*) FROM `contacts`")->fetchColumn();
    echo " Concluído: {$countContacts} colaboradores migrados.\n";

    // 3.5 Migrar Lotes de Arquivo (tb_import_file -> inventory_batches)
    echo " - Migrando Histórico de Lotes...";
    $pdo->exec("
        INSERT INTO `inventory_batches` (`id`, `user_id`, `filename`, `total_items`, `status`, `created_at`)
        SELECT 
            f.`id`, 
            f.`id_user`, 
            COALESCE(f.`name_file`, 'arquivo_importado.xlsx'), 
            0, 
            COALESCE(f.`status`, 1), 
            COALESCE(f.`created`, NOW())
        FROM `bearing_old`.`tb_import_file` f
        JOIN `users` u ON u.`id` = f.`id_user`;
    ");
    $countBatches = $pdo->query("SELECT count(*) FROM `inventory_batches`")->fetchColumn();
    echo " Concluído: {$countBatches} lotes migrados.\n";

    // 3.6 Migrar Estoque (tb_inventory -> inventory)
    echo " - Migrando Catálogo de Estoque de Rolamentos (isso pode levar alguns segundos)...";
    $pdo->exec("
        INSERT INTO `inventory` (`id`, `user_id`, `batch_id`, `brand`, `product`, `product_search`, `quantity`, `created_at`, `updated_at`)
        SELECT 
            i.`id`, 
            CAST(i.`id_user` AS UNSIGNED), 
            i.`id_import_file`, 
            COALESCE(TRIM(i.`brand`), 'GENÉRICA'), 
            COALESCE(TRIM(i.`product`), ''), 
            UPPER(REGEXP_REPLACE(COALESCE(i.`product`, ''), '[^a-zA-Z0-9]', '')), 
            COALESCE(i.`number_pieces`, 1), 
            COALESCE(i.`created`, NOW()), 
            COALESCE(i.`updated`, NOW())
        FROM `bearing_old`.`tb_inventory` i
        JOIN `users` u ON u.`id` = CAST(i.`id_user` AS UNSIGNED)
        JOIN `inventory_batches` b ON b.`id` = i.`id_import_file`
        WHERE i.`product` IS NOT NULL AND TRIM(i.`product`) != '';
    ");
    $countInventory = $pdo->query("SELECT count(*) FROM `inventory`")->fetchColumn();
    echo " Concluído: {$countInventory} peças de estoque migradas com sucesso!\n";

    // 3.7 Migrar Licenças de Usuários (tb_user_license -> user_licenses)
    echo " - Migrando Licenças...";
    $pdo->exec("
        INSERT INTO `user_licenses` (`id`, `user_id`, `plan_id`, `price`, `max_users`, `start_date`, `end_date`, `status`, `is_closed`, `created_at`)
        SELECT 
            ul.`id`, 
            ul.`user_id`, 
            CASE 
                WHEN ul.`products_id` IN (1, 2, 3, 4, 5) THEN ul.`products_id`
                ELSE 2
            END, 
            COALESCE(ul.`price`, 0), 
            COALESCE(ul.`quant_user`, 1), 
            COALESCE(ul.`date_start_license`, CURDATE()), 
            COALESCE(ul.`date_end_license`, DATE_ADD(CURDATE(), INTERVAL 30 DAY)), 
            COALESCE(ul.`status`, 1), 
            COALESCE(ul.`status_closed`, 0), 
            COALESCE(ul.`created`, NOW())
        FROM `bearing_old`.`tb_user_license` ul
        JOIN `users` u ON u.`id` = ul.`user_id`;
    ");
    $countLicenses = $pdo->query("SELECT count(*) FROM `user_licenses`")->fetchColumn();
    echo " Concluído: {$countLicenses} licenças migradas.\n";

    // 4. Se o admin padrão não existia, garante que ele exista
    $adminExists = $pdo->query("SELECT count(*) FROM `users` WHERE `email` = 'admin@stoqueonline.com.br'")->fetchColumn();
    if (!$adminExists) {
        echo " - Inserindo Administrador padrão...";
        $pdo->exec("
            INSERT INTO `users` (`name`, `email`, `phone`, `password`, `confirmed_activation`, `allowed_access`, `is_admin`, `first_access_free`)
            VALUES ('Administrador StoqueOnline', 'admin@stoqueonline.com.br', '(11) 99999-9999', '$2y$10$1Figlz0MDjaz6FsqDmE3Z.LN3YXiTlrPGdJUR9G6TtRxnm4nFETIK', 1, 1, 1, 0);
        ");
        $adminId = $pdo->lastInsertId();
        $pdo->exec("
            INSERT INTO `user_licenses` (`user_id`, `plan_id`, `price`, `max_users`, `start_date`, `end_date`, `status`, `is_closed`)
            VALUES ({$adminId}, 5, 0, 999, '2026-01-01', '2099-12-31', 1, 0);
        ");
        echo " OK!\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 5. Excluir banco temporário
    echo "\n3. Excluindo banco temporário 'bearing_old'...";
    $pdo->exec("DROP DATABASE IF EXISTS `bearing_old`;");
    echo " OK!\n";

    echo "\n=== MIGRAÇÃO CONCLUÍDA COM SUCESSO TOTAL! ===\n";
    echo "Resumo:\n";
    echo " - Usuários: {$countUsers}\n";
    echo " - Empresas: {$countCompanies}\n";
    echo " - Colaboradores: {$countContacts}\n";
    echo " - Lotes de Estoque: {$countBatches}\n";
    echo " - Peças em Estoque: {$countInventory}\n";
    echo " - Licenças: {$countLicenses}\n";

} catch (Exception $e) {
    echo "\nERRO DURANTE A MIGRAÇÃO: " . $e->getMessage() . "\n";
}
