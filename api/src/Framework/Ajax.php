<?php
/**
 * EGroupware - Framework for Ajax based templates: jdots & Pixelegg
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage framework
 * @author Andreas Stöckel <as@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @author Nathan Gray <ng@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;
use EGroupware\Api\Egw;

/**
* Stylite jdots template
*/
abstract class Ajax extends Api\Framework
{
	/**
	 * Appname used to include javascript code
	 */
	const JS_INCLUDE_APP = '';
	/**
	 * Appname used for everything else
	 */
	const APP = '';

	/**
	 * Minimum width of sidebar eg. from German 2-letter daynames in Calendar
	 * or Calendar's navigation buttons need to be seen
	 *
	 * Need to be changed in js/fw_[template].js.
	 */
	const MIN_SIDEBAR_WIDTH = 245;
	/**
	 * Default width need to be tested with English 3-letter day-names and Pixelegg template in Calendar
	 *
	 * Need to be changed in js/egw_fw.js around line 1536 too!
	 */
	const DEFAULT_SIDEBAR_WIDTH = 255;
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
	 * @param string $template = '' name of the template
	 */
	function __construct($template=self::APP)
	{
		parent::__construct($template);		// call the constructor of the extended class

		$this->template_dir = '/'.$template;		// we are packaged as an application
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
		if (Api\Header\UserAgent::type() == 'msie' && Api\Header\UserAgent::version() < 7)
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

	/**
	 * Returns the global width of the sidebox. If the app_specific_sidebar_width had been switched
	 * on, the default width will be returned
	 */
	private static function get_global_sidebar_width()
	{
		return self::DEFAULT_SIDEBAR_WIDTH;
	}



	/**
	 * Extract applicaton name from given url (incl. GET parameters)
	 *
	 * @param string $url
	 * @return string appname or NULL if it could not be detected (eg. constructing javascript urls)
	 */
	public static function app_from_url($url)
	{
		$matches = null;
		if (preg_match('/menuaction=([a-z0-9_-]+)\./i',$url,$matches))
		{
			return $matches[1];
		}
		if ($GLOBALS['egw_info']['server']['webserver_url'] &&
			($webserver_path = parse_url($GLOBALS['egw_info']['server']['webserver_url'],PHP_URL_PATH)))
		{
			list(,$url) = explode($webserver_path, parse_url($url,PHP_URL_PATH),2);
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
	 * @param string $url The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 * @param string $link_app = null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function link($url = '', $extravars = '', $link_app=null)
	{
		if (is_null($link_app)) $link_app = self::$link_app;
		$link = parent::link($url, $extravars);

		// $link_app === true --> detect application, otherwise use given application
		if ($link_app && (is_string($link_app) || ($link_app = self::app_from_url($link))))
		{
			// Link gets handled in JS, so quotes need slashes as well as url-encoded
			// encoded ampersands in get parameters (%26) need to be encoded twise,
			// so they are still encoded when assigned to window.location
			$link_with_slashes = str_replace(array('%27','%26'), array('\%27','%2526'), $link);

			//$link = "javascript:window.egw_link_handler?egw_link_handler('$link','$link_app'):parent.egw_link_handler('$link','$link_app');";
			$link = "javascript:egw_link_handler('$link_with_slashes','$link_app')";
		}
		return $link;
	}

	/**
	 * Query additional CSP frame-src from current app
	 *
	 * We have to query all apps, as we dont reload frameset!
	 *
	 * @return array
	 */
	protected function _get_csp_frame_src()
	{
		$srcs = array();
		foreach(Api\Hooks::process('csp-frame-src') as $src)
		{
			if ($src) $srcs = array_merge($srcs, $src);
		}
		return $srcs;
	}

	/**
	 * Returns the html-header incl. the opening body tag
	 *
	 * @param array $extra = array() extra attributes passed as data-attribute to egw.js
	 * @return string with Api\Html
	 */
	function header(array $extra=array())
	{
		// make sure header is output only once
		if (self::$header_done) return '';
		self::$header_done = true;

		$this->send_headers();

		// catch error echo'ed before the header, ob_start'ed in the header.inc.php
		$content = ob_get_contents();
		ob_end_clean();
		//error_log(__METHOD__.'('.array2string($extra).') called from:'.function_backtrace());

		// the instanciation of the template has to be here and not in the constructor,
		// as the old template class has problems if restored from the session (php-restore)
		// todo: check if this is still true
		$this->tpl = new Template(EGW_SERVER_ROOT.$this->template_dir);
		if (Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile')
		{
			$this->tpl->set_file(array('_head' => 'head_mobile.tpl'));
		}
		else
		{
			$this->tpl->set_file(array('_head' => 'head.tpl'));
		}
		$this->tpl->set_block('_head','head');
		$this->tpl->set_block('_head','framework');

		// should we draw the framework, or just a header
		$do_framework = isset($_GET['cd']) && $_GET['cd'] === 'yes';

		// load clientside link registry to framework only
		if (!isset($GLOBALS['egw_info']['flags']['js_link_registry']))
		{
			$GLOBALS['egw_info']['flags']['js_link_registry'] = $do_framework;
		}
		// Loader
		$this->tpl->set_var('loader_text', lang('please wait...'));

		if ($do_framework)
		{
			//echo __METHOD__.__LINE__.' do framework ...'.'<br>';
			// framework javascript classes only need for framework
			if (Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile')
			{
				self::includeJS('.', 'fw_mobile', static::JS_INCLUDE_APP);
			}
			else
			{
				self::includeJS('.', 'fw_'.static::APP, static::JS_INCLUDE_APP);
			}
			Api\Cache::unsetSession(__CLASS__,'sidebox_md5');	// sideboxes need to be send again

			$extra['navbar-apps'] = $this->get_navbar_apps($_SERVER['REQUEST_URI']);
		}
		// for an url WITHOUT cd=yes --> load framework if not yet loaded:
		// - if top has framework object, we are all right
		// - if not we need to check if we have an opener (are a popup window)
		// - as popups can open further popups, we need to decend all the way down until we find a framework
		// - only if we cant find a framework in all openers, we redirect to create a new framework
		if(!$do_framework)
		{
			// fetch sidebox from application and set it in extra data, if we are no popup
			if (!$GLOBALS['egw_info']['flags']['nonavbar'])
			{
				$this->do_sidebox();
			}
			// for remote manual never check/create framework
			if (!in_array($GLOBALS['egw_info']['flags']['currentapp'], array('manual', 'login', 'logout', 'sitemgr')))
			{
				if (empty($GLOBALS['egw_info']['flags']['java_script'])) $GLOBALS['egw_info']['flags']['java_script']='';
				$extra['check-framework'] = $_GET['cd'] !== 'no';
			}
		}
		$this->tpl->set_var($this->_get_header($extra));
		$content = $this->tpl->fp('out','head').$content;

		if (!$do_framework)
		{
			return $content;
		}

		// topmenu
		$vars = $this->_get_navbar($apps = $this->_get_navbar_apps());
		$this->tpl->set_var($this->topmenu($vars,$apps));

		// hook after_navbar (eg. notifications)
		$this->tpl->set_var('hook_after_navbar',$this->_get_after_navbar());

		//Global sidebar width
		$this->tpl->set_var('sidebox_width', self::get_global_sidebar_width());
		$this->tpl->set_var('sidebox_min_width', self::MIN_SIDEBAR_WIDTH);

		if (!(Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile'))
		{
			// logout button
			$this->tpl->set_var('title_logout', lang("Logout"));
			$this->tpl->set_var('link_logout', Egw::link('/logout.php'));
			//Print button title
			$this->tpl->set_var('title_print', lang("Print current view"));
		}

		// add framework div's
		$this->tpl->set_var($this->_get_footer());
		$content .= $this->tpl->fp('out','framework');
		$content .= self::footer(false);

		echo $content;
		exit();
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
		$vars['topmenu_info_items'] = '';
		foreach($this->topmenu_info_items as $id => $item)
		{
			$vars['topmenu_info_items'] .= '<div class="topmenu_info_item"'.
				(is_numeric($id) ? '' : ' id="topmenu_info_'.$id.'"').'>'.$item."</div>\n";
		}
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
	* @param mixed $tooltip string containing the tooltip Api\Html, or null of no tooltip
	* @todo implement in a reasonable way for jdots
	* @return void
	*/
	function topmenu_info_icon($id,$icon_src,$iconlink,$blink=false,$tooltip=null)
	{
		unset($id,$icon_src,$iconlink,$blink,$tooltip);	// not used
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
		switch($app_data['name'])
		{
			case 'logout':
				if (Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile')
				{

				}
				else
				{
					return;	// no need for logout in topmenu on jdots
				}
				break;

			case 'manual':
				$app_data['url'] = "javascript:callManual();";
				break;

			default:
				if (strpos($app_data['url'],'logout.php') === false && substr($app_data['url'], 0, 11) != 'javascript:')
				{
					$app_data['url'] = "javascript:egw_link_handler('".$app_data['url']."','".
						(isset($GLOBALS['egw_info']['user']['apps'][$app_data['name']]) ?
							$app_data['name'] : 'about')."')";
				}
		}
		$id = $app_data['id'] ? $app_data['id'] : ($app_data['name'] ? $app_data['name'] : $app_data['title']);
		$title =  htmlspecialchars($alt_label ? $alt_label : $app_data['title']);
		$this->topmenu_items[] = '<a id="topmenu_' . $id . '" href="'.htmlspecialchars($app_data['url']).'" title="'.$app_data['title'].'">'.$title.'</a>';
	}

	/**
	 * Add info items to the topmenu template class to be displayed
	 *
	 * @param string $content Api\Html of item
	 * @param string $id = null
	 * @access protected
	 * @return void
	 */
	function _add_topmenu_info_item($content, $id=null)
	{
		if(strpos($content,'menuaction=admin.admin_accesslog.sessions') !== false)
		{
			$content = preg_replace('/href="([^"]+)"/',"href=\"javascript:egw_link_handler('\\1','admin')\"",$content);
		}
		if ($id)
		{
			$this->topmenu_info_items[$id] = $content;
		}
		else
		{
			$this->topmenu_info_items[] = $content;
		}
	}

	/**
	 * Change timezone
	 *
	 * @param string $tz
	 */
	static function ajax_tz_selection($tz)
	{
		Api\DateTime::setUserPrefs($tz);	// throws exception, if tz is invalid

		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->add('common','tz',$tz);
		$GLOBALS['egw']->preferences->save_repository();
	}

	/**
	 * Flag if do_sidebox() was called
	 *
	 * @var boolean
	 */
	protected $sidebox_done = false;

	/**
	 * Returns the Api\Html from the body-tag til the main application area (incl. opening div tag)
	 *
	 * jDots does NOT use a navbar, but it tells us that application might want a sidebox!
	 *
	 * @return string
	 */
	function navbar()
	{
		$header = '';
		if (!self::$header_done)
		{
			$header = $this->header();
		}
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;

		if (!$this->sidebox_done && self::$header_done)
		{
			$this->do_sidebox();
			return $header.'<span id="late-sidebox" data-setSidebox="'.htmlspecialchars(json_encode(self::$extra['setSidebox'])).'"/>';
		}

		return $header;
	}

	/**
	 * Set sidebox content in self::$data['setSidebox']
	 *
	 * We store in the session the md5 of each sidebox menu already send to client.
	 * If the framework get reloaded, that list gets cleared in header();
	 * Most apps never change sidebox, so we not even need to generate it more then once.
	 */
	function do_sidebox()
	{
		$this->sidebox_done = true;

		$app = $GLOBALS['egw_info']['flags']['currentapp'];

		// only send admin sidebox, for admin index url (when clicked on admin),
		// not for other admin pages, called eg. from sidebox menu of other apps
		// --> that way we always stay in the app, and NOT open admin sidebox for an app tab!!!
		if ($app == 'admin' && substr($_SERVER['PHP_SELF'],-16) != '/admin/index.php' &&
			$_GET['menuaction'] != 'admin.admin_ui.index')
		{
			//error_log(__METHOD__."() app=$app, menuaction=$_GET[menuaction], PHP_SELF=$_SERVER[PHP_SELF] --> sidebox request ignored");
			return;
		}
		$md5_session =& Api\Cache::getSession(__CLASS__,'sidebox_md5');

		//Set the sidebox content
		$sidebox = $this->get_sidebox($app);
		$md5 = md5(json_encode($sidebox));

		if ($md5_session[$app] !== $md5)
		{
			//error_log(__METHOD__."() header changed md5_session[$app]!=='$md5' --> setting it on self::\$extra[setSidebox]");
			$md5_session[$app] = $md5;	// update md5 in session
			self::$extra['setSidebox'] = array($app, $sidebox, $md5);
		}
		//else error_log(__METHOD__."() md5_session[$app]==='$md5' --> nothing to do");
	}

	/**
	 * Return true if we are rendering the top-level EGroupware window
	 *
	 * A top-level EGroupware window has a navbar: eg. no popup and for a framed template (jdots) only frameset itself
	 *
	 * @return boolean $consider_navbar_not_yet_called_as_true=true ignored by jdots, we only care for cd=yes GET param
	 * @return boolean
	 */
	public function isTop($consider_navbar_not_yet_called_as_true=true)
	{
		unset($consider_navbar_not_yet_called_as_true);	// not used
		return isset($_GET['cd']) && $_GET['cd'] === 'yes';
	}

	/**
	 * Array containing sidebox menus by applications and menu-name
	 *
	 * @var array
	 */
	protected $sideboxes;

	/**
	 * Should calls the first call to self::sidebox create an opened menu
	 *
	 * @var boolean
	 */
	protected $sidebox_menu_opened = true;

	/**
	 * Callback for sideboxes hooks, collects the data in a private var
	 *
	 * @param string $appname
	 * @param string $menu_title
	 * @param array $file
	 * @param string $type = null 'admin', 'preferences', 'favorites', ...
	 */
	public function sidebox($appname,$menu_title,$file,$type=null)
	{
		if (!isset($file['menuOpened'])) $file['menuOpened'] = (boolean)$this->sidebox_menu_opened;
		//error_log(__METHOD__."('$appname', '$menu_title', file[menuOpened]=$file[menuOpened], ...) this->sidebox_menu_opened=$this->sidebox_menu_opened");
		$this->sidebox_menu_opened = false;

		// fix app admin menus to use admin.admin_ui.index loader
		if (($type == 'admin' || $menu_title == lang('Admin')) && $appname != 'admin')
		{
			foreach($file as &$link)
			{
				preg_match('/ajax=(true|false)/', $link, $ajax);
				$link = preg_replace("/^(javascript:egw_link_handler\(')(.*)menuaction=([^&]+)(.*)(','[^']+'\))$/",
					'$1$2menuaction=admin.admin_ui.index&load=$3$4&ajax=' . ($ajax[1] ? $ajax[1] : 'true') .'\',\'admin\')', $file_was=$link);
			}
			 
		}

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
	 *				'lang_item' => translated menu item or Api\Html, i item_link === false
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
			Api\Hooks::process('sidebox_'.$appname,array($appname),true);	// true = call independent of app-permissions

			// calling the old hook
			$this->sidebox_menu_opened = true;
			Api\Hooks::single('sidebox_menu',$appname);
			self::$link_app = null;

			// allow other apps to hook into sidebox menu of every app: sidebox_all
			Api\Hooks::process('sidebox_all',array($GLOBALS['egw_info']['flags']['currentapp']),true);
		}
		//If there still is no sidebox content, return null here
		if (!isset($this->sideboxes[$appname]))
		{
			return null;
		}

		$data = array();
		$sendToBottom = array();
		foreach($this->sideboxes[$appname] as $menu_name => &$file)
		{
			$current_menu = array(
				'menu_name' => md5($menu_name),	// can contain Api\Html tags and javascript!
				'title' => $menu_name,
				'entries' => array(),
				'opened' => (boolean)$file['menuOpened'],
			);
			foreach($file as $item_text => $item_link)
			{
				if ($item_text === 'menuOpened' || $item_text === 'sendToBottom' ||// flag, not menu entry
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
						$var['icon_or_star'] = $item_link['icon'] ? Api\Image::find($app,$item_link['icon']) : False;
					}
					$var['lang_item'] = isset($item_link['no_lang']) && $item_link['no_lang'] ? $item_link['text'] : lang($item_link['text']);
					$var['item_link'] = $item_link['link'];
					if ($item_link['target'])
					{
						// we only support real targets not Api\Html markup with target in it
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

			if ($file['sendToBottom'])
			{
				$sendToBottom[] = $current_menu;
			}
			else
			{
				$data[] = $current_menu;
			}
		}
		return array_merge($data, $sendToBottom);
	}

	/**
	 * Ajax callback which is called whenever a previously opened tab is closed or
	 * opened.
	 *
	 * @param $tablist is an array which contains each tab as an associative array
	 *   with the keys 'appName' and 'active'
	 */
	public static function ajax_tab_changed_state($tablist)
	{
		$tabs = array();
		foreach($tablist as $data)
		{
			$tabs[] = $data['appName'];
			if ($data['active']) $active = $data['appName'];
		}
		// send app a notification, that it's tab got closed
		// used eg. in phpFreeChat to leave the chat
		if (($old_tabs = Api\Cache::getSession(__CLASS__, 'open_tabs')))
		{
			foreach(array_diff(explode(',',$old_tabs),$tabs) as $app)
			{
				//error_log("Tab '$app' closed, old_tabs=$old_tabs");
				Api\Hooks::single(array(
					'location' => 'tab_closed',
					'app' => $app,
				), $app);
			}
		}
		$open = implode(',',$tabs);

		if ($open != $GLOBALS['egw_info']['user']['preferences']['common']['open_tabs'] ||
			$active != $GLOBALS['egw_info']['user']['preferences']['common']['active_tab'])
		{
			//error_log(__METHOD__.'('.array2string($tablist).") storing common prefs: open_tabs='$tabs', active_tab='$active'");
			Api\Cache::setSession(__CLASS__, 'open_tabs', $open);
			$GLOBALS['egw']->preferences->read_repository();
			$GLOBALS['egw']->preferences->add('common', 'open_tabs', $open);
			$GLOBALS['egw']->preferences->add('common', 'active_tab', $active);
			$GLOBALS['egw']->preferences->save_repository(true);
		}
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
		// dont block session, while we read sidebox, they are not supposed to change something in the session
		$GLOBALS['egw']->session->commit_session();

		$response = Api\Json\Response::get();
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
	public static function ajax_sideboxwidth($appname, $width)
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
	public static function ajax_appsort(array $apps)
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

		//Store the order array inside the common user Api\Preferences
		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->add('common', 'user_apporder', serialize($order));
		$GLOBALS['egw']->preferences->save_repository(true);
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
		$apps = parent::_get_navbar_apps(Api\Image::svg_usable());	// use svg if usable in browser

		//Add its sidebox width to each app
		foreach ($apps as $app => &$data)
		{
			$data['sideboxwidth'] = self::get_sidebar_width($app);
			// overwrite icon with svg, if supported by browser
			unset($data['icon_hover']);	// not used in jdots
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
	protected function get_navbar_apps($url)
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
		$last_direct_url =& Api\Cache::getSession(__CLASS__, 'last_direct_url');
		if ($last_direct_url)
		{
			$url = $last_direct_url;
			$active_tab = self::app_from_url($last_direct_url);
		}
		else if (strpos($url, 'menuaction') > 0)
		{
			// Coming in with a specific URL, save it and redirect to index.php
			// so reloads work nicely
			$last_direct_url = $url;
			Api\Framework::redirect_link('/index.php?cd=yes');
		}
		else
		{
			$active_tab = $GLOBALS['egw_info']['user']['preferences']['common']['active_tab'];
			if (!$active_tab) $active_tab = $default_app;
		}

		//self::app_from_url might return an application the user has no rights
		//for or may return an application that simply does not exist. So check first
		//whether the $active_tab really exists in the $apps array.
		if ($active_tab && array_key_exists($active_tab, $apps))
		{
			// Do not remove cd=yes if it's an ajax=true app
			if (strpos( $apps[$active_tab]['url'],'ajax=true') !== False)
			{
				$url = preg_replace('/[&?]cd=yes/','',$url);
			}
			if($last_direct_url)
			{
				$apps[$active_tab]['openOnce'] = $url;
			}
			$store_prefs = true;
		}
		$last_direct_url = null;
		// if we have the open tabs in the session, use it instead the maybe forced common prefs open_tabs
		if (!($open_tabs = Api\Cache::getSession(__CLASS__, 'open_tabs')))
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
			$GLOBALS['egw']->preferences->add('common', 'open_tabs', implode(',',$open_tabs));
			$GLOBALS['egw']->preferences->add('common', 'active_tab', $active_tab);
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
		return array_values($apps);
	}

	/**
	 * Have we output the footer
	 *
	 * @var boolean
	 */
	static private $footer_done;

	/**
	 * Returns the Api\Html from the closing div of the main application area to the closing html-tag
	 *
	 * @param boolean $no_framework = true
	 * @return string
	 */
	function footer($no_framework=true)
	{
		//error_log(__METHOD__."($no_framework) footer_done=".array2string(self::$footer_done).' '.function_backtrace());
		if (self::$footer_done) return;	// prevent (multiple) footers
		self::$footer_done = true;

		if (!isset($GLOBALS['egw_info']['flags']['nofooter']) || !$GLOBALS['egw_info']['flags']['nofooter'])
		{
			if ($no_framework && $GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
			{
				$vars = $this->_get_footer();
				$footer = "\n".$vars['page_generation_time']."\n";
			}
		}
		return $footer.
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
	 * @var Api\Json\Response
	 */
	public $response;

	/**
	 * Run a link via ajax, returning content via egw_json_response->data()
	 *
	 * This behavies like /index.php, but returns the content via json.
	 *
	 * @param string $link
	 */
	public static function ajax_exec($link)
	{
		$parts = parse_url($link);
		$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $parts['path'];
		if ($parts['query'])
		{
			$_SERVER['REQUEST_URI'] = '?'.$parts['query'];
			parse_str($parts['query'],$_GET);
			$_REQUEST = $_GET;	// some apps use $_REQUEST to check $_GET or $_POST
		}

		if (!isset($_GET['menuaction']))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."('$link') no menuaction set!");
		}
		// set session action
		$GLOBALS['egw']->session->set_action('Ajax: '.$_GET['menuaction']);

		list($app,$class,$method) = explode('.',$_GET['menuaction']);

		if (!isset($GLOBALS['egw_info']['user']['apps'][$app]))
		{
			throw new Api\Exception\NoPermission\App($app);
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;

		$GLOBALS['egw']->framework->response = Api\Json\Response::get();

		$GLOBALS[$class] = $obj = CreateObject($app.'.'.$class);

		if(!is_array($obj->public_functions) || !$obj->public_functions[$method])
		{
			throw new Api\Exception\NoPermission("Bad menuaction {$_GET['menuaction']}, not listed in public_functions!");
		}
		// dont send header and footer
		self::$header_done = self::$footer_done = true;

		// need to call do_sidebox, as header() with $header_done does NOT!
		$GLOBALS['egw']->framework->do_sidebox();

		// send Api\Preferences, so we dont need to request them in a second ajax request
		$GLOBALS['egw']->framework->response->call('egw.set_preferences',
			(array)$GLOBALS['egw_info']['user']['preferences'][$app], $app);

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
			$GLOBALS['egw']->framework->response->data($output);
		}
	}

	/**
	 * Apps available for mobile, if admin did not configured something else
	 * (needs to kept in sync with list in phpgwapi/js/framework/fw_mobile.js!)
	 *
	 * Constant is read by admin_hooks::config to set default for fw_mobile_app_list.
	 */
	const DEFAULT_MOBILE_APPS = 'calendar,infolog,timesheet,resources,addressbook,projectmanager,tracker,mail,filemanager';
}
