<?php

if (empty ($phpgw_info['server']['file_repository']))
{
	$phpgw_info['server']['file_repository'] = 'sql';
}

include (PHPGW_API_INC . '/class.vfs_' . $phpgw_info['server']['file_repository'] . '.inc.php');

?>
