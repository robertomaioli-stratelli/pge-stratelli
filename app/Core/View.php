<?php
namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'app'): void
    {
        $viewsRoot = dirname(__DIR__) . '/Views/';
        $viewFile = $viewsRoot . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View não encontrada: ' . $view);
        }

        // Cada template roda em um escopo isolado. Assim, variáveis internas da view
        // (ex.: $base, $layout, $content) nunca sobrescrevem variáveis do motor de views.
        $renderFile = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        if ($layout !== null && Auth::check()) {
            try {
                $notificationService = new \App\Services\NotificationService();
                $data['notificacoes'] = $notificationService->recentForCurrentUser(8);
                $data['notificacaoContagemAtiva'] = $notificationService->unreadCount();
            } catch (\Throwable $e) {
                $data['notificacoes'] = [];
                $data['notificacaoContagemAtiva'] = 0;
            }
        }

        $content = $renderFile($viewFile, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $viewsRoot . 'layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout não encontrado: ' . $layout);
        }

        echo $renderFile($layoutFile, $data + ['content' => $content]);
    }
}
