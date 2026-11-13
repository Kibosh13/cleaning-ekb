<?php
declare(strict_types=1);

function admin_schema(string $tab): array
{
    $text = fn(string $label): array => ['type' => 'text', 'label' => $label];
    $long = fn(string $label): array => ['type' => 'textarea', 'label' => $label];
    $image = fn(string $label): array => ['type' => 'image', 'label' => $label];
    $list = fn(string $label, array $item, int $max = 30, string $itemName = 'Элемент'): array =>
        ['type' => 'list', 'label' => $label, 'item' => $item, 'max' => $max, 'itemName' => $itemName];
    $object = fn(array $fields): array => ['type' => 'object', 'fields' => $fields];
    $titleText = $object(['title' => $text('Заголовок'), 'text' => $long('Текст')]);
    $options = $object(['title' => $text('Название'), 'id' => $text('Код латиницей')]);
    $table = [
        'type' => 'table', 'label' => 'Таблица цен',
        'fields' => ['title' => $text('Название таблицы')],
    ];
    $tables = fn(string $label): array => $list($label, $table, 15, 'Таблица');
    $copy = function (array $prefixes, array $names = []) use ($object, $long): array {
        $fields = [];
        foreach (SITE_COPY as $key => [$label]) {
            if (in_array($key, $names, true) || array_filter($prefixes, fn(string $prefix): bool => str_starts_with($key, $prefix))) {
                $fields[$key] = $long($label);
            }
        }
        return $object($fields) + ['label' => 'Подписи и кнопки'];
    };

    return match ($tab) {
        'general' => [
            'brand' => $text('Название компании'), 'logo_image' => $image('Логотип и иконка вкладки'),
            'seo_title' => $text('Заголовок в поиске'), 'seo_description' => $long('Описание для поиска'),
            'phone' => $text('Телефон'), 'email' => $text('Почта для посетителей'), 'area' => $text('Район выезда'),
            'maps_url' => $text('Ссылка на Яндекс Карты'), 'telegram_url' => $text('Ссылка на Telegram'),
            'operator' => $text('Имя оператора'), 'inn' => $text('ИНН'), 'operator_address' => $text('Адрес оператора'),
            'copy' => $copy(['contacts_', 'footer_', 'cookie_', 'error_'], ['book', 'callback', 'choose']),
        ],
        'home' => [
            'hero_title' => $long('Заголовок первого экрана'), 'hero_description' => $long('Описание первого экрана'),
            'hero_image' => $image('Фотография первого экрана'),
            'sections' => $list('Порядок и видимость разделов', $object([
                'id' => ['type' => 'fixed', 'label' => 'Раздел', 'values' => SECTION_NAMES],
                'visible' => ['type' => 'checkbox', 'label' => 'Показывать раздел'],
            ]), count(SECTION_NAMES), 'Раздел') + ['fixed' => true, 'titles' => SECTION_NAMES],
            'benefits' => $list('Преимущества', $titleText, 20, 'Преимущество'),
            'custom_blocks' => $list('Дополнительные блоки', $object([
                'title' => $text('Заголовок'), 'text' => $long('Текст'),
                'image' => $image('Фото блока') + ['optional' => true],
                'visible' => ['type' => 'checkbox', 'label' => 'Показывать блок'],
            ]), 20, 'Блок'),
            'copy' => $copy(['hero_']),
        ],
        'services' => [
            'services_intro' => $long('Вступление к услугам'), 'services_note' => $long('Примечание под услугами'),
            'services' => $list('Список услуг', $object([
                'title' => $text('Название услуги'),
                'id' => $text('Код услуги латиницей'),
                'group' => ['type' => 'select', 'label' => 'Категории', 'values' => [
                    'home' => 'Для дома', 'business' => 'Для бизнеса', 'special' => 'После ремонта',
                    'home business' => 'Для дома и бизнеса', 'home special' => 'Для дома и после ремонта',
                    'business special' => 'Для бизнеса и после ремонта', 'home business special' => 'Все категории',
                ]],
                'description' => $long('Краткое описание'), 'image' => $image('Фотография услуги'),
                'price' => $text('Цена от'), 'price_unit' => $text('Единица цены'),
                'price_context' => $text('Уточнение цены'), 'note' => $long('Примечание в карточке'),
                'includes' => $list('Работы без группы', $text('Пункт работ'), 20, 'Пункт'),
                'sections' => $list('Группы работ', $object([
                    'title' => $text('Название группы'),
                    'items' => $list('Работы', $text('Пункт работ'), 60, 'Пункт'),
                ]), 20, 'Группа работ'),
                'pricing' => $tables('Таблицы цен'),
            ]), 30, 'Услуга'),
            'copy' => $copy(['services_', 'filter_', 'dialog_']),
        ],
        'offers' => [
            'additional_services' => $tables('Дополнительные услуги'),
            'gift_certificate' => $object(['title' => $text('Название сертификата'), 'button' => $text('Кнопка сертификата')]) + ['label' => 'Подарочный сертификат'],
            'promotion' => $object([
                'value' => $text('Размер бонуса'), 'title' => $text('Заголовок'), 'description' => $long('Условия'),
                'tagline' => $long('Завершающая фраза'),
            ]) + ['label' => 'Акция'],
            'copy' => $copy(['additional_']),
        ],
        'about' => [
            'about_title' => $text('Заголовок компании'), 'about_text' => $long('Описание компании'),
            'about_subtitle' => $text('Заголовок принципов'), 'about_image' => $image('Фотография компании'),
            'about_points' => $list('Принципы работы', $object([
                'title' => $text('Заголовок'), 'paragraphs' => $list('Абзацы', $long('Абзац'), 10, 'Абзац'),
            ]), 20, 'Принцип'),
            'steps' => $list('Этапы работы', $titleText, 20, 'Этап'),
            'copy' => $copy(['about_', 'process_']),
        ],
        'portfolio' => [
            'works_image' => $image('Основная фотография работ'),
            'cases' => $list('Примеры работ', $object([
                'title' => $text('Название работы'), 'description' => $long('Описание'), 'image' => $image('Фото работы'),
            ]), 30, 'Работа'),
            'reviews' => $list('Отзывы', $object([
                'name' => $text('Автор'), 'quote' => $long('Цитата'), 'source' => $text('HTTPS-ссылка на источник, если есть'),
            ]), 30, 'Отзыв'),
            'copy' => $copy(['works_', 'reviews_', 'review_']),
        ],
        'faq' => [
            'faq' => $list('Вопросы и ответы', $object([
                'question' => $text('Вопрос'), 'answer' => $long('Ответ'),
            ]), 30, 'Вопрос'),
            'copy' => $copy(['faq_']),
        ],
        'request' => [
            'accept_leads' => ['type' => 'checkbox', 'label' => 'Принимать заявки через форму'],
            'request_form' => $object([
                'contact_heading' => $text('Заголовок предложения'), 'contact_offer' => $long('Текст предложения'),
                'property_types' => $list('Типы помещений', $options, 20, 'Тип помещения'),
                'areas' => $list('Диапазоны площади', $options, 20, 'Площадь'),
            ]) + ['label' => 'Поля и варианты выбора'],
            'request_choices' => $list('Другие пожелания', $options, 20, 'Вариант'),
            'copy' => $copy(['request_', 'form_']),
        ],
        'documents' => [
            'consent_version' => $text('Версия согласия в формате ГГГГ-ММ-ДД'),
            'documents' => $object(array_map(fn(string $label): array => $object([
                'title' => $text('Заголовок страницы'), 'description' => $long('Описание для поиска'),
                'body' => $long('Текст документа'),
            ]) + ['label' => $label], [
                'privacy' => 'Политика обработки данных', 'consent' => 'Согласие на обработку данных', 'cookies' => 'Использование cookie',
            ])) + ['label' => 'Документы'],
        ],
        default => [],
    };
}

