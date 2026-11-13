<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
require ROOT . '/app/admin-auth.php';
$user=$argv[1]??'admin';
if(!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D',$user))exit("Логин: 3–60 латинских букв, цифр, ._-\n");
fwrite(STDOUT,"Новый пароль (минимум 12 символов): ");
$tty=function_exists('stream_isatty')&&stream_isatty(STDIN);
if($tty)system('stty -echo');
try{$password=rtrim((string)fgets(STDIN),"\r\n");fwrite(STDOUT,"\nПовторите пароль: ");$repeat=rtrim((string)fgets(STDIN),"\r\n");}finally{if($tty)system('stty echo');fwrite(STDOUT,"\n");}
if (strlen($password) < 12 || strlen($password) > 72 || !hash_equals($password, $repeat)) {
    fwrite(STDERR, "Пароли должны совпадать и содержать от 12 до 72 байт.\n");
    exit(1);
}
with_data_lock('admin-setup', function () use ($user, $password): void {
    write_json_file(ADMIN_CREDENTIALS, ['user' => $user, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    if (is_file(ADMIN_SETUP_HASH)) {
        @unlink(ADMIN_SETUP_HASH);
    }
});
fwrite(STDOUT, "Вход настроен. Админка открывается по адресу /admin.php.\n");
