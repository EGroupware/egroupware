#!/usr/bin/php -qC 
<?php
/**
 * Admin - Command line interface
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>admin-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] > 1)
{
	$arguments = $_SERVER['argv'];
	array_shift($arguments);
	$action = array_shift($arguments);
}
else
{
	$action = '--help';
}
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
$arg0s = explode(',',@$arguments[0]);
@list(,$_GET['domain']) = explode('@',$arg0s[0]);

if (is_dir('/tmp')) ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
	)
);

include('../header.inc.php');

switch($action)
{
	case '--delete-user':
		return do_delete_user($arg0s[2],$arg0s[3]);
		
	case '--change-account-id':
		return do_change_account_id($arg0s);

	default:
		usage($action);
		break;
}
exit(0);

/**
 * callback if the session-check fails, redirects via xajax to login.php
 * 
 * @param array &$account account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow the access and account is set, a sessionid or false otherwise
 */
function user_pass_from_argv(&$account)
{
	$account = array(
		'login'  => $GLOBALS['arg0s'][0],
		'passwd' => $GLOBALS['arg0s'][1],
		'passwd_type' => 'text',
	);
	//print_r($account);
	if (!($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		echo "Wrong admin-account or -password !!!\n\n";
		usage('',1);
	}
	if (!$GLOBALS['egw_info']['user']['apps']['admin'])	// will be tested by the header too, but whould give html error-message
	{
		echo "Permission denied !!!\n\n";
		usage('',2);
	}
	return $sessionid;
}

/**
 * Give a usage message and exit
 *
 * @param string $action=null
 * @param int $ret=0 exit-code
 */
function usage($action=null,$ret=0)
{
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: $cmd command [additional options]\n\n";
	
	echo "--delete-user admin-account[@domain],admin-password,account-to-delete[,account-to-move-data]\n";
	echo "	Deletes a user from eGroupWare. It's data can be moved to an other user or it get deleted too.\n";
	echo "--change-account-id admin-account[@domain],admin-password,from1,to1[...,fromN,toN]\n";
	echo "	Changes one or more account_id's in the database (make a backup before!).\n";
	exit;	
}

/**
 * Delete a given user from eGW
 *
 * @param int/string $user
 * @param int/string $new_user=0
 * @return int 0 on success, 2-4 otherwise (see source)
 */
function do_delete_user($user,$new_user=0)
{
	//echo "do_delete_user('$user','$new_user')\n";
	if ($GLOBALS['egw']->acl->check('account_access',32,'admin'))	// user is explicitly forbidden to delete users
	{
		echo "Permission denied !!!\n";
		return 2;
	}
	if (!is_numeric($user) && !($uid = $GLOBALS['egw']->accounts->name2id($lid=$user)) ||
	     is_numeric($user) && !($lid = $GLOBALS['egw']->accounts->id2name($uid=$user)))
	{
		echo "Unknown user to delete: $user !!!\n";
		return 3;
	}
	if ($new_user && (!is_numeric($new_user) && !($new_uid = $GLOBALS['egw']->accounts->name2id($new_user)) ||
	                   is_numeric($new_user) && !$GLOBALS['egw']->accounts->id2name($new_uid=$new_user)))
	{
		echo "Unknown user to move to: $new_user !!!\n";
		return 4;
	}
	// delete the suer
	$GLOBALS['hook_values'] = array(
		'account_id'  => $uid,
		'account_lid' => $lid,
		'new_owner'   => (int)$new_uid,
		'location'    => 'deleteaccount',
	);
	// first all other apps, then preferences and admin
	foreach(array_merge(array_diff(array_keys($GLOBALS['egw_info']['apps']),array('preferences','admin')),array('preferences','admin')) as $app)
	{
		$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'],$app);
	}
	echo "Account '$user' deleted.\n";
	return 0;
}


function do_change_account_id($args)
{
	/**
	 * App-, Table- and column-names containing nummeric account-id's
	 * @var array
	 */
	$columns2change = array(
		'phpgwapi' => array(
			'egw_access_log'     => 'account_id',
			'egw_accounts'       => array(array('account_id','.type'=>'abs'),'account_primary_group'),
			'egw_acl'            => array('acl_account','acl_location'),
			'egw_addressbook'    => array('contact_owner','contact_creator','contact_modifier','account_id'),
			'egw_addressbook2list' => array('list_added_by'),
			'egw_addressbook_extra' => 'contact_owner',
			'egw_addressbook_lists' => array('list_owner','list_creator'),
			'egw_api_content_history' => 'sync_changedby',
			'egw_applications'   => false,
			'egw_app_sessions'   => 'loginid',
			'egw_async'          => 'async_account_id',
			'egw_categories'     => array(array('cat_owner','cat_owner > 0')),	// -1 are global cats, not cats from group 1!
			'egw_config'         => false,
			'egw_history_log'    => 'history_owner',
			'egw_hooks'          => false,
			'egw_interserv'      => false,
			'egw_lang'           => false,
			'egw_languages'      => false,
			'egw_links'          => 'link_owner',
			'egw_log'            => 'log_user',
			'egw_log_msg'        => false,
			'egw_nextid'         => false,
			'egw_preferences'    => array(array('preference_owner','preference_owner > 0')),
			'egw_sessions'       => false,	// only account_lid stored
			'egw_vfs'            => array('vfs_owner_id','vfs_createdby_id','vfs_modifiedby_id'),	// 'vfs_directory' contains account_lid for /home/account 
		),
		'etemplate' => array(
			'egw_etemplate'      => 'et_group',
		),
		'bookmarks' => array(
			'egw_bookmarks'      => 'bm_owner',
		),
		'calendar' => array(
			'egw_cal'            => array('cal_owner','cal_modifier'),
			'egw_cal_dates'      => false,
			'egw_cal_extra'      => false,
			'egw_cal_holidays'   => false,
			'egw_cal_repeats'    => false,
			'egw_cal_user'       => array(array('cal_user_id','cal_user_type' => 'u')),	// cal_user_id for cal_user_type='u'
		),
		'emailadmin' => array(
			'egw_emailadmin'     => false,
		),
		'felamimail' => array(
			'egw_felamimail_accounts'      => 'fm_owner',
			'egw_felamimail_cache'         => 'fmail_accountid',	// afaik not used in 1.4+
			'egw_felamimail_displayfilter' => 'fmail_filter_accountid',
			'egw_felamimail_folderstatus'  => 'fmail_accountid',	// afaik not used in 1.4+
			'egw_felamimail_signatures'    => 'fm_accountid',
		),
		'infolog' => array(
			'egw_infolog'        => array('info_owner',array('info_responsible','.type' => 'comma-sep'),'info_modifier'),
			'egw_infolog_extra'  => false,
		),
		'news_admin' => array(
			'egw_news'           => 'news_submittedby',
			'egw_news_export'    => false,
		),
		'projectmanager' => array(
			'egw_pm_constraints' => false,
			'egw_pm_elements'    => array('pe_modifier',array('pe_resources','.type' => 'comma-sep')),
			'egw_pm_extra'       => false,
			'egw_pm_members'     => 'member_uid',
			'egw_pm_milestones'  => false,
			'egw_pm_pricelist'   => false,
			'egw_pm_prices'      => 'pl_modifier',
			'egw_pm_projects'    => array('pm_creator','pm_modifier'),
			'egw_pm_roles'       => false,
		),
		'registration' => array(
			'egw_reg_accounts'   => false,
			'egw_reg_fields'     => false,
		),
		'resources' => array(
			'egw_resources'      => false,
			'egw_resources_extra'=> 'extra_owner',
		),
		'sitemgr' => array(
			'egw_sitemgr_active_modules'   => false,
			'egw_sitemgr_blocks'           => false,
			'egw_sitemgr_blocks_lang'      => false,
			'egw_sitemgr_categories_lang'  => false,
			'egw_sitemgr_categories_state' => false,
			'egw_sitemgr_content'          => false,
			'egw_sitemgr_content_lang'     => false,
			'egw_sitemgr_modules'          => false,
			'egw_sitemgr_notifications'    => false,
			'egw_sitemgr_notify_messages'  => false,
			'egw_sitemgr_pages'            => false,
			'egw_sitemgr_pages_lang'       => false,
			'egw_sitemgr_properties'       => false,
			'egw_sitemgr_sites'            => false,
		),
		'syncml' => array(
			'egw_contentmap'        => false,
			'egw_syncmldeviceowner' => false,	// Lars: is owner_devid a account_id???
			'egw_syncmldevinfo'     => false,
			'egw_syncmlsummary'     => false,
		),
		'timesheet' => array(
			'egw_timesheet'      => array('ts_owner','ts_modifier'),
			'egw_timesheet_extra'=> false,
		),
		'wiki' => array(
			'egw_wiki_interwiki' => false,
			'egw_wiki_links'     => false,
			'egw_wiki_pages'     => array(array('wiki_readable','wiki_readable < 0'),array('wiki_writable','wiki_writable < 0')),	// only groups
			'egw_wiki_rate'      => false,
			'egw_wiki_remote_pages' => false,
			'egw_wiki_sisterwiki'=> false,
		),
		'phpbrain' => array(	// aka knowledgebase
			'phpgw_kb_articles'  => array('user_id','modified_user_id'),
			'phpgw_kb_comment'   => 'user_id',
			'phpgw_kb_files'     => false,
			'phpgw_kb_questions' => 'user_id',
			'phpgw_kb_ratings'   => 'user_id',
			'phpgw_kb_related_art' => false,
			'phpgw_kb_search'    => false,
			'phpgw_kb_urls'      => false,
		),
		'polls' => array(
			'phpgw_polls_data'   => false,
			'phpgw_polls_desc'   => false,
			'phpgw_polls_settings' => false,
			'phpgw_polls_user'   => 'user_id',
		),
		// MyDMS	ToDo!!!
		// VFS2		ToDo!!!
	);
	
	if (count($args) < 4) usage();	// 4 means at least user,pw,from1,to1
	
	$ids2change = array();
	for($n = 2; $n < count($args); $n += 2)
	{
		$from = (int)$args[$n];
		$to   = (int)$args[$n+1];
		if (!$from || !$to) die("\nAccount-id's have to be integers!\n\n");
		$ids2change[$from] = $to;
	}
	$total = 0;
	foreach($columns2change as $app => $data)
	{
		$db = clone($GLOBALS['egw']->db);
		$db->set_app($app);
		
		foreach($data as $table => $columns)
		{
			if (!$columns)
			{
				echo "$app: $table no columns with account-id's\n";
				continue;	// noting to do for this table
			}
			if (!is_array($columns)) $columns = array($columns);
			
			foreach($columns as $column)
			{
				$type = $where = null;
				if (is_array($column))
				{
					$type = $column['.type'];
					unset($column['.type']);
					$where = $column;
					$column = array_shift($where);
				}
				$total += ($changed = _update_account_id($ids2change,$db,$table,$column,$where,$type));
				echo "$app: $table.$column $changed id's changed\n";
			}
		}
	}
	echo "\nTotal of $total id's changed.\n\n";
}

function _update_account_id($ids2change,$db,$table,$column,$where=null,$type=null)
{
	static $update_sql;
	
	if (is_null($update_sql))
	{
		foreach($ids2change as $from => $to)
		{
			$update_sql .= "WHEN $from THEN $to ";
		}
		$update_sql .= "END";
	}
	switch($type)
	{
		case 'comma-sep':
			if (!$where) $where = array();
			$where[] = "$column IS NOT NULL";
			$where[] = "$column != ''";
			$db->select($table,'DISTINCT '.$column,$where,__LINE__,__FILE__);
			$change = array();
			while(($row = $db->row(true)))
			{
				$ids = explode(',',$old_ids=$row[$column]);
				foreach($ids as $key => $id)
				{
					if (isset($account_id2change[$id])) $ids[$key] = $account_id2change[$id];
				}
				$ids = implode(',',$ids);
				if ($ids != $old_ids)
				{
					$change[$old_ids] = $ids;
				}
			}
			$changed = 0;
			foreach($change as $from => $to)
			{
				$db->update($table,array($column=>$to),$where+array($column=>$from),__LINE__,__FILE__);
				$changed += $db->affected_rows();
			}
			break;
			
		case 'abs':
			if (!$where) $where = array();
			$where[$column] = array();
			foreach(array_keys($ids2change) as $id)
			{
				$where[$column][] = abs($id);
			}
			$db->update($table,$column.'= CASE '.$column.' '.preg_replace('/-([0-9]+)/','\1',$update_sql),$where,__LINE__,__FILE__);
			$changed = $db->affected_rows();
			break;
			
		default:
			if (!$where) $where = array();
			$where[$column] = array_keys($ids2change);
			$db->update($table,$column.'= CASE '.$column.' '.$update_sql,$where,__LINE__,__FILE__);
			$changed = $db->affected_rows();
			break;
	}
	return $changed;
}
