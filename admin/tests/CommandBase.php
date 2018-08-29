<?php
/**
 * EGroupware Api: Application test base class
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage test
 * @author Nathan Gray
 * @copyright (c) 2016 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');	// Application test base


/**
 * Base class for admin command tests
 *
 * Extend this class to test one command.
 */
abstract class CommandBase extends EGroupware\Api\LoggedInTest
{

	/**
	 * Get how many entries in the admin command history log so we can see if
	 * a command made a log entry.
	 */
	protected function get_log_count()
	{
		return count(admin_cmd::search(array()));
	}
}