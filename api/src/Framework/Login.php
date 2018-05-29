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
		Api\Header\ContentSecurityPolicy::add('frame-src', array());	// array() no external frame-sources

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

		$tmpl->set_var('lang_message',$GLOBALS['loginscreenmessage']);

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
				!empty($GLOBALS['egw_info']['server']['login_message']) ? 'error' : '');

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
				list($default_domain) = each($GLOBALS['egw_domain']);

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
				$reg_link='&nbsp;<a href="'. $this->framework->link('/registration/index.php','lang_code='.$lang). '">'.lang('Sigup').'</a>';
			}
			if ($config_reg['lostpassword_link'])
			{
				$lostpw_link='&nbsp;<a href="'. $this->framework->link('/registration/index.php','menuaction=registration.registration_ui.lost_password&lang_code='.$lang). '">'.lang('Lost password').'</a>';
			}
			if ($config_reg['lostid_link'])
			{
				$lostid_link='&nbsp;<a href="'. $this->framework->link('/registration/index.php','menuaction=registration.registration_ui.lost_username&lang_code='.$lang). '">'.lang('Lost Login Id').'</a>';
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
		$tmpl->set_var('login', htmlspecialchars($last_loginid));

		$tmpl->set_var('lang_username',lang('username'));
		$tmpl->set_var('lang_login',lang('login'));

		$tmpl->set_var('website_title', $GLOBALS['egw_info']['server']['site_title']);
		$tmpl->set_var('template_set',$this->framework->template);

		$var['background_file'] = self::pick_login_background($GLOBALS['egw_info']['server']['login_background_file']);

		$var['logo_file'] = Api\Framework::get_login_logo_or_bg_url('login_logo_file', 'login_logo');

		$var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.egroupware.org';
		if (substr($var['logo_url'],0,4) != 'http')
		{
			$var['logo_url'] = 'http://'.$var['logo_url'];
		}
		$var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.eGroupWare.org';
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

		if($GLOBALS['egw_info']['server']['allow_cookie_auth'])
		{
			$tmpl->set_block('login_form','remember_me_selection');
			$tmpl->set_var('lang_remember_me',lang('Remember me'));
			$tmpl->set_var('select_remember_me',Api\Html::select('remember_me', '', array(
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
		Api\Framework::includeJS('jquery', 'jquery');

		$this->framework->render($tmpl->fp('loginout','login_form'),false,false);
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
			return Api\Image::find('api',$chosen ? $chosen : 'login_background', '', null);
		}
	}

	/**
	* displays a login denied message
	*/
	function denylogin_screen()
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
			case 10:
				$GLOBALS['egw']->session->egw_setcookie('sessionid');
				$GLOBALS['egw']->session->egw_setcookie('kp3');
				$GLOBALS['egw']->session->egw_setcookie('domain');
				return lang('Your session timed out, please log in again');
			default:
				if (!$code)
				{
					return '&nbsp;';
				}
				return htmlspecialchars($code);
		}
	}
}
