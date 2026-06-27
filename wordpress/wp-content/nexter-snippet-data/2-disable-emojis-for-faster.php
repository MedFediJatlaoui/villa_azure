<?php
// <Internal Start>
/*
*
* @name: Disable Emojis for Faster Loading
* @description: Disable WordPress emoji scripts to improve page speed.
* @tags: ["Performance","Optimization","Frontend"]
* @type: php
* @status: publish
* @created_by: 1
* @created_at: 2026-06-16 18:52:57
* @updated_at: 2026-06-16 18:52:57
* @updated_by: 1
* @condition: {"status":0,"priority":10,"code-execute":"front-end","php-hidden-execute":"yes"}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal End> ?>
<?php
remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');