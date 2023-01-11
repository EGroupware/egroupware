<?php
/**
 * EGroupware preferences: Security and passwords
 *
 * @package preferences
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use PragmaRX\Google2FAQRCode\Google2FA;
use EGroupware\Api\Mail\Credentials;

/**
 * Security and passwords
 *
 * Other apps can add tabs to this popup by implementing the "preferences_security" hook
 * like eg. the OpenID App does to allow users to revoke tokens.
 */
class preferences_password
{
	var $public_functions = array(
		'change' => True
	);
	const GAUTH_ANDROID = 'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2';
	const GAUTH_IOS = 'https://appstore.com/googleauthenticator';

	/**
	 * Change password, two factor auth or revoke tokens
	 *
	 * @param type $content
	 */
	function change($content = null)
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Security & Password');
		$tmpl = new Etemplate('preferences.password');

		$readonlys = $sel_options = [];
		try {
			// PHP 7.1+: using SVG image backend (requiring XMLWriter) and not ImageMagic extension
			if (class_exists('BaconQrCode\Renderer\Image\SvgImageBackEnd'))
			{
				$image_backend = new \BaconQrCode\Renderer\Image\SvgImageBackEnd;
			}
			$google2fa = new Google2FA($image_backend);
			$prefs = new Api\Preferences($GLOBALS['egw_info']['user']['account_id']);
			$prefs->read_repository();

			if (!is_array($content))
			{
				$content = [];
				$content['2fa'] = $this->generateQRCode($google2fa)+[
					'gauth_android' => self::GAUTH_ANDROID,
					'gauth_ios' => self::GAUTH_IOS,
				];
			}
			else
			{
				$secret_key = $content['2fa']['secret_key'];
				unset($content['2fa']['secret_key']);

				// check user password for everything but password change, where it will be checked anyway
				$auth = new Api\Auth();
				if ($content['tabs'] !== 'change_password' &&
					!$auth->authenticate($GLOBALS['egw_info']['user']['account_lid'], $content['password']))
				{
					$tmpl->set_validation_error('password', lang('Password is invalid'));
				}
				else
				{
					switch($content['tabs'])
					{
						case 'change_password':
							if (!$GLOBALS['egw']->acl->check('nopasswordchange', 1) && $content['button']['save'])
							{
								if (($errors = self::do_change($content['password'], $content['n_passwd'], $content['n_passwd_2'])))
								{
									Framework::message(implode("\n", $errors), 'error');
								}
								else
								{
									Framework::refresh_opener(lang('Password changed'), 'preferences');
									Framework::window_close();
								}
							}
							break;

						case 'two_factor_auth':
							switch(key($content['2fa']['action'] ?? []))
							{
								case 'show':
									$content['2fa'] = $this->generateQRCode($google2fa, false);
									break;
								case 'reset':
									$content['2fa'] = $this->generateQRCode($google2fa, true);
									Framework::message(lang('New secret generated, you need to save it to disable the old one!'));
									break;
								case 'disable':
									if (Credentials::delete(0, $GLOBALS['egw_info']['user']['account_id'], Credentials::TWOFA))
									{
										Framework::refresh_opener(lang('Secret deleted, two factor authentication disabled.'), 'preferences');
										Framework::window_close();
									}
									else
									{
										Framework::message(lang('Failed to delete secret!'), 'error');
									}
									break;
								default:	// no action, save secret
									if (!$google2fa->verifyKey($secret_key, $content['2fa']['code']))
									{
										$tmpl->set_validation_error('code', lang('Code is invalid'), '2fa');
										break 2;
									}
									if (($content['2fa']['cred_id'] = Credentials::write(0,
										$GLOBALS['egw_info']['user']['account_lid'],
										$secret_key, Credentials::TWOFA,
										$GLOBALS['egw_info']['user']['account_id'],
										$content['2fa']['cred_id'])))
									{
										Framework::refresh_opener(lang('Two Factor Auth enabled.'), 'preferences');
										Framework::window_close();
									}
									else
									{
										Framework::message(lang('Failed to store secret!'), 'error');
									}
									break;
							}
							unset($content['2fa']['action']);
							break;

						default:
							// for other tabs call their save_callback (user password is already checked!)
							if (!empty($content['save_callbacks'][$content['tabs']]) &&
								($msg = call_user_func_array($content['save_callbacks'][$content['tabs']], [&$content])))
							{
								Framework::message($msg, 'success');
							}
							break;
					}
				}
			}
		}
		catch (Exception $e) {
			Framework::message($e->getMessage(), 'error');
		}

