<?php if ($lead): ?>
    <a class="back" href="<?= e(admin_link('leads')) ?>">← Все заявки</a>
    <section class="panel lead-detail">
        <div class="lead-title"><h2><?= e($lead['name']) ?></h2><span class="badge <?= e($lead['status']) ?>"><?= e(LEAD_STATUSES[$lead['status']]) ?></span></div>
        <dl>
            <dt>Телефон</dt><dd><a href="<?= e(phone_url($lead['phone'])) ?>"><?= e($lead['phone']) ?></a></dd>
            <dt>Услуга</dt><dd><?= e($lead['service']) ?></dd>
            <dt>Помещение</dt><dd><?= e($lead['property']) ?></dd>
            <dt>Площадь</dt><dd><?= e($lead['area']) ?></dd>
            <dt>Желаемое время</dt><dd><?= e($lead['time'] ?: 'Не указано') ?></dd>
            <dt>Поступила</dt><dd><?= e(lead_date($lead['created_at'])) ?> по Екатеринбургу</dd>
            <dt>Согласие</dt><dd>Получено · версия <?= e($lead['consent_version']) ?></dd>
        </dl>
        <form method="post">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
            <label>Статус<select name="status"><?php foreach (LEAD_STATUSES as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === $lead['status'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Внутренняя заметка<textarea name="note" rows="5" maxlength="4000"><?= e($lead['note']) ?></textarea></label>
            <button name="action" value="update_lead">Сохранить заявку</button>
        </form>
        <details class="danger-zone">
            <summary>Удалить заявку</summary>
            <form method="post">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                <p>Заявка и её контактные данные будут удалены из журнала.</p>
                <button class="danger" name="action" value="delete_lead">Удалить окончательно</button>
            </form>
        </details>
    </section>
<?php elseif ($leadList): ?>
    <p class="muted"><?= leads_enabled($c) ? 'Приём заявок включён.' : 'Приём заявок выключен.' ?> <a href="<?= e(admin_link('request')) ?>">Настройки формы</a></p>
    <section class="panel">
        <form method="get" class="filters" action="/admin.php">
            <input type="hidden" name="tab" value="leads">
            <label>Поиск<input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Имя, телефон или услуга"></label>
            <label>Статус<select name="status"><option value="">Все статусы</option><?php foreach (LEAD_STATUSES as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <button type="submit">Найти</button>
            <?php if ($search !== '' || $status !== ''): ?><a href="<?= e(admin_link('leads')) ?>">Сбросить</a><?php endif; ?>
        </form>
        <?php if (!$leadList['items']): ?>
            <div class="empty"><h2><?= $search !== '' || $status !== '' ? 'Заявки не найдены' : 'Заявок пока нет' ?></h2></div>
        <?php else: ?>
            <div class="table-scroll"><table class="leads-table">
                <thead><tr><th>Имя и телефон</th><th>Услуга</th><th>Дата</th><th>Статус</th></tr></thead>
                <tbody><?php foreach ($leadList['items'] as $item): ?><tr>
                    <td><a class="lead-name" href="<?= e(admin_link('leads', ['id' => $item['id']])) ?>"><?= e($item['name']) ?></a><br><span class="muted"><?= e($item['phone']) ?></span></td>
                    <td><?= e($item['service']) ?></td><td><?= e(lead_date($item['created_at'])) ?></td>
                    <td><span class="badge <?= e($item['status']) ?>"><?= e(LEAD_STATUSES[$item['status']]) ?></span></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php if ($leadList['pages'] > 1): ?>
                <nav class="pagination" aria-label="Страницы заявок">
                    <?php if ($leadList['page'] > 1): ?><a href="<?= e(admin_link('leads', ['q' => $search, 'status' => $status, 'page' => $leadList['page'] - 1])) ?>">← Предыдущая</a><?php endif; ?>
                    <span><?= $leadList['page'] ?> из <?= $leadList['pages'] ?></span>
                    <?php if ($leadList['page'] < $leadList['pages']): ?><a href="<?= e(admin_link('leads', ['q' => $search, 'status' => $status, 'page' => $leadList['page'] + 1])) ?>">Следующая →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <a class="back" href="<?= e(admin_link('leads')) ?>">← Все заявки</a>
<?php endif; ?>
