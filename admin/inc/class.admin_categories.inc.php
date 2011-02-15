<?php
/**
 * EGgroupware admin - Edit global categories
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2010 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Edit global categories
 */
class admin_categories
{
	/**
	 * Which methods of this class can be called as menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'edit'  => true,
	);

	/**
	 * Path where the icons are stored (relative to webserver_url)
	 */
	const ICON_PATH = '/phpgwapi/images';

	/**
	 * Stupid old admin ACL - dont think anybody uses or understands it ;-)
	 *
	 * @var boolean
	 */
	private static $acl_search;
	private static $acl_add;
	private static $acl_view;
	private static $acl_edit;
	private static $acl_delete;
	private static $acl_add_sub;

	/**
	 * Constructor
	 */
	function __construct()
	{
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			throw new egw_exception_no_permission_admin();
		}
		if ($GLOBALS['egw']->acl->check('global_categories_access',1,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		self::init_static();
	}

	/**
	 * Init static vars (static constructor)
	 */
	public static function init_static()
	{
		if (is_null(self::$acl_search))
		{
			self::$acl_search = !$GLOBALS['egw']->acl->check('global_categories_access',2,'admin');
			self::$acl_add    = !$GLOBALS['egw']->acl->check('global_categories_access',4,'admin');
			self::$acl_view   = !$GLOBALS['egw']->acl->check('global_categories_access',8,'admin');
			self::$acl_edit   = !$GLOBALS['egw']->acl->check('global_categories_access',16,'admin');
			self::$acl_delete = !$GLOBALS['egw']->acl->check('global_categories_access',32,'admin');
			self::$acl_add_sub= !$GLOBALS['egw']->acl->check('global_categories_access',64,'admin');
		}
	}

	/**
	 * Edit / add a category
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public function edit(array $content=null,$msg='')
	{
		// read the session, as the global_cats param is stored with it.
		$session = egw_cache::getSession(__CLASS__,'nm');
		$global_cats = $session['global_cats'];
		unset($session);

		if (!isset($content))
		{
			if (!(isset($_GET['cat_id']) && $_GET['cat_id'] > 0 &&
				($content = categories::read($_GET['cat_id']))))
			{
				$content = array('data' => array());
				if(isset($_GET['parent']) && $_GET['parent'] > 0)
				{
					$content['parent'] = (int)$_GET['parent'];
				}
				if (isset($_GET['appname']) && isset($GLOBALS['egw_info']['apps'][$_GET['appname']]))
				{
					$content['appname'] = $_GET['appname'];
				}
				else
				{
					$content['appname'] = categories::GLOBAL_APPNAME;
				}
			}
			elseif (!self::$acl_edit)
			{
				// only allow to view category
				$readonlys['__ALL__'] = true;
				$readonlys['button[cancel]'] = false;
			}
			$content['base_url'] = self::icon_url();
			$content['icon_url'] = $content['base_url'] . $content['data']['icon'];
		}
		elseif ($content['button'] || $content['delete'])
		{
			$cats = new categories(categories::GLOBAL_ACCOUNT,$content['appname']);

			if ($content['delete']['delete'])
			{
				$button = 'delete';
				$delete_subs = $content['delete']['subs'];
			}
			else
			{
				list($button) = each($content['button']);
				unset($content['button']);
			}
			unset($content['delete']);

			switch($button)
			{
				case 'save':
				case 'apply':
					if ($content['id'] && self::$acl_edit)
					{
						$cats->edit($content);
						$msg = lang('Category saved.');
					}
					elseif (!$content['id'] && (
						$content['parent'] && self::$acl_add_sub ||
						!$content['parent'] && self::$acl_add))
					{
						$content['id'] = $cats->add($content);
						$msg = lang('Category saved.');
					}
					else
					{
						$msg = lang('Permission denied!');
						unset($button);
					}
					break;

				case 'delete':
					if (self::$acl_delete)
					{
						$cats->delete($content['id'],$delete_subs,!$delete_subs);
						$msg = lang('Category deleted.');
					}
					else
					{
						$msg = lang('Permission denied!');
						unset($button);
					}
					break;
			}
			$link = egw::link('/index.php',array(
				'menuaction' => 'admin.admin_categories.index',
				'msg' => $msg,
				'global_cats' => (empty($global_cats)? false : true),
			));
			$js = "window.opener.location='$link';";
			if ($button == 'save' || $button == 'delete')
			{
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				common::egw_exit();
			}
			if (!empty($js)) $GLOBALS['egw']->js->set_onload($js);
		}
		$content['msg'] = $msg;
		$sel_options['icon'] = self::get_icons();

		$readonlys['button[delete]'] = !$content['id'] || !self::$acl_delete;	// cant delete not yet saved category

		$tmpl = new etemplate('admin.categories.edit');
		$tmpl->exec('admin.admin_categories.edit',$content,$sel_options,$readonlys,$content+array(
			'old_parent' => $content['old_parent'] ? $content['old_parent'] : $content['parent'],
		),2);
	}

	/**
	 * Return URL of an icon, or base url with trailing slash
	 *
	 * @param string $icon='' filename
	 * @return string url
	 */
	static function icon_url($icon='')
	{
		return $GLOBALS['egw_info']['server']['webserver_url'].self::ICON_PATH.'/'.$icon;
	}

	/**
	 * Return icons from /phpgwapi/images
	 *
	 * @return array filename => label
	 */
	static function get_icons()
	{
		$dir = dir(EGW_SERVER_ROOT.self::ICON_PATH);
		$icons = array();
		while(($file = $dir->read()))
		{
			if (preg_match('/^(.*)\\.(png|gif|jpe?g)$/i',$file,$matches))
			{
				$icons[$file] = ucfirst($matches[1]);
			}
		}
		$dir->close();
		asort($icons);

		return $icons;
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	static function get_rows($query,&$rows,&$readonlys)
	{
		self::init_static();

		if (!isset($query['appname']))
		{
			throw new egw_exception_assertion_failed(__METHOD__.'($query,...) $query[appname] NOT set!');
		}
		$globalcat = true;
		if (isset($query['global_cats']) && $query['global_cats']===false)
		{
			$globalcat = false;
		}
		egw_cache::setSession(__CLASS__,'nm',$query);

		$cats = new categories(categories::GLOBAL_ACCOUNT,$query['appname']);
		$rows = $cats->return_sorted_array($query['start'],$query['num_rows'],$query['search'],$query['sort'],$query['order'],$globalcat,0,true);

		foreach($rows as &$row)
		{
			$row['level_spacer'] = str_repeat('&nbsp; &nbsp; ',$row['level']);

			if ($row['data']['icon']) $row['icon_url'] = self::icon_url($row['data']['icon']);

			$row['subs'] = count($row['children']);

			$row['class'] = 'level'.$row['level'];

			$readonlys["edit[$row[id]]"]   = !self::$acl_edit;
			$readonlys["add[$row[id]]"]    = !self::$acl_add_sub;
			$readonlys["delete[$row[id]]"] = !self::$acl_delete;
		}
		// make appname available for actions
		$rows['appname'] = $query['appname'];

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Global categories').
			($query['appname'] != categories::GLOBAL_APPNAME ? ': '.lang($query['appname']) : '');

		return $cats->total_records;
	}

	/**
	 * Display the accesslog
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public function index(array $content=null,$msg='')
	{
		//_debug_array($content);

		if(!isset($content))
		{
			if (isset($_GET['msg'])) $msg = $_GET['msg'];

			$content['nm'] = egw_cache::getSession(__CLASS__,'nm');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	'admin_categories::get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter'      => True,	// I  disable the 1. filter
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
					'header_left'    =>	false,	// I  template to show left of the range-value, left-aligned (optional)
					'header_right'   =>	false,	// I  template to show right of the range-value, right-aligned (optional)
					'never_hide'     => True,	// I  never hide the nextmatch-line if less then maxmatch entries
					'lettersearch'   => false,	// I  show a lettersearch
					'start'          =>	0,		// IO position in list
					'order'          =>	'name',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'default_cols'   => '!color,last_mod,subs',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false,	// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'appname'        => categories::GLOBAL_APPNAME,
					'no_search'      => !self::$acl_search,
				);
			}
			else
			{
				$content['nm']['start']=0;
			}
			if (isset($_GET['appname']) && ($_GET['appname'] == categories::GLOBAL_APPNAME ||
				isset($GLOBALS['egw_info']['apps'][$_GET['appname']])))
			{
				$content['nm']['appname'] = $_GET['appname'];
			}
			$content['nm']['global_cats'] = true;
			if (isset($_GET['global_cats']) && empty($_GET['global_cats'] ))
			{
				$content['nm']['global_cats'] = false;
			}
		}
		else
		{
			if($content['delete']['delete'] && ($cat = categories::read($content['delete']['cat_id'])))
			{
				$cats = new categories(categories::GLOBAL_ACCOUNT,$cat['appname']);
				$cats->delete($content['delete']['cat_id'],$content['delete']['subs'],!$content['delete']['subs']);
				$msg = lang('Category deleted.');
			}
			unset($content['delete']);
		}
		$content['msg'] = $msg;
		$readonlys['add'] = !self::$acl_add;

		$tmpl = new etemplate('admin.categories.index');
		$tmpl->exec('admin.admin_categories.index',$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}
}

admin_categories::init_static();
