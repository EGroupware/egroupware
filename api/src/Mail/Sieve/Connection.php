<?php
/**
 * EGroupware Api: Connection interface for Sieve scripts
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Sieve;

use EGroupware\Api\Translation;
use Horde;
use Horde\ManageSieve\Exception;

/**
 * Connection interface for Sieve scripts which Horde\ManageSieve and Sieve\Jmap implements
 *
 * Constructor and setters will throw exceptions for connection, login or other errors.
 *
 * retrieveRules and getters will not throw an exception if there's no script currently.
 *
 * Most methods incl. constructor accepts a script-name, but by default the current active script is used
 * and if there's no script Sieve::DEFAULT_SCRIPT_NAME.
 */
interface Connection
{
	/**
	 * Constructor
	 *
	 * @param array|Imap $params =array()
	 */
	function __construct($params=[]);

	/**
	 * Returns the list of scripts on the server.
	 *
	 * @throws \Exception
	 * @return array  An array with the list of scripts in the first element
	 *                and the active script in the second element.
	 */
	public function listScripts();

	/**
	 * Returns the active script.
	 *
	 * @return string  The active scriptname.
	 */
	public function getActive();

	/**
	 * Retrieves a script.
	 *
	 * @param string $scriptname The name of the script to be retrieved.
	 *
	 * @throws \Exception
	 * @return string  The script.
	 */
	public function getScript($scriptname);

	/**
	 * Adds a script to the server.
	 *
	 * @param string  $scriptname Name of the script.
	 * @param string  $script     The script content.
	 * @param boolean $makeactive Whether to make this the active script.
	 *
	 * @throws \Exception
	 */
	public function installScript($scriptname, $script, $makeactive = false);

	/**
	 * Removes a script from the server.
	 *
	 * @param string $scriptname Name of the script.
	 *
	 * @throws \Exception
	 */
	public function removeScript($scriptname);

	/**
	 * Checks if the server has space to store the script by the server.
	 *
	 * @param string  $scriptname The name of the script to mark as active.
	 * @param integer $size       The size of the script.
	 *
	 * @throws \Horde\ManageSieve\Exception
	 * @return boolean  True if there is space.
	 */
	public function hasSpace($scriptname, $size);

	/**
	 * Returns the list of extensions the server supports.
	 *
	 * @throws \Exception
	 * @return array  List of extensions.
	 */
	public function getExtensions();

	/**
	 * Returns whether the server supports an extension.
	 *
	 * @param string $extension The extension to check.
	 *
	 * @throws \Exception
	 * @return boolean  Whether the extension is supported.
	 */
	public function hasExtension($extension);
}