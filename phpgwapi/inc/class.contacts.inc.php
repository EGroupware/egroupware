<?php
	if(!$GLOBALS['phpgw_info']['server']['contact_repository'])
	{
		$GLOBALS['phpgw_info']['server']['contact_repository'] = 'sql';
	}
	if(!$GLOBALS['phpgw_info']['server']['contact_application'] ||
		$GLOBALS['phpgw_info']['server']['contact_application'] == 'addressbook')
	{
		$contactapp = 'phpgwapi';
	}
	else
	{
		$contactapp = $GLOBALS['phpgw_info']['server']['contact_application'];
	}

	$repository = PHPGW_SERVER_ROOT . '/' . $contactapp
		. '/inc/class.contacts_' . $GLOBALS['phpgw_info']['server']['contact_repository'] . '.inc.php';
	$shared     = PHPGW_SERVER_ROOT . '/' . $contactapp . '/inc/class.contacts_shared.inc.php';

	if(@file_exists($repository))
	{
		include($repository);
	}
	if(@file_exists($shared))
	{
		include($shared);
	}

	unset($contactapp);
	unset($repository);
	unset($shared);
?>
