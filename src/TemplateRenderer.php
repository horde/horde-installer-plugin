<?php

declare(strict_types=1);

namespace Horde\Composer;

use RuntimeException;

class TemplateRenderer
{
    /**
     * Render a PHP template file with the given variables.
     *
     * @param string $templatePath Absolute path to the template file
     * @param array<string, mixed> $vars Variables to extract into the template scope
     */
    public function render(string $templatePath, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $templatePath;
        $result = ob_get_clean();
        if ($result === false) {
            throw new RuntimeException('Output buffering failed while rendering template: ' . $templatePath);
        }
        return $result;
    }
}
