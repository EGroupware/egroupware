<?php
	$setup_info['addressbook']['name'] = 'Addressbook';
	$setup_info['addressbook']['version'] = '0.9.11';
	$setup_info['addressbook']['app_order'] = 4;
	$setup_info['addressbook']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['addressbook']['hooks'] = $hooks_string;
?>