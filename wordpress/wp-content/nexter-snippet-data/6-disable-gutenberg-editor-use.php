<?php
// <Internal Start>
/*
*
* @name: Disable Gutenberg Editor (Use Classic Editor)
* @description: Disable Gutenberg block editor and enable classic editor experience.
* @tags: ["Editor","Backend","Classic"]
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
add_filter('use_block_editor_for_post', '__return_false');