<?php

/**
 * Carregador de variáveis de ambiente (.env) nativo e seguro
 */
if (!function_exists('loadEnv')) {
    function loadEnv(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover aspas simples ou duplas
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv(sprintf('%s=%s', $key, $value));
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

if (!function_exists('env_file_path')) {
    /**
     * Qual arquivo de configuração vale nesta máquina.
     *
     *   Hostinger (Linux)          -> .env.producao
     *   computador local (Windows) -> .env
     *
     * Os dois arquivos andam juntos na pasta do projeto, então ela pode ir
     * inteira pelo FTP: antes havia um .env só, e o do computador (root sem
     * senha, depuração ligada) sobrescrevia o do servidor e derrubava o site.
     *
     * A escolha é pela máquina, nunca pelo endereço acessado — o cabeçalho
     * Host vem do visitante e pode ser forjado para "localhost".
     * Sem o .env.producao no servidor, cai no .env como antes.
     */
    function env_file_path(?string $sistema = null): string
    {
        $raiz = dirname(__DIR__);
        $producao = $raiz . '/.env.producao';

        if (($sistema ?? PHP_OS_FAMILY) !== 'Windows' && is_file($producao)) {
            return $producao;
        }

        return $raiz . '/.env';
    }
}

loadEnv(env_file_path());

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        return match (strtolower((string)$value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('app_debug')) {
    function app_debug(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }
}

// ---------------------------------------------------------------------
// Fuso horário e tratamento de erros
// ---------------------------------------------------------------------
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

if (app_debug()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Em produção nada de stack trace na tela: tudo vai para o log
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . '/php-errors.log');
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $baseUrl = rtrim((string) env('APP_URL', 'http://localhost/stoqueonline/stoqueonline_v2/public'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('base_uri')) {
    /**
     * Caminho base da aplicação (sem host), usado pelo JavaScript para montar
     * as chamadas de API sem depender da presença de "/public" na URL.
     */
    function base_uri(): string
    {
        $path = parse_url((string) env('APP_URL', ''), PHP_URL_PATH);
        return rtrim((string) $path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $file = base_path('public/' . ltrim($path, '/'));
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return url($path) . $version;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('safe_url')) {
    /**
     * Devolve a URL apenas se o esquema for seguro para uso em href.
     * Bloqueia javascript:, data:, vbscript: etc.
     */
    function safe_url(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        // Sem esquema explícito assume-se https
        if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }

        return $url;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        if (isset($_SESSION['_flash'][$key])) {
            $msg = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $msg;
        }

        return null;
    }
}

if (!function_exists('remember_old_input')) {
    /**
     * Guarda os dados do formulário para repopular os campos após um erro
     * de validação. Campos sensíveis nunca são memorizados.
     */
    function remember_old_input(?array $input = null): void
    {
        $input = $input ?? $_POST;
        $blocked = ['password', 'password_confirm', 'senha', '_csrf', 'token'];

        $_SESSION['_old_input'] = array_filter(
            array_map(
                static fn($value) => is_array($value) ? $value : (string) $value,
                array_diff_key($input, array_flip($blocked))
            ),
            static fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('flush_old_input')) {
    /**
     * Chamado pelo layout depois de renderizar o formulário: os dados antigos
     * valem para uma única requisição, igual às mensagens flash.
     */
    function flush_old_input(): void
    {
        unset($_SESSION['_old_input']);
    }
}

if (!function_exists('setting')) {
    /**
     * Configuração editável pelo administrador em /admin/configuracoes.
     *
     * Existe para que telefone, e-mail e endereço deixem de estar escritos
     * dentro das telas: trocar o contato passa a ser um formulário, não uma
     * nova subida de arquivos.
     */
    function setting(string $chave, string $padrao = ''): string
    {
        return \App\Models\Setting::obter($chave, $padrao);
    }
}

if (!function_exists('whatsapp_link')) {
    /**
     * Link pronto do WhatsApp a partir do número configurado. Devolve string
     * vazia quando não há número, para a tela poder esconder o botão.
     */
    function whatsapp_link(): string
    {
        return \App\Models\Setting::whatsappLink();
    }
}
