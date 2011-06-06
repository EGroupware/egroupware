<?php
/**
 * EGroupware EMail - timed vacation
 *
 * @link http://www.stylite.de
 * @package felamimail
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
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
		$ret = $icServer->setVacationUser($icServer->loginName,$_vacation['scriptName'],$_vacation);
		if ($ret) $icServer->setAsyncJob($_vacation);
	}

}
