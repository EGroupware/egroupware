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

class preferences_password
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
		if (isset($_GET['message'])) $message = str_replace("<br />"," ",html::purify($_GET['message']));
		if($GLOBALS['egw']->acl->check('nopasswordchange', 1) || $_POST['cancel'])
		{
			egw_framework::window_close();
		}

		$GLOBALS['egw']->template->set_file(array(
			'form' => 'changepassword.tpl'
		));
		$GLOBALS['egw']->template->set_var('lang_enter_password',lang('Enter your new password'));
		$GLOBALS['egw']->template->set_var('lang_reenter_password',lang('Re-enter your password'));
		$GLOBALS['egw']->template->set_var('lang_enter_old_password',lang('Enter your old password'));
		$GLOBALS['egw']->template->set_var('lang_change',lang('Change password'));
		$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
		$GLOBALS['egw']->template->set_var('form_action',egw::link('/index.php','menuaction=preferences.preferences_password.change'));

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
				egw_framework::message(implode("\n", $errors), 'error');
				common::egw_header();
				echo parse_navbar();
				$GLOBALS['egw']->template->pfp('out','form');
				common::egw_exit(True);
			}
			else
			{
				egw_framework::refresh_opener(lang('Password changed'), 'preferences');
				egw_framework::window_close();
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Change your password');
		common::egw_header();
		echo parse_navbar();

		$GLOBALS['egw']->template->pfp('out','form');
		common::egw_footer();
	}
}
