<?php
/**
 * EGroupware EMailAdmin: Interface for IMAP support
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('IMAP_NAMESPACE_PERSONAL', 'personal');
define('IMAP_NAMESPACE_OTHERS'	, 'others');
define('IMAP_NAMESPACE_SHARED'	, 'shared');
define('IMAP_NAMESPACE_ALL'	, 'all');

/**
 * This class holds all information about the imap connection.
 * This is the base class for all other imap classes.
 *
 * Also proxies Sieve calls to emailadmin_sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 */
interface defaultimap
{
	/**
	 * adds a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function addAccount($_hookValues);

	/**
	 * updates a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function updateAccount($_hookValues);

	/**
	 * deletes a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function deleteAccount($_hookValues);

	/**
	 * converts a foldername from current system charset to UTF7
	 *
	 * @param string $_folderName
	 * @return string the encoded foldername
	 */
	function encodeFolderName($_folderName);

	/**
	 * returns the supported capabilities of the imap server
	 * return false if the imap server does not support capabilities
	 *
	 * @return array the supported capabilites
	 */
	function getCapabilities();

	/**
	 * return the delimiter used by the current imap server
	 *
	 * @return string the delimimiter
	 */
	function getDelimiter();

	/**
	 * get the effective Username for the Mailbox, as it is depending on the loginType
	 * @param string $_username
	 * @return string the effective username to be used to access the Mailbox
	 */
	function getMailBoxUserName($_username);

	/**
	 * Create mailbox string from given mailbox-name and user-name
	 *
	 * @param string $_folderName=''
	 * @return string utf-7 encoded (done in getMailboxName)
	 */
	function getUserMailboxString($_username, $_folderName='');

	/**
	 * get list of namespaces
	 *
	 * @return array with keys 'personal', 'shared' and 'others' and value array with values for keys 'name' and 'delimiter'
	 */
	function getNameSpaceArray();
	/**
	 * return the quota for another user
	 * used by admin connections only
	 *
	 * @param string $_username
	 * @param string $_what - what to retrieve either QMAX, USED or ALL is supported
	 * @return mixed the quota for specified user (by what) or array with all available Quota Information, or false
	 */
	function getQuotaByUser($_username, $_what='QMAX');

	/**
	 * returns information about a user
	 *
	 * Only a stub, as admin connection requires, which is only supported for Cyrus
	 *
	 * @param string $_username
	 * @return array userdata
	 */
	function getUserData($_username);

	/**
	 * opens a connection to a imap server
	 *
	 * @param bool $_adminConnection create admin connection if true
	 * @param int $_timeout=null timeout in secs, if none given fmail pref or default of 20 is used
	 * @throws Exception on error
	 */
	function openConnection($_adminConnection=false, $_timeout=null);

	/**
	 * set userdata
	 *
	 * @param string $_username username of the user
	 * @param int $_quota quota in bytes
	 * @return bool true on success, false on failure
	 */
	function setUserData($_username, $_quota);

	/**
	 * check if imap server supports given capability
	 *
	 * @param string $_capability the capability to check for
	 * @return bool true if capability is supported, false if not
	 */
	function supportsCapability($_capability);

	/**
	 * Set vacation message for given user
	 *
	 * @param int|string $_euser nummeric account_id or imap username
	 * @param array $_vacation
	 * @param string $_scriptName=null
	 * @return boolean
	 */
	public function setVacationUser($_euser, array $_vacation, $_scriptName=null);

	/**
	 * Get vacation message for given user
	 *
	 * @param int|string $_euser nummeric account_id or imap username
	 * @param string $_scriptName=null
	 * @throws Exception on connection error or authentication failure
	 * @return array
	 */
	public function getVacationUser($_euser, $_scriptName=null);
}
