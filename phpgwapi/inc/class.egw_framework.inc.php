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
 * @version $Id$
 */

/**
 * eGW API - framework: virtual base class for all template sets
 *
 * This class creates / renders the eGW framework:
 *  a) html header
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
abstract class egw_framework
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
	 *
	 * @return egw_framework
	 */
	function __construct($template)
	{
		$this->template = $template;

		if (!isset($GLOBALS['egw']->framework))
		{
			$GLOBALS['egw']->framework = $this;
		}
		$this->template_dir = '/phpgwapi/templates/'.$template;
	}

	/**
	 * Factory method to instanciate framework object
	 *
	 * @return egw_framwork
	 */
	public static function factory()
	{
		if ((html::$ua_mobile || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile') &&
			file_exists(EGW_SERVER_ROOT.'/pixelegg'))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'pixelegg';
		}
		// default to idots, if no template_set set, to eg. not stall installations if settings use egw::link
		if (empty($GLOBALS['egw_info']['server']['template_set'])) $GLOBALS['egw_info']['server']['template_set'] = 'idots';
		// setup the new eGW framework (template sets)
		$class = $GLOBALS['egw_info']['server']['template_set'].'_framework';
		if (!class_exists($class))	// first try to autoload the class
		{
			require_once($file=EGW_INCLUDE_ROOT.'/phpgwapi/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/class.'.$class.'.inc.php');
			if (!in_array($file,(array)$_SESSION['egw_required_files']))
			{
				$_SESSION['egw_required_files'][] = $file;	// automatic load the used framework class, when the object get's restored
			}
		}
		// fall back to idots if a template does NOT support current user-agent
		if ($class != 'idots_framework' && method_exists($class,'is_supported_user_agent') &&
			!call_user_func(array($class,'is_supported_user_agent')))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'idots';
			return self::factory();
		}
		return new $class($GLOBALS['egw_info']['server']['template_set']);
	}

	/**
	 * Additional attributes or urls for CSP script-src 'self'
	 *
	 * 'unsafe-eval' is currently allways added, as it is used in a couple of places.
	 *
	 * @var array
	 */
	private static $csp_script_src_attrs = array("'unsafe-eval'");

	/**
	 * Set/get Content-Security-Policy attributes for script-src: 'unsafe-eval' and/or 'unsafe-inline'
	 *
	 * Using CK-Editor currently requires both to be set :(
	 *
	 * Old pre-et2 apps might need to call egw_framework::csp_script_src_attrs(array('unsafe-eval','unsafe-inline'))
	 *
	 * EGroupware itself currently still requires 'unsafe-eval'!
	 *
	 * @param string|array $set =array() 'unsafe-eval' and/or 'unsafe-inline' (without quotes!) or URL (incl. protocol!)
	 * @return string with attributes eg. "'unsafe-eval' 'unsafe-inline'"
	 */
	public static function csp_script_src_attrs($set=null)
	{
		foreach((array)$set as $attr)
		{
			if (in_array($attr, array('none', 'self', 'unsafe-eval', 'unsafe-inline')))
			{
				$attr = "'$attr'";	// automatic add quotes
			}
			if (!in_array($attr, self::$csp_script_src_attrs))
			{
				self::$csp_script_src_attrs[] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
		//error_log(__METHOD__."(".array2string($set).") returned ".array2string(implode(' ', self::$csp_script_src_attrs)).' '.function_backtrace());
		return implode(' ', self::$csp_script_src_attrs);
	}

	/**
	 * Additional attributes or urls for CSP style-src 'self'
	 *
	 * 'unsafe-inline' is currently allways added, as it is used in a couple of places.
	 *
	 * @var array
	 */
	private static $csp_style_src_attrs = array("'unsafe-inline'");

	/**
	 * Set/get Content-Security-Policy attributes for style-src: 'unsafe-inline'
	 *
	 * EGroupware itself currently still requires 'unsafe-inline'!
	 *
	 * @param string|array $set =array() 'unsafe-inline' (without quotes!) and/or URL (incl. protocol!)
	 * @return string with attributes eg. "'unsafe-inline'"
	 */
	public static function csp_style_src_attrs($set=null)
	{
		foreach((array)$set as $attr)
		{
			if (in_array($attr, array('none', 'self', 'unsafe-inline')))
			{
				$attr = "'$attr'";	// automatic add quotes
			}
			if (!in_array($attr, self::$csp_style_src_attrs))
			{
				self::$csp_style_src_attrs[] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
		//error_log(__METHOD__."(".array2string($set).") returned ".array2string(implode(' ', self::$csp_script_src_attrs)).' '.function_backtrace());
		return implode(' ', self::$csp_style_src_attrs);
	}

	/**
	 * Additional attributes or urls for CSP connect-src 'self'
	 *
	 * @var array
	 */
	private static $csp_connect_src_attrs = array();

	/**
	 * Set/get Content-Security-Policy attributes for connect-src:
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 * @return string with attributes eg. "'unsafe-inline'"
	 */
	public static function csp_connect_src_attrs($set=null)
	{
		foreach((array)$set as $attr)
		{
			if (!in_array($attr, self::$csp_connect_src_attrs))
			{
				self::$csp_connect_src_attrs[] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
		//error_log(__METHOD__."(".array2string($set).") returned ".array2string(implode(' ', self::$csp_connect_src_attrs)).' '.function_backtrace());
		return implode(' ', self::$csp_connect_src_attrs);
	}

	/**
	 * Additional attributes or urls for CSP frame-src 'self'
	 *
	 * @var array
	 */
	private static $csp_frame_src_attrs;

	/**
	 * Set/get Content-Security-Policy attributes for frame-src:
	 *
	 * Calling this method with an empty array sets no frame-src, but "'self'"!
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 * @return string with attributes eg. "'unsafe-inline'"
	 */
	public static function csp_frame_src_attrs($set=null)
	{
		// set frame-src attrs of API and apps via hook
		if (!isset(self::$csp_frame_src_attrs) && !isset($set))
		{
			$frame_src = array('manual.egroupware.org', 'www.egroupware.org');
			if (($app_additional = $GLOBALS['egw']->hooks->process('csp-frame-src')))
			{
				foreach($app_additional as $addtional)
				{
					if ($addtional) $frame_src = array_unique(array_merge($frame_src, $addtional));
				}
			}
			return self::csp_frame_src_attrs($frame_src);
		}

		if (!isset(self::$csp_frame_src_attrs)) self::$csp_frame_src_attrs = array();

		foreach((array)$set as $attr)
		{
			if (!in_array($attr, self::$csp_frame_src_attrs))
			{
				self::$csp_frame_src_attrs[] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
		//error_log(__METHOD__."(".array2string($set).") returned ".array2string(implode(' ', self::$csp_frame_src_attrs)).' '.function_backtrace());
		return implode(' ', self::$csp_frame_src_attrs);
	}

	/**
	 * Send HTTP headers: Content-Type and Content-Security-Policy
	 */
	public function send_headers()
	{
		// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
		header('Content-type: text/html; charset='.translation::charset());

		// content-security-policy header:
		// - "script-src 'self' 'unsafe-eval'" allows only self and eval (eg. ckeditor), but forbids inline scripts, onchange, etc
		// - "connect-src 'self'" allows ajax requests only to self
		// - "style-src 'self' 'unsave-inline'" allows only self and inline style, which we need
		// - "frame-src 'self' manual.egroupware.org" allows frame and iframe content only for self or manual.egroupware.org
		$csp = "script-src 'self' ".self::csp_script_src_attrs().
			"; connect-src 'self' ".self::csp_connect_src_attrs().
			"; style-src 'self' ".self::csp_style_src_attrs().
			"; frame-src 'self' ".self::csp_frame_src_attrs();

		//$csp = "default-src * 'unsafe-eval' 'unsafe-inline'";	// allow everything
		header("Content-Security-Policy: $csp");
		header("X-Webkit-CSP: $csp");	// Chrome: <= 24, Safari incl. iOS
		header("X-Content-Security-Policy: $csp");	// FF <= 22

		// allow client-side to detect first load aka just logged in
		$reload_count =& egw_cache::getSession(__CLASS__, 'framework-reload');
		self::$extra['framework-reload'] = (int)(bool)$reload_count++;
	}

	/**
	 * Constructor for static variables
	 */
	public static function init_static()
	{
		self::$js_include_mgr = new egw_include_mgr(array(
			// We need LABjs, but putting it through egw_include_mgr causes it to re-load itself
			//'/phpgwapi/js/labjs/LAB.src.js',

			// allways load jquery (not -ui) and egw_json first
			'/phpgwapi/js/jquery/jquery.js',
			// always include javascript helper functions
			'/phpgwapi/js/jsapi/jsapi.js',
			'/phpgwapi/js/jsapi/egw.js',
		));
	}

	/**
	 * PHP4-Constructor
	 *
	 * The constructor instanciates the class in $GLOBALS['egw']->framework, from where it should be used
	 *
	 * @deprecated use __construct()
	 */
	function egw_framework($template)
	{
		self::__construct($template);
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
	 * Redirects direct to a generated link
	 *
	 * @param string $url	The url the link is for
	 * @param string|array	$extravars	Extra params to be passed to the url
	 * @param string $link_app =null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function redirect_link($url, $extravars='', $link_app=null)
	{
		egw::redirect(self::link($url, $extravars), $link_app);
	}

	/**
	 * Renders an applicaton page with the complete eGW framework (header, navigation and menu)
	 *
	 * This is the (new) prefered way to render a page in eGW!
	 *
	 * @param string $content html of the main application area
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
	 * Extra values send as data attributes to script tag of egw.js
	 *
	 * @var array
	 */
	protected static $extra = array();

	/**
	 * Refresh given application $targetapp display of entry $app $id, incl. outputting $msg
	 *
	 * Calling egw_refresh and egw_message on opener in a content security save way
	 *
	 * To provide more information about necessary refresh an automatic 9th parameter is added
	 * containing an object with application-name as attributes containing an array of linked ids
	 * (adding happens in get_extras to give apps time to link new entries!).
	 *
	 * @param string $msg message (already translated) to show, eg. 'Entry deleted'
	 * @param string $app application name
	 * @param string|int $id =null id of entry to refresh
	 * @param string $type =null either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.
	 *	Sorting and filtering are not considered, so if the sort field is changed,
	 *	the row will not be moved.  If the current filtering could include or exclude
	 *	the record, use edit.
	 * - edit: rows changed, but sorting or filtering may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * - null: full reload
	 * @param string $targetapp =null which app's window should be refreshed, default current
	 * @param string|RegExp $replace =null regular expression to replace in url
	 * @param string $with =null
	 * @param string $msg_type =null 'error', 'warning' or 'success' (default)
	 */
	public static function refresh_opener($msg, $app, $id=null, $type=null, $targetapp=null, $replace=null, $with=null, $msg_type=null)
	{
		unset($msg, $app, $id, $type, $targetapp, $replace, $with, $msg_type);	// used only via func_get_args();
		//error_log(__METHOD__.'('.array2string(func_get_args()).')');
		self::$extra['refresh-opener'] = func_get_args();
	}

	/**
	 * Display an error or regular message
	 *
	 * Calls egw_message on client-side in a content security save way
	 *
	 * @param string $msg message to show
	 * @param string $type ='success' 'error', 'warning' or 'success' (default)
	 */
	public static function message($msg, $type='success')
	{
		unset($msg, $type);	// used only via func_get_args();
		self::$extra['message'] = func_get_args();
	}

	/**
	 * Open a popup independent if we run as json or regular request
	 *
	 * @param string $link
	 * @param string $target
	 * @param string $popup
	 */
	public static function popup($link, $target='_blank', $popup='640x480')
	{
		unset($link, $target, $popup);	// used only via func_get_args()
		// default params are not returned by func_get_args!
		$args = func_get_args()+array(null, '_blank', '640x480');

		if (egw_json_request::isJSONRequest())
		{
			egw_json_response::get()->apply('egw.open_link', $args);
		}
		else
		{
			self::$extra['popup'] = $args;
		}
	}

	/**
	 * Close (popup) window, use to replace egw_framework::onload('window.close()') in a content security save way
	 *
	 * @param string $alert_msg ='' optional message to display as alert, before closing the window
	 */
	public static function window_close($alert_msg='')
	{
		//error_log(__METHOD__."()");
		self::$extra['window-close'] = $alert_msg ? $alert_msg : true;

		// are we in ajax_process_content -> just return extra data, with close instructions
		if (preg_match('/etemplate(_new)?(::|\.)ajax_process_content/', $_GET['menuaction']))
		{
			$response = egw_json_response::get();
			$response->generic('et2_load', egw_framework::get_extra());
		}
		else
		{
			$GLOBALS['egw']->framework->render('', false, false);
		}
		common::egw_exit();
	}

	/**
	 * Close (popup) window, use to replace egw_framework::onload('window.close()') in a content security save way
	 */
	public static function window_focus()
	{
		//error_log(__METHOD__."()");
		self::$extra['window-focus'] = true;
	}

	/**
	 * Allow app to store arbitray values in egw script tag
	 *
	 * Attribute name will be "data-$app-$name" and value will be json serialized, if not scalar.
	 *
	 * @param string $app
	 * @param string $name
	 * @param mixed $value
	 */
	public static function set_extra($app, $name, $value)
	{
		self::$extra[$app.'-'.$name] = $value;
	}

	/**
	 * Clear all extra data
	 */
	public static function clear_extra()
	{
		self::$extra = array();
	}

	/**
	 * Allow eg. ajax to query content set via refresh_opener or window_close
	 *
	 * @return array content of egw_framework::$extra
	 */
	public static function get_extra()
	{
		// adding links of refreshed entry, to give others apps more information about necessity to refresh
		if (isset(self::$extra['refresh-opener']) && count(self::$extra['refresh-opener']) <= 8 &&	// do not run twice
			!empty(self::$extra['refresh-opener'][1]) && !empty(self::$extra['refresh-opener'][2]))	// app/id given
		{
			$links = egw_link::get_links(self::$extra['refresh-opener'][1], self::$extra['refresh-opener'][2]);
			$apps = array();
			foreach($links as $link)
			{
				$apps[$link['app']][] = $link['id'];
			}
			while (count(self::$extra['refresh-opener']) < 8)
			{
				self::$extra['refresh-opener'][] = null;
			}
			self::$extra['refresh-opener'][] = $apps;
		}
		return self::$extra;
	}

	/**
	 * Returns the html-header incl. the opening body tag
	 *
	 * @return string with html
	 */
	abstract function header(array $extra=array());

	/**
	 * Returns the html from the body-tag til the main application area (incl. opening div tag)
	 *
	 * If header has NOT been called, also return header content!
	 * No need to manually call header, this allows to postpone header so navbar / sidebox can include JS or CSS.
	 *
	 * @return string with html
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
	 * Returns the html from the closing div of the main application area to the closing html-tag
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
		self::csp_frame_src_attrs(array());	// array() no external frame-sources

		//error_log(__METHOD__."() this->template=$this->template, this->template_dir=$this->template_dir, get_class(this)=".get_class($this));
		$tmpl = new Template(EGW_SERVER_ROOT.$this->template_dir);

		$tmpl->set_file(array('login_form' => html::$ua_mobile?'login_mobile.tpl':'login.tpl'));

		$tmpl->set_var('lang_message',$GLOBALS['loginscreenmessage']);

		// hide change-password fields, if not requested
		if (!$change_passwd)
		{
			$tmpl->set_block('login_form','change_password');
			$tmpl->set_var('change_password', '');
			$tmpl->set_var('lang_password',lang('password'));
			$tmpl->set_var('cd',check_logoutcode($_GET['cd']));
			$tmpl->set_var('cd_class', isset($_GET['cd']) && $_GET['cd'] != 1 ? 'error' : '');
			$last_loginid = $_COOKIE['last_loginid'];
			$last_domain  = $_COOKIE['last_domain'];
			$tmpl->set_var('passwd', '');
			$tmpl->set_var('autofocus_login', 'autofocus');
		}
		else
		{
			$tmpl->set_var('lang_password',lang('Old password'));
			$tmpl->set_var('lang_new_password',lang('New password'));
			$tmpl->set_var('lang_repeat_password',lang('Repeat password'));
			$tmpl->set_var('cd', $change_passwd);
			$tmpl->set_var('cd_class', 'error');
			$last_loginid = $_POST['login'];
			$last_domain  = $_POST['domain'];
			$tmpl->set_var('passwd', $_POST['passwd']);
			$tmpl->set_var('autofocus_login', '');
			$tmpl->set_var('autofocus_new_passwd', 'autofocus');
		}
		if($GLOBALS['egw_info']['server']['show_domain_selectbox'])
		{
			foreach(array_keys($GLOBALS['egw_domain']) as $domain)
			{
				$domains[$domain] = $domain;
			}
			$tmpl->set_var(array(
				'lang_domain'   => lang('domain'),
				'select_domain' => html::select('logindomain',$last_domain,$domains,true,'tabindex="2"',0,false),
			));
		}
		else
		{
			/* trick to make domain section disapear */
			$tmpl->set_block('login_form','domain_selection');
			$tmpl->set_var('domain_selection',$GLOBALS['egw_info']['user']['domain'] ?
			html::input_hidden('logindomain',$GLOBALS['egw_info']['user']['domain']) : '');

			if($last_loginid !== '')
			{
				reset($GLOBALS['egw_domain']);
				list($default_domain) = each($GLOBALS['egw_domain']);

				if(!empty ($last_domain) && $last_domain != $default_domain)
				{
					$last_loginid .= '@' . $last_domain;
				}
			}
		}

		$config_reg = config::read('registration');

		if($config_reg['enable_registration'])
		{
			if ($config_reg['register_link'])
			{
				$reg_link='&nbsp;<a href="'. egw::link('/registration/index.php','lang_code='.$_GET['lang']). '">'.lang('Not a user yet? Register now').'</a><br/>';
			}
			if ($config_reg['lostpassword_link'])
			{
				$lostpw_link='&nbsp;<a href="'. egw::link('/registration/index.php','menuaction=registration.registration_ui.lost_password&lang_code='.$_GET['lang']). '">'.lang('Lost password').'</a><br/>';
			}
			if ($config_reg['lostid_link'])
			{
				$lostid_link='&nbsp;<a href="'. egw::link('/registration/index.php','menuaction=registration.registration_ui.lost_username&lang_code='.$_GET['lang']). '">'.lang('Lost Login Id').'</a><br/>';
			}

			/* if at least one option of "registration" is activated display the registration section */
			if($config_reg['register_link'] || $config_reg['lostpassword_link'] || $config_reg['lostid_link'] )
			{
				$tmpl->set_var(array(
				'register_link'     => $reg_link,
				'lostpassword_link' => $lostpw_link,
				'lostid_link'       => $lostid_link,
				));
			}
			else
			{
				/* trick to make registration section disapear */
				$tmpl->set_block('login_form','registration');
				$tmpl->set_var('registration','');
			}
		}

		$tmpl->set_var('login_url', $GLOBALS['egw_info']['server']['webserver_url'] . '/login.php' . $extra_vars);
		$tmpl->set_var('version', $GLOBALS['egw_info']['server']['versions']['phpgwapi']);
		$tmpl->set_var('login', $last_loginid);

		$tmpl->set_var('lang_username',lang('username'));
		$tmpl->set_var('lang_login',lang('login'));

		$tmpl->set_var('website_title', $GLOBALS['egw_info']['server']['site_title']);
		$tmpl->set_var('template_set',$this->template);

		if (substr($GLOBALS['egw_info']['server']['login_logo_file'], 0, 4) == 'http' ||
			$GLOBALS['egw_info']['server']['login_logo_file'][0] == '/')
		{
			$var['logo_file'] = $GLOBALS['egw_info']['server']['login_logo_file'];
		}
		else
		{
			$var['logo_file'] = common::image('phpgwapi',$GLOBALS['egw_info']['server']['login_logo_file']?$GLOBALS['egw_info']['server']['login_logo_file']:'logo', '', null);	// null=explicit allow svg
		}
		$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';
		if (substr($var['logo_url'],0,4) != 'http')
		{
			$var['logo_url'] = 'http://'.$var['logo_url'];
		}
		$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.eGroupWare.org';
		$tmpl->set_var($var);

		/* language section if activated in site config */
		if (@$GLOBALS['egw_info']['server']['login_show_language_selection'])
		{
			$tmpl->set_var(array(
				'lang_language' => lang('Language'),
				'select_language' => html::select('lang',$GLOBALS['egw_info']['user']['preferences']['common']['lang'],
				translation::get_installed_langs(),true,'tabindex="1"',0,false),
			));
		}
		else
		{
			$tmpl->set_block('login_form','language_select');
			$tmpl->set_var('language_select','');
		}

		/********************************************************\
		* Check if authentification via cookies is allowed       *
		* and place a time selectbox, how long cookie is valid   *
		\********************************************************/

		if($GLOBALS['egw_info']['server']['allow_cookie_auth'])
		{
			$tmpl->set_block('login_form','remember_me_selection');
			$tmpl->set_var('lang_remember_me',lang('Remember me'));
			$tmpl->set_var('select_remember_me',html::select('remember_me', '', array(
				'' => lang('not'),
				'1hour' => lang('1 Hour'),
				'1day' => lang('1 Day'),
				'1week'=> lang('1 Week'),
				'1month' => lang('1 Month'),
				'forever' => lang('Forever'),
			),true,'tabindex="3"',0,false));
		}
		else
		{
			/* trick to make remember_me section disapear */
			$tmpl->set_block('login_form','remember_me_selection');
			$tmpl->set_var('remember_me_selection','');
		}
		$tmpl->set_var('autocomplete', ($GLOBALS['egw_info']['server']['autocomplete_login'] ? 'autocomplete="off"' : ''));

		// load jquery for login screen too
		self::validate_file('jquery', 'jquery');

		$this->render($tmpl->fp('loginout','login_form'),false,false);
	}

	/**
	* displays a login denied message
	*/
	function denylogin_screen()
	{
		$tmpl = new Template(EGW_SERVER_ROOT.$this->template_dir);

		$tmpl->set_file(array(
			'login_form' => 'login_denylogin.tpl'
		));

		$tmpl->set_var(array(
			'template_set' => 'default',
			'deny_msg'     => lang('Oops! You caught us in the middle of system maintainance.').
			'<br />'.lang('Please, check back with us shortly.'),
		));

		// load jquery for deny-login screen too
		self::validate_file('jquery', 'jquery');

		$this->render($tmpl->fp('loginout','login_form'),false,false);
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
			'version'        => $GLOBALS['egw_info']['server']['versions']['phpgwapi']
		);
		$var['page_generation_time'] = '';
		if($GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
		{
			$totaltime = sprintf('%4.2lf',microtime(true) - $GLOBALS['egw_info']['flags']['page_start_time']);

			$var['page_generation_time'] = '<div class="pageGenTime" id="divGenTime_'.$GLOBALS['egw_info']['flags']['currentapp'].'"><span>'.lang('Page was generated in %1 seconds',$totaltime);
			if ($GLOBALS['egw_info']['flags']['session_restore_time'])
			{
				$var['page_generation_time'] .= ' '.lang('(session restored in %1 seconds)',
					sprintf('%4.2lf',$GLOBALS['egw_info']['flags']['session_restore_time']));
			}
			$var['page_generation_time'] .= '</span></div>';
		}
		$var['powered_by'] = '<a href="http://www.egroupware.org/" target="_blank">'.
			lang('Powered by').' Stylite\'s EGroupware '.
			$GLOBALS['egw_info']['server']['versions']['phpgwapi'].'</a>';

		return $var;
	}

	/**
	 * Get the (depricated) application footer
	 *
	 * @return string html
	 */
	protected static function _get_app_footer()
	{
		ob_start();
		// Include the apps footer files if it exists
		if (EGW_APP_INC != EGW_API_INC &&	// this prevents an endless inclusion on the homepage
			                                // (some apps set currentapp in hook_home => it's not releyable)
			(file_exists (EGW_APP_INC . '/footer.inc.php') || isset($_GET['menuaction'])) &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'home' &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'login' &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'logout' &&
			!@$GLOBALS['egw_info']['flags']['noappfooter'])
		{
			list(, $class) = explode('.',(string)$_GET['menuaction']);
			if ($class && is_object($GLOBALS[$class]) && is_array($GLOBALS[$class]->public_functions) &&
				isset($GLOBALS[$class]->public_functions['footer']))
			{
				$GLOBALS[$class]->footer();
			}
			elseif(file_exists(EGW_APP_INC.'/footer.inc.php'))
			{
				include(EGW_APP_INC . '/footer.inc.php');
			}
		}
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
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
			auth::check_password_change($message) !== true)
		{
			self::message($message, 'info');
		}

		// get used language code (with a little xss check, if someone tries to sneak something in)
		if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$GLOBALS['egw_info']['user']['preferences']['common']['lang']))
		{
			$lang_code = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		}
		// IE specific fixes
		if (html::$user_agent == 'msie')
		{
			// tell IE to use it's own mode, not old compatibility modes (set eg. via group policy for all intranet sites)
			// has to be before any other header tags, but meta and title!!!
			$pngfix = '<meta http-equiv="X-UA-Compatible" content="IE=edge" />'."\n";

			// pngfix for IE6 defaults to yes
			if(!$GLOBALS['egw_info']['user']['preferences']['common']['disable_pngfix'] && html::$ua_version < 7)
			{
				$pngfix_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/pngfix.js';
				$pngfix .= '<!-- This solves the Internet Explorer PNG-transparency bug, but only for IE 5.5 - 6.0 and higher -->
				<!--[if lt IE 7.0]>
				<script src="'.$pngfix_src.'" type="text/javascript">
				</script>
				<![endif]-->';
			}
		}

		$app = $GLOBALS['egw_info']['flags']['currentapp'];
		$app_title = isset($GLOBALS['egw_info']['apps'][$app]) ? $GLOBALS['egw_info']['apps'][$app]['title'] : lang($app);
		$app_header = $GLOBALS['egw_info']['flags']['app_header'] ? $GLOBALS['egw_info']['flags']['app_header'] : $app_title;
		$site_title = strip_tags($GLOBALS['egw_info']['server']['site_title'].' ['.($app_header ? $app_header : $app_title).']');

		// send appheader to clientside
		$extra['app-header'] = $app_header;

		if($GLOBALS['egw_info']['flags']['currentapp'] != 'wiki') $robots ='<meta name="robots" content="none" />';
		if (substr($GLOBALS['egw_info']['server']['favicon_file'],0,4) == 'http')
		{
			$var['favicon_file'] = $GLOBALS['egw_info']['server']['favicon_file'];
		}
		else
		{
			$var['favicon_file'] = common::image('phpgwapi',$GLOBALS['egw_info']['server']['favicon_file']?$GLOBALS['egw_info']['server']['favicon_file']:'favicon.ico');
		}

		if ($GLOBALS['egw_info']['flags']['include_wz_tooltip'] &&
			file_exists(EGW_SERVER_ROOT.($wz_tooltip = '/phpgwapi/js/wz_tooltip/wz_tooltip.js')))
		{
			$include_wz_tooltip = '<script src="'.$GLOBALS['egw_info']['server']['webserver_url'].
				$wz_tooltip.'?'.filemtime(EGW_SERVER_ROOT.$wz_tooltip).'" type="text/javascript"></script>';
		}
		return $this->_get_css()+array(
			'img_icon'			=> $var['favicon_file'],
			'img_shortcut'		=> $var['favicon_file'],
			'pngfix'        	=> $pngfix,
			'lang_code'			=> $lang_code,
			'charset'       	=> translation::charset(),
			'website_title' 	=> $site_title,
			'body_tags'     	=> self::_get_body_attribs(),
			'java_script'   	=> self::_get_js($extra),
			'meta_robots'		=> $robots,
			'dir_code'			=> lang('language_direction_rtl') != 'rtl' ? '' : ' dir="rtl"',
			'include_wz_tooltip'=> $include_wz_tooltip,
			'webserver_url'     => $GLOBALS['egw_info']['server']['webserver_url'],
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

		// current users for admins
		$var['current_users'] = $this->_current_users();

		// quick add selectbox
		$var['quick_add'] = $this->_get_quick_add();

		$var['user_info'] = $this->_user_time_info();

		if($GLOBALS['egw_info']['user']['account_lastpwd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login').'<br />'.
				lang('Click this image on the navbar: %1','<img src="'.common::image('preferences','navbar.gif').'">');
		}
		elseif($GLOBALS['egw_info']['server']['change_pwd_every_x_days'] && $GLOBALS['egw_info']['user']['account_lastpwd_change'] < time() - (86400*$GLOBALS['egw_info']['server']['change_pwd_every_x_days']))
		{
			$api_messages = lang('it has been more then %1 days since you changed your password',$GLOBALS['egw_info']['server']['change_pwd_every_x_days']);
		}

		if (substr($GLOBALS['egw_info']['server']['login_logo_file'],0,4) == 'http' ||
			$GLOBALS['egw_info']['server']['login_logo_file'][0] == '/')
		{
			$var['logo_file'] = $GLOBALS['egw_info']['server']['login_logo_file'];
		}
		else
		{
			$var['logo_file'] = common::image('phpgwapi',$GLOBALS['egw_info']['server']['login_logo_file']?$GLOBALS['egw_info']['server']['login_logo_file']:'logo', '', null);	// null=explicit allow svg
		}
		$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';

		if (substr($var['logo_url'],0,4) != 'http')
		{
			$var['logo_url'] = 'http://'.$var['logo_url'];
		}
		$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.eGroupWare.org';

		return $var;
	}

	/**
	 * Returns html with user and time
	 *
	 * @return void
	 */
	protected static function _user_time_info()
	{
		$now = new egw_time();
		$user_info = '<b>'.common::display_fullname() .'</b>'. ' - ' . lang($now->format('l')) . ' ' . $now->format(true);

		$user_tzs = egw_time::getUserTimezones();
		if (count($user_tzs) > 1)
		{
			$tz = $GLOBALS['egw_info']['user']['preferences']['common']['tz'];
			$user_info .= html::form(html::select('tz',$tz,$user_tzs,true),array(),
				'/index.php','','tz_selection',' style="display: inline;"','GET');
		}
		return $user_info;
	}

	/**
	 * Prepare the current users
	 *
	 * @return string
	 */
	protected static function _current_users()
	{
	   if( $GLOBALS['egw_info']['user']['apps']['admin'] && $GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers'])
	   {
		  $current_users = '<a href="' . egw::link('/index.php','menuaction=admin.admin_accesslog.sessions') . '">' .
		  	lang('Current users') . ': <span id="currentusers">' . $GLOBALS['egw']->session->session_count() . '</span></a>';
		  return $current_users;
	   }
	}

	/**
	 * Prepare the quick add selectbox
	 *
	 * @return string
	 */
	protected static function _get_quick_add()
	{
		return '<span id="quick_add"></span>';
	}

	/**
	 * Prepare notification signal (blinking bell)
	 *
	 * @return string
	 */
	protected static function _get_notification_bell()
	{
		return html::image('notifications', 'notificationbell', lang('notifications'),
			'id="notificationbell" style="display: none"');
	}

	/**
	 * URL to check for security or maintenance updates
	 */
	const CURRENT_VERSION_URL = 'http://www.egroupware.org/currentversion';
	/**
	 * How long to cache (in secs) / often to check for updates
	 */
	const VERSIONS_CACHE_TIMEOUT = 7200;
	/**
	 * After how many days of not applied security updates, start warning non-admins too
	 */
	const WARN_USERS_DAYS = 3;

	/**
	 * Check update status
	 *
	 * @return string
	 * @todo Check from client-side, if server-side check fails
	 */
	protected static function _get_update_notification()
	{
		$versions = egw_cache::getTree(__CLASS__, 'versions', function()
		{
			$versions = array();
			$security = null;
			if (($remote = file_get_contents(egw_framework::CURRENT_VERSION_URL, false, egw_framework::proxy_context())))
			{
				list($current, $security) = explode("\n", $remote);
				if (empty($security)) $security = $current;
				$versions = array(
					'current'  => $current,		// last maintenance update
					'security' => $security,	// last security update
				);
			}
			return $versions;
		}, array(), self::VERSIONS_CACHE_TIMEOUT);

		$api = self::api_version();

		if ($versions)
		{
			if (version_compare($api, $versions['security'], '<'))
			{
				if (!$GLOBALS['egw_info']['user']['apps']['admin'] && !self::update_older($versions['security'], self::WARN_USERS_DAYS))
				{
					return null;
				}
				return html::a_href(html::image('phpgwapi', 'security-update', lang('EGroupware security update %1 needs to be installed!', $versions['security'])),
					'http://www.egroupware.org/changelog', null, ' target="_blank"');
			}
			if ($GLOBALS['egw_info']['user']['apps']['admin'] && version_compare($api, $versions['current'], '<'))
			{
				return html::a_href(html::image('phpgwapi', 'update', lang('EGroupware maintenance update %1 available', $versions['current'])),
					'http://www.egroupware.org/changelog', null, ' target="_blank"');
			}
		}
		elseif ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$error = lang('Automatic update check failed, you need to check manually!');
			if (!ini_get('allow_url_fopen'))
			{
				$error .= "\n".lang('%1 setting "%2" = %3 disallows access via http!',
					'php.ini', 'allow_url_fopen', array2string(ini_get('allow_url_fopen')));
			}
			return html::a_href(html::image('phpgwapi', 'update', $error),
				'http://www.egroupware.org/changelog', null, ' target="_blank" data-api-version="'.$api.'"');
		}
		return null;
	}

	/**
	 * Get context to use with file_get_context or fopen to use our proxy settings from setup
	 *
	 * @param string $username =null username for regular basic auth
	 * @param string $password =null password --------- " ----------
	 * @return resource|null context to use with file_get_context/fopen or null if no proxy configured
	 */
	public static function proxy_context($username=null, $password=null)
	{
		$opts = array(
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
	 * Check if version is older then $days days
	 *
	 * @param string $version eg. "14.1.20140715" last part is checked (only if > 20140000!)
	 * @param int $days
	 * @return boolean
	 */
	protected static function update_older($version, $days)
	{
		list(,,$date) = explode('.', $version);
		if ($date < 20140000) return false;
		$version_timestamp = mktime(0, 0, 0, (int)substr($date, 4, 2), (int)substr($date, -2), (int)substr($date, 0, 4));

		return (time() - $version_timestamp) / 86400 > $days;
	}

	/**
	 * Get API version from changelog or database, whichever is bigger
	 *
	 * @param string &$changelog on return path to changelog
	 * @return string
	 */
	public static function api_version(&$changelog=null)
	{
		$changelog = EGW_SERVER_ROOT.'/doc/rpm-build/debian.changes';

		return egw_cache::getTree(__CLASS__, 'api_version', function() use ($changelog)
		{
			$version = preg_replace('/[^0-9.]/', '', $GLOBALS['egw_info']['server']['versions']['phpgwapi']);
			// parse version from changelog
			$matches = null;
			if (($f = fopen($changelog, 'r')) && preg_match('/egroupware-epl \(([0-9.]+)/', fread($f, 80), $matches) &&
				version_compare($version, $matches[1], '<'))
			{
				$version = $matches[1];
				fclose($f);
			}
			return $version;
		}, array(), 300);
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
			throw new egw_exception_wrong_parameter("'$app' not a valid app for this user!");
		}
		$index = '/'.$app.'/index.php';
		if (isset($data['index']))
		{
			if ($data['index'][0] == '/')
			{
				$index = $data['index'];
			}
			else
			{
				$index = '/index.php?menuaction='.$data['index'];
			}
		}
		return egw::link($index,$GLOBALS['egw_info']['flags']['params'][$app]);
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
	 * @param boolean $svg =false should svg images be returned or not:
	 *	true: always return svg, false: never return svg (current default), null: browser dependent, see svg_usable()
	 * @return array
	 */
	protected static function _get_navbar_apps($svg=false)
	{
		list($first) = each($GLOBALS['egw_info']['user']['apps']);
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
		foreach($GLOBALS['egw_info']['user']['apps'] as $app => $data)
		{
			if (is_long($app))
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

				$icon = isset($data['icon']) ?  $data['icon'] : 'navbar';
				$icon_app = isset($data['icon_app']) ? $data['icon_app'] : $app;
				if ($app != $GLOBALS['egw_info']['flags']['currentapp'])
				{
					$apps[$app]['icon']  = common::image($icon_app,Array($icon,'nonav'),'',$svg);
					$apps[$app]['icon_hover']  = common::image_on($icon_app,Array($icon,'nonav'),'-over',$svg);
				}
				else
				{
					$apps[$app]['icon']  = common::image_on($icon_app,Array($icon,'nonav'),'-over',$svg);
					$apps[$app]['icon_hover']  = common::image($icon_app,Array($icon,'nonav'),'',$svg);
				}
			}
		}

		//Sort the applications accordingly to their user sort setting
		if ($GLOBALS['egw_info']['user']['preferences']['common']['user_apporder'])
		{
			//Sort the application array using the user_apporder array as sort index
			self::$user_apporder =
				unserialize($GLOBALS['egw_info']['user']['preferences']['common']['user_apporder']);
			uasort($apps, 'egw_framework::_sort_apparray');
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

		if ($GLOBALS['egw_info']['user']['apps']['preferences'])	// preferences last
		{
			$prefs = $apps['preferences'];
			unset($apps['preferences']);
			$apps['preferences'] = $prefs;
		}

		// We handle this here becuase its special
		$apps['about']['title'] = 'EGroupware';

		$apps['about']['url']   = egw::link('/about.php');
		$apps['about']['icon']  = common::image('phpgwapi',Array('about','nonav'));
		$apps['about']['icon_hover']  = common::image_on('phpgwapi',Array('about','nonav'),'-over');
		$apps['about']['name'] = 'about';

		$apps['logout']['title'] = lang('Logout');
		$apps['logout']['name'] = 'logout';
		$apps['logout']['url']   = egw::link('/logout.php');
		$apps['logout']['icon']  = common::image('phpgwapi',Array('logout','nonav'));
		$apps['logout']['icon_hover']  = common::image_on('phpgwapi',Array('logout','nonav'),'-over');

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
			// Load these first
			// Cascade should go:
			//  Libs < etemplate2 < framework/theme < app < print
			// Enhanced selectboxes (et1)
			self::includeCSS('/phpgwapi/js/jquery/chosen/chosen.css');

			// eTemplate2 uses jQueryUI, so load it first so et2 can override if needed
			self::includeCSS("/phpgwapi/js/jquery/jquery-ui/redmond/jquery-ui.css");

			// eTemplate2 - load in top so sidebox has styles too
			self::includeCSS('/etemplate/templates/default/etemplate2.css');

			// Category styles
			categories::css(categories::GLOBAL_APPNAME);

			// For mobile user-agent we prefer mobile theme over selected one with a final fallback to theme named as template
			$themes_to_check = array();
			if (html::$ua_mobile) $themes_to_check[] = $this->template_dir.'/css/mobile.css';
			$themes_to_check[] = $this->template_dir.'/css/'.$GLOBALS['egw_info']['user']['preferences']['common']['theme'].'.css';
			$themes_to_check[] = $this->template_dir.'/css/'.$this->template.'.css';
			foreach($themes_to_check as $theme_css)
			{
				if (file_exists(EGW_SERVER_ROOT.$theme_css)) break;
			}
			self::includeCSS($theme_css);

			// search for app specific css file, so it can customize the theme
			self::includeCSS($GLOBALS['egw_info']['flags']['currentapp'], 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
				self::includeCSS($GLOBALS['egw_info']['flags']['currentapp'], 'app');

			// sending print css last, so it can overwrite anything
			$print_css = $this->template_dir.'/print.css';
			if(!file_exists(EGW_SERVER_ROOT.$print_css))
			{
				$print_css = '/phpgwapi/templates/idots/print.css';
			}
			self::includeCSS($print_css);
		}
		// add all css files from self::includeCSS
		$max_modified = 0;
		$debug_minify = $GLOBALS['egw_info']['server']['debug_minify'] === 'True';
		$base_path = $GLOBALS['egw_info']['server']['webserver_url'];
		if ($base_path[0] != '/') $base_path = parse_url($base_path, PHP_URL_PATH);
		$css_files = '';
		foreach(self::$css_include_files as $path)
		{
			foreach(self::resolve_css_includes($path) as $path)
			{
				list($file,$query) = explode('?',$path,2);
				if (($mod = filemtime(EGW_SERVER_ROOT.$file)) > $max_modified) $max_modified = $mod;

				// do NOT include app.css or categories.php, as it changes from app to app
				if ($debug_minify || substr($path, -8) == '/app.css' || substr($file,-14) == 'categories.php')
				{
					$css_files .= '<link href="'.$GLOBALS['egw_info']['server']['webserver_url'].$path.($query ? '&' : '?').$mod.'" type="text/css" rel="StyleSheet" />'."\n";
				}
				else
				{
					$css_file .= ($css_file ? ',' : '').substr($path, 1);
				}
			}
		}
		if (!$debug_minify)
		{
			$css = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/inc/min/?';
			if ($base_path && $base_path != '/') $css .= 'b='.substr($base_path, 1).'&';
			$css .= 'f='.$css_file .
				($GLOBALS['egw_info']['server']['debug_minify'] === 'debug' ? '&debug' : '').
				'&'.$max_modified;
			$css_files = '<link href="'.$css.'" type="text/css" rel="StyleSheet" />'."\n".$css_files;
		}
		return array(
			'app_css'   => $app_css,
			'css_file'  => $css_files,
		);
	}

	/**
	 * Parse beginning of given CSS file for /*@import url("...") statements
	 *
	 * @param string $path EGroupware relative path eg. /phpgwapi/templates/default/some.css
	 * @return array parsed pathes (EGroupware relative) including $path itself
	 */
	protected static function resolve_css_includes($path, &$pathes=array())
	{
		$matches = null;

		list($file) = explode('?',$path,2);
		if (($to_check = file_get_contents (EGW_SERVER_ROOT.$file, false, null, -1, 1024)) &&
			stripos($to_check, '/*@import') !== false && preg_match_all('|/\*@import url\("([^"]+)"|i', $to_check, $matches))
		{
			foreach($matches[1] as $import_path)
			{
				if ($import_path[0] != '/')
				{
					$dir = dirname($path);
					while(substr($import_path,0,3) == '../')
					{
						$dir = dirname($dir);
						$import_path = substr($import_path, 3);
					}
					$import_path = ($dir != '/' ? $dir : '').'/'.$import_path;
				}
				self::resolve_css_includes($import_path, $pathes);
			}
		}
		$pathes[] = $path;

		return $pathes;
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
		// add configuration, link-registry, images, user-data and -perferences for non-popup windows
		// specifying etag in url to force reload, as we send expires header
		if ($GLOBALS['egw_info']['flags']['js_link_registry'])
		{
			self::validate_file('/phpgwapi/config.php', array(
				'etag' => md5(json_encode(config::clientConfigs()).egw_link::json_registry()),
			));
			self::validate_file('/phpgwapi/images.php', array(
				'template' => $GLOBALS['egw_info']['server']['template_set'],
				'etag' => md5(json_encode(common::image_map($GLOBALS['egw_info']['server']['template_set']))),
				'svg' => 0,	// always load non-svg image map
			));
			self::validate_file('/phpgwapi/user.php', array(
				'user' => $GLOBALS['egw_info']['user']['account_lid'],
				'lang' => $GLOBALS['egw_info']['user']['preferences']['common']['lang'],
				// add etag on url, so we can set an expires header
				'etag' => md5(json_encode($GLOBALS['egw_info']['user']['preferences']['common']).
					$GLOBALS['egw']->accounts->json($GLOBALS['egw_info']['user']['account_id'])),
			));
		}

		$extra['url'] = $GLOBALS['egw_info']['server']['webserver_url'];
		$extra['include'] = array_map(function($str){return substr($str,1);}, self::get_script_links(true), array(1));
		$extra['app'] = $GLOBALS['egw_info']['flags']['currentapp'];

		// Load LABjs ONCE here
		$java_script .= '<script type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].
				'/phpgwapi/js/labjs/LAB.src.js?'.filemtime(EGW_SERVER_ROOT.'/phpgwapi/js/labjs/LAB.src.js')."\"></script>\n".
			'<script type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].
				'/phpgwapi/js/jsapi/egw.js?'.filemtime(EGW_SERVER_ROOT.'/phpgwapi/js/jsapi/egw.js').'" id="egw_script_id"';

		// add values of extra parameter and class var as data attributes to script tag of egw.js
		foreach($extra+self::$extra as $name => $value)
		{
			if (is_array($value)) $value = json_encode($value);
			// we need to double encode (html::htmlspecialchars( , TRUE)), as otherwise we get invalid json, eg. for quotes
			$java_script .= ' data-'.$name."=\"". html::htmlspecialchars($value, true)."\"";
		}
		$java_script .= "></script>\n";

		if(@isset($_GET['menuaction']))
		{
			list(, $class) = explode('.',$_GET['menuaction']);
			if(is_array($GLOBALS[$class]->public_functions) &&
				$GLOBALS[$class]->public_functions['java_script'])
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
	 * List available themes
	 *
	 * Themes are css file in the template directory
	 *
	 * @param string $themes_dir ='css'
	 */
	function list_themes()
	{
		$list = array();
		if (($dh = @opendir(EGW_SERVER_ROOT.$this->template_dir . SEP . 'css')))
		{
			while (($file = readdir($dh)))
			{
				if (preg_match('/'."\.css$".'/i', $file))
				{
					list($name) = explode('.',$file);
					$list[$name] = $name;
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
		$list = array('pixelegg'=>null,'jdots'=>null,'idots'=>null);
		// templates packaged in the api
		$d = dir(EGW_SERVER_ROOT . '/phpgwapi/templates');
		while (($entry=$d->read()))
		{
			if ($entry != '..' && file_exists(EGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry .'/class.'.$entry.'_framework.inc.php'))
			{
				if (file_exists ($f = EGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry . '/setup/setup.inc.php'))
				{
					include($f);
					$list[$entry] = $full_data ? $GLOBALS['egw_info']['template'][$entry] :
						$GLOBALS['egw_info']['template'][$entry]['title'];
				}
				else
				{
					$list[$entry] = $full_data ? array(
						'name'  => $entry,
						'title' => $entry,
					) : $entry;
				}
			}
		}
		$d->close();
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
		if($GLOBALS['egw_info']['user']['apps']['home'] && isset($apps['home']))
		{
			$this->_add_topmenu_item($apps['home']);
		}

		if($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			$this->add_preferences_topmenu('prefs');
			$this->add_preferences_topmenu('acl');
			$this->add_preferences_topmenu('cats');
		}

		// allways display password in topmenu, if user has rights to change it
		if ($GLOBALS['egw_info']['user']['apps']['preferences'] &&
			!$GLOBALS['egw']->acl->check('nopasswordchange', 1, 'preferences'))
		{
			$this->_add_topmenu_item(array(
				'id'    => 'password',
				'name'  => 'preferences',
				'title' => lang('Password'),
				'url'   => "javascript:egw.open_link('".
					egw::link('/index.php?menuaction=preferences.preferences_password.change')."','_blank','400x270')",
			));
		}
		/* disable help until content is reworked
		if($GLOBALS['egw_info']['user']['apps']['manual'] && isset($apps['manual']))
		{
			$this->_add_topmenu_item(array_merge($apps['manual'],array('title' => lang('Help'))));
		}*/

		$GLOBALS['egw']->hooks->process('topmenu_info',array(),true);
		// Add extra items added by hooks
		foreach(self::$top_menu_extra as $extra_item) {
			$this->_add_topmenu_item($extra_item);
		}

		$this->_add_topmenu_item($apps['logout']);

		if (($update = self::_get_update_notification()))
		{
			$this->_add_topmenu_info_item($update, 'update');
		}
		if($GLOBALS['egw_info']['user']['apps']['notifications'])
		{
			$this->_add_topmenu_info_item(self::_get_notification_bell(), 'notifications');
		}
		$this->_add_topmenu_info_item($vars['user_info'], 'user_info');
		$this->_add_topmenu_info_item($vars['current_users'], 'current_users');
		$this->_add_topmenu_info_item($vars['quick_add'], 'quick_add');
	}

	/**
	 * Add preferences link to topmenu using settings-hook to know if an app supports preferences
	 */
	protected function add_preferences_topmenu($type='prefs')
	{
		static $memberships=null;
		if (!isset($memberships)) $memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
		static $types = array(
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
		);
		if (!$GLOBALS['egw_info']['user']['apps']['preferences'] || $GLOBALS['egw_info']['server']['deny_'.$type] &&
			array_intersect($memberships, (array)$GLOBALS['egw_info']['server']['deny_'.$type]) &&
			!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			return;	// user has no access to preferences app
		}
		if (isset($types[$type]['run_hook']))
		{
			$apps = $GLOBALS['egw']->hooks->process($types[$type]['hook']);
			// as all apps answer, we need to remove none-true responses
			foreach($apps as $app => $val)
			{
				if (!$val) unset($apps[$app]);
			}
		}
		else
		{
			$apps = $GLOBALS['egw']->hooks->hook_implemented($types[$type]['hook']);
		}
		$this->_add_topmenu_item(array(
			'id' => $type,
			'name' => 'preferences',
			'title' => lang($types[$type]['title']),
			'url' => "javascript:egw.show_preferences(\"$type\",".json_encode($apps).')',
		));
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
	* @param string $content html of item
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
		$GLOBALS['egw']->hooks->process('after_navbar',null,true);
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
	 * Body tags for onLoad, onUnload and onResize
	 *
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @var array
	 */
	protected static $body_tags = array();

	/**
	 * Sets an onLoad action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onLoad']))
		{
			self::$body_tags['onLoad'] = $code;
		}
		else
		{
			self::$body_tags['onLoad'] .= $code;
		}
		return self::$body_tags['onLoad'];
	}

	/**
	 * Sets an onUnload action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onunload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onUnload']))
		{
			self::$body_tags['onUnload'] = $code;
		}
		else
		{
			self::$body_tags['onUnload'] .= $code;
		}
		return self::$body_tags['onUnload'];
	}

	/**
	 * Sets an onBeforeUnload action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onbeforeunload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onBeforeUnload']))
		{
			self::$body_tags['onBeforeUnload'] = $code;
		}
		else
		{
			self::$body_tags['onBeforeUnload'] .= $code;
		}
		return self::$body_tags['onBeforeUnload'];
	}

	/**
	 * Sets an onResize action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onresize($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onResize']))
		{
			self::$body_tags['onResize'] = $code;
		}
		else
		{
			self::$body_tags['onResize'] .= $code;
		}
		return self::$body_tags['onResize'];
	}

	/**
	 * Adds on(Un)Load= attributes to the body tag of a page
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

	/**
	 * The include manager manages including js files and their dependencies
	 */
	protected static $js_include_mgr;

	/**
	* Checks to make sure a valid package and file name is provided
	*
	* Example call syntax:
	* a) egw_framework::validate_file('jscalendar','calendar')
	*    --> /phpgwapi/js/jscalendar/calendar.js
	* b) egw_framework::validate_file('/phpgwapi/inc/calendar-setup.js',array('lang'=>'de'))
	*    --> /phpgwapi/inc/calendar-setup.js?lang=de
	*
	* @param string $package package or complete path (relative to EGW_SERVER_ROOT) to be included
	* @param string|array $file =null file to be included - no ".js" on the end or array with get params
	* @param string $app ='phpgwapi' application directory to search - default = phpgwapi
	* @param boolean $append =true should the file be added
	*
	* @discuss The browser specific option loads the file which is in the correct
	*          browser folder. Supported folder are those supported by class.browser.inc.php
	*
	* @returns bool was the file found?
	*/
	static function validate_file($package, $file=null, $app='phpgwapi')
	{
		self::$js_include_mgr->include_js_file($package, $file, $app);
	}

	/**
	 * Set or return all javascript files set via validate_file, optionally clear all files
	 *
	 * @param array $files =null array with pathes relative to EGW_SERVER_ROOT, eg. /phpgwapi/js/jquery/jquery.js
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
	 * @param boolean $return_pathes =false false: return html script tags, true: return array of file pathes relative to webserver_url
	 * @param boolean $clear_files =false true clear files after returning them
	 * @return string|array see $return_pathes parameter
	 */
	static public function get_script_links($return_pathes=false, $clear_files=false)
	{
		$to_include = self::bundle_js_includes(self::$js_include_mgr->get_included_files($clear_files));

		if ($return_pathes)
		{
			return $to_include;
		}
		$start = '<script type="text/javascript" src="'. $GLOBALS['egw_info']['server']['webserver_url'];
		$end = '">'."</script>\n";
		return "\n".$start.implode($end.$start, $to_include).$end;
	}

	/**
	 * Devide js-includes in bundles of javascript files to include eg. api or etemplate2, if minifying is enabled
	 *
	 * @param array $js_includes files to include with egw relative url
	 * @return array egw relative urls to include incl. bundels/minify urls, if enabled
	 */
	public static function bundle_js_includes(array $js_includes)
	{
		$file2bundle = array();
		if ($GLOBALS['egw_info']['server']['debug_minify'] !== 'True')
		{
			// get used bundles and cache them on tree-level for 2h
			//$bundles = self::get_bundles(); egw_cache::setTree(__CLASS__, 'bundles', $bundles, 7200);
			$bundles = egw_cache::getTree(__CLASS__, 'bundles', array(__CLASS__, 'get_bundles'), array(), 7200);
			$bundles_ts = $bundles['.ts'];
			unset($bundles['.ts']);
			foreach($bundles as $name => $files)
			{
				$file2bundle += array_combine($files, array_fill(0, count($files), $name));
			}
		}
		$to_include = $included_bundles = array();
		$query = null;
		foreach($js_includes as $file)
		{
			if (!isset($to_include[$file]))
			{
				if (($bundle = $file2bundle[$file]))
				{
					//error_log(__METHOD__."() requiring boundle $bundle for $file");
					if (!in_array($bundle, $included_bundles))
					{
						$max_modified = 0;
						$to_include = array_merge($to_include, self::bundle_urls($bundles[$bundle], $max_modified));
						$included_bundles[] = $bundle;
						// check if bundle-config is more recent then
						if ($max_modified > $bundles_ts)
						{
							// force new bundle config by deleting cached one and call ourself again
							egw_cache::unsetTree(__CLASS__, 'bundles');
							return self::bundle_js_includes($js_includes);
						}
					}
				}
				else
				{
					unset($query);
					list($path, $query) = explode('?', $file, 2);
					$mod = filemtime(EGW_SERVER_ROOT.$path);

					$to_include[$file] = $path.'?'.$mod.($query ? '&'.$query : '');
				}
			}
		}
		/*_debug_array($js_includes);
		_debug_array(array_values($to_include));
		die('STOP');*/

		return array_values($to_include);
	}

	/**
	 * Generate bundle url(s) for given js files
	 *
	 * @param array $js_includes
	 * @param int& $max_modified =null on return maximum modification time of bundle
	 * @return array js-files (can be more then one, if one of given files can not be bundeled)
	 */
	protected static function bundle_urls(array $js_includes, &$max_modified=null)
	{
		$debug_minify = $GLOBALS['egw_info']['server']['debug_minify'] === 'True';
		$to_include = $to_minify = array();
		$max_modified = 0;
		$query = null;
		foreach($js_includes as $path)
		{
			if ($path == '/phpgwapi/js/jsapi/egw.js') continue;	// loaded via own tag, and we must not load it twice!

			unset($query);
			list($path,$query) = explode('?',$path,2);
			$mod = filemtime(EGW_SERVER_ROOT.$path);

			// for now minify does NOT support query parameters, nor php files generating javascript
			if ($debug_minify || $query || substr($path, -3) != '.js' || strpos($path,'ckeditor') !== false ||
				substr($path, -7) == '/app.js')	// do NOT include app.js, as it changes from app to app
			{
				$path .= '?'. $mod.($query ? '&'.$query : '');
				$to_include[] = $path;
			}
			else
			{
				if ($mod > $max_modified) $max_modified = $mod;
				$to_minify[] = substr($path,1);
			}
		}
		if (!$debug_minify && $to_minify)
		{
			$base_path = $GLOBALS['egw_info']['server']['webserver_url'];
			if ($base_path[0] != '/') $base_path = parse_url($base_path, PHP_URL_PATH);
			$path = '/phpgwapi/inc/min/?'.($base_path && $base_path != '/' ? 'b='.substr($base_path, 1).'&' : '').
				'f='.implode(',', $to_minify) .
				($GLOBALS['egw_info']['server']['debug_minify'] === 'debug' ? '&debug' : '').
				'&'.$max_modified;
			// need to include minified javascript before not minified stuff like jscalendar-setup, as it might depend on it
			array_unshift($to_include, $path);
		}
		//error_log(__METHOD__."(".array2string($js_includes).") returning ".array2string($to_include));
		return $to_include;
	}

	/**
	 * Maximum number of files in a bundle
	 *
	 * We split bundles, if they contain more then these number of files,
	 * because IE silently stops caching them, if Content-Length get's too big.
	 *
	 * IE11 cached 142kb compressed api bundle, but not 190kb et2 bundle.
	 * Splitting et2 bundle in max 50 files chunks, got IE11 to cache both bundles.
	 */
	const MAX_BUNDLE_FILES = 50;

	/**
	 * Return typical bundes we use:
	 * - api stuff phpgwapi/js/jsapi/* and it's dependencies incl. jquery
	 * - etemplate2 stuff not including api bundle, but jquery-ui
	 *
	 * @return array bundle-url => array of contained files
	 */
	public static function get_bundles()
	{
		$inc_mgr = new egw_include_mgr();
		$bundles = array();

		$api_max_mod = $et2_max_mod = $jdots_max_mod = 0;

		// generate api bundle
		$inc_mgr->include_js_file('/phpgwapi/js/jquery/jquery.js');
		$inc_mgr->include_js_file('/phpgwapi/js/jquery/jquery-ui.js');
		$inc_mgr->include_js_file('/phpgwapi/js/jsapi/jsapi.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_json.js');
		$inc_mgr->include_js_file('/phpgwapi/js/jsapi/egw.js');
		// dhtmlxTree (dhtmlxMenu get loaded via dependency in egw_menu_dhtmlx.js)
		$inc_mgr->include_js_file('/phpgwapi/js/dhtmlxtree/codebase/dhtmlxcommon.js');
		$inc_mgr->include_js_file('/phpgwapi/js/dhtmlxtree/sources/dhtmlxtree.js');
		$inc_mgr->include_js_file('/phpgwapi/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js');
		// actions
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_action.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_keymanager.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_action_popup.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_action_dragdrop.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_dragdrop_dhtmlx_tree.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_menu.js');
		$inc_mgr->include_js_file('/phpgwapi/js/egw_action/egw_menu_dhtmlx.js');
		// include choosen in api, as old eTemplate uses it and fail if it pulls in half of et2
		$inc_mgr->include_js_file('/phpgwapi/js/jquery/chosen/chosen.jquery.js');
		// include CKEditor in api, as old eTemplate uses it too
		$inc_mgr->include_js_file('/phpgwapi/js/ckeditor/ckeditor.js');
		$inc_mgr->include_js_file('/phpgwapi/js/ckeditor/config.js');
		$bundles['api'] = $inc_mgr->get_included_files();
		self::bundle_urls($bundles['api'], $api_max_mod);

		// generate et2 bundle (excluding files in api bundle)
		//$inc_mgr->include_js_file('/etemplate/js/lib/jsdifflib/difflib.js');	// it does not work with "use strict" therefore included in front
		$inc_mgr->include_js_file('/etemplate/js/etemplate2.js');
		$bundles['et2'] = array_diff($inc_mgr->get_included_files(), $bundles['api']);
		self::bundle_urls($bundles['et2'], $et2_max_mod);

		// generate jdots bundle, if installed
		/* switching jdots bundle off, as fw_pixelegg will cause whole jdots bundle incl. fw_jdots to include
		if (file_exists(EGW_SERVER_ROOT.'/jdots'))
		{
			$inc_mgr->include_js_file('/jdots/js/fw_jdots.js');
			$bundles['jdots'] = array_diff($inc_mgr->get_included_files(), call_user_func_array('array_merge', $bundles));
			self::bundle_urls($bundles['jdots'], $jdots_max_mod);
		}*/

		// automatic split bundles with more then MAX_BUNDLE_FILES (=50) files
		foreach($bundles as $name => $files)
		{
			$n = '';
			while (count($files) > self::MAX_BUNDLE_FILES*(int)$n)
			{
				$files80 = array_slice($files, self::MAX_BUNDLE_FILES*(int)$n, self::MAX_BUNDLE_FILES, true);
				$bundles[$name.$n++] = $files80;
			}
		}

		// store max modification time of all files in all bundles
		$bundles['.ts'] = max(array($api_max_mod, $et2_max_mod, $jdots_max_mod));

		//error_log(__METHOD__."() returning ".array2string($bundles));
		return $bundles;
	}

	/**
	 * Content from includeCSS calls
	 *
	 * @var array
	 */
	protected static $css_include_files = array();

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
			self::$css_include_files = array();
		}

		if (!is_null($name))
		{
			$path = '/'.$app.'/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/'.$name.'.css';
			if (!file_exists(EGW_SERVER_ROOT.$path))
			{
				$path = '/'.$app.'/templates/default/'.$name.'.css';
			}
		}
		else
		{
			$path = $app;
		}
		if (!file_exists(EGW_SERVER_ROOT.$path) && !file_exists(EGW_SERVER_ROOT . parse_url($path,PHP_URL_PATH)))
		{
			//error_log(__METHOD__."($app,$name) $path NOT found!");
			return false;
		}
		if (!in_array($path,self::$css_include_files))
		{
			if ($append)
			{
				self::$css_include_files[] = $path;
			}
			else
			{
				self::$css_include_files = array_merge(array($path), self::$css_include_files);
			}
		}
		return true;
	}

	/**
	 * Add registered CSS and javascript to ajax response
	 */
	public static function include_css_js_response()
	{
		$response = egw_json_response::get();
		$app = $GLOBALS['egw_info']['flags']['currentapp'];

		// try to add app specific css file
		self::includeCSS($app, 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
			self::includeCSS($app,'app');

		// add all css files from egw_framework::includeCSS()
		$query = null;
		foreach(self::$css_include_files as $path)
		{
			unset($query);
			list($path,$query) = explode('?',$path,2);
			$path .= '?'. ($query ? $query : filemtime(EGW_SERVER_ROOT.$path));
			$response->includeCSS($GLOBALS['egw_info']['server']['webserver_url'].$path);
		}

		// try to add app specific js file
		self::validate_file('.', 'app', $app);

		// add all js files from egw_framework::validate_file()
		$files = self::bundle_js_includes(self::$js_include_mgr->get_included_files());
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
	 * Get preferences of a certain application via ajax
	 *
	 * @param string $app
	 */
	public static function ajax_get_preference($app)
	{
		if (preg_match('/^[a-z0-9_]+$/i', $app))
		{
			// send etag header, if we are directly called (not via jsonq!)
			if (strpos($_GET['menuaction'], __FUNCTION__) !== false)
			{
				$etag = '"'.$app.'-'.md5(json_encode($GLOBALS['egw_info']['user']['preferences'][$app])).'"';
				if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
				{
					header("HTTP/1.1 304 Not Modified");
					common::egw_exit();
				}
				header('ETag: '.$etag);
			}
			$response = egw_json_response::get();
			$response->call('egw.set_preferences', (array)$GLOBALS['egw_info']['user']['preferences'][$app], $app);
		}
	}

	/**
	 * Include favorites when generating the page server-side
	 *
	 * @param string $app application, needed to find preferences
	 * @param string $default =null preference name for default favorite, default "nextmatch-$app.index.rows-favorite"
	 * @deprecated use egw_favorites::favorite_list
	 * @return array with a single sidebox menu item (array) containing html for favorites
	 */
	public static function favorite_list($app, $default=null)
	{
		return egw_favorites::list_favorites($app, $default);
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
		return egw_favorites::set_favorite($app, $name, $action, $group, $filters);
	}

	/**
	 * Get a cachable list of users for the client
	 *
	 * The account source takes care of access and filtering according to preference
	 */
	public static function ajax_user_list()
	{
		$list = array('accounts' => array(),'groups' => array(), 'owngroups' => array());
		if($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'primary_group')
		{
			$list['accounts']['filter']['group'] = $GLOBALS['egw_info']['user']['account_primary_group'];
		}
		foreach($list as $type => &$accounts)
		{
			$options = array('account_type' => $type) + $accounts;
			$key_pair = accounts::link_query('',$options);
			$accounts = array();
			foreach($key_pair as $account_id => $name)
			{
				$accounts[] = array('value' => $account_id, 'label' => $name);
			}
		}

		egw_json_response::get()->data($list);
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
		$list = array();
		foreach((array)$_account_ids as $account_id)
		{
			foreach($account_id < 0 && $_resolve_groups ?
				$GLOBALS['egw']->accounts->members($account_id, true) : array($account_id) as $account_id)
			{
				// Make sure name is formatted according to preference
				if($_field == 'account_fullname')
				{
					$list[$account_id] = common::display_fullname(
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

		egw_json_response::get()->data($list);
		return $list;
	}
}

// Init all static variables
egw_framework::init_static();

/**
 * Public functions to be compatible with the exiting eGW framework
 */
if (!function_exists('parse_navbar'))
{
	/**
	 * echo's out the navbar
	 *
	 * @deprecated use $GLOBALS['egw']->framework->navbar() or $GLOBALS['egw']->framework::render()
	 */
	function parse_navbar()
	{
		echo $GLOBALS['egw']->framework->navbar();
	}
}

if (!function_exists('display_sidebox'))
{
	/**
	 * echo's out a sidebox menu
	 *
	 * @deprecated use $GLOBALS['egw']->framework->sidebox()
	 */
	function display_sidebox($appname,$menu_title,$_file)
	{
		$file = str_replace('preferences.uisettings.index', 'preferences.preferences_settings.index', $_file);
		$GLOBALS['egw']->framework->sidebox($appname,$menu_title,$file);
	}
 }
