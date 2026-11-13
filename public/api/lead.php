<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
require ROOT . '/app/leads.php';
common_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, bool $ok, string $message): never {http_response_code($status);echo json_encode(compact('ok','message'),JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: POST'); respond(405,false,'Используйте форму на сайте.'); }
if((int)($_SERVER['CONTENT_LENGTH']??0)>16384) respond(413,false,'Слишком большой запрос.');
if(!check_csrf()) respond(403,false,'Сессия формы обновилась. Повторите отправку.');
foreach(['name','phone','time','service','property_type','area_size','website','consent','consent_version','request_key'] as $key) if(isset($_POST[$key]) && (!is_string($_POST[$key]) || !mb_check_encoding($_POST[$key], 'UTF-8'))) respond(422,false,'Проверьте данные формы.');
if(!empty($_POST['website'])) respond(422,false,'Не удалось проверить форму. Позвоните нам.');
$name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$time=trim($_POST['time']??'');$service=trim($_POST['service']??'');
if(mb_strlen($name)<1||mb_strlen($name)>80||preg_match('/[\x00-\x1f]/',$name)) respond(422,false,'Укажите имя (до 80 символов).');
if(mb_strlen($phone)>25||!preg_match('/^\+?[\d\s()\-]{10,25}$/D',$phone)||strlen(preg_replace('/\D/','',$phone))<10||strlen(preg_replace('/\D/','',$phone))>15) respond(422,false,'Проверьте номер телефона.');
if(mb_strlen($time)>120||preg_match('/[\x00-\x1f]/',$time)) respond(422,false,'Укажите удобное время (до 120 символов).');
if(($_POST['consent']??'')!=='1') respond(422,false,'Необходимо согласие на обработку персональных данных.');
$c=content();
if (isset($_POST['consent_version']) && $_POST['consent_version'] !== $c['consent_version']) {
    respond(409, false, 'Документы изменились. Обновите страницу и ознакомьтесь с ними перед отправкой.');
}
$found=array_values(array_filter(array_merge($c['services'],$c['request_choices']),fn($s)=>$s['id']===$service));
if($service!==''&&!$found) respond(422,false,'Выберите услугу из списка.');
$property=trim($_POST['property_type']??'');$area=trim($_POST['area_size']??'');
$propertyOptions=array_column($c['request_form']['property_types'],'title','id');
$areaOptions=array_column($c['request_form']['areas'],'title','id');
if($service!=='gift-certificate'&&(!isset($propertyOptions[$property])||!isset($areaOptions[$area]))) respond(422,false,'Выберите тип помещения и его площадь.');
if($property!==''&&!isset($propertyOptions[$property])) respond(422,false,'Проверьте тип помещения.');
if($area!==''&&!isset($areaOptions[$area])) respond(422,false,'Проверьте площадь помещения.');
if (!leads_enabled($c)) {
    respond(503, false, $c['copy']['form_unavailable']);
}
$requestKey = $_POST['request_key'] ?? bin2hex(random_bytes(16));
if (!preg_match('/^[a-f0-9]{32}$/D', $requestKey)) {
    respond(422, false, 'Обновите страницу и повторите отправку.');
}
try {
    [$lead, $created] = receive_lead([
        'name' => $name, 'phone' => $phone, 'service' => $found[0]['title'] ?? $c['copy']['form_choose'],
        'property' => $propertyOptions[$property] ?? 'Не требуется',
        'area' => $areaOptions[$area] ?? 'Не требуется',
        'time' => $time, 'consent_version' => $c['consent_version'],
    ], $requestKey);
} catch (OverflowException $error) {
    respond(429, false, $error->getMessage());
} catch (Throwable $error) {
    error_log('Lead storage error: ' . get_class($error));
    respond(503, false, 'Не удалось принять заявку. Позвоните нам или попробуйте позже.');
}
session_write_close();
if (!$created) {
    respond(200, true, $c['copy']['form_success']);
}
$text = implode("\n", [
    'Новая заявка на уборку',
    'Имя: ' . $lead['name'], 'Телефон: ' . $lead['phone'], 'Услуга: ' . $lead['service'],
    'Помещение: ' . $lead['property'], 'Площадь: ' . $lead['area'],
    'Удобное время для уборки: ' . ($lead['time'] ?: 'Не указано'),
    'Согласие на обработку данных: получено', 'Версия согласия: ' . $lead['consent_version'],
    'Дата (UTC): ' . $lead['created_at'],
]);

try {
    if ($config['mail_enabled'] && filter_var($config['mail_to'], FILTER_VALIDATE_EMAIL) && filter_var($config['mail_from'], FILTER_VALIDATE_EMAIL)) {
        $headers = ['From' => $config['mail_from'], 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8'];
        $subject = '=?UTF-8?B?' . base64_encode('Заявка на уборку — ' . $c['brand']) . '?=';
        if (!@mail($config['mail_to'], $subject, $text, $headers)) {
            error_log('Lead email notification failed.');
        }
    }
    if ($config['telegram_bot_token'] && $config['telegram_chat_id'] && function_exists('curl_init')) {
        $client = curl_init('https://api.telegram.org/bot' . $config['telegram_bot_token'] . '/sendMessage');
        curl_setopt_array($client, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $config['telegram_chat_id'], 'text' => $text]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $result = curl_exec($client);
        $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
        curl_close($client);
        $body = is_string($result) ? json_decode($result, true) : null;
        if ($status !== 200 || ($body['ok'] ?? false) !== true) {
            error_log('Lead Telegram notification failed.');
        }
    }
} catch (Throwable $error) {
    error_log('Lead notification failed: ' . get_class($error));
}
respond(200, true, $c['copy']['form_success']);
