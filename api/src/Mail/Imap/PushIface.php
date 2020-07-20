<?php
/**
 * EGroupware Api: Push Interface for IMAP
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @author EGroupware GmbH <info@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

/**
 * This class holds all information about the imap connection.
 * This is the base class for all other imap classes.
 *
 * Also proxies Sieve calls to Mail\Sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 */
interface PushIface
{
	/**
	 * Check if push is available / konfigured for given server
	 *
	 * @return bool
	 */
	function pushAvailable();

	/**
	 * Enable push notifictions for current connection and given account_id
	 *
	 * @param int $account_id =null 0=everyone on the instance
	 * @return bool true on success, false on failure
	 */
	function enablePush($account_id=null);
}