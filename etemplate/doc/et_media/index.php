<?php
	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'	=> 'et_media',
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	include('../header.inc.php');

	$et_media = CreateObject('et_media.et_media');

	$et_media->edit();

	$GLOBALS['phpgw']->common->phpgw_footer();
