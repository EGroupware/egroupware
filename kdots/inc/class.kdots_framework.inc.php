<?php

use EGroupware\Api;
use EGroupware\Api\Hooks;
use EGroupware\Api\Image;
use EGroupware\Api\Framework\Updates;
use EGroupware\Api\Header\UserAgent;

class kdots_framework extends Api\Framework\Ajax
{
	/**
	 * Appname used for everything but JS includes
	 */
	const APP = 'kdots';
	/**
	 * Appname used to include javascript code
	 */
	const JS_INCLUDE_APP = 'kdots';

	/**
	 * Enable to use this template sets login.tpl for login page
	 */
	const LOGIN_TEMPLATE_SET = true;


	/**
	 * Constructor
	 *
	 * Overwritten to set own app/template name (parent can NOT use static::APP!)
	 *
	 * @param string $template ='pixelegg' name of the template
	 */
	function __construct($template = self::APP)
	{
		parent::__construct($template);        // call the constructor of the extended class

		// search 'mobile' dirs first
		if(Api\Header\UserAgent::mobile())
		{
			array_unshift($this->template_dirs, 'mobile');
		}
		$this->template_dirs[] = '/kdots/css/themes';
	}

	/**
	 * List available themes
	 *
	 * Themes are css file in the template directory
	 *
	 * @param string $themes_dir ='css'
	 */
	function list_themes()
	{
		$list = array();
		if(($dh = @opendir(EGW_SERVER_ROOT . $this->template_dir . '/css/themes')))
		{
			while(($file = readdir($dh)))
			{
				if(preg_match('/' . "\.css$" . '/i', $file) &&
					!in_array($file, ['mobile.css', 'mobile.min.css']))
				{
					list($name) = explode('.', $file);
					if(!isset($list[$name]))
					{
						$list[$name] = ucfirst($name);
					}
				}
			}
			closedir($dh);
		}
		return $list;
	}

