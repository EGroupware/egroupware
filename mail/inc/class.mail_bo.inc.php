<?php
/**
 * EGroupware - Mail - worker class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-2014 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail worker class
 *  -provides backend functionality for all classes in Mail
 *  -provides classes that may be used by other apps too
 *
 * @link https://github.com/horde/horde/blob/master/imp/lib/Contents.php
 */
class mail_bo extends emailadmin_imapbase
{

	/**
	 * Hook stuff
	 */

	/**
	 * hook to add account
	 *
	 * this function is a wrapper function for emailadmin
	 *
	 * @param _hookValues contains the hook values as array
	 * @return nothing
	 */
	function addAccount($_hookValues)
	{
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();

/*
		if(($icServer instanceof defaultimap)) {
			// if not connected, try opening an admin connection
			if (!$icServer->_connected) $this->openConnection($this->profileID,true);
			$icServer->addAccount($_hookValues);
			if ($icServer->_connected) $this->closeConnection(); // close connection afterwards
		}
		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->addAccount($_hookValues);
		}
*/
	}

	/**
	 * hook to delete account
	 *
	 * this function is a wrapper function for emailadmin
	 *
	 * @param _hookValues contains the hook values as array
	 * @return nothing
	 */
	function deleteAccount($_hookValues)
	{
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();
/*
		if(($icServer instanceof defaultimap)) {
			//try to connect with admin rights, when not connected
			if (!$icServer->_connected) $this->openConnection($this->profileID,true);
			$icServer->deleteAccount($_hookValues);
			if ($icServer->_connected) $this->closeConnection(); // close connection
		}

		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->deleteAccount($_hookValues);
		}
*/
	}

	/**
	 * hook to update account
	 *
	 * this function is a wrapper function for emailadmin
	 *
	 * @param _hookValues contains the hook values as array
	 * @return nothing
	 */
	function updateAccount($_hookValues)
	{
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();
/*
		if(($icServer instanceof defaultimap)) {
			$icServer->updateAccount($_hookValues);
		}

		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->updateAccount($_hookValues);
		}
*/
	}
}
