<?php
/**
 * EGgroupware admin - Reset passwords
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2011-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/setup/inc/hook_config.inc.php');	// functions to return password hashes

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Mail\Credentials;
use EGroupware\OpenID\Repositories\AccessTokenRepository;
use EGroupware\WebAuthn\PublicKeyCredentialSourceRepository;

/**
 * Reset passwords
 */
class admin_passwordreset
{
	/**
	 * Which methods of this class can be called as menuation
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'ajax_clear_credentials' => true
	);

	/**
	 * @var array
	 */
	var $replacements = array(
		'lid' => 'LoginID',
		'firstname' => 'first name',
		'lastname' => 'last name',
		'fullname' => 'full name',
		'email' => 'email',
		'password' => 'new password',
		'id' => 'nummeric account ID',
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		if($GLOBALS['egw']->acl->check('account_access',16,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
	}

	/**
	 * Reset passwords
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 */
	function index(array $content=null, $msg='')
	{
		if (!($account_repository = $GLOBALS['egw_info']['server']['account_repository']) &&
			!($account_repository = $GLOBALS['egw_info']['server']['auth_type']))
		{
			$account_repository = 'sql';
		}
		if (!($current_hash = $GLOBALS['egw_info']['server'][$account_repository.'_encryption_type']))
		{
			$current_hash = 'md5';
		}
		if (is_array($content))
		{
			// Save message for next time
			Api\Config::save_value('password_reset_message',array('subject' => $content['subject'], 'body' => $content['body']),'admin');

			if ($content['download_csv'] && $content['changed'])
			{
				Api\Header\Content::type('changed.csv', 'text/csv');
				//echo "account_lid;account_password;account_email;account_firstname;account_lastname\n";
				foreach($content['changed'] as $account)
				{
					echo "$account[account_lid];$account[account_password];$account[account_email];$account[account_firstname];$account[account_lastname]\n";
				}
				exit;
			}
			if (!$content['users'])
			{
				$msg = lang('You need to select some users first!');
			}
			elseif (!$content['random_pw'] && !$content['hash'] && !$content['notify'] &&
				(string)$content['changepassword'] === '' && (string)$content['mustchangepassword'] === '' &&
				(string)$content['mail']['activate'] === '' && (string)$content['mail']['quota'] === '' &&
				strpos($content['mail']['domain'], '.') === false)
			{
				$msg = lang('You need to select as least one action!');
			}
			elseif(!$content['random_pw'] && $content['hash'] && $content['hash'] != $current_hash && $current_hash != 'plain')
			{
				$msg = lang('You can only change the hash, if you set a random password or currently use plaintext passwords!');
			}
			else
			{
				if ($content['hash'] && $content['hash'] != $current_hash)
				{
					Api\Config::save_value($account_repository.'_encryption_type',$content['hash'],'phpgwapi');
					$msg = lang('Changed password hash for %1 to %2.',strtoupper($account_repository),$content['hash'])."\n";
					$GLOBALS['egw_info']['server'][$account_repository.'_encryption_type'] = $content['hash'];
				}
				$change_pw = $content['random_pw'] || $content['hash'] && $content['hash'] != $current_hash;
				$changed = array();
				$emailadmin = null;
				foreach($content['users'] as $account_id)
				{
					if (($account = $GLOBALS['egw']->accounts->read($account_id)))
					{
						//_debug_array($account); //break;
						if ($content['random_pw'])
						{
							if (($minlength=$GLOBALS['egw_info']['server']['force_pwd_length']) < 8)
							{
								$minlength = 8;
							}
							$n = 0;
							do {
								$password = Api\Auth::randomstring($minlength,
									$GLOBALS['egw_info']['server']['force_pwd_strength'] >= 4);
								error_log(__METHOD__."() minlength=$minlength, n=$n, password=$password");
							} while (++$n < 100 && Api\Auth::crackcheck($password, null, null, null, $account));
							$old_password = null;
						}
						elseif ($change_pw && !preg_match('/^{plain}/i',$account['account_pwd']) &&
							($current_hash != 'plain' || $current_hash == 'plain' && $account['account_pwd'][0] == '{'))
						{
							$msg .= lang('Account "%1" has NO plaintext password!',$account['account_lid'])."\n";
							continue;
						}
						else
						{
							$old_password = $password = preg_replace('/^{plain}/i','',$account['account_pwd']);
						}
						// change password, if requested
						try {
							if ($change_pw && !$GLOBALS['egw']->auth->change_password($old_password,$password,$account_id))
							{
								$msg .= lang('Failed to change password for account "%1"!',$account['account_lid'])."\n";
								continue;
							}
						}
						catch(Exception $e) {
							$msg .= lang('Failed to change password for account "%1"!',$account['account_lid']).' '.$e->getMessage()."\n";
							continue;
						}
						// force password change on next login
						if ((string)$content['mustchangepassword'] !== '' && !(!$content['mustchangepassword'] && $change_pw))
						{
							// dont use password here, as the use of passwords indicates the usage of the functionality in usermode
							$GLOBALS['egw']->auth->setLastPwdChange($account_id, null, $content['mustchangepassword'] ? 0 : time());
						}
						// allow or forbid to change password, if requested
						if ((string)$content['changepassword'] !== '')
						{
							if(!$content['changepassword'])
							{
								$GLOBALS['egw']->acl->add_repository('preferences','nopasswordchange',$account_id,1);
							}
							else
							{
								$GLOBALS['egw']->acl->delete_repository('preferences','nopasswordchange',$account_id);
							}
						}
						$account['account_password'] = $password;

						if ((string)$content['mail']['activate'] !== '' || (string)$content['mail']['quota'] !== '' ||
							strpos($content['mail']['domain'], '.') !== false)
						{
							if (!isset($emailadmin))
							{
								$emailadmin = Api\Mail\Account::get_default();
								if (!Api\Mail\Account::is_multiple($emailadmin))
								{
									$msg = lang('No default account found!');
									break;
								}
							}
							if (($userData = $emailadmin->getUserData ($account_id)))
							{
								if ((string)$content['mail']['activate'] !== '')
								{
									$userData['accountStatus'] = $content['mail']['activate'] ? 'active' : '';
								}
								if ((string)$content['mail']['quota'] !== '')
								{
									$userData['quotaLimit'] = $content['mail']['quota'];
								}
								if (strpos($content['mail']['domain'], '.') !== false)
								{
									$userData['mailLocalAddress'] = preg_replace('/@'.preg_quote($emailadmin->acc_domain).'$/', '@'.$content['mail']['domain'], $userData['mailLocalAddress']);

									foreach($userData['mailAlternateAddress'] as &$alias)
									{
										$alias = preg_replace('/@'.preg_quote($emailadmin->acc_domain).'$/', '@'.$content['mail']['domain'], $alias);
									}
								}
								$emailadmin->saveUserData($account_id, $userData);
							}
							else
							{
								$msg .= lang('No profile defined for user %1', '#'.$account_id.' '.$account['account_fullname']."\n");
								continue;
							}
						}
						$changed[] = $account;

						if ($content['notify'])
						{
							if (strpos($account['account_email'],'@') === false)
							{
								$msg .= lang('Account "%1" has no email address --> not notified!',$account['account_lid']);
								continue;
							}
							$send = new Api\Mailer();
							$send->AddAddress($account['account_email'],$account['account_fullname']);
							$replacements = array();
							foreach($this->replacements as $name => $label)
							{
								$replacements['$$'.$name.'$$'] = $account['account_'.$name];
							}
							$send->addHeader('Subject', strtr($content['subject'], $replacements));
							$send->setBody(strtr($content['body'], $replacements));
							if (!empty($GLOBALS['egw_info']['user']['account_email']))
							{
								$send->addHeader('From', Api\Mailer::add_personal(
									$GLOBALS['egw_info']['user']['account_email'],
									$GLOBALS['egw_info']['user']['account_fullname']));
							}
							try
							{
								$send->Send();
							}
							catch (Exception $e)
							{
								$msg .= lang('Notifying account "%1" %2 failed!',$account['account_lid'],$account['account_email']).
									': '.strip_tags(str_replace('<p>', "\n", $e->getMessage()))."\n";
							}
						}
					}
				}
				if ($changed)
				{
					$msg .= lang('Passwords and/or attributes of %1 accounts changed',count($changed));
				}
			}
		}
		$content['msg'] = $msg;
		$content['account_repository'] = $account_repository;
		$content['current_hash'] = $content['hash'] ? $content['hash'] : $current_hash;
		$sel_options['hash'] = $account_repository == 'sql' ?
			sql_passwdhashes($GLOBALS['egw_info']['server'],true) :
			passwdhashes($GLOBALS['egw_info']['server'],true);
		$sel_options['activate'] = array('Deactivate','Activate');

		// Start with same message as last time
		$config = Api\Config::read('admin');
		$message = $config['password_reset_message'];
		$content['subject'] = $message['subject'];
		$content['body'] = $message['body'];

		$content['replacements'] = array();
		foreach($this->replacements as $name => $label)
		{
			$content['replacements'][] = array(
				'name'  => '$$'.$name.'$$',
				'label' => $label,
			);
		}
		$readonlys['download_csv'] = !$changed;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Bulk password reset');

		$tmpl = new Api\Etemplate('admin.passwordreset');
		$tmpl->exec('admin.admin_passwordreset.index',$content,$sel_options,$readonlys,array(
			'changed' => $changed,
		));
	}

	public function ajax_clear_credentials($action_id, $account_ids)
	{
		$msg = [];

		if($action_id == 'clear_mail')
		{
			$count = Api\Mail\Credentials::delete(0,$account_ids, Credentials::IMAP|Credentials::SMTP|Credentials::SMIME);
			$msg[] = lang("%1 mail credentials deleted", $count);
		}

		$action['action'] = 'delete';
		$action['selected'] = $account_ids;
		$hook_data = array();

		if($action_id == 'clear_2fa')
		{
			if (Credentials::delete(0, $account_ids, Credentials::TWOFA))
			{
				$msg[] = lang('Secret deleted, two factor authentication disabled.');
			}
			$hook_data = Api\Hooks::process(array('location' => 'preferences_security'), ['openid'], true);
		}
		foreach($hook_data as $extra_tab)
		{
			if($extra_tab['delete'])
			{
				$msg[] = call_user_func_array($extra_tab['delete'], [$account_ids]);
			}
			else
			{
				switch ($extra_tab['name'])
				{
					case 'openid.access_tokens':
						// We need to get all access tokens, no easy way to delete by account
						$token_repo = new AccessTokenRepository();
						$token_repo->revokeAccessToken(['account_id' => $action['selected']]);
						$count = $GLOBALS['egw']->db->affected_rows();
						$msg[] = ($count > 1 ? $count.' ' : '') .  lang('Access Token revoked.');
						break;
					case 'webauthn.tokens':
						$token_repo = new PublicKeyCredentialSourceRepository();
						$count = $token_repo->delete(['account_id' => $action['selected']]);
						$msg[] = ($count > 1 ? $count.' ' : '') . lang($extra_tab['label']) . ' ' . lang('deleted');
						break;
					default:
						// Each credential / security option can have its nm as a different ID
						$content['tabs'] = $extra_tab['name'];
						foreach($extra_tab['data'] as $id => $datum)
						{
							if(is_array($datum) && array_key_exists('get_rows',$datum))
							{
								$content[$id] = $action;
							}
						}
					$msg[] = call_user_func_array($extra_tab['save_callback'], [$content]);
				}
			}
		}
		Framework::message(implode("\n",$msg), 'success');
		Framework::redirect_link('/index.php', 'menuaction=admin.admin_ui.index','admin');
	}
}
