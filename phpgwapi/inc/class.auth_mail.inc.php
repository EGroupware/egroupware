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
	 * We are always trying to establish a TLS connection, but we do not
	 * (yet) validate certs, as most PHP installs dont validate them!
	 * For imap/pop3 we are NOT adding notls to use STARTTLS if server supports it.
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		unset($passwd_type);	// not used but required by function signature

		check_load_extension('imap', true);

		switch ($GLOBALS['egw_info']['server']['mail_login_type'])
		{
			case 'vmailmgr':
				$username = $username . '@' . $GLOBALS['egw_info']['server']['mail_suffix'];
				break;
			case 'email':
				$username = $GLOBALS['egw']->accounts->id2name($username, 'account_email');
				break;
			case 'uidNumber':
				$username = 'u'.$GLOBALS['egw']->accounts->name2id($username);
				break;
		}

		list($host, $port) = explode(':', $GLOBALS['egw_info']['server']['mail_server']);
		switch ($GLOBALS['egw_info']['server']['mail_server_type'])
		{
			case 'imap':
			default:
				if (!isset($port)) $port = 143;
				$mailauth = imap_open('{'.$host.':'.$port.'/imap/novalidate-cert}INBOX', $username , $passwd);
				break;
			case 'imaps':
				if (!isset($port)) $port = 993;
				$mailauth = imap_open('{'.$host.'/imap/ssl/novalidate-cert:'.$port.'}INBOX', $username , $passwd);
				break;
			case 'pop3':
				if (!isset($port)) $port = 110;
				$mailauth = imap_open('{'.$host.'/pop3/novalidate-cert:'.$port.'}INBOX', $username , $passwd);
				break;
			case 'pop3s':
				if (!isset($port)) $port = 995;
				$mailauth = imap_open('{'.$host.'/pop3/ssl/novalidate-cert:'.$port.'}INBOX', $username , $passwd);
				break;
		}

		if (!$mailauth)
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
		unset($old_passwd, $new_passwd, $account_id);	// not used but required by function sigature

		return False;
	}
}
