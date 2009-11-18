<?php
/**
 * eGW API - framework
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
	 * Constructor
	 *
	 * The constructor instanciates the class in $GLOBALS['egw']->framework, from where it should be used
	 *
	 * @return egw_framework
	 */
	function egw_framework($template)
	{
		$this->template = $template;

		if (!is_object($GLOBALS['egw']->framework))
		{
			$GLOBALS['egw']->framework = $this;
		}
	}

	/**
	 * Renders an applicaton page with the complete eGW framework (header, navigation and menu)
	 *
	 * This is the (new) prefered way to render a page in eGW!
	 *
	 * @param string $content html of the main application area
	 * @param string $app_header=null application header, default what's set in $GLOBALS['egw_info']['flags']['app_header']
	 * @param string $navbar=null show the navigation, default !$GLOBALS['egw_info']['flags']['nonavbar'], false gives a typical popu
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
	 * @return string with html
	 */
	abstract function header();

	/**
	 * Returns the html from the body-tag til the main application area (incl. opening div tag)
	 *
	 * @return string with html
	 */
	abstract function navbar();

	/**
	 * Returns the content of one sidebox
	 *
	 * @param string $appname
	 * @param string $menu_title
	 * @param array $file
	 */
	abstract function sidebox($appname,$menu_title,$file);

	/**
	 * Returns the html from the closing div of the main application area to the closing html-tag
	 *
	 * @return string
	 */
	abstract function footer();

	/**
	 * displays a login screen
	 *
	 * @string $extra_vars for login url
	 * @return string
	 */
	abstract function login_screen($extra_vars);

	/**
	 * displays a login denied message
	 *
	 * @return string
	 */
	abstract function denylogin_screen();

	/**
	 * Get footer as array to eg. set as vars for a template (from idots' head.inc.php)
	 *
	 * @return array
	 */
	protected function _get_footer()
	{
		$var = Array(
			'img_root'       => $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/'.$this->template.'/images',
			'version'        => $GLOBALS['egw_info']['server']['versions']['phpgwapi']
		);
		if($GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
		{
			$totaltime = sprintf('%4.2lf',microtime(true) - $GLOBALS['egw_info']['flags']['page_start_time']);

			$var['page_generation_time'] = '<div id="divGenTime"><br/><span>'.lang('Page was generated in %1 seconds',$totaltime);
			if ($GLOBALS['egw_info']['flags']['session_restore_time'])
			{
				$var['page_generation_time'] .= ' '.lang('(session restored in %1 seconds)',
					sprintf('%4.2lf',$GLOBALS['egw_info']['flags']['session_restore_time']));
			}
			$var['page_generation_time'] .= '</span></div>';
		}
		$var['powered_by'] = lang('Powered by').' <a href="'.$GLOBALS['egw_info']['server']['webserver_url'].'/about.php">eGroupWare</a> '.lang('version').' '.$GLOBALS['egw_info']['server']['versions']['phpgwapi'];
		$var['activate_tooltips'] = '<script src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_tooltip/wz_tooltip.js" type="text/javascript"></script>';

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
			list($app,$class,$method) = explode('.',(string)$_GET['menuaction']);
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
	 * @return array
	 */
	protected function _get_header()
	{
		// get used language code
		$lang_code = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];

		//pngfix defaults to yes
		if(!$GLOBALS['egw_info']['user']['preferences']['common']['disable_pngfix'])
		{
			$pngfix_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/pngfix.js';
			$pngfix ='<!-- This solves the Internet Explorer PNG-transparency bug, but only for IE 5.5 - 6.0 and higher -->
			<!--[if lt IE 7.0]>
			<script src="'.$pngfix_src.'" type="text/javascript">
			</script>
			<![endif]-->';
		}

		if(!$GLOBALS['egw_info']['user']['preferences']['common']['disable_slider_effects'])
		{
			$slider_effects_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/slidereffects.js';
			$slider_effects = '<script src="'.$slider_effects_src.'" type="text/javascript">
			</script>';
		}
		else
		{
			$simple_show_hide_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/simple_show_hide.js';
			$simple_show_hide = '<script src="'.$simple_show_hide_src.'" type="text/javascript">
			</script>';
		}

		if ($GLOBALS['egw_info']['flags']['app_header'])
		{
			$app = $GLOBALS['egw_info']['flags']['app_header'];
		}
		else
		{
			$app = $GLOBALS['egw_info']['flags']['currentapp'];
			$app = isset($GLOBALS['egw_info']['apps'][$app]) ? $GLOBALS['egw_info']['apps'][$app]['title'] : lang($app);
		}

		if($app!='wiki') $robots ='<meta name="robots" content="none" />';
		if (substr($GLOBALS['egw_info']['server']['favicon_file'],0,4) == 'http')
		{
			$var['favicon_file'] = $GLOBALS['egw_info']['server']['favicon_file'];
		}
		else
		{
			$var['favicon_file'] = common::image('phpgwapi',$GLOBALS['egw_info']['server']['favicon_file']?$GLOBALS['egw_info']['server']['favicon_file']:'favicon.ico');
		}

		return $this->_get_css()+array(
			#'img_icon'      	=> EGW_IMAGES_DIR . '/favicon.ico',
			'img_icon'			=> $var['favicon_file'],
			#'img_shortcut'  	=> EGW_IMAGES_DIR . '/favicon.ico',
			'img_shortcut'		=> $var['favicon_file'],
			'pngfix'        	=> $pngfix,
			'slider_effects'	=> $slider_effects,
			'simple_show_hide'	=> $simple_show_hide,
			'lang_code'			=> $lang_code,
			'charset'       	=> translation::charset(),
			'website_title' 	=> strip_tags($GLOBALS['egw_info']['server']['site_title']. ($app ? " [$app]" : '')),
			'body_tags'     	=> self::_get_body_attribs(),
			'java_script'   	=> self::_get_js(),
			'meta_robots'		=> $robots,
			'dir_code'			=> lang('language_direction_rtl') != 'rtl' ? '' : ' dir="rtl"',
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

		if($GLOBALS['egw_info']['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login').'<br />'.
				lang('Click this image on the navbar: %1','<img src="'.common::image('preferences','navbar.gif').'">');
		}
		elseif($GLOBALS['egw_info']['user']['lastpasswd_change'] < time() - (86400*30))
		{
			$api_messages = lang('it has been more then %1 days since you changed your password',30);
		}

		// This is gonna change
		if(isset($cd))
		{
			$var['messages'] = $api_messages . '<br />' . checkcode($cd);
		}

		if (substr($GLOBALS['egw_info']['server']['login_logo_file'],0,4) == 'http')
		{
			$var['logo_file'] = $GLOBALS['egw_info']['server']['login_logo_file'];
		}
		else
		{
			$var['logo_file'] = common::image('phpgwapi',$GLOBALS['egw_info']['server']['login_logo_file']?$GLOBALS['egw_info']['server']['login_logo_file']:'logo');
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
			$user_info .= html::form(html::select('tz',$tz,$user_tzs,true,' onchange="this.form.submit();"'),array(),
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
		  $current_users = '<a href="' . egw::link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">' .
		  	lang('Current users') . ': ' . $GLOBALS['egw']->session->session_count() . '</a>';
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
		$apps = egw_link::app_list('add');
		asort($apps);	// sort them alphabetic

		$options = array(lang('Add').' ...');
		foreach($apps as $app => $label)
		{
			$link = egw::link('/index.php',egw_link::add($app,$GLOBALS['egw_info']['flags']['currentapp'],$GLOBALS['egw_info']['flags']['currentid'])+
				(is_array($GLOBALS['egw_info']['flags']['quick_add']) ? $GLOBALS['egw_info']['flags']['quick_add'] : array()));
			if (($popup = egw_link::is_popup($app,'add')))
			{
				list($w,$h) = explode('x',$popup);
				$action = "window.open('$link','_blank','width=$w,height=$h,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes');";
			}
			else
			{
				$action = "location.href = '$link';";
			}
			$options[$action] = $label;
		}
		return html::select('quick_add','',$options,true,$options=' onchange="eval(this.value); this.value=0; return false;"');
	}

	/**
	 * Prepare notification signal (blinking bell)
	 *
	 * @return string
	 */
	protected static function _get_notification_bell()
	{
		return html::div(
			html::a_href(
				html::image('notifications','notificationbell',lang('notifications')),
				'javascript: egwpopup_display();'
			),
			'id="notificationbell"', // options
			'', // class
			'display: none' //style
		);
	}

	/**
	 * Get the link to an application's index page
	 *
	 * @param string $app
	 * @return string
	 */
	public function index($app)
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
	 * Prepare an array with apps used to render the navbar
	 *
	 * This is similar to the former common::navbar() method - though it returns the vars and does not place them in global scope.
	 *
	 * @return array
	 */
	protected static function _get_navbar_apps()
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
					$apps[$app]['icon']  = common::image($icon_app,Array($icon,'nonav'));
					$apps[$app]['icon_hover']  = common::image_on($icon_app,Array($icon,'nonav'),'-over');
				}
				else
				{
					$apps[$app]['icon']  = common::image_on($icon_app,Array($icon,'nonav'),'-over');
					$apps[$app]['icon_hover']  = common::image($icon_app,Array($icon,'nonav'));
				}
			}
		}
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'preferences' || $GLOBALS['egw_info']['flags']['currentapp'] == 'about')
		{
			$app = $app_title = 'eGroupWare';
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
		$apps['about']['title'] = lang('About %1',$app_title);

		$apps['about']['url']   = egw::link('/about.php','app='.$app);
		$apps['about']['icon']  = common::image('phpgwapi',Array('about','nonav'));
		$apps['about']['icon_hover']  = common::image_on('phpgwapi',Array('about','nonav'),'-over');

		$apps['logout']['title'] = lang('Logout');
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
	protected function _get_css()
	{
		$app_css = '';
		if(isset($_GET['menuaction']))
		{
			list($app,$class,$method) = explode('.',$_GET['menuaction']);
			if(is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['css'])
			{
				error_log("Deprecated functionality in $app class $class: using of public_function css, use \$GLOBALS['egw_info']['flags']['css'] or an app.css file!");
				$app_css .= $GLOBALS[$class]->css();
			}
		}
		if (isset($GLOBALS['egw_info']['flags']['css']))
		{
			$app_css .= $GLOBALS['egw_info']['flags']['css'];
		}

		// search for app specific css file
		if(@isset($GLOBALS['egw_info']['flags']['currentapp']))
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];

			$css_file = '/'.$appname.'/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/app.css';
			if (!file_exists(EGW_SERVER_ROOT.$css_file))
			{
				$css_file = '/'.$appname.'/templates/default/app.css';

				if (!file_exists(EGW_SERVER_ROOT.$css_file)) $css_file = '';
			}
			if($css_file)
			{
				$css_file = '<LINK href="'.$GLOBALS['egw_info']['server']['webserver_url'].
					$css_file.'?'.filemtime(EGW_SERVER_ROOT.$css_file).'" type=text/css rel=StyleSheet>';
			}
		}
		#_debug_array($GLOBALS['egw_info']['user']['preferences']['common']);
		$theme_css = '/phpgwapi/templates/'.$this->template.'/css/'.$GLOBALS['egw_info']['user']['preferences']['common']['theme'].'.css';
		if(!file_exists(EGW_SERVER_ROOT.$theme_css))
		{
			$theme_css = '/phpgwapi/templates/'.$this->template.'/css/'.$this->template.'.css';
		}
		$theme_css = $GLOBALS['egw_info']['server']['webserver_url'] . $theme_css .'?'.filemtime(EGW_SERVER_ROOT.$theme_css);

		$print_css = '/phpgwapi/templates/'.$this->template.'/print.css';
		if(!file_exists(EGW_SERVER_ROOT.$theme_css))
		{
			$print_css = '/phpgwapi/templates/idots/print.css';
		}
		$print_css = $GLOBALS['egw_info']['server']['webserver_url'] . $print_css .'?'.filemtime(EGW_SERVER_ROOT.$print_css);

		return array(
			'app_css'   => $app_css,
			'css_file'  => $css_file,
			'theme_css' => $theme_css,
			'print_css' => $print_css,
		);
	}

	/**
	 * Used by the template headers for including javascript in the header
	 *
	 * The method is included here to make it easier to change the js support
	 * in eGW.  One change then all templates will support it (as long as they
	 * include a call to this method).
	 *
	 * @author Dave Hall (*vaguely based* on verdilak? css inclusion code)
	 * @return string the javascript to be included
	 */
	public static function _get_js()
	{
		$java_script = '';

		// always include javascript helper functions
		$GLOBALS['egw']->js->validate_file('jsapi','jsapi');

		//viniciuscb: in Concisus this condition is inexistent, and in all
		//pages the javascript globals are inserted. Today, because
		//filescenter needs these javascript globals, this
		//include_jsbackend is a must to the javascript globals be
		//included.
		if ($GLOBALS['egw_info']['flags']['include_jsbackend'])
		{
			if (!$GLOBALS['egw_info']['flags']['nojsapi'])
			{
				$GLOBALS['egw']->js->validate_jsapi();
			}

			if(@is_object($GLOBALS['egw']->js))
			{
				$java_script .= $GLOBALS['egw']->js->get_javascript_globals();
			}
		}

		if ($GLOBALS['egw']->acl->check('run',1,'notifications') && !$GLOBALS['egw_info']['user']['preferences']['notifications']['disable_ajaxpopup'])
		{
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		}

		if ($GLOBALS['egw_info']['flags']['include_xajax'])
		{
			require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/xajax.inc.php');

			$xajax = new xajax(egw::link('/xajax.php'), 'xajax_', translation::charset());
			$xajax->waitCursorOff();
			$xajax->registerFunction("doXMLHTTP");

			$java_script .= $xajax->getJavascript($GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/js/');
		}

		/* this flag is for all javascript code that has to be put before other jscode.
		Think of conf vars etc...  (pim@lingewoud.nl) */
		if (isset($GLOBALS['egw_info']['flags']['java_script_thirst']))
		{
			$java_script .= $GLOBALS['egw_info']['flags']['java_script_thirst'] . "\n";
		}

		if(@is_object($GLOBALS['egw']->js))
		{
			$java_script .= $GLOBALS['egw']->js->get_script_links();
		}

		if(@isset($_GET['menuaction']))
		{
			list($app,$class,$method) = explode('.',$_GET['menuaction']);
			if(is_array($GLOBALS[$class]->public_functions) &&
				$GLOBALS[$class]->public_functions['java_script'])
			{
				$java_script .= $GLOBALS[$class]->java_script();
			}
		}
		if (isset($GLOBALS['egw_info']['flags']['java_script']))
		{
			$java_script .= $GLOBALS['egw_info']['flags']['java_script'] . "\n";
		}
		return $java_script;
	}

	/**
	 * Returns on(Un)Load attributes from js class
	 *
	 * @author Dave Hall - skwashd at egroupware.org
	 * @returns string body attributes
	 */
	protected static function _get_body_attribs()
	{
		if(@is_object($GLOBALS['egw']->js))
		{
			return $GLOBALS['egw']->js->get_body_attribs();
		}
		else
		{
			return '';
		}
	}
}

/**
 * Public functions to be compatible with the exiting eGW framework
 */
if (!function_exists('parse_navbar'))
{
	/**
	 * echo's out the navbar
	 *
	 * @deprecated use $GLOBALS['egw']->framework::navbar() or $GLOBALS['egw']->framework::render()
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
	 * @deprecated use $GLOBALS['egw']->framework::sidebox()
	 */
	function display_sidebox($appname,$menu_title,$file)
	{
		$GLOBALS['egw']->framework->sidebox($appname,$menu_title,$file);
	}
 }
