<?php
//Code to be executed before a start activity
// If we didn't retrieve the instance before
if(empty($instance->instanceId) && isset($_REQUEST['iid'])) {
  // in case we're looping back to a start activity, we need to retrieve the instance
  $instance->getInstance($_REQUEST['iid']);
} else {
  // otherwise we'll create an instance when this activity is completed
}

?>
