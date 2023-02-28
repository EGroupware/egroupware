<?php
/**
 * EGroupware: Admin app UI
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Etemplate\Widget\Tree;
use EGroupware\Api\Link;

/**
 * UI for admin
 */
class admin_ui
{
	/**
	 * Methods callable via menuaction
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
	);

	/**
	 * Reference to global accounts object
	 *
	 * @var Api\Accounts
	 */
	private static $accounts;

	/**
	 * New index page
	 *
	 * @param array $content
	 */
	public function index(array $content=null)
	{
		/* disable usage statistic for now, as no more backend
		if (admin_statistics::check(false))
		{
			$_GET['load'] = 'admin.admin_statistics.submit';
			$_GET['ajax'] = 'false';
			$_GET['required'] = 'true';
		}*/
		$tpl = new Etemplate('admin.index');

		if (!is_array($content)) $content = array();
		$content['nm'] = array(
			'get_rows' => 'admin_ui::get_users',
			'no_cat' => true,
			'filter_no_lang' => true,
			'lettersearch' => true,
			'order' => 'account_lid',
			'sort' => 'ASC',
			'row_id' => 'account_id',
			'default_cols' => '!account_id,created,account_lastlogin,account_lastloginfrom,account_lastpwd_change',
			'actions' => self::user_actions(),
			'placeholder_actions' => array('add')
		);
		$content['groups'] = array(
			'get_rows'            => 'admin_ui::get_groups',
			'no_cat'              => true,
			'no_filter'           => true,
			'no_filter2'          => true,
			'num_rows'            => 0,
			'row_id'              => 'account_id',
			'actions'             => self::group_actions(),
			'placeholder_actions' => array('add')
		);

		$sel_options['tree'] = $this->tree_data();
		$sel_options['filter'] = array('' => lang('All groups'));
		foreach(self::$accounts->search(array(
			'type' => 'groups',
			'start' => false,
			'order' => 'account_lid',
			'sort' => 'ASC',
		)) as $data)
		{
			$sel_options['filter'][$data['account_id']] = empty($data['account_description']) ? $data['account_lid'] : array(
				'label' => $data['account_lid'],
				'title' => $data['account_description'],
			);
		}

		$sel_options['filter2'] = array(
			''            => 'All',
			'enabled'     => 'Enabled',
			'disabled'    => 'Disabled',
			'expired'     => 'Expired',
			'expires'     => 'Expires',
			'not_enabled' => 'Not enabled'
		);

		$tpl->setElementAttribute('tree', 'actions', self::tree_actions());

		// switching between iframe and nm/accounts-list depending on load parameter
		// important for first time load eg. from an other application calling it's site configuration
		$tpl->setElementAttribute('iframe', 'disabled', empty($_GET['load']));
		$content['iframe'] = 'about:blank';	// we show accounts-list be default now
		if (!empty($_GET['load']))
		{
			$vars = $_GET;
			$vars['menuaction'] = $vars['load'];
			unset($vars['load']);
			$content[$vars['ajax'] ? 'ajax_target':'iframe'] = Egw::link('/index.php', $vars);
		}

		$tpl->exec('admin.admin_ui.index', $content, $sel_options);
	}

	/**
	 * Actions on tree / groups
	 *
	 * @return array
	 */
	public static function tree_actions()
	{
		$actions = static::group_actions();

		foreach($actions as $action_id =>  &$action)
		{
			if (!isset($action['enableId']) && !in_array($action_id, array('add')))
			{
				$action['enableId'] = '^/groups/-\\d+';
			}
		}

		return $actions;
	}

