<?php 
$notification = new notification();
$notification->set_message($body);
$notification->set_receivers(array($userid));
try {
	$notification->send();
}
catch(Exception $exception) {
	error_log($exception->getMessage());
}
?>
