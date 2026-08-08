<?php
// <Internal Start>
/*
*
* @name: Add Google Analytics Tracking Code
* @description: Insert Google Analytics script directly without extra plugins.
* @tags: ["Analytics","Tracking","Frontend"]
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
add_action('wp_head', function() { ?>
<!-- Replace with your Google Analytics Code -->
<script async src='https://www.googletagmanager.com/gtag/js?id=YOUR-ID'></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', 'YOUR-ID');
</script>
<?php });