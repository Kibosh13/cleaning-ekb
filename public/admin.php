<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
common_headers();header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
start_session();
$error='';$notice='';
$local=in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true);
if($config['force_https'] && !$local && (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')) { http_response_code(403);exit('Для входа в админку используйте HTTPS.'); }
$configured=$config['admin_user']!=='' && $config['admin_password_hash']!=='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!check_csrf()){http_response_code(403);$error='Сессия обновилась. Повторите действие.';}
 elseif(isset($_POST['logout'])){$_SESSION=[];session_regenerate_id(true);header('Location: /admin.php',true,303);exit;}
 elseif(isset($_POST['login']) && $configured){
   if(!rate_allowed('admin',5,900)){$error='Слишком много попыток. Повторите через 15 минут.';http_response_code(429);}
   elseif(is_string($_POST['user']??null) && is_string($_POST['password']??null) && hash_equals($config['admin_user'],$_POST['user']) && password_verify($_POST['password'],$config['admin_password_hash'])){session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_activity']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /admin.php',true,303);exit;}
   else{$error='Неверный логин или пароль.';http_response_code(401);}
 }
}
if(!empty($_SESSION['admin']) && time()-($_SESSION['last_activity']??0)>1800){unset($_SESSION['admin']);$error='Сессия завершена. Войдите снова.';}
$auth=!empty($_SESSION['admin']) && $configured;
if($auth)$_SESSION['last_activity']=time();
$c=content();
if($auth && $_SERVER['REQUEST_METHOD']==='POST' && check_csrf()) {
 try {
   if(isset($_POST['save_json'])){
     $raw=$_POST['content_json']??'';
     if(!is_string($raw)||strlen($raw)>180000)throw new InvalidArgumentException('Файл слишком большой.');
     $new=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
     if(!is_array($new))throw new InvalidArgumentException('Ожидается объект JSON.');
     save_content($new);$c=content();$notice='Все разделы сохранены.';
   } elseif(isset($_POST['save'])) {
     $new=$c;
     foreach(['brand','hero_title','hero_description','phone','email','area','maps_url','telegram_url','about_title','about_text','about_subtitle','services_intro','services_note','operator_address','guarantee_terms'] as $key){
       if(!is_string($_POST[$key]??null))throw new InvalidArgumentException('Проверьте поле '.$key);
       $new[$key]=trim($_POST[$key]);
     }
     foreach(['promotion'=>['value','title','description','tagline'],'gift_certificate'=>['title','button'],'request_form'=>['contact_heading','contact_offer']] as $section=>$keys) foreach($keys as $key){
       if(!is_string($_POST[$section][$key]??null))throw new InvalidArgumentException('Проверьте текст '.$section);
       $new[$section][$key]=trim($_POST[$section][$key]);
     }
     if(!is_array($_POST['about_points']??null)||!is_array($_POST['additional_services']??null))throw new InvalidArgumentException('Проверьте описание компании и дополнительные услуги.');
     $new['about_points']=[];
     foreach($_POST['about_points'] as $point){
       if(!is_string($point['title']??null)||!is_string($point['paragraphs']??null))throw new InvalidArgumentException('Проверьте принцип работы.');
       $new['about_points'][]=['title'=>trim($point['title']),'paragraphs'=>array_values(array_filter(array_map('trim',explode("\n",$point['paragraphs']))))];
     }
     $new['additional_services']=$_POST['additional_services'];
     foreach($new['services'] as $i=>&$s){
       foreach(['title','description','price','price_unit','price_context','note'] as $key){if(!is_string($_POST['services'][$i][$key]??null))throw new InvalidArgumentException('Проверьте услугу.');$s[$key]=trim($_POST['services'][$i][$key]);}
       if(!is_string($_POST['services'][$i]['includes']??null))throw new InvalidArgumentException('Проверьте состав работ.');
       $s['includes']=array_values(array_filter(array_map('trim',explode("\n",$_POST['services'][$i]['includes']))));
       if(!is_array($_POST['services'][$i]['sections']??null)||!is_array($_POST['services'][$i]['pricing']??null))throw new InvalidArgumentException('Проверьте состав и цены услуги.');
       $s['sections']=[];
       foreach($_POST['services'][$i]['sections'] as $section){
         if(!is_string($section['title']??null)||!is_string($section['items']??null))throw new InvalidArgumentException('Проверьте группу работ.');
         $s['sections'][]=['title'=>trim($section['title']),'items'=>array_values(array_filter(array_map('trim',explode("\n",$section['items']))))];
       }
       $s['pricing']=$_POST['services'][$i]['pricing'];
     }unset($s);
     foreach($new['faq'] as $i=>&$f)foreach(['question','answer'] as $key){if(!is_string($_POST['faq'][$i][$key]??null))throw new InvalidArgumentException('Проверьте FAQ.');$f[$key]=trim($_POST['faq'][$i][$key]);}unset($f);
     save_content($new);$c=content();$notice='Тексты, цены и FAQ сохранены.';
   } elseif(isset($_POST['upload'])) {
     $f=$_FILES['image']??null;
     if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024)throw new InvalidArgumentException('Загрузите JPEG, PNG или WebP размером до 5 МБ.');
     $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
     $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
     $size=@getimagesize($f['tmp_name']);
     if(!isset($types[$mime])||!$size||$size[0]*$size[1]>20000000||$size['mime']!==$mime)throw new InvalidArgumentException('Нужен корректный JPEG, PNG или WebP до 20 мегапикселей.');
     $target=$_POST['target']??'';$new=$c;
     if(!is_string($target))throw new InvalidArgumentException('Выберите изображение.');
     if(!in_array($target,['hero_image','about_image'],true) && !preg_match('/^service-([0-9]+)$/D',$target))throw new InvalidArgumentException('Неизвестное место изображения.');
     if(str_starts_with($target,'service-')&&!isset($new['services'][(int)substr($target,8)]))throw new InvalidArgumentException('Услуга не найдена.');
     $path='/uploads/'.bin2hex(random_bytes(16)).'.'.$types[$mime];
     if(!move_uploaded_file($f['tmp_name'],__DIR__.$path))throw new RuntimeException('Нет прав записи в public/uploads.');
     chmod(__DIR__.$path,0644);
     if(str_starts_with($target,'service-'))$new['services'][(int)substr($target,8)]['image']=$path;else $new[$target]=$path;
     save_content($new);$c=content();$notice='Фото загружено и установлено.';
   }
 }catch(Throwable $ex){$error=$ex instanceof JsonException?'Ошибка синтаксиса JSON.':$ex->getMessage();}
}
$labels=['brand'=>'Название компании','hero_title'=>'Заголовок первого экрана','hero_description'=>'Описание первого экрана','phone'=>'Телефон','email'=>'Почта для посетителей','area'=>'География выезда','maps_url'=>'Ссылка на Яндекс Карты','telegram_url'=>'Telegram: подтверждённая HTTPS-ссылка или пусто','about_title'=>'Заголовок «О компании»','about_text'=>'Описание компании','about_subtitle'=>'Подзаголовок принципов работы','services_intro'=>'Вступление к услугам','services_note'=>'Примечание к услугам','operator_address'=>'Адрес оператора для документов','guarantee_terms'=>'Условия гарантии'];
function field(string $name,string $label,string $value,bool $large=false):void{?><label><?=e($label)?><?php if($large): ?><textarea name="<?=e($name)?>" rows="3"><?=e($value)?></textarea><?php else: ?><input name="<?=e($name)?>" value="<?=e($value)?>"><?php endif; ?></label><?php }

