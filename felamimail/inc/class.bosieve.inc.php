<?php
/**
 * EGroupware EMail - timed vacation
 *
 * @link http://www.stylite.de
 * @package felamimail
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.uisieve.inc.php,v 1.24 2005/11/30 08:29:45 ralfbecker Exp $
 */

/**
 * Class containing callback to set/reset vacation notice in future (timed vacation)
 *
 * Rest of class is moved to emailadmin_sieve and accessible via incomming server object (IMAP).
 */
class bosieve
{
	/**
	 * Callback for the async job to enable/disable the vacation message
	 *
	 * @param array $_vacation
	 */
	function async_vacation($_vacation)
	{
		if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.'('.print_r($_vacation,true).')');
		// unset the fm_preferences session object, to force the reload/rebuild
		$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize(array()));
		$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));

		$_restoreSession = false; // as in async, each call may be for a different user
		$bopreferences    = CreateObject('felamimail.bopreferences',$_restoreSession);
		$mailPreferences  = $bopreferences->getPreferences();
		$icServer = $mailPreferences->getIncomingServer(0);
		if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.'->LoginName:'.$icServer->loginName);
		//error_log(__METHOD__.__LINE__.array2string($_vacation));
		try
		{
			$ret = $icServer->setVacationUser($icServer->loginName,$_vacation['scriptName'],$_vacation);
		}
		catch (Exception $e) {
			error_log(__METHOD__.'::'.__LINE__.' call for '.$icServer->loginName.','.$_vacation['scriptName'].' failed for reason:'.$e->getMessage());
			$ret = false;
		}
		if ($ret) $icServer->setAsyncJob($_vacation);
		if ($ret===false) $icServer->setAsyncJob($_vacation,null,true); //reschedule
		return $ret;
	}

}
