<?php
	$title = $appname;
	$file = Array(
		'Login History' => array('/index.php','menuaction=admin.uiaccess_history.list_history')
	);

	display_section($appname,$title,$file);
?>