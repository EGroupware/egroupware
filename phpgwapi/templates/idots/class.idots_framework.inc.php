<?php
/**
 * EGroupware idots template set
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
* eGW idots template
*
* The idots_framework class draws the default idots template. It's a phplib template based template-set.
*
* Other phplib template based template-sets should extend (not copy!) this class and reimplement methods they which to change.
*/
class idots_framework extends egw_framework
{
	/**
	* HTML of the sidebox menu, get's collected here by calls to $this->sidebox
	*
	* @var string
	*/
	var $sidebox_content = '';
	/**
	* Instance of the phplib Template class for the API's template dir (EGW_TEMPLATE_DIR)
	*
	* @var Template
	*/
	var $tpl;
	/**
	* Instance of the Savant template class
	*
	* @var tplsavant2
	*/
	var $tplsav2;

	/**
	* Contains array with linked icons in the topmenu
	*
	* @var mixed
	* @access public
	*/
	var $topmenu_icon_arr = array();

	/**
	* Contains array of information for additional topmenu items added
	* by hooks
	*/
	private static $hook_items = array();

	/**
	* Constructor
	*
	* @param string $template='idots' name of the template
	* @return idots_framework
	*/
	function __construct($template='idots')
	{
		parent::__construct($template);		// call the constructor of the extended class

		// js stuff is not needed by login page and gives errors
		$GLOBALS['egw_info']['flags']['js_link_registry'] = $GLOBALS['egw_info']['flags']['currentapp'] !== 'login';

		$this->tplsav2 = new tplsavant2();
		$this->tplsav2->set_tpl_path(EGW_SERVER_ROOT.SEP.'phpgwapi'.SEP.'templates'.SEP.'idots');
	}

	/**
	* @deprecated use __construct()
	*/
	function idots_framework($template='idots')
	{
		self::__construct($template);
	}

	/**
	* Returns the html-header incl. the opening body tag
	*
	* @param array $extra=array() extra attributes passed as data-attribute to egw.js
	* @return string with html
	*/
	function header(array $extra=array())
	{
		// make sure header is output only once
		if (self::$header_done) return '';
		self::$header_done = true;

		self::_send_headers();

		// catch error echo'ed before the header, ob_start'ed in the header.inc.php
		$content = ob_get_contents();
		ob_end_clean();

		// the instanciation of the template has to be here and not in the constructor,
		// as the old Template class has problems if restored from the session (php-restore)
		if (!is_object($this->tpl)) ;
		$this->tpl = new Template(EGW_TEMPLATE_DIR,'keep');
		$this->tpl->set_file(array('_head' => 'head.tpl'));
		$this->tpl->set_block('_head','head');

		if (html::$ua_mobile)
		{
			self::$css_include_files[] = '/phpgwapi/templates/idots/mobile.css';
			// hide location bar
			egw_framework::set_onload('window.setTimeout(function(){window.scrollTo(0, 1);}, 100);');
		}

		// include regular include slidereffects.js
		if (!$GLOBALS['egw_info']['user']['preferences']['common']['disable_slider_effects'])
		{
			self::validate_file('/phpgwapi/templates/idots/js/slidereffects.js');
		}
		else
		{
			self::validate_file('/phpgwapi/templates/idots/js/simple_show_hide.js');
		}
		self::validate_file('/phpgwapi/templates/idots/js/idots.js');

		if ($GLOBALS['egw_info']['user']['preferences']['common']['click_or_onmouseover'] == 'onmouseover' && !html::$ua_mobile)
		{
			$show_menu_event = 'mouseover';
		}
		else
		{
			$show_menu_event = 'click';
		}
		$extra['slide-out'] = $this->slide_out_menus($show_menu_event);

		$this->tpl->set_var($this->_get_header($extra));

		$content .= $this->tpl->fp('out','head');

		$this->sidebox_content = '';	// need to be emptied here, as the object get's stored in the session

		return $content;
	}

