<?php
declare(strict_types=1);
// Static demo: no server endpoints, authentication, analytics or private config.
define('STATIC_PREVIEW', true);
require __DIR__.'/../app/bootstrap.php';
$out = $argv[1] ?? '';
$previewUrl = rtrim($argv[2] ?? '', '/');
if (!$out || !is_dir($out) || !filter_var($previewUrl, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php scripts/export-preview.php OUTPUT_DIRECTORY HTTPS_DEMO_URL\n");
    exit(1);
}
if (parse_url($previewUrl, PHP_URL_SCHEME) !== 'https' && !in_array(parse_url($previewUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "The demo URL must use HTTPS.\n");
    exit(1);
}
$basePath = rtrim((string) parse_url($previewUrl, PHP_URL_PATH), '/');
$config['base_url'] = $previewUrl;
$config['metrika_id'] = '';
$config['webmaster_verification'] = '';
function save_demo_page(string $name, string $html): void {
    global $out, $basePath;
    // Project Pages use /repository/ rather than the account's domain root.
    $html = preg_replace_callback('~\b(href|src|action)="(/(?!/)[^"]*)"~',
        fn($m) => $m[1].'="'.$basePath.$m[2].'"', $html);
    $html = preg_replace_callback('~(<script id="services-data" type="application/json">)(.*?)(</script>)~s',
        function($m) use ($basePath) {
            $services = json_decode($m[2], true, 512, JSON_THROW_ON_ERROR);
            foreach ($services as &$service) $service['image'] = $basePath.$service['image'];
            unset($service);
            return $m[1].json_encode($services, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).$m[3];
        }, $html);
    if (file_put_contents($out.'/'.$name, $html) === false) throw new RuntimeException('Could not write '.$name);
}
ob_start(); require ROOT.'/app/page.php'; save_demo_page('index.html', ob_get_clean());
foreach (['privacy','consent','cookies'] as $route) {
    ob_start(); require ROOT.'/app/legal.php'; save_demo_page($route.'.html', ob_get_clean());
}
ob_start(); require ROOT.'/app/error.php'; save_demo_page('404.html', ob_get_clean());
file_put_contents($out.'/robots.txt', "User-agent: *\nDisallow: /\n");
// No demo sitemap is advertised; every HTML page has an explicit noindex directive.
file_put_contents($out.'/.nojekyll', '');
if (!is_dir($out.'/assets')) mkdir($out.'/assets', 0755, true);
foreach (glob(ROOT.'/public/assets/*') as $file) copy($file, $out.'/assets/'.basename($file));
fwrite(STDOUT, "Static demo exported to $out\n");
