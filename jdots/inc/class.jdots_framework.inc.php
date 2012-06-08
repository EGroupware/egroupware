<?php
/**
 * Stylite: jdots template
 *
 * @link http://www.stylite.de
 * @package jdots
 * @author Andreas Stöckel <as@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @author Nathan Gray <ng@stylite.de>
 * @version $Id$
 */

/**
* Stylite jdots template
*/
class jdots_framework extends egw_framework
{
	const MIN_SIDEBAR_WIDTH = 185;
	const DEFAULT_SIDEBAR_WIDTH = 225;
	/**
	 * Whether javascript:egw_link_handler calls (including given app) should be returned by the "link" function
	 * or just the link
	 *
	 * @var string
	 */
	private static $link_app;

	/**
	* Constructor
	*
	* @param string $template='idots' name of the template
	* @return idots_framework
	*/
	function __construct($template='jdots')
	{
		parent::__construct($template);		// call the constructor of the extended class

		$this->template_dir = '/jdots';		// we are packaged as an application
	}

	/**
	 * Check if current user agent is supported
	 *
	 * Currently we do NOT support:
	 * - iPhone, iPad, Android, SymbianOS due to iframe scrolling problems of Webkit
	 * - IE < 7
	 *
	 * @return boolean
	 */
	public static function is_supported_user_agent()
	{
		if (html::$ua_mobile || html::$user_agent == 'msie' && html::$ua_version < 7)
		{
			return false;
		}
		return true;
	}

	/**
	 * Reads an returns the width of the sidebox or false if the width is not set
	 */
	private static function get_sidebar_width($app)
	{
		//If the global_sidebar_width option is set, we'll simply return false
		if ($GLOBALS['egw_info']['user']['preferences']['common']['app_specific_sidebar_width'])
		{
			$width = self::DEFAULT_SIDEBAR_WIDTH;

			//Check whether the width had been stored explicitly for the jdots template, use that value
			if ($GLOBALS['egw_info']['user']['preferences'][$app]['jdotssideboxwidth'])
			{
				$width = (int)$GLOBALS['egw_info']['user']['preferences'][$app]['jdotssideboxwidth'];
//				error_log(__METHOD__.__LINE__."($app):$width --> reading jdotssideboxwidth");
			}
			//Otherwise use the legacy "idotssideboxwidth" value
			else if ($GLOBALS['egw_info']['user']['preferences'][$app]['idotssideboxwidth'])
			{
				$width = (int)$GLOBALS['egw_info']['user']['preferences'][$app]['idotssideboxwidth'];
//				error_log(__METHOD__.__LINE__."($app):$width --> reading idotssideboxwidth");
			}

			//Width may not be smaller than MIN_SIDEBAR_WIDTH
			if ($width < self::MIN_SIDEBAR_WIDTH)
				$width = self::MIN_SIDEBAR_WIDTH;

			return $width;
		}
		return false;
	}

	/**
	 * Returns the global width of the sidebox. If the app_specific_sidebar_width had been switched
	 * on, the default width will be returned
	 */
	private static function get_global_sidebar_width()
	{
		if (!$GLOBALS['egw_info']['user']['preferences']['common']['app_specific_sidebar_width'] &&
		    $GLOBALS['egw_info']['user']['preferences']['common']['global_sidebar_width_value'])
		{
			return $GLOBALS['egw_info']['user']['preferences']['common']['global_sidebar_width_value'];
		}

		return self::DEFAULT_SIDEBAR_WIDTH;
	}


	/**
	 * Sets the sidebox width accoringly to the app_specific_sidebar_width setting, either
     * in the current application or globaly
	 */
	private static function set_sidebar_width($app, $val)
	{
		$GLOBALS['egw']->preferences->read_repository();
		if ($GLOBALS['egw_info']['user']['preferences']['common']['app_specific_sidebar_width'])
		{
//			error_log(__METHOD__.__LINE__."($app, $val) --> setting jdotssideboxwidth");
			$GLOBALS['egw']->preferences->change($app, 'jdotssideboxwidth', $val);
		}
		else
		{
//			error_log(__METHOD__.__LINE__."($app, $val) --> setting global sidebar width value");
			$GLOBALS['egw']->preferences->change('common', 'global_sidebar_width_value', $val);
		}
		$GLOBALS['egw']->preferences->save_repository(True);
	}

