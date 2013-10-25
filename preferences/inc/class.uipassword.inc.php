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

	function change()
	{
		//_debug_array($GLOBALS['egw_info']['user']);
		$n_passwd   = $_POST['n_passwd'];
		$n_passwd_2 = $_POST['n_passwd_2'];
		$o_passwd_2 = $_POST['o_passwd_2'];
		if (isset($_GET['message'])) $_GET['message'] = str_replace("<br />"," ",html::purify($_GET['message']));
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
		$GLOBALS['egw']->template->set_var('lang_change',lang('Change password'));
		$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
		$GLOBALS['egw']->template->set_var('form_action',
			$GLOBALS['egw_info']['user']['apps']['preferences'] ?
				egw::link('/index.php','menuaction=preferences.uipassword.change') :
				egw::link('/preferences/password.php'));

		if($GLOBALS['egw_info']['server']['auth_type'] != 'ldap')
		{
			$smtpClassName = 'defaultsmtp';
			if (($default_profile_id = emailadmin_bo::getDefaultProfileID()))
			{
				$bofelamimail = felamimail_bo::forceEAProfileLoad($default_profile_id);
				//fetch the smtpClass
				//_debug_array($bofelamimail->ogServer);
				$smtpClassName = get_class($bofelamimail->ogServer);
			}
			$GLOBALS['egw']->template->set_var('sql_message',
				$smtpClassName != 'defaultsmtp' ? '' :
					lang('note: This feature does *not* change your email password. This will need to be done manually.'));
		}

		$errors = array();
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

			// allow auth backends or configured password strenght to throw exceptions and display there message
			if (!$errors)
			{
				try {
					$passwd_changed = $GLOBALS['egw']->auth->change_password($o_passwd, $n_passwd,
						$GLOBALS['egw_info']['user']['account_id']);
				}
				catch (Exception $e) {
					$errors[] = $e->getMessage();
				}
			}
			if(!$passwd_changed)
			{
				if (!$errors)	// if we have no specific error, add general message
				{
					$errors[] = lang('Failed to change password.');
				}
				common::egw_header();
				echo parse_navbar();
				$GLOBALS['egw']->template->set_var('messages',common::error_list($errors));
				$GLOBALS['egw']->template->pfp('out','form');
				common::egw_exit(True);
			}
			else
			{
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
