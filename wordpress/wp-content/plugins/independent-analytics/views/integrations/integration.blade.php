@php /** @var \IAWP\Integrations\Integration $integration */ @endphp

<?php
$class = '';
if ($integration->activated()) {
    $class = 'active';
    if (iawp_is_pro()) {
        $class .= ' tracking';
    } else {
        $class .= ' not-tracking';
    }
}
$plugin_slug = sanitize_title($integration->name());
if ($plugin_slug == 'kadence') {
    $plugin_slug = 'kadence-blocks';
} elseif ($plugin_slug == 'avada') {
    $plugin_slug = 'avada-forms';
}
?>
<div class="iawp-integration <?php echo esc_attr($class); ?>">
    <p class="iawp-plugin-icon"><?php echo $integration->icon(); ?></p>
    <p class="iawp-plugin-name"><?php echo esc_html($integration->name()); ?></p>
    <?php if (!iawp_is_pro()) : ?>
        <a href="https://independentwp.com/integrations/<?php echo esc_attr($plugin_slug); ?>/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Integration+Menu" target="_blank">
            <?php esc_html_e('View integration', 'independent-analytics'); ?> <span class="dashicons dashicons-external"></span>
        </a>
    <?php endif; ?>
</div>
