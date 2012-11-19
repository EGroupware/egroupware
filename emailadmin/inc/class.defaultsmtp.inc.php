<?php
/***************************************************************************\
* EGroupWare - EMailAdmin                                                   *
* http://www.egroupware.org                                                 *
* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
* -------------------------------------------------                         *
* This program is free software; you can redistribute it and/or modify it   *
* under the terms of the GNU General Public License as published by the     *
* Free Software Foundation; either version 2 of the License, or (at your    *
* option) any later version.                                                *
\***************************************************************************/
/* $Id$ */

class defaultsmtp
{
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default';

	/**
	 * SmtpServerId
	 *
	 * @var int
	 */
	var $SmtpServerId;

	var $smtpAuth = false;

	var $editForwardingAddress = false;

	var $host;

	var $port;

	var $username;

	var $password;

	var $defaultDomain;

	// the constructor
	function defaultsmtp($defaultDomain=null)
	{
		$this->defaultDomain = $defaultDomain ? $defaultDomain : $GLOBALS['egw_info']['server']['mail_suffix'];
	}

	// add a account
	function addAccount($_hookValues)
	{
		return true;
	}

	// delete a account
	function deleteAccount($_hookValues)
	{
		return true;
	}

	function getAccountEmailAddress($_accountName)
	{
		$accountID = $GLOBALS['egw']->accounts->name2id($_accountName);
		$emailAddress = $GLOBALS['egw']->accounts->id2name($accountID,'account_email');
		if(empty($emailAddress))
			$emailAddress = $_accountName.'@'.$this->defaultDomain;

		$realName = trim($GLOBALS['egw_info']['user']['account_firstname'] . (!empty($GLOBALS['egw_info']['user']['account_firstname']) ? ' ' : '') . $GLOBALS['egw_info']['user']['account_lastname']);

		return array(
			array(
				'name'		=> $realName,
				'address'	=> $emailAddress,
				'type'		=> 'default'
			)
		);
	}

	function getUserData($_uidnumber) {
		$userData = array();

		return $userData;
	}

	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy) {
		return true;
	}

	function setUserData($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress, $_deliveryMode) {
		return true;
	}

	// update a account
	function updateAccount($_hookValues) {
		return true;
	}

	/**
	 * Build mailbox address for given account and mail_addr_type
	 *
	 * If $account is an array (with values for keys account_(id|lid|email), it does NOT call accounts class
	 *
	 * @param int|array $account account_id or whole account array with values for keys
	 * @param string $domain=null domain, default use $this->defaultDomain
	 * @param string $mail_login_type=null standard(uid), vmailmgr(uid@domain), email or uidNumber,
	 * 	default use $GLOBALS['egw_info']['server']['mail_login_type']
	 * @return string
	 */
	/*static*/ public function mailbox_addr($account,$domain=null,$mail_login_type=null)
	{
		if (is_null($domain)) $domain = $this->defaultDomain;
		if (is_null($mail_login_type)) $mail_login_type = $GLOBALS['egw_info']['server']['mail_login_type'];

		switch($mail_login_type)
		{
			case 'email':
				$mbox = is_array($account) ? $account['account_email'] : $GLOBALS['egw']->accounts->id2name($account,'account_email');
				break;

			case 'uidNumber':
				if (is_array($account)) $account = $account['account_id'];
				$mbox = 'u'.$account.'@'.$domain;
				break;

			case 'standard':
				$mbox = is_array($account) ? $account['account_lid'] : $GLOBALS['egw']->accounts->id2name($account);
				break;

			case 'vmailmgr':
			default:
				$mbox = is_array($account) ? $account['account_lid'] : $GLOBALS['egw']->accounts->id2name($account);
				$mbox .= '@'.$domain;
				break;
		}
		//error_log(__METHOD__."(".array2string($account).",'$domain','$mail_login_type') = '$mbox'");

		return $mbox;
	}
}