	/**
	 * Extract applicaton name from given url (incl. GET parameters)
	 *
	 * @param string $url
	 * @return string appname or NULL if it could not be detected (eg. constructing javascript urls)
	 */
	public static function app_from_url($url)
	{
		if (preg_match('/menuaction=([a-z0-9_-]+)\./i',$url,$matches))
		{
			return $matches[1];
		}
		if (strlen($GLOBALS['egw_info']['server']['webserver_url']) > 1)
		{
			list(,$url) = explode(parse_url($GLOBALS['egw_info']['server']['webserver_url'],PHP_URL_PATH),
				parse_url($url,PHP_URL_PATH),2);
		}
		if (preg_match('/\/([^\/]+)\/([^\/]+\.php)?(\?|\/|$)/',$url,$matches))
		{
			return $matches[1];
		}
		//error_log(__METHOD__."('$url') could NOT detect application!");
		return null;
	}

	/**
	 * Link url generator
	 *
	 * @param string	$string	The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 * @param string $link_app=null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function link($url = '', $extravars = '', $link_app=null)
	{
		if (is_null($link_app)) $link_app = self::$link_app;
		$link = parent::link($url,$extravars);

		// $link_app === true --> detect application, otherwise use given application
		if ($link_app && (is_string($link_app) || ($link_app = self::app_from_url($link))))
		{
			// Link gets handled in JS, so quotes need slashes as well as url-encoded
			// encoded ampersands in get parameters (%26) need to be encoded twise,
			// so they are still encoded when assigned to window.location
			$link = str_replace(array('%27','%26'), array('\%27','%2526'), $link);

			$link = "javascript:window.egw_link_handler?egw_link_handler('$link','$link_app'):parent.egw_link_handler('$link','$link_app');";
		}
		return $link;
	}

	/**
	 * Redirects direct to a generated link
	 *
	 * If a session could not be verified or during login time, jDots is NOT used!
	 * It's only used if user preferences are loaded AND user select it in his prefs
	 *
	 * @param string	$string	The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 */
	static function redirect_link($url = '',$extravars='')
	{
		return parent::redirect_link($url, $extravars);
	}

	/**
	 * Overwrite to add our customizable colors
	 *
	 * @see egw_framework::_get_css()
	 * @return array
	 */
	public function _get_css()
	{
		$ret = parent::_get_css();

		// color to use
		$color = str_replace('custom',$GLOBALS['egw_info']['user']['preferences']['common']['template_custom_color'],
			$GLOBALS['egw_info']['user']['preferences']['common']['template_color']);
		// use active tab or header, beside sidebox
		if (($use_active_tab = $color[0] == '@')) $color = substr($color,1);

		if (preg_match('/^(#[0-9A-F]+|[A-Z]+)$/i',$color))	// a little xss check
		{
			$ret['app_css'] .= "
/**
 * theme changes to color jdots for color: $color
 */
.egw_fw_ui_sidemenu_entry_header_active, .egw_fw_ui_sidemenu_entry_content, .egw_fw_ui_sidemenu_entry_header:hover {
	background-color: $color;
	border-color: $color;
}
.egw_fw_ui_sidemenu_entry_header_active, .egw_fw_ui_sidemenu_entry_header:hover {
	background-image: url(jdots/images/gradient30transparent.png);
}
.egw_fw_ui_sidemenu_entry_content {
	background-image: url(jdots/images/gradient10transparent.png);
}
div .egw_fw_ui_sidemenu_entry_content > div {
	background-color: #ffffff;
}".($use_active_tab ? "
.egw_fw_ui_tab_header_active {
	background-image: url(jdots/images/gradient30transparent.png);
	background-color: $color;
}
" : "
.egw_fw_ui_tabs_header {
	background-image: url(jdots/images/gradient22transparent.png);
	background-color: $color;
}");
		}
		return $ret;
	}

