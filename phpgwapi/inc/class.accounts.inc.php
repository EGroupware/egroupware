<?php
	if (empty($GLOBALS['phpgw_info']['server']['account_repository']))
	{
		if (!empty($GLOBALS['phpgw_info']['server']['auth_type']))
		{
			$GLOBALS['phpgw_info']['server']['account_repository'] = $GLOBALS['phpgw_info']['server']['auth_type'];
		}
		else
		{
			$GLOBALS['phpgw_info']['server']['account_repository'] = 'sql';
		}
	}
	include(PHPGW_API_INC . '/class.accounts_' . $GLOBALS['phpgw_info']['server']['account_repository'] . '.inc.php');
	include(PHPGW_API_INC . '/class.accounts_shared.inc.php');
?>