	/**
	 * Return slide-out-menu config for idots.js
	 *
	 * @param string $show_menu_event='click'
	 * @return array
	 */
	protected function slide_out_menus($show_menu_event='click')
		{
		return array(
			array(
				'id' => 'menu1',
				'dir' => 'down',
				'left' => 10,
				'top' => html::$ua_mobile ? 0 : 114,
				'width' => 180,
				'height' => 200,
				'pos' => 'right',
				'bind' => array(
					'#extra_icons_show' => array('event' => $show_menu_event, 'method' => 'showMenu'),
					'#menu1close' => array('event' => $show_menu_event, 'method' => 'hide'),
				),
			),
			array(
				'id' => 'menu2',
				'dir' => 'right',
				'left' => 0,
				'top' => html::$ua_mobile ? 0 : 105,
				'width' => 100,
				'height' => 200,
				'bind' => array(
					'#menu2show' => array('event' => $show_menu_event, 'method' => 'showMenu'),
					'#menu2close' => array('event' => 'click', 'method' => 'hide'),
				),
			),
		);
	}

	/**
	* Returns the html from the body-tag til the main application area (incl. opening div tag)
	*
	* @return string with html
	*/
	function navbar()
	{
		if (self::$navbar_done) return '';

		if (!empty($_GET['nonavbar']) || $GLOBALS['egw_info']['flags']['currentapp'] == 'admin' && empty($_GET['ajax']))
		{
			if (!self::$header_done) return $this->header();
			return '';
		}
		self::$navbar_done = true;

		// the navbar
		if (!is_object($this->tpl)) $this->tpl = new Template(EGW_TEMPLATE_DIR,'keep');
		$this->tpl->set_file(array('navbar' => 'navbar.tpl'));

		$this->tpl->set_block('navbar','extra_blocks_header','extra_block_header');
		$this->tpl->set_block('navbar','extra_block_row','extra_block_row');
		$this->tpl->set_block('navbar','extra_block_row_raw','extra_block_row_raw');
		$this->tpl->set_block('navbar','extra_block_row_no_link','extra_block_row_no_link');
		$this->tpl->set_block('navbar','extra_block_spacer','extra_block_spacer');
		$this->tpl->set_block('navbar','extra_blocks_footer','extra_blocks_footer');
		$this->tpl->set_block('navbar','sidebox_hide_header','sidebox_hide_header');
		$this->tpl->set_block('navbar','sidebox_hide_footer','sidebox_hide_footer');
		$this->tpl->set_block('navbar','appbox','appbox');
		$this->tpl->set_block('navbar','navbar_footer','navbar_footer');

		$this->tpl->set_block('navbar','upper_tab_block','upper_tabs');
		$this->tpl->set_block('navbar','app_icon_block','app_icons');
		$this->tpl->set_block('navbar','app_title_block','app_titles');
		$this->tpl->set_block('navbar','app_extra_block','app_extra_icons');
		$this->tpl->set_block('navbar','app_extra_icons_div');
		$this->tpl->set_block('navbar','app_extra_icons_icon');

		if (html::$ua_mobile)	// replace whole navbar with just the extra apps icon
		{
			$this->tpl->set_block('navbar','navbar','mobil_not_needed');
			$this->tpl->set_block('app_extra_icons_icon','extra_icons_show');
			$this->tpl->set_var('mobil_not_needed',$this->tpl->get_var('extra_icons_show'));
		}
		$this->tpl->set_block('navbar','navbar_header','navbar_header');

		$apps = $this->_get_navbar_apps();
		$vars = $this->_get_navbar($apps);

		// add link registry to non-popup windows
		if (!isset($GLOBALS['egw_info']['flags']['js_link_registry']))
		{
			self::validate_file('/phpgwapi/config.php');
			self::validate_file('/phpgwapi/images.php',array('template' => $GLOBALS['egw_info']['user']['preferences']['common']['template_set']));
			$content .= '<script type="text/javascript">
egw_LAB.wait(function() {egw.set_preferences('.json_encode($GLOBALS['egw_info']['user']['preferences']['common']).', "common");
egw.set_user('.$GLOBALS['egw']->accounts->json($GLOBALS['egw_info']['user']['account_id']).');});
</script>'."\n";
		}
		if($GLOBALS['egw_info']['user']['preferences']['common']['show_general_menu'] != 'sidebox' && !html::$ua_mobile)
		{
			$content .= $this->topmenu($vars,$apps);
			$vars['current_users'] = $vars['quick_add'] = $vars['user_info']='';
		}

		$this->tpl->set_var($vars);
		$content .= $this->tpl->fp('out','navbar_header');

		// general (app-unspecific) sidebox menu, instead of topmenu
		if($GLOBALS['egw_info']['user']['preferences']['common']['show_general_menu'] == 'sidebox')
		{
			$menu_title = lang('General Menu');

			$this->topmenu($vars,$apps);
			$file = $this->tplsav2->menuitems;

			$this->sidebox('',$menu_title,$file);
		}

		// allow other apps to hook into sidebox menu of an app, hook-name: sidebox_$app
		$GLOBALS['egw']->hooks->process('sidebox_'.$GLOBALS['egw_info']['flags']['currentapp'],
			array($GLOBALS['egw_info']['flags']['currentapp']),true);	// true = call independent of app-permissions
		// calling the old hook
		$GLOBALS['egw']->hooks->single('sidebox_menu',$GLOBALS['egw_info']['flags']['currentapp']);

		// allow other apps to hook into sidebox menu of every app: sidebox_all
		$GLOBALS['egw']->hooks->process('sidebox_all',array($GLOBALS['egw_info']['flags']['currentapp']),true);

		if($this->sidebox_content)
		{
			if($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox'] || html::$ua_mobile)
			{
				$this->tpl->set_var('lang_show_menu',lang('show menu'));
				$content .= $this->tpl->parse('out','sidebox_hide_header');

				$content .= $this->sidebox_content;	// content from calls to $this->sidebox

				$content .= $this->tpl->parse('out','sidebox_hide_footer');

				$var['sideboxcolstart'] = '';

				$this->tpl->set_var($var);
				$content .= $this->tpl->parse('out','appbox');
				$var['remove_padding'] = 'style="padding-left:0px;"';
				$var['sideboxcolend'] = '';
			}
			else
			{
				$prefs = array();

				if (isset($GLOBALS['egw_info']['user']['preferences'][$GLOBALS['egw_info']['flags']['currentapp']]['idotssideboxwidth']))
				{
					$sideboxwidth = $GLOBALS['egw_info']['user']['preferences'][$GLOBALS['egw_info']['flags']['currentapp']]['idotssideboxwidth'];
				}
				if((int)$sideboxwidth < 1)
				{
					$sideboxwidth = 203;
				}

				$var['menu_link'] = '';

				$var['sideboxcolstart'] = '<td id="tdSidebox" valign="top"><div id="thesideboxcolumn" style="width:'.$sideboxwidth.'px">';
				$var['sideboxcolstart'] .= '<div id="sideresize"></div>';
				$var['remove_padding'] = '';
				$this->tpl->set_var($var);
				$content .= $this->tpl->parse('out','appbox');

				$content .= $this->sidebox_content;

				$var['sideboxcolend'] = '</div></td>';

				$this->tplsav2->assign('sideboxwidth', $sideboxwidth);
			}
		}
		else
		{
			$var['sideboxcolend']='';
		}

		$this->tpl->set_var($var);
		$content .= $this->tpl->parse('out','navbar_footer');

		// depricated (!) application header, if not disabled
		// ToDo: check if it can be removed
		if(!@$GLOBALS['egw_info']['flags']['noappheader'] && @isset($_GET['menuaction']))
		{
			list($app,$class,$method) = explode('.',$_GET['menuaction']);
			if(is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['header'])
			{
				ob_start();
				$GLOBALS[$class]->header();
				$content .= ob_get_contents();
				ob_end_clean();
			}
		}

		// hook after navbar
		$content .= $this->_get_after_navbar();

		// make sure header is output (not explicitly calling header, allows to put validate calls eg. in sidebox)
		if (!self::$header_done) $content = $this->header() . $content;

		return $content;
	}

	/**
	 * Return true if we are rendering the top-level EGroupware window
	 *
	 * A top-level EGroupware window has a navbar: eg. no popup and for a framed template (jdots) only frameset itself
	 *
	 * @return boolean $consider_navbar_not_yet_called_as_true=true
	 * @return boolean
	 */
	public function isTop($consider_navbar_not_yet_called_as_true=true)
	{
		return self::$navbar_done || $consider_navbar_not_yet_called_as_true ||
			isset($GLOBALS['egw_info']['flags']['nonavbar']) && !$GLOBALS['egw_info']['flags']['nonavbar'];
	}

	/**
	* displays a login screen
	*
	* @param string $extra_vars for login url
	*/
	function login_screen($extra_vars)
	{
		$tmpl = new Template($GLOBALS['egw_info']['server']['template_dir']);

		$tmpl->set_file(array('login_form' => 'login.tpl'));

		$tmpl->set_var('lang_message',$GLOBALS['loginscreenmessage']);

		$last_loginid = $_COOKIE['last_loginid'];

		if($GLOBALS['egw_info']['server']['show_domain_selectbox'])
		{
			foreach($GLOBALS['egw_domain'] as $domain => $data)
			{
				$domains[$domain] = $domain;
			}
			$tmpl->set_var(array(
				'lang_domain'   => lang('domain'),
				'select_domain' => html::select('logindomain',$_COOKIE['last_domain'],$domains,true,'tabindex="2"'),
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

				if($_COOKIE['last_domain'] != $default_domain && !empty($_COOKIE['last_domain']))
				{
					$last_loginid .= '@' . $_COOKIE['last_domain'];
				}
			}
		}

		$config_reg = config::read('registration');

		if($config_reg['enable_registration'])
		{
			if ($config_reg['register_link'])
			{
				$reg_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','lang_code='.$_GET['lang']). '">'.lang('Not a user yet? Register now').'</a><br/>';
			}
			if ($config_reg['lostpassword_link'])
			{
				$lostpw_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','menuaction=registration.registration_ui.lost_password&lang_code='.$_GET['lang']). '">'.lang('Lost password').'</a><br/>';
			}
			if ($config_reg['lostid_link'])
			{
				$lostid_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','menuaction=registration.registration_ui.lost_username&lang_code='.$_GET['lang']). '">'.lang('Lost Login Id').'</a><br/>';
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
		$tmpl->set_var('version',$GLOBALS['egw_info']['server']['versions']['phpgwapi']);
		$tmpl->set_var('cd',check_logoutcode($_GET['cd']));
		$tmpl->set_var('cookie',$last_loginid);

		$tmpl->set_var('lang_username',lang('username'));
		$tmpl->set_var('lang_password',lang('password'));
		$tmpl->set_var('lang_login',lang('login'));

		$tmpl->set_var('website_title', $GLOBALS['egw_info']['server']['site_title']);
		$tmpl->set_var('template_set',$this->template);

		if (substr($GLOBALS['egw_info']['server']['login_logo_file'], 0, 4) == 'http' ||
			strpos($GLOBALS['egw_info']['server']['login_logo_file'], '//') !== false)
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
		$tmpl->set_var($var);

		/* language section if activated in site config */
		if (@$GLOBALS['egw_info']['server']['login_show_language_selection'])
		{
			$tmpl->set_var(array(
				'lang_language' => lang('Language'),
				'select_language' => html::select('lang',$GLOBALS['egw_info']['user']['preferences']['common']['lang'],
				translation::get_installed_langs(),true,'tabindex="1"'),
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
			),true,'tabindex="3"'));
		}
		else
		{
			/* trick to make remember_me section disapear */
			$tmpl->set_block('login_form','remember_me_selection');
			$tmpl->set_var('remember_me_selection','');
		}
		$tmpl->set_var('autocomplete', ($GLOBALS['egw_info']['server']['autocomplete_login'] ? 'autocomplete="off"' : ''));

		$GLOBALS['egw']->js->set_onload('document.login_form.login.focus();');

		$this->render($tmpl->fp('loginout','login_form'),false,false);
	}

	/**
	* displays a login denied message
	*/
	function denylogin_screen()
	{
		$tmpl = new Template($GLOBALS['egw_info']['server']['template_dir']);

		$tmpl->set_file(array(
			'login_form' => 'login_denylogin.tpl'
		));

		$tmpl->set_var(array(
			'template_set' => 'default',
			'deny_msg'     => lang('Oops! You caught us in the middle of system maintainance.').
			'<br />'.lang('Please, check back with us shortly.'),
		));
		$this->render($tmpl->fp('loginout','login_form'),false,false);
	}

	/**
	* Get navbar as array to eg. set as vars for a template (from idots' navbar.inc.php)
	*
	* Reimplemented so set the vars for the navbar itself (uses $this->tpl and the blocks a and b)
	*
	* @internal PHP5 protected
	* @param array $apps navbar apps from _get_navbar_apps
	* @return array
	*/
	function _get_navbar($apps)
	{
		$var = parent::_get_navbar($apps);

		if($GLOBALS['egw_info']['user']['userid'] == 'anonymous')
		{
			$config_reg = config::read('registration');

			$this->tpl->set_var(array(
				'url'   => $GLOBALS['egw']->link('/logout.php'),
				'title' => lang('Login'),
			));
			$this->tpl->fp('upper_tabs','upper_tab_block');
			if ($config_reg[enable_registration]=='True' && $config_reg[register_link]=='True')
			{
				$this->tpl->set_var(array(
					'url'   => $GLOBALS['egw']->link('/registration/index.php'),
					'title' => lang('Register'),
				));
			}
		}
		else
		{
			$this->tpl->set_var('upper_tabs','');
		}

		if (html::$ua_mobile)
		{
			$max_icons = 0;
			$this->tpl->set_var('app_icons','');
		}
		elseif (!($max_icons=$GLOBALS['egw_info']['user']['preferences']['common']['max_icons']))
		{
			$max_icons = 30;
		}

		if($GLOBALS['egw_info']['user']['preferences']['common']['start_and_logout_icons'] == 'no' && !html::$ua_mobile)
		{
			$tdwidth = 100 / $max_icons;
		}
		else
		{
			$tdwidth = 100 / ($max_icons+1);	// +1 for logout
		}
		$this->tpl->set_var('tdwidth',round($tdwidth));

		// not shown in the navbar
		foreach($apps as $app => $app_data)
		{
			if ($app != 'preferences' && $app != 'about' && $app != 'logout' && $app != 'manual' &&
				($app != 'home' || $GLOBALS['egw_info']['user']['preferences']['common']['start_and_logout_icons'] != 'no') ||
				html::$ua_mobile && in_array($app,array('preferences','logout','home')))
			{
				$this->tpl->set_var($app_data);

				if($i < $max_icons)
				{
					$this->tpl->set_var($app_data);
					if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format'] != 'text')
					{
						$this->tpl->fp('app_icons','app_icon_block',true);
					}
					if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format'] != 'icons')
					{
						$this->tpl->fp('app_titles','app_title_block',true);
					}
				}
				else // generate extra icon layer shows icons and/or text
				{
					$this->tpl->fp('app_extra_icons','app_extra_block',true);
				}
				$i++;
			}
		}
		// settings for the extra icons dif
		if ($i <= $max_icons)	// no extra icon div
		{
			$this->tpl->set_var('app_extra_icons_div','');
			$this->tpl->set_var('app_extra_icons_icon','');
		}
		else
		{
			$var['lang_close'] = lang('Close');
			$var['lang_show_more_apps'] = lang('show_more_apps');
		}
		if ($GLOBALS['egw_info']['user']['preferences']['common']['start_and_logout_icons'] != 'no' &&
			$GLOBALS['egw_info']['user']['userid'] != 'anonymous')
		{
			$this->tpl->set_var($apps['logout']);
			if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format'] != 'text')
			{
				$this->tpl->fp('app_icons','app_icon_block',true);
			}
			if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format'] != 'icons')
			{
				$this->tpl->fp('app_titles','app_title_block',true);
			}
		}

		if($GLOBALS['egw_info']['user']['preferences']['common']['navbar_format'] == 'icons')
		{
			$var['app_titles'] = '<td colspan="'.$max_icons.'">&nbsp;</td>';
		}
		return $var;
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
		$_item['link'] = $_item['url'] = htmlspecialchars($app_data['url']);
		$_item['target'] = $_item['urlextra'] = $app_data['target'];
		$_item['text'] = $_item['label'] = $alt_label ? $alt_label : $app_data['title'];
		$this->tplsav2->menuitems[] = $_item;
		$this->tplsav2->icon_or_star = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/'.$this->template.'/images'.'/orange-ball.png';
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
		$this->tplsav2->menuinfoitems[] = $content;
	}

	/**
	* Display the string with html of the topmenu if its enabled
	*
	* @param array $vars
	* @param array $apps
	* @return string
	*/
	function topmenu(array $vars,array $apps)
	{
		$this->tplsav2->menuitems = array();
		$this->tplsav2->menuinfoitems = array();

		parent::topmenu($vars,$apps);

		$this->tplsav2->assign('info_icons',$this->topmenu_icon_arr);

		return $this->tplsav2->fetch('topmenu.tpl.php');
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
	function topmenu_info_icon($id,$icon_src,$iconlink,$blink=false,$tooltip=null)
	{
		$icon_arr['id'] = $id;
		$icon_arr['blink'] = $blink;
		$icon_arr['link'] = $iconlink;
		$icon_arr['image'] = $icon_src;

		if(!is_null($tooltip))
		{
			$icon_arr['tooltip'] = html::tooltip($tooltip);
		}

		$this->topmenu_icon_arr[]=$icon_arr;
	}

	/**
	* Returns the html from the closing div of the main application area to the closing html-tag
	*
	* @return string html or null if no footer needed/wanted
	*/
	function footer()
	{
		static $footer_done;
		if ($footer_done++) return;	// prevent multiple footers, not sure we still need this (RalfBecker)

		if (!isset($GLOBALS['egw_info']['flags']['nofooter']) || !$GLOBALS['egw_info']['flags']['nofooter'])
		{
			// get the (depricated) application footer
			$content = $this->_get_app_footer();

			// run the hook navbar_end
			// ToDo: change to return the content
			ob_start();
			$GLOBALS['egw']->hooks->process('navbar_end');
			$content .= ob_get_contents();
			ob_end_clean();

			// eg. javascript, which need to be at the end of the page
			if ($GLOBALS['egw_info']['flags']['need_footer'])
			{
				$content .= $GLOBALS['egw_info']['flags']['need_footer'];
			}

			// do the template sets footer, former parse_navbar_end function
			// this closes the application area AND renders the closing body- and html-tag
			if (self::$navbar_done)
			{
				if (!is_a($this->tpl,'Template')) $this->tpl = new Template(EGW_TEMPLATE_DIR);
				$this->tpl->set_file(array('footer' => 'footer.tpl'));
				$this->tpl->set_var($this->_get_footer());
				$content .= $this->tpl->fp('out','footer');
			}
			elseif (!isset($GLOBALS['egw_info']['flags']['noheader']) || !$GLOBALS['egw_info']['flags']['noheader'] ||
				self::$header_done || !empty($_GET['nonavbar']) ||
				$GLOBALS['egw_info']['flags']['currentapp'] == 'admin' && empty($_GET['ajax']))
			{
				$content .= "</body>\n</html>\n";	// close body and html tag, eg. for popups
			}
			return $content;
		}
	}

	/**
	* Parses one sidebox menu and add's the html to $this->sidebox_content for later use by $this->navbar
	*
	* @param string $appname
	* @param string $menu_title
	* @param array $file
	* @param string $type=null 'admin', 'preferences', 'favorites', ...
	*/
	function sidebox($appname,$menu_title,$file,$type=null)
	{
		if((!$appname || ($appname==$GLOBALS['egw_info']['flags']['currentapp'] && $file)) && is_object($this->tpl))
		{
			// fix app admin menus to use admin.admin_ui.index loader
			if (($type == 'admin' || $menu_title == lang('Admin')) && $appname != 'admin')
			{
				$file = preg_replace("/^(.*)menuaction=([^&]+)(.*)$/",
					'$1menuaction=admin.admin_ui.index&load=$2$3&ajax=true', $file);
			}
			$this->tpl->set_var('lang_title',$menu_title);
			$this->sidebox_content .= $this->tpl->fp('out','extra_blocks_header');

			foreach($file as $text => $url)
			{
				$this->sidebox_content .= $this->_sidebox_menu_item($url,$text);
			}
			$this->sidebox_content .= $this->tpl->parse('out','extra_blocks_footer');
		}
	}

	/**
	* Return a sidebox menu item
	*
	* @internal PHP5 protected
	* @param string $item_link
	* @param string $item_text
	* @return string
	*/
	function _sidebox_menu_item($item_link='',$item_text='')
	{
		if($item_text === '_NewLine_' || $item_link === '_NewLine_')
		{
			return $this->tpl->parse('out','extra_block_spacer');
		}
		if (strtolower($item_text) == 'grant access' && $GLOBALS['egw_info']['server']['deny_user_grants_access'])
		{
			return;
		}

		$var['icon_or_star']='<img class="sideboxstar" src="'.$GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/'.$this->template.'/images'.'/orange-ball.png" width="9" height="9" alt="ball"/>';
		$var['target'] = '';
		if(is_array($item_link))
		{
			if(isset($item_link['icon']))
			{
				$app = isset($item_link['app']) ? $item_link['app'] : $GLOBALS['egw_info']['flags']['currentapp'];
				$var['icon_or_star'] = $item_link['icon'] ? '<img style="margin:0px 2px 0px 2px; height: 16px;" src="'.common::image($app,$item_link['icon']).'"/>' : False;
			}
			$var['lang_item'] = isset($item_link['no_lang']) && $item_link['no_lang'] ? $item_link['text'] : lang($item_link['text']);
			$var['item_link'] = $item_link['link'];
			if ($item_link['target'])
			{
				if (strpos($item_link['target'], 'target=') !== false)
				{
					$var['target'] = $item_link['target'];
				}
				else
				{
					$var['target'] = ' target="' . $item_link['target'] . '"';
				}
			}
		}
		else
		{
			$var['lang_item'] = lang($item_text);
			$var['item_link'] = $item_link;
		}
		$this->tpl->set_var($var);

		$block = 'extra_block_row';
		if ($var['item_link'] === False)
		{
			$block .= $var['icon_or_star'] === False ? '_raw' : '_no_link';
		}
		return $this->tpl->parse('out',$block);
	}

	/**
	 * Return javascript (eg. for onClick) to open manual with given url
	 *
	 * @param string $url
	 * @return string
	 */
	function open_manual_js($url)
	{
		return "egw_openWindowCentered2('$url','manual',800,600,'yes')";
	}
}
