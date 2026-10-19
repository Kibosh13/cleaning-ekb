<?php
// Скопируйте в config.local.php. Храните вне public/ и не публикуйте.
return [
    'base_url' => '', // [заполнить] https://ваш-домен.рф без / в конце
    'admin_user' => '',
    'admin_password_hash' => '', // создаётся scripts/setup-admin.php
    'leads_enabled' => false, // true после настройки и проверки доставки
    'mail_enabled' => false, // true: PHP mail(), нужен настроенный MTA хостинга
    'mail_to' => 'chistota.v.dome@mail.ru',
    'mail_from' => '', // [заполнить] отправитель вашего домена
    'telegram_bot_token' => '', // токен бота, а не телефон
    'telegram_chat_id' => '', // числовой chat_id, а не телефон
    'metrika_id' => '', // [заполнить] номер счётчика
    'webmaster_verification' => '', // [заполнить] код Яндекс Вебмастера
    'force_https' => true,
];
