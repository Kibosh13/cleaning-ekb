<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/admin-auth.php';
require ROOT . '/app/admin-schema.php';
require ROOT . '/app/leads.php';

common_headers();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1'], true);
if ($config['force_https'] && !$local && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    exit('Для входа в админку используйте HTTPS.');
}

$tabs = [
    'leads' => 'Заявки', 'general' => 'Общие настройки', 'home' => 'Главная', 'services' => 'Услуги и цены',
    'offers' => 'Акции и доп. услуги', 'about' => 'О компании', 'portfolio' => 'Работы и отзывы',
    'faq' => 'Вопросы', 'request' => 'Форма заявки', 'documents' => 'Документы', 'account' => 'Учётная запись',
];
$tab = $_GET['tab'] ?? 'leads';
if (!is_string($tab) || !isset($tabs[$tab])) {
    $tab = 'leads';
}
function admin_link(string $tab, array $query = []): string
{
    return '/admin.php?' . http_build_query(['tab' => $tab] + $query);
}

function admin_redirect(string $tab, string $message = '', array $query = []): never
{
    if ($message !== '') {
        $_SESSION['notice'] = $message;
    }
    header('Location: ' . admin_link($tab, $query), true, 303);
    exit;
}

function post_text(string $name): string
{
    if (!is_string($_POST[$name] ?? null)) {
        throw new InvalidArgumentException('Проверьте поля формы.');
    }
    return trim($_POST[$name]);
}

function post_password(string $name): string
{
    return is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
}

start_session();
$credentials = admin_credentials($config);
$configured = admin_ready($credentials);
$authenticated = $configured && is_string($_SESSION['admin_key'] ?? null)
    && hash_equals(admin_session_key($credentials), $_SESSION['admin_key'])
    && time() - ($_SESSION['last_activity'] ?? 0) <= 1800;
