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
		switch ($query['col_filter']['type'])
		{
			case Sharing::LINK:
				$query['col_filter'][] = "share_path LIKE ".$GLOBALS['egw']->db->quote(self::$tmp_dir.'%');
				break;

			case Sharing::READONLY:
				$query['col_filter'][] = "share_path NOT LIKE ".$GLOBALS['egw']->db->quote(self::$tmp_dir.'%');
				$query['col_filter']['share_writable'] = false;
				break;

			case Sharing::WRITABLE:
				$query['col_filter']['share_writable'] = true;
				break;
		}
		unset($query['col_filter']['type']);

		if (class_exists('EGroupware\\Collabora\\Wopi'))
		{
			$query['col_filter'][] = 'share_writable NOT IN ('.
				EGroupware\Collabora\Wopi::WOPI_WRITABLE.','.EGroupware\Collabora\Wopi::WOPI_READONLY.')';
		}

		if ((string)$query['col_filter']['share_passwd'] !== '')
		{
			$query['col_filter'][] = $query['col_filter']['share_passwd'] === 'yes' ?
				'share_passwd IS NOT NULL' : 'share_passwd IS NULL';
		}
		unset($query['col_filter']['share_passwd']);

		$query['col_filter']['share_owner'] = $GLOBALS['egw_info']['user']['account_id'];

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
			$row['share_passwd'] = (boolean)$row['share_passwd'];
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
				'popup' => '500x200',
				'url' => 'menuaction=stylite.stylite_filemanager.edit_share&share_id=$id',
				'disableIfNoEPL' => true
			),
			'delete' => array(
				'caption' => lang('Delete'),
				'group' => ++$group,
				'confirm' => 'Delete these shares?',
			),
		);
		return $actions;
	}

	/**
	 * Show files shared
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public function index(array $content=null, $msg = null)
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
					$where = array('share_owner' => $GLOBALS['egw_info']['user']['account_id']);
					if (!$content['nm']['select_all'])
					{
						$where['share_id'] = $content['nm']['selected'];
					}
					Framework::message(lang('%1 shares deleted.', Sharing::delete($where)), 'success');
					break;
				default:
					throw new Api\Exception\WrongParameter("Unknown action '{$content['nm']['action']}'!");
			}
			unset($content['nm']['action']);
			unset($content['nm']['id']);
		}
		$content['is_setup'] = self::$is_setup;

		$sel_options = array(
			'type' => Sharing::$modes,
			'share_passwd' => array(
				'no' => lang('No'),
				'yes' => lang('Yes'),
			)
		);
		unset($sel_options['type'][Sharing::ATTACH]);

		$tpl = new Etemplate('filemanager.shares');
		$tpl->exec('filemanager.filemanager_shares.index', $content, $sel_options, null, $content);
	}
}