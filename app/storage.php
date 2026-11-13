<?php
declare(strict_types=1);

function with_data_lock(string $name, callable $operation): mixed
{
    $lock = @fopen(ROOT . '/data/' . $name . '.lock', 'c');
    if (!$lock) {
        throw new RuntimeException('Каталог данных недоступен для записи.');
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Не удалось получить доступ к данным.');
        }
        return $operation();
    } finally {
        fclose($lock);
    }
}

function read_json_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Не удалось прочитать данные.');
    }
    return json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
}

function write_json_file(string $path, array $value): void
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = tempnam(dirname($path), 'write-');
    if ($temporary === false) {
        throw new RuntimeException('Не удалось подготовить файл данных.');
    }
    try {
        if (file_put_contents($temporary, $json) !== strlen($json) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            throw new RuntimeException('Не удалось сохранить данные.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}
