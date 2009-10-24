<?php
/**
 * eGroupWare - SyncML based on Horde 3.0
 *
 * The Horde_SyncML_SyncHdr and Horde_SyncML_SyncBody classes provides
 * a SyncHdr and SyncBody in SyncML Representation Protocol, version
 * 1.1 5.2.2 and 5.2.3.  Most of the work is passed on to
 * Horde_SyncML_Command_Alert and Horde_SyncML_Command_Sync.
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @author Karsten Fourmont <fourmont@gmx.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/Command.php';
include_once 'Horde/SyncML/Command/Status.php';
include_once 'Horde/SyncML/Command/Alert.php';
include_once 'Horde/SyncML/Command/Final.php';
include_once 'Horde/SyncML/Command/Sync.php';
include_once 'Horde/SyncML/Sync.php';
include_once 'Horde/SyncML/Command/Sync/SyncElementItem.php';

class Horde_SyncML_ContentHandler {

    /**
     * Output ContentHandler used to output XML events.
     * @var object $_output
     */
    var $_output;

    /**
     * @var integer $_xmlStack
     */
    var $_xmlStack = 1;

    /**
     * @var string $_chars
     */
    var $_chars;

    function setOutput(&$output)
    {
        $this->_output = &$output;
    }

    function startElement($uri, $element, $attrs)
    {
        $this->_xmlStack++;
    }

    function endElement($uri, $element)
    {
        if (isset($this->_chars)) {
            unset($this->_chars);
        }

        $this->_xmlStack--;
    }

    function characters($str)
    {
        if (isset($this->_chars)) {
            $this->_chars = $this->_chars . $str;
        } else {
            $this->_chars = $str;
        }
    }

}

/**
 * Defined in SyncML Representation Protocol, version 1.1 5.2.2
 *
 * @package Horde_SyncML
 */
class Horde_SyncML_SyncMLHdr extends Horde_SyncML_ContentHandler {

    /**
     * Used to specify if in Source tag.  Defined in SyncML
     * Representation Protocol, version 1.1 5.1.20.
     *
     * @var boolean $_isSource
     */
    var $_isSource = false;

    /**
     * Defined in SyncML Representation Protocol, version 1.1
     * 5.1.9. User name.
     *
     * @var string $_locName
     */
    var $_locName;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.18
     *
     * @var string $_sessionID
     */

    var $_sessionID;

    /**
     * Defined in SyncML Representation Protocol, version 1.1.  Must
     * be 1.0 (0), 1.1 (1) or 1.2(2).
     *
     * @var string $_version
     */
    var $_version;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.12
     *
     * @var string $_msgID
     */
    var $_msgID;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.10
     *
     * @var string $_targetURI
     */
    var $_targetURI;

    /**
     * Defined in SyncML Representation Protocol, version 1.1 5.1.10,
     * 5.1.20
     *
     * @var string $_sourceURI
     */
    var $_sourceURI;

    var $_isCred;

    var $_credData;

    var $_credFormat;

    var $_credType;

    var $_maxMsgSize;

