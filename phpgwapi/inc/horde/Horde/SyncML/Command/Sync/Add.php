<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/Command/Sync/SyncElement.php';

class Horde_SyncML_Command_Sync_Add extends Horde_SyncML_Command_Sync_SyncElement {

    var $_status = RESPONSE_ITEM_ADDED;

    function output($currentCmdID, &$output)
    {
        $status = new Horde_SyncML_Command_Status($this->_status, 'Add');
        $status->setCmdRef($this->_cmdID);

        if (!empty($this->_items)) {
            $status->setSyncItems($this->_items);
        }

        return $status->output($currentCmdID, $output);
    }

}
