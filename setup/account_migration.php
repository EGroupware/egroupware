<?php
/**
 * eGroupware Setup - Account migration between SQL <--> LDAP
 *
 * The migration is done to the account-repository configured for eGroupWare!
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include('./inc/functions.inc.php');

// Authorize the user to use setup app and load the database
if (!$GLOBALS['egw_setup']->auth('Config') || $_POST['cancel'])
{
	Header('Location: index.php');
	exit;
}
// Does not return unless user is authorized

$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
$setup_tpl->set_file(array(
	'migration' => 'account_migration.tpl',
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_alert_msg' => 'msg_alert_msg.tpl'
));
$setup_tpl->set_var('hidden_vars', html::input_hidden('csrf_token', egw_csrf::token(__FILE__)));

// check CSRF token for POST requests with any content (setup uses empty POST to call it's modules!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST)
{
	egw_csrf::validate($_POST['csrf_token'], __FILE__);
}

// determine from where we migrate to what
if (!is_object($GLOBALS['egw_setup']->db))
{
	$GLOBALS['egw_setup']->loaddb();
}
// Load configuration values account_repository and auth_type, as setup has not yet done so
foreach($GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',
	"config_name LIKE 'ldap%' OR config_name LIKE 'account_%' OR config_name LIKE '%encryption%' OR config_name='auth_type' OR config_name='mail_suffix'",
	__LINE__,__FILE__) as $row)
{
	$GLOBALS['egw_info']['server'][$row['config_name']] = $row['config_value'];
}
$from = $GLOBALS['egw_info']['server']['account_repository'];
if (!$from && !($from = $GLOBALS['egw_info']['server']['auth_type']))
{
	$from = 'sql';
}
$to = $from == 'sql' ? 'ldap' : 'sql';
$direction = strtoupper($from).' --> '.strtoupper($to);

$GLOBALS['egw_setup']->html->show_header($direction,False,'config',$GLOBALS['egw_setup']->ConfigDomain .
	'(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');

// create base one level off ldap_context
$base_parts = explode(',',$GLOBALS['egw_info']['server']['ldap_context']);
array_shift($base_parts);

$cmd = new setup_cmd_ldap(array(
	'domain' => $GLOBALS['egw_setup']->ConfigDomain,
	'sub_command' => $_POST['passwords2sql'] ? 'passwords_to_sql' : 'migrate_to_'.$to,
	// allow to set ldap root DN (ldap_admin) to create instance specific admin DN and structure
	'ldap_admin' => !empty($_POST['ldap_admin']) ? $_POST['ldap_admin'] : $GLOBALS['egw_info']['server']['ldap_root_dn'],
	'ldap_admin_pw' => !empty($_POST['ldap_admin']) ? $_POST['ldap_admin_pw'] : $GLOBALS['egw_info']['server']['ldap_root_pw'],
	'ldap_base' => implode(',',$base_parts),
	'truncate_egw_accounts' => !empty($_POST['truncate_egw_accounts']),
)+$GLOBALS['egw_info']['server']);

if (!$_POST['migrate'] && !$_POST['passwords2sql'])
{
	$accounts = $cmd->accounts($from == 'ldap');

	// now outputting the account selection
	$setup_tpl->set_block('migration','header','header');
	$setup_tpl->set_block('migration','user_list','user_list');
	$setup_tpl->set_block('migration','group_list','group_list');
	$setup_tpl->set_block('migration','ldap_admin','ldap_admin');
	$setup_tpl->set_block('migration','submit','submit');
	$setup_tpl->set_block('migration','footer','footer');
	$setup_tpl->set_block('migration','truncate_egw_accounts','truncate_egw_accounts');

	foreach($accounts as $account_id => $account)
	{
		if ($account['account_type'] == 'g')
		{
			if (isset($accounts[abs($account_id)]))
			{
				$identical_account_id_warning .= lang('Group %1 uses identical ID %2 as user %3!', $account['account_lid'], abs($account_id), $accounts[abs($account_id)]['account_lid']);
			}
			$group_list .= '<option value="' . $account_id . '" selected="1">'. $account['account_lid'] . "</option>\n";
		}
		else
		{
			$user_list .= '<option value="' . $account_id . '" selected="1">'.
				common::display_fullname($account['account_lid'],
				$account['account_firstname'],$account['account_lastname'])	. "</option>\n";
		}
	}
	$setup_tpl->set_var('action_url','account_migration.php');
	$setup_tpl->set_var('users',$user_list);
	$setup_tpl->set_var('groups',$group_list);

	$setup_tpl->set_var('description',lang('Migration between EGroupware account repositories').': '.$direction);
	$setup_tpl->set_var('select_users',lang('Select which user(s) will be exported'));
	$setup_tpl->set_var('select_groups',lang('Select which group(s) will be exported'));
	$setup_tpl->set_var('memberships',$identical_account_id_warning ?
		'<p style="color: red">'.$identical_account_id_warning.'<br/>'.lang('Migration of group will fail, as SQL does NOT allow identical IDs.').'</p>' :
		lang('Group memberships will be migrated too.'));
	$setup_tpl->set_var('ldap_admin_message', lang('Give LDAP root DN and password, if you need to create an instance specific admin user, user- or group-context'));
	$setup_tpl->set_var('ldap_admin_label', lang('Root DN'));
	$setup_tpl->set_var('ldap_admin_pw_label', lang('Root DN password'));
	$setup_tpl->set_var('migrate',$direction);
	$setup_tpl->set_var('cancel',lang('Cancel'));
	if ($from == 'sql' && $GLOBALS['egw_info']['server']['auth_type'] == 'ldap')
	{
		$setup_tpl->set_var('extra_button', html::submit_button('passwords2sql', lang('Passwords --> SQL')));
	}

	$setup_tpl->pfp('out','header');
	if($user_list)
	{
		$setup_tpl->pfp('out','user_list');
	}
	if($group_list)
	{
		$setup_tpl->pfp('out','group_list');
	}
	if ($to == 'ldap')
	{
		$setup_tpl->pfp('out','ldap_admin');
	}
	if ($to == 'sql')
	{
		$setup_tpl->set_var('truncate_egw_accounts_message', lang('Delete all existing accounts from SQL database'));
		$setup_tpl->pfp('out','truncate_egw_accounts');
	}
	$setup_tpl->pfp('out','submit');
	$setup_tpl->pfp('out','footer');
}
else	// do the migration
{
	$cmd->only = (array)$_POST['users'];
	if (empty($_POST['passwords2sql'])) $cmd->only = array_merge($cmd->only, (array)$_POST['groups']);
	$cmd->verbose = true;
	echo '<p align="center">'.str_replace("\n","</p>\n<p align='center'>",$cmd->run())."</p>\n";

	// store new repostory (and auth_type), as we are migrated now
	if ($_POST['migrate'])
	{
		config::save_value('account_repository', $GLOBALS['egw_info']['server']['account_repository']=$to, 'phpgwapi');
		if (empty($GLOBALS['egw_info']['server']['auth_type']) || $GLOBALS['egw_info']['server']['auth_type'] == $from)
		{
			config::save_value('auth_type', $GLOBALS['egw_info']['server']['auth_type']=$to, 'phpgwapi');
		}
	}
	echo '<p align="center">'.lang('Click <a href="index.php">here</a> to return to setup.')."</p>\n";
}

$GLOBALS['egw_setup']->html->show_footer();
