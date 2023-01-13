<?php
/**
 * EGroupware API - framework baseclass
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @author Pim Snel <pim@lingewoud.nl> author of the idots template set
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 */

namespace EGroupware\Api;

use EGroupware\Api\Framework\Bundle;
use EGroupware\Api\Framework\IncludeMgr;
use EGroupware\Api\Header\ContentSecurityPolicy;

/**
 * Framework: virtual base class for all template sets
 *
 * This class creates / renders the eGW framework:
 *  a) Html header
 *  b) navbar
 *  c) sidebox menu
 *  d) main application area
 *  e) footer
 * It replaces several methods in the common class and the diverse templates.
 *
 * Existing apps either set $GLOBALS['egw_info']['flags']['noheader'] and call common::egw_header() and
 * (if $GLOBALS['egw_info']['flags']['nonavbar'] is true) parse_navbar() or it's done by the header.inc.php include.
 * The app's hook_sidebox then calls the public function display_sidebox().
 * And the app calls common::egw_footer().
 *
 * This are the authors (and their copyrights) of the original egw_header, egw_footer methods of the common class:
 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>
 * and Joseph Engo <jengo@phpgroupware.org>
 * and Mark Peters <skeeter@phpgroupware.org>
 * and Lars Kneschke <lkneschke@linux-at-work.de>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * Copyright (C) 2003 Lars Kneschke
 */
abstract class Framework extends Framework\Extra
{
	/**
	 * Name of the template set, eg. 'idots'
	 *
	 * @var string
	 */
	var $template;

	/**
	 * Path relative to EGW_SERVER_ROOT for the template directory
	 *
	 * @var string
	 */
	var $template_dir;

	/**
	 * Application specific template directories to try in given order for CSS
	 *
	 * @var string[]
	 */
	var $template_dirs = array();

	/**
	* true if $this->header() was called
	*
	* @var boolean
	*/
	static $header_done = false;
	/**
	* true if $this->navbar() was called
	*
	* @var boolean
	*/
	static $navbar_done = false;

	/**
	 * Constructor
	 *
	 * The constructor instanciates the class in $GLOBALS['egw']->framework, from where it should be used
	 */
	function __construct($template)
	{
		$this->template = $template;

		if (!isset($GLOBALS['egw']->framework))
		{
			$GLOBALS['egw']->framework = $this;
		}
		$this->template_dir = '/api/templates/'.$template;

		$this->template_dirs[] = $template;
		$this->template_dirs[] = 'default';
	}

	/**
	 * Factory method to instanciate framework object
	 *
	 * @return self
	 */
	public static function factory()
	{
		// we prefer Pixelegg template, if it is available
		if (file_exists(EGW_SERVER_ROOT.'/pixelegg') &&
			(empty($GLOBALS['egw_info']['flags']['deny_mobile']) && Header\UserAgent::mobile() ||
			$GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile' ||
			empty($GLOBALS['egw_info']['server']['template_set'])) ||
			// change old idots and jerryr to our standard template (pixelegg)
			in_array($GLOBALS['egw_info']['server']['template_set'], array('idots', 'jerryr')))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'pixelegg';
		}
		// then jdots aka Stylite template
		if (file_exists(EGW_SERVER_ROOT.'/jdots') && empty($GLOBALS['egw_info']['server']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'jdots';
		}
		// eg. "default" is only used for login at the moment
		if (!class_exists($class=$GLOBALS['egw_info']['server']['template_set'].'_framework'))
		{
			$class = __CLASS__.'\\Minimal';
		}
		return new $class($GLOBALS['egw_info']['server']['template_set']);
	}

	/**
	 * Check if we have a valid and installed EGroupware template
	 *
	 * Templates are installed in their own directory and contain a setup/setup.inc.php file
	 *
	 * @param string $template
	 * @return boolean
	 */
	public static function validTemplate($template)
	{
		return preg_match('/^[A-Z0-9_-]+$/i', $template) &&
			file_exists(EGW_SERVER_ROOT.'/'.$template) &&
			file_exists($file=EGW_SERVER_ROOT.'/'.$template.'/setup/setup.inc.php') &&
			include($file) && !empty($GLOBALS['egw_info']['template'][$template]);
	}

	/**
	 * Send HTTP headers: Content-Type and Content-Security-Policy
	 */
	public function send_headers()
	{
		// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
		header('Content-type: text/html; charset='.Translation::charset());

		Header\ContentSecurityPolicy::send();

		// allow client-side to detect first load aka just logged in
		$reload_count =& Cache::getSession(__CLASS__, 'framework-reload');
		self::$extra['framework-reload'] = (int)(bool)$reload_count++;
	}

	/**
	 * Constructor for static variables
	 */
	public static function init_static()
	{
		self::$js_include_mgr = new Framework\IncludeMgr(array(
			// We need LABjs, but putting it through Framework\IncludeMgr causes it to re-load itself
			//'/api/js/labjs/LAB.src.js',

			// always load jquery (not -ui) first
			'/vendor/bower-asset/jquery/dist/jquery.js',
			// always include javascript helper functions
			'/api/js/jsapi.min.js',
			'/api/js/jsapi/egw.min.js',
		));
	}

	/**
	 * Link url generator
	 *
	 * @param string $url	The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 * @param string $link_app =null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function link($url, $extravars = '', $link_app=null)
	{
		unset($link_app);	// not used by required by function signature
		return $GLOBALS['egw']->session->link($url, $extravars);
	}

	/**
	 * Get a full / externally usable URL from an EGroupware link
	 *
	 * @param string $link
	 */
	static function getUrl($link)
	{
		return Header\Http::fullUrl($link);
	}

	/**
	 * Handles redirects under iis and apache, it does NOT return (calls exit)
	 *
	 * This function handles redirects under iis and apache it assumes that $phpgw->link() has already been called
	 *
	 * @param string $url url to redirect to
	 * @param string $link_app =null appname to redirect for, default currentapp
	 */
	static function redirect($url, $link_app=null)
	{
		// Determines whether the current output buffer should be flushed
		$do_flush = true;

		if (Json\Response::isJSONResponse() || Json\Request::isJSONRequest())
		{
			Json\Response::get()->redirect($url, false, $link_app);

			// check if we have a message, in which case send it along too
			$extra = self::get_extra();
			if ($extra['message'])
			{
				Json\Response::get()->apply('egw.message', $extra['message']);
			}

			// If we are in a json request, we should not flush the current output!
			$do_flush = false;
		}
		else
		{
			$file = $line = null;
			if (headers_sent($file,$line))
			{
				throw new Exception\AssertionFailed(__METHOD__."('".htmlspecialchars($url)."') can NOT redirect, output already started at $file line $line!");
			}
			if ($GLOBALS['egw']->framework instanceof Framework\Ajax && !empty($link_app))
			{
				self::set_extra('egw', 'redirect', array($url, $link_app));
				$GLOBALS['egw']->framework->render('');
			}
			else
			{
				Header("Location: $url");
				print("\n\n");
			}
		}

		if ($do_flush)
		{
			@ob_flush(); flush();
		}

		// commit session (if existing), to fix timing problems sometimes preventing session creation ("Your session can not be verified")
		if (isset($GLOBALS['egw']->session)) $GLOBALS['egw']->session->commit_session();

		// run egw destructor now explicit, in case a (notification) email is send via Egw::on_shutdown(),
		// as stream-wrappers used by Horde Smtp fail when PHP is already in destruction
		if (isset($GLOBALS['egw'])) $GLOBALS['egw']->__destruct();
		exit;
	}

