<?php
/**
 * EGroupware API - Render (deny-)login screen
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

namespace EGroupware\Api\Framework;

use EGroupware\Api;

/**
 * Render (deny-)login screen
 */
class Login
{
	/**
	 * Framework object
	 *
	 * @var Api\Framework
	 */
	protected $framework;

	/**
	 * Constructor
	 *
	 * @param Api\Framework $framework
	 */
	function __construct(Api\Framework $framework)
	{
		$this->framework = $framework;
	}

	/**
	 * Displays the login screen
	 *
	 * @param string $extra_vars for login url
	 * @param string $change_passwd =null string with message to render input fields for password change
	*/
	function screen($extra_vars, $change_passwd=null)
	{
		// set a more limiting CSP for our login page
		Api\Header\ContentSecurityPolicy::add('frame-src', 'none');
		Api\Header\ContentSecurityPolicy::add('media-src', 'none');

		//error_log(__METHOD__."() this->template=$this->framework->template, this->template_dir=$this->framework->template_dir, get_class(this)=".get_class($this));
		try {
			$tmpl = new Template(EGW_SERVER_ROOT.$this->framework->template_dir);
			$tmpl->set_file(array('login_form' => Api\Header\UserAgent::mobile()?'login_mobile.tpl':'login.tpl'));
		}
		catch(Api\Exception\WrongParameter $e) {
			unset($e);
			$tmpl = new Template(EGW_SERVER_ROOT.'/api/templates/default');
			$tmpl->set_file(array('login_form' => Api\Header\UserAgent::mobile()?'login_mobile.tpl':'login.tpl'));
		}
		$tmpl->set_var('lang_message', in_array(strip_tags($GLOBALS['loginscreenmessage']), ['', 'EGroupware']) ?
			lang('Your Collaboration Platform') : $GLOBALS['loginscreenmessage']);

		// did admin disable 2FA
		if ($GLOBALS['egw_info']['server']['2fa_required'] === 'disabled')
		{
			$tmpl->set_block('login_form','2fa_section');
			$tmpl->set_var('2fa_section', '');
		}
		else
		{
			$tmpl->set_var('lang_2fa',lang('2-Factor-Authentication'));
			$tmpl->set_var('lang_2fa_help', htmlspecialchars(
				lang('If you use "2-Factor-Authentication", please enter the code here.')));

			if (in_array($GLOBALS['egw_info']['server']['2fa_required'], ['required', 'strict']) ||
				$_GET['cd'] == Api\Session::CD_SECOND_FACTOR_REQUIRED)
			{
				$tmpl->set_var('2fa_class', 'et2_required');
			}
		}

		// check if we need some discovery (select login options eg. a SAML IdP), hide it if not
		$discovery = '';
		foreach(Api\Hooks::process('login_discovery', [], true) as $app => $data)
		{
			if (!empty($data)) $discovery .= $data;
		}
		if (!empty($discovery))
		{
			$tmpl->set_var('discovery', $discovery);
		}
		else
		{
			$tmpl->set_block('login_form','discovery_block');
			$tmpl->set_var('discovery_block', '');
		}

		// hide change-password fields, if not requested
		if (!$change_passwd)
		{
			$tmpl->set_block('login_form','change_password');
			$tmpl->set_var('change_password', '');
			$tmpl->set_var('lang_password',lang('password'));

			// display login-message depending on $_GET[cd] and what's in database/header for "login_message"
			$cd_msg = self::check_logoutcode($_GET['cd']);
			if (!empty($GLOBALS['egw_info']['server']['login_message']))
			{
				$cd_msg = $GLOBALS['egw_info']['server']['login_message'].
					// only add non-empty and not "successful loged out" message below
					(!empty($cd_msg) && $cd_msg != '&nbsp;' && $_GET['cd'] != 1 ? "\n\n".$cd_msg : '');
			}
			$tmpl->set_var('cd', $cd_msg);
			$tmpl->set_var('cd_class', isset($_GET['cd']) && $_GET['cd'] != 1 ||
				!empty($GLOBALS['egw_info']['server']['login_message']) ? 'error_message' : '');

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
			$tmpl->set_var('cd_class', 'error_message');
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
				'select_domain' => Api\Html::select('logindomain',$last_domain,$domains,true,'tabindex="2"',0,false),
			));
		}
		else
		{
			/* trick to make domain section disapear */
			$tmpl->set_block('login_form','domain_selection');
			$tmpl->set_var('domain_selection',$GLOBALS['egw_info']['user']['domain'] ?
			Api\Html::input_hidden('logindomain',$GLOBALS['egw_info']['user']['domain']) : '');

			if($last_loginid !== '')
			{
				reset($GLOBALS['egw_domain']);
				$default_domain = key($GLOBALS['egw_domain']);

				if(!empty ($last_domain) && $last_domain != $default_domain)
				{
					$last_loginid .= '@' . $last_domain;
				}
			}
		}

		$config_reg = Api\Config::read('registration');

		if($config_reg['enable_registration'])
		{
			$lang = $_GET['lang'] ? $_GET['lang'] : $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			if ($config_reg['register_link'])
			{
				$reg_link='<a  class="signup" href="'. $this->framework->link('/registration/index.php','lang_code='.$lang). '&cd=no">'.lang('Sign up').'</a>';
			}
			if ($config_reg['lostpassword_link'])
			{
				$lostpw_link='<a href="'. $this->framework->link('/registration/index.php','menuaction=registration.registration_ui.lost_password&lang_code='.$lang). '&cd=no">'.lang('Lost password').'</a>';
			}
			if ($config_reg['lostid_link'])
			{
				$lostid_link='<a href="'. $this->framework->link('/registration/index.php','menuaction=registration.registration_ui.lost_username&lang_code='.$lang). '&cd=no">'.lang('Lost Login Id').'</a>';
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

		$tmpl->set_var('login_url', $GLOBALS['egw_info']['server']['webserver_url'] . '/login.php?' .
			substr($extra_vars,$extra_vars[0] === '&' || [0] === '?' ? 1 : 0));
		$tmpl->set_var('version', $GLOBALS['egw_info']['server']['versions']['phpgwapi']);
		$tmpl->set_var('login', htmlspecialchars($last_loginid));

		$tmpl->set_var('lang_username',lang('username'));
		$tmpl->set_var('lang_login',lang('login'));

		$tmpl->set_var('website_title', $GLOBALS['egw_info']['server']['site_title']);
		$tmpl->set_var('template_set',$this->framework->template);

		$var['background_file'] = self::pick_login_background($GLOBALS['egw_info']['server']['login_background_file']);
		// add "stockLoginBackground" class to div#loginMainDiv to fix positions for stock background
		$var['stock_background_class'] = strpos($var['background_file'], '/api/templates/default/images/login_background') !== false ?
			'stockLoginBackground' : '';

		$var['logo_file'] = \EGroupware\Api\Framework::get_login_logo_or_bg_url('login_logo_file', 'login_logo');

		$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.egroupware.org';
		if (substr($var['logo_url'],0,4) != 'http')
		{
			$var['logo_url'] = 'http://'.$var['logo_url'];
		}
		$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.egroupware.org';
		$tmpl->set_var($var);

		/* language section if activated in site Config */
		if (@$GLOBALS['egw_info']['server']['login_show_language_selection'])
		{
			$tmpl->set_var(array(
				'lang_language' => lang('Language'),
				'select_language' => Api\Html::select('lang',$GLOBALS['egw_info']['user']['preferences']['common']['lang'],
				Api\Translation::get_installed_langs(),true,'tabindex="1"',0,false),
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

		if ($GLOBALS['egw_info']['server']['remember_me_token'] === 'always' ||
			($GLOBALS['egw_info']['server']['2fa_required'] !== 'disabled' &&
				$GLOBALS['egw_info']['server']['remember_me_token'] !== 'disabled'))
		{
			$tmpl->set_block('login_form','remember_me_selection');
			$help = htmlspecialchars(lang('Do NOT use on public computers!'));
			$tmpl->set_var('lang_remember_me_help', $help);
			if ($GLOBALS['egw_info']['server']['remember_me_lifetime'] === 'user')
			{
				$tmpl->set_var('lang_remember_me', '');
				$tmpl->set_var('select_remember_me',Api\Html::select('remember_me', '', array(
					'' => lang('Do not remember me'),
					'P1W'=> lang('Remember me for %1', lang('1 Week')),
					'P2W'=> lang('Remember me for %1', lang('2 Weeks')),
					'P1M' => lang('Remember me for %1', lang('1 Month')),
					'P3M' => lang('Remember me for %1', lang('3 Month')),
					'P1Y' => lang('Remember me for %1', lang('1 Year')),
				), true, 'tabindex="3" title="'.$help.'"', 0, false));
			}
			else
			{
				$tmpl->set_var('lang_remember_me',lang('Remember me'));
				$tmpl->set_var('select_remember_me',
					Api\Html::checkbox('remember_me', false, 'True', ' id="remember_me" tabindex="3" title="'.$help.'"'));
			}
		}
		else
		{
			/* trick to make remember_me section disapear */
			$tmpl->set_block('login_form','remember_me_selection');
			$tmpl->set_var('remember_me_selection','');
		}
		$tmpl->set_var('autocomplete', ($GLOBALS['egw_info']['server']['autocomplete_login'] ? 'autocomplete="off"' : ''));

		$tmpl->set_var('footer_apps', self::get_apps_node());
		if (Api\Header\UserAgent::type() == 'msie' && Api\Header\UserAgent::version() < 12)
		{
			$tmpl->set_var('cd', lang('Browser %1 %2 is not recommended. You may experience issues and not working features. Please use the latest version of Chrome, Firefox or Edge. Thank You!',Api\Header\UserAgent::type(), Api\Header\UserAgent::version()));
			$tmpl->set_var('cd_class', 'warning_message');
		}
		// load jquery for login screen too
		Api\Framework::includeJS('jquery', 'jquery');

		// call hook to allow apps to modify login page, eg. for multifactor auth
		Api\Hooks::process([
			'location' => 'login_page',
			'tmpl' => $tmpl,
		], [], true);

		$this->framework->render($tmpl->fp('loginout','login_form'),false,false);
	}

	/**
	 * Build dom nodes for login applications footer banner
	 * @return string
	 */
	static function get_apps_node()
	{
		if (true||!($json = Api\Cache::getCache(Api\Cache::TREE, __CLASS__, 'egw_login_json')))
		{
			$json = file_get_contents('pixelegg/login/login.json');
			// Cache the json object for a day
			Api\Cache::setCache(Api\Cache::TREE, __CLASS__, 'egw_login_json', $json, 86400);
		}
		$data = json_decode($json, true);
		$nodes = '';
		$counter = 1;
		if (is_array($data))
		{
			foreach ($data['apps'] as $id => $app)
			{
				$icon = strpos($app['icon'], "/") === 0 ? $GLOBALS['egw_info']['server']['webserver_url'].$app['icon'] : $app['icon'];
				$icon2 = strpos($app['icon2'], "/") === 0 ? $GLOBALS['egw_info']['server']['webserver_url'].$app['icon2'] : $app['icon2'];
				$icon3 = strpos($app['icon3'], "/") === 0 ? $GLOBALS['egw_info']['server']['webserver_url'].$app['icon3'] : $app['icon3'];
				$title = lang($app['title']);
				$nodes .= '<div class="app" style="animation:login-apps '.$counter*0.1.'s ease-out" data-id="'.$id.'">'
					.'<a href="'.htmlspecialchars($app['url']).'" title="'.htmlspecialchars($title).'" class="" target="blank">'
					.'<img class="icon" src="'.htmlspecialchars($icon).'"/></a>'
					.'<div class="tooltip">'
					.'<div class="content">'
					.'<h3><a href="'.htmlspecialchars($app['url']).'" title="'.htmlspecialchars($title).'" target="blank">'
					.htmlspecialchars($title).'</a></h3>'
					.'<img class="icon-bg" src="'.htmlspecialchars($icon).'"/>'
					.'<img class="icon-bg icon2-bg" src="'.htmlspecialchars($icon2).'"/>'
					.'<img class="icon-bg icon3-bg" src="'.htmlspecialchars($icon3).'"/>'
					.'<p>'.htmlspecialchars($app['desc']).'</p><div class="arrow"></div>'
					.'</div></div></div>';
				$counter++;
			}
		}
		return $nodes;
	}

	/**
	 * Function to pick login background from given values. It picks them randomly
	 * if there's more than one image in the list.
	 *
	 * @param array|string $backgrounds array of background urls or an url as string
	 *
	 * @return string returns full url of background image
	 */
	static function pick_login_background($backgrounds)
	{
		if (is_array($backgrounds))
		{
			$chosen = $backgrounds[rand(0, count($backgrounds)-1)];
		}
		else
		{
			$chosen = $backgrounds;
		}

		if (substr($chosen, 0, 4) == 'http' ||
			$chosen[0] == '/')
		{
			return $chosen;
		}
		else
		{
			return Api\Image::find('api', $chosen ? $chosen : 'login_background', '', true);	// true: add cachebuster
		}
	}

	/**
	* displays a login denied message
	*/
	function deny_screen()
	{
		try {
			$tmpl = new Template(EGW_SERVER_ROOT.$this->framework->template_dir);
			$tmpl->set_file(array('login_form' => 'login_denylogin.tpl'));
		}
		catch(Api\Exception\WrongParameter $e) {
			unset($e);
			$tmpl = new Template(EGW_SERVER_ROOT.'/api/templates/default');
			$tmpl->set_file(array('login_form' => 'login_denylogin.tpl'));
		}

		$tmpl->set_var(array(
			'template_set' => 'default',
			'deny_msg'     => lang('Oops! You caught us in the middle of system maintainance.').
			'<br />'.lang('Please, check back with us shortly.'),
		));

		// load jquery for deny-login screen too
		Api\Framework::includeJS('jquery', 'jquery');

		$this->framework->render($tmpl->fp('loginout','login_form'),false,false);
	}

	/**
	 * Return verbose message for nummeric logout code ($_GET[cd])
	 *
	 * @param int|string $code
	 * @return string
	 */
	static function check_logoutcode($code)
	{
		switch($code)
		{
			case 1:
				return lang('You have been successfully logged out');
			case 2:
				return lang('Sorry, your login has expired');
			case 4:
				return lang('Cookies are required to login to this site');
			case Api\Session::CD_BAD_LOGIN_OR_PASSWORD:
				return lang('Bad login or password');
			case Api\Session::CD_FORCE_PASSWORD_CHANGE:
				return lang('You must change your password!');
			case Api\Session::CD_ACCOUNT_EXPIRED:
				return lang('Account is expired');
			case Api\Session::CD_BLOCKED:
				return lang('Blocked, too many attempts');
			case Api\Session::CD_SECOND_FACTOR_REQUIRED:
				return lang('2-Factor-Authentication required');
			case 10:
				$GLOBALS['egw']->session->egw_setcookie('sessionid');
				$GLOBALS['egw']->session->egw_setcookie('kp3');
				$GLOBALS['egw']->session->egw_setcookie('domain');
				return lang('Your session timed out, please log in again');
			case 100:
				return lang('Login rejected by EPL firewall, please contact your administrator');
			default:
				if (!$code)
				{
					return '&nbsp;';
				}
				return htmlspecialchars($code);
		}
	}
}
