<?php
// $Id: views-view-timeline.tpl.php,v 1.2.4.3 2009/09/01 19:44:49 xamanu Exp $

/**
 * @file
 * This template includes the timeline view.
 */
?>
<div class="<?php print $class; ?>-wrapper timeline-wrapper" style="width: <?php print $width; ?>; height: <?php print $height; ?>" >
  <noscript><?php print t('This page uses Javascript to show you a Timeline. Please enable Javascript in your browser to see the full page. Thank you.'); ?></noscript>
  <div id="<?php print $id; ?>" class="<?php print $class; ?> <?php print $timeline_theme ?>" style="height: <?php print $height; ?>;"></div>
  <div id="timeline_top_left"></div>
  <div id="timeline_top_right"></div>
  <div id="timeline_bot_left"></div>
  <div id="timeline_bot_right"></div>
  <?php if (isset($controls)): ?>
    <div id="<?php print $id; ?>-controls" class="<?php print $class; ?>-controls"></div>
  <?php endif; ?>
</div>
<?php if (isset($debug)) print '<br />' . $debug; ?>