		// disable 2FA tab, if admin disabled it
		if ($GLOBALS['egw_info']['server']['2fa_required'] === 'disabled')
		{
			$readonlys['tabs']['two_factor_auth'] = true;
		}

		// disable password change, if user has not right to change it
		if ($GLOBALS['egw']->acl->check('nopasswordchange', 1))
		{
			$readonlys['tabs']['change_password'] = true;
		}

		$preserve = [
			'2fa' => $content['2fa']+[
				'secret_key' => $secret_key,
			]
		];

		$tmpl->setElementAttribute('tabs', 'add_tabs', true);
		$tabs =& $tmpl->getElementAttribute('tabs', 'extraTabs');
		if (($first_call = !isset($tabs)))
		{
			$tabs = array();
		}
		// register hooks, if openid is available, but new hook not yet registered (should be removed after 19.1)
		if (!empty($GLOBALS['egw_info']['apps']['openid']) && !Api\Hooks::implemented('preferences_security'))
		{
			Api\Hooks::read(true);
		}
		$hook_data = Api\Hooks::process(array('location' => 'preferences_security')+$content, ['openid'], true);
		foreach($hook_data as $extra_tabs)
		{
			if (!$extra_tabs) continue;

			foreach(isset($extra_tabs[0]) ? $extra_tabs : [$extra_tabs] as $extra_tab)
			{
				if (!empty($extra_tab['data']) && is_array($extra_tab['data']))
				{
					$content = array_merge($content, $extra_tab['data']);
				}
				if (!empty($extra_tab['preserve']) && is_array($extra_tab['preserve']))
				{
					$preserve = array_merge($preserve, $extra_tab['preserve']);
				}
				if (!empty($extra_tab['sel_options']) && is_array($extra_tab['sel_options']))
				{
					$sel_options = array_merge($sel_options, $extra_tab['sel_options']);
				}
				if (!empty($extra_tab['readonlys']) && is_array($extra_tab['readonlys']))
				{
					$readonlys = array_merge($readonlys, $extra_tab['readonlys']);
				}
				if (!empty($extra_tab['save_callback']))
				{
					$preserve['save_callbacks'][$extra_tab['name']] = $extra_tab['save_callback'];
				}
				// we must NOT add tabs more then once!
				if ($first_call && !empty($extra_tab['label']) && !empty($extra_tab['name']))
				{
					$tabs[] = array(
						'label' =>	$extra_tab['label'],
						'template' =>	$extra_tab['name'],
						'prepend' => $extra_tab['prepend'],
					);
				}
				//error_log(__METHOD__."() changed tabs=".array2string($tabs));
			}
		}

		$tmpl->exec('preferences.preferences_password.change', $content, $sel_options, $readonlys, $preserve, 2);
	}

	/**
	 * Generate QRCode and optional new secret
	 *
	 * @param Google2FA $google2fa
	 * @param boolean|null $generate =null null: generate new qrCode/secret, if none exists
	 *  true: allways generate new qrCode (to reset existing one)
	 *  false: use existing secret, but generate qrCode
	 * @return array with keys "qrc" and "cred_id"
	 */
	protected function generateQRCode(Google2FA $google2fa, $generate=null)
	{
		$creds = Credentials::read(0, Credentials::TWOFA, $GLOBALS['egw_info']['user']['account_id']);

		if (!$generate && $creds && strlen($creds['2fa_password']) >= 16)
		{
			$secret_key = $creds['2fa_password'];
		}
		else
		{
			$secret_key = $google2fa->generateSecretKey();//16, $GLOBALS['egw_info']['user']['account_lid']);
		}
		if (isset($generate) || empty($creds))
		{
			$image = $google2fa->getQRCodeInline(
				!empty($GLOBALS['egw_info']['server']['site_title']) ?
					$GLOBALS['egw_info']['server']['site_title'] : 'EGroupware',
				$GLOBALS['egw_info']['user']['account_email'],
				$secret_key
			);
			// bacon/bacon-qr-code >= 2 does not generate a data-url itself, but 1.x does :(
			if (substr($image, 0, 11) !== 'data:image/')
			{
				$image = 'data:image/'.(substr($image, 0, 5) === '<?xml' ? 'svg+xml' : 'png').
					';base64,'.base64_encode($image);
			}
		}
		return [
			'qrc' => $image,
			'hide_qrc' => empty($image),
			'cred_id' => !empty($creds) ? $creds['2fa_cred_id'] : null,
			'secret_key' => $secret_key,
			'status' => !empty($creds) ? lang('Two Factor Auth is already setup.') : '',
		];
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
			Api\Translation::add_app('preferences');
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
