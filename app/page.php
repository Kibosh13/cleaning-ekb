<?php
$c=content();
$title=$c['seo_title'];
$description=$c['seo_description'];
$base=rtrim((string)($config['base_url']??''),'/');
$canonical=$base ? $base.'/' : '';
$visibleSections = array_column(array_filter($c['sections'], fn(array $section): bool => $section['visible']), 'id');
$requestVisible = in_array('request', $visibleSections, true);
$bookingHref = $requestVisible ? '#request' : phone_url($c['phone']);
$chooseHref = in_array('services', $visibleSections, true) ? '#services' : $bookingHref;
function section_navigation(bool $mobile): void {
    global $visibleSections;
    $labels = ['services' => 'services_nav', 'about' => 'about_nav', 'works' => 'works_nav', 'reviews' => 'reviews_nav', 'faq' => 'faq_nav'];
    if ($mobile) $labels['request'] = 'request_nav';
    foreach ($visibleSections as $id) {
        if (isset($labels[$id])) echo '<a href="#'.e($id).'">'.site_copy($labels[$id]).'</a>';
    }
}
function render_price_table(array $table): void { ?>
<div class="price-table-wrap"><table class="price-table"><caption><?=e($table['title'])?></caption><thead><tr><?php foreach($table['columns'] as $column): ?><th scope="col"><?=e($column)?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($table['rows'] as $row): ?><tr><?php foreach($row as $i=>$cell): ?><?php if($i===0): ?><th scope="row"><?=e($cell)?></th><?php else: ?><td><?=e($cell)?></td><?php endif; ?><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<?php }
function brand(): void { global $c; ?><a class="brand" href="/" aria-label="<?=e($c['brand'])?> — на главную"><img class="brand-logo" src="<?=e(image_path($c['logo_image']))?>" width="1460" height="1078" alt="<?=e($c['brand'])?>" decoding="async"></a><?php }
function header_html(): void { global $c, $bookingHref; ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<header class="header">
  <div class="container header-inner">
    <?php brand(); ?>
    <nav class="desktop-nav" aria-label="Основная навигация"><?php section_navigation(false); ?></nav>
    <div class="header-contact"><a class="phone" href="<?=e(phone_url($c['phone']))?>"><?=e($c['phone'])?></a><a href="<?=e($bookingHref)?>" class="callback"><?=site_copy('callback')?></a></div>
    <button class="menu-toggle" aria-expanded="false" aria-controls="mobile-nav" aria-label="Открыть меню"><span></span><span></span></button>
  </div>
  <nav id="mobile-nav" class="mobile-nav" aria-label="Мобильная навигация" hidden><?php section_navigation(true); ?></nav>
</header>
<?php }
function footer_html(): void { global $c; ?>
<footer class="footer"><div class="container footer-top"><?php brand(); ?><p><?= copy_lines('footer_caption') ?></p><a class="to-top" href="#top"><?= site_copy('footer_top') ?></a></div><div class="container footer-bottom"><p>© <?=date('Y')?> <?=e($c['brand'])?><br><?=e($c['operator'])?> · ИНН <?=e($c['inn'])?></p><div><a href="<?=page_url('privacy')?>"><?= site_copy('footer_privacy') ?></a><a href="<?=page_url('consent')?>"><?= site_copy('footer_consent') ?></a><button class="cookie-settings" type="button"><?= site_copy('footer_cookie') ?></button></div></div></footer>
<div class="cookie-banner" role="region" aria-label="<?= site_copy('footer_cookie') ?>" hidden><p><?=site_copy('cookie_text')?> <a href="<?=page_url('cookies')?>"><?= site_copy('cookie_more') ?></a></p><div><button type="button" data-cookie="essential"><?= site_copy('cookie_essential') ?></button><button type="button" data-cookie="all"><?= site_copy('cookie_all') ?></button></div></div>
<?php }
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php if(is_preview()): ?><meta name="robots" content="noindex,nofollow,noarchive"><meta name="googlebot" content="noindex,nofollow,noarchive"><?php else: ?><meta name="robots" content="index,follow"><?php endif; ?><meta name="color-scheme" content="light"><title><?=e($title)?></title><meta name="description" content="<?=e($description)?>"><?php if($canonical): ?><link rel="canonical" href="<?=e($canonical)?>"><?php endif; ?><meta property="og:title" content="<?=e($title)?>"><meta property="og:description" content="<?=e($description)?>"><meta property="og:type" content="website"><meta property="og:locale" content="ru_RU"><?php if($base): ?><meta property="og:url" content="<?=e($canonical)?>"><meta property="og:image" content="<?=e($base.image_path($c['hero_image']))?>"><?php endif; ?><?php if($config['webmaster_verification']): ?><meta name="yandex-verification" content="<?=e($config['webmaster_verification'])?>"><?php endif; ?><link rel="icon" href="<?=e(image_path($c['logo_image']))?>"><link rel="preload" href="/assets/site.css?v=20261108-2" as="style"><link rel="stylesheet" href="/assets/site.css?v=20261108-2"><script src="/assets/site.js?v=20261113-1" defer></script>
<script type="application/ld+json"><?=json_encode(array_filter(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$c['brand'],'url'=>$base ?: null,'telephone'=>$c['phone'],'email'=>$c['email'],'areaServed'=>$c['area'],'sameAs'=>[$c['maps_url']]]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP)?></script></head>
<body id="top" data-preview="<?=is_preview()?'true':'false'?>" data-metrika="<?=e($config['metrika_id'])?>"><?php header_html(); ?>
<main id="main">
<section class="hero hero-fullscreen" aria-label="Профессиональная уборка дома и офиса">
  <div class="hero-background"><img src="<?=e(image_path($c['hero_image']))?>" width="1536" height="1024" alt="<?= site_copy('hero_alt') ?>" fetchpriority="high"></div>
  <div class="hero-shade" aria-hidden="true"></div>
  <div class="container hero-content"><p class="hero-kicker"><?= site_copy('hero_kicker') ?></p><h1><?=e($c['hero_title'])?></h1><p class="hero-description"><?=e($c['hero_description'])?></p><div class="hero-actions"><a class="button button-light main-action" href="<?=e($bookingHref)?>"><?= site_copy('book') ?></a><a class="button button-glass" href="<?=e($chooseHref)?>"><?= site_copy('choose') ?></a></div></div>
  <div class="container hero-bottom"><p><?=copy_lines('hero_caption')?></p><p><?= site_copy('hero_details') ?><br><span><?=e($c['area'])?></span></p></div>
