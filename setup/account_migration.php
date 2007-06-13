<?php
/**
 * Setup - Account migration between SQL <--> LDAP
 * 
 * The migration is done to the account-repository configured for eGroupWare!
 * 
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'home',
		'noapi'      => True
));
include('./inc/functions.inc.php');

// Authorize the user to use setup app and load the database
if (!$GLOBALS['egw_setup']->auth('Config') || $_POST['cancel'])
{
	Header('Location: index.php');
	exit;
}
// Does not return unless user is authorized

// the migration script needs a session to store the accounts
session_name('setup_session');
session_set_cookie_params(0,'/',$GLOBALS['egw_setup']->cookie_domain);
if (isset($_REQUEST['setup_session']))
{
    session_id($_REQUEST['setup_session']);
}
session_start();

$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
$setup_tpl = CreateObject('setup.Template',$tpl_root);
$setup_tpl->set_file(array(
	'migration' => 'account_migration.tpl',
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_alert_msg' => 'msg_alert_msg.tpl'
));

function hash_sql2ldap($hash)
{
	switch(strtolower($GLOBALS['egw_info']['server']['sql_encryption_type']))
	{
		case '':	// not set sql_encryption_type
		case 'md5':
			$hash = '{md5}' . base64_encode(pack("H*",$hash));
			break;
		case 'crypt':
			$hash = '{crypt}' . $hash;
			break;
	}
	return $hash;
}

// determine from where we migrate to what
if (!is_object($GLOBALS['egw_setup']->db))
{
	$GLOBALS['egw_setup']->loaddb();
}
// Load configuration values account_repository and auth_type, a setup has not yet done so
$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',
	array('config_name'=>array('account_repository','auth_type')),__LINE__,__FILE__);
while(($row = $GLOBALS['egw_setup']->db->row(true)))
{
	$GLOBALS['egw_info']['server'][$row['config_name']] = $row['config_value'];
}
$to = $GLOBALS['egw_info']['server']['account_repository'];
if (!$to && !($to = $GLOBALS['egw_info']['server']['auth_type']))
{
	$to = 'sql';
}
$from = $to == 'sql' ? 'ldap' : 'sql';
$direction = strtoupper($from).' --> '.strtoupper($to);

$GLOBALS['egw_setup']->html->show_header($direction,False,'config',$GLOBALS['egw_setup']->ConfigDomain . 
	'(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
	
if (!$_POST['migrate'])
{
	// fetch and display the accounts of the NOT set $from repository
	$GLOBALS['egw_info']['server']['account_repository'] = $from;
	$GLOBALS['egw_setup']->setup_account_object();
	
	// fetch all users and groups
	$accounts = $GLOBALS['egw']->accounts->search(array(
		'type' => 'both',
	));
	// fetch the complete data (search reads not everything), plus the members(hips)
	foreach($accounts as $account_id => $account)
	{
		if ($account_id != $account['account_id'])      // not all backends have as key the account_id
		{
			unset($accounts[$account_id]);
			$account_id = $account['account_id'];
		}
		$accounts[$account_id] = $GLOBALS['egw']->accounts->read($account_id);

		if ($account['account_type'] == 'g')
		{
			$accounts[$account_id]['members'] = $GLOBALS['egw']->accounts->members($account_id,true);
		}
		else
		{
			$accounts[$account_id]['memberships'] = $GLOBALS['egw']->accounts->memberships($account_id,true);
		}
	}
	//_debug_array($accounts);
	// store the complete info in the session to be availible after user selected what to migrate
	// we cant instanciate to account-repositories at the same time, as the backend-classes have identical names
	$_SESSION['all_accounts'] =& $accounts;
	
	// now outputting the account selection
	$setup_tpl->set_block('migration','header','header');
	$setup_tpl->set_block('migration','user_list','user_list');
	$setup_tpl->set_block('migration','group_list','group_list');
	$setup_tpl->set_block('migration','submit','submit');
	$setup_tpl->set_block('migration','footer','footer');
	
	foreach($accounts as $account_id => $account)
	{
		if ($account['account_type'] == 'g')
		{
			$group_list .= '<option value="' . $account_id . '" selected="1">'. $account['account_lid'] . "</option>\n";
		}
		else
		{
			$user_list .= '<option value="' . $account_id . '" selected="1">'.
				$GLOBALS['egw']->common->display_fullname($account['account_lid'],
				$account['account_firstname'],$account['account_lastname'])	. "</option>\n";
		}
	}
	$setup_tpl->set_var('action_url','account_migration.php');
	$setup_tpl->set_var('users',$user_list);
	$setup_tpl->set_var('groups',$group_list);
	
	$setup_tpl->set_var('description',lang('Migration between eGroupWare account repositories').': '.$direction);
	$setup_tpl->set_var('select_users',lang('Select which user(s) will be exported'));
	$setup_tpl->set_var('select_groups',lang('Select which group(s) will be exported'));
	$setup_tpl->set_var('memberships',lang('Group memberships will be migrated too.'));
	$setup_tpl->set_var('migrate',$direction);
	$setup_tpl->set_var('cancel',lang('Cancel'));
	
	$setup_tpl->pfp('out','header');
	if($user_list)
	{
		$setup_tpl->pfp('out','user_list');
	}
	if($group_list)
	{
		$setup_tpl->pfp('out','group_list');
	}
	$setup_tpl->pfp('out','submit');
	$setup_tpl->pfp('out','footer');
}
else	// do the migration
{
	$GLOBALS['egw_info']['server']['account_repository'] = $to;
	$GLOBALS['egw_setup']->setup_account_object();

	$target = strtoupper($to);
	$accounts =& $_SESSION['all_accounts'];

	if($_POST['users'])
	{
		foreach($_POST['users'] as $account_id)
		{
			if (!isset($accounts[$account_id])) continue;

			// check if user already exists
			if ($GLOBALS['egw']->accounts->exists($account_id))
			{
				echo '<p>'.lang('%1 already exists in %2.',lang('User')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";
				continue;
			}
			if ($to == 'ldap')
			{
				if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
				{
					$accounts[$account_id]['homedirectory'] = $GLOBALS['egw_info']['server']['ldap_account_home'] . '/' . $accounts[$account_id]['account_lid'];
					$accounts[$account_id]['loginshell'] = $GLOBALS['egw_info']['server']['ldap_account_shell'];
				}
				$accounts[$account_id]['account_passwd'] = hash_sql2ldap($accounts[$account_id]['account_pwd']);
			}
			else
			{
				// ToDo migrate ldap password hashes to sql, not as easy as we dont store the hash-type in the password
				// maybe we should change sql to store passwords identical to ldap prefixed with {hash}
				$accounts[$account_id]['account_passwd'] = $accounts[$account_id]['account_pwd'];
			}
			unset($accounts[$account_id]['person_id']);

			if (!$GLOBALS['egw']->accounts->save($accounts[$account_id]))
			{
				echo '<p>'.lang('Creation of %1 in %2 failed !!!',lang('User')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";
				continue;
			}
			$GLOBALS['egw']->accounts->set_memberships($accounts[$account_id]['memberships'],$account_id);
			echo '<p>'.lang('%1 created in %2.',lang('User')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";
		}
	}
	if($_POST['groups'])
	{
		foreach($_POST['groups'] as $account_id)
		{
			if (!isset($accounts[$account_id])) continue;

			// check if group already exists
			if (!$GLOBALS['egw']->accounts->exists($account_id))
			{
				if (!$GLOBALS['egw']->accounts->save($accounts[$account_id]))
				{
					echo '<p>'.lang('Creation of %1 in %2 failed !!!',lang('Group')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";
					continue;
				}
				echo '<p>'.lang('%1 created in %2.',lang('Group')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";
			}
			else
			{
				echo '<p>'.lang('%1 already exists in %2.',lang('Group')." $account_id ({$accounts[$account_id]['account_lid']})",$target)."</p>\n";

				if ($GLOBALS['egw']->accounts->id2name($account_id) != $accounts[$account_id]['account_lid'])
				{
					continue;	// different group under that gidnumber!
				}
			}
			// now saving / updating the memberships
			$GLOBALS['egw']->accounts->set_members($accounts[$account_id]['members'],$account_id);
		}
	}
	echo '<p align="center">'.lang('Export has been completed!')."</p>\n";
	echo '<p align="center">'.lang('Click <a href="index.php">here</a> to return to setup.')."</p>\n";
}

$GLOBALS['egw_setup']->html->show_footer();
