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

	protected $appname = 'admin';
	protected $get_rows = 'admin.admin_categories.get_rows';
	protected $list_link = 'admin.admin_categories.index';
	protected $add_link = 'admin.admin_categories.edit';
	protected $edit_link = 'admin.admin_categories.edit';

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
		$appname = $content['appname'] ? $content['appname'] : ($_GET['appname']?$_GET['appname']:categories::GLOBAL_APPNAME);
		$session = egw_cache::getSession(__CLASS__.$appname,'nm');
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
					$appname = $_GET['appname'];
				}
				else
				{
					$appname = categories::GLOBAL_APPNAME;
				}
			}
			elseif ($content['appname'] != $appname || !self::$acl_edit || ( $content['owner'] != $GLOBALS['egw_info']['user']['account_id'] && $this->appname != 'admin'))
			{
				// only allow to view category
				$readonlys['__ALL__'] = true;
				$readonlys['button[cancel]'] = false;
			}
			$content['base_url'] = self::icon_url();
		}
		elseif ($content['button'] || $content['delete'])
		{
			$cats = new categories($content['owner'] ? $content['owner'] : categories::GLOBAL_ACCOUNT,$content['appname']);

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
					if($content['owner'] == '') $content['owner'] = 0;
					if ($content['id'] && self::$acl_edit)
					{
						try {
							$cats->edit($content);
							$msg = lang('Category saved.');
						}
						catch (egw_exception_wrong_userinput $e)
						{
							$msg = lang('Unwilling to save category with current settings. Check for inconsistency:').$e->getMessage();	// display conflicts etc.
						}
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
				'menuaction' => $this->list_link,
				'appname' => $appname,
				'msg' => $msg,
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
		if(!$content['appname']) $content['appname'] = $appname;
		$content['icon_url'] = $content['base_url'] . $content['data']['icon'];

		$sel_options['icon'] = self::get_icons();
		$sel_options['owner'] = array();

		// User's category - add current value to be able to preserve owner
		if(!$content['id'])
		{
			if($this->appname != 'admin')
			{
				$content['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			}
			else
			{
				$content['owner'] = 0;
			}
		}

		if($this->appname != 'admin' && $content['owner'] > 0 )
		{
			$sel_options['owner'][$content['owner']] = common::grab_owner_name($content['owner']);
		}
		// Add 'All users', in case owner is readonlys
		if($content['id'] && $content['owner'] == 0)
		{
			$sel_options['owner'][0] = lang('All users');
		}
		if($this->appname == 'admin' || ($content['id'] && !((int)$content['owner'] > 0)))
		{
			if($content['owner'] > 0)
			{
				$content['msg'] .= "\n".lang('owner "%1" removed, please select group-owner', common::grab_owner_name($content['owner']));
				$content['owner'] = 0;
			}
			$sel_options['owner'][0] = lang('All users');
			$accs = $GLOBALS['egw']->accounts->get_list('groups');
			foreach($accs as $acc)
			{
				if ($acc['account_type'] == 'g')
				{
					$sel_options['owner'][$acc['account_id']] = ExecMethod2('etemplate.select_widget.accountInfo',$acc['account_id'],$acc,$type2,$type=='both');
				}
			}
			$content['no_private'] = true;
		}

		if($this->appname == 'admin')
		{
			$content['access'] = 'public';
			$readonlys['owner'] = false;
		} else {
			$readonlys['owner'] = true;
			$readonlys['access'] = $content['owner'] != $GLOBALS['egw_info']['user']['account_id'];
		}

		egw_framework::validate_file('.','global_categories','admin');
		egw_framework::set_onload('$j(document).ready(function() {
			cat_original_owner = [' . ($content['owner'] ? $content['owner'] : ($content['id'] ? '0' : '')) .'];
			permission_prompt = \'' . lang('Removing access for groups may cause problems for data in this category.  Are you sure?  Users in these groups may no longer have access:').'\';
		});');

		$readonlys['button[delete]'] = !$content['id'] || !self::$acl_delete ||		// cant delete not yet saved category
			$appname != $content['appname'] || // Can't edit a category from a different app
			 ($this->appname != 'admin' && $content['owner'] != $GLOBALS['egw_info']['user']['account_id']);

		$tmpl = new etemplate('admin.categories.edit');
		$tmpl->exec($this->edit_link,$content,$sel_options,$readonlys,$content+array(
			'old_parent' => $content['old_parent'] ? $content['old_parent'] : $content['parent'], 'appname' => $appname
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
	public function get_rows(&$query,&$rows,&$readonlys)
	{
		self::init_static();

		$filter = array();
		$globalcat = ($query['filter'] === categories::GLOBAL_ACCOUNT || !$query['filter']);
		if (isset($query['global_cats']) && $query['global_cats']===false)
		{
			$globalcat = false;
		}
		if ($globalcat && $query['filter']) $filter['access'] = 'public';
		// new column-filter access has highest priority
		if (!empty($query['col_filter']['access']))$filter['access'] = $query['col_filter']['access'];

		egw_cache::setSession(__CLASS__.$query['appname'],'nm',$query);

		if($query['filter'] > 0 || $query['col_filter']['owner'])
		{
			$owner = $query['col_filter']['owner'] ? $query['col_filter']['owner'] : $query['filter'];
		}
		if($query['col_filter']['app'])
		{
			$filter['appname'] = $query['col_filter']['app'];
		}
		$cats = new categories($filter['owner'],$query['appname']);
		$globalcat = isset($GLOBALS['egw_info']['user']['apps']['admin']) ? 'all_no_acl' : $globalcat;	// ignore acl only for admins
		$rows = $cats->return_sorted_array($query['start'],false,$query['search'],$query['sort'],$query['order'],$globalcat,$parent=0,true,$filter);
		$count = $cats->total_records;
		foreach($rows as $key => &$row)
		{
			$row['owner'] = explode(',',$row['owner']);
			if(($owner && !in_array($owner, $row['owner'])) || ((string)$query['filter'] === (string)categories::GLOBAL_ACCOUNT && $row['owner'][0] > 0))
			{
				unset($rows[$key]);
				$count--;
				continue;
			}

			$row['level_spacer'] = str_repeat('&nbsp; &nbsp; ',$row['level']);

			if ($row['data']['icon']) $row['icon_url'] = self::icon_url($row['data']['icon']);

			$row['subs'] = count($row['children']);

			$row['class'] = 'level'.$row['level'];
			if($row['owner'] > 0 && !$GLOBALS['egw_info']['user']['apps']['admin'] && $row['owner'] != $GLOBALS['egw_info']['user']['account_id'])
			{
				$row['class'] .= ' rowNoEdit rowNoDelete ';
			}
			// Can only edit (via context menu) categories for the selected app (backend restriction)
			if($row['appname'] != $query['appname'])
			{
				$row['class'] .= ' rowNoEdit ';
			}
			$readonlys["edit[$row[id]]"]   = !self::$acl_edit;
			$readonlys["add[$row[id]]"]    = !self::$acl_add_sub;
			$readonlys["delete[$row[id]]"] = !self::$acl_delete;
		}
		$rows = $count <= $query['num_rows'] ? array_values($rows) : array_slice($rows, $query['start'], $query['num_rows']);
		// make appname available for actions
		$rows['appname'] = $query['appname'];
		$rows['edit_link'] = $this->edit_link;

		// disable access column for global categories
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'admin') $rows['no_access'] = true;

		$GLOBALS['egw_info']['flags']['app_header'] = lang($this->appname).' - '.lang('categories').
			($query['appname'] != categories::GLOBAL_APPNAME ? ': '.lang($query['appname']) : '');

		return $count;
	}

	/**
	 * Display the accesslog
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public function index(array $content=null,$msg='')
	{
		//_debug_array($_GET);
		if ($this->appname != 'admin') translation::add_app('admin');	// need admin translations

		if(!isset($content))
		{
			if (isset($_GET['msg'])) $msg = $_GET['msg'];

			$appname = categories::GLOBAL_APPNAME;
			foreach(array($content['nm']['appname'], $_GET['cats_app'], $_GET['appname']) as $field)
			{
				if($field)
				{
					$appname = $field;
					break;
				}
			}
			$content['nm'] = egw_cache::getSession(__CLASS__.$appname,'nm');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	$this->get_rows,	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'options-filter' => array(
						'' => lang('All categories'),
						categories::GLOBAL_ACCOUNT => lang('Global categories'),
						$GLOBALS['egw_info']['user']['account_id'] => lang('Own categories'),
					),
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
					'header_left'    =>	false,	// I  template to show left of the range-value, left-aligned (optional)
					'header_right'   =>	false,	// I  template to show right of the range-value, right-aligned (optional)
					'never_hide'     => True,	// I  never hide the nextmatch-line if less then maxmatch entries
					'lettersearch'   => false,	// I  show a lettersearch
					'start'          =>	0,		// IO position in list
					'order'          =>	'name',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'default_cols'   => '!color,last_mod,subs,legacy_actions',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false,	// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'no_search'      => !self::$acl_search,
					'row_id'         => 'id',
				);
				$content['nm']['filter'] = $this->appname == 'admin'?categories::GLOBAL_ACCOUNT:$GLOBALS['egw_info']['user']['account_id'];
			}
			else
			{
				$content['nm']['start']=0;
			}
			$content['nm']['appname'] = $appname = $_GET['appname'] ? $_GET['appname'] : $appname;
			$content['nm']['actions'] = $this->get_actions($appname);
			// switch filter off for application global cats too, not only for super-global ones
			$content['nm']['no_filter'] = $GLOBALS['egw_info']['flags']['currentapp'] == 'admin';

			$content['nm']['global_cats'] = true;
			if (isset($_GET['global_cats']) && empty($_GET['global_cats'] ))
			{
				$content['nm']['global_cats'] = false;
			}
		}
		elseif($content['nm']['action'])
		{
			// Old buttons
			foreach(array('delete') as $button)
			{
				if(isset($content['nm']['rows'][$button]))
				{
					list($id) = @each($content['nm']['rows'][$button]);
					$content['nm']['action'] = $button;
					$content['nm']['selected'] = array($id);
					break; // Only one can come per submit
				}
			}
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
			}
			else
			{
				// Action has an additional action - add / delete, etc.  Buttons named <multi-action>_action[action_name]
				if(in_array($content['nm']['action'], array('owner')))
				{
					$action = $content['nm']['action'];
					$content['nm']['action'] .= '_' . key($content[$action . '_action']);

					if(is_array($content[$action]))
					{
						$content[$action] = implode(',',$content[$action]);
					}
					$content['nm']['action'] .= '_' . $content[$action];
				}
				if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,$content['nm'],$msg))
				{
					$msg .= lang('%1 category(s) %2',$success,$action_msg);
				}
				elseif(empty($msg))
				{
					$msg .= lang('%1 category(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
			}
		}
		$content['msg'] = $msg;
		$content['add_link']= $this->add_link.'&appname='.$appname;
		$content['edit_link']= $this->edit_link.'&appname='.$appname;
		$content['owner'] = '';

		$sel_options['appname'] = $this->get_app_list();
		$sel_options['app'] = array(
			'' => lang('All'),
			$appname => lang($appname)
		);
		$sel_options['access'] = array(
			'public'  => 'No',
			'private' => 'Yes',
		);

		$sel_options['owner'][0] = lang('All users');
		$accs = $GLOBALS['egw']->accounts->get_list('groups');
		foreach($accs as $acc)
		{
			if ($acc['account_type'] == 'g')
			{
				$sel_options['owner'][$acc['account_id']] = ExecMethod2('etemplate.select_widget.accountInfo',$acc['account_id'],$acc,$type2,$type=='both');
			}
		}

		$readonlys['add'] = !self::$acl_add;
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$readonlys['nm']['rows']['owner'] = true;
			$readonlys['nm']['col_filter']['owner'] = true;
		}
		if($appname == categories::GLOBAL_APPNAME) {
			$sel_options['app'] = array(''=>'');
			$readonlys['nm']['rows']['app'] = true;
		}

		$tmpl = new etemplate('admin.categories.index');
		$tmpl->exec($this->list_link,$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}

	protected function get_actions($appname=categories::GLOBAL_APPNAME) {

		$actions = array(
			'open' => array(        // does edit if allowed, otherwise view
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction='.$this->edit_link.'&cat_id=$id&appname='.$appname,
				'popup' => '600x380',
				'group' => $group=1,
			),
			'add' => array(
				'caption' => 'Add',
				'allowOnMultiple' => false,
				'icon' => 'new',
				'url' => 'menuaction='.$this->add_link.'&appname='.$appname,
				'popup' => '600x380',
				'group' => $group,
			),
			'sub' => array(
				'caption' => 'Add sub',
				'allowOnMultiple' => false,
				'icon' => 'new',
				'url' => 'menuaction='.$this->add_link.'&parent=$id&appname='.$appname,
				'popup' => '600x380',
				'group' => $group,
				'disableClass' => 'rowNoSub',
			),
			'owner' => array(
				'caption' => 'Change owner',
				'icon' => 'users',
				'nm_action' => 'open_popup',
				'group' => $group,
				'disableClass' => 'rowNoEdit',
			),
			'delete' => array(
				'caption' => 'Delete',
				'allowOnMultiple' => true,
				'nm_action' => 'open_popup',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
			),
		);

		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			unset($actions['owner']);
		}

		return $actions;
	}

	/**
	 * Handles actions on multiple entries
	 *
	 * @param action
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like '%1 entries deleted'
	 * @param array $query get_rows parameter
	 * @param string &$msg on return user feedback
	 * @param boolean $skip_notifications=false true to NOT notify users about changes
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action, $checked, $use_all, &$success, &$failed, &$action_msg,
		array $query, &$msg, $skip_notifications = false)
	{
		//echo '<p>'.__METHOD__."('$action',".array2string($checked).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all)
		{
			@set_time_limit(0);                     // switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;        // all
			$this->get_rows($query,$result,$readonlys);
			$checked = array();
			foreach($result as $key => $info)
			{
				if(is_numeric($key))
				{
					$checked[] = $info['id'];
				}
			}
		}
		$owner = $query['col_filter']['owner'] ? $query['col_filter']['owner'] : $query['filter'];
		$app = $query['col_filter']['app'] ? $query['col_filter']['app'] : $query['appname'];
		$cats = new categories($owner,$app);

		list($action, $settings) = explode('_', $action, 2);

		switch($action)
		{
			case 'delete':
				foreach($checked as $id)
				{
					$cats->delete($id,$settings == 'sub',$settings != 'sub');
					$action_msg = lang('deleted');
					$success++;
				}
				break;
			case 'owner':
				$action_msg = lang('updated');
				list($add_remove, $ids) = explode('_', $settings, 2);
				$ids = explode(',',$ids);
				// Adding 'All users' removes all the others
				if($add_remove == 'add' && array_search(categories::GLOBAL_ACCOUNT,$ids) !== false) $ids = array(categories::GLOBAL_ACCOUNT);

				foreach($checked as $id)
				{
					if (!$data = $cats->read($id)) continue;
					$data['owner'] = explode(',',$data['owner']);
					if(array_search(categories::GLOBAL_ACCOUNT,$data['owner']) !== false || $data['owner'][0] > 0)
					{
						$data['owner'] = array();
					}
					$data['owner'] = $add_remove == 'add' ?
						$ids == array(categories::GLOBAL_ACCOUNT) ? $ids : array_merge($data['owner'],$ids) :
						array_diff($data['owner'],$ids);
					$data['owner'] = implode(',',array_unique($data['owner']));

					if ($cats->edit($data))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				break;
		}

		return $failed == 0;
	}

	/**
	 * Get a list of apps for selectbox / filter
	 */
	protected function get_app_list()
	{
		$apps = array();
		foreach ($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if ($app == 'phpgwapi')
			{
				$apps['phpgw'] = lang('Global');
				continue;
			}
			// Skip apps that don't show in the app bar, they [usually] don't have categories
			if($data['status'] > 1 || in_array($app, array('home','admin','felamimail','sitemgr','sitemgr-link'))) continue;
			if ($GLOBALS['egw_info']['user']['apps'][$app])
			{
				$apps[$app] = $data['title'] ? $data['title'] : lang($app);
			}
		}
		return $apps;
	}
}

admin_categories::init_static();
