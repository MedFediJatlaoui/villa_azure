<?php
/**
 * One-shot: restore Elementor revision 3508 onto live post 2617 (chambres-suites),
 * then invalidate Elementor's CSS cache for that post so it regenerates fresh.
 * Run: docker exec villa_wp php /var/www/html/wp-content/mu-plugins/assets/vaz-restore-revision.php
 * Delete afterwards.
 */
if (php_sapi_name() !== 'cli') { return; }

define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';

$POST_ID = 2617;
$REVISION_ID = 3508;

$before = get_post_field('post_modified', $POST_ID);
$restored = wp_restore_post_revision($REVISION_ID);

if (is_wp_error($restored) || !$restored) {
    fwrite(STDERR, "Restore failed: " . (is_wp_error($restored) ? $restored->get_error_message() : 'unknown') . "\n");
    exit(1);
}

$after = get_post_field('post_modified', $POST_ID);
echo "Restored revision $REVISION_ID onto post $POST_ID\n";
echo "post_modified: $before -> $after\n";

// Invalidate Elementor's compiled CSS so it regenerates from the restored data.
if (class_exists('\Elementor\Plugin')) {
    delete_post_meta($POST_ID, '_elementor_css');
    if (isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "Elementor CSS cache cleared.\n";
    }
}

echo "DONE\n";
