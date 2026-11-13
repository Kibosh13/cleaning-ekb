<?php
declare(strict_types=1);

const LEAD_STATUSES = [
    'new' => 'Новая', 'working' => 'В работе', 'scheduled' => 'Запланирована',
    'done' => 'Выполнена', 'closed' => 'Закрыта',
];

function lead_path(string $id): string
{
    if (!preg_match('/^[a-f0-9]{40}$/D', $id)) {
        throw new InvalidArgumentException('Заявка не найдена.');
    }
    return ROOT . '/data/leads/' . $id . '.json';
}

function receive_lead(array $fields, string $requestKey): array
{
    // A retry with the same browser session and key returns the original receipt.
    $id = substr(hash('sha256', session_id() . '|' . $requestKey), 0, 40);
    return with_data_lock('leads', function () use ($id, $fields): array {
        $path = lead_path($id);
        if (is_file($path)) {
            return [read_json_file($path), false];
        }
        if (!rate_allowed('lead', 5, 900)) {
            throw new OverflowException('Слишком много попыток. Попробуйте через 15 минут.');
        }
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700)) {
            throw new RuntimeException('Не удалось создать журнал заявок.');
        }
        $lead = $fields + ['id' => $id, 'created_at' => gmdate('c'), 'status' => 'new', 'note' => ''];
        write_json_file($path, $lead);
        return [$lead, true];
    });
}

function get_lead(string $id): array
{
    $path = lead_path($id);
    if (!is_file($path)) {
        throw new InvalidArgumentException('Заявка не найдена.');
    }
    return read_json_file($path);
}

function manage_lead(string $id, string $action, string $status = '', string $note = ''): void
{
    with_data_lock('leads', function () use ($id, $action, $status, $note): void {
        $lead = get_lead($id);
        $path = lead_path($id);
        if ($action === 'delete') {
            if (!unlink($path)) {
                throw new RuntimeException('Не удалось удалить заявку.');
            }
            return;
        }
        if (!isset(LEAD_STATUSES[$status]) || mb_strlen($note) > 4000) {
            throw new InvalidArgumentException('Проверьте статус и заметку.');
        }
        $lead['status'] = $status;
        $lead['note'] = $note;
        $lead['updated_at'] = gmdate('c');
        write_json_file($path, $lead);
    });
}

function list_leads(string $status, string $search, int $page): array
{
    $files = glob(ROOT . '/data/leads/*.json') ?: [];
    $leads = [];
    $search = mb_strtolower($search);
    $phoneSearch = preg_replace('/\D/', '', $search);
    foreach ($files as $path) {
        if (!is_file($path)) {
            continue;
        }
        try {
            $lead = read_json_file($path);
        } catch (Throwable $error) {
            if (!is_file($path)) {
                continue;
            }
            throw $error;
        }
        if ($status !== '' && $lead['status'] !== $status) {
            continue;
        }
        $matchText = str_contains(mb_strtolower($lead['name'] . ' ' . $lead['service']), $search);
        $matchPhone = $phoneSearch !== '' && preg_match('/^[+\d\s()-]+$/D', $search)
            && str_contains(preg_replace('/\D/', '', $lead['phone']), $phoneSearch);
        if ($search === '' || $matchText || $matchPhone) {
            $leads[] = $lead;
        }
    }
    usort($leads, fn(array $a, array $b): int => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
    $pages = max(1, (int) ceil(count($leads) / 20));
    $page = min(max(1, $page), $pages);
    return ['items' => array_slice($leads, ($page - 1) * 20, 20), 'page' => $page, 'pages' => $pages, 'total' => count($leads)];
}