    function getStateFromSession($sourceURI, $locName, $sessionID)
    {
        // Remove any existing session since we'll be contructing a
        // custom session id.
		session_regenerate_id();
        session_destroy();

		// we need to (re-)load the eGW session-handler, as session_destroy unloads custom session-handlers
		if (function_exists('init_session_handler')) {
 			init_session_handler();
		}

        // Reload the Horde SessionHandler if necessary.
        Horde::setupSessionHandler();

        // It would seem multisync does not send the user name once it
        // has been authorized. Make sure we have a valid session id.
        if(!empty($_GET['syncml_sessionid'])) {
        	session_id($_GET['syncml_sessionid']);
	        Horde::logMessage('SyncML['.  session_id() .']: reusing existing session',
	        	__FILE__, __LINE__, PEAR_LOG_DEBUG);
        } else {
        	#session_id('syncml' . preg_replace('/[^a-zA-Z0-9]/', '', $sourceURI . $sessionID));
        	session_id('syncml-' . md5(uniqid(rand(), true)));
	        Horde::logMessage('SyncML['.  session_id() .']: starting new session for '
	        	. $this->_locName, __FILE__, __LINE__, PEAR_LOG_INFO);
        }

        @session_start();

        if (!isset($_SESSION['SyncML.state'])) {
            // Create a new state if one does not already exist.
            Horde::logMessage('SyncML['. session_id() .']: create new session state variable for '
            	. $sourceURI, __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $_SESSION['SyncML.state'] = new EGW_SyncML_State($sourceURI, $locName, $sessionID);
		}


        if($_SESSION['SyncML.state']->isAuthorized()) {
			Horde::logMessage('SyncML['. session_id() .']: session is authorized',
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
        #Horde::logMessage('SyncML['. session_id() . "]:\n" . print_r($_SESSION['SyncML.state'], true), __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return $_SESSION['SyncML.state'];
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch ($this->_xmlStack) {
        case 3:
            if ($element == 'Source') {
                // <SyncML><SyncHdr><Source>
                $this->_isSource = true;
            } elseif ($element == 'Cred') {
                $this->_isCred = true;
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
        switch ($this->_xmlStack) {
        case 2:
            /*
            $str = 'localname=' . $this->_locName;
            $str .= ' version=' . $this->_version;
            $str .= ' msgid=' . $this->_msgID;
            $str .= ' source=' . $this->_sourceURI;
            $str .= ' target=' . $this->_targetURI;
            $str .= ' sessionID=' . $this->_sessionID;
            */
            // </SyncHdr></SyncML>
            // Find the state.
            #Horde::logMessage('SymcML: SyncHdr done. Try to load state from session.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $state = $this->getStateFromSession($this->_sourceURI, $this->_locName, $this->_sessionID);

			Horde::logMessage('SyncML['. session_id() .']: package '
				. $this->_msgID.' +++++++++++++++++++++ started',
				__FILE__, __LINE__, PEAR_LOG_DEBUG);

            $state->setVersion($this->_version);
            $state->setMsgID($this->_msgID);
            $state->setTargetURI($this->_targetURI);
            $state->setWBXML(is_a($this->_output, 'XML_WBXML_Encoder'));

            if(isset($this->_credData)
            	&& isset($this->_locName)
            	&& !$state->isAuthorized()) {
            	$state->setPassword($this->_credData);
                $state->setLocName($this->_locName);
            }

            if (isset($this->_maxMsgSize)) {
				$state->setMaxMsgSize($this->_maxMsgSize);
            }

            #$str  = 'authorized=' . $state->isAuthorized();
            #$str .= ' version=' . $state->getVersion();
            #$str .= ' msgid=' . $state->getMsgID();
            #$str .= ' source=' . $state->getSourceURI();
            #$str .= ' target=' . $state->getTargetURI();
            #$str .= ' locName=' . $state->getLocName();

            $_SESSION['SyncML.state'] = $state;

            #Horde::logMessage('SymcML: session id 2 =' . session_id(), __FILE__, __LINE__, PEAR_LOG_DEBUG);

            // Got the state; now write our SyncHdr header.
            $this->outputSyncHdr($this->_output);
            break;

        case 3:
            if ($element == 'VerProto') {
                // </VerProto></SyncHdr></SyncML>
				switch (strtolower(trim($this->_chars))) {
					case 'syncml/1.2':
						$this->_version = 2;
						break;
					case 'syncml/1.1':
						$this->_version = 1;
						break;
					default:
						$this->_version = 0;
						break;
				}
            } elseif ($element == 'SessionID') {
                // </SessionID></SyncHdr></SyncML>
                $this->_sessionID = trim($this->_chars);
            } elseif ($element == 'MsgID') {
                // </MsgID></SyncHdr></SyncML>
                $this->_msgID = intval(trim($this->_chars));
            } elseif ($element == 'Source') {
                // </Source></SyncHdr></SyncML>
                $this->_isSource = false;
            } elseif ($element == 'Cred') {
                // </Cred></SyncHdr></SyncML>
                $this->_isCred = false;

                //multisync does not specify the cred format
                //if ($this->_credFormat == 'b64') {
                $this->_credData = base64_decode($this->_credData);
                //}

                $tmp = explode(':', $this->_credData, 2);
                // set only if not set by LocName already
                if(!isset($this->_locName)) {
                	$this->_locName = $tmp[0];
                }
                $this->_credData = $tmp[1];

                #Horde::logMessage('SyncML['. session_id() .']: $this->_locName: ' . $this->_locName, __FILE__, __LINE__, PEAR_LOG_DEBUG);
            }
            break;

        case 4:
		switch ($element) {
            		case 'LocURI':
                		if ($this->_isSource) {
                    			// </LocURI></Source></SyncHdr></SyncML>
                    			$this->_sourceURI = trim($this->_chars);
                		} else {
                    			// </LocURI></Target></SyncHdr></SyncML>
                    			$this->_targetURI = trim($this->_chars);
                		}
				break;
            		case 'LocName':
                		if ($this->_isSource) {
                    			// </LocName></Source></SyncHdr></SyncML>
                    			$this->_locName = trim($this->_chars);
                		}
				break;
            		case 'Data':
                    		// </Data></Cred></SyncHdr></SyncML>
                		if ($this->_isCred) {
                    			$this->_credData = trim($this->_chars);
                		}
				break;
			case 'MaxMsgSize':
				//</MaxMsgSize></Meta></SyncHdr></SyncML>
				$this->_maxMsgSize = trim($this->_chars);
				break;
            		}
            		break;

        case 5:
            if ($this->_isCred) {
                if ($element == 'Format') {
                    // </Format></Meta></Cred></SyncHdr></SyncML>
                    $this->_credFormat = trim($this->_chars);
                } elseif ($element == 'Type') {
                    // </Type></Meta></Cred></SyncHdr></SyncML>
                    $this->_credType = trim($this->_chars);
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function outputSyncHdr(&$output)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        $uri = $state->getURI();
        $uriMeta = $state->getURIMeta();
        $output->startElement($uri, 'SyncHdr', $attrs);

        $output->startElement($uri, 'VerDTD', $attrs);
		if ($this->_version == 2) {
        	$chars = '1.2';
		} elseif ($this->_version == 1) {
			$chars = '1.1';
		} else {
			$chars = '1.0';
		}
        $output->characters($chars);
        $output->endElement($uri, 'VerDTD');

        $output->startElement($uri, 'VerProto', $attrs);
		if ($this->_version == 2) {
        	$chars = 'SyncML/1.2';
		} elseif ($this->_version == 1) {
			$chars = 'SyncML/1.1';
		} else {
			$chars = 'SyncML/1.0';
		}
        $output->characters($chars);
        $output->endElement($uri, 'VerProto');

        $output->startElement($uri, 'SessionID', $attrs);
        $output->characters($this->_sessionID);
        $output->endElement($uri, 'SessionID');

        $output->startElement($uri, 'MsgID', $attrs);
        $output->characters($this->_msgID);
        $output->endElement($uri, 'MsgID');

        $output->startElement($uri, 'Target', $attrs);
        $output->startElement($uri, 'LocURI', $attrs);
        $output->characters($this->_sourceURI);
        $output->endElement($uri, 'LocURI');
        $output->endElement($uri, 'Target');

        $output->startElement($uri, 'Source', $attrs);
        $output->startElement($uri, 'LocURI', $attrs);
       	$output->characters($this->_targetURI);
        $output->endElement($uri, 'LocURI');
        $output->endElement($uri, 'Source');

		if (session_id() != '' && !strpos($this->_targetURI,'syncml_sessionid')) {
			$output->startElement($uri, 'RespURI', $attrs);

			// some clients don't send the whole URL as targetURI
			if (strpos($this->_targetURI,$_SERVER['PHP_SELF']) === false) {
				$output->characters($this->_targetURI . $_SERVER['PHP_SELF'] . '?syncml_sessionid=' . session_id());
			} else {
				$output->characters($this->_targetURI . '?syncml_sessionid=' . session_id());
			}
			$output->endElement($uri, 'RespURI');
		}


        $output->startElement($uri, 'Meta', $attrs);

		if (!($maxMsgSize = $state->getMaxMsgSizeClient())) {
			// Dummy MaxMsqSize, this is just put in to make the packet
			// work, it is not our real value.
			$maxMsgSize = 50000;
		}
        $output->startElement($uriMeta, 'MaxMsgSize', $attrs);
        $output->characters($maxMsgSize);
        $output->endElement($uriMeta, 'MaxMsgSize');

        #// Dummy MaxObjSize, this is just put in to make the packet
        #// work, it is not our real value.
		#if ($this->_version > 0) {
		#	// Don't send this to old devices
        #	$output->startElement($uriMeta, 'MaxObjSize', $attrs);
        #	$output->characters('4000000');
        #	$output->endElement($uriMeta, 'MaxObjSize');
		#}

        $output->endElement($uri, 'Meta');

        $output->endElement($uri, 'SyncHdr');
    }

    function getSourceURI()
    {
        return $this->_sourceURI;
    }

    function getLocName()
    {
        return $this->_locName;
    }

    function getSessionID()
    {
        return $this->_sessionID;
    }

    function getVersion()
    {
        return $this->_version;
    }

    function getMsgID()
    {
        return $this->_msgID;
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function opaque($o)
    {
    }

}

/**
 * Defined in SyncML Representation Protocol, version 1.1 5.2.3
 *
 * @package Horde_SyncML
 */
class Horde_SyncML_SyncMLBody extends Horde_SyncML_ContentHandler {

    var $_currentCmdID = 1;

    var $_currentCommand;

    var $_actionCommands = false;

    var $_clientSentFinal = false;

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);
        $state = &$_SESSION['SyncML.state'];

        switch ($this->_xmlStack) {
        case 2:
            $this->_actionCommands = false; // so far, we have not seen commands that require action from our side
            $state->_sendFinal = false;

            // <SyncML><SyncBody>
            $this->_output->startElement($uri, $element, $attrs);

	    	if($state->getLocName()) {
				if($state->isAuthConfirmed()) {
            		// Right our status about the header
            		$status = new Horde_SyncML_Command_Status(($state->isAuthorized()) ?
                    	RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS, 'SyncHdr');
				} else {
            		// Right our status about the header.
            		$status = new Horde_SyncML_Command_Status(($state->isAuthorized()) ?
                   		RESPONSE_AUTHENTICATION_ACCEPTED : RESPONSE_INVALID_CREDENTIALS, 'SyncHdr');
				}
            } else {
            	// Request credentials if not sent so far
            	$status = new Horde_SyncML_Command_Status(RESPONSE_MISSING_CREDENTIALS, 'SyncHdr');
            }

            $status->setSourceRef($state->getSourceURI());
            $status->setTargetRef($state->getTargetURI());
            $status->setCmdRef(0);
            $state->clearNumberOfElements();

            /*$str = 'authorized=' . $state->isAuthorized();
            $str .= ' version=' . $state->getVersion();
            $str .= ' msgid=' . $state->getMsgID();
            $str .= ' source=' . $state->getSourceURI();
            $str .= ' target=' . $state->getTargetURI();
            */
            $this->_currentCmdID = $status->output($this->_currentCmdID, $this->_output);
            if ($state->isAuthorized()) {
	    		$state->AuthConfirmed();
            }
            break;

        case 3:
            // <SyncML><SyncBody><[Command]>
            #Horde::logMessage('SyncML['. session_id() ."]:    found command    $element         ", __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $this->_currentCommand = Horde_SyncML_Command::factory($element);
            $this->_currentCommand->startElement($uri, $element, $attrs);

            if ($element != 'Status' && $element != 'Map' && $element != 'Final') {
                // We've got to do something! This can't be the last
                // packet.
                $this->_actionCommands = true;
                Horde::logMessage('SyncML['. session_id() ."]: found action commands <$element> ", __FILE__, __LINE__, PEAR_LOG_DEBUG);
            }

            switch($element)
            {
            	case 'Sync':
            		$state->setSyncStatus(CLIENT_SYNC_STARTED);
                	Horde::logMessage('SyncML['. session_id() .']: syncStatus(client sync started) ' . $state->getSyncStatus(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
            		break;
            }
            break;

        default:
            // <SyncML><SyncBody><Command><...>
            $this->_currentCommand->startElement($uri, $element, $attrs);
            break;
        }
    }

	function endElement($uri, $element)
	{
		$state = &$_SESSION['SyncML.state'];

		switch ($this->_xmlStack) {
			case 2:
				// </SyncBody></SyncML>

				Horde::logMessage('SyncML['. session_id() .']: package ----------------------- done', __FILE__, __LINE__, PEAR_LOG_DEBUG);

				if ($state->getAlert222Received() == true) {
					// the Funambol specialty
					if ($state->getSyncStatus() == CLIENT_SYNC_FINNISHED) {
						$state->setSyncStatus(CLIENT_SYNC_ACKNOWLEDGED);
					}
					$state->setAlert222Received(false);
				}


				// send the sync reply
				// we do still have some data to send OR
				// we should reply to the Sync command
				if($state->getSyncStatus() > CLIENT_SYNC_FINNISHED && $state->getSyncStatus() < SERVER_SYNC_FINNISHED) {
					$sync = new Horde_SyncML_Command_Sync();
					$this->_currentCmdID = $sync->syncToClient($this->_currentCmdID, $this->_output);
				}

				// send the Final tag if possible
				#if($state->getSyncStatus() != SERVER_SYNC_DATA_PENDING && $state->getSyncStatus() != CLIENT_SYNC_STARTED) {
				if($state->getSyncStatus() >= SERVER_SYNC_FINNISHED || $state->_sendFinal) {
					$final = new Horde_SyncML_Command_Final();
					$this->_currentCmdID = $final->output($this->_currentCmdID, $this->_output);
				}

				$this->_output->endElement($uri, $element);

				Horde::logMessage('SyncML['. session_id() .']: syncStatus = '. $state->getSyncStatus() .', actionCommands = '.
					($this->_actionCommands ? 'True' : 'False'), __FILE__, __LINE__, PEAR_LOG_DEBUG);

				if (!$this->_actionCommands && $state->getSyncStatus() == SERVER_SYNC_FINNISHED) {
					// this packet did not contain any real actions, just status and map.
					// This means, we're through! The session can be closed and
					// the Anchors saved for the next Sync
					Horde::logMessage('SyncML['. session_id() .']: sync' . session_id() . ' completed successfully!', __FILE__, __LINE__, PEAR_LOG_INFO);
					$state->writeSyncSummary();
					$log = $state->getLog();
					$s="";
					foreach($log as $k => $v) {
						$s .= " $k=$v";
					}
					if (strlen(trim($s)) == 0) {
						$s = ' Both parties were already in sync';
					}
					Horde::logMessage('SyncML['. session_id() .']: summary:' . $s, __FILE__, __LINE__, PEAR_LOG_INFO);
				#	Horde::logMessage('SyncML['. session_id() .']: destroying sync session '.session_id(), __FILE__, __LINE__, PEAR_LOG_INFO);
				#	// session can be closed here!
				#	session_unset();
				#	session_destroy();
				}

				if (!$this->_actionCommands && $state->getSyncStatus() == SERVER_SYNC_ACKNOWLEDGED) {
					// this packet did not contain any real actions, just status and map.
					// This means, we're through! The session can be closed and
					// the Anchors saved for the next Sync
					Horde::logMessage('SyncML['. session_id() .']: sync' . session_id() . ' completed successfully!', __FILE__, __LINE__, PEAR_LOG_INFO);
					$state->writeSyncSummary();
					$log = $state->getLog();
					$s="";
					foreach($log as $k => $v) {
						$s .= " $k=$v";
					}
					if (strlen(trim($s)) == 0) {
						$s = ' Both parties were already in sync';
					}
					Horde::logMessage('SyncML['. session_id() .']: summary:' . $s, __FILE__, __LINE__, PEAR_LOG_INFO);

					Horde::logMessage('SyncML['. session_id() .']: destroying sync session '.session_id(), __FILE__, __LINE__, PEAR_LOG_INFO);
					// session can be closed here!
					session_unset();
					session_destroy();
				}
				break;

			case 3:
				// </[Command]></SyncBody></SyncML>

            			$this->_currentCommand->endElement($uri, $element);

            			switch($element) {
	            			case 'Final':
		            			$this->_actionCommands = false;
		            			$deviceInfo = $state->getClientDeviceInfo();

		            			if ($state->getSyncStatus() == CLIENT_SYNC_STARTED) {
			            			if (strtolower($deviceInfo['manufacturer']) == 'funambol'
				            			&& isset($deviceInfo['softwareVersion'])) {
				            			$swversion = $deviceInfo['softwareVersion'];
				            			if ($swversion < 1.0) {
					            			// e.g. Mozilla plugin uses this range
					            			$swversion = $swversion * 10;
				            			}
				            			if (3.0 < $swversion && $swversion < 7.0) {
					            			// We wait for a ALERT_NEXT_MESSAGE from Funambol old clients
					            			Horde::logMessage('SyncML['. session_id()
						            			. "]: Special treatment for Funambol version $swversion activated",
												__FILE__, __LINE__, PEAR_LOG_DEBUG);
					            			$state->setSyncStatus(CLIENT_SYNC_FINNISHED);
				            			} else {
					            			$state->setSyncStatus(CLIENT_SYNC_ACKNOWLEDGED);
				            			}
			            			} else {
				            			$state->setSyncStatus(CLIENT_SYNC_ACKNOWLEDGED);
			            			}
		            			}

		            			if ($state->getSyncStatus() == SERVER_SYNC_FINNISHED) {
			            			$state->setSyncStatus(SERVER_SYNC_ACKNOWLEDGED);
		            			}

		            			$this->_clientSentFinal = true;
		            			Horde::logMessage('SyncML['. session_id() .']: syncStatus(server sync acknowledged) '
			            			. $state->getSyncStatus(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
		            			break;

	            			default:
		            			$this->_currentCmdID = $this->_currentCommand->output($this->_currentCmdID, $this->_output);
	            			break;
            			}

				unset($this->_currentCommand);
				break;

			default:
				// </...></[Command]></SyncBody></SyncML>
				$this->_currentCommand->endElement($uri, $element);
				break;
		}

		parent::endElement($uri, $element);
	}

	function characters($str) {
		if (isset($this->_currentCommand)) {
			$this->_currentCommand->characters($str);
		}
	}

}
