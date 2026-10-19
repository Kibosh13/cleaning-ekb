<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
const CONTENT_FILE = ROOT . '/data/content.json';
$config = require ROOT . '/config.example.php';
if (!is_preview() && is_file(ROOT . '/config.local.php')) $config = array_replace($config, require ROOT . '/config.local.php');
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function content(): array { return json_decode((string)file_get_contents(CONTENT_FILE), true, 512, JSON_THROW_ON_ERROR); }
function is_preview(): bool { return defined('STATIC_PREVIEW') && STATIC_PREVIEW; }
function page_url(string $name): string { return '/' . $name . (is_preview() ? '.html' : ''); }
function image_path(string $v): string {
    return preg_match('~^/(?:assets|uploads)/[a-zA-Z0-9_./-]+\.(?:webp|jpe?g|png)$~D', $v) && !str_contains($v, '..') ? $v : '/assets/living.webp';
}
function https_url(string $v): string { return filter_var($v, FILTER_VALIDATE_URL) && parse_url($v, PHP_URL_SCHEME) === 'https' ? $v : ''; }
function phone_url(string $v): string { return 'tel:+' . preg_replace('/\D/', '', $v); }
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('chistota_session');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
}
function csrf(): string { start_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): bool { start_session(); return is_string($_POST['csrf'] ?? null) && is_string($_SESSION['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
function save_content(array $data): void {
    validate_content($data);
    $lock = fopen(ROOT . '/data/content.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Не удалось заблокировать файл.');
    try {
        $tmp = tempnam(ROOT . '/data', 'content-');
        if (!$tmp || file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Ошибка записи.');
        chmod($tmp, 0640);
        if (!rename($tmp, CONTENT_FILE)) throw new RuntimeException('Ошибка сохранения.');
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function validate_content(array $d): void {
    foreach (['brand','hero_title','hero_description','hero_image','phone','email','area','maps_url','telegram_url','about_title','about_text','about_image','services_intro','services_note','operator','inn','operator_address','guarantee_terms'] as $key) {
        if (!isset($d[$key]) || !is_string($d[$key]) || mb_strlen($d[$key]) > 6000) throw new InvalidArgumentException('Проверьте поле: '.$key);
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^\+?[\d\s()\-]{10,25}$/', $d['phone'])) throw new InvalidArgumentException('Проверьте контакты.');
    foreach(['maps_url','telegram_url'] as $k) if($d[$k]!=='' && !https_url($d[$k])) throw new InvalidArgumentException('Нужна HTTPS-ссылка: '.$k);
    foreach(['hero_image','about_image'] as $k) if(image_path($d[$k])!==$d[$k]) throw new InvalidArgumentException('Недопустимый путь изображения.');
    foreach(['services','faq','reviews','cases'] as $k) if(!isset($d[$k]) || !is_array($d[$k]) || count($d[$k])>30) throw new InvalidArgumentException('Проверьте раздел: '.$k);
    if(count($d['services'])<1 || count($d['faq'])<1) throw new InvalidArgumentException('Добавьте услуги и FAQ.');
    $ids=[];
    foreach($d['services'] as $s){
        foreach(['id','title','group','description','price','image','note'] as $k) if(!isset($s[$k]) || !is_string($s[$k]) || mb_strlen($s[$k])>3000) throw new InvalidArgumentException('Некорректная услуга.');
        if(!preg_match('/^[a-z0-9-]{1,40}$/D',$s['id']) || isset($ids[$s['id']])) throw new InvalidArgumentException('ID услуг должны быть уникальными.');
        $ids[$s['id']]=true;
        if(!preg_match('/^(home|business|special)( (home|business|special))*$/D',$s['group'])) throw new InvalidArgumentException('Неизвестная группа услуги.');
        if(image_path($s['image'])!==$s['image'] || !is_array($s['includes']) || count($s['includes'])>20) throw new InvalidArgumentException('Проверьте фото и состав услуги.');
        foreach($s['includes'] as $line) if(!is_string($line) || mb_strlen($line)>500) throw new InvalidArgumentException('Слишком длинный состав услуги.');
    }
    foreach($d['faq'] as $f) foreach(['question','answer'] as $k) if(!is_string($f[$k]??null) || mb_strlen($f[$k])>6000) throw new InvalidArgumentException('Проверьте FAQ.');
    foreach($d['reviews'] as $r) {
        foreach(['name','quote','source'] as $k) if(!is_string($r[$k]??null) || mb_strlen($r[$k])>3000) throw new InvalidArgumentException('Проверьте отзыв.');
        if(!https_url($r['source'])) throw new InvalidArgumentException('Укажите HTTPS-источник отзыва.');
    }
    foreach($d['cases'] as $c) {
        foreach(['title','description','image'] as $k) if(!is_string($c[$k]??null) || mb_strlen($c[$k])>3000) throw new InvalidArgumentException('Проверьте кейс.');
        if(image_path($c['image'])!==$c['image']) throw new InvalidArgumentException('Проверьте фото кейса.');
    }
}
function rate_allowed(string $bucket, int $max, int $seconds): bool {
    $key=hash('sha256', $bucket . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $file=ROOT . '/data/rate-' . $key . '.json';
    $fp=fopen($file, 'c+');
    if(!$fp || !flock($fp, LOCK_EX)) return false;
    try {
        $events=json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
        $events=array_values(array_filter($events, fn($t)=>is_int($t) && $t>time()-$seconds));
        $ok=count($events)<$max;
        if($ok) $events[]=time();
        rewind($fp); ftruncate($fp,0); fwrite($fp,json_encode($events));
        return $ok;
    } finally { flock($fp, LOCK_UN); fclose($fp); }
}
function common_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