	/**
	 * Redirects direct to a generated link
	 *
	 * @param string $url	The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 * @param string $link_app =null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function redirect_link($url, $extravars='', $link_app=null)
	{
		self::redirect(self::link($url, $extravars), $link_app);
	}

	/**
	 * Renders an applicaton page with the complete eGW framework (header, navigation and menu)
	 *
	 * This is the (new) prefered way to render a page in eGW!
	 *
	 * @param string $content Html of the main application area
	 * @param string $app_header =null application header, default what's set in $GLOBALS['egw_info']['flags']['app_header']
	 * @param string $navbar =null show the navigation, default !$GLOBALS['egw_info']['flags']['nonavbar'], false gives a typical popu
	 *
	 */
	function render($content,$app_header=null,$navbar=null)
	{
		if (!is_null($app_header)) $GLOBALS['egw_info']['flags']['app_header'] = $app_header;
		if (!is_null($navbar)) $GLOBALS['egw_info']['flags']['nonavbar'] = !$navbar;

		echo $this->header();

		if (!isset($GLOBALS['egw_info']['flags']['nonavbar']) || !$GLOBALS['egw_info']['flags']['nonavbar'])
		{
			echo $this->navbar();
		}
		echo $content;

		echo $this->footer();
	}

	/**
	 * Returns the html-header incl. the opening body tag
	 *
	 * @return string with Html
	 */
	abstract function header(array $extra=array());

	/**
	 * Returns the Html from the body-tag til the main application area (incl. opening div tag)
	 *
	 * If header has NOT been called, also return header content!
	 * No need to manually call header, this allows to postpone header so navbar / sidebox can include JS or CSS.
	 *
	 * @return string with Html
	 */
	abstract function navbar();

	/**
	 * Return true if we are rendering the top-level EGroupware window
	 *
	 * A top-level EGroupware window has a navbar: eg. no popup and for a framed template (jdots) only frameset itself
	 *
	 * @return boolean $consider_navbar_not_yet_called_as_true=true
	 * @return boolean
	 */
	abstract function isTop($consider_navbar_not_yet_called_as_true=true);

	/**
	 * Returns the content of one sidebox
	 *
	 * @param string $appname
	 * @param string $menu_title
	 * @param array $file
	 * @param string $type =null 'admin', 'preferences', 'favorites', ...
	 */
	abstract function sidebox($appname,$menu_title,$file,$type=null);

	/**
	 * Returns the Html from the closing div of the main application area to the closing html-tag
	 *
	 * @return string
	 */
	abstract function footer();

	/**
	 * Displays the login screen
	 *
	 * @param string $extra_vars for login url
	 * @param string $change_passwd =null string with message to render input fields for password change
	 */
	function login_screen($extra_vars, $change_passwd=null)
	{
		(new Framework\Login($this))->screen($extra_vars, $change_passwd);
	}

	/**
	 * displays a login denied message
	 */
	function denylogin_screen()
	{
		(new Framework\Login($this))->deny_screen();
	}

	/**
	 * Calculate page-generation- and session-restore times
	 *
	 * @return array values for keys 'page_generation_time' and 'session_restore_time', if display is an
	 */
	public static function get_page_generation_time()
	{
		$times = array(
			'page_generation_time' => sprintf('%4.2f', microtime(true) - $GLOBALS['egw_info']['flags']['page_start_time']),
		);
		if ($GLOBALS['egw_info']['flags']['session_restore_time'])
		{
			$times['session_restore_time'] = sprintf('%4.2f', $GLOBALS['egw_info']['flags']['session_restore_time']);
		}
		return $times;
	}

