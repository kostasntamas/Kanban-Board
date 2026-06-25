<?php
function vite_assets(string $entry): string
{
    $hotPath      = __DIR__ . '/../dist/hot';
    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';

    if (file_exists($hotPath)) {
        $devServer = trim(file_get_contents($hotPath));
        return '<script type="module" src="' . $devServer . '/@vite/client"></script>' . "\n"
             . '<script type="module" src="' . $devServer . '/' . $entry . '"></script>' . "\n";
    }

    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $chunk    = $manifest[$entry] ?? null;
        if (!$chunk) return '';

        $html = '';
        foreach ($chunk['css'] ?? [] as $css) {
            $html .= '<link rel="stylesheet" href="dist/' . $css . '">' . "\n";
        }
        $html .= '<script type="module" src="dist/' . $chunk['file'] . '"></script>' . "\n";
        return $html;
    }

    return '';
}