	/**
	 * Returns the html-header incl. the opening body tag
	 *
	 * @return string with html
	 */
	function header()
	{
		// make sure header is output only once
		if (self::$header_done) return '';
		self::$header_done = true;
		// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
		header('Content-type: text/html; charset='.translation::charset());

		// catch error echo'ed before the header, ob_start'ed in the header.inc.php
		$content = ob_get_contents();
		ob_end_clean();
		//echo __METHOD__.__LINE__.' loading ...<br> called from:'.function_backtrace().'<br>';

		// the instanciation of the template has to be here and not in the constructor,
		// as the old Template class has problems if restored from the session (php-restore)
		// todo: check if this is still true
		$this->tpl = new Template(common::get_tpl_dir('jdots'),'keep');
		$this->tpl->set_file(array('_head' => 'head.tpl'));
		$this->tpl->set_block('_head','head');
		$this->tpl->set_block('_head','framework');

		// should we draw the framework, or just a header
		$do_framework = isset($_GET['cd']) && $_GET['cd'] === 'yes';

		// load clientside link registry to framework only
		$GLOBALS['egw_info']['flags']['js_link_registry'] = $do_framework;

		if ($do_framework)
		{
			//echo __METHOD__.__LINE__.' do framework ...'.'<br>';
			// framework javascript classes only need for framework
			self::validate_file('jquery','jquery-ui');
			self::validate_file('.','widgets','etemplate');
			self::validate_file('.','egw_fw','jdots');
			self::validate_file('.','egw_fw_ui','jdots');
			self::validate_file('.','egw_fw_classes','jdots');

			egw_cache::unsetSession(__CLASS__,'sidebox_md5');	// sideboxes need to be send again

			// load jscalendar for calendar users
			if ($GLOBALS['egw_info']['user']['apps']['calendar'])
			{
				$GLOBALS['egw']->jscalendar;
			}
			// load dhtmlxtree for pm or email users
			if ($GLOBALS['egw_info']['user']['apps']['projectmanager'] || $GLOBALS['egw_info']['user']['apps']['felamimail'])
			{
				$GLOBALS['egw_info']['flags']['java_script'] .= html::tree(null,null);
			}
		}
		// for an url WITHOUT cd=yes --> load framework if not yet loaded:
		// - check if iframe parent (top) has a framework loaded or
		// - we are a popup (opener) or
		// - we are an iframe in a popup (top.opener)
		// - we are the (remote) manual
		if(!$do_framework)
		{
			// for remote manual never check/create framework
			if ($GLOBALS['egw_info']['flags']['currentapp'] != 'manual')
			{
				if (empty($GLOBALS['egw_info']['flags']['java_script'])) $GLOBALS['egw_info']['flags']['java_script']='';
				$GLOBALS['egw_info']['flags']['java_script'] .= '<script type="text/javascript">
	if (typeof top.framework == "undefined" && !opener && !top.opener)
	{
		window.location.search += window.location.search ? "&cd=yes" : "?cd=yes";
	}
	window.egw_appName = "'.$GLOBALS['egw_info']['flags']['currentapp'].'";
</script>';
			}
			// app header for print (different from website_title, which also contains app header)
			if ($GLOBALS['egw_info']['flags']['app_header'])
			{
				$app_header = $GLOBALS['egw_info']['flags']['app_header'];
			}
			else
			{
				$app = $GLOBALS['egw_info']['flags']['currentapp'];
				$app_header = isset($GLOBALS['egw_info']['apps'][$app]) ? $GLOBALS['egw_info']['apps'][$app]['title'] : lang($app);
			}
		}
		$this->tpl->set_var('app_header',(string)$app_header);
		$this->tpl->set_var($vars = $this->_get_header());
		$content = $this->tpl->fp('out','head').$content;

		// for remote manual, do NOT try to reach parent windows, as it's from a different domain (manual.egroupware.org)
		if (!$do_framework && $GLOBALS['egw_info']['flags']['currentapp'] != 'manual')
		{
			// set app_header
			$app = $GLOBALS['egw_info']['flags']['currentapp'];
			$content .= '<script type="text/javascript">
	if (typeof window.parent.framework != "undefined")
	{
		window.parent.framework.setWebsiteTitle(egw_getApp("'.$app.'"),"'.htmlspecialchars($vars['website_title']).'","'.$app_header.'");
	}';

			//Register the global key press handler
/*			$content .= "
	window.keyPressHandler = function(event) {
		if (event.keyCode == 112)
		{
			event.preventDefault();
			window.callManual();
		}
	}
	$j(document).keypress(keyPressHandler);\n";
*/
			// if manual is enabled, assamble manual url and define global callManual() function
			if ($GLOBALS['egw_info']['user']['apps']['manual'])
			{
				$manual_url = egw::link('/index.php',array(
						'menuaction' => 'manual.uimanual.view',
					)+($GLOBALS['egw_info']['flags']['params']['manual'] ?
					$GLOBALS['egw_info']['flags']['params']['manual'] : array(
						'referer' => ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
					)));
				$content .= '
	window.callManual = function(_url)
	{
		var _framework = window.opener ? window.opener.parent.framework :  window.parent.framework;
		if (typeof _url == "undefined" || !_url) _url = "'.$manual_url.'";
		_framework.linkHandler(_url,"manual");
	}';
			}
			$content .= "\n</script>";
		}
		if (!$do_framework)
		{
			return $content;
		}

		// from here on, only framework
		if ($GLOBALS['egw_info']['user']['apps']['manual'])
		{
			$content .= '<script type="text/javascript">
	window.callManual = function(_url)
	{
		framework.callManual();
	}
</script>';
		}
		// topmenu
		$vars = $this->_get_navbar($apps = $this->_get_navbar_apps());
		$this->tpl->set_var($this->topmenu($vars,$apps));

		// hook after_navbar (eg. notifications)
		$this->tpl->set_var('hook_after_navbar',$this->_get_after_navbar());

		//Global sidebar width
		$this->tpl->set_var('sidebox_width', self::get_global_sidebar_width());
		$this->tpl->set_var('sidebox_min_width', self::MIN_SIDEBAR_WIDTH);

		// logout button
		$this->tpl->set_var('title_logout', lang("Logout"));
		$this->tpl->set_var('link_logout', egw::link('/logout.php'));

		//Print button title
		$this->tpl->set_var('title_print', lang("Print current view"));

		// add framework div's
		$this->tpl->set_var($this->_get_footer());
		$content .= $this->tpl->fp('out','framework');
		$content .= self::footer(false);

		echo $content;
		common::egw_exit();
	}

