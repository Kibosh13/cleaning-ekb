<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
common_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, bool $ok, string $message): never {http_response_code($status);echo json_encode(compact('ok','message'),JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: POST'); respond(405,false,'Используйте форму на сайте.'); }
if((int)($_SERVER['CONTENT_LENGTH']??0)>16384) respond(413,false,'Слишком большой запрос.');
if(!check_csrf()) respond(403,false,'Сессия формы обновилась. Повторите отправку.');
foreach(['name','phone','time','service','website','consent'] as $key) if(isset($_POST[$key]) && !is_string($_POST[$key])) respond(422,false,'Проверьте данные формы.');
if(!empty($_POST['website'])) respond(422,false,'Не удалось проверить форму. Позвоните нам.');
$name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$time=trim($_POST['time']??'');$service=trim($_POST['service']??'');
if(mb_strlen($name)<1||mb_strlen($name)>80||preg_match('/[\x00-\x1f]/',$name)) respond(422,false,'Укажите имя (до 80 символов).');
if(mb_strlen($phone)>25||!preg_match('/^\+?[\d\s()\-]{10,25}$/D',$phone)||strlen(preg_replace('/\D/','',$phone))<10||strlen(preg_replace('/\D/','',$phone))>15) respond(422,false,'Проверьте номер телефона.');
if(mb_strlen($time)>120||preg_match('/[\x00-\x1f]/',$time)) respond(422,false,'Укажите удобное время (до 120 символов).');
if(($_POST['consent']??'')!=='1') respond(422,false,'Необходимо согласие на обработку персональных данных.');
$c=content();$found=array_values(array_filter($c['services'],fn($s)=>$s['id']===$service));
if($service!==''&&!$found) respond(422,false,'Выберите услугу из списка.');
if(!$config['leads_enabled']) respond(503,false,'Приём заявок через форму ещё не подключён.');
if(!rate_allowed('lead',5,900)) respond(429,false,'Слишком много попыток. Попробуйте через 15 минут.');
$text="Новая заявка на уборку\nИмя: $name\nТелефон: $phone\nУслуга: ".($found[0]['title']??'Помогите выбрать')."\nУдобное время: ".($time?:'Не указано')."\nСогласие на обработку данных: получено\nВерсия согласия: 2026-10-19\nДата (UTC): ".gmdate('c');
$sent=false;
if($config['mail_enabled'] && filter_var($config['mail_to'],FILTER_VALIDATE_EMAIL) && filter_var($config['mail_from'],FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/',$config['mail_from'])) {
    $headers=['From'=>$config['mail_from'],'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8'];
    $sent=@mail($config['mail_to'],'=?UTF-8?B?'.base64_encode('Заявка на уборку — '.$c['brand']).'?=',$text,$headers);
}
if($config['telegram_bot_token'] && $config['telegram_chat_id'] && function_exists('curl_init')) {
    $ch=curl_init('https://api.telegram.org/bot'.$config['telegram_bot_token'].'/sendMessage');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>$config['telegram_chat_id'],'text'=>$text]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    $result=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $body=is_string($result)?json_decode($result,true):null;
    $sent=$sent||($code===200 && ($body['ok']??false)===true);
}
if(!$sent) respond(502,false,'Сервис доставки временно недоступен.');
unset($_SESSION['csrf']);
respond(200,true,'Спасибо! Заявка передана. Мы свяжемся с вами, чтобы обсудить уборку.');
