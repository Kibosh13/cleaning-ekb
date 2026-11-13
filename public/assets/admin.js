'use strict';

const form = document.querySelector('#content-editor');

if (form) {
    const editor = JSON.parse(document.querySelector('#editor-data').textContent);
    const fields = document.querySelector('#editor-fields');
    const status = document.querySelector('#editor-status');
    const submit = form.querySelector('button[type="submit"]');
    let uploads = 0;
    let dirty = false;

    function element(tag, text = '', className = '') {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    function button(text, action, className = 'secondary') {
        const node = element('button', text, className);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    }

    function changed() {
        dirty = true;
        status.textContent = 'Есть несохранённые изменения';
    }

    function initial(schema) {
        switch (schema.type) {
            case 'object':
                return Object.fromEntries(Object.entries(schema.fields).map(([key, child]) => [key, initial(child)]));
            case 'list': return [];
            case 'checkbox': return true;
            case 'table': return {title: '', columns: ['Работа', 'Стоимость'], rows: [['', '']]};
            case 'image': return schema.optional ? '' : '/assets/living.webp';
            case 'select': return Object.keys(schema.values)[0];
            default: return '';
        }
    }

    function labeled(text, input, className = '') {
        const label = element('label', text, className);
        label.append(input);
        return label;
    }

    function inputFor(value, multiline = false) {
        const input = element(multiline ? 'textarea' : 'input');
        input.value = value ?? '';
        if (multiline) input.rows = 3;
        return input;
    }

    function field(schema, value, onChange) {
        const update = next => {
            onChange(next);
            changed();
        };

        if (schema.type === 'object') {
            const block = element('fieldset', '', schema.label ? 'panel object' : 'object');
            if (schema.label) block.append(element('legend', schema.label));
            for (const [key, child] of Object.entries(schema.fields)) {
                block.append(field(child, value[key], next => { value[key] = next; }));
            }
            return block;
        }
        if (schema.type === 'list') return listField(schema, value);
        if (schema.type === 'table') return tableField(value);
        if (schema.type === 'image') return imageField(schema, value, update);
        if (schema.type === 'fixed') return element('p', schema.values[value], 'fixed-label');

        if (schema.type === 'checkbox') {
            const input = element('input');
            input.type = 'checkbox';
            input.checked = value;
            input.addEventListener('change', () => update(input.checked));
            const label = element('label', '', 'check');
            label.append(input, document.createTextNode(schema.label));
            return label;
        }

        const input = schema.type === 'select' ? element('select') : inputFor(value, schema.type === 'textarea');
        if (schema.type === 'select') {
            for (const [key, label] of Object.entries(schema.values)) {
                const option = element('option', label);
                option.value = key;
                input.append(option);
            }
            input.value = value;
        }
        input.addEventListener('input', () => update(input.value));
        return labeled(schema.label, input);
    }

    function listField(schema, items) {
        const block = element('fieldset', '', 'panel list');
        block.append(element('legend', schema.label));
        const body = element('div', '', 'list-items');
        block.append(body);
        const add = button('Добавить ' + schema.itemName.toLocaleLowerCase('ru'), () => {
            items.push(initial(schema.item));
            draw(items.length - 1);
            changed();
            body.lastElementChild?.querySelector('input, textarea')?.focus();
        });
        if (!schema.fixed) block.append(add);

        function draw(openIndex = -1) {
            body.replaceChildren();
            if (!items.length) body.append(element('p', 'Список пуст.', 'muted'));
            items.forEach((item, index) => {
                const title = schema.titles?.[item?.id] || item?.title || item?.question || item?.name
                    || (typeof item === 'string' ? item : '') || schema.itemName + ' ' + (index + 1);
                const row = element('details', '', 'list-item');
                row.open = index === openIndex || Boolean(schema.fixed);
                const summary = element('summary', title);
                const actions = element('div', '', 'row-actions');
                const move = direction => {
                    const next = index + direction;
                    [items[index], items[next]] = [items[next], items[index]];
                    draw(next);
                    body.children[next]?.querySelector('summary')?.focus();
                    changed();
                };
                const up = button('↑', () => move(-1));
                up.setAttribute('aria-label', 'Переместить выше: ' + title);
                up.disabled = index === 0;
                const down = button('↓', () => move(1));
                down.setAttribute('aria-label', 'Переместить ниже: ' + title);
                down.disabled = index === items.length - 1;
                actions.append(up, down);
                if (!schema.fixed) {
                    actions.append(button('Удалить', () => {
                        items.splice(index, 1);
                        draw();
                        (body.children[Math.min(index, items.length - 1)]?.querySelector('summary') || add).focus();
                        changed();
                    }, 'secondary remove'));
                }
                const inner = element('div', '', 'list-body');
                inner.append(actions, field(schema.item, item, next => {
                    items[index] = next;
                    if (typeof next === 'string') summary.textContent = next || schema.itemName + ' ' + (index + 1);
                }));
                row.append(summary, inner);
                body.append(row);
            });
            add.disabled = items.length >= schema.max;
        }
        draw();
        return block;
    }

    function tableField(data) {
        const block = element('div', '', 'price-editor');
        block.append(field({type: 'text', label: 'Название таблицы'}, data.title, next => { data.title = next; }));
        const grid = element('div', '', 'table-scroll');
        const actions = element('div', '', 'row-actions');
        const addRow = button('Добавить строку', () => {
            data.rows.push(data.columns.map(() => ''));
            draw();
            changed();
        });
        const addColumn = button('Добавить столбец', () => {
            data.columns.push('Новый столбец');
            data.rows.forEach(row => row.push(''));
            draw();
            changed();
        });
        actions.append(addRow, addColumn);
        block.append(grid, actions);

        function cell(value, label, update) {
            const input = inputFor(value);
            input.setAttribute('aria-label', label);
            input.addEventListener('input', () => {
                update(input.value);
                changed();
            });
            return input;
        }

        function draw() {
            const table = element('table');
            const head = element('thead');
            const headers = element('tr');
            data.columns.forEach((value, index) => {
                const header = element('th');
                const remove = button('Убрать столбец', () => {
                    data.columns.splice(index, 1);
                    data.rows.forEach(row => row.splice(index, 1));
                    draw();
                    changed();
                });
                remove.disabled = data.columns.length <= 2;
                header.append(cell(value, 'Заголовок столбца ' + (index + 1), next => { data.columns[index] = next; }), remove);
                headers.append(header);
            });
            headers.append(element('th', 'Действия'));
            head.append(headers);
            const body = element('tbody');
            data.rows.forEach((row, i) => {
                const tr = element('tr');
                row.forEach((value, j) => {
                    const td = element('td');
                    td.append(cell(value, 'Строка ' + (i + 1) + ', столбец ' + (j + 1), next => { row[j] = next; }));
                    tr.append(td);
                });
                const td = element('td');
                td.append(button('Удалить строку', () => {
                    data.rows.splice(i, 1);
                    draw();
                    changed();
                }, 'secondary remove'));
                tr.append(td);
                body.append(tr);
            });
            table.append(head, body);
            grid.replaceChildren(table);
            addRow.disabled = data.rows.length >= 100;
            addColumn.disabled = data.columns.length >= 6;
        }
        draw();
        return block;
    }

    function imageField(schema, value, update) {
        const block = element('div', '', 'image-field');
        const preview = element('img');
        preview.alt = '';
        preview.loading = 'lazy';
        function show(path) {
            if (/^\/(assets|uploads)\/[a-z0-9_./-]+\.(webp|png|jpe?g)$/i.test(path) && !path.includes('..')) {
                preview.src = path;
            } else {
                preview.removeAttribute('src');
            }
        }
        show(value);
        const input = inputFor(value);
        input.addEventListener('input', () => { show(input.value); update(input.value); });
        const file = element('input');
        file.type = 'file';
        file.accept = 'image/png,image/jpeg,image/webp';
        const feedback = element('p', '', 'muted');
        feedback.setAttribute('role', 'status');
        file.addEventListener('change', async () => {
            if (!file.files.length) return;
            const data = new FormData();
            data.set('csrf', form.elements.csrf.value);
            data.set('action', 'upload_image');
            data.set('image', file.files[0]);
            uploads++;
            submit.disabled = true;
            file.disabled = true;
            feedback.textContent = 'Загружаем изображение…';
            try {
                const response = await fetch(location.pathname + location.search, {method: 'POST', body: data});
                const result = await response.json();
                if (!response.ok || !result.path) throw new Error(result.error || 'Не удалось загрузить изображение.');
                input.value = result.path;
                show(result.path);
                update(result.path);
                feedback.textContent = 'Изображение выбрано. Сохраните изменения, чтобы опубликовать его.';
            } catch (error) {
                feedback.textContent = error.message;
            } finally {
                uploads--;
                submit.disabled = uploads > 0;
                file.disabled = false;
                file.value = '';
            }
        });
        block.append(preview, labeled(schema.label, input), labeled('Выбрать JPEG, PNG или WebP', file));
        if (schema.optional) block.append(button('Убрать фото', () => {
            input.value = '';
            show('');
            update('');
        }));
        block.append(feedback);
        return block;
    }

    for (const [key, schema] of Object.entries(editor.schema)) {
        fields.append(field(schema, editor.values[key], value => { editor.values[key] = value; }));
    }
    submit.disabled = false;
    form.addEventListener('submit', event => {
        if (uploads) {
            event.preventDefault();
            return;
        }
        form.elements.values.value = JSON.stringify(editor.values);
        dirty = false;
        submit.disabled = true;
        status.textContent = 'Сохраняем…';
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
