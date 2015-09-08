<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * The Horde_SyncML_Map class provides a SyncML implementation of
 * the Map command as defined in SyncML Representation Protocol,
 * version 1.0.1 5.5.8.
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Map extends Horde_SyncML_Command {

    /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Map';

   /**
     * Source database of the Map command.
     *
     * @var string
     */
    var $_sourceLocURI;

    /**
     * Target database of the Map command.
     *
     * @var string
     */
    var $_targetLocURI;

    /**
     * Recipient map item specifier.
     *
     * @var string
     */
    var $_mapTarget;

    /**
     * Originator map item specifier.
     *
     * @var string
     */
    var $_mapSource;

    function output($currentCmdID, &$output)
    {
        $attrs = array();

        $state = $_SESSION['SyncML.state'];

        $status = new Horde_SyncML_Command_Status($state->isAuthorized() ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS, 'Map');
        $status->setCmdRef($this->_cmdID);
        if ($this->_sourceLocURI != null) {
            $status->setSourceRef($this->_sourceLocURI);
        }
        if ($this->_targetLocURI != null) {
            $status->setTargetRef($this->_targetLocURI);
        }

        $currentCmdID = $status->output($currentCmdID, $output);

        return $currentCmdID;
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        if (count($this->_stack) == 2 &&
            $element == 'MapItem') {
            unset($this->_mapTarget);
            unset($this->_mapSource);
        }
    }

    function endElement($uri, $element)
    {

        $state = &$_SESSION['SyncML.state'];

        switch (count($this->_stack)) {
        case 2:
            if ($element == 'MapItem') {
                $sync = $state->getSync($this->_targetLocURI);
                if (!$state->isAuthorized()) {
                    Horde::logMessage('SyncML: Not Authorized in the middle of MapItem!', __FILE__, __LINE__, PEAR_LOG_ERR);
                } else {
                    Horde::logMessage("SyncML: creating Map for source=" .
                                      $this->_mapSource . " and target=" . $this->_mapTarget, __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    // Overwrite existing data by removing it first:
                    $ts = $state->getServerAnchorNext($this->_targetLocURI);
                    $r = $state->setUID($this->_targetLocURI, $this->_mapSource, $this->_mapTarget, $ts);
                    if (is_a($r, 'PEAR_Error')) {
                        Horde::logMessage('SyncML: PEAR Error: ' . $r->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
                        return false;
                    }
                }
            }
            break;

        case 3:
            if ($element == 'LocURI') {
                if ($this->_stack[1] == 'Source') {
                    $this->_sourceLocURI = trim($this->_chars);
                } elseif ($this->_stack[1] == 'Target') {
                    $targetLocURIData = explode('?/',trim($this->_chars));
		    		$this->_targetLocURI = $targetLocURIData[0];
                }
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                if ($this->_stack[2] == 'Source') {
                    $this->_mapSource = trim($this->_chars);
                } elseif ($this->_stack[2] == 'Target') {
                    $this->_mapTarget = trim($this->_chars);
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

}
