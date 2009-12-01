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
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';
include_once 'Horde/SyncML/Command/Sync/SyncElement.php';
include_once 'Horde/SyncML/Sync/TwoWaySync.php';
include_once 'Horde/SyncML/Sync/SlowSync.php';
include_once 'Horde/SyncML/Sync/OneWayFromServerSync.php';
include_once 'Horde/SyncML/Sync/OneWayFromClientSync.php';
include_once 'Horde/SyncML/Sync/RefreshFromServerSync.php';
include_once 'Horde/SyncML/Sync/RefreshFromClientSync.php';

class Horde_SyncML_Command_Sync extends Horde_SyncML_Command {

   /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Sync';

    /**
     * Source database of the <Sync> command.
     *
     * @var string
     */
    var $_sourceURI;

    /**
     * Target database of the <Sync> command.
     *
     * @var string
     */
    var $_targetURI;

    /**
     * Optional parameter for the Target.
     *
     * @var string
     */
    var $_targetURIParameters;

    /**
     * SyncML_SyncElement object for the currently parsed sync command.
     *
     * @var SyncML_SyncElement
     */
    var $_curItem;

    /**
     * List of all SyncML_SyncElement objects that have parsed.
     *
     * @var array
     */
    var $_syncElements = array();

    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];

        Horde::logMessage('SyncML: $this->_targetURI = ' . $this->_targetURI, __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $status = new Horde_SyncML_Command_Status(RESPONSE_OK, 'Sync');

        $status->setCmdRef($this->_cmdID);

        if ($this->_targetURI != null) {
            $status->setTargetRef((isset($this->_targetURIParameters) ? $this->_targetURI.'?/'.$this->_targetURIParameters : $this->_targetURI));
        }

        if ($this->_sourceURI != null) {
            $status->setSourceRef($this->_sourceURI);
        }

        $currentCmdID = $status->output($currentCmdID, $output);

        if ($this->_targetURI != "configuration" && // Fix Funambol issue
        	($sync = &$state->getSync($this->_targetURI))) {
            $currentCmdID = $sync->startSync($currentCmdID, $output);

            foreach ($this->_syncElements as $element) {
                $currentCmdID = $sync->nextSyncCommand($currentCmdID, $element, $output);
            }
        }

	return $currentCmdID;
    }


    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch (count($this->_stack)) {
        case 2:
            if ($element == 'Replace' ||
                $element == 'Add' ||
                $element == 'Delete') {
		Horde::logMessage("SyncML: sync element $element found", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                $this->_curItem = &Horde_SyncML_Command_Sync_SyncElement::factory($element);
            }
            break;
        }
        if (isset($this->_curItem)) {
            $this->_curItem->startElement($uri, $element, $attrs);
        }
    }


    // We create a seperate Sync Element for the Sync Data sent
    // from the Server to the client as we want to process the
    // client sync information before.

    function syncToClient($currentCmdID, &$output)
    {
	    Horde::logMessage('SyncML: starting sync to client',
		    __FILE__, __LINE__, PEAR_LOG_DEBUG);

	    $state = &$_SESSION['SyncML.state'];

	    if ($state->getSyncStatus() >= CLIENT_SYNC_FINNISHED
		    && $state->getSyncStatus() < SERVER_SYNC_FINNISHED)
	    {
		    $deviceInfo = $state->getClientDeviceInfo();
		    if (($targets = $state->getTargets())) {
			    foreach ($targets as $target)
				{
				    $sync = &$state->getSync($target);
				    Horde::logMessage('SyncML[' . session_id() . ']: sync alerttype ' .
				    	$sync->_syncType . ' found for target ' . $target,
				    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
				    if ($sync->_syncType == ALERT_ONE_WAY_FROM_CLIENT ||
					    $sync->_syncType == ALERT_REFRESH_FROM_CLIENT) {
					    Horde::logMessage('SyncML[' . session_id() .
							']: From client Sync, no sync of ' . $target .
							' to client', __FILE__, __LINE__, PEAR_LOG_DEBUG);
					    $state->clearSync($target);

				    } elseif ($state->getSyncStatus() >= CLIENT_SYNC_ACKNOWLEDGED) {

					    Horde::logMessage("SyncML: starting sync to client $target",
					    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
					    $attrs = array();

					    $state->setSyncStatus(SERVER_SYNC_DATA_PENDING);

					    $output->startElement($state->getURI(), 'Sync', $attrs);
					    $output->startElement($state->getURI(), 'CmdID', $attrs);
					    $output->characters($currentCmdID);
					    $currentCmdID++;
					    $output->endElement($state->getURI(), 'CmdID');

					    $output->startElement($state->getURI(), 'Target', $attrs);
					    $output->startElement($state->getURI(), 'LocURI', $attrs);
					    $chars = $sync->_sourceLocURI;
					    $output->characters($chars);
					    $output->endElement($state->getURI(), 'LocURI');
					    $output->endElement($state->getURI(), 'Target');

					    $output->startElement($state->getURI(), 'Source', $attrs);
					    $output->startElement($state->getURI(), 'LocURI', $attrs);
					    $chars = (isset($sync->_targetLocURIParameters) ? $sync->_targetLocURI.'?/'.$sync->_targetLocURIParameters : $sync->_targetLocURI);
					    $output->characters($chars);
					    $output->endElement($state->getURI(), 'LocURI');
					    $output->endElement($state->getURI(), 'Source');

					    if(!$sync->_syncDataLoaded)
					    {
						    $numberOfItems = $sync->loadData();
						    if($deviceInfo['supportNumberOfChanges'])
						    {
							    $output->startElement($state->getURI(), 'NumberOfChanges', $attrs);
							    $output->characters($numberOfItems);
							    $output->endElement($state->getURI(), 'NumberOfChanges');
						    }
					    }

					    $currentCmdID = $sync->endSync($currentCmdID, $output);

					    $output->endElement($state->getURI(), 'Sync');

					    if (isset($state->curSyncItem) ||
							    $state->getNumberOfElements() === false) {
						    break;
					    }
				    } else {
					    Horde::logMessage("SyncML: Waiting for client ACKNOWLEDGE for $target",
					    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
				    }
				}
		    }

		    // no syncs left
		    if ($state->getTargets() === false &&
			    !isset($state->curSyncItem)) {
			    $state->setSyncStatus(SERVER_SYNC_FINNISHED);
		    }
		    Horde::logMessage('SyncML: syncStatus(syncToClient) = ' .
		    	$state->getSyncStatus(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
	    }
	    return $currentCmdID;
    }

    function endElement($uri, $element)
    {
        if (isset($this->_curItem)) {
            $this->_curItem->endElement($uri, $element);
        }

        switch (count($this->_stack)) {
        case 2:
            if ($element == 'Replace' ||
                $element == 'Add' ||
                $element == 'Delete') {
                $this->_syncElements[] = &$this->_curItem;
                unset($this->_curItem);
            }
            break;

        case 3:
            if ($element == 'LocURI' && !isset($this->_curItem)) {
                if ($this->_stack[1] == 'Source') {
                    $this->_sourceURI = trim($this->_chars);
                } elseif ($this->_stack[1] == 'Target') {
                    $targetURIData = explode('?/',trim($this->_chars));

                    $this->_targetURI = $targetURIData[0];

                    if (isset($targetURIData[1])) {
                        $this->_targetURIParameters = $targetURIData[1];
                    }
                }
            }
            break;
        }

        parent::endElement($uri, $element);

    }

    function characters($str)
    {
        if (isset($this->_curItem)) {
            $this->_curItem->characters($str);
        } else {
            if (isset($this->_chars)) {
                $this->_chars .= $str;
            } else {
                $this->_chars = $str;
            }
        }
    }

}