	/**
	 * Actions on users
	 *
	 * @return array
	 */
	public static function user_actions()
	{
		static $actions = null;

		if (!isset($actions))
		{
			$actions = array(
				'edit' => array(
					'caption' => 'Open',
					'default' => true,
					'allowOnMultiple' => false,
					'onExecute' => 'javaScript:app.admin.account',
					'group' => $group=0,
				),
				'add' => array(
					'caption' => 'Add user',
					'onExecute' => 'javaScript:app.admin.account',
					'group' => $group,
				),
				'copy' => array(
					'caption' => 'Copy',
					'url' => 'menuaction=addressbook.addressbook_ui.edit&makecp=1&contact_id=$id',
					'onExecute' => 'javaScript:app.admin.account',
					'allowOnMultiple' => false,
					'icon' => 'copy',
				),
			);
			// generate urls for add/edit accounts via addressbook
			$edit = Link::get_registry('addressbook', 'edit');
			$edit['account_id'] = '$id';
			foreach($edit as $name => $val)
			{
				$actions['edit']['url'] .= ($actions['edit']['url'] ? '&' : '').$name.'='.$val;
			}
			unset($edit['account_id']);
			$edit['owner'] = 0;
			foreach($edit as $name => $val)
			{
				$actions['add']['url'] .= ($actions['edit']['url'] ? '&' : '').$name.'='.$val;
			}
			++$group;
			// supporting both old way using $GLOBALS['menuData'] and new just returning data in hook
			$apps = array_unique(array_merge(array('admin'), Api\Hooks::implemented('edit_user')));
			foreach($apps as $app)
			{
				$GLOBALS['menuData'] = $data = array();
				$data = Api\Hooks::single('edit_user', $app);
				if (!is_array($data)) $data = $GLOBALS['menuData'];
				foreach($data as $item)
				{
					// allow hook to return "real" actions, but still support legacy: description, url, extradata, options
					if (empty($item['caption']))
					{
						$item['caption'] = $item['description'];
						unset($item['description']);
					}
					if (isset($item['url']) && isset($item['extradata']))
					{
						$item['url'] = $item['extradata'].'&account_id=$id';
						$item['id'] = substr($item['extradata'], 11);
						unset($item['extradata']);
						$matches = null;
						if (!empty($item['options']) && preg_match('/(egw_openWindowCentered2?|window.open)\([^)]+,(\d+),(\d+).*(title="([^"]+)")?/', $item['options'], $matches))
						{
							$item['popup'] = $matches[2].'x'.$matches[3];
							if (isset($matches[5])) $item['tooltip'] = $matches[5];
							unset($item['options']);
						}
					}
					if (empty($item['icon'])) $item['icon'] = $app.'/navbar';
					if (empty($item['group'])) $item['group'] = $group;
					if (empty($item['onExecute'])) $item['onExecute'] = !empty($item['popup']) ?
						'javaScript:nm_action' : 'javaScript:app.admin.iframe_location';
					if (!isset($item['allowOnMultiple'])) $item['allowOnMultiple'] = false;

					$actions[$item['id']] = $item;
				}
			}
			$actions['delete'] = array(
				'caption' => 'Delete',
				'group' => ++$group,
				'popup' => '615x600',
				'url' => 'menuaction=admin.admin_account.delete&account_id=$id',
				'allowOnMultiple' => true,
			);
		}
		//error_log(__METHOD__."() actions=".array2string($actions));
		return $actions;
	}

