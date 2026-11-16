<?php

namespace App\Core;

class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);

        $viewFile = base_path('views/' . ltrim($view, '/') . '.php');
        if (!file_exists($viewFile)) {
            $this->fail("Visualização [{$view}] não encontrada.");
        }

        // Se layout for vazio ou falso, renderiza apenas o arquivo da view
        if (!$layout) {
            require $viewFile;
            flush_old_input();
            return;
        }

        $layoutFile = base_path('views/layouts/' . $layout . '.php');
        if (!file_exists($layoutFile)) {
            $this->fail("Layout [{$layout}] não encontrado.");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;

        // Os dados antigos do formulário valem para uma única renderização
        flush_old_input();
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redireciona gravando mensagens flash. Quando a mensagem é de erro, os
     * dados digitados no formulário são preservados para repopular os campos.
     */
    protected function redirect(string $path, array $flash = []): void
    {
        foreach ($flash as $key => $message) {
            flash($key, $message);
        }

        if (isset($flash['error']) || isset($flash['warning'])) {
            remember_old_input();
        }

        header('Location: ' . url($path));
        exit;
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
            }

            http_response_code(419);
            flash('error', 'Sua sessão expirou por segurança. Por favor, envie o formulário novamente.');
            header('Location: ' . url(''));
            exit;
        }
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        // Nunca deixa array chegar onde se espera texto
        if (is_array($value) && !is_array($default)) {
            return $default;
        }

        return $value;
    }

    protected function text(string $key, string $default = '', int $maxLength = 255): string
    {
        $value = trim((string) $this->input($key, $default));

        // Entrada que não é UTF-8 válido (colagem vinda de sistema legado,
        // navegador antigo, ERP) é convertida em vez de descartada: sem isso
        // o filtro abaixo devolveria null e o campo chegaria vazio.
        if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        if ($cleaned === null) {
            // Último recurso: remove os bytes de controle sem interpretar UTF-8
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        }

        return mb_substr($cleaned, 0, $maxLength);
    }

    protected function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    protected function notFound(): void
    {
        http_response_code(404);
        require base_path('views/errors/404.php');
        exit;
    }

    /**
     * Erro interno: detalhes só aparecem em ambiente de desenvolvimento.
     */
    protected function fail(string $message): void
    {
        error_log('[StoqueOnline] ' . $message);

        http_response_code(500);
        if (app_debug()) {
            exit($message);
        }

        require base_path('views/errors/500.php');
        exit;
    }
}