if ($authenticated) {
    $_SESSION['last_activity'] = time();
} else {
    unset($_SESSION['admin_key'], $_SESSION['last_activity']);
}
$setupAvailable = !$configured && is_file(ADMIN_SETUP_HASH);
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$error = '';
$editorDraft = null;
$editorRevision = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $upload = $action === 'upload_image';
    try {
        if (!check_csrf()) {
            http_response_code(403);
            throw new InvalidArgumentException('Сессия обновилась. Обновите страницу и повторите действие.');
        }
        if ($action === 'logout' && $authenticated) {
            $_SESSION = [];
            session_regenerate_id(true);
            admin_redirect('leads');
        }
        if ($action === 'setup' && $setupAvailable) {
            if (!rate_allowed('admin-setup', 5, 900)) {
                http_response_code(429);
                throw new InvalidArgumentException('Слишком много попыток. Повторите через 15 минут.');
            }
            $credentials = create_admin($config, post_text('setup_token'), post_text('user'), post_password('password'), post_password('repeat'));
            establish_admin_session($credentials);
            admin_redirect('request', 'Вход настроен. Здесь можно включить приём заявок.');
        }
        if ($action === 'login' && $configured) {
            if (!rate_allowed('admin', 5, 900)) {
                http_response_code(429);
                throw new InvalidArgumentException('Слишком много попыток. Повторите через 15 минут.');
            }
            $password = $_POST['password'] ?? null;
            if (!is_string($password) || strlen($password) > 300 || !hash_equals($credentials['user'], post_text('user'))
                || !password_verify($password, $credentials['password_hash'])) {
                http_response_code(401);
                throw new InvalidArgumentException('Неверный логин или пароль.');
            }
            establish_admin_session($credentials);
            admin_redirect('leads');
        }
        if (!$authenticated) {
            http_response_code(403);
            throw new InvalidArgumentException('Войдите в админку.');
        }
        switch ($action) {
            case 'change_password':
                if (!rate_allowed('admin-password', 5, 900)) {
                    http_response_code(429);
                    throw new InvalidArgumentException('Слишком много попыток. Повторите через 15 минут.');
                }
                $updated = change_admin_password($config, $credentials, post_password('current_password'), post_password('password'), post_password('repeat'));
                establish_admin_session($updated);
                admin_redirect('account', 'Пароль изменён. Другие сеансы завершены.');
            case 'save':
                $schema = admin_schema($tab);
                $raw = $_POST['values'] ?? null;
                if (!$schema || !is_string($raw) || strlen($raw) > 800000) {
                    throw new InvalidArgumentException('Не удалось прочитать поля редактора.');
                }
                $values = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($values)) {
                    throw new InvalidArgumentException('Проверьте поля редактора.');
                }
                $updated = merge_editor_content(content(), $schema, $values);
                $editorDraft = editor_content($updated, $schema);
                $editorRevision = post_text('revision');
                save_content($updated, $editorRevision);
                admin_redirect($tab, 'Изменения опубликованы.');
            case 'upload_image':
                $path = upload_admin_image(__DIR__);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['path' => $path], JSON_THROW_ON_ERROR);
                exit;
            case 'update_lead':
                $id = post_text('id');
                manage_lead($id, 'update', post_text('status'), post_text('note'));
                admin_redirect('leads', 'Заявка обновлена.', ['id' => $id]);
            case 'delete_lead':
                manage_lead(post_text('id'), 'delete');
                admin_redirect('leads', 'Заявка удалена.');
            default:
                throw new InvalidArgumentException('Неизвестное действие.');
        }
    } catch (Throwable $exception) {
        $expected = $exception instanceof InvalidArgumentException || $exception instanceof DomainException;
        $error = $expected ? $exception->getMessage() : 'Не удалось выполнить действие. Попробуйте ещё раз.';
        if (!$expected) {
            error_log('Admin operation failed: ' . get_class($exception));
        }
        if ($upload) {
            http_response_code(http_response_code() >= 400 ? http_response_code() : ($expected ? 422 : 500));
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (http_response_code() < 400) {
            http_response_code($exception instanceof DomainException ? 409 : ($expected ? 422 : 500));
        }
    }
}