	private $topmenu_items;
	private $topmenu_info_items;

	/**
	 * Compile entries for topmenu:
	 * - regular items: links
	 * - info items
	 *
	 * @param array $vars
	 * @param array $apps
	 * @return array
	 */
	function topmenu(array $vars,array $apps)
	{
		$this->topmenu_items = $this->topmenu_info_items = array();

		parent::topmenu($vars,$apps);
		$vars['topmenu_items'] = "<ul>\n<li>".implode("</li>\n<li>",$this->topmenu_items)."</li>\n</ul>";
		$vars['topmenu_info_items'] = '<div class="topmenu_info_item">'.
			implode("</div>\n".'<div class="topmenu_info_item">',$this->topmenu_info_items)."</div>\n";

		$this->topmenu_items = $this->topmenu_info_items = null;

		return $vars;
	}

	/**
	* called by hooks to add an icon in the topmenu info location
	*
	* @param string $id unique element id
	* @param string $icon_src src of the icon image. Make sure this nog height then 18pixels
	* @param string $iconlink where the icon links to
	* @param booleon $blink set true to make the icon blink
	* @param mixed $tooltip string containing the tooltip html, or null of no tooltip
	* @todo implement in a reasonable way for jdots
	* @return void
	*/
	function topmenu_info_icon($id,$icon_src,$iconlink,$blink=false,$tooltip=null)
	{
		// not yet implemented, only used in admin/inc/hook_topmenu_info.inc.php to notify about pending updates
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
	function _add_topmenu_item(array $app_data,$alt_label=null)
	{
		if ($app_data['name'] == 'manual')
		{
			$app_data['url'] = "javascript:callManual();";
		}
		elseif (strpos($app_data['url'],'logout.php') === false)
		{
			$app_data['url'] = "javascript:egw_link_handler('".$app_data['url']."','".
				(isset($GLOBALS['egw_info']['user']['apps'][$app_data['name']]) ?
					$app_data['name'] : 'about')."')";
		}
		$this->topmenu_items[] = '<a href="'.htmlspecialchars($app_data['url']).'">'.
			htmlspecialchars($alt_label ? $alt_label : $app_data['title']).'</a>';
	}

	/**
	 * Add info items to the topmenu template class to be displayed
	 *
	 * @param string $content html of item
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_info_item($content)
	{
		if (strpos($content,'tz_selection') !== false)
		{
			$content = preg_replace('/onchange="[^"]+"/','onchange="framework.tzSelection(this.value); return false;"',$content);
		}
		elseif(strpos($content,'menuaction=admin.admin_accesslog.sessions') !== false)
		{
			$content = preg_replace('/href="([^"]+)"/',"href=\"javascript:egw_link_handler('\\1','admin')\"",$content);
		}
		$this->topmenu_info_items[] = $content;
	}

	/**
	 * Change timezone
	 *
	 * @param string $tz
	 */
	function ajax_tz_selection($tz)
	{
		egw_time::setUserPrefs($tz);	// throws exception, if tz is invalid

		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->add('common','tz',$tz);
		$GLOBALS['egw']->preferences->save_repository();
	}

	/**
	 * Returns the html from the body-tag til the main application area (incl. opening div tag)
	 *
	 * jDots does NOT use a navbar, but we use this to send the sidebox content!
	 *
	 * We store in the session the md5 of each sidebox menu already send to client.
	 * If the framework get reloaded, that list gets cleared in header();
	 * Most apps never change sidebox, so we not even need to generate it more then once.
	 *
	 * @return string with javascript to set sidebox
	 */
	function navbar()
	{
		$app = $GLOBALS['egw_info']['flags']['currentapp'];

		// only send admin sidebox, for admin index url (when clicked on admin),
		// not for other admin pages, called eg. from sidebox menu of other apps
		// --> that way we always stay in the app, and NOT open admin sidebox for an app tab!!!
		if ($app == 'admin' && substr($_SERVER['PHP_SELF'],-16) != '/admin/index.php')
		{
			return $this->header();
		}
		$md5_session =& egw_cache::getSession(__CLASS__,'sidebox_md5');

		//Set the sidebox content
		$sidebox = json_encode($this->get_sidebox($app));
		$md5 = md5($sidebox);

		$header = $this->header();	// in case it's not yet called (call it now AFTER get_sidebox())

		if ($md5_session[$app] === $md5)
		{
			//error_log(__METHOD__."() md5_session[$app]==='$md5' --> nothing to do");
			return $header;	// no need to send to client
		}
		$md5_session[$app] = $md5;	// update md5 in session

		return $header.'<script type="text/javascript">
	$j(document).ready(function() {
		if (typeof window.parent.framework != "undefined")
		{
			window.parent.framework.setSidebox(egw_getApp("'.$app.'"),'.$sidebox.',"'.$md5.'");
		}
	});
</script>';
	}

	/**
	 * displays a login screen
	 *
	 * Currently not used for jDots, as it's no login template set!
	 *
	 * @param string $extra_vars for login url
	 */
	function login_screen($extra_vars)
	{

	}

	/**
	 * displays a login denied message
	 *
	 * Currently not used for jDots, as it's no login template set!
	 */
	function denylogin_screen()
	{

	}

	/**
	 * Array containing sidebox menus by applications and menu-name
	 *
	 * @var array
	 */
	private $sideboxes;

	/**
	 * Should calls the first call to self::sidebox create an opened menu
	 *
	 * @var boolean
	 */
	private $sidebox_menu_opened = true;

	/**
	 * Callback for sideboxes hooks, collects the data in a private var
	 *
	 * @param string $appname
	 * @param string $menu_title
	 * @param array $file
	 */
	public function sidebox($appname,$menu_title,$file)
	{
		if (!isset($file['menuOpened'])) $file['menuOpened'] = (boolean)$this->sidebox_menu_opened;
		$this->sidebox_menu_opened = false;

		$this->sideboxes[$appname][$menu_title] = $file;
	}

	/**
	 * Return sidebox data for an application
	 *
	 * @param $appname
	 * @return array of array(
	 * 		'menu_name' => (string),	// menu name, currently md5(title)
	 * 		'title'     => (string),	// translated title to display
	 * 		'opened'    => (boolean),	// menu opend or closed
	 *  	'entries'   => array(
	 *			array(
	 *				'lang_item' => translated menu item or html, i item_link === false
	 * 				'icon_or_star' => url of bullet images, or false for none
	 *  			'item_link' => url or false (lang_item contains complete html)
	 *  			'target' => target attribute fragment, ' target="..."'
	 *			),
	 *			// more entries
	 *		),
	 * 	),
	 *	array (
	 *		// next menu
	 *	)
	 */
	public function get_sidebox($appname)
	{
		if (!isset($this->sideboxes[$appname]))
		{
			self::$link_app = $appname;
			// allow other apps to hook into sidebox menu of an app, hook-name: sidebox_$appname
			$this->sidebox_menu_opened = true;
			$GLOBALS['egw']->hooks->process('sidebox_'.$appname,array($appname),true);	// true = call independent of app-permissions

			// calling the old hook
			$this->sidebox_menu_opened = true;
			$GLOBALS['egw']->hooks->single('sidebox_menu',$appname);
			self::$link_app = null;

			// allow other apps to hook into sidebox menu of every app: sidebox_all
			$GLOBALS['egw']->hooks->process('sidebox_all',array($GLOBALS['egw_info']['flags']['currentapp']),true);
		}
		//If there still is no sidebox content, return null here
		if (!isset($this->sideboxes[$appname]))
		{
			return null;
		}

		$data = array();
		foreach($this->sideboxes[$appname] as $menu_name => &$file)
		{
			$current_menu = array(
				'menu_name' => md5($menu_name),	// can contain html tags and javascript!
				'title' => $menu_name,
				'entries' => array(),
				'opened' => (boolean)$file['menuOpened'],
			);
			foreach($file as $item_text => $item_link)
			{
				if ($item_text === 'menuOpened' ||	// flag, not menu entry
					$item_text === '_NewLine_' || $item_link === '_NewLine_')
				{
					continue;
				}
				if (strtolower($item_text) == 'grant access' && $GLOBALS['egw_info']['server']['deny_user_grants_access'])
				{
					continue;
				}

				$var = array();
				$var['icon_or_star'] = $GLOBALS['egw_info']['server']['webserver_url'] . $this->template_dir.'/images/bullet.png';
				$var['target'] = '';
				if(is_array($item_link))
				{
					if(isset($item_link['icon']))
					{
						$app = isset($item_link['app']) ? $item_link['app'] : $appname;
						$var['icon_or_star'] = $item_link['icon'] ? common::image($app,$item_link['icon']) : False;
					}
					$var['lang_item'] = isset($item_link['no_lang']) && $item_link['no_lang'] ? $item_link['text'] : lang($item_link['text']);
					$var['item_link'] = $item_link['link'];
					if ($item_link['target'])
					{
						// we only support real targets not html markup with target in it
						if (strpos($item_link['target'], 'target=') === false &&
							strpos($item_link['target'], '"') === false)
						{
							$var['target'] = $item_link['target'];
						}
					}
				}
				else
				{
					$var['lang_item'] = lang($item_text);
					$var['item_link'] = $item_link;
				}
				$current_menu['entries'][] = $var;
			}
			$data[] = $current_menu;
		}
		return $data;
	}

	/**
	 * Ajax callback which is called whenever a previously opened tab is closed or
	 * opened.
	 *
	 * @param $tablist is an array which contains each tab as an associative array
	 *   with the keys 'appName' and 'active'
	 */
	public function ajax_tab_changed_state($tablist)
	{
		$tabs = array();
		foreach($tablist as $data)
		{
			$tabs[] = $data['appName'];
			if ($data['active']) $active = $data['appName'];
		}
		// send app a notification, that it's tab got closed
		// used eg. in phpFreeChat to leave the chat
		if (($old_tabs =& egw_cache::getSession(__CLASS__, 'open_tabs')))
		{
			foreach(array_diff(explode(',',$old_tabs),$tabs) as $app)
			{
				//error_log("Tab '$app' closed, old_tabs=$old_tabs");
				$GLOBALS['egw']->hooks->single(array(
					'location' => 'tab_closed',
					'app' => $app,
				), $app);
			}
		}
		$tabs = $old_tabs = implode(',',$tabs);

		if ($tabs != $GLOBALS['egw_info']['user']['preferences']['common']['open_tabs'] ||
			$active != $GLOBALS['egw_info']['user']['preferences']['common']['active_tab'])
		{
			//error_log(__METHOD__.'('.array2string($tablist).") storing common prefs: open_tabs='$tabs', active_tab='$active'");
			$GLOBALS['egw']->preferences->read_repository();
			$GLOBALS['egw']->preferences->change('common', 'open_tabs', $tabs);
			$GLOBALS['egw']->preferences->change('common', 'active_tab', $active);
			$GLOBALS['egw']->preferences->save_repository(true);
		}
	}

	/**
	 * Ajax callback to store opened/closed status of menu's within one apps sidebox
	 *
	 * @param string $app
	 * @param string $menu_name
	 * @param boolean $opened true = user opened menu, false = user closed it
	 * @todo implement storing and using stored values in get_sidebox
	 */
	public function ajax_sidebox_menu_opened($app,$menu_name,$opened)
	{
		//error_log(__METHOD__."('$app','$menu_name',$opened)");
	}

	/**
	 * Return sidebox data for an application
	 *
	 * Format see get_sidebox()
	 *
	 * @param $appname
	 */
	public function ajax_sidebox($appname, $md5)
	{
		$response = egw_json_response::get();
		$sidebox = $this->get_sidebox($appname);
		$encoded = json_encode($sidebox);
		$new_md5 = md5($encoded);

		$response_array = array();
		$response_array['md5'] = $new_md5;

		if ($new_md5 != $md5)
		{
			//TODO: Add some proper solution to be able to attach the already
			//JSON data to the response in order to gain some performace improvements.
			$response_array['data'] = $sidebox;
		}

		$response->data($response_array);
	}

	/**
	 * Stores the width of the sidebox menu depending on the sidebox menu settings
	 * @param $appname the name of the application
	 * @param $width the width set
	 */
	public function ajax_sideboxwidth($appname, $width)
	{
		//error_log(__METHOD__."($appname, $width)");
		//Check whether the supplied parameters are valid
		if (is_int($width) && $GLOBALS['egw_info']['user']['apps'][$appname])
		{
			self::set_sidebar_width($appname, $width);
		}
	}

	/**
	 * Stores the user defined sorting of the applications inside the preferences
	 *
	 * @param array $apps
	 */
	public function ajax_appsort(array $apps)
	{
		$order = array();
		$i = 0;

		//Parse the "$apps" array for valid content (security)
		foreach($apps as $app)
		{
			//Check whether the app really exists and add it to the $app_arr var
			if ($GLOBALS['egw_info']['user']['apps'][$app])
			{
				$order[$app] = $i;
				$i++;
			}
		}

		//Store the order array inside the common user preferences
		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->change('common', 'user_apporder', serialize($order));
		$GLOBALS['egw']->preferences->save_repository(true);
	}

	/**
	 * Reads all available remote applications (currently there may only be one)
	 * and returns them as an array.
	 */
	public function jdots_remote_apps()
	{
		$result = array();

		/*if ($GLOBALS['egw_info']['user']['preferences']['common']['remote_application_enabled'])
		{
			$name = $GLOBALS['egw_info']['user']['preferences']['common']['remote_application_name'];
			$title = $GLOBALS['egw_info']['user']['preferences']['common']['remote_application_title'];
			$url = $GLOBALS['egw_info']['user']['preferences']['common']['remote_application_url'];

			$result[$name.'_remote'] = array(
				'name' => $name.'_remote',
				'sideboxwidth' => false,
				'target' => false,
				'title' => $title,
				'icon' => "/egroupware/trunk/egroupware/".$name."/templates/default/images/navbar.png", //TODO: Check whether icon exists
				'baseUrl' => $url,
				'url' => $url.$name.'/index.php',
				'internalName' => $name,
			);
		}*/

		return $result;
	}

	/**
	 * Prepare an array with apps used to render the navbar
	 *
	 * @return array of array(
	 *  'name'  => app / directory name
	 * 	'title' => translated application title
	 *  'url'   => url to call for index
	 *  'icon'  => icon name
	 *  'icon_app' => application of icon
	 *  'icon_hover' => hover-icon, if used by template
	 *  'target'=> ' target="..."' attribute fragment to open url in target, popup or ''
	 * )
	 */
	public function navbar_apps()
	{
		$apps = parent::_get_navbar_apps();
		$apps += $this->jdots_remote_apps();

		//Add its sidebox width to each app
		foreach ($apps as $key => $value)
		{
			$apps[$key]['sideboxwidth'] = self::get_sidebar_width($key);
		}

		unset($apps['logout']);	// never display it
		if (isset($apps['about'])) $apps['about']['noNavbar'] = true;
		if (isset($apps['preferences'])) $apps['preferences']['noNavbar'] = true;
		if (isset($apps['manual'])) $apps['manual']['noNavbar'] = true;
		if (isset($apps['home'])) $apps['home']['noNavbar'] = true;

		// no need for website icon, if we have sitemgr
		if (isset($apps['sitemgr']) && isset($apps['sitemgr-link']))
		{
			unset($apps['sitemgr-link']);
		}
		return $apps;
	}

	/**
	 * Prepare an array with apps used to render the navbar
	 *
	 * @param string $url contains the current url on the client side. It is used to
	 *  determine whether the default app/home should be opened on the client
	 *  or whether a specific application-url has been given.
	 *
	 * @return array of array(
	 *  'name'  => app / directory name
	 * 	'title' => translated application title
	 *  'url'   => url to call for index
	 *  'icon'  => icon name
	 *  'icon_app' => application of icon
	 *  'icon_hover' => hover-icon, if used by template
	 *  'target'=> ' target="..."' attribute fragment to open url in target, popup or ''
	 *  'opened' => unset or false if the tab should not be opened, otherwise the numeric position in the tab list
	 *  'active' => true if this tab should be the active one when it is restored, otherwise unset or false
	 *  'openOnce' => unset or the url which will be opened when the tab is restored
	 * )
	 */
	public function ajax_navbar_apps($url)
	{
		$apps = $this->navbar_apps();

		// open tab for default app, if no other tab is set
		if (!($default_app = $GLOBALS['egw_info']['user']['preferences']['common']['default_app']))
		{
			$default_app = 'home';
		}
		if (isset($apps[$default_app]))
		{
			$apps[$default_app]['isDefault'] = true;
		}

		// check if user called a specific url --> open it as active tab
		$last_direct_url =& egw_cache::getSession(__CLASS__, 'last_direct_url');
		if ($url !== $last_direct_url)
		{
			$active_tab = $url_tab = self::app_from_url($url);
			$last_direct_url = $url;
		}

		//self::app_from_url might return an application the user has no rights
		//for or may return an application that simply does not exist. So check first
		//whether the $active_tab really exists in the $apps array.
		if ($active_tab && array_key_exists($active_tab, $apps))
		{
			$apps[$active_tab]['openOnce'] = preg_replace('/[&?]cd=yes/','',$url);
			$store_prefs = true;
		}
		else
		{
			$active_tab = $GLOBALS['egw_info']['user']['preferences']['common']['active_tab'];
			if (!$active_tab) $active_tab = $default_app;
		}
		// if we have the open tabs in the session, use it instead the maybe forced common prefs open_tabs
		if (!($open_tabs = egw_cache::getSession(__CLASS__, 'open_tabs')))
		{
			$open_tabs = $GLOBALS['egw_info']['user']['preferences']['common']['open_tabs'];
		}
		$open_tabs = $open_tabs ? explode(',',$open_tabs) : array();
		if ($active_tab && !in_array($active_tab,$open_tabs))
		{
			$open_tabs[] = $active_tab;
			$store_prefs = true;
		}
		if ($store_prefs)
		{
			$GLOBALS['egw']->preferences->read_repository();
			$GLOBALS['egw']->preferences->change('common', 'open_tabs', implode(',',$open_tabs));
			$GLOBALS['egw']->preferences->change('common', 'active_tab', $active_tab);
			$GLOBALS['egw']->preferences->save_repository(true);
		}

		//error_log(__METHOD__."('$url') url_tab='$url_tab', active_tab=$active_tab, open_tabs=".array2string($open_tabs));
		// Restore Tabs
		foreach($open_tabs as $n => $app)
		{
			if (isset($apps[$app]))		// user might no longer have app rights
			{
				$apps[$app]['opened'] = $n;
				if ($app == $active_tab)
				{
					$apps[$app]['active'] = true;
				}
			}
		}

		$response = egw_json_response::get();
		$response->data(array_values($apps));
	}

	/**
	 * Have we output the footer
	 *
	 * @var boolean
	 */
	static private $footer_done;

	/**
	 * Returns the html from the closing div of the main application area to the closing html-tag
	 *
	 * @param boolean $no_framework=true
	 * @return string
	 */
	function footer($no_framework=true)
	{
		if (self::$footer_done) return;	// prevent (multiple) footers
		self::$footer_done = true;
		if (!(!isset($GLOBALS['egw_info']['flags']['nofooter']) || !$GLOBALS['egw_info']['flags']['nofooter'])) return;
		//error_log(__METHOD__.array2string(function_backtrace()));

		if($no_framework && $GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
		{
			$vars = $this->_get_footer();
		}
		return "\n".$vars['page_generation_time']."\n".
			$GLOBALS['egw_info']['flags']['need_footer']."\n".	// eg. javascript, which need to be at the end of the page
			"</body>\n</html>\n";
	}

	/**
	 * Return javascript (eg. for onClick) to open manual with given url
	 *
	 * @param string $url
	 * @return string
	 */
	function open_manual_js($url)
	{
		return "callManual('$url')";
	}

	/**
	 * JSON reponse object
	 *
	 * If set output is requested for an ajax response --> no header, navbar or footer
	 *
	 * @var egw_json_response
	 */
	public $response;

	/**
	 * Run a link via ajax, returning content via egw_json_response->data()
	 *
	 * This behavies like /index.php, but returns the content via json.
	 *
	 * @param string $link
	 */
	function ajax_exec($link)
	{
		$parts = parse_url($link);
		$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $parts['path'];
		if ($parts['query'])
		{
			$_SERVER['REQUEST_URI'] = '?'.$parts['query'];
			parse_str($parts['query'],$_GET);
		}

		if (!isset($_GET['menuaction']))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."('$link') no menuaction set!");
		}
		list($app,$class,$method) = explode('.',$_GET['menuaction']);

		if (!isset($GLOBALS['egw_info']['user']['apps'][$app]))
		{
			throw new egw_exception_no_permission_app($app);
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;

		$GLOBALS[$class] = $obj = CreateObject($app.'.'.$class);

		if(!is_array($obj->public_functions) || !$obj->public_functions[$method])
		{
			throw new egw_exception_no_permission("Bad menuaction {$_GET['menuaction']}, not listed in public_functions!");
		}
		// dont send header and footer
		self::$header_done = self::$footer_done = true;

		$this->response = egw_json_response::get();

		// call application menuaction
		ob_start();
		$obj->$method();
		$output .= ob_get_contents();
		ob_end_clean();

		// add registered css and javascript to the response
		self::include_css_js_response();

		// add output if present
		if ($output)
		{
			$this->response->data($output);
		}
	}
}
