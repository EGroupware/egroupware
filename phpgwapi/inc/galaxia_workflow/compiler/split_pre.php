<?php
//Code to be executed before a split activity
// If we didn't retrieve the instance before
if(empty($instance->instanceId)) {
  // This activity needs an instance to be passed to 
  // be started, so get the instance into $instance.
  if(isset($_REQUEST['iid'])) {
    $instance->getInstance($_REQUEST['iid']);
  } else {
    // defined in lib/Galaxia/config.php
    galaxia_show_error(lang("No instance indicated"));
    die;  
  }
}
// Set the current user for this activity
if(isset($GLOBALS['user']) && ($activity->isInteractive()) && !empty($instance->instanceId) && !empty($activity_id)) {
  if (!$instance->setActivityUser($activity_id,$GLOBALS['user'])){
     galaxia_show_error(lang("You do not have the right to run this activity anymore, maybe a concurrent access problem, refresh your datas."));
     die;
  }
}
?>
