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
				$o_passwd = $GLOBALS['egw_info']['user']['passwd'];
				if($o_passwd != $content['o_passwd_2'])
				{
					$errors[] = lang('The old password is not correct');
				}
				if($content['n_passwd'] != $content['n_passwd_2'])
				{
					$errors[] = lang('The two passwords are not the same');
				}

				if($o_passwd == $content['n_passwd'])
				{
					$errors[] = lang('Old password and new password are the same. This is invalid. You must enter a new password');
				}

				if(!$content['n_passwd'])
				{
					$errors[] = lang('You must enter a password');
				}

				// allow auth backends or configured password strenght to throw exceptions and display there message
				if (!$errors)
				{
					try {
						$passwd_changed = $GLOBALS['egw']->auth->change_password($o_passwd, $content['n_passwd'],
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
}
