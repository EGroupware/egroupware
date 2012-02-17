<?php
/**
 * eGgroupWare admin - admin command: change an account_id
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */


/**
 * admin command: change an account_id
 */
class admin_cmd_change_account_id extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param array $change array with old => new id pairs
	 */
	function __construct(array $change)
	{
		if (!isset($change['change']))
		{
			$change = array(
				'change' => $change,
			);
		}
		admin_cmd::__construct($change);
	}

	/**
	 * App-, Table- and column-names containing nummeric account-id's
	 * @var array
	 */
	private $columns2change = array(
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
				'egw_async'          => 'async_account_id',
				'egw_categories'     => array(array('cat_owner','.type' => 'comma-sep')),
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
				'egw_sqlfs'          => array('fs_uid','fs_creator','fs_modifier',array('fs_gid','.type' => 'absgroup')),
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
				'egw_emailadmin'     => array(array('ea_user',"ea_user > '0'"),array('ea_group',"ea_group < '0'")),
			),
			'felamimail' => array(
				'egw_felamimail_accounts'      => 'fm_owner',
				'egw_felamimail_displayfilter' => 'fmail_filter_accountid',
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
			'tracker' => array(
				'egw_tracker'           => array('tr_creator','tr_modifier'),
				'egw_tracker_assignee'	=> 'tr_assigned',
				'egw_tracker_bounties'  => array('bounty_creator','bounty_confirmer'),
				'egw_tracker_replies'   => array('reply_creator'),
				'egw_tracker_votes'     => array('vote_uid'),
			),
			'timesheet' => array(
				'egw_timesheet'      => array('ts_owner','ts_modifier'),
				'egw_timesheet_extra'=> false,
			),
			'wiki' => array(
				'egw_wiki_interwiki' => false,
				'egw_wiki_links'     => false,
				'egw_wiki_pages'     => array(array('wiki_readable',"wiki_readable < '0'"),array('wiki_writable',"wiki_writable < '0'")),	// only groups
				'egw_wiki_rate'      => false,
				'egw_wiki_remote_pages' => false,
				'egw_wiki_sisterwiki'=> false,
			),
			'phpbrain' => array(	// aka knowledgebase
				'egw_kb_articles'  => array('user_id','modified_user_id'),
				'egw_kb_comment'   => 'user_id',
				'egw_kb_files'     => false,
				'egw_kb_questions' => 'user_id',
				'egw_kb_ratings'   => 'user_id',
				'egw_kb_related_art' => false,
				'egw_kb_search'    => false,
				'egw_kb_urls'      => false,
			),
			'polls' => array(
				'egw_polls'         => false,
				'egw_polls_answers' => false,
				'egw_polls_votes'   => 'vote_uid',
			),
			'gallery' => array(
				'g2_ExternalIdMap' => array(array('g_externalId',"g_entityType='GalleryUser'")),
			),
			'eventmgr' => array(
				'egw_eventmgr' => array('event_creator', 'event_modifier', 'event_wpm_inhouse', 'event_sales_engineer')
			),
			// MyDMS	ToDo!!!
			// VFS2		ToDo!!!
		);

	/**
	 * give or remove run rights from a given account and application
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws egw_exception_no_admin
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws egw_exception_wrong_userinput(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	protected function exec($check_only=false)
	{
		foreach($this->change as $from => $to)
		{
			if (!(int)$from || !(int)$to)
			{
				throw new egw_exception_wrong_userinput(lang("Account-id's have to be integers!"),16);
			}
		}
		if ($check_only) return true;

		$total = 0;
		foreach($this->columns2change as $app => $data)
		{
			if (!isset($GLOBALS['egw_info']['apps'][$app])) continue;	// $app is not installed

			$db = clone($GLOBALS['egw']->db);
			$db->set_app($app);

			foreach($data as $table => $columns)
			{
				$db->column_definitions = $db->get_table_definitions($app,$table);
				$db->column_definitions = $db->column_definitions['fd'];
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
					$total += ($changed = self::_update_account_id($this->change,$db,$table,$column,$where,$type));
					echo "$app: $table.$column $changed id's changed\n";
				}
			}
		}
		return lang("Total of %1 id's changed.",$total)."\n\n";
	}

	private static function _update_account_id($ids2change,$db,$table,$column,$where=null,$type=null)
	{
		//static $update_sql;
		//static $update_sql_abs;
		$update_sql = $update_sql_abs = '';
		if (is_null($update_sql));
		{
			foreach($ids2change as $from => $to)
			{
				$update_sql .= "WHEN ".$db->quote($from,$db->column_definitions[$column]['type'])." THEN ".$db->quote($to,$db->column_definitions[$column]['type'])." ";
				//echo "#$column->".$db->column_definitions[$column]['type']."#\n";
				if ($to < 0 && $from < 0)
				{
					$update_sql_abs .= 'WHEN '.$db->quote(abs($from),$db->column_definitions[$column]['type']).' THEN '.$db->quote(abs($to),$db->column_definitions[$column]['type']).' ';
				}
			}
			$update_sql .= 'END';
			if ($update_sql_abs) $update_sql_abs .= 'END';
		}
		switch($type)
		{
			case 'comma-sep':
				if (!$where) $where = array();
				$where[] = "$column IS NOT NULL";
				$where[] = "$column != ''";
				$change = array();
				foreach($db->select($table,'DISTINCT '.$column,$where,__LINE__,__FILE__) as $row)
				{
					$ids = explode(',',$old_ids=$row[$column]);
					foreach($ids as $key => $id)
					{
						if (isset($ids2change[$id])) $ids[$key] = $ids2change[$id];
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

			case 'absgroup':
				if (!$update_sql_abs) break;	// no groups to change
				if (!$where) $where = array();
				$where[$column] = array();
				foreach(array_keys($ids2change) as $id)
				{
					if ($id < 0) $where[$column][] = abs($id);
				}
				$db->update($table,$column.'= CASE '.$column.' '.$update_sql_abs,$where,__LINE__,__FILE__);
				$changed = $db->affected_rows();
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

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		$change = array();
		foreach($this->change as $from => $to) $change[] = $from.'->'.$to;

		return lang('Change account_id').': '.implode(', ',$change);
	}
}
