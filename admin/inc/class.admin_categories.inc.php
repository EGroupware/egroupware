<?php
/**
 * EGroupware admin - Edit global categories
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Categories;

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
		'delete' => true,
	);

	/**
	 * Path where the icons are stored (relative to webserver_url)
	 */
	const ICON_PATH = '/api/images';

	protected $appname = 'admin';
	protected $get_rows = 'admin.admin_categories.get_rows';
	protected $list_link = 'admin.admin_categories.index';
	protected $add_link = 'admin.admin_categories.edit';
	protected $edit_link = 'admin.admin_categories.edit';
	protected $delete_link = 'admin.admin_categories.delete';

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
			throw new Api\Exception\NoPermission\Admin();
		}
		if ($GLOBALS['egw']->acl->check('global_categorie',1,'admin'))
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
			self::$acl_search = !$GLOBALS['egw']->acl->check('global_categorie',2,'admin');
			self::$acl_add    = !$GLOBALS['egw']->acl->check('global_categorie',4,'admin');
			self::$acl_view   = !$GLOBALS['egw']->acl->check('global_categorie',8,'admin');
			self::$acl_edit   = !$GLOBALS['egw']->acl->check('global_categorie',16,'admin');
			self::$acl_delete = !$GLOBALS['egw']->acl->check('global_categorie',32,'admin');
			self::$acl_add_sub= !$GLOBALS['egw']->acl->check('global_categorie',64,'admin');
		}
	}

	/**
	 * Edit / add a category
	 *
	 * @param array $content = null
	 * @param string $msg = ''
	 */
	public function edit(array $content=null,$msg='')
	{
		// read the session, as the global_cats param is stored with it.
		$appname = $content['appname'] ? $content['appname'] : ($_GET['appname']?$_GET['appname']:Api\Categories::GLOBAL_APPNAME);
		$session = Api\Cache::getSession(__CLASS__.$appname,'nm');
		unset($session);
		if (!isset($content))
		{
			if (!(isset($_GET['cat_id']) && $_GET['cat_id'] > 0 &&
				($content = Categories::read($_GET['cat_id']))))
			{
				$content = array('data' => array());
				if(isset($_GET['parent']) && $_GET['parent'] > 0)
				{
					// Sub-category - set some defaults from parent
					$content['parent'] = (int)$_GET['parent'];
					$parent_cat = Categories::read($content['parent']);
					$content['owner'] = $parent_cat['owner'];
				}
				if (isset($_GET['appname']) && isset($GLOBALS['egw_info']['apps'][$_GET['appname']]))
				{
					$appname = $_GET['appname'];
				}
				else
				{
					$appname = Categories::GLOBAL_APPNAME;
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
			$cats = new Categories($content['owner'] ? $content['owner'] : Categories::GLOBAL_ACCOUNT,$content['appname']);

			if ($content['delete']['delete'] || $content['delete']['subs'])
			{
				$button = 'delete';
				$delete_subs = $content['delete']['subs']?true:false;
			}
			else
			{
				$button = key($content['button']);
				unset($content['button']);
			}
			unset($content['delete']);

			$refresh_app = $this->appname == 'preferences' ? $content['appname'] : $this->appname;

			switch($button)
			{
				case 'save':
				case 'apply':
					if(is_array($content['owner'])) $content['owner'] = implode(',',$content['owner']);
					if($content['owner'] == '') $content['owner'] = 0;
					unset($content['msg']);
					if ($content['id'] && self::$acl_edit)
					{

						$data = $cats->id2name($content['id'],'data');
						if(!$content['parent'])
						{
							$content['parent'] = '0';
						}
						try
						{
							$cmd = new admin_cmd_category($appname, $content, $cats->read($content['id']), $content['admin_cmd']);
							$msg = $cmd->run();
						}
						catch (Api\Exception\WrongUserinput $e)
						{
							$msg = lang('Unwilling to save category with current settings. Check for inconsistency:').$e->getMessage();	// display conflicts etc.
						}
					}
					elseif (!$content['id'] && (
						$content['parent'] && self::$acl_add_sub ||
						!$content['parent'] && self::$acl_add))
					{
						$cmd = new admin_cmd_category($appname, $content);
						$cmd->run();
						$content['id'] = $cmd->cat_id;
						$msg = lang('Category saved.');
					}
					else
					{
						$msg = lang('Permission denied!');
						unset($button);
					}
					// If color changed, we need to do an edit 'refresh' instead of 'update'
					// to reload the whole nextmatch instead of just the row
					$change_color = ($data['color'] != $content['data']['color']);
					// Nicely reload just the category window / iframe
					if($change_color)
					{
						if(Api\Json\Response::isJSONResponse())
						{
							if($this->appname != 'admin')
							{
								// Need to forcably re-load everything to force the CSS to be loaded
								Api\Json\Response::get()->redirect(Framework::link('/index.php', array(
									'menuaction' => 'preferences.preferences_categories_ui.index',
									'ajax' => 'true',
									'cats_app' => $appname
								)), TRUE, $this->appname);
							}
							else
							{
								// Need to forcably re-load everything to force the CSS to be loaded
								Api\Json\Response::get()->redirect(Framework::link('/index.php', array(
									'menuaction' => 'admin.admin_ui.index',
									'load' => $this->list_link,
									'ajax' => 'true',
									'appname' => $appname
								)), TRUE, $this->appname);
							}
							Framework::window_close();
							return;
						}
						else
						{
							Categories::css($refresh_app == 'admin' ? Categories::GLOBAL_APPNAME : $refresh_app);
							Framework::refresh_opener('', null, null);
							if ($button == 'save')
							{
								Framework::window_close();
							}
							return;
						}
					}

					if ($button == 'save')
					{
						Framework::refresh_opener($msg, $refresh_app, $content['id'], $change_color ? null : 'update', $refresh_app);
						Framework::window_close();
					}
					break;

				case 'delete':
					if (self::$acl_delete)
					{
						$cmd = new admin_cmd_delete_category($content['id'], $delete_subs);
						$msg = $cmd->run();

						Framework::refresh_opener($msg, $refresh_app, $content['id'],'delete', $this->appname);
						Framework::window_close();
						return;
					}
					else
					{
						$msg = lang('Permission denied!');
						unset($button);
					}
					break;
			}
			// This should probably refresh the application $this->appname in the target tab $refresh_app, but that breaks pretty much everything
			Framework::refresh_opener($msg, $refresh_app, $content['id'], $change_color ? null : 'update', $refresh_app);
		}
		$content['msg'] = $msg;
		if(!$content['appname']) $content['appname'] = $appname;
		if($content['data']['icon'])
		{
			$content['icon_url'] = $content['base_url'] . $content['data']['icon'];
		}

		$sel_options['icon'] = self::get_icons();
		$sel_options['owner'] = array();

		// User's category - add current value to be able to preserve owner
		if(!$content['id'])
		{
			if($this->appname != 'admin')
			{
				$content['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			}
			elseif (!$content['owner'])
			{
				$content['owner'] = 0;
			}
		}

		if($this->appname != 'admin' && $content['owner'] > 0 )
		{
			$sel_options['owner'][$content['owner']] = Api\Accounts::username($content['owner']);
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
				$content['msg'] .= "\n".lang('owner "%1" removed, please select group-owner', Api\Accounts::username($content['owner']));
				$content['owner'] = 0;
			}
			$sel_options['owner'][0] = lang('All users');
			foreach($GLOBALS['egw']->accounts->search(array('type' => 'groups')) as $acc)
			{
				if ($acc['account_type'] == 'g')
				{
					$sel_options['owner'][$acc['account_id']] = Etemplate\Widget\Select::accountInfo($acc['account_id'], $acc);
				}
			}
			$content['no_private'] = true;
		}

		if($this->appname == 'admin')
		{
			$content['access'] = 'public';
			// Allow admins access to all categories as parent
			$content['all_cats'] = 'all_no_acl';
			$readonlys['owner'] = false;
		} else {
			$readonlys['owner'] = true;
			$readonlys['access'] = $content['owner'] != $GLOBALS['egw_info']['user']['account_id'];
		}

		Framework::includeJS('.','global_categories','admin');
		Api\Translation::add_app('admin');

		$readonlys['button[delete]'] = !$content['id'] || !self::$acl_delete ||		// cant delete not yet saved category
			$appname != $content['appname'] || // Can't edit a category from a different app
			 ($this->appname != 'admin' && $content['owner'] != $GLOBALS['egw_info']['user']['account_id']);
		$content['delete_link'] = $this->delete_link;

		// Make sure $content['owner'] is an array otherwise it wont show up values in the multiselectbox
		if (!is_array($content['owner'])) $content['owner'] = explode(',',$content['owner']);
		$tmpl = new Etemplate('admin.categories.edit');
		$tmpl->exec($this->edit_link,$content,$sel_options,$readonlys,$content+array(
			'old_parent' => $content['old_parent'] ? $content['old_parent'] : $content['parent'], 'appname' => $appname
		),2);
	}

	/**
	 * Return URL of an icon, or base url with trailing slash
	 *
	 * @param string $icon = '' filename
	 * @return string url
	 */
	static function icon_url($icon='')
	{
		return $GLOBALS['egw_info']['server']['webserver_url'].self::ICON_PATH.'/'.$icon;
	}

	/**
	 * Return icons from /api/images
	 *
	 * @return array filename => label
	 */
	static function get_icons()
	{
		$icons = array();
		if (file_exists($image_dir=EGW_SERVER_ROOT.self::ICON_PATH) && ($dir = dir($image_dir)))
		{
			$matches = null;
			while(($file = $dir->read()))
			{
				if (preg_match('/^(.*)\\.(png|gif|jpe?g)$/i',$file,$matches))
				{
					$icons[$file] = ucfirst($matches[1]);
				}
			}
			$dir->close();
			asort($icons);
		}
		return $icons;
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on Acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	public function get_rows(&$query,&$rows,&$readonlys)
	{
		self::init_static();

		$filter = array();
		$globalcat = ($query['filter'] === Categories::GLOBAL_ACCOUNT || !$query['filter']);
		if (isset($query['global_cats']) && $query['global_cats']===false)
		{
			$globalcat = false;
		}
		if ($globalcat && $query['filter']) $filter['access'] = 'public';
		// new column-filter access has highest priority
		if (!empty($query['col_filter']['access']))$filter['access'] = $query['col_filter']['access'];

		Api\Cache::setSession(__CLASS__.$query['appname'],'nm',$query);

		if($query['filter'] > 0 || $query['col_filter']['owner'])
		{
			$owner = $query['col_filter']['owner'] ? $query['col_filter']['owner'] : $query['filter'];
		}
		if($query['col_filter']['app'])
		{
			$filter['appname'] = $query['col_filter']['app'];
		}
		$GLOBALS['egw']->categories = $cats = new Categories($filter['owner'],$query['appname']);
		$globals = isset($GLOBALS['egw_info']['user']['apps']['admin']) ? 'all_no_acl' : $globalcat;	// ignore Acl only for admins
		$parent = $query['search'] ? false : 0;
		$rows = $cats->return_sorted_array($query['start'],false,$query['search'],$query['sort'],$query['order'],$globals,$parent,true,$filter);
		$count = $cats->total_records;
		foreach($rows as $key => &$row)
		{
			$row['owner'] = explode(',',$row['owner']);
			if(($owner && !in_array($owner, $row['owner'])) || ((string)$query['filter'] === (string)Api\Categories::GLOBAL_ACCOUNT && $row['owner'][0] > 0))
			{
				unset($rows[$key]);
				$count--;
				continue;
			}

			if($row['level'] >= 0)
			{
				$row['level_spacer'] = str_repeat('&nbsp; &nbsp; ',$row['level']);
			}

			if ($row['data']['icon']) $row['icon_url'] = self::icon_url($row['data']['icon']);

			$row['subs'] = $row['children'] ? count($row['children']) : 0;

			$row['class'] = 'level'.$row['level'];
			if($row['owner'][0] > 0 && !$GLOBALS['egw_info']['user']['apps']['admin'] && $row['owner'][0] != $GLOBALS['egw_info']['user']['account_id'])
			{
				$row['class'] .= ' rowNoEdit rowNoDelete ';
			}
			else if (!$GLOBALS['egw_info']['user']['apps']['admin'])
			{
				if(!$cats->check_perms(Acl::EDIT, $row['id']) || !self::$acl_edit)
				{
					$row['class'] .= ' rowNoEdit';
				}
				if(!$cats->check_perms(Acl::DELETE, $row['id']) || !self::$acl_delete ||
					// Only admins can delete globals
					$cats->is_global($row['id']) && !$GLOBALS['egw_info']['user']['apps']['admin'])
				{
					$row['class'] .= ' rowNoDelete';
				}
			}
			// Can only edit or delete (via context menu) Categories for the selected app (backend restriction)
			if($row['appname'] != $query['appname'])
			{
				$row['class'] .= ' rowNoEdit rowNoDelete ';
			}
			$readonlys['nm']["edit[$row[id]]"]   = !self::$acl_edit;
			$readonlys['nm']["add[$row[id]]"]    = !self::$acl_add_sub;
			$readonlys['nm']["delete[$row[id]]"] = !self::$acl_delete;
		}
		if (true) $rows = $count <= $query['num_rows'] ? array_values($rows) : array_slice($rows, $query['start'], $query['num_rows']);
		// make appname available for actions
		$rows['appname'] = $query['appname'];
		$rows['edit_link'] = $this->edit_link;

		// disable access column for global Categories
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'admin') $rows['no_access'] = true;

		$GLOBALS['egw_info']['flags']['app_header'] = lang($this->appname).' - '.lang('categories').
			($query['appname'] != Categories::GLOBAL_APPNAME ? ': '.lang($query['appname']) : '');

		return $count;
	}

	/**
	 * Display the accesslog
	 *
	 * @param array $content = null
	 * @param string $msg = ''
	 */
	public function index(array $content=null,$msg='')
	{
		//_debug_array($_GET);
		if ($this->appname != 'admin') Api\Translation::add_app('admin');	// need admin translations

		if(!isset($content))
		{
			if (isset($_GET['msg'])) $msg = $_GET['msg'];

			$appname = Categories::GLOBAL_APPNAME;
			foreach(array($content['nm']['appname'], $_GET['cats_app'], $_GET['appname']) as $field)
			{
				if($field)
				{
					$appname = $field;
					break;
				}
			}
			$content['nm'] = Api\Cache::getSession(__CLASS__.$appname,'nm');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	$this->get_rows,	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'options-filter' => array(
						'' => lang('All categories'),
						Categories::GLOBAL_ACCOUNT => lang('Global categories'),
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
					'dataStorePrefix' => 'categories' // Avoid conflict with user list when in admin
				);
				$content['nm']['filter'] = $this->appname == 'admin'?Api\Categories::GLOBAL_ACCOUNT:$GLOBALS['egw_info']['user']['account_id'];
			}
			else
			{
				$content['nm']['start']=0;
			}
			$content['nm']['appname'] = $appname = $_GET['appname'] ? $_GET['appname'] : $appname;
			$content['nm']['actions'] = $this->get_actions($appname);
			// switch filter off for super-global categories
			if($appname == 'phpgw')
			{
				$content['nm']['no_filter'] = true;
				// Make sure filter is set properly, could be different if user was looking at something else
				$content['nm']['filter'] = Categories::GLOBAL_ACCOUNT;
			}

			$content['nm']['global_cats'] = true;
			if (isset($_GET['global_cats']) && empty($_GET['global_cats'] ))
			{
				$content['nm']['global_cats'] = false;
			}
		}
		elseif($content['nm']['action'])
		{
			$appname = $content['nm']['appname'];
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
					if ($content[$action.'_popup'])
					{
						$content = array_merge($content,$content[$action.'_popup']);
					}
					$content['nm']['action'] .= '_' . key($content[$action . '_action']);

					if(is_array($content[$action]))
					{
						$content[$action] = implode(',',$content[$action]);
					}
					$content['nm']['action'] .= '_' . $content[$action];
				}
				$success = $failed = 0;
				$action_msg = null;
				if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,$content['nm']))
				{
					$msg .= lang('%1 category(s) %2',$success,$action_msg);
				}
				elseif(empty($msg))
				{
					$msg .= lang('%1 category(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
				Framework::refresh_opener($msg, $this->appname);
				$msg = '';
			}
		}
		$content['msg'] = $msg;
		$content['nm']['add_link']= Framework::link('/index.php','menuaction='.$this->add_link . '&cat_id=&appname='.$appname);
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
		foreach($GLOBALS['egw']->accounts->search(array('type' => 'groups')) as $acc)
		{
			if ($acc['account_type'] == 'g')
			{
				$sel_options['owner'][$acc['account_id']] = Etemplate\Widget\Select::accountInfo($acc['account_id'], $acc);
			}
		}

		$readonlys['add'] = !self::$acl_add;
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$readonlys['nm']['rows']['owner'] = true;
			$readonlys['nm']['col_filter']['owner'] = true;
		}
		if($appname == Categories::GLOBAL_APPNAME) {
			$sel_options['app'] = array(''=>'');
			$readonlys['nm']['rows']['app'] = true;
		}

		$tmpl = new Etemplate('admin.categories.index');
		// we need to set a different dom-id for each application and also global categories of that app
		// otherwise eT2 objects are overwritter when a second categories template is shown
		$tmpl->set_dom_id($appname.'.'.$this->appname.'.categories.index');

		// Category styles
		Categories::css($appname);

		$tmpl->exec($this->list_link,$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}

	/**
	 * Dialog to delete a category
	 *
	 * @param array $content =null
	 */
	public function delete(array $content=null)
	{
		if (!is_array($content))
		{
			if (isset($_GET['cat_id']))
			{
				$content = array(
					'cat_id'=>strpos($_GET['cat_id'], ',') !== False ? explode(',',$_GET['cat_id']) : [(int)$_GET['cat_id']],
				);
			}
			//error_log(__METHOD__."() \$_GET[account_id]=$_GET[account_id], \$_GET[contact_id]=$_GET[contact_id] content=".array2string($content));
		}
		if($_GET['appname'])
		{

		}
		$cats = new Categories('', Categories::id2name($content['cat_id'][0],'appname'));
		foreach($content['cat_id'] as $index => $cat_id)
		{
			if ((!$cats->check_perms(Acl::DELETE, $cat_id) || !self::$acl_delete) &&
					// Only admins can delete globals
					$cats->is_global($cat_id) && !$GLOBALS['egw_info']['user']['apps']['admin'])

			{
				unset($content['cat_id'][$index]);
			}
		}
		if(count($content['cat_id']) == 0)
		{
			Framework::window_close(lang('Permission denied!!!'));
		}
		if ($content['button'])
		{
			$refresh_app = $this->appname == 'preferences' ? $content['appname'] : $this->appname;
			foreach($content['cat_id'] as $cat_id)
			{
				if ($cats->check_perms(Acl::DELETE, $cat_id, (boolean)$GLOBALS['egw_info']['user']['apps']['admin']))
				{
					$cmd = new admin_cmd_delete_category(
							$cat_id,
							key($content['button']) == 'delete_sub',
							$content['admin_cmd']
					);
					$cmd->run();
					Framework::refresh_opener(lang('Deleted'), $refresh_app, $cat_id, count($content['cat_id']) > 0 ? 'edit':'delete',$refresh_app);
				}
			}
			Framework::window_close();
		}
		$tpl = new Etemplate('admin.categories.delete');
		$tpl->exec($this->delete_link, $content, array(), array(), $content, 2);
	}

	protected function get_actions($appname=Api\Categories::GLOBAL_APPNAME) {

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
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
				'popup' => '450x400',
				'url' => 'menuaction='.$this->delete_link.'&appname='.($this->appname == 'preferences' ? $appname : $this->appname).'&cat_id=$id',
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
	 * @param string $_action
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like '%1 entries deleted'
	 * @param array $query get_rows parameter
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($_action, $checked, $use_all, &$success, &$failed, &$action_msg, array $query)
	{
		//echo '<p>'.__METHOD__."('$action',".array2string($checked).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all)
		{
			@set_time_limit(0);                     // switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;        // all
			$result = $readonlys = array();
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
		$cats = new Categories($owner,$app);

		list($action, $settings) = explode('_', $_action, 2);

		switch($action)
		{
			case 'delete':
				$action_msg = lang('deleted');
				foreach($checked as $id)
				{
					if($cats->check_perms(Acl::DELETE, $id, (boolean)$GLOBALS['egw_info']['user']['apps']['admin']))
					{
						$cmd = new admin_cmd_delete_category($id, $settings == 'sub');
						$cmd->run();
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				break;
			case 'owner':
				$action_msg = lang('updated');
				list($add_remove, $ids_csv) = explode('_', $settings, 2);
				$ids = explode(',', $ids_csv);
				// Adding 'All users' removes all the others
				if($add_remove == 'add' && array_search(Categories::GLOBAL_ACCOUNT,$ids) !== false) $ids = array(Categories::GLOBAL_ACCOUNT);

				foreach($checked as $id)
				{
					if (!$data = $cats->read($id)) continue;
					$data['owner'] = explode(',',$data['owner']);
					if(array_search(Categories::GLOBAL_ACCOUNT,$data['owner']) !== false || $data['owner'][0] > 0)
					{
						$data['owner'] = array();
					}
					$data['owner'] = $add_remove == 'add' ?
						$ids == array(Categories::GLOBAL_ACCOUNT) ? $ids : array_merge($data['owner'],$ids) :
						array_diff($data['owner'],$ids);
					$data['owner'] = implode(',',array_unique($data['owner']));

					$cmd = new admin_cmd_category($app, $data, array());
					if ($cmd->run())
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
			// Skip apps that don't show in the app bar, they [usually] don't have Categories
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
