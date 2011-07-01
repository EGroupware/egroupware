<?php
/***************************************************************************\
* EGroupWare - EMailAdmin SMTP via Plesk                                    *
* http://www.egroupware.org                                                 *
* Written and (c) 2006 by RalfBecker-AT-outdoor-training.de                 *
* ------------------------------------------------------------------------- *
* emailadmin plugin for plesk:                                              *
* - tested with Plesk7.5 under Linux, but should work with other plesk      *
*   versions and Windows too as it uses plesks cli (command line interface) *
* - this plugin ONLY works if you have root access to the webserver !!!     *
* - configuration instructions are in the class.pleskimap.inc.php           *
* => as with the "LDAP, Postfix & Cyrus" plugin the plesk one creates mail  *
*    users and manages passwords, aliases, forwards and quota from within   *
*    eGroupWare - no need to additionally visit the plesk interface anymore *
* ------------------------------------------------------------------------- *
* This program is free software; you can redistribute it and/or modify it   *
* under the terms of the GNU General Public License as published by the     *
* Free Software Foundation; either version 2 of the License, or (at your    *
* option) any later version.                                                *
\***************************************************************************/
/* $Id$ */

include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultsmtp.inc.php");
include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.pleskimap.inc.php");

class smtpplesk extends defaultsmtp
{
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * @var string/boolean $error string with last error-message or false
	 */
	var $error = false;

	/**
	 * call plesk's mail command line interface
	 *
	 * The actual code is in the pleskimap class, to not double it.
	 *
	 * @param string $action 'info', 'create', 'update' or 'remove'
	 * @param string/int $account account_lid or numerical account_id
	 * @param string $password=null string with password or null to not change
	 * @param array $aliases=null array with aliases or null to not change the aliases
	 * @param string/boolean $forward=null email address to forward, false to not forward or null to not change
	 * @param boolean $keepLocalCopy=null if forwarding keep a local copy or not, null = dont change
	 * @param int $quota_kb=null mailbox quota in kb
	 * @return boolean/array array with returned values or false otherwise, error-message in $this->error
	 */
	function plesk_mail($action,$account,$password=null,$aliases=null,$forward=null,$keepLocalCopy=null,$quota_kb=null)
	{
		static $plesk;
		if (!is_object($plesk))
		{
			$plesk = new pleskimap(null);
			$this->error =& $plesk->error;
		}
		return $plesk->plesk_mail($action,$account,$password,$aliases,$forward,$keepLocalCopy,$quota_kb);
	}

	function addAccount($hookValues)
	{
		// account is added via pleskimap::addAccount();
	}
	
	/**
	 * Returns the email address of the current user
	 *
	 * @param string/int $accountName account-id or -lis (name)
	 * @return array of arrays with keys name, address and type={default|alternate}
	 */
	function getAccountEmailAddress()
	{
		//echo "<p>smtpplesk::getAccountEmailAddress()</p>\n";
		
		return array(array(
			'name'		=> $GLOBALS['egw_info']['user']['fullname'],
			'address'	=> $GLOBALS['egw_info']['user']['email'],
			'type'		=> 'default'
		));
	}

	/**
	 * Save SMTP forwarding address
	 *
	 * @param int $accountID user-id
	 * @param string $forwardingAddress email to forward to
	 * @param string $keepLocalCopy 'yes' or something else
	 */
	function saveSMTPForwarding($accountID, $forwardingAddress, $keepLocalCopy)
	{
		//echo "<p>smtpplesk::saveSMTPForwarding('$accountID','$forwardingAddress','$keepLocalCopy')</p>\n";

		return $this->plesk_mail('update',$accountID,null,null,array($forwardingAddress),$keepLocalCopy == 'yes');
	}
}
