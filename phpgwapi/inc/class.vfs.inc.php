<?php
	if (empty ($GLOBALS['phpgw_info']['server']['file_repository']))
	{
		$GLOBALS['phpgw_info']['server']['file_repository'] = 'sql';
	}

	include (PHPGW_API_INC . '/class.vfs_shared.inc.php');
	include (PHPGW_API_INC . '/class.vfs_' . $GLOBALS['phpgw_info']['server']['file_repository'] . '.inc.php');
?>
