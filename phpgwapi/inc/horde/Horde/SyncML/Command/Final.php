<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * The Horde_SyncML_Command_Final class.
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
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Final extends Horde_SyncML_Command {

    /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Final';

    function output($currentCmdID, &$output)
    {
        $state = $_SESSION['SyncML.state'];

        $attrs = array();
        $output->startElement($state->getURI(), 'Final', $attrs);

        $output->endElement($state->getURI(), 'Final');

        return $currentCmdID;
    }

}
