<?php
	/* Basic information about this app */
	$setup_info['phpgwapi']['name']    = 'phpgwapi';
	$setup_info['phpgwapi']['title']   = 'phpgwapi';
	$setup_info['phpgwapi']['version'] = '0.9.12.001';
//	$setup_info['phpgwapi']['app_order'] = '6';

	/* The tables this app creates */
	$setup_info['phpgwapi']['tables'][] = 'phpgw_sessions';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_preferences';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_acl';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_hooks';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_config';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_categories';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_applications';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_app_sessions';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_accounts';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_access_log';
	$setup_info['phpgwapi']['tables'][] = 'lang';
	$setup_info['phpgwapi']['tables'][] = 'languages';
	$setup_info['phpgwapi']['tables'][] = 'phpgw_nextid';
?>
