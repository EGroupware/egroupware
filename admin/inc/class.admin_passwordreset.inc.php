<?php
/**
 * EGgroupware admin - Reset passwords
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2011 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/setup/inc/hook_config.inc.php');	// functions to return password hashes

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
	);

	/**
	 * @var array
	 */
	var $replacements = array();

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
		$this->replacements = array(
			'account_lid' => lang('Login-ID'),
			'account_firstname' => lang('firstname'),
			'account_lastname' => lang('lastname'),
			'account_email' => lang('email'),
			'account_password' => lang('new password'),
			'account_id' => lang('nummeric account ID'),
		);
	}

	/**
	 * Reset passwords
	 *
	 * @param array $content=null
	 * @param string $msg=''
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
			if ($content['download_csv'] && $content['changed'])
			{
				html::content_header('changed.csv','text/csv');
				//echo "account_lid;account_password;account_email;account_firstname;account_lastname\n";
				foreach($content['changed'] as $account)
				{
					echo "$account[account_lid];$account[account_password];$account[account_email];$account[account_firstname];$account[account_lastname]\n";
				}
				common::egw_exit();
			}
			if (!$content['users'])
			{
				$msg = lang('You need to select some users first!');
			}
			elseif (!$content['random_pw'] && !$content['hash'] && !$content['notify'])
			{
				$msg = lang('You need to check "%1", "%2" or select any from "%3"!',
					lang('Set a random password'),
					lang('Notify user by email'),
					lang('Change password hash to'));
			}
			elseif(!$content['random_pw'] && $content['hash'] && $content['hash'] != $current_hash && $current_hash != 'plain')
			{
				$msg = lang('You can only change the hash, if you set a random password or currently use plaintext passwords!');
			}
			else
			{
				if ($content['hash'] && $content['hash'] != $current_hash)
				{
					config::save_value($account_repository.'_encryption_type',$content['hash'],'phpgwapi');
					$msg = lang('Changed password hash for %1 to %2.',strtoupper($account_repository),$content['hash'])."\n";
					$GLOBALS['egw_info']['server'][$account_repository.'_encryption_type'] = $content['hash'];
				}
				$changed = array();
				foreach($content['users'] as $account_id)
				{
					if (($account = $GLOBALS['egw']->accounts->read($account_id)))
					{
						//_debug_array($account); //break;

						if ($content['random_pw'])
						{
							$password = auth::randomstring(8);
							$old_password = null;
						}
						elseif (!preg_match('/^{plain}/i',$account['account_pwd']) &&
							($current_hash != 'plain' || $current_hash == 'plain' && $account['account_pwd'][0] == '{'))
						{
							$msg .= lang('Account "%1" has NO plaintext password!',$account['account_lid'])."\n";
							continue;
						}
						else
						{
							$old_password = $password = preg_replace('/^{plain}/i','',$account['account_pwd']);
						}
						if (!$GLOBALS['egw']->auth->change_password($old_password,$password,$account_id))
						{
							$msg .= lang('Failed to change password for account "%1"!',$account['account_lid'])."\n";
							continue;
						}
						$account['account_password'] = $password;
						$changed[] = $account;

						if ($content['notify'])
						{
							if (strpos($account['account_email'],'@') === false)
							{
								$msg .= lang('Account "%1" has no email address --> not notified!',$account['account_lid']);
								continue;
							}
							$send = new send();
							$send->AddAddress($account['account_email'],$account['account_fullname']);
							$replacements = array();
							foreach($this->replacements as $name => $label)
							{
								$replacements['$$'.$name.'$$'] = $account[$name];
							}
							$send->Subject = strtr($content['subject'],$replacements);
							$send->Body = strtr($content['body'],$replacements);
							if (!empty($GLOBALS['egw_info']['user']['account_email']))
							{
								$send->From = $GLOBALS['egw_info']['user']['account_email'];
								$send->FromName = $GLOBALS['egw_info']['user']['account_fullname'];
							}
							try
							{
								$send->Send();
							}
							catch (phpmailerException $e)
							{
								$msg .= lang('Notifying account "%1" %2 failed!',$account['account_lid'],$account['account_email']).
									': '.strip_tags(str_replace('<p>',"\n",$send->ErrorInfo))."\n";
							}
						}
					}
				}
				if ($changed)
				{
					$msg .= lang('Passwords of %1 accounts changed',count($changed));
				}
			}
		}
		$content['msg'] = $msg;
		$content['account_repository'] = $account_repository;
		$content['current_hash'] = $current_hash;
		$sel_options['hash'] = $account_repository == 'sql' ?
			sql_passwdhashes($GLOBALS['egw_info']['server'],true) :
			passwdhashes($GLOBALS['egw_info']['server'],true);
		$content['replacements'] = array();
		foreach($this->replacements as $name => $label)
		{
			$content['replacements'][] = array(
				'name'  => '$$'.$name.'$$',
				'label' => $label,
			);
		}
		$readonlys['download_csv'] = !$changed;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Reset passwords');

		$tmpl = new etemplate('admin.passwordreset');
		$tmpl->exec('admin.admin_passwordreset.index',$content,$sel_options,$readonlys,array(
			'changed' => $changed,
		));
	}
}