	/**
	 * Actions on groups
	 *
	 * @return array
	 */
	public static function group_actions()
	{
		$user_actions = self::user_actions();
		$actions = array(
			'view' => array(
				'onExecute' => 'javaScript:app.admin.group',
				'caption' => 'Show members',
				'default' => true,
				'group' => $group=1,
				'allowOnMultiple' => false
			),
			'add' => array(
				'group' => $group,
			)+$user_actions['add'],
			'acl' => array(
				'onExecute' => 'javaScript:app.admin.group',
				'caption' => 'Access control',
				'url' => 'menuaction=admin.admin_acl.index&account_id=$id',
				'popup' => '900x450',
				'icon' => 'lock',
				'group' => 2,
				'allowOnMultiple' => false
			),
		);
		if (!$GLOBALS['egw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
		{
			$actions['deny'] = array(
				'caption'   => 'Deny access',
				'url'       => 'menuaction=admin.admin_denyaccess.list_apps&account_id=$id',
				'onExecute' => 'javaScript:app.admin.group',
				'icon'      => 'cancel',
				'group'     => 2,
				'allowOnMultiple' => false
			);
		}

		$group = 5;	// allow to place actions in different groups by hook, this is the default

		$apps = Api\Hooks::implemented('edit_group');
		// register hooks, if no admin one, can be removed after 22.1
		if (!isset($apps['admin']))
		{
			Api\Hooks::read(true);
			$apps = Api\Hooks::implemented('edit_group');
		}
		// skip EPL and groups app, in case their group-admin is still installed
		$apps = array_unique(array_diff($apps, ['groups', 'stylite']));
		foreach($apps as $app)
		{
			// supporting both old way using $GLOBALS['menuData'] and new just returning data in hook
			$GLOBALS['menuData'] = [];
			$data = Api\Hooks::single('edit_group', $app);
			if (!is_array($data)) $data = $GLOBALS['menuData'];

			foreach($data as $item)
			{
				// allow hook to return "real" actions, but still support legacy: description, url, extradata, options
				if (empty($item['caption']))
				{
					$item['caption'] = $item['description'];
					unset($item['description']);
				}
				if (isset($item['url']) && isset($item['extradata']))
				{
					$item['url'] = $item['extradata'].'&account_id=$id';
					$item['id'] = substr($item['extradata'], 11);
					unset($item['extradata']);
					$matches = null;
					if (!empty($item['options']) && preg_match('/(egw_openWindowCentered2?|window.open)\([^)]+,(\d+),(\d+).*(title="([^"]+)")?/', $item['options'], $matches))
					{
						$item['popup'] = $matches[2].'x'.$matches[3];
						$item['onExecute'] = 'javaScript:nm_action';
						if (isset($matches[5])) $item['tooltip'] = $matches[5];
						unset($item['options']);
					}
				}
				if (empty($item['icon'])) $item['icon'] = $app.'/navbar';
				if (empty($item['group'])) $item['group'] = $group;
				if (empty($item['onExecute'])) $item['onExecute'] = 'javaScript:app.admin.group';
				if (!isset($item['allowOnMultiple'])) $item['allowOnMultiple'] = false;

				$actions[$item['id']] = $item;
			}
		}
		return $actions;
	}

	/**
	 * Callback for nextmatch to fetch users
	 *
	 * @param array $query
	 * @param array &$rows=null
	 * @return int total number of rows available
	 */
	public static function get_users(array $query, array &$rows=null)
	{
		$params = array(
			'type' => (int)($query['filter'] ?? 0) ?: 'accounts',
			'start' => $query['start'],
			'offset' => $query['num_rows'],
			'order' => $query['order'],
			'sort' => $query['sort'],
			'active' => !empty($query['active']) ? $query['active'] : false,
		);
		// Make sure active filter give status what it needs
		switch($query['filter2'] ?? '')
		{
			case 'disabled':
			case 'expired':
			case 'not_enabled':
			case 'expires':
				// this filters are not implemented by backend --> need to do unlimited query, then apply status filter and finally limit the query
				$need_status_filter = $query['filter2'];
				$params['start'] = false;
				unset($params['offset']);
				$params['active'] = $query['filter2'] === 'expires';
				break;

			case '':	// all
				$params['active'] = false;
				break;

			case 'enabled':
				$params['active'] = true;
				break;
		}

		if (!empty($query['searchletter']))
		{
			$params['query'] = $query['searchletter'];
			$params['query_type'] = 'start';
		}
		elseif(!empty($query['search']))
		{
			$params['query'] = $query['search'];
			$params['query_type'] = 'all';
		}
		if (!empty($query['account_id']))
		{
			$params['account_id'] = (array)$query['account_id'];
		}

		$rows = array_values(self::$accounts->search($params));
		//error_log(__METHOD__."() accounts->search(".array2string($params).") total=".self::$accounts->total);
		$total = self::$accounts->total;

		// release session (after query got cached!) to allow parallel requests to run
		$GLOBALS['egw']->session->commit_session();

		foreach($rows as $key => &$row)
		{
			// Filter by status
			if (!empty($need_status_filter) && !static::filter_status($need_status_filter, $row))
			{
				unset($rows[$key]);
				$total--;
				continue;
			}
			$row['status'] = self::$accounts->is_expired($row) ?
				lang('Expired').' '.Api\DateTime::to($row['account_expires'], true) :
					(!self::$accounts->is_active($row) ? lang('Disabled') :
						($row['account_expires'] != -1 ? lang('Expires').' '.Api\DateTime::to($row['account_expires'], true) :
							lang('Enabled')));

			if (!self::$accounts->is_active($row)) $row['status_class'] = 'adminAccountInactive';
		}
		// finally, limit query, if status filter was used
		if (!empty($need_status_filter))
		{
			$rows = array_values(array_slice($rows, (int)$query['start'], $query['num_rows'] ?: count($rows)));
		}
		return $total;
	}


	/**
	 * Filter the account based on given status.
	 *
	 * Status is one of enabled, disabled, expired, expires, not_enabled
	 * @param $status
	 * @param $account
	 */
	protected static function filter_status($status, &$account)
	{
		switch($status)
		{
			case 'enabled':
				return $account['account_status'] == 'A';

			case 'disabled':
				return $account['account_status'] !== 'A' && $account['account_expires'] == '-1';

			case 'expired':
				return $account['account_expires'] !== '-1' && $account['account_expires'] <= time();

			case 'expires':
				return $account['account_expires'] != '-1' && $account['account_status'] == 'A';

			case 'not_enabled':
				return static::filter_status('disabled', $account) || static::filter_status('expired', $account);
		}
		return false;
	}

	/**
	 * Callback for the nextmatch to get groups
	 *
	 * Does NOT set members for huge installations, but return "is_huge" === true in $rows.
	 *
	 * @param array &$query
	 * @param array|null &$rows on return rows plus boolean value for key "is_huge" === Accounts::isHuge()
	 * @return int total number of rows
	 */
	public static function get_groups(array &$query, array &$rows=null)
	{
		$groups = $GLOBALS['egw']->accounts->search(array(
				'type'  => 'groups',
				'query' => $query['search'] ?? null,
				'order' => $query['order'] ?? null,
				'sort'  => $query['sort'] ?? null,
				'start' => (int)$query['start'],
				'offset' => (int)$query['num_rows']
			));

		// release session (after query got cached!) to allow parallel requests to run
		$GLOBALS['egw']->session->commit_session();

		$apps = array();
		foreach($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if (!$data['enabled'] || !$data['status'] || $data['status'] == 3)
			{
				continue;	// do NOT show disabled apps, or our API (status = 3)
			}

			$apps[] = $app;
		}

		$rows = [];
		$is_huge = $GLOBALS['egw']->accounts->isHuge();
		foreach($groups as &$group)
		{
			$run_rights = $GLOBALS['egw']->acl->get_user_applications($group['account_id'], false, false);
			foreach($apps as $app)
			{
				if(!empty($run_rights[$app]))
				{
					$group['apps'][] = $app;
				}
			}

			// do NOT set members for huge installations, but return "is_huge" === true in $rows
			if (!$is_huge)
			{
				$group['members'] = $GLOBALS['egw']->accounts->members($group['account_id'],true);
			}
			$rows[] = $group;
		}
		$rows['is_huge'] = $is_huge;
		return $GLOBALS['egw']->accounts->total;
	}

	/**
	 * Autoload tree from $_GET['id'] on
	 */
	public static function ajax_tree()
	{
		Etemplate\Widget\Tree::send_quote_json(self::tree_data(!empty($_GET['id']) ? $_GET['id'] : '/'));
	}

	/**
	 * Get data for navigation tree
	 *
	 * Example:
	 * array(
	 *	'id' => 0, 'item' => array(
	 *		array('id' => '/INBOX', 'text' => 'INBOX', 'tooltip' => 'Your inbox', 'open' => 1, 'im1' => 'kfm_home.png', 'im2' => 'kfm_home.png', 'child' => '1', 'item' => array(
	 *			array('id' => '/INBOX/sub', 'text' => 'sub', 'im0' => 'folderClosed.gif'),
	 *			array('id' => '/INBOX/sub2', 'text' => 'sub2', 'im0' => 'folderClosed.gif'),
	 *		)),
	 *		array('id' => '/user', 'text' => 'user', 'child' => '1', 'item' => array(
	 *			array('id' => '/user/birgit', 'text' => 'birgit', 'im0' => 'folderClosed.gif'),
	 *		)),
	 * ));
	 *
	 * @param string $root ='/'
	 * @return array
	 */
	public static function tree_data($root = '/')
	{
		$tree = array(Tree::ID => $root === '/' ? 0 : $root, Tree::CHILDREN => array(), 'child' => 1);

		if ($root == '/')
		{
			$hook_data = self::call_hook();
			foreach($hook_data as $app => $app_data)
			{
				if(!is_array($app_data))
				{
					// Application has no data
					continue;
				}
				foreach($app_data as $text => $data)
				{
					if (!is_array($data))
					{
						$data = array(
							'link' => $data,
						);
					}
					if (empty($data[Tree::LABEL])) $data[Tree::LABEL] = $text;
					if (empty($data[Tree::ID]))
					{
						$data['id'] = $root.($app == 'admin' ? 'admin' : 'apps/'.$app).'/';
						$matches = null;
						if (preg_match_all('/(menuaction|load)=([^&]+)/', $data['link'], $matches))
						{
							$data[Tree::ID] .= $matches[2][(int)array_search('load', $matches[1])];
						}
					}
					if (!empty($data['icon']))
					{
						$icon = Etemplate\Widget\Tree::imagePath($data['icon']);
						if (!empty($data['child']) || !empty($data[Tree::CHILDREN]))
						{
							$data[Tree::IMAGE_FOLDER_OPEN] = $data[Tree::IMAGE_FOLDER_CLOSED] = $icon;
						}
						else
						{
							$data[Tree::IMAGE_LEAF] = $icon;
						}
					}
					unset($data['icon']);
					$parent =& $tree[Tree::CHILDREN];
					$parts = explode('/', $data[Tree::ID]);
					if ($data[Tree::ID][0] == '/') array_shift($parts);	// remove root
					array_pop($parts);
					$path = '';
					foreach($parts as $part)
					{
						$path .= ($path == '/' ? '' : '/').$part;
						if (!isset($parent[$path]))
						{
							$icon = Etemplate\Widget\Tree::imagePath($part == 'apps' ? Api\Image::find('api', 'home') :
								(($i=Api\Image::find($part, 'navbar')) ? $i : Api\Image::find('api', 'nonav')));
							$parent[$path] = array(
								Tree::ID => $path,
								Tree::LABEL => $part == 'apps' ? lang('Applications') : lang($part),
								//'im0' => 'folderOpen.gif',
								Tree::IMAGE_FOLDER_OPEN => $icon,
								Tree::IMAGE_FOLDER_CLOSED => $icon,
								Tree::CHILDREN => array(),
								'child' => 1,
							);
							if ($path == '/admin') $parent[$path]['open'] = true;
						}
						$parent =& $parent[$path][Tree::CHILDREN];
					}
					$data[Tree::LABEL] = lang($data[Tree::LABEL]);
					if (!empty($data['tooltip'])) $data['tooltip'] = lang($data['tooltip']);
					// make sure keys are unique, as we overwrite tree entries otherwise
					if (isset($parent[$data[Tree::ID]])) $data[Tree::ID] .= md5($data['link']);
					$parent[$data[Tree::ID]] = self::fix_userdata($data);
				}
			}
		}
		elseif ($root == '/groups')
		{
			foreach($GLOBALS['egw']->accounts->search(array(
				'type' => 'groups',
				'order' => 'account_lid',
				'sort' => 'ASC',
			)) as $group)
			{
				$tree[Tree::CHILDREN][] = self::fix_userdata(array(
					Tree::LABEL => $group['account_lid'],
					Tree::TOOLTIP => $group['account_description'],
					Tree::ID => $root.'/'.$group['account_id'],
				));
			}
		}
		self::strip_item_keys($tree[Tree::CHILDREN]);
		//_debug_array($tree); exit;
		return $tree;
	}

	/**
	 * Fix userdata as understood by tree
	 *
	 * @param array $data
	 * @return array
	 */
	private static function fix_userdata(array $data)
	{
		if(!$data[Tree::LABEL])
		{
			$data[Tree::LABEL] = $data['text'];
		}
		// store link as userdata, maybe we should store everything not directly understood by tree this way ...
		foreach(array_diff_key($data, array_flip(array(
			Tree::ID,Tree::LABEL,Tree::TOOLTIP,'im0','im1','im2','item','child','select','open','call',
		))) as $name => $content)
		{
			$data['userdata'][] = array(
				'name' => $name,
				'content' => $content,
			);
			unset($data[$name]);
		}
		return $data;
	}

	/**
	 * Attribute 'item' has to be an array
	 *
	 * @param array $items
	 */
	private static function strip_item_keys(array &$items)
	{
		$items = array_values($items);
		foreach($items as &$item)
		{
			if (is_array($item) && isset($item['item']))
			{
				self::strip_item_keys($item['item']);
			}
		}
	}

	public static $hook_data = array();
	/**
	 * Return data from regular admin hook calling display_section() instead of returning it
	 *
	 * @return array appname => array of label => link/data pairs
	 */
	protected static function call_hook()
	{
		self::$hook_data = array();
		function display_section($appname,$file,$file2=False)
		{
			admin_ui::$hook_data[$appname] = $file2 ? $file2 : $file;
			//error_log(__METHOD__."(".array2string(func_get_args()).")");
		}
		self::$hook_data = array_merge(Api\Hooks::process('admin', array('admin')), self::$hook_data);

		// sort apps alphabetic by their title / Api\Translation of app-name
		uksort(self::$hook_data, function($a, $b)
		{
			return strcasecmp(lang($a), lang($b));
		});
		// make sure admin is first
		self::$hook_data = array_merge(array('admin' => self::$hook_data['admin']), self::$hook_data);

		return self::$hook_data;
	}

	/**
	 * Init static variables
	 */
	public static function init_static()
	{
		self::$accounts = $GLOBALS['egw']->accounts;
	}
}
admin_ui::init_static();