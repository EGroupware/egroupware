<?php
/**
 * EGgroupware admin - access- and session-log
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2009-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
	 * @var so_sql
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
		$this->so = new so_sql(self::APP,self::TABLE,null,'',true);
		$this->so->timestamps = array('li', 'lo', 'session_dla', 'notification_hartbeat');
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$heartbeat_limit = egw_session::heartbeat_limit();

		if ($query['session_list'])	// filter active sessions
		{
			$query['col_filter']['lo'] = null;	// not logged out
			$query['col_filter'][0] = 'session_dla > '.(int)(time() - $GLOBALS['egw_info']['server']['sessions_timeout']);
			$query['col_filter'][1] = "(notification_heartbeat IS NULL OR notification_heartbeat > $heartbeat_limit)";
		}
		$total = $this->so->get_rows($query,$rows,$readonlys);

		$no_kill = !$GLOBALS['egw']->acl->check('current_sessions_access',8,'admin') && !$query['session_list'];

		foreach($rows as &$row)
		{
			$row['sessionstatus'] = lang('success');
			if ($row['notification_heartbeat'] > $heartbeat_limit)
			{
				$row['sessionstatus'] = lang('active');
			}
			if (stripos($row['session_php'],'blocked') !== false ||
				stripos($row['session_php'],'bad login') !== false ||
				strpos($row['sessioin_php'],' ') !== false)
			{
				$row['sessionstatus'] = $row['session_php'];
			}
			if ($row['lo']) {
				$row['total'] = ($row['lo'] - $row['li']) / 60;
				$row['sessionstatus'] = lang('logged out');
			}
			// eg. for bad login or password
			if (!$row['account_id']) $row['alt_loginid'] = $row['loginid'];

			$readonlys['kill['.$row['sessionid'].']'] = $no_kill;
			$readonlys['delete['.$row['sessionid'].']'] = $query['session_list'];

			// do not allow to kill or select own session
			if ($GLOBALS['egw']->session->sessionid_access_log == $row['sessionid'] && $query['session_list'])
			{
				$readonlys['kill['.$row['sessionid'].']'] = $readonlys['selected['.$row['sessionid'].']'] = true;
			}
			// do not allow to delete access log off active sessions
			if (!$row['lo'] && $row['session_dla'] > time()-$GLOBALS['egw_info']['server']['sessions_timeout'] && !$query['session_list'])
			{
				$readonlys['delete['.$row['sessionid'].']'] = $readonlys['selected['.$row['sessionid'].']'] = true;
			}
			unset($row['session_php']);	// for security reasons, do NOT give real PHP sessionid to UI
		}
		if ($query['session_list'])
		{
			$rows['no_total'] = $rows['no_lo'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.
			($query['session_list'] ? lang('View sessions') : lang('View Access Log')).
			($query['col_filter']['account_id'] ? ': '.common::grab_owner_name($query['col_filter']['account_id']) : '');

		return $total;
	}

	/**
	 * Display the access log or session list
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 * @param boolean $sessions_list=false
	 */
	function index(array $content=null, $msg='', $sessions_list=false)
	{
		//_debug_array($content);
		if (is_array($content)) $sessions_list = $content['nm']['session_list'];

		// check if user has access to requested functionality
		if ($GLOBALS['egw']->acl->check($sessions_list ? 'current_sessions_access' : 'access_log_access',1,'admin'))
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
				//'default_cols'   => 	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
				'csv_fields'     =>	false,	// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
								//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
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
		elseif(isset($content['nm']['rows']['delete']) || isset($content['delete']))
		{
			if (isset($content['nm']['rows']['delete']))
			{
				list($sessionid) = each($content['nm']['rows']['delete']);
				unset($content['nm']['rows']['delete']);
			}
			else
			{
				unset($content['delete']);
				$sessionid = $content['nm']['rows']['selected'];
			}
			if ($sessionid && $this->so->delete(array('sessionid' => $sessionid)))
			{
				$msg = lang('%1 log entries deleted.',1);
			}
			else
			{
				$msg = lang('Error deleting log entry!');
			}
		}
		elseif(isset($content['nm']['rows']['kill']) || isset($content['kill']))
		{
			if (isset($content['nm']['rows']['kill']))
			{
				list($sessionid) = each($content['nm']['rows']['kill']);
				$sessionid = array($sessionid);
				unset($content['nm']['rows']['kill']);
			}
			else
			{
				unset($content['kill']);
				$sessionid = $content['nm']['rows']['selected'];
			}
			if (($key = array_search($GLOBALS['egw']->session->sessionid_access_log, $sessionid)))
			{
				unset($sessionid[$key]);	// dont allow to kill own sessions
			}
			if ($GLOBALS['egw']->acl->check('current_sessions_access',8,'admin'))
			{
				$msg = lang('Permission denied!');
			}
			else
			{
				foreach((array)$sessionid as $id)
				{
					$GLOBALS['egw']->session->destroy($id);
				}
				$msg = lang('%1 sessions killed',count($sessionid));
			}
		}
		$readonlys['kill'] = !$sessions_list;
		$readonlys['delete'] = $sessions_list;

		$content['msg'] = $msg;
		$content['percent'] = 100.0 * $GLOBALS['egw']->db->query(
			'SELECT ((SELECT COUNT(*) FROM '.self::TABLE.' WHERE lo != 0) / COUNT(*)) FROM '.self::TABLE,
			__LINE__,__FILE__)->fetchColumn();

		$tmpl = new etemplate('admin.accesslog');
		$tmpl->exec('admin.admin_accesslog.index',$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}

	/**
	 * Display session list
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function sessions(array $content=null, $msg='')
	{
		return $this->index(null,$msg,true);
	}
}
