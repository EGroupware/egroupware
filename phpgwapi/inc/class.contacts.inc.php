<?php
	if (!$phpgw_info['server']['contact_repository'])
	{
		$phpgw_info['server']['contact_repository'] = 'sql';
	}
	include(PHPGW_API_INC . '/class.contacts_'.$phpgw_info['server']['contact_repository'] . '.inc.php');
	include(PHPGW_API_INC . '/class.contacts_shared.inc.php');
?>
