<?php

return [
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => env('DB_PORT', '3306'),
    'database' => env('DB_NAME', 'bearing_v2'),
    'username' => env('DB_USER', 'root'),
    'password' => (string) env('DB_PASS', ''),
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Prepares reais: o servidor separa SQL de dados, eliminando qualquer
        // chance de injeção por escape incorreto do driver.
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        // rowCount() passa a contar as linhas encontradas, e não apenas as
        // alteradas: um UPDATE que grava o mesmo valor deixa de parecer falha.
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];
