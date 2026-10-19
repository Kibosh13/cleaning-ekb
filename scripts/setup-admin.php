<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
$user=$argv[1]??'admin';
if(!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D',$user))exit("Логин: 3–60 латинских букв, цифр, ._-\n");
fwrite(STDOUT,"Новый пароль (минимум 12 символов): ");
$tty=function_exists('stream_isatty')&&stream_isatty(STDIN);
if($tty)system('stty -echo');
try{$password=rtrim((string)fgets(STDIN),"\r\n");fwrite(STDOUT,"\nПовторите пароль: ");$repeat=rtrim((string)fgets(STDIN),"\r\n");}finally{if($tty)system('stty echo');fwrite(STDOUT,"\n");}
if(strlen($password)<12||!hash_equals($password,$repeat))exit("Пароли не совпадают или короче 12 символов.\n");
$config['admin_user']=$user;$config['admin_password_hash']=password_hash($password,PASSWORD_DEFAULT);
$file=ROOT.'/config.local.php';
$tmp=tempnam(ROOT,'config-');
file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n");chmod($tmp,0600);
if(!rename($tmp,$file))exit("Не удалось сохранить конфигурацию.\n");
fwrite(STDOUT,"Админка настроена. Вход: /admin.php. Пароль не записан в открытом виде.\n");
