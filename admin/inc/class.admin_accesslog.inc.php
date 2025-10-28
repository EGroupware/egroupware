<?php
/**
 * EGroupware admin - access- and session-log
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2009-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * Show EGroupware access- and session-log
 */
class admin_accesslog
{
	/**
	 * Which methods of this class can be called as menuation
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'sessions' => true,
	);

	/**
	 * Our storage object
	 *
	 * @var Api\Storage\Base
	 */
	protected $so;

	/**
	 * Name of our table
	 */
	const TABLE = 'egw_access_log';
	/**
	 * Name of app the table is registered
	 */
	const APP = 'phpgwapi';

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		$this->so = new Api\Storage\Base(self::APP,self::TABLE,null,'',true);
		$this->so->timestamps = array('li', 'lo', 'session_dla', 'notification_heartbeat');
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter' and
	 *	'session_list' true: all sessions, false: whole access-log, 'active': only sessions with session-status active (browser, no sync)
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$heartbeat_limit = Api\Session::heartbeat_limit();

		if ($query['session_list'])	// filter active sessions
		{
			$query['col_filter']['lo'] = null;	// not logged out
			$query['col_filter'][0] = 'session_dla > '.(int)(time() - $GLOBALS['egw_info']['server']['sessions_timeout']);
			// for push via fallback (no native push) we use the heartbeat (constant polling of notification app)
			if (Api\Json\Push::onlyFallback())
			{
				$active_query = "notification_heartbeat > $heartbeat_limit";
			}
			else
			{
				// for native push we ask the push-server who is active
				$online = (array)Api\Json\Push::online();
				$active_query = $GLOBALS['egw']->db->expression(self::TABLE, ['account_id' => $online]);
			}
			switch((string)$query['session_list'])
			{
				case 'active':	// remove status != 'active', eg. CalDAV/eSync
					$query['col_filter'][1] = $active_query;
					$query['col_filter'][3] = "session_php NOT LIKE '% %'";	// remove blocked, bad login, etc
					break;
				default:
					$query['col_filter'][1] = "(notification_heartbeat IS NULL OR $active_query)";
					break;
			}
			$query['col_filter'][2] = 'account_id>0';
		}
		$total = $this->so->get_rows($query,$rows,$readonlys);

		$heartbeat_limit_user = Api\DateTime::server2user($heartbeat_limit, 'ts');

