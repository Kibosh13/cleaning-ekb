<?php foreach ($c['custom_blocks'] as $block): ?>
    <?php if (!$block['visible']) continue; ?>
    <section class="section">
        <div class="container works-panel" data-reveal>
            <div>
                <h2><?= e($block['title']) ?></h2>
                <p><?= nl2br(e($block['text']), false) ?></p>
            </div>
            <?php if ($block['image'] !== ''): ?>
                <div class="works-photo"><img src="<?= e(image_path($block['image'])) ?>" alt="<?= e($block['title']) ?>" loading="lazy"></div>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
