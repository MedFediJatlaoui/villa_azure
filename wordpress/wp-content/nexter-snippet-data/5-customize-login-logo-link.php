<?php
// <Internal Start>
/*
*
* @name: Customize Login Logo Link URL
* @description: Change WordPress login logo URL to your site homepage.
* @tags: ["Branding","Login-Page","Frontend"]
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
function custom_login_url() {
	return home_url();
}
add_filter('login_headerurl', 'custom_login_url');