function read_editor_value(array $schema, mixed $value): mixed
{
    switch ($schema['type']) {
        case 'checkbox':
            if (is_bool($value)) return $value;
            break;
        case 'object':
            if (!is_array($value)) break;
            $fields = [];
            foreach ($schema['fields'] as $key => $child) {
                $fields[$key] = read_editor_value($child, $value[$key] ?? null);
            }
            return $fields;
        case 'list':
            if (!is_array($value) || !array_is_list($value) || count($value) > $schema['max']) break;
            return array_map(fn(mixed $item): mixed => read_editor_value($schema['item'], $item), $value);
        case 'table':
            if (!is_array($value) || !is_string($value['title'] ?? null) || !is_array($value['columns'] ?? null) || !is_array($value['rows'] ?? null)
                || !array_is_list($value['columns']) || !array_is_list($value['rows'])) break;
            foreach ($value['rows'] as $row) {
                if (!is_array($row) || !array_is_list($row)) throw new InvalidArgumentException('Проверьте строки таблицы.');
            }
            $table = array_intersect_key($value, array_flip(['title', 'columns', 'rows']));
            validate_tables([$table]);
            return $table;
        case 'fixed':
        case 'select':
            if (is_string($value) && isset($schema['values'][$value])) return $value;
            break;
        default:
            $limit = $schema['label'] === 'Текст документа' ? 48000 : 6000;
            if (is_string($value) && mb_strlen($value) <= $limit) return $value;
    }
    throw new InvalidArgumentException('Проверьте поле «' . ($schema['label'] ?? 'Элемент списка') . '».');
}

function editor_content(array $content, array $schema): array
{
    $values = [];
    foreach ($schema as $key => $field) {
        $values[$key] = match ($key) {
            'copy' => array_intersect_key($content['copy'], $field['fields']),
            'accept_leads' => lead_switch($content),
            default => $content[$key],
        };
    }
    return $values;
}

function merge_editor_content(array $content, array $schema, array $values): array
{
    foreach ($schema as $key => $field) {
        $value = read_editor_value($field, $values[$key] ?? null);
        if ($key === 'copy') {
            $content['copy'] = array_replace($content['copy'], $value);
            continue;
        }
        $content[$key] = $value;
    }
    $sectionIds = array_column($content['sections'], 'id');
    sort($sectionIds);
    $allowed = array_keys(SECTION_NAMES);
    sort($allowed);
    if ($sectionIds !== $allowed) {
        throw new InvalidArgumentException('В списке разделов каждый раздел должен быть один раз.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $content['consent_version'])
        || !($date = DateTimeImmutable::createFromFormat('!Y-m-d', $content['consent_version']))
        || $date->format('Y-m-d') !== $content['consent_version']) {
        throw new InvalidArgumentException('Проверьте дату версии согласия.');
    }
    return $content;
}

function upload_admin_image(string $publicDirectory): string
{
    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Загрузите JPEG, PNG или WebP размером до 5 МБ.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $size = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || !$size || $size[0] * $size[1] > 20000000 || $size['mime'] !== $mime) {
        throw new InvalidArgumentException('Нужен корректный JPEG, PNG или WebP до 20 мегапикселей.');
    }
    $directory = $publicDirectory . '/uploads';
    if (!is_dir($directory) || !is_file($directory . '/.htaccess')) {
        throw new RuntimeException('Не удалось открыть каталог загрузок.');
    }
    $path = '/uploads/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
    $destination = $publicDirectory . $path;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Каталог изображений недоступен для записи.');
    }
    chmod($destination, 0644);
    return $path;
}
