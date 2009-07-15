<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * The SyncML_Command_Results class provides a SyncML implementation of the
 * Results command as defined in SyncML Representation Protocol, version 1.1,
 * section 5.5.12.
 *
 * The Results command is used to return the results of a Search or Get
 * command. Currently SyncML_Command_Results behaves the same as
 * SyncML_Command_Put. The only results we get is the same DevInf as for the
 * Put command.
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
include_once 'Horde/SyncML/Command/Put.php';

class Horde_SyncML_Command_Results extends Horde_SyncML_Command_Put {

    /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Results';

}