</section>
<?php
$serviceGroup = ['services', 'additional', 'promotion'];
$groupOpen = false;
foreach ($visibleSections as $id) {
    if (!isset(SECTION_NAMES[$id])) continue;
    $inGroup = in_array($id, $serviceGroup, true);
    if ($inGroup && !$groupOpen) {
        echo '<section class="section services"><div class="container">';
    } elseif (!$inGroup && $groupOpen) {
        echo '</div></section>';
    }
    $groupOpen = $inGroup;
    if ($id === 'services') echo '<div id="services">';
    require __DIR__ . '/sections/' . $id . '.php';
    if ($id === 'services') echo '</div>';
}
if ($groupOpen) echo '</div></section>';
?>
</main><?php footer_html(); ?>
<dialog id="service-dialog" class="service-dialog"><button class="dialog-close" type="button" aria-label="Закрыть">×</button><p class="eyebrow"><?= site_copy('dialog_label') ?></p><h2 id="dialog-title"></h2><p id="dialog-description"></p><div id="dialog-sections"></div><div id="dialog-pricing"></div><p id="dialog-note" class="content-note"></p><p class="dialog-price" id="dialog-price"></p><a class="button button-green main-action" href="<?=e($bookingHref)?>" id="dialog-choose"><?= site_copy('book') ?></a><?php if (in_array('additional', $visibleSections, true)): ?><a id="dialog-extras" class="text-link" href="#additional"><?=site_copy('dialog_extras')?></a><?php endif; ?></dialog>
<script id="services-data" type="application/json"><?=json_encode($c['services'],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<script id="contact-data" type="application/json"><?=json_encode(['email'=>$c['email'],'phone'=>$c['phone']],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?></script>
</body></html>
