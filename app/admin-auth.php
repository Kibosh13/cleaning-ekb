<?php
declare(strict_types=1);

const ADMIN_CREDENTIALS = ROOT . '/data/admin.json';
const ADMIN_SETUP_HASH = ROOT . '/data/admin-setup.sha256';

function admin_credentials(array $config): array
{
    if (is_file(ADMIN_CREDENTIALS)) {
        return read_json_file(ADMIN_CREDENTIALS);
    }
    return ['user' => $config['admin_user'], 'password_hash' => $config['admin_password_hash']];
}

function admin_ready(array $credentials): bool
{
    return $credentials['user'] !== '' && $credentials['password_hash'] !== '';
}

function create_admin(array $config, string $token, string $user, string $password, string $repeat): array
{
    return with_data_lock('admin-setup', function () use ($config, $token, $user, $password, $repeat): array {
        if (admin_ready(admin_credentials($config)) || !is_file(ADMIN_SETUP_HASH)) {
            throw new InvalidArgumentException('Первичная настройка уже закрыта.');
        }
        $expected = trim((string) file_get_contents(ADMIN_SETUP_HASH));
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($expected, hash('sha256', $token))) {
            throw new InvalidArgumentException('Неверный код настройки.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D', $user)) {
            throw new InvalidArgumentException('Логин должен содержать 3–60 латинских букв, цифр или знаков ._-.');
        }
        validate_admin_password($password, $repeat);
        $credentials = ['user' => $user, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)];
        write_json_file(ADMIN_CREDENTIALS, $credentials);
        // The stored credentials close setup even if the now-unused digest cannot be removed.
        @unlink(ADMIN_SETUP_HASH);
        return $credentials;
    });
}

function validate_admin_password(string $password, string $repeat): void
{
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")
        || !hash_equals($password, $repeat)) {
        throw new InvalidArgumentException('Введите совпадающие пароли длиной от 12 до 72 байт.');
    }
}

function change_admin_password(array $config, array $sessionCredentials, string $current, string $password, string $repeat): array
{
    return with_data_lock('admin-setup', function () use ($config, $sessionCredentials, $current, $password, $repeat): array {
        $credentials = admin_credentials($config);
        if (!hash_equals(admin_session_key($credentials), admin_session_key($sessionCredentials))) {
            throw new DomainException('Пароль уже изменён. Войдите снова.');
        }
        if (strlen($current) > 300 || !password_verify($current, $credentials['password_hash'])) {
            throw new InvalidArgumentException('Текущий пароль неверен.');
        }
        validate_admin_password($password, $repeat);
        if (password_verify($password, $credentials['password_hash'])) {
            throw new InvalidArgumentException('Новый пароль должен отличаться от текущего.');
        }
        $updated = ['user' => $credentials['user'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT)];
        write_json_file(ADMIN_CREDENTIALS, $updated);
        return $updated;
    });
}

function admin_session_key(array $credentials): string
{
    return hash('sha256', $credentials['user'] . ':' . $credentials['password_hash']);
}

function establish_admin_session(array $credentials): void
{
    session_regenerate_id(true);
    $_SESSION = ['admin_key' => admin_session_key($credentials), 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
}
