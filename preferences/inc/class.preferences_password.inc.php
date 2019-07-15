<?php
/**
 * EGroupware preferences
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
use EGroupware\OpenID\Repositories\AccessTokenRepository;
use EGroupware\OpenID\Repositories\ScopeRepository;
use EGroupware\OpenID\Repositories\RefreshTokenRepository;

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
		if ($GLOBALS['egw']->acl->check('nopasswordchange', 1))
		{
			Framework::window_close('Password change is disabled!');
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Change your password');
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

				switch($content['tabs'])
				{
					case 'change_password':
						if ($content['button']['save'])
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
						$auth = new Api\Auth();
						if (!$auth->authenticate($GLOBALS['egw_info']['user']['account_lid'], $content['password']))
						{
							$tmpl->set_validation_error('password', lang('Password is invalid'), '2fa');
							break;
						}
						switch(key($content['2fa']['action']))
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

					case 'tokens':
						if (is_array($content) && $content['nm']['selected'])
						{
							try {
								switch($content['nm']['action'])
								{
									case 'delete':
										$token_repo = new AccessTokenRepository();
										$token_repo->revokeAccessToken(['access_token_id' => $content['nm']['selected']]);
										$refresh_token_repo = new RefreshTokenRepository();
										$refresh_token_repo->revokeRefreshToken(['access_token_id' => $content['nm']['selected']]);
										$msg = (count($content['nm']['selected']) > 1 ?
											count($content['nm']['selected']).' ' : '').
											lang('Access Token revoked.');
										break;
								}
							}
							catch(\Exception $e) {
								$msg = lang('Error').': '.$e->getMessage();
								break;
							}
						}
						break;
				}
			}
		}
		catch (Exception $e) {
			Framework::message($e->getMessage(), 'error');
		}

		// display tokens, if we have openid installed (currently no run-rights needed!)
		if ($GLOBALS['egw_info']['apps']['openid'] && class_exists(AccessTokenRepository::class))
		{
			$content['nm'] = [
				'get_rows' => 'preferences.'.__CLASS__.'.getTokens',
				'no_cat' => true,
				'no_filter' => true,
				'no_filter2' => true,
				'filter_no_lang' => true,
				'order' => 'access_token_updated',
				'sort' => 'DESC',
				'row_id' => 'access_token_id',
				'default_cols' => '!client_id',
				'actions' => self::tokenActions(),
			];
			$sel_options += [
				'client_status' => ['Disabled', 'Active'],
				'access_token_revoked' => ['Active', 'Revoked'],
				'access_token_scopes' => (new ScopeRepository())->selOptions(),
			];
		}
		else
		{
			$readonlys['tabs']['tokens'] = true;
		}

		// disable 2FA tab, if admin disabled it
		if ($GLOBALS['egw_info']['server']['2fa_required'] === 'disabled')
		{
			$readonlys['tabs']['two_factor_auth'] = true;
		}

		$tmpl->exec('preferences.preferences_password.change', $content, $sel_options, $readonlys, [
			'2fa' => $content['2fa']+[
				'secret_key' => $secret_key,
			],
		], 2);
	}

	/**
	 * Query tokens for nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int number of rows found
	 */
	public function getTokens(array $query, array &$rows, array &$readonlys)
	{
		if (!class_exists(AccessTokenRepository::class)) return;

		$token_repo = new AccessTokenRepository();
		if (($ret = $token_repo->get_rows($query, $rows, $readonlys)))
		{
			foreach($rows as $key => &$row)
			{
				if (!is_int($key)) continue;

				// boolean does NOT work as key for select-box
				$row['access_token_revoked'] = (string)(int)$row['access_token_revoked'];
				$row['client_status'] = (string)(int)$row['client_status'];

				// dont send token itself to UI
				unset($row['access_token_identifier']);

				// format user-agent as "OS Version\nBrowser Version" prefering auth-code over access-token
				// as for implicit grant auth-code contains real user-agent, access-token container the server
				if (!empty($row['auth_code_user_agent']))
				{
					$row['user_agent'] = Api\Header\UserAgent::osBrowser($row['auth_code_user_agent']);
					$row['user_ip'] = $row['auth_code_ip'];
					$row['user_agent_tooltip'] = Api\Header\UserAgent::osBrowser($row['access_token_user_agent']);
					$row['user_ip_tooltip'] = $row['access_token_ip'];
				}
				else
				{
					$row['user_agent'] = Api\Header\UserAgent::osBrowser($row['access_token_user_agent']);
					$row['user_ip'] = $row['access_token_ip'];
				}
			}
		}
		return $ret;
	}

	/**
	 * Get actions for tokens
	 */
	protected function tokenActions()
	{
		return [
			'delete' => array(
				'caption' => 'Revoke',
				'allowOnMultiple' => true,
				'confirm' => 'Revoke this token',
			),
		];
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
		$qrc = '';
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
