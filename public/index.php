<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
common_headers();
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($path!=='/' && str_ends_with($path,'/')) { header('Location: /'.ltrim(rtrim($path,'/'),'/'),true,301);exit; }
$route=trim($path,'/');
if($route==='robots.txt') {
 header('Content-Type: text/plain; charset=utf-8');
 echo "User-agent: *\nDisallow: /admin.php\nDisallow: /api/\nDisallow: /uploads/\n";
 if($config['base_url']) echo 'Sitemap: '.rtrim($config['base_url'],'/')."/sitemap.xml\n";
 exit;
}
if($route==='sitemap.xml') {
 header('Content-Type: application/xml; charset=utf-8');
 echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
 if($config['base_url']) foreach(['','privacy','consent','cookies'] as $p) echo '<url><loc>'.e(rtrim($config['base_url'],'/').'/'.$p).'</loc></url>';
 echo '</urlset>';exit;
}
if($route==='') { require ROOT.'/app/page.php'; exit; }
if(in_array($route,['privacy','consent','cookies'],true)) { require ROOT.'/app/legal.php';exit; }
http_response_code(404);require ROOT.'/app/error.php';
