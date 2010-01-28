<?php
/**
 * eGroupWare API - Authentication agains mail server
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Authentication agains mail server
 */
class auth_mail implements auth_backend
{
	var $previous_login = -1;

	/**
	 * password authentication
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		$notls = '/notls';
		if ($GLOBALS['egw_info']['server']['mail_login_type'] == 'vmailmgr')
		{
			$username = $username . '@' . $GLOBALS['egw_info']['server']['mail_suffix'];
		}
		if ($GLOBALS['egw_info']['server']['mail_server_type']=='imap')
		{
			$GLOBALS['egw_info']['server']['mail_port'] = '143';
		}
		elseif ($GLOBALS['egw_info']['server']['mail_server_type']=='pop3')
		{
			$GLOBALS['egw_info']['server']['mail_port'] = '110';
		}
		elseif ($GLOBALS['egw_info']['server']['mail_server_type']=='imaps')
		{
			$GLOBALS['egw_info']['server']['mail_port'] = '993';
			$notls = '';
		}
		elseif ($GLOBALS['egw_info']['server']['mail_server_type']=='pop3s')
		{
			$GLOBALS['egw_info']['server']['mail_port'] = '995';
		}

		if( $GLOBALS['egw_info']['server']['mail_server_type']=='pop3')
		{
			$mailauth = imap_open('{'.$GLOBALS['egw_info']['server']['mail_server'].'/pop3'
				.':'.$GLOBALS['egw_info']['server']['mail_port'].'}INBOX', $username , $passwd);
		}
		elseif ( $GLOBALS['egw_info']['server']['mail_server_type']=='imaps' )
		{
			// IMAPS support:
			$mailauth = imap_open('{'.$GLOBALS['egw_info']['server']['mail_server']."/ssl/novalidate-cert"
				.':993}INBOX', $username , $passwd);
		}
		elseif ( $GLOBALS['egw_info']['server']['mail_server_type']=='pop3s' )
		{
			// POP3S support:
			$mailauth = imap_open('{'.$GLOBALS['egw_info']['server']['mail_server']."/ssl/novalidate-cert"
				.':995}INBOX', $username , $passwd);
		}
		else
		{
			/* assume imap */
			$mailauth = imap_open('{'.$GLOBALS['egw_info']['server']['mail_server']
				.':'.$GLOBALS['egw_info']['server']['mail_port'].$notls.'}INBOX', $username , $passwd);
		}

		if ($mailauth == False)
		{
			return False;
		}
		imap_close($mailauth);
		
		return True;
	}

	/**
	 * changes password
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id=0 account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		return False;
	}
}
