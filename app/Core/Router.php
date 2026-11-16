<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        // {id} aceita somente números; {slug} aceita texto simples
        $pattern = preg_replace('/\{id\}/', '(?P<id>\d+)', $path);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', (string) $pattern);
        $pattern = '#^' . rtrim((string) $pattern, '/') . '$#';

        $this->routes[] = [
            'method'      => $method,
            'path'        => $path,
            'pattern'     => $pattern === '#^$#' ? '#^/$#' : $pattern,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(): void
    {
        $this->sendSecurityHeaders();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $uri = $this->currentUri();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            // Parâmetros nomeados da URL
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            try {
                foreach ($route['middlewares'] as $middleware) {
                    $this->runMiddleware($middleware);
                }

                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$controllerClass, $action] = $handler;
                    $controller = new $controllerClass();
                    call_user_func_array([$controller, $action], $params);
                    return;
                }

                call_user_func_array($handler, $params);
                return;
            } catch (\Throwable $e) {
                $this->handleException($e);
                return;
            }
        }

        // Caminho existe, mas o método HTTP não é permitido
        if ($pathMatched) {
            http_response_code(405);
            header('Allow: GET, POST');
            require base_path('views/errors/404.php');
            return;
        }

        http_response_code(404);
        require base_path('views/errors/404.php');
    }

    private function currentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        // Remove o caminho base, seja qual for a forma de publicação:
        // domínio apontando para /public, domínio apontando para a raiz do
        // projeto, ou instalação em subdiretório (XAMPP local).
        foreach ($this->basePrefixes() as $prefix) {
            if ($this->pathStartsWith($uri, $prefix)) {
                $uri = substr($uri, strlen($prefix));
                break;
            }
        }

        $uri = '/' . trim($uri, '/');

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Prefixos candidatos a caminho base, do mais específico para o mais
     * genérico.
     *
     * @return string[]
     */
    private function basePrefixes(): array
    {
        $prefixes = [];

        // 1. Caminho declarado em APP_URL (fonte de verdade da configuração)
        $appBase = base_uri();
        if ($appBase !== '' && $appBase !== '/') {
            $prefixes[] = $appBase;

            // APP_URL pode terminar em /public enquanto o acesso é pela raiz
            if (str_ends_with($appBase, '/public')) {
                $prefixes[] = substr($appBase, 0, -strlen('/public'));
            }
        }

        // 2. Diretório real do front controller
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '') {
            $prefixes[] = $scriptDir;

            // O .htaccess da raiz reescreve para /public: nesse caso o script
            // está um nível abaixo do caminho que o visitante digitou.
            if (str_ends_with($scriptDir, '/public')) {
                $parent = substr($scriptDir, 0, -strlen('/public'));
                if ($parent !== '') {
                    $prefixes[] = $parent;
                }
            }
        }

        $prefixes = array_values(array_unique(array_filter($prefixes)));

        // O prefixo mais longo tem precedência
        usort($prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $prefixes;
    }

    /**
     * Compara caminhos por segmento: "/publicidade" não pode ser confundido
     * com o prefixo "/public".
     */
    private function pathStartsWith(string $uri, string $prefix): bool
    {
        if ($prefix === '' || $prefix === '/') {
            return false;
        }

        if ($uri === $prefix) {
            return true;
        }

        return str_starts_with($uri, rtrim($prefix, '/') . '/');
    }

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header_remove('X-Powered-By');

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function handleException(\Throwable $e): void
    {
        error_log('[StoqueOnline] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());

        http_response_code(500);

        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => app_debug() ? $e->getMessage() : 'Erro interno no servidor.',
            ]);
            return;
        }

        if (app_debug()) {
            echo '<h1>Erro interno</h1><pre>' . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
            return;
        }

        require base_path('views/errors/500.php');
    }

    private function runMiddleware(string $middleware): void
    {
        Session::start();

        switch ($middleware) {
            case 'auth':
                if (!Auth::check() || !Auth::user()) {
                    flash('error', 'Por favor, faça login para acessar esta página.');
                    header('Location: ' . url('login'));
                    exit;
                }
                break;

            case 'guest':
                if (Auth::check()) {
                    header('Location: ' . url());
                    exit;
                }
                break;

            case 'admin':
                if (!Auth::check() || !Auth::isAdmin()) {
                    flash('error', 'Acesso restrito para administradores.');
                    header('Location: ' . url());
                    exit;
                }
                break;

            case 'license':
                if (!Auth::check() || !Auth::user()) {
                    flash('error', 'Por favor, faça login para acessar.');
                    header('Location: ' . url('login'));
                    exit;
                }
                if (!Auth::hasActiveLicense()) {
                    flash('warning', 'Você precisa de uma assinatura ou plano ativo para utilizar este recurso.');
                    header('Location: ' . url('planos'));
                    exit;
                }
                break;
        }
    }
}
