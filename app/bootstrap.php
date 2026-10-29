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
function service_price(array $service): string {
    if (str_contains($service['price'], '[заполнить')) return 'Стоимость по запросу';
    return 'от '.$service['price'].' '.($service['price_unit'] ?? '₽').(!empty($service['price_context']) ? ' · '.$service['price_context'] : '');
}
function validate_tables(array $tables): void {
    if (count($tables)>15) throw new InvalidArgumentException('Слишком много таблиц стоимости.');
    foreach ($tables as $table) {
        if (!is_array($table) || !is_string($table['title']??null) || mb_strlen($table['title'])>300 || !is_array($table['columns']??null) || count($table['columns'])<2 || count($table['columns'])>6 || !is_array($table['rows']??null) || count($table['rows'])>100) throw new InvalidArgumentException('Проверьте таблицу стоимости.');
        foreach ($table['columns'] as $column) if (!is_string($column) || mb_strlen($column)>200) throw new InvalidArgumentException('Проверьте заголовки стоимости.');
        foreach ($table['rows'] as $row) {
            if (!is_array($row) || count($row)!==count($table['columns'])) throw new InvalidArgumentException('Число ячеек не совпадает с заголовками.');
            foreach ($row as $cell) if (!is_string($cell) || mb_strlen($cell)>1000) throw new InvalidArgumentException('Проверьте ячейку стоимости.');
        }
    }
}
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
    foreach (['brand','hero_title','hero_description','hero_image','phone','email','area','maps_url','telegram_url','about_title','about_text','about_subtitle','about_image','services_intro','services_note','operator','inn','operator_address','guarantee_terms'] as $key) {
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
        foreach (['price_unit','price_context'] as $key) if (!is_string($s[$key]??null) || mb_strlen($s[$key])>100) throw new InvalidArgumentException('Проверьте единицу стоимости.');
        if (!is_array($s['sections']??null) || count($s['sections'])>20 || !is_array($s['pricing']??null)) throw new InvalidArgumentException('Проверьте разделы услуги.');
        foreach ($s['sections'] as $section) {
            if (!is_array($section) || !is_string($section['title']??null) || mb_strlen($section['title'])>300 || !is_array($section['items']??null) || count($section['items'])>60) throw new InvalidArgumentException('Проверьте группу работ.');
            foreach ($section['items'] as $item) if (!is_string($item) || mb_strlen($item)>1000) throw new InvalidArgumentException('Проверьте пункт состава работ.');
        }
        validate_tables($s['pricing']);
    }
    if (!is_array($d['additional_services']??null)) throw new InvalidArgumentException('Проверьте дополнительные услуги.');
    validate_tables($d['additional_services']);
    foreach (['promotion'=>['value','title','description','tagline'],'gift_certificate'=>['title','button'],'request_form'=>['contact_heading','contact_offer']] as $section=>$keys) {
        if (!is_array($d[$section]??null)) throw new InvalidArgumentException('Проверьте раздел '.$section);
        foreach ($keys as $key) if (!is_string($d[$section][$key]??null) || mb_strlen($d[$section][$key])>6000) throw new InvalidArgumentException('Проверьте текст '.$section);
    }
    if (!is_array($d['about_points']??null) || count($d['about_points'])>20) throw new InvalidArgumentException('Проверьте описание компании.');
    foreach ($d['about_points'] as $point) {
        if (!is_array($point) || !is_string($point['title']??null) || mb_strlen($point['title'])>300 || !is_array($point['paragraphs']??null) || count($point['paragraphs'])>10) throw new InvalidArgumentException('Проверьте принципы работы.');
        foreach ($point['paragraphs'] as $paragraph) if (!is_string($paragraph) || mb_strlen($paragraph)>3000) throw new InvalidArgumentException('Проверьте описание принципа работы.');
    }
    foreach (['property_types','areas'] as $key) {
        if (!is_array($d['request_form'][$key]??null) || count($d['request_form'][$key])<1 || count($d['request_form'][$key])>20) throw new InvalidArgumentException('Проверьте варианты формы.');
        $optionIds=[];
        foreach ($d['request_form'][$key] as $option) {
            if (!is_array($option) || !is_string($option['id']??null) || !preg_match('/^[a-z0-9-]{1,40}$/D',$option['id']) || isset($optionIds[$option['id']]) || !is_string($option['title']??null) || mb_strlen($option['title'])>150) throw new InvalidArgumentException('Проверьте вариант формы.');
            $optionIds[$option['id']]=true;
        }
    }
    if (!is_array($d['request_choices']??null) || count($d['request_choices'])>20) throw new InvalidArgumentException('Проверьте дополнительные варианты заявки.');
    foreach ($d['request_choices'] as $choice) {
        if (!is_array($choice) || !is_string($choice['id']??null) || !preg_match('/^[a-z0-9-]{1,40}$/D',$choice['id']) || isset($ids[$choice['id']]) || !is_string($choice['title']??null) || mb_strlen($choice['title'])>150) throw new InvalidArgumentException('Проверьте вариант услуги в заявке.');
        $ids[$choice['id']]=true;
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