	/**
	 * Get header as array to eg. set as vars for a template (from idots' head.inc.php)
	 *
	 * @param array $extra =array() extra attributes passed as data-attribute to egw.js
	 * @return array
	 */
	protected function _get_header(array $extra = array())
	{
		// Skip making a mess for iframe apps, they're on their own
		/* status app / jitsi videoconference needs to load the header, so it's not just iframe apps
		if($extra['check-framework'] == true)
		{
			$extra['check-framework'] = false;
			return [];
		}
		*/
		$data = parent::_get_header($extra);

		$data['theme'] .= $GLOBALS['egw_info']['user']['preferences']['common']['darkmode'] ? 'data-darkmode="1"' : '';
		$data['kdots_theme'] = 'kdots-' . $GLOBALS['egw_info']['user']['preferences']['common']['theme'];
		unset($data['darkmode']);

		if($extra['navbar-apps'])
		{
			// Enable / disable framework features for each app
			// Feature name => EGw hook name
			$hooks = ['preferences' => 'settings', 'appConfig' => 'admin', 'categories' => 'categories',
					  'aclRights'   => 'acl_rights'];
			array_walk($extra['navbar-apps'], function (&$item, $key) use (&$hooks)
			{
				// Fix wrong icon type
				if(!$item['icon'] || !str_ends_with($item['icon'], 'svg'))
				{
					$item['icon'] = Image::find('api', 'navbar');
				}
				// Enable preferences etc. for app
				foreach($hooks as $feature_name => $hookname)
				{
					$item['features'][$feature_name] = Hooks::exists($hookname, $item['name']);
				}
			});
			$data['application-list'] = htmlentities(json_encode($extra['navbar-apps'], JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
			$open_app = current(array_filter($extra['navbar-apps'], function ($app)
			{
				return $app['active'] ?? false;
			})) ?? [];
			$data['open_app_name'] = $open_app['name'];
			$data['open_app_url'] = $open_app['url'];
		}
		if($data['open_app_name'] && !$this->sidebox_done)
		{
			$this->do_sidebox();
			$data['setSidebox'] = htmlentities(json_encode(static::$extra['setSidebox'], JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
		}
		return $data;
	}

	function topmenu(array $vars, array $apps)
	{
		// array of header info items (orders of the items matter)
		$topmenu_info_items = [
			'user_avatar'   => $this->_user_avatar_menu(),
			'update'        => ($update = Updates::notification()) ? $update : null,
			'notifications' => ($GLOBALS['egw_info']['user']['apps']['notifications']) ? static::_get_notification_bell() : null,
			'quick_add'     => $vars['quick_add'],
			'darkmode'      => static::_darkmode_menu(),
		];

		// array of Avatar menu items (orders of the items matter)
		$avatar_menu_items = [
			0 => (is_array(($current_user = $this->_current_users()))) ? $current_user : null,
		];

		// array of topmenu preferences items (orders of the items matter)
		$topmenu_preferences = ['prefs', 'acl', 'useraccount', 'security'];

		// set avatar menu items
		foreach($topmenu_preferences as $prefs)
		{
			$this->add_preferences_topmenu($prefs);
		}

		// call topmenu info items hooks
		Hooks::process('topmenu_info', array(), true);

		// Add extra items added by hooks
		foreach(self::$top_menu_extra as $extra_item)
		{
			array_push($avatar_menu_items, $extra_item);
		}
		// push logout as the last item in topmenu items list
		array_push($avatar_menu_items, $apps['logout']);

		// set topmenu info items
		foreach($topmenu_info_items as $id => $content)
		{
			if(!$content || (in_array($id, ['search', 'quick_add', 'update', 'quick_add', 'darkmode',
											'print_title']) && (UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'fw_mobile')))
			{
				continue;
			}
			$this->_add_topmenu_info_item($content, $id);
		}
		// set topmenu items
		foreach($avatar_menu_items as $item)
		{
			if($item)
			{
				$this->_add_topmenu_item($item);
			}
		}

		$vars['topmenu_items'] = "<sl-menu id='egw_fw_topmenu_items'>" . implode("\n", $this->topmenu_items) . "</sl-menu>";
		$vars['topmenu_info_items'] = '';
		foreach($this->topmenu_info_items as $id => $item)
		{
			switch($id)
			{
				case 'user_avatar':
					$vars['topmenu_info_items'] .= "<sl-dropdown class=\"topmenu_info_item\" id=\"topmenu_info_{$id}\" aria-label='" . lang("User menu") . "' tabindex='0'><div slot='trigger'>$item</div> {$vars['topmenu_items']}</sl-dropdown>";
					break;
				case 'notifications':
				case 'darkmode':
				case 'quick_add':
					$vars['topmenu_info_items'] .= $item;
					break;
				default:
					$vars['topmenu_info_items'] .= '<button class="topmenu_info_item"' .
						(is_numeric($id) ? '' : ' id="topmenu_info_' . $id . '"') . '>' . $item . "</button>\n";
			}

		}
		$this->topmenu_items = $this->topmenu_info_items = null;

		return $vars;
	}

	protected function add_preferences_topmenu($type = 'prefs')
	{
		static $memberships = null;
		if(!isset($memberships))
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
		}
		if(!$GLOBALS['egw_info']['user']['apps']['preferences'] || $GLOBALS['egw_info']['server']['deny_' . $type] &&
			array_intersect($memberships, (array)$GLOBALS['egw_info']['server']['deny_' . $type]) &&
			!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			return;    // user has no access to Preferences app
		}
		static $types = array(
			'prefs'       => array(
				'title' => 'Preferences',
				'hook'  => 'settings',
				'icon'  => 'preferences',
			),
			'acl'         => array(
				'title' => 'Access',
				'hook'  => 'acl_rights',
				'icon'  => 'lock',
			),
			'useraccount' => array(
				'title' => 'My Account',
				'hook'  => 'user_account',
				'icon'  => 'addressbook/accounts',
			),
			'cats'        => array(
				'title'    => 'Categories',
				'hook'     => 'categories',
				'run_hook' => true,    // acturally run hook, not just look it's implemented
				'icon'     => 'tag',
			),
			'security'    => array(
				'title' => 'Security & Password',
				'hook'  => 'preferences_security',
				'icon'  => 'key',
			),
		);

		if(isset($types[$type]['run_hook']))
		{
			$apps = Hooks::process($types[$type]['hook']);
			// as all apps answer, we need to remove none-true responses
			foreach($apps as $app => $val)
			{
				if(!$val)
				{
					unset($apps[$app]);
				}
			}
		}
		else
		{
			$apps = Hooks::implemented($types[$type]['hook']);
		}
		if(!$apps)
		{
			return;
		}

		switch($type)
		{
			case 'security':
				// always display password in topmenu, if user has rights to change it
				if($GLOBALS['egw_info']['server']['2fa_required'] !== 'disabled' ||
					!$GLOBALS['egw']->acl->check('nopasswordchange', 1))
				{
					$this->_add_topmenu_item(array(
												 'id'    => 'password',
												 'name'  => 'preferences',
												 'title' => lang($types[$type]['title']),
												 'url'   => 'javascript:egw.open_link("' .
													 self::link('/index.php?menuaction=preferences.preferences_password.change') . '","_blank","850x580")',
												 'icon'  => $types[$type]['icon'],
											 ));
				}
				break;
			default:
				$this->_add_topmenu_item(array(
											 'id'    => $type,
											 'name'  => 'preferences',
											 'icon'  => $types[$type]['icon'],
											 'title' => lang($types[$type]['title']),
											 'url'   => "javascript:egw.show_preferences(\"$type\",[],\"common\")",
										 ));
		}
	}

	/**
	 * Add info items to the topmenu template class to be displayed
	 *
	 * @param string $content Api\Html of item
	 * @param string $id = null
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_info_item($content, $id = null)
	{
		if(in_array($id, ['print_title']))
		{
			return;
		}

		if(strpos($content, 'menuaction=admin.admin_accesslog.sessions') !== false)
		{
			$content = preg_replace('/href="([^"]+)"/', "href=\"javascript:egw_link_handler('\\1','admin')\"", $content);
		}
		if($id)
		{
			$this->topmenu_info_items[$id] = $content;
		}
		else
		{
			$this->topmenu_info_items[] = $content;
		}
	}

	/**
	 * Add menu items to the topmenu template class to be displayed
	 *
	 * @param array $app application data
	 * @param mixed $alt_label string with alternative menu item label default value = null
	 * @param string $urlextra string with alternate additional code inside <a>-tag
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_item(array $app_data, $alt_label = null)
	{
		switch($app_data['name'])
		{
			case 'manual':
				$app_data['url'] = "javascript:callManual();";
				break;

			default:
				if(Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile')
				{
					break;
				}
				if(strpos($app_data['url'], 'logout.php') === false && substr($app_data['url'], 0, 11) != 'javascript:')
				{
					$app_data['url'] = "javascript:egw_link_handler('" . $app_data['url'] . "','" .
						(isset($GLOBALS['egw_info']['user']['apps'][$app_data['name']]) ?
							$app_data['name'] : 'about') . "')";
				}
		}
		$id = $app_data['id'] ? $app_data['id'] : ($app_data['name'] ? $app_data['name'] : $app_data['title']);
		$title = htmlspecialchars($alt_label ? $alt_label : $app_data['title']);
		$this->topmenu_items[] = '<sl-menu-item id="topmenu_' . $id . '" value="' . htmlspecialchars($app_data['url']) . '" title="' . $app_data['title'] . '">' .
			"<et2-image slot='prefix' src='${app_data['icon']}'></et2-image>" .
			$title .
			'</sl-menu-item>';
	}

	/**
	 * Set site-wide CSS like preferred font-size
	 *
	 * @param array $themes_to_check
	 * @return array
	 * @see Api\Framework::_get_css()
	 */
	public function _get_css(array $themes_to_check = array())
	{
		$themes_to_check[] = $this->template_dir . '/css/themes/' . $GLOBALS['egw_info']['user']['preferences']['common']['theme'] . '.css';
		$ret = parent::_get_css($themes_to_check);

		$textsize = $GLOBALS['egw_info']['user']['preferences']['common']['textsize'] ?? '12';
		$ret['app_css'] .= "
			:root, :host, body, input {
				font-size: {$textsize}px;
				font-family: egroupware, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 
					Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
			}
		";

		return $ret;
	}


	/**
	 * Returns darkmode menu
	 *
	 * @return string
	 */
	protected static function _darkmode_menu()
	{
		$mode = $GLOBALS['egw_info']['user']['preferences']['common']['darkmode'] == 1 ? 'dark' : 'light';
		return '<egw-darkmode-toggle title="' . lang("%1 mode", $mode) . '" class="' .
			($mode == 'dark' ? 'darkmode_on' : '') . '"' . ($mode == 'dark' ? 'darkmode' : '') .
			' label="' . lang('Dark mode') . '"> </egw-darkmode-toggle>';
	}

	/**
	 * Prepare notification signal (blinking bell)
	 *
	 * TODO: THis is copied from pixelegg, and is not accessible
	 *
	 *
	 * @return string
	 */
	protected static function _get_notification_bell()
	{
		// This should all be handled by notification app
		$path = "../../notifications/js/Et2NotificationBell.js";
		self::includeJS($path . '?' . filemtime(EGW_SERVER_ROOT . $path));
		return '<et2-button-icon label="' . lang('notifications') . '" id="topmenu_info_notifications" name="bell-fill" nosubmit></et2-button-icon>';
	}


	/**
	 * Prepare the quick add selectbox
	 *
	 * @return string
	 */
	protected static function _get_quick_add()
	{
		return '<et2-button-icon id="quick_add" title="' . lang('Quick add') . '" name="plus-circle" nosubmit></et2-button-icon>';
	}
}