		foreach($rows as &$row)
		{
			$row['sessionstatus'] = 'success';
			if (isset($online) ?
				// we still need to check notification_heartbeat to distinguish from non-interactive session like *DAV
				isset($row['notification_heartbeat']) && in_array($row['account_id'], $online) :
				$row['notification_heartbeat'] > $heartbeat_limit_user)
			{
				$row['sessionstatus'] = 'active';
			}
			if (stripos($row['session_php'],'blocked') !== false ||
				stripos($row['session_php'],'bad login') !== false ||
				strpos($row['session_php'],' ') !== false)
			{
				$row['sessionstatus'] = $row['session_php'];
			}
			if ($row['lo']) {
				$row['total'] = ($row['lo'] - $row['li']) / 60;
				$row['sessionstatus'] = 'logged out';
			}
			// eg. for bad login or password
			if (!$row['account_id']) $row['alt_loginid'] = ($row['loginid']?$row['loginid']:lang('none'));

			// do not allow to kill or select own session
			if ($GLOBALS['egw']->session->sessionid_access_log == $row['sessionid'] && $query['session_list'])
			{
				$row['class'] .= ' rowNoDelete ';
			}
			// do not allow to delete access log off active sessions
			if (!$row['lo'] && $row['session_dla'] > time()-$GLOBALS['egw_info']['server']['sessions_timeout'] &&
				in_array($row['sessionstatus'], array('active', 'success')) && !$query['session_list'])
			{
				$row['class'] .= ' rowNoDelete ';
			}
			$row['sessionstatus'] = lang($row['sessionstatus']);
			unset($row['session_php']);	// for security reasons, do NOT give real PHP sessionid to UI

			$row['os_browser'] = Api\Header\UserAgent::osBrowser($row['user_agent']);
		}
		if ($query['session_list'])
		{
			$rows['no_total'] = $rows['no_lo'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.
			($query['session_list'] ? lang('View sessions') : lang('View Access Log')).
			($query['col_filter']['account_id'] ? ': '.Api\Accounts::username($query['col_filter']['account_id']) : '');

		return $total;
	}

	/**
	 * Display the access log or session list
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 * @param boolean $sessions_list =false
	 */
	function index(?array $content=null, $msg='', $sessions_list=false)
	{

		if (is_array($content)) $sessions_list = $content['nm']['session_list'];

		// check if user has access to requested functionality
		if ($GLOBALS['egw']->acl->check($sessions_list ? 'current_sessions' : 'access_log_acces',1,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}

		if(!isset($content))
		{
			$content['nm'] = array(
				'get_rows'       =>	'admin.admin_accesslog.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
				'no_filter'      => True,	// I  disable the 1. filter
				'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,	// I  disable the cat-selectbox
				'header_left'    =>	false,	// I  template to show left of the range-value, left-aligned (optional)
				'header_right'   =>	false,	// I  template to show right of the range-value, right-aligned (optional)
				'never_hide'     => True,	// I  never hide the nextmatch-line if less then maxmatch entries
				'lettersearch'   => false,	// I  show a lettersearch
				'start'          =>	0,		// IO position in list
				'order'          =>	'li',	// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',	// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'   => '!session_action',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
				'csv_fields'     =>	false,	// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
								//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
				'actions'		=> $this->get_actions($sessions_list),
				'placeholder_actions' => false,
				'row_id'			=> 'sessionid',
			);
			if ((int)$_GET['account_id'])
			{
				$content['nm']['col_filter']['account_id'] = (int)$_GET['account_id'];
			}
			if ($sessions_list)
			{
				$content['nm']['order'] = 'session_dla';
				$content['nm']['options-selectcols'] = array(
					'lo' => false,
					'total' => false,
				);
			}
			$content['nm']['session_list'] = $sessions_list;
		}
		//error_log(__METHOD__. ' accesslog =>' . array2string($content['nm']['selected']));
		if ($content['nm']['action'])
		{
			if ($content['nm']['select_all'])
			{
				// get the whole selection
				$query = array(
					'search' => $content['nm']['search'],
					'col_filter' => $content['nm']['col_filter']
				);

				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$all = $readonlys = array();
				$this->get_rows($query,$all,$readonlys);
				$content['nm']['selected'] = array();
				foreach($all as $session)
				{
					$content['nm']['selected'][] = $session[$content['nm']['row_id']];
				}
			}
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
			}
			else
			{
				$success = $failed = $action = $action_msg = null;
				if ($this->action($content['nm']['action'],$content['nm']['selected']
					,$success,$failed,$action_msg,$msg))
				{ // In case of action success
					switch ($action_msg)
					{
						case'deleted':
							$msg = lang('%1 log entries deleted.',$success);
							break;
						case'killed':
							$msg = lang('%1 sessions killed',$success);
					}
				}
				elseif($failed) // In case of action failiure
				{
					switch ($action_msg)
					{
						case'deleted':
							$msg = lang('Error deleting log entry!');
							break;
						case'killed':
							$msg = lang('Permission denied!');
					}
				}
			}
		}

		$content['msg'] = $msg;
		$content['percent'] = 100.0 * $GLOBALS['egw']->db->query(
			'SELECT ((SELECT COUNT(*) FROM '.self::TABLE.' WHERE lo != 0) / COUNT(*)) FROM '.self::TABLE,
			__LINE__,__FILE__)->fetchColumn();

		$tmpl = new Etemplate('admin.accesslog');
		$tmpl->exec('admin.admin_accesslog.index', $content, array(), $readonlys, array(
			'nm' => $content['nm'],
		));
	}

	/**
	 * Apply an action to multiple logs
	 *
	 * @param type $action
	 * @param type $checked
	 * @param type $use_all
	 * @param type $success
	 * @param int $failed
	 * @param type $action_msg
	 * @return type number of failed
	 */
	function action($action,$checked,&$success,&$failed,&$action_msg)
	{
		$success = $failed = 0;
		//error_log(__METHOD__.'selected:' . array2string($checked). 'action:' . $action);
		switch ($action)
		{
			case "delete":
				$action_msg = "deleted";
				$del_msg= $this->so->delete(array('sessionid' => $checked));
				if ($checked && $del_msg)
				{
					$success = $del_msg;
				}
				else
				{
					$failed ++;
				}
				break;
			case "kill":
				$action_msg = "killed";
				$sessionid = $checked;
				if (($key = array_search($GLOBALS['egw']->session->sessionid_access_log, $sessionid)))
				{
						unset($sessionid[$key]);	// dont allow to kill own sessions
				}
				if ($GLOBALS['egw']->acl->check('current_sessions',8,'admin'))
				{
					$failed ++;
				}
				else
				{
					foreach((array)$sessionid as $id)
					{
						$GLOBALS['egw']->session->destroy($id);
					}
					$success= count($sessionid);
				}
				break;
		}
		return !$failed;
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private static function get_actions($sessions_list)
	{
		$group = 0;
		if ($sessions_list)
		{
		//	error_log(__METHOD__. $sessions_list);
			$actions= array(
				'kill' => array(
					'caption' => 'Kill',
					'confirm' => 'Kill this session',
					'confirm_multiple' => 'Kill these sessions',
					'group' => $group,
					'disableClass' => 'rowNoDelete',
				),
			);

		}
		else
		{
			$actions= array(
				'delete' => array(
					'caption' => 'Delete',
					'confirm' => 'Delete this entry',
					'confirm_multiple' => 'Delete these entries',
					'group' => $group,
					'disableClass' => 'rowNoDelete',
				),
			);
		}
		// Automatic select all doesn't work with only 1 action
		$actions['select_all'] = array(
			'caption' => 'Select all',
			//'checkbox' => true,
			'hint' => 'Select all entries',
			'enabled' => true,
			'shortcut' => array(
				'keyCode'	=>	65, // A
				'ctrl'		=>	true,
				'caption'	=> lang('Ctrl').'+A'
			),
			'group' => $group++,
		);
		return $actions;
	}

	/**
	 * Display session list
	 *
	 * @param ?array $content =null
	 * @param string $msg =''
	 */
	function sessions(?array $content=null, $msg='')
	{
		return $this->index($content, $msg, true);
	}
}