<?php
// <Internal Start>
/*
*
* @name: Limit Post Revisions to Optimize Database
* @description: Restrict number of saved post revisions to reduce database size.
* @tags: ["Database","Optimization","Performance"]
* @type: php
* @status: publish
* @created_by: 1
* @created_at: 2026-06-16 18:52:57
* @updated_at: 2026-06-16 18:52:57
* @updated_by: 1
* @condition: {"status":0,"priority":10,"code-execute":"global","php-hidden-execute":"yes"}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal End> ?>
<?php
define('WP_POST_REVISIONS', 5);