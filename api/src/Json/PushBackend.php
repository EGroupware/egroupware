<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Json;

/**
 * Interface for push backends
 */
interface PushBackend
{
	/**
	 * Adds any type of data to the message
	 *
	 * @param int $account_id =null account_id to push message too or
	 *	null: for current session only or 0 for whole instance / broadcast
	 * @param string $key
	 * @param mixed $data
	 * @throws Exception\NotOnline if $account_id is not online
	 */
	public function addGeneric($account_id, $key, $data);

	/**
	 * Get users online / connected to push-server
	 *
	 * @return array of integer account_id currently available for push
	 */
	public function online();
}
