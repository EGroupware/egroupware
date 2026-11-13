<?php
/**
 * EGroupware Filemanager: shares
 *
 * @link http://www.egroupware.org/
 * @package filemanager
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Vfs\Sharing;

/**
 * Filemanager: shares
 */
class filemanager_shares extends filemanager_ui
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
	);

	/**
	 * Autheticated user is setup config user
	 *
	 * @var boolean
	 */
	static protected $is_setup = false;

	static protected $tmp_dir;

	/**
	 * Session key under which get_rows() stores the query the list last ran, for "select all"
	 */
	const SELECT_ALL_QUERY = 'shares-query';

	/**
	 * Constructor
	 */
	function __construct()
	{
		// sudo handling
		parent::__construct();
		self::$is_setup = Api\Cache::getSession('filemanager', 'is_setup');
		self::$tmp_dir = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.tmp/';
	}

	/**
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	public function get_rows(&$query_in, &$rows)
	{
		$query = $query_in;
		// Remember what the list is showing, so "select all" can be expanded to exactly those
		// shares. Not for the nextmatch's own partial queries (a single-row refresh, a check of
		// known rows): those are narrowed to a few ids and would turn the next "select all" into
		// just those rows.
		if (empty($query['csv_export']))
		{
			Api\Cache::setSession('filemanager', self::SELECT_ALL_QUERY,
				array_intersect_key($query, array_flip(['search', 'col_filter', 'admin'])));
		}
		switch ($query['col_filter']['type'])
		{
			case Sharing::LINK:
				$query['col_filter'][] = Api\Db\SqlFragment::raw("share_path LIKE ".$GLOBALS['egw']->db->quote(self::$tmp_dir.'%'));
				break;

			case Sharing::READONLY:
				$query['col_filter'][] = Api\Db\SqlFragment::raw("share_path NOT LIKE ".$GLOBALS['egw']->db->quote(self::$tmp_dir.'%'));
				$query['col_filter']['share_writable'] = false;
				break;

			case Sharing::WRITABLE:
				$query['col_filter']['share_writable'] = true;
				break;
		}
		unset($query['col_filter']['type']);

		if (class_exists('EGroupware\\Collabora\\Wopi'))
		{
			$query['col_filter'][] = Api\Db\SqlFragment::raw('share_writable NOT IN ('.
				EGroupware\Collabora\Wopi::WOPI_WRITABLE.','.EGroupware\Collabora\Wopi::WOPI_READONLY.')');
		}

		if ((string)$query['col_filter']['share_passwd'] !== '')
		{
			$query['col_filter'][] = Api\Db\SqlFragment::raw($query['col_filter']['share_passwd'] === 'yes' ?
				'share_passwd IS NOT NULL' : 'share_passwd IS NULL');
		}
		unset($query['col_filter']['share_passwd']);

		if(!$query['admin'] || !isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$query['col_filter']['share_owner'] = $GLOBALS['egw_info']['user']['account_id'];
		}

		$readonlys = null;
		$total = Sharing::so()->get_rows($query, $rows, $readonlys);

		foreach($rows as &$row)
		{
			if (substr($row['share_path'], 0, strlen(self::$tmp_dir)) === self::$tmp_dir)
			{
				$row['share_path'] = substr($row['share_path'], strlen(self::$tmp_dir));
				$row['type'] = Sharing::LINK;
			}
			else
			{
				$row['type'] = $row['share_writable'] ? Sharing::WRITABLE : Sharing::READONLY;
			}
			$row['share_passwd'] = (bool)$row['share_passwd'];
			if ($row['share_with']) $row['share_with'] = preg_replace('/,([^ ])/', ', $1', $row['share_with']);

			foreach(['share_created','share_last_accessed'] as $date_field)
			{
				$row[$date_field] = Api\DateTime::server2user($row[$date_field]);
			}
		}
		return $total;
	}

	/**
	 * Context menu
	 *
	 * @return array
	 */
	public static function get_actions()
	{
		$group = 1;
		$actions = array(

			'shareLink' => array(
				'caption' => lang('View link'),
				'group' => $group,
				'icon' => 'share',
				'allowOnMultiple' => false,
				'default' => true,
				'onExecute' => 'javaScript:app.filemanager.view_link',
				'disableIfNoEPL' => true
			),
			'shareEdit' => array(
				'caption' => lang('Edit Share'),
				'group' => 1,
				'icon' => 'edit',
				'allowOnMultiple' => false,
				'popup' => '600x200',
				'url' => 'menuaction=stylite.stylite_filemanager.edit_share&share_id=$id',
				'disableIfNoEPL' => true
			),
			'delete' => array(
				'caption' => lang('Delete'),
				'group' => ++$group,
				'confirm' => lang('Delete these shares?'),
				'onExecute' => 'javaScript:app.filemanager.ajax_action',
				'data' => ['menuaction' => 'filemanager.filemanager_shares.ajax_delete'],
			),
		);
		// with "select all" action() expands the selection itself from the list's query, so the
		// client does not have to fetch the ids of all rows first
		return Api\Etemplate\Widget\Nextmatch::flagServerSelectAll($actions, ['javaScript:app.filemanager.ajax_action']);
	}

	/**
	 * Show files shared
	 *
	 * @param ?array $content=null
	 * @param string $msg=''
	 */
	/**
	 * Delete shares via AJAX instead of submitting the whole eTemplate
	 *
	 * Keeps the list standing - its scroll position, selection and row state - instead of
	 * rebuilding it just to drop the deleted rows.
	 *
	 * Deliberately NOT called ajax_action(): this class extends filemanager_ui, whose
	 * ajax_action() is a static VFS-operation endpoint with an entirely different signature -
	 * redeclaring it here is a fatal ("cannot make static method non static") and overriding it
	 * would shadow the inherited VFS one.
	 *
	 * @param string $action only 'delete' is supported
	 * @param string[] $selected share_id's
	 * @param bool $all_selected delete every share matching the current filters
	 */
	public function ajax_delete($exec_id, $action, $selected, $all_selected = false)
	{
		// The context menu calls this directly, so the eTemplate's exec id is the only thing
		// saying the caller had one of our pages open - see Nextmatch::validateExecId()
		if (!Api\Etemplate\Widget\Nextmatch::validateExecId($exec_id))
		{
			return;
		}
		if ($action !== 'delete')
		{
			throw new Api\Exception\WrongParameter("Unknown action '$action'!");
		}
		$msg = lang('%1 shares deleted.', $this->deleteShares((array)$selected, $all_selected));

		Api\Json\Response::get()->call('egw.refresh', $msg, 'filemanager',
			$all_selected ? null : ($selected[0] ?? null),
			$all_selected || count((array)$selected) > 1 ? null : 'delete',
			'filemanager', null, null, 'success');
	}

	/**
	 * Delete the given shares, or with "select all" every share the list is showing
	 *
	 * "Select all" is expanded by re-running the query get_rows() last stored, so it covers
	 * exactly the shares matching the list's search and filters - never the shares the filters
	 * hide, other users' shares in the "my shares" view of an admin, or Collabora's own shares,
	 * which the list does not show at all.
	 *
	 * A non-admin may only delete their own shares, whatever ids are asked for.
	 *
	 * @param string[] $selected share_id's, ignored with $all_selected
	 * @param bool|int $all_selected true, or the number of shares the client's list holds
	 * @return int number of shares deleted
	 * @throws Api\Exception\WrongUserinput with "select all", if the list's query is not known
	 *	or matches a different number of shares than the client's list holds
	 */
	protected function deleteShares(array $selected, $all_selected) : int
	{
		if ($all_selected)
		{
			$query = Api\Cache::getSession('filemanager', self::SELECT_ALL_QUERY);
			if (!is_array($query))
			{
				throw new Api\Exception\WrongUserinput(
					lang('Could not determine the current selection. Nothing was changed, please reload the list and try again.'));
			}
			@set_time_limit(0);
			$query['start'] = 0;
			$query['num_rows'] = -1;
			$query['csv_export'] = true;	// do not store this query as what the list shows
			$rows = [];
			$this->get_rows($query, $rows);
			$selected = array_column($rows, 'share_id');
			// refuse to act on a different number of entries than the list showed the user
			Api\Etemplate\Widget\Nextmatch::checkSelectAllCount($all_selected, count($selected));
		}
		if (!$selected)
		{
			return 0;
		}
		$where = ['share_id' => $selected];
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$where['share_owner'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		return (int)Sharing::delete($where);
	}

	public function index(?array $content=null, $msg = null)
	{
		if (!is_array($content))
		{
			$content = array(
				'nm' => array(
					'get_rows'       =>	'filemanager.filemanager_shares.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter'      => True,	// current dir only
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
					'lettersearch'   => false,	// I  show a lettersearch
					'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,		// IO position in list
					'order'          =>	'share_created',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',	// IO direction of the sort: 'ASC' or 'DESC'
					//'default_cols'   => '!',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'actions'        => self::get_actions(),
					'row_id'         => 'share_id',
					'dataStorePrefix' => 'egw_shares',
				),
			);
		}
		elseif ($content['nm']['action'])
		{
			switch($content['nm']['action'])
			{
				case 'delete':
					Framework::message(lang('%1 shares deleted.',
						$this->deleteShares((array)$content['nm']['selected'], $content['nm']['select_all'])), 'success');
					break;
				default:
					throw new Api\Exception\WrongParameter("Unknown action '{$content['nm']['action']}'!");
			}
			unset($content['nm']['action']);
			unset($content['nm']['id']);
		}
		$content['is_setup'] = self::$is_setup;
		$content['nm']['admin'] = $content['nm']['admin'] || !empty($GLOBALS['egw_info']['user']['apps']['admin']) && $_GET['admin'];
		if($content['nm']['admin'])
		{
			$content['nm']['columnselection_pref'] = "filemanager.shares.rows-admin";
		}

		$sel_options = array(
			'type' => Sharing::$modes,
			'share_passwd' => array(
				'no' => lang('No'),
				'yes' => lang('Yes'),
			)
		);
		unset($sel_options['type'][Sharing::ATTACH]);

		$tpl = new Etemplate('filemanager.shares');
		$tpl->set_dom_id($tpl->name . ($content['nm']['admin'] ? '-admin' : ''));
		$tpl->exec('filemanager.filemanager_shares.index', $content, $sel_options, null, $content);
	}
}