	/**
	 * Get footer as array to eg. set as vars for a template (from idots' head.inc.php)
	 *
	 * @return array
	 */
	public function _get_footer()
	{
		$var = Array(
			'img_root'       => $GLOBALS['egw_info']['server']['webserver_url'] . $this->template_dir.'/images',
			'version'        => $GLOBALS['egw_info']['server']['versions']['api']
		);
		$var['page_generation_time'] = '';
		if($GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
		{
			$times = self::get_page_generation_time();

			$var['page_generation_time'] = '<div class="pageGenTime" id="divGenTime_'.$GLOBALS['egw_info']['flags']['currentapp'].'"><span>'.
				lang('Page was generated in %1 seconds', $times['page_generation_time']);

			if (isset($times['session_restore_time']))
			{
				$var['page_generation_time'] .= ' '.lang('(session restored in %1 seconds)',
					$times['session_restore_time']);
			}
			$var['page_generation_time'] .= '</span></div>';
		}
		if (empty($GLOBALS['egw_info']['server']['versions']['maintenance_release']))
		{
			$GLOBALS['egw_info']['server']['versions']['maintenance_release'] = self::api_version();
		}
		$var['powered_by'] = '<a href="http://www.egroupware.org/" class="powered_by" target="_blank">'.
			lang('Powered by').' EGroupware '.
			$GLOBALS['egw_info']['server']['versions']['maintenance_release'].'</a>';

		return $var;
	}

	/**
	 * Body tags for onLoad, onUnload and onResize
	 *
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @var array
	 */
	protected static $body_tags = array();

	/**
	 * Adds on(Un)Load= attributes to the body tag of a page
	 *
	 * Can only be set via egw_framework::set_on* methods.
	 *
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @returns string the attributes to be used
	 */
	static public function _get_body_attribs()
	{
		$js = '';
		foreach(self::$body_tags as $what => $data)
		{
			if (!empty($data))
			{
				if($what == 'onLoad')
				{
					$js .= 'onLoad="egw_LAB.wait(function() {'. htmlspecialchars($data).'})"';
					continue;
				}
				$js .= ' '.$what.'="' . htmlspecialchars($data) . '"';
			}
		}
		return $js;
	}

	protected static $body_classes = [];

	/**
	 * Set a CSS class on the body tag
	 *
	 * @param string $class =null
	 * @return array with all currently set css classes
	 */
	public static function bodyClass($class=null)
	{
		if (!empty($class))
		{
			self::$body_classes[] = $class;
		}
		return self::$body_classes;
	}

	/**
	 * Get class attribute for body tag
	 *
	 * @return string
	 */
	protected static function bodyClassAttribute()
	{
		return self::$body_classes ? ' class="'.htmlspecialchars(implode(' ', self::$body_classes)).'"' : '';
	}

	/**
	 * Get header as array to eg. set as vars for a template (from idots' head.inc.php)
	 *
	 * @param array $extra =array() extra attributes passed as data-attribute to egw.js
	 * @return array
	 */
	protected function _get_header(array $extra=array())
	{
		// display password expires in N days message once per session
		$message = null;
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' &&
			Auth::check_password_change($message) !== true)
		{
			self::message($message, 'info');
		}

		// get used language code (with a little xss check, if someone tries to sneak something in)
		if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$GLOBALS['egw_info']['user']['preferences']['common']['lang']))
		{
			$lang_code = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		}

		$app = $GLOBALS['egw_info']['flags']['currentapp'];
		$app_title = $GLOBALS['egw_info']['apps'][$app]['title'] ?? lang($app);
		$app_header = $GLOBALS['egw_info']['flags']['app_header'] ?? $app_title;
		$site_title = strip_tags(($GLOBALS['egw_info']['server']['site_title']??'').' ['.($app_header ?: $app_title).']');

		// send appheader to clientside
		$extra['app-header'] = $app_header;

		if($GLOBALS['egw_info']['flags']['currentapp'] != 'wiki') $robots ='<meta name="robots" content="none" />';

		$var['favicon_file'] = self::get_login_logo_or_bg_url('favicon_file', 'favicon.ico');

		if (!empty($GLOBALS['egw_info']['flags']['include_wz_tooltip']) &&
			file_exists(EGW_SERVER_ROOT.($wz_tooltip = '/phpgwapi/js/wz_tooltip/wz_tooltip.js')))
		{
			$include_wz_tooltip = '<script src="'.$GLOBALS['egw_info']['server']['webserver_url'].
				$wz_tooltip.'?'.filemtime(EGW_SERVER_ROOT.$wz_tooltip).'" type="text/javascript"></script>';
		}
		return $this->_get_css()+array(
			'img_icon'			=> $var['favicon_file'],
			'img_shortcut'		=> $var['favicon_file'],
			'lang_code'			=> $lang_code,
			'charset'       	=> Translation::charset(),
			'website_title' 	=> $site_title,
			'body_tags'         => self::_get_body_attribs().self::bodyClassAttribute(),
			'java_script'   	=> self::_get_js($extra),
			'meta_robots'		=> $robots,
			'dir_code'			=> lang('language_direction_rtl') != 'rtl' ? '' : ' dir="rtl"',
			'include_wz_tooltip'=> $include_wz_tooltip ?? '',
			'webserver_url'     => $GLOBALS['egw_info']['server']['webserver_url'],
			'darkmode'		=>  !empty(Cache::getSession('api','darkmode')) ?? $GLOBALS['egw_info']['user']['preferences']['common']['darkmode']
		);
	}

	/**
	 * Get navbar as array to eg. set as vars for a template (from idots' navbar.inc.php)
	 *
	 * @param array $apps navbar apps from _get_navbar_apps
	 * @return array
	 */
	protected function _get_navbar($apps)
	{
		$var['img_root'] = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/'.$this->template.'/images';

		if(isset($GLOBALS['egw_info']['flags']['app_header']))
		{
			$var['current_app_title'] = $GLOBALS['egw_info']['flags']['app_header'];
		}
		else
		{
			$var['current_app_title']=$apps[$GLOBALS['egw_info']['flags']['currentapp']]['title'];
		}
		$var['currentapp'] = $GLOBALS['egw_info']['flags']['currentapp'];

		// quick add selectbox
		$var['quick_add'] = $this->_get_quick_add();

		$var['user_info'] = $this->_user_time_info();

		if($GLOBALS['egw_info']['user']['account_lastpwd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login').'<br />'.
				lang('Click this image on the navbar: %1','<img src="'.Image::find('preferences','navbar.gif').'">');
		}
		elseif($GLOBALS['egw_info']['server']['change_pwd_every_x_days'] && $GLOBALS['egw_info']['user']['account_lastpwd_change'] < time() - (86400*$GLOBALS['egw_info']['server']['change_pwd_every_x_days']))
		{
			$api_messages = lang('it has been more then %1 days since you changed your password',$GLOBALS['egw_info']['server']['change_pwd_every_x_days']);
		}

		$var['logo_header'] = $var['logo_file'] = self::get_login_logo_or_bg_url('login_logo_file', 'logo');

		if ($GLOBALS['egw_info']['server']['login_logo_header'])
		{
			$var['logo_header'] = self::get_login_logo_or_bg_url('login_logo_header', 'logo');
		}

		$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.egroupware.org';

		if (substr($var['logo_url'],0,4) != 'http')
		{
			$var['logo_url'] = 'http://'.$var['logo_url'];
		}
		$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.egroupware.org';

		return $var;
	}

	/**
	 * Get login logo or background image base on requested config type
	 *
	 * @param string $type config type to fetch. e.g.: "login_logo_file"
	 * @param string $find_type type of image to search on as alternative option. e.g.: "logo"
	 *
	 * @return string returns full url of the image
	 */
	static function get_login_logo_or_bg_url ($type, $find_type)
	{
		$url = !empty($GLOBALS['egw_info']['server'][$type]) && is_array($GLOBALS['egw_info']['server'][$type]) ?
			$GLOBALS['egw_info']['server'][$type][0] :
			$GLOBALS['egw_info']['server'][$type] ?? null;

		if (substr($url, 0, 4) === 'http' ||
			!empty($url) && $url[0] === '/')
		{
			return $url;
		}
		else
		{
			return Image::find('api',$url ? $url : $find_type, '', null);
		}
	}

	/**
	 * Returns Html with user and time
	 *
	 * @return void
	 */
	protected static function _user_time_info()
	{
		$now = new DateTime();
		$user_info = '<span>'.lang($now->format('l')) . ' ' . $now->format(true).'</span>';

		$user_tzs = DateTime::getUserTimezones();
		if (count($user_tzs) > 1)
		{
			$tz = $GLOBALS['egw_info']['user']['preferences']['common']['tz'];
			$user_info .= Html::form(Html::select('tz',$tz,$user_tzs,true),array(),
				'/index.php','','tz_selection',' style="display: inline;"','GET');
		}
		return $user_info;
	}

	/**
	 * Returns user avatar menu
	 *
	 * @return string
	 */
	protected static function _user_avatar_menu()
	{
		$stats = Hooks::process('framework_avatar_stat');
		$stat = array_pop($stats);

		return '<et2-avatar shape="squared" title="'.Accounts::format_username().'" src="'.Egw::link('/api/avatar.php', array(
								'account_id' => $GLOBALS['egw_info']['user']['account_id'],
							)).'"></et2-avatar>'.(!empty($stat) ?
				'<span class="fw_avatar_stat '.$stat['class'].'" title="'.$stat['title'].'">'.$stat['body'].'</span>' : '');
	}

	/**
	 * Returns logout menu
	 *
	 * @return string
	 */
	protected static function _logout_menu()
	{
		return '<a href="'.Egw::link('/logout.php').'" title="'.lang("Logout").'" ></a>';
	}

	/**
	 * Returns print menu
	 *
	 * @return string
	 */
	protected static function _print_menu()
	{
		return '<span title="'.lang("Print current view").'"</span>';
	}

	/**
	 * Returns darkmode menu
	 *
	 * @return string
	 */
	protected static function _darkmode_menu()
	{
		$mode = $GLOBALS['egw_info']['user']['preferences']['common']['darkmode'] == 1?'dark':'light';
		return '<span title="'.lang("%1 mode", $mode).'" class="'.
			($mode == 'dark'?'darkmode_on':'').'"> </span>';
	}

	/**
	 * Prepare the current users
	 *
	 * @return array
	 */
	protected static function _current_users()
	{
	   if( $GLOBALS['egw_info']['user']['apps']['admin'] && $GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers'])
	   {
		   return [
			   'name' => 'current_user',
			   'title' => lang('Current users').':'.$GLOBALS['egw']->session->session_count(),
			   'url' => self::link('/index.php','menuaction=admin.admin_accesslog.sessions&ajax=true')
		   ];
	   }
	}

	/**
	 * Prepare the quick add selectbox
	 *
	 * @return string
	 */
	protected static function _get_quick_add()
	{
		return '<span id="quick_add" title="'.lang('Quick add').'"></span>';
	}

	/**
	 * Prepare notification signal (blinking bell)
	 *
	 * @return string
	 */
	protected static function _get_notification_bell()
	{
		return Html::image('notifications', 'notificationbell', lang('notifications'),
			'id="notificationbell" style="display: none"');
	}

	/**
	 * Get context to use with file_get_context or fopen to use our proxy settings from setup
	 *
	 * @param string $username =null username for regular basic Auth
	 * @param string $password =null password --------- " ----------
	 * @param array $opts =array() further params for http(s) context, eg. array('timeout' => 123)
	 * @return resource|null context to use with file_get_context/fopen or null if no proxy configured
	 */
	public static function proxy_context($username=null, $password=null, array $opts = array())
	{
		$opts += array(
			'method' => 'GET',
		);
		if (!empty($GLOBALS['egw_info']['server']['httpproxy_server']))
		{
			$opts += array (
				'proxy'  => 'tcp://'.$GLOBALS['egw_info']['server']['httpproxy_server'].':'.
					($GLOBALS['egw_info']['server']['httpproxy_port'] ? $GLOBALS['egw_info']['server']['httpproxy_port'] : 8080),
				'request_fulluri' => true,
			);
			// proxy authentication
			if (!empty($GLOBALS['egw_info']['server']['httpproxy_server_username']))
			{
				$opts['header'][] = 'Proxy-Authorization: Basic '.base64_encode($GLOBALS['egw_info']['server']['httpproxy_server_username'].':'.
					$GLOBALS['egw_info']['server']['httpproxy_server_password']);
			}
		}
		// optional authentication
		if (isset($username))
		{
			$opts['header'][] = 'Authorization: Basic '.base64_encode($username.':'.$password);
		}
		return stream_context_create(array(
			'http' => $opts,
			'https' => $opts,
		));
	}

	/**
	 * Get API version from changelog or database, whichever is bigger
	 *
	 * @param string &$changelog on return path to changelog
	 * @return string
	 */
	public static function api_version(&$changelog=null)
	{
		return Framework\Updates::api_version($changelog);
	}

	/**
	 * Get the link to an application's index page
	 *
	 * @param string $app
	 * @return string
	 */
	public static function index($app)
	{
		$data =& $GLOBALS['egw_info']['user']['apps'][$app];
		if (!isset($data))
		{
			throw new Exception\WrongParameter("'$app' not a valid app for this user!");
		}
		$index = '/'.$app.'/index.php';
		if (isset($data['index']))
		{
			if (preg_match('|^https?://|', $data['index']))
			{
				return $data['index'];
			}
			if ($data['index'][0] == '/')
			{
				$index = $data['index'];
			}
			else
			{
				$index = '/index.php?menuaction='.$data['index'];
			}
		}
		return self::link($index, $GLOBALS['egw_info']['flags']['params'][$app] ?? '');
	}

	/**
	 * Used internally to store unserialized value of $GLOBALS['egw_info']['user']['preferences']['common']['user_apporder']
	 */
	private static $user_apporder = array();

	/**
	 * Internal usort callback function used to sort an array according to the
	 * user sort order
	 */
	private static function _sort_apparray($a, $b)
	{
		//Unserialize the user_apporder array
		$arr = self::$user_apporder;

		$ind_a = isset($arr[$a['name']]) ? $arr[$a['name']] : null;
		$ind_b = isset($arr[$b['name']]) ? $arr[$b['name']] : null;

		if ($ind_a == $ind_b)
			return 0;

		if ($ind_a == null)
			return -1;

		if ($ind_b == null)
			return 1;

		return $ind_a > $ind_b ? 1 : -1;
	}

	/**
	 * Prepare an array with apps used to render the navbar
	 *
	 * This is similar to the former common::navbar() method - though it returns the vars and does not place them in global scope.
	 *
	 * @return array
	 */
	protected static function _get_navbar_apps()
	{
		$first = key((array)$GLOBALS['egw_info']['user']['apps']);
		if(is_array($GLOBALS['egw_info']['user']['apps']['admin']) && $first != 'admin')
		{
			$newarray['admin'] = $GLOBALS['egw_info']['user']['apps']['admin'];
			foreach($GLOBALS['egw_info']['user']['apps'] as $index => $value)
			{
				if($index != 'admin')
				{
					$newarray[$index] = $value;
				}
			}
			$GLOBALS['egw_info']['user']['apps'] = $newarray;
			reset($GLOBALS['egw_info']['user']['apps']);
		}
		unset($index);
		unset($value);
		unset($newarray);

		$apps = array();
		foreach((array)$GLOBALS['egw_info']['user']['apps'] as $app => $data)
		{
			if (is_int($app))
			{
				continue;
			}

			if ($app == 'preferences' || $GLOBALS['egw_info']['apps'][$app]['status'] != 2 && $GLOBALS['egw_info']['apps'][$app]['status'] != 3)
			{
				$apps[$app]['title'] = $GLOBALS['egw_info']['apps'][$app]['title'];
				$apps[$app]['url']   = self::index($app);
				$apps[$app]['name']  = $app;

				// create popup target
				if ($data['status'] == 4)
				{
					$apps[$app]['target'] = ' target="'.$app.'" onClick="'."if (this != '') { window.open(this+'".
						(strpos($apps[$app]['url'],'?') !== false ? '&' : '?').
						"referer='+encodeURIComponent(location),this.target,'width=800,height=600,scrollbars=yes,resizable=yes'); return false; } else { return true; }".'"';
				}
				elseif(isset($GLOBALS['egw_info']['flags']['navbar_target']) && $GLOBALS['egw_info']['flags']['navbar_target'])
				{
					$apps[$app]['target'] = 'target="' . $GLOBALS['egw_info']['flags']['navbar_target'] . '"';
				}
				else
				{
					$apps[$app]['target'] = '';
				}

				// take status flag into account as we might use it on client-side.
				// for instance: applications with status 5 will run in background
				$apps[$app]['status'] = $data['status'];

				if (!empty($data['icon']) && preg_match('#^(https?://|/)#', $data['icon']))
				{
					$icon_url =  $data['icon'];
				}
				else
				{
					$icon = isset($data['icon']) ?  $data['icon'] : 'navbar';
					$icon_app = isset($data['icon_app']) ? $data['icon_app'] : $app;
					$icon_url = Image::find($icon_app,Array($icon,'nonav'),'');
				}
				$apps[$app]['icon']  = $apps[$app]['icon_hover'] = $icon_url;
			}
		}

		//Sort the applications accordingly to their user sort setting
		if (!empty($GLOBALS['egw_info']['user']['preferences']['common']['user_apporder']))
		{
			//Sort the application array using the user_apporder array as sort index
			self::$user_apporder =
				unserialize($GLOBALS['egw_info']['user']['preferences']['common']['user_apporder'], ['allowed_classes' => false]);
			uasort($apps, __CLASS__.'::_sort_apparray');
		}

		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'preferences' || $GLOBALS['egw_info']['flags']['currentapp'] == 'about')
		{
			$app = $app_title = 'EGroupware';
		}
		else
		{
			$app = $GLOBALS['egw_info']['flags']['currentapp'];
			$app_title = $GLOBALS['egw_info']['apps'][$app]['title'];
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'])	// Preferences last
		{
			$prefs = $apps['preferences'];
			unset($apps['preferences']);
			$apps['preferences'] = $prefs;
		}

		// We handle this here because its special
		$apps['about']['title'] = 'EGroupware';
		$apps['about']['url']   = self::link('/about.php');
		$apps['about']['icon']  = $apps['about']['icon_hover'] = Image::find('api',Array('about','nonav'));
		$apps['about']['name']  = 'about';

		$apps['logout']['title'] = lang('Logout');
		$apps['logout']['name']  = 'logout';
		$apps['logout']['url']   = self::link('/logout.php');
		$apps['logout']['icon']  = $apps['logout']['icon_hover'] = Image::find('api',Array('logout','nonav'));

		return $apps;
	}

	/**
	 * Used by template headers for including CSS in the header
	 *
	 * 'app_css'   - css styles from a) the menuaction's css-method and b) the $GLOBALS['egw_info']['flags']['css']
	 * 'file_css'  - link tag of the app.css file of the current app
	 * 'theme_css' - url of the theme css file
	 * 'print_css' - url of the print css file
	 *
	 * @author Dave Hall (*based* on verdilak? css inclusion code)
	 * @return array with keys 'app_css' from the css method of the menuaction-class and 'file_css' (app.css file of the application)
	 */
	public function _get_css()
	{
		$app_css = '';
		if (isset($GLOBALS['egw_info']['flags']['css']))
		{
			$app_css = $GLOBALS['egw_info']['flags']['css'];
		}

		if (self::$load_default_css)
		{
			// For mobile user-agent we prefer mobile theme over selected one with a final fallback to theme named as template
			$themes_to_check = array();
			if (Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'fw_mobile')
			{
				$themes_to_check[] = $this->template_dir.'/mobile/'.$GLOBALS['egw_info']['user']['preferences']['common']['theme'].'.css';
				$themes_to_check[] = $this->template_dir.'/mobile/fw_mobile.css';
			}
			$themes_to_check[] = $this->template_dir.'/css/'.$GLOBALS['egw_info']['user']['preferences']['common']['theme'].'.css';
			$themes_to_check[] = $this->template_dir.'/css/'.$this->template.'.css';
			foreach($themes_to_check as $theme_css)
			{
				if (file_exists(EGW_SERVER_ROOT.$theme_css)) break;
			}
			// no longer available in config, of you don't want minified CSS on a developer install, don't install grunt/generate the files
			//$debug_minify = !empty($GLOBALS['egw_info']['server']['debug_minify']) && $GLOBALS['egw_info']['server']['debug_minify'] === 'True';
			if (/*!$debug_minify &&*/ file_exists(EGW_SERVER_ROOT.($theme_min_css = str_replace('.css', '.min.css', $theme_css))))
			{
				//error_log(__METHOD__."() Framework\CssIncludes::get()=".array2string(Framework\CssIncludes::get()));
				self::includeCSS($theme_min_css);

				// Global category styles
				if (basename($_SERVER['PHP_SELF']) !== 'login.php')
				{
					Categories::css(Categories::GLOBAL_APPNAME);
				}
			}
			else
			{
				// Load these first
				// Cascade should go:
				//  Libs < etemplate2 < framework/theme < app < print
				// Et2Date uses flatpickr
				self::includeCSS('/node_modules/flatpickr/dist/themes/light.css');

				// eTemplate2 - load in top so sidebox has styles too
				self::includeCSS('/api/templates/default/etemplate2.css');

				// Category styles
				if(basename($_SERVER['PHP_SELF']) !== 'login.php')
				{
					Categories::css(Categories::GLOBAL_APPNAME);
				}

				self::includeCSS($theme_css);

				// sending print css last, so it can overwrite anything
				$print_css = $this->template_dir.'/print.css';
				if(!file_exists(EGW_SERVER_ROOT.$print_css))
				{
					$print_css = '/api/templates/default/print.css';
				}
				self::includeCSS($print_css);
			}
			// search for app specific css file, so it can customize the theme
			self::includeCSS($GLOBALS['egw_info']['flags']['currentapp'], 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
				self::includeCSS($GLOBALS['egw_info']['flags']['currentapp'], 'app');
		}
		return array(
			'app_css'   => $app_css,
			'css_file'  => Framework\CssIncludes::tags(),
		);
	}

	/**
	 * Used by the template headers for including javascript in the header
	 *
	 * The method is included here to make it easier to change the js support
	 * in eGW.  One change then all templates will support it (as long as they
	 * include a call to this method).
	 *
	 * @param array $extra =array() extra data to pass to egw.js as data-parameter
	 * @return string the javascript to be included
	 */
	public static function _get_js(array $extra=array())
	{
		$java_script = '';

		/* this flag is for all javascript code that has to be put before other jscode.
		Think of conf vars etc...  (pim@lingewoud.nl) */
		if (isset($GLOBALS['egw_info']['flags']['java_script_thirst']))
		{
			$java_script .= $GLOBALS['egw_info']['flags']['java_script_thirst'] . "\n";
		}
		// add configuration, link-registry, images, user-data and -preferences for non-popup windows
		// specifying etag in url to force reload, as we send expires header
		if ($GLOBALS['egw_info']['flags']['js_link_registry'] || !isset($_GET['cd']) || isset($_GET['cd']) && $_GET['cd'] === 'popup')
		{
			self::includeJS('/api/config.php', array(
				'etag' => md5(json_encode(Config::clientConfigs()).Link::json_registry()),
			));
			self::includeJS('/api/images.php', array(
				'template' => $GLOBALS['egw_info']['server']['template_set'],
				'etag' => md5(json_encode(Image::map($GLOBALS['egw_info']['server']['template_set'])))
			));
			self::includeJS('/api/user.php', array(
				'user' => $GLOBALS['egw_info']['user']['account_lid'],
				'lang' => $GLOBALS['egw_info']['user']['preferences']['common']['lang'],
				// add etag on url, so we can set an expires header
				'etag' => md5(json_encode($GLOBALS['egw_info']['user']['preferences']['common']).
					$GLOBALS['egw']->accounts->json($GLOBALS['egw_info']['user']['account_id'])),
			));
		}
		// manually load old legacy javascript dhtmlx & jQuery-UI via script tag
		self::includeJS('/api/js/dhtmlxtree/codebase/dhtmlxcommon.js');
		self::includeJS('/api/js/dhtmlxMenu/sources/dhtmlxmenu.js');
		self::includeJS('/api/js/dhtmlxMenu/sources/ext/dhtmlxmenu_ext.js');
		self::includeJS('/api/js/dhtmlxtree/sources/dhtmlxtree.js');
		self::includeJS('/api/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js');

		$extra['url'] = $GLOBALS['egw_info']['server']['webserver_url'];
		$map = null;
		$extra['include'] = array_map(static function($str){
			return substr($str,1);
		}, self::get_script_links(true, false, $map));
		$extra['app'] = $GLOBALS['egw_info']['flags']['currentapp'];

		// Static things we want to make sure are loaded first
//$java_script .='<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.44/dist/themes/base.css">
//<script type="module" src="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.44/dist/shoelace.js"></script>';

		// load our clientside entrypoint egw.min.js with a cache-buster
		$java_script .= '<script type="module" src="'.$GLOBALS['egw_info']['server']['webserver_url'].
			'/api/js/jsapi/egw.min.js?'.filemtime(EGW_SERVER_ROOT.'/api/js/jsapi/egw.min.js').
			'" id="egw_script_id"';

		// add values of extra parameter and class var as data attributes to script tag of egw.js
		foreach($extra+self::$extra as $name => $value)
		{
			if (is_array($value)) $value = json_encode($value);
			// we need to double encode (Html::htmlspecialchars( , TRUE)), as otherwise we get invalid json, eg. for quotes
			$java_script .= ' data-'.$name."=\"". Html::htmlspecialchars($value, true)."\"";
		}
		$java_script .= "></script>\n";

		if(@isset($_GET['menuaction']))
		{
			list(, $class) = explode('.',$_GET['menuaction']);
			if (!empty($GLOBALS[$class]->public_functions['java_script']))
			{
				$java_script .= $GLOBALS[$class]->java_script();
			}
		}
		if (isset($GLOBALS['egw_info']['flags']['java_script']))
		{
			// Strip out any script tags, this needs to be executed as anonymous function
			$GLOBALS['egw_info']['flags']['java_script'] = preg_replace(array('/(<script[^>]*>)([^<]*)/is','/<\/script>/'),array('$2',''),$GLOBALS['egw_info']['flags']['java_script']);
			if(trim($GLOBALS['egw_info']['flags']['java_script']) != '')
			{
				$java_script .= '<script type="text/javascript">window.egw_LAB.wait(function() {'.$GLOBALS['egw_info']['flags']['java_script'] . "});</script>\n";
			}
		}

		return $java_script;
	}

	/**
	 * Files imported via script tag in egw.js, because they are no modules
	 */
	const legacy_js_imports = '#/dhtmlx|jquery|magicsuggest|resumable#';

	/**
	 * Add EGroupware URL prefix eg. '/egroupware' to files AND bundles
	 *
	 * @return array
	 */
	public static function getImportMap()
	{
		$imports = Bundle::getImportMap();

		// adding some extra mappings
		if (($prefix = parse_url($GLOBALS['egw_info']['server']['webserver_url'], PHP_URL_PATH)) === '/') $prefix = '';

		// fix egw_global(.d.ts) import
		$imports[$prefix.'/api/js/jsapi/egw_global'] = $prefix.'/api/js/jsapi/egw_global.js?'.
			filemtime(EGW_SERVER_ROOT.'/api/js/jsapi/egw_global.js');

		// @todo: add all node_modules as bare imports

		// map all legacy-js to something "not hurting"
		$imports = array_map(static function($url) use ($prefix)
		{
			return !preg_match(self::legacy_js_imports, $url) ? $url :
				$prefix.'/api/js/jquery/jquery.noconflict.js';
		}, $imports);

		ContentSecurityPolicy::add("script-src","https://cdn.skypack.dev");
		ContentSecurityPolicy::add("script-src","https://cdn.jsdelivr.net");
		ContentSecurityPolicy::add("style-src","https://cdn.jsdelivr.net");
		return ['imports' => $imports];
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
		if (file_exists($file=EGW_SERVER_ROOT.$this->template_dir.'/setup/setup.inc.php') &&
			(include $file) && isset($GLOBALS['egw_info']['template'][$this->template]['themes']))
		{
			$list = $GLOBALS['egw_info']['template'][$this->template]['themes'];
		}
		if (($dh = @opendir(EGW_SERVER_ROOT.$this->template_dir.'/css')))
		{
			while (($file = readdir($dh)))
			{
				if (preg_match('/'."\.css$".'/i', $file))
				{
					list($name) = explode('.',$file);
					if (!isset($list[$name])) $list[$name] = ucfirst ($name);
				}
			}
			closedir($dh);
		}
		return $list;
	}

	/**
	 * List available templates
	 *
	 * @param boolean $full_data =false true: value is array with values for keys 'name', 'title', ...
	 * @returns array alphabetically sorted list of templates
	 */
	static function list_templates($full_data=false)
	{
		$list = array('pixelegg'=>null);
		// templates packaged like apps in own directories (containing as setup/setup.inc.php file!)
		$dr = dir(EGW_SERVER_ROOT);
		while (($entry=$dr->read()))
		{
			if ($entry != '..' && !isset($GLOBALS['egw_info']['apps'][$entry]) && is_dir(EGW_SERVER_ROOT.'/'.$entry) &&
				file_exists($f = EGW_SERVER_ROOT . '/' . $entry .'/setup/setup.inc.php'))
			{
				include($f);
				if (isset($GLOBALS['egw_info']['template'][$entry]))
				{
					$list[$entry] = $full_data ? $GLOBALS['egw_info']['template'][$entry] :
						$GLOBALS['egw_info']['template'][$entry]['title'];
				}
			}
		}
		$dr->close();

		return array_filter($list);
	}

	/**
	* Compile entries for topmenu:
	* - regular items: links
	* - info items
	*
	* @param array $vars
	* @param array $apps
	*/
	function topmenu(array $vars,array $apps)
	{
		// array of topmenu info items (orders of the items matter)
		$topmenu_info_items = [
			'user_avatar' => $this->_user_avatar_menu(),
			'update' => ($update = Framework\Updates::notification()) ? $update : null,
			'logout' => (Header\UserAgent::mobile()) ? self::_logout_menu() : null,
			'notifications' => ($GLOBALS['egw_info']['user']['apps']['notifications']) ? self::_get_notification_bell() : null,
			'quick_add' => $vars['quick_add'],
			'print_title' => $this->_print_menu(),
			'darkmode' => self::_darkmode_menu()
		];

		// array of topmenu items (orders of the items matter)
		$topmenu_items = [
			0 => (is_array(($current_user = $this->_current_users()))) ? $current_user : null,
		];

		// Home should be at the top before preferences
		if($GLOBALS['egw_info']['user']['apps']['home'] && isset($apps['home']))
		{
			$this->_add_topmenu_item($apps['home']);
		}

		// array of topmenu preferences items (orders of the items matter)
		$topmenu_preferences = ['darkmode','prefs', 'acl', 'cats', 'security'];

		// set topmenu preferences items
		if($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			foreach ($topmenu_preferences as $prefs)
			{
				$this->add_preferences_topmenu($prefs);
			}
		}

		// call topmenu info items hooks
		Hooks::process('topmenu_info',array(),true);

		// Add extra items added by hooks
		foreach(self::$top_menu_extra as $extra_item) {
			if ($extra_item['name'] == 'search')
			{
				$topmenu_info_items['search'] = '<a href="'.$extra_item['url'].'" title="'.$extra_item['title'].'"></a>';
			}
			else
			{
				array_push($topmenu_items, $extra_item);
			}
		}
		// push logout as the last item in topmenu items list
		array_push($topmenu_items, $apps['logout']);

		// set topmenu info items
		foreach ($topmenu_info_items as $id => $content)
		{
			if (!$content || (in_array($id, ['search', 'quick_add', 'update', 'darkmode', 'print_title']) && (Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'fw_mobile')))
			{
				continue;
			}
			$this->_add_topmenu_info_item($content, $id);
		}
		// set topmenu items
		foreach ($topmenu_items as $item)
		{
			if ($item) $this->_add_topmenu_item($item);
		}
	}

	/**
	 * Add Preferences link to topmenu using settings-hook to know if an app supports Preferences
	 */
	protected function add_preferences_topmenu($type='prefs')
	{
		static $memberships=null;
		if (!isset($memberships)) $memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
		static $types = array(
			'darkmode' => array(
				'title' => 'Darkmode'
			),
			'prefs' => array(
				'title' => 'Preferences',
				'hook'  => 'settings',
			),
			'acl' => array(
				'title' => 'Access',
				'hook'  => 'acl_rights',
			),
			'cats' => array(
				'title' => 'Categories',
				'hook' => 'categories',
				'run_hook' => true,	// acturally run hook, not just look it's implemented
			),
			'security' => array(
				'title' => 'Security & Password',
				'hook' => 'preferences_security',
			),
		);
		if (!$GLOBALS['egw_info']['user']['apps']['preferences'] || $GLOBALS['egw_info']['server']['deny_'.$type] &&
			array_intersect($memberships, (array)$GLOBALS['egw_info']['server']['deny_'.$type]) &&
			!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			return;	// user has no access to Preferences app
		}
		if (isset($types[$type]['run_hook']))
		{
			$apps = Hooks::process($types[$type]['hook']);
			// as all apps answer, we need to remove none-true responses
			foreach($apps as $app => $val)
			{
				if (!$val) unset($apps[$app]);
			}
		}
		else
		{
			$apps = Hooks::implemented($types[$type]['hook']);
		}
		// allways display password in topmenu, if user has rights to change it
		switch ($type)
		{
			case 'security':
				if ($apps || $GLOBALS['egw_info']['server']['2fa_required'] !== 'disabled' ||
					!$GLOBALS['egw']->acl->check('nopasswordchange', 1))
				{
					$this->_add_topmenu_item(array(
						'id'    => 'password',
						'name'  => 'preferences',
						'title' => lang($types[$type]['title']),
						'url'   => 'javascript:egw.open_link("'.
							self::link('/index.php?menuaction=preferences.preferences_password.change').'","_blank","850x580")',
					));
				}
				break;
			case 'darkmode':
				if ((Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'fw_mobile'))
				{
					$this->_add_topmenu_item(array(
						'id'    => 'darkmode',
						'name'  => 'preferences',
						'title' => lang($types[$type]['title']),
						'url'   => "javascript:framework.toggle_darkmode()",
					));
				}
				break;
			default:
				$this->_add_topmenu_item(array(
					'id' => $type,
					'name' => 'preferences',
					'title' => lang($types[$type]['title']),
					'url' => "javascript:egw.show_preferences(\"$type\",".json_encode($apps).')',
				));
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
	abstract function _add_topmenu_item(array $app_data,$alt_label=null);

	/**
	* Add info items to the topmenu template class to be displayed
	*
	* @param string $content Html of item
	* @param string $id =null
	* @access protected
	* @return void
	*/
	abstract function _add_topmenu_info_item($content, $id=null);

	static $top_menu_extra = array();

	/**
	* Called by hooks to add an entry in the topmenu location.
	* Extra entries will be added just before Logout.
	*
	* @param string $id unique element id
	* @param string $url Address for the entry to link to
	* @param string $title Text displayed for the entry
	* @param string $target Optional, so the entry can open in a new page or popup
	* @access public
	* @return void
	*/
	public static function add_topmenu_item($id,$url,$title,$target = '')
	{
		$entry['name'] = $id;
		$entry['url'] = $url;
		$entry['title'] = $title;
		$entry['target'] = $target;

		self::$top_menu_extra[$id] = $entry;
	}

	/**
	* called by hooks to add an icon in the topmenu info location
	*
	* @param string $id unique element id
	* @param string $icon_src src of the icon image. Make sure this nog height then 18pixels
	* @param string $iconlink where the icon links to
	* @param booleon $blink set true to make the icon blink
	* @param mixed $tooltip string containing the tooltip html, or null of no tooltip
	* @access public
	* @return void
	*/
	abstract function topmenu_info_icon($id,$icon_src,$iconlink,$blink=false,$tooltip=null);

	/**
	 * Call and return content of 'after_navbar' hook
	 *
	 * @return string
	 */
	protected function _get_after_navbar()
	{
		ob_start();
		Hooks::process('after_navbar',null,true);
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Return javascript (eg. for onClick) to open manual with given url
	 *
	 * @param string $url
	 */
	abstract function open_manual_js($url);

	/**
	 * Methods to add javascript to framework
	 */

	/**
	 * The include manager manages including js files and their dependencies
	 *
	 * @var IncludeMgr
	 */
	protected static $js_include_mgr;

	/**
	* Checks to make sure a valid package and file name is provided
	*
	* Example call syntax:
	* a) Api\Framework::includeJS('jscalendar','calendar')
	*    --> /phpgwapi/js/jscalendar/calendar.js
	* b) Api\Framework::includeJS('/phpgwapi/inc/calendar-setup.js',array('lang'=>'de'))
	*    --> /phpgwapi/inc/calendar-setup.js?lang=de
	*
	* @param string $package package or complete path (relative to EGW_SERVER_ROOT) to be included
	* @param string|array $file =null file to be included - no ".js" on the end or array with get params
	* @param string $app ='phpgwapi' application directory to search - default = phpgwapi
	* @param boolean $append =true should the file be added
	*/
	static function includeJS($package, $file=null, $app='api', $append=true)
	{
		self::$js_include_mgr->include_js_file($package, $file, $app, $append);
	}

	/**
	 * Set or return all javascript files set via validate_file, optionally clear all files
	 *
	 * @param array $files =null array with pathes relative to EGW_SERVER_ROOT, eg. /api/js/jquery/jquery.js
	 * @param boolean $clear_files =false true clear files after returning them
	 * @return array with pathes relative to EGW_SERVER_ROOT
	 */
	static function js_files(array $files=null, $clear_files=false)
	{
		if (isset($files) && is_array($files))
		{
			self::$js_include_mgr->include_files($files);
		}
		return self::$js_include_mgr->get_included_files($clear_files);
	}

	/**
	 * Used for generating the list of external js files to be included in the head of a page
	 *
	 * NOTE: This method should only be called by the template class.
	 * The validation is done when the file is added so we don't have to worry now
	 *
	 * @param boolean $return_pathes =false false: return Html script tags, true: return array of file pathes relative to webserver_url
	 * @param boolean $clear_files =false true clear files after returning them
	 * @param array& $map on return map file => bundle
	 * @return string|array see $return_pathes parameter
	 */
	static public function get_script_links($return_pathes=false, $clear_files=false, array &$map=null)
	{
		$to_include = Framework\Bundle::js_includes(self::$js_include_mgr->get_included_files($clear_files), $map);

		if ($return_pathes)
		{
			return $to_include;
		}
		$start = '<script type="text/javascript" src="'. $GLOBALS['egw_info']['server']['webserver_url'];
		$end = '">'."</script>\n";
		return "\n".$start.implode($end.$start, $to_include).$end;
	}

	/**
	 *
	 * @var boolean
	 */
	protected static $load_default_css = true;

	/**
	 * Include a css file, either speicified by it's path (relative to EGW_SERVER_ROOT) or appname and css file name
	 *
	 * @param string $app path (relative to EGW_SERVER_ROOT) or appname (if !is_null($name))
	 * @param string $name =null name of css file in $app/templates/{default|$this->template}/$name.css
	 * @param boolean $append =true true append file, false prepend (add as first) file used eg. for template itself
	 * @param boolean $no_default_css =false true do NOT load any default css, only what app explicitly includes
	 * @return boolean false: css file not found, true: file found
	 */
	public static function includeCSS($app, $name=null, $append=true, $no_default_css=false)
	{
		if ($no_default_css)
		{
			self::$load_default_css = false;
		}
		//error_log(__METHOD__."('$app', '$name', append=$append, no_default=$no_default_css) ".function_backtrace());
		return Framework\CssIncludes::add($app, $name, $append, $no_default_css);
	}

	/**
	 * Add registered CSS and javascript to ajax response
	 */
	public static function include_css_js_response()
	{
		$response = Json\Response::get();
		$app = $GLOBALS['egw_info']['flags']['currentapp'];

		// try to add app specific css file
		self::includeCSS($app, 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
			self::includeCSS($app,'app');

		// add all css files from Framework::includeCSS()
		$query = null;
		//error_log(__METHOD__."() Framework\CssIncludes::get()=".array2string(Framework\CssIncludes::get()));
		foreach(Framework\CssIncludes::get() as $path)
		{
			unset($query);
			list($path,$query) = explode('?', $path,2)+[null,null];
			$path .= '?'. ($query ?? filemtime(EGW_SERVER_ROOT.$path));
			$response->includeCSS($GLOBALS['egw_info']['server']['webserver_url'].$path);
		}

		// try to add app specific js file
		if (file_exists(EGW_SERVER_ROOT.($path = '/'.$app.'/js/app.min.js')) ||
			file_exists(EGW_SERVER_ROOT.($path = '/'.$app.'/js/app.js')))
		{
			self::includeJS($path);
		}

		// add all js files from Framework::includeJS()
		$files = Framework\Bundle::js_includes(self::$js_include_mgr->get_included_files());
		foreach($files as $path)
		{
			$response->includeScript($GLOBALS['egw_info']['server']['webserver_url'].$path);
		}
	}

	/**
	 * Set a preference via ajax
	 *
	 * @param string $app
	 * @param string $name
	 * @param string $value
	 */
	public static function ajax_set_preference($app, $name, $value)
	{
		$GLOBALS['egw']->preferences->read_repository();
		if ((string)$value === '')
		{
			$GLOBALS['egw']->preferences->delete($app, $name);
		}
		else
		{
			$GLOBALS['egw']->preferences->add($app, $name, $value);
		}
		$GLOBALS['egw']->preferences->save_repository(True);
	}

	/**
	 * Get Preferences of a certain application via ajax
	 *
	 * @param string $app
	 */
	public static function ajax_get_preference($app)
	{
		// dont block session, while we read preferences, they are not supposed to change something in the session
		$GLOBALS['egw']->session->commit_session();

		if (preg_match('/^[a-z0-9_]+$/i', $app))
		{
			// send etag header, if we are directly called (not via jsonq!)
			if (strpos($_GET['menuaction'], __FUNCTION__) !== false)
			{
				$etag = '"'.$app.'-'.md5(json_encode($GLOBALS['egw_info']['user']['preferences'][$app])).'"';
				if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
				{
					header("HTTP/1.1 304 Not Modified");
					exit();
				}
				header('ETag: '.$etag);
			}
			$response = Json\Response::get();
			$response->call('egw.set_preferences', (array)$GLOBALS['egw_info']['user']['preferences'][$app], $app);
		}
	}

	/**
	 * Create or delete a favorite for multiple users
	 *
	 * Need to be in egw_framework to be called with .template postfix from json.php!
	 *
	 * @param string $app Current application, needed to save preference
	 * @param string $name Name of the favorite
	 * @param string $action "add" or "delete"
	 * @param boolean|int|string $group ID of the group to create the favorite for, or 'all' for all users
	 * @param array $filters =array() key => value pairs for the filter
	 * @return boolean Success
	 */
	public static function ajax_set_favorite($app, $name, $action, $group, $filters = array())
	{
		return Framework\Favorites::set_favorite($app, $name, $action, $group, $filters);
	}

	/**
	 * Get a cachable list of users for the client
	 *
	 * The account source takes care of access and filtering according to preference
	 */
	public static function ajax_user_list()
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		$list = array('accounts'  => array('num_rows' => Link::DEFAULT_NUM_ROWS), 'groups' => array(),
					  'owngroups' => array());
		if($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'primary_group')
		{
			$list['accounts']['filter']['group'] = $GLOBALS['egw_info']['user']['account_primary_group'];
		}
		$contact_obj = new Contacts();
		foreach($list as $type => &$accounts)
		{
			$options = array('account_type' => $type, 'tag_list' => true) + $accounts;
			$accounts = Accounts::link_query('',$options);
		}

		Json\Response::get()->data($list);
		return $list;
	}

	/**
	 * Get certain account-data of given account-id(s)
	 *
	 * @param string|array $_account_ids
	 * @param string $_field ='account_email'
	 * @param boolean $_resolve_groups =false true: return attribute for all members, false return attribute for group itself
	 * @return array account_id => data pairs
	 */
	public static function ajax_account_data($_account_ids, $_field, $_resolve_groups=false)
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		$list = array();
		foreach((array)$_account_ids as $account_id)
		{
			foreach($account_id < 0 && $_resolve_groups ?
				$GLOBALS['egw']->accounts->members($account_id, true) : array($account_id) as $account_id)
			{
				// Make sure name is formatted according to preference
				if($_field == 'account_fullname')
				{
					$list[$account_id] = Accounts::format_username(
						$GLOBALS['egw']->accounts->id2name($account_id, 'account_lid'),
						$GLOBALS['egw']->accounts->id2name($account_id, 'account_firstname'),
						$GLOBALS['egw']->accounts->id2name($account_id, 'account_lastname'),
						$account_id
					);
				}
				else
				{
					$list[$account_id] = $GLOBALS['egw']->accounts->id2name($account_id, $_field);
				}
			}
		}

		Json\Response::get()->data($list);
		return $list;
	}
}
// Init all static variables
Framework::init_static();