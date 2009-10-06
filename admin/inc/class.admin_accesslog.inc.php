<?php
/**
 * eGgroupWare admin - accesslog
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2009 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Show eGroupware access log
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
		if ($GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		$this->so = new so_sql(self::APP,self::TABLE,null,'',true);
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
		$total = $this->so->get_rows($query,$rows,$readonlys);

		foreach($rows as &$row)
		{
			$row['sessionstatus'] = lang('success');
			if (stripos($row['sessionid'],'blocked') !== False || stripos($row['sessionid'],'bad login') !== False)
			{
				$row['sessionstatus'] = $row['sessionid'];
			}
			if ($row['lo']) {
				$row['total'] = ($row['lo'] - $row['li']) / 60;
				$row['sessionstatus'] = lang('logged out');
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('View Access Log').
			($query['col_filter']['account_id'] ? ': '.common::grab_owner_name($query['col_filter']['account_id']) : '');

		return $total;
	}

	/**
	 * Display the accesslog
	 *
	 * @param array $content
	 */
	function index(array $content=null)
	{
		//_debug_array($content);

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
		}
		elseif(isset($content['nm']['rows']['delete']))
		{
			list($sessionid) = each($content['nm']['rows']['delete']);
			unset($content['nm']['rows']['delete']);
			if ($sessionid && $this->so->delete(array('sessionid' => $sessionid)))
			{
				$content['msg'] = lang('%1 log entries deleted.',1);
			}
			else
			{
				$content['msg'] = lang('Error deleting log entry!');
			}
		}
		elseif(isset($content['delete']))
		{
			unset($content['delete']);
			if (($deleted = $this->so->delete(array('sessionid' => $content['nm']['rows']['selected']))))
			{
				$content['msg'] = lang('%1 log entries deleted.',$deleted);
			}
			else
			{
				$content['msg'] = lang('Error deleting log entry!');
			}
		}
		$content['percent'] = 100.0 * $GLOBALS['egw']->db->query(
			'SELECT ((SELECT COUNT(*) FROM '.self::TABLE.' WHERE lo != 0) / COUNT(*)) FROM '.self::TABLE,
			__LINE__,__FILE__)->fetchColumn();

		$tmpl = new etemplate('admin.accesslog');
		$tmpl->exec('admin.admin_accesslog.index',$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}
}
