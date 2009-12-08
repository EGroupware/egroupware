<?php
/**
 * eGroupWare EmailAdmin - Postfix with inetOrgPerson schema
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package emailadmin
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @author Lars Kneschke <l.kneschke@metaways.de>
 * @version $Id$
 */

include_once(EGW_SERVER_ROOT.'/emailadmin/inc/class.defaultsmtp.inc.php');

/**
 * Postfix with inetOrgPerson schema (default for eGW accounts)
 *
 * Stores the aliases as aditional mail Attributes. The primary mail address is the first one.
 * 
 * At the moment we support no forwarding with this schema and no disabling of an account
 */
class postfixinetorgperson extends defaultsmtp
{
	/**
	 * Add an account, nothing needed as we dont have to add an additional schema
	 *
	 * @param array $_hookValues
	 */
	function addAccount($_hookValues)
	{
	}

	/**
	 * Get all email addresses of an account
	 *
	 * @param string $_accountName
	 * @return array
	 */
	function getAccountEmailAddress($_accountName)
	{
		$emailAddresses	= array();
		$ds = $GLOBALS['egw']->common->ldapConnect();
		$sri = @ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'],"(&(uid=$_accountName)(objectclass=posixAccount))",array('dn','mail'));
		
		if ($sri && ($allValues = ldap_get_entries($ds, $sri)) && is_array($allValues[0]['mail']))
		{
			$realName = trim($GLOBALS['egw_info']['user']['firstname'] . (!empty($GLOBALS['egw_info']['user']['firstname']) ? ' ' : '') . $GLOBALS['egw_info']['user']['lastname']);
			foreach($allValues[0]['mail'] as $n => $mail)
			{
				if (!is_numeric($n)) continue;

				$emailAddresses[] = array(
					'name'		=> $realName,
					'address'	=> $mail,
					'type'		=> !$n ? 'default' : 'alternate',
				);
			}
		}
		//echo "<p>postfixinetorgperson::getAccountEmail($_accountName)"; _debug_array($emailAddresses);
		return $emailAddresses;
	}

	/**
	 * Get the data of a given user
	 * 
	 * @param int $_uidnumber numerical user-id
	 * @return array
	 */
	function getUserData($_uidnumber) 
	{
		$userdata = array();
		$ldap = $GLOBALS['egw']->common->ldapConnect();
		
		if (($sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],'uidnumber='.(int)$_uidnumber,array('mail'))))
		{
			$allValues = ldap_get_entries($ldap, $sri);
			if ($allValues['count'] > 0)
			{
				unset($allValues[0]['mail']['count']);
				$userdata = array(
					'mailLocalAddress'      => array_shift($allValues[0]['mail']),
					'mailAlternateAddress'  => $allValues[0]['mail'],
					'accountStatus'		    => 'active',
					'mailForwardingAddress' => array(),
//					'deliveryMode'          => ,
				);
			}
		}
		//echo "<p>postfixinetorgperson::getUserData($_uidnumber) = ".print_r($userdata,true)."</p>\n";
		return $userdata;
	}
	
	/**
	 * Set the data of a given user
	 * 
	 * @param int $_uidnumber numerical user-id
	 * @param array $_mailAlternateAddress
	 * @param array $_mailForwardingAddress
	 * @param string $_deliveryMode
	 * @param string $_accountStatus
	 * @param string $_mailLocalAddress
	 * @return boolean
	 */
	function setUserData($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress, $_deliveryMode, $_accountStatus, $_mailLocalAddress) 
	{
		$filter = "uidnumber=$_uidnumber";

		$ldap = $GLOBALS['egw']->common->ldapConnect();

		if (($sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],'uidnumber='.(int)$_uidnumber,array('dn')))) 
		{
			$allValues 	= ldap_get_entries($ldap, $sri);
			$accountDN 	= $allValues[0]['dn'];

			sort($_mailAlternateAddress);
			$newData['mail'] = array_values(array_unique(array_merge(array($_mailLocalAddress),$_mailAlternateAddress)));

//			sort($_mailForwardingAddress);
//			$newData['forwards'] = (array)$_mailForwardingAddress;
//			$newData['active'] = $_accountStatus;
//			$newData['mode'] = $_deliveryMode;

			//echo "<p>postfixinetorgperson::setUserData($_uidnumber,...) setting $accountDN to ".print_r($newData,true)."</p>\n";
			return ldap_mod_replace($ldap, $accountDN, $newData);
		}
		return false;
	}
	
	/**
	 * Saves the forwarding information
	 *
	 * @param int $_accountID
	 * @param string $_forwardingAddress
	 * @param string $_keepLocalCopy 'yes'
	 */
	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
	{
	}
}
