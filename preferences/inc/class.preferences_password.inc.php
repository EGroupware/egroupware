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

	/**
	 * Change password function
	 * process change password form
	 *
	 * @param type $content
	 */
	function change($content = null)
	{
		if ($GLOBALS['egw']->acl->check('nopasswordchange', 1))
		{
			egw_framework::window_close('There was no password change!');
		}

		if (!is_array($content))
		{
			$content= array();
		}
		else
		{
			if ($content['button']['change'])
			{
				if (($errors = self::do_change($content['o_passwd_2'], $content['n_passwd'], $content['n_passwd_2'])))
				{
					egw_framework::message(implode("\n", $errors), 'error');
					$content = array();
				}
				else
				{
					egw_framework::refresh_opener(lang('Password changed'), 'preferences');
					egw_framework::window_close();
				}
			}
		}

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Change your password');
		$tmpl = new etemplate_new('preferences.password');

		$tmpl->exec('preferences.preferences_password.change', $content,array(),array(),array(),2);
	}

	/**
	 * Do some basic checks and then change password
	 *
	 * @param string $old_passwd
	 * @param string $new_passwd
	 * @param string $new_passwd2
	 * @return array with already translated errors
	 */
	public static function do_change($old_passwd, $new_passwd, $new_passwd2)
	{
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'preferences')
		{
			translation::add_app('preferences');
		}
		$errors = array();

		if (isset($GLOBALS['egw_info']['user']['passwd']) &&
			$old_passwd !== $GLOBALS['egw_info']['user']['passwd'])
		{
			$errors[] = lang('The old password is not correct');
		}
		if ($new_passwd != $new_passwd2)
		{
			$errors[] = lang('The two passwords are not the same');
		}

		if ($old_passwd !== false && $old_passwd == $new_passwd)
		{
			$errors[] = lang('Old password and new password are the same. This is invalid. You must enter a new password');
		}

		if (!$new_passwd)
		{
			$errors[] = lang('You must enter a password');
		}

		// allow auth backends or configured password strenght to throw exceptions and display there message
		if (!$errors)
		{
			try {
				if (!$GLOBALS['egw']->auth->change_password($old_passwd, $new_passwd,
					$GLOBALS['egw']->session->account_id))
				{
					// if we have no specific error, add general message
					$errors[] = lang('Failed to change password.');
				}
			}
			catch (Exception $e) {
				$errors[] = $e->getMessage();
			}
		}
		return $errors;
	}
}
