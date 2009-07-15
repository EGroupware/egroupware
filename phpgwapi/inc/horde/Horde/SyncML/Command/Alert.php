<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * The Horde_SyncML_Alert class provides a SyncML implementation of
 * the Alert command as defined in SyncML Representation Protocol,
 * version 1.1 5.5.2.
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
include_once 'Horde/SyncML/State_egw.php';
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Alert extends Horde_SyncML_Command {

    /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Alert';

    /**
     * The alert type. Should be one of the ALERT_* constants.
     *
     * @var integer
     */
    var $_alert;

    /**
     * Source database of the Alert command.
     *
     * @var string
     */
    var $_sourceLocURI;

    /**
     * Target database of the Alert command.
     *
     * @var string
     */
    var $_targetLocURI;

    /**
     * Optional parameter for the Target database.
     *
     * @var string
     */
    var $_targetLocURIParameters;

    /**
     * The current time this synchronization happens, from the <Meta><Next>
     * element.
     *
     * @var string
     */
    var $_metaAnchorNext;

    /**
     * The last time when synchronization happened, from the <Meta><Last>
     * element.
     *
     * @var integer
     */
    var $_metaAnchorLast;

    /**
     * The filter expression the client provided
     *  (e.g. the time range for calendar synchronization)
     *
     * @var string
     */
    var $_filterExpression = '';


    /**
     * Creates a new instance of Alert.
     */
    function Horde_SyncML_Command_Alert($alert = null)
    {
        if ($alert != null) {
            $this->_alert = $alert;
        }
    }

    function output($currentCmdID, &$output)
    {
        global $registry;

        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        // Handle unauthorized first.
        if (!$state->isAuthorized()) {
            $status = new Horde_SyncML_Command_Status(RESPONSE_INVALID_CREDENTIALS, 'Alert');
            $status->setCmdRef($this->_cmdID);
            $currentCmdID = $status->output($currentCmdID, $output);
            return $currentCmdID;
        }

        $type = $this->_targetLocURI;

        $clientAnchorNext = $this->_metaAnchorNext;

        if ($this->_alert == ALERT_TWO_WAY ||
            $this->_alert == ALERT_ONE_WAY_FROM_CLIENT ||
            $this->_alert == ALERT_ONE_WAY_FROM_SERVER) {
            // Check if we have information about previous sync.
            $info = $state->getSyncSummary($this->_targetLocURI);
            if (is_a($info, 'DataTreeObject')) {
                $x = $info->get('ClientAnchor');
                $clientlast = $x[$type];
                $x = $info->get('ServerAnchor');
                $serverAnchorLast = $x[$type];
            } elseif (is_array($info)) {
                $clientlast = $info['ClientAnchor'];
                $serverAnchorLast = $info['ServerAnchor'];
            } else {
                $clientlast = false;
                $serverAnchorLast = 0;
            }
            $state->setServerAnchorLast($type, $serverAnchorLast);

            if ($clientlast !== false){
                // Info about previous successful sync sessions found.
                Horde::logMessage('SyncML: Previous sync found for target ' . $type
                    . '; client timestamp: ' . $clientlast,
                    __FILE__, __LINE__, PEAR_LOG_DEBUG);

                // Check if anchor sent from client matches our own stored
                // data.
                if ($clientlast == $this->_metaAnchorLast) {
                    // Last sync anchors matche, TwoWaySync will do.
                    $anchormatch = true;
                    Horde::logMessage('SyncML: Anchor timestamps match, TwoWaySync possible. Syncing data since '
                        . date('Y-m-d H:i:s', $serverAnchorLast),
                        __FILE__, __LINE__, PEAR_LOG_DEBUG);
                } else {
                    // Server and client have different anchors, enforce
                    // SlowSync/RefreshSync
                    Horde::logMessage('SyncML: Client requested sync with anchor timestamp '
                        . $this->_metaAnchorLast
                        . ' but server has recorded timestamp '
                        . $clientlast . '. Enforcing SlowSync',
                        __FILE__, __LINE__, PEAR_LOG_INFO);
                    $anchormatch = false;
                    $clientlast = 0;
                }
            } else {
                // No info about previous sync, use SlowSync or RefreshSync.
                Horde::logMessage('SyncML: No info about previous syncs found for device ' .
                    $state->getSourceURI() . ' and target ' . $type,
                    __FILE__, __LINE__, PEAR_LOG_DEBUG);
                $clientlast = 0;
                $serverAnchorLast = 0;
                $anchormatch = false;
            }
        } else {
            // SlowSync requested, no anchor check required.
            $anchormatch = true;
        }

        // Determine sync type and status response code.
        Horde::logMessage("SyncML: Alert " . $this->_alert, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        switch ($this->_alert) {
	case ALERT_NEXT_MESSAGE:
		$state->setAlert222Received(true);
	case ALERT_RESULT_ALERT:
	case ALERT_NO_END_OF_DATA:
            // Nothing to do on our side
            $status = new Horde_SyncML_Command_Status(RESPONSE_OK, 'Alert');
            $status->setCmdRef($this->_cmdID);
            if ($this->_sourceLocURI != null) {
                $status->setSourceRef($this->_sourceLocURI);
            }
            if ($this->_targetLocURI != null) {
                $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
            }
            if ($this->_alert == ALERT_NEXT_MESSAGE) {
                if ($this->_sourceLocURI != null) {
                    $status->setItemSourceLocURI($this->_sourceLocURI);
                }
                if ($this->_targetLocURI != null) {
                    $status->setItemTargetLocURI(isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI);
                }
            }
            $currentCmdID = $status->output($currentCmdID, $output);
            return $currentCmdID;
        case ALERT_TWO_WAY:
            if ($anchormatch) {
                $synctype = ALERT_TWO_WAY;
                $response = RESPONSE_OK;
            } else {
                $synctype = ALERT_SLOW_SYNC;
                $response = RESPONSE_REFRESH_REQUIRED;
            }
            break;

        case ALERT_SLOW_SYNC:
            $synctype = ALERT_SLOW_SYNC;
            $response = $anchormatch ? RESPONSE_OK : RESPONSE_REFRESH_REQUIRED;
            break;

        case ALERT_ONE_WAY_FROM_CLIENT:
            if ($anchormatch) {
                $synctype = ALERT_ONE_WAY_FROM_CLIENT;
                $response = RESPONSE_OK;
            } else {
                $synctype = ALERT_REFRESH_FROM_CLIENT;
                $response = RESPONSE_REFRESH_REQUIRED;
            }
            break;

        case ALERT_REFRESH_FROM_CLIENT:
            $synctype = ALERT_REFRESH_FROM_CLIENT;
            $response = $anchormatch ? RESPONSE_OK : RESPONSE_REFRESH_REQUIRED;

            // We will erase the current server content,
            // then we can add the client's contents.

            $hordeType = $state->getHordeType($this->_targetLocURI);

            $state->setTargetURI($this->_targetLocURI);
            $deletes = $state->getClientItems();
            if (is_array($deletes)) {
                foreach ($deletes as $delete) {
                    $registry->call($hordeType . '/delete', array($delete));
                }
                Horde::logMessage("SyncML: RefreshFromClient " . count($deletes) . " entries deleted for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
            }
            $anchormatch = false;
            break;

       case ALERT_ONE_WAY_FROM_SERVER:
            if ($anchormatch) {
                $synctype = ALERT_ONE_WAY_FROM_SERVER;
                $response = RESPONSE_OK;
            } else {
                $synctype = ALERT_REFRESH_FROM_SERVER;
                $response = RESPONSE_REFRESH_REQUIRED;
            }
            break;

        case ALERT_REFRESH_FROM_SERVER:
            $synctype = ALERT_REFRESH_FROM_SERVER;
            $response = $anchormatch ? RESPONSE_OK : RESPONSE_REFRESH_REQUIRED;
            break;

        case ALERT_RESUME:
            // @TODO: Suspend and Resume is not supported yet
            $synctype = ALERT_SLOW_SYNC;
            $response = RESPONSE_REFRESH_REQUIRED;
            break;

        default:
            // We can't handle this one
            Horde::logMessage('SyncML: Unknown sync type ' . $this->_alert,
                __FILE__, __LINE__, PEAR_LOG_ERR);
            $status = new Horde_SyncML_Command_Status(RESPONSE_BAD_REQUEST, 'Alert');
            $status->setCmdRef($this->_cmdID);
            if ($this->_sourceLocURI != null) {
                $status->setSourceRef($this->_sourceLocURI);
            }
            if ($this->_targetLocURI != null) {
                $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
            }
       	    $currentCmdID = $status->output($currentCmdID, $output);
            return $currentCmdID;
        }

        // Store client's Next Anchor in State and
        // set server's Next Anchor.  After successful sync
        // this is then written to persistence for negotiation of
        // further syncs.
        $state->setClientAnchorNext($type, $this->_metaAnchorNext);
        $serverAnchorNext = time();
        $state->setServerAnchorNext($type, $serverAnchorNext);

        // Now set interval to retrieve server changes from, defined by
        // ServerAnchor [Last,Next]
        if ($synctype != ALERT_TWO_WAY &&
            $synctype != ALERT_ONE_WAY_FROM_CLIENT &&
            $synctype != ALERT_ONE_WAY_FROM_SERVER) {
            $serverAnchorLast = 0;
            #if (!$anchormatch) {
                // Erase existing map:
                $state->removeAllUID($this->_targetLocURI);
            #}
        }
        // Now create the actual SyncML_Sync object, if it doesn't exist yet.
        $sync = &$state->getSync($this->_targetLocURI);
        if (!$sync) {
            Horde::logMessage('SyncML: Creating SyncML_Sync object for target '
                . $this->_targetLocURI .  '; sync type ' . $synctype,
                __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $sync = &Horde_SyncML_Sync::factory($synctype);
	    $state->clearConflictItems($this->_targetLocURI);
        }
        $sync->setTargetLocURI($this->_targetLocURI);
        $sync->setSourceLocURI($this->_sourceLocURI);
		$sync->setLocName($state->getLocName()); // We need it for conflict handling
        $sync->setsyncType($synctype);
        $sync->setFilterExpression($this->_filterExpression);
        $state->setSync($this->_targetLocURI, $sync);

       	$status = new Horde_SyncML_Command_Status($response, 'Alert');
        $status->setCmdRef($this->_cmdID);
        if ($this->_sourceLocURI != null) {
            $status->setSourceRef($this->_sourceLocURI);
        }
        if ($this->_targetLocURI != null) {
            $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
        }

       	// Mirror Next Anchor from client back to client.
       	if (isset($this->_metaAnchorNext)) {
       	    $status->setItemDataAnchorNext($this->_metaAnchorNext);
       	}

       	// Mirror Last Anchor from client back to client.
       	if (isset($this->_metaAnchorLast)) {
       	    $status->setItemDataAnchorLast($this->_metaAnchorLast);
       	}

       	$currentCmdID = $status->output($currentCmdID, $output);

        $output->startElement($state->getURI(), 'Alert', $attrs);

        $output->startElement($state->getURI(), 'CmdID', $attrs);
        $chars = $currentCmdID;
        $output->characters($chars);
        $output->endElement($state->getURI(), 'CmdID');

        $output->startElement($state->getURI(), 'Data', $attrs);
        $chars = $synctype;
        $output->characters($chars);
        $output->endElement($state->getURI(), 'Data');

        $output->startElement($state->getURI(), 'Item', $attrs);

        if ($this->_sourceLocURI != null) {
            $output->startElement($state->getURI(), 'Target', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
            $chars = $this->_sourceLocURI;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Target');
        }

        if ($this->_targetLocURI != null) {
            $output->startElement($state->getURI(), 'Source', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
            $chars = (isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI);
            $output->characters($chars);
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Source');
        }

        $output->startElement($state->getURI(), 'Meta', $attrs);

        $output->startElement($state->getURIMeta(), 'Anchor', $attrs);

        $output->startElement($state->getURIMeta(), 'Last', $attrs);
        $chars = $state->getServerAnchorLast($type);
        $output->characters($chars);
        $output->endElement($state->getURIMeta(), 'Last');

        $output->startElement($state->getURIMeta(), 'Next', $attrs);
        $chars = $state->getServerAnchorNext($type);
        $output->characters($chars);
        $output->endElement($state->getURIMeta(), 'Next');

        $output->endElement($state->getURIMeta(), 'Anchor');
        $output->endElement($state->getURI(), 'Meta');
        $output->endElement($state->getURI(), 'Item');
        $output->endElement($state->getURI(), 'Alert');

        // Final packet of this message
        $state->_sendFinal = true;

        $currentCmdID++;

        if ($state->_devinfoRequested == false &&
            $this->_sourceLocURI != null &&
            is_a($state->getPreferedContentTypeClient($this->_sourceLocURI), 'PEAR_Error')) {

            Horde::logMessage("SyncML: PreferedContentTypeClient missing, sending <Get>", __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $output->startElement($state->getURI(), 'Get', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $output->characters($currentCmdID);
            $currentCmdID++;
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            if (is_a($output, 'XML_WBXML_Encoder')) {
                $output->characters('application/vnd.syncml-devinf+wbxml');
            } else {
                $output->characters('application/vnd.syncml-devinf+xml');
            }
            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');

            $output->startElement($state->getURI(), 'Item', $attrs);
            $output->startElement($state->getURI(), 'Target', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
	    if ($state->getVersion() == 2) {
                $output->characters('./devinf12');
            } elseif ($state->getVersion() == 1) {
                $output->characters('./devinf11');
            } else {
                $output->characters('./devinf10');
            }
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Target');
            $output->endElement($state->getURI(), 'Item');

            $output->endElement($state->getURI(), 'Get');

            $state->_devinfoRequested = true;
        }
        return $currentCmdID;
    }

    /**
     * End element handler for the XML parser, delegated from
     * SyncML_ContentHandler::endElement().
     *
     * @param string $uri      The namespace URI of the element.
     * @param string $element  The element tag name.
     */
    function endElement($uri, $element)
    {
        switch (count($this->_stack)) {
        case 2:
            if ($element == 'Data') {
                $this->_alert = intval(trim($this->_chars));
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                switch ($this->_stack[2]) {
                case 'Source':
                    $this->_sourceLocURI = trim($this->_chars);
                    break;
                case 'Target':
                	$targetLocURIData = explode('?/',trim($this->_chars));

                    $this->_targetLocURI = $targetLocURIData[0];

                    if (isset($targetLocURIData[1])) {
                        $this->_targetLocURIParameters = $targetLocURIData[1];
                    }
                    break;
                }
            }
            break;

        case 5:
            switch ($element) {
            case 'Next':
                $this->_metaAnchorNext = trim($this->_chars);
                break;
            case 'Last':
                $this->_metaAnchorLast = trim($this->_chars);
                break;
            }
            break;

		case 7:
			if ($element == 'Data'
				&& $this->_stack[2] == 'Target'
				&& $this->_stack[3] == 'Filter'
				&& $this->_stack[4] == 'Record'
				&& $this->_stack[5] == 'Item') {
				$this->_filterExpression = 	trim($this->_chars);
			}
            break;
        }
        parent::endElement($uri, $element);
    }

}