function edit_tables(string $prefix,array $tables):void{
 foreach($tables as $t=>$table){
   field($prefix."[$t][title]",'Название таблицы',$table['title']);
   ?><div class="edit-table-wrap"><table class="edit-table"><thead><tr><?php foreach($table['columns'] as $j=>$column): ?><th><?=e($column)?><input type="hidden" name="<?=e($prefix."[$t][columns][$j]")?>" value="<?=e($column)?>"></th><?php endforeach; ?></tr></thead><tbody><?php foreach($table['rows'] as $r=>$row): ?><tr><?php foreach($row as $j=>$value): ?><td><input aria-label="<?=e($table['title'].' · '.$row[0].' · '.$table['columns'][$j])?>" name="<?=e($prefix."[$t][rows][$r][$j]")?>" value="<?=e($value)?>"></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php
 }
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Управление сайтом — Чистота в доме</title><link rel="icon" href="/assets/favicon.svg"><style>*{box-sizing:border-box}body{font:14px/1.6 Arial,sans-serif;margin:0;background:#f4f6ee;color:#294c3e}main{max-width:1000px;margin:auto;padding:32px 20px}h1,h2{font-family:Georgia,serif;font-weight:400}h1{font-size:32px}h2{font-size:25px}header{display:flex;justify-content:space-between;align-items:center;gap:20px}section{padding:25px;background:white;margin:20px 0;border:1px solid #dfe4d5}label{display:block;font-size:12px;margin:12px 0}input,textarea,select{display:block;width:100%;font:14px/1.5 Arial,sans-serif;border:1px solid #c5ceba;padding:10px;margin-top:5px;color:#294c3e}textarea{resize:vertical}button,.button{display:inline-block;background:#294c3e;color:#fff;border:0;padding:12px 20px;font:13px Arial,sans-serif;cursor:pointer;text-decoration:none}input:focus,textarea:focus,select:focus,button:focus-visible{outline:2px solid #b49659;outline-offset:2px}.error,.notice{padding:15px 20px;background:#e4ecd9}.error{color:#8b3326;background:#fae9e4}.row{display:grid;grid-template-columns:1fr 1fr;gap:20px}.service{padding:15px 0;border-top:1px solid #e0e5d8}.hint{color:#71806c;font-size:12px}.sticky-save{position:sticky;bottom:10px;background:#eef2e6;padding:12px;border:1px solid #cbd5be;display:flex;gap:20px;align-items:center}.json{font-family:monospace;font-size:12px;min-height:400px}.photo-preview{width:150px;height:100px;object-fit:cover}code{font-size:12px;overflow-wrap:anywhere}.login{max-width:460px;margin:70px auto}.inline-form{margin:0}@media(max-width:600px){.row{grid-template-columns:1fr}header{display:block}section{padding:18px}.sticky-save{display:block}.sticky-save span{display:block;margin-top:8px}}.edit-table-wrap{overflow-x:auto;margin-bottom:25px}.edit-table{border-collapse:collapse;width:100%;min-width:450px}.edit-table td,.edit-table th{padding:7px;border:1px solid #dce4d4;font-size:12px;text-align:left}.edit-table input{min-width:100px;margin:0;font-size:13px}details{margin:18px 0}summary{cursor:pointer}</style></head><body><main><header><div><a href="/">← На сайт</a><h1>Управление сайтом</h1></div><?php if($auth): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><button name="logout" value="1">Выйти</button></form><?php endif; ?></header><?php if($error): ?><p class="error" role="alert"><?=e($error)?></p><?php endif; ?><?php if($notice): ?><p class="notice" role="status"><?=e($notice)?></p><?php endif; ?>
<?php if(!$configured): ?><section><h2>Первичная настройка</h2><p>Админка защищена и пока не активирована. Выполните в каталоге проекта на сервере:</p><code>php scripts/setup-admin.php admin</code><p>Задайте свой пароль длиной от 12 символов. Пароль вводится скрыто, в конфигурации сохраняется только хеш. Не размещайте каталог проекта целиком в публичной папке.</p></section>
<?php elseif(!$auth): ?><section class="login"><h2>Вход для владельца</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Логин<input name="user" required autocomplete="username" maxlength="100"></label><label>Пароль<input name="password" type="password" required autocomplete="current-password" maxlength="300"></label><button name="login" value="1">Войти</button></form></section>
<?php else: ?><p class="hint">Изменения появятся на сайте сразу после сохранения. Перед изменением длинных текстов сохраните копию content.json. Не публикуйте вымышленные отзывы и неподтверждённые цены.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><section><h2>Тексты и контакты</h2><div class="row"><?php foreach($labels as $key=>$label)field($key,$label,$c[$key],in_array($key,['hero_title','hero_description','about_text','services_intro','services_note','guarantee_terms'],true)); ?></div><p class="hint">Почта доставки заявок меняется отдельно в config.local.php. Условия гарантии сохраняются для согласования; публикация через дополнительный текст FAQ.</p></section><section><h2>Наш подход к работе</h2><?php foreach($c['about_points'] as $i=>$point): ?><?php field("about_points[$i][title]",'Заголовок принципа',$point['title']);field("about_points[$i][paragraphs]",'Описание: каждый абзац с новой строки',implode("\n",$point['paragraphs']),true); ?><?php endforeach; ?></section>
<section><h2>Акция</h2><?php foreach(['value'=>'Размер бонуса','title'=>'Заголовок акции','description'=>'Условия акции','tagline'=>'Завершающая фраза'] as $key=>$label)field("promotion[$key]",$label,$c['promotion'][$key],in_array($key,['description','tagline'])); ?></section>
<section><h2>Услуги и цены</h2><?php foreach($c['services'] as $i=>$service): ?><div class="service"><h3><?=e($service['title'])?></h3><div class="row"><?php field("services[$i][title]",'Название',$service['title']);field("services[$i][price]",'Минимальная цена без единицы',$service['price']);field("services[$i][price_unit]",'Единица: ₽, ₽/м², ₽/створка',$service['price_unit']);field("services[$i][price_context]",'Уточнение минимальной цены (например, шторы)',$service['price_context']); ?></div><?php field("services[$i][description]",'Краткое описание',$service['description'],true);field("services[$i][note]",'Примечание',$service['note']);field("services[$i][includes]",'Общие пункты вне групп: каждый с новой строки',implode("\n",$service['includes']),true); ?><details><summary>Полный состав работ</summary><?php foreach($service['sections'] as $j=>$group){field("services[$i][sections][$j][title]",'Название группы работ',$group['title']);field("services[$i][sections][$j][items]",'Работы: каждый пункт с новой строки',implode("\n",$group['items']),true);} ?></details><details><summary>Подробные таблицы стоимости</summary><?php edit_tables("services[$i][pricing]",$service['pricing']); ?></details></div><?php endforeach; ?></section>
<section><h2>Дополнительные услуги</h2><?php edit_tables('additional_services',$c['additional_services']);field('gift_certificate[title]','Название сертификата',$c['gift_certificate']['title']);field('gift_certificate[button]','Кнопка сертификата',$c['gift_certificate']['button']); ?></section>
<section><h2>Персональное предложение в форме</h2><?php field('request_form[contact_heading]','Короткий заголовок',$c['request_form']['contact_heading']);field('request_form[contact_offer]','Текст предложения',$c['request_form']['contact_offer'],true); ?></section>
<section><h2>Вопросы и ответы</h2><?php foreach($c['faq'] as $i=>$f): ?><div class="service"><?php field("faq[$i][question]",'Вопрос',$f['question']);field("faq[$i][answer]",'Ответ',$f['answer'],true); ?></div><?php endforeach; ?></section><div class="sticky-save"><button name="save" value="1">Сохранить изменения</button><span class="hint">Тексты выводятся как текст. HTML не выполняется.</span></div></form>
<section><h2>Фотографии</h2><p class="hint">JPEG, PNG, WebP до 5 МБ. Загружайте изображения, на использование которых у вас есть право. После загрузки выбранное фото заменится сразу.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Куда поставить фото<select name="target"><option value="hero_image">Первый экран</option><option value="about_image">О компании</option><?php foreach($c['services'] as $i=>$s): ?><option value="service-<?=$i?>"><?=e($s['title'])?></option><?php endforeach; ?></select></label><label>Изображение<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><button name="upload" value="1">Загрузить и установить</button></form></section>
<section><details><summary>Все данные: отзывы, кейсы, новые услуги (JSON)</summary><p class="hint">Редактор для расширенной настройки. Отзывы: name, quote, source. Кейсы: title, description, image. Для фото кейса сначала загрузите файл через блок выше и скопируйте его путь /uploads/… из JSON. Ошибочный формат не сохранится.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><textarea class="json" name="content_json" spellcheck="false"><?=e(json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></textarea><button name="save_json" value="1">Сохранить все данные</button></form></details></section>
<?php endif; ?></main></body></html>
