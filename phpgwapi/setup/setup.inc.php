<?php
	$setup_info['phpgwapi']['name'] = 'phpgwapi';
	$setup_info['phpgwapi']['version'] = '0.9.11';
//	$setup_info['phpgwapi']['app_order'] = '6';
	$tables = Array();
	$tables[] = 'phpgw_sessions';
	$tables[] = 'phpgw_preferences';
	$tables[] = 'phpgw_acl';
	$tables[] = 'phpgw_hooks';
	$tables[] = 'phpgw_config';
	$tables[] = 'phpgw_categories';
	$tables[] = 'phpgw_applications';
	$tables[] = 'phpgw_ass_sessions';
	$tables[] = 'phpgw_accounts';
	$tables[] = 'phpgw_access_log';
	$tables[] = 'phpgw_lang';
	$tables[] = 'phpgw_languages';
	$tables_string = implode (',', $tables);
	$setup_info['phpgwapi']['tables'] = $tables_string;
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['phpgwapi']['hooks'] = $hooks_string;
?>