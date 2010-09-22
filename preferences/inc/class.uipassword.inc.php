<?php
/**
 * EGroupware preferences
 *
 * @package preferences
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class uipassword
{
	var $public_functions = array(
		'change' => True
	);

	function uipassword()
	{
		$this->bo =& CreateObject('preferences.bopassword');

	}

	function change()
	{
		//_debug_array($GLOBALS['egw_info']['user']);
		$n_passwd   = $_POST['n_passwd'];
		$n_passwd_2 = $_POST['n_passwd_2'];
		$o_passwd_2 = $_POST['o_passwd_2'];

		if($GLOBALS['egw']->acl->check('nopasswordchange', 1) || $_POST['cancel'])
		{
			if ($GLOBALS['egw_info']['user']['apps']['preferences'])
			{
				egw::redirect_link('/preferences/index.php');
			}
			else
			{
				egw::redirect_link('/index.php');	// redirect to start page
			}
		}

		$GLOBALS['egw']->template->set_file(array(
			'form' => 'changepassword.tpl'
		));
		$GLOBALS['egw']->template->set_var('lang_enter_password',lang('Enter your new password'));
		$GLOBALS['egw']->template->set_var('lang_reenter_password',lang('Re-enter your password'));
		$GLOBALS['egw']->template->set_var('lang_enter_old_password',lang('Enter your old password'));
		$GLOBALS['egw']->template->set_var('lang_change',lang('Change'));
		$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
		$GLOBALS['egw']->template->set_var('form_action',
			$GLOBALS['egw_info']['user']['apps']['preferences'] ?
				egw::link('/index.php','menuaction=preferences.uipassword.change') :
				egw::link('/preferences/password.php'));

		if($GLOBALS['egw_info']['server']['auth_type'] != 'ldap')
		{
			$GLOBALS['egw']->template->set_var('sql_message',lang('note: This feature does *not* change your email password. This will '
				. 'need to be done manually.'));
		}

		if($_POST['change'])
		{
			$o_passwd = $GLOBALS['egw_info']['user']['passwd'];

			if($o_passwd != $o_passwd_2)
			{
				$errors[] = lang('The old password is not correct');
			}

			if($n_passwd != $n_passwd_2)
			{
				$errors[] = lang('The two passwords are not the same');
			}

			if($o_passwd == $n_passwd)
			{
				$errors[] = lang('Old password and new password are the same. This is invalid. You must enter a new password');
			}

			if(!$n_passwd)
			{
				$errors[] = lang('You must enter a password');
			}
			$strength = ($GLOBALS['egw_info']['server']['force_pwd_strength']?$GLOBALS['egw_info']['server']['force_pwd_strength']:false);
			//error_log(__METHOD__.__LINE__.' Strength:'.$strength);

			if ($strength && $strength>5) $strength =5;
			if ($strength && $strength<0) $strength = false; 
			if($GLOBALS['egw_info']['server']['check_save_passwd'] && $strength==false) $strength=5;//old behavior
			//error_log(__METHOD__.__LINE__.' Strength:'.$strength);
			if(($GLOBALS['egw_info']['server']['check_save_passwd'] || $strength) && $error_msg = $GLOBALS['egw']->auth->crackcheck($n_passwd,$strength))
			{
				$errors[] = $error_msg;
			}

			if(is_array($errors))
			{
				common::egw_header();
				echo parse_navbar();
				$GLOBALS['egw']->template->set_var('messages',common::error_list($errors));
				$GLOBALS['egw']->template->pfp('out','form');
				common::egw_exit(True);
			}

			$passwd_changed = $this->bo->changepass($o_passwd, $n_passwd);
			if(!$passwd_changed)
			{
				$errors[] = lang('Failed to change password.  Please contact your administrator.');
				common::egw_header();
				echo parse_navbar();
				$GLOBALS['egw']->template->set_var('messages',common::error_list($errors));
				$GLOBALS['egw']->template->pfp('out','form');
				common::egw_exit(True);
			}
			else
			{
				$GLOBALS['egw']->session->appsession('password','phpgwapi',base64_encode($n_passwd));
				$GLOBALS['egw_info']['user']['passwd'] = $n_passwd;
				$GLOBALS['egw_info']['user']['account_lastpwd_change'] = egw_time::to('now','ts');
				accounts::cache_invalidate($GLOBALS['egw_info']['user']['account_id']);
				egw::invalidate_session_cache();
				//_debug_array( $GLOBALS['egw_info']['user']);
				$GLOBALS['hook_values']['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
				$GLOBALS['hook_values']['old_passwd'] = $o_passwd;
				$GLOBALS['hook_values']['new_passwd'] = $n_passwd;

				// called for every app now, not only for the ones enabled for the user
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'changepassword',
				),False,True);
				if ($GLOBALS['egw_info']['user']['apps']['preferences'])
				{
					egw::redirect_link('/preferences/index.php','cd=18');
				}
				$_GET['message'] = lang('Password changed');
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Change your password');
		common::egw_header();
		echo parse_navbar();

		$GLOBALS['egw']->template->set_var('messages','<span class="redItalic">'.htmlspecialchars($_GET['message']).'</span>');
		$GLOBALS['egw']->template->pfp('out','form');
		common::egw_footer();
	}
}
