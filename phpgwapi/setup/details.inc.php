<?php
	include('../version.inc.php');
	$phpgw_info['setup']['phpgwapi']['name'] = 'phpgwapi';
	$phpgw_info['setup']['phpgwapi']['version'] = $phpgw_info['server']['versions']['phpgwapi'];
//	$phpgw_info['setup']['phpgwapi']['app_order'] = '6';
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
	$phpgw_info['setup']['phpgwapi']['tables'] = $tables_string;
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$phpgw_info['setup']['phpgwapi']['hooks'] = $hooks_string;
?>