$c = content($revision);
$editor = null;
$lead = null;
$leadList = null;
$status = '';
$search = '';
if ($authenticated && $tab === 'leads') {
    try {
        $id = $_GET['id'] ?? '';
        if (is_string($id) && $id !== '') {
            $lead = get_lead($id);
        } else {
            $status = is_string($_GET['status'] ?? null) && isset(LEAD_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
            $search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
            $page = is_string($_GET['page'] ?? null) ? (int) $_GET['page'] : 1;
            $leadList = list_leads($status, $search, $page);
        }
    } catch (InvalidArgumentException $exception) {
        http_response_code(404);
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        http_response_code(500);
        $error = 'Не удалось открыть журнал заявок.';
        error_log('Lead journal error: ' . get_class($exception));
    }
} elseif ($authenticated && $tab !== 'account') {
    $schema = admin_schema($tab);
    $editor = [
        'schema' => $schema, 'values' => $editorDraft ?? editor_content($c, $schema),
        'revision' => $editorRevision ?? $revision,
    ];
}

function admin_csrf_field(): void
{
    echo '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}

function lead_date(string $date): string
{
    return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Asia/Yekaterinburg'))->format('d.m.Y H:i');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Управление сайтом · <?= e($c['brand']) ?></title>
    <link rel="icon" href="<?= e(image_path($c['logo_image'])) ?>">
    <link rel="stylesheet" href="/assets/admin.css?v=1">
    <?php if ($authenticated): ?><script src="/assets/admin.js?v=1" defer></script><?php endif; ?>
</head>
<body>
<header class="top">
    <div class="top-inner">
        <a class="wordmark" href="<?= e(admin_link('leads')) ?>"><?= e($c['brand']) ?></a>
        <div class="top-actions">
            <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
            <?php if ($authenticated): ?>
                <form method="post"><?php admin_csrf_field(); ?><button class="secondary" name="action" value="logout">Выйти</button></form>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="<?= $authenticated ? 'layout' : 'login-layout' ?>">
<?php if ($authenticated): ?>
    <nav aria-label="Разделы админки" class="sidebar">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e(admin_link($key)) ?>" <?= $key === $tab ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<main>
    <?php if ($notice): ?><p class="flash success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$authenticated): ?>
        <section class="panel login">
            <?php if ($setupAvailable): ?>
                <h1>Настройка входа</h1>
                <p class="muted">Введите одноразовый код установки и придумайте свой пароль.</p>
                <form method="post">
                    <?php admin_csrf_field(); ?>
                    <label>Одноразовый код<input name="setup_token" required minlength="64" maxlength="64" autocomplete="off" spellcheck="false"></label>
                    <label>Логин<input name="user" required minlength="3" maxlength="60" autocomplete="username"></label>
                    <label>Пароль<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                    <label>Повторите пароль<input name="repeat" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                    <button name="action" value="setup">Создать вход</button>
                </form>
            <?php elseif ($configured): ?>
                <h1>Вход в админку</h1>
                <form method="post">
                    <?php admin_csrf_field(); ?>
                    <label>Логин<input name="user" required autocomplete="username" maxlength="100"></label>
                    <label>Пароль<input name="password" type="password" required autocomplete="current-password" maxlength="300"></label>
                    <button name="action" value="login">Войти</button>
                </form>
            <?php else: ?>
                <h1>Вход ещё не настроен</h1>
                <p>Владелец может настроить вход с помощью одноразового кода установки или команды <code>php scripts/setup-admin.php</code> на сервере.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <h1><?= e($tabs[$tab]) ?></h1>
        <?php if (!empty($credentials['temporary_password']) && $tab !== 'account'): ?>
            <p class="flash">Вход настроен с временным паролем. <a href="<?= e(admin_link('account')) ?>">Задать свой пароль</a></p>
        <?php endif; ?>
        <?php if ($tab === 'leads'): ?>
            <?php require ROOT . '/app/admin-leads-view.php'; ?>
        <?php elseif ($tab === 'account'): ?>
            <section class="panel login">
                <h2>Смена пароля</h2>
                <p>Логин: <strong><?= e($credentials['user']) ?></strong></p>
                <form method="post">
                    <?php admin_csrf_field(); ?>
                    <input type="hidden" name="user" value="<?= e($credentials['user']) ?>" autocomplete="username">
                    <label>Текущий пароль<input name="current_password" type="password" required autocomplete="current-password" maxlength="300"></label>
                    <label>Новый пароль<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                    <label>Повторите новый пароль<input name="repeat" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                    <button name="action" value="change_password">Сменить пароль</button>
                </form>
            </section>
        <?php else: ?>
            <?php if ($tab === 'home'): ?><p class="muted">Первый экран всегда на месте. Остальные разделы можно переставить или скрыть.</p><?php endif; ?>
            <?php if ($tab === 'documents'): ?><p class="muted">Пустой текст оставляет исходный документ. В своём тексте можно разделить абзацы пустой строкой, а заголовки начать с «## ».</p><?php endif; ?>
            <form method="post" id="content-editor" enctype="multipart/form-data">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="revision" value="<?= e($editor['revision']) ?>">
                <input type="hidden" name="values">
                <div id="editor-fields"></div>
                <div class="save-bar"><button type="submit" disabled>Сохранить и опубликовать</button><span class="muted" role="status" id="editor-status"></span></div>
                <noscript><p>Включите JavaScript, чтобы редактировать содержимое.</p></noscript>
            </form>
            <script type="application/json" id="editor-data"><?= json_encode($editor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
        <?php endif; ?>
    <?php endif; ?>
</main>
</div>
</body>
</html>
