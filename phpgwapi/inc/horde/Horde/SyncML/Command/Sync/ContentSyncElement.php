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
include_once 'Horde/SyncML/Command/Sync/SyncElementItem.php';

class Horde_SyncML_Command_Sync_ContentSyncElement extends Horde_SyncML_Command_Sync_SyncElementItem {

    function outputCommand($currentCmdID, &$output, $command)
    {
        $state = $_SESSION['SyncML.state'];
		$maxMsgSize = $state->getMaxMsgSizeClient();
		$maxGUIDSize = $state->getMaxGUIDSizeClient();

		if ($this->_moreData) {
			$command = $this->_command;
		} else {
			$this->_command = $command;
		}

        $attrs = array();
        $output->startElement($state->getURI(), $command, $attrs);

        $output->startElement($state->getURI(), 'CmdID', $attrs);
        $output->characters($currentCmdID);
        $output->endElement($state->getURI(), 'CmdID');

/*
        if (isset($this->_contentType)) {
            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            $output->characters($this->_contentType);
            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');
        }
*/
        if (isset($this->_content) && !$this->_moreData) {
			//$this->_content = trim($this->_content);
	    	$this->_contentSize = strlen($this->_content);
			if (strtolower($this->_contentFormat) == 'b64') {
				$this->_content = base64_encode($this->_content);
			}
        } else {
            $this->_contentSize = 0;
        }

            // <command><Meta>
        if ($this->_contentSize || isset($this->_contentType) || isset($this->_contentFormat)) {
			$output->startElement($state->getURI(), 'Meta', $attrs);
        	if (isset($this->_contentType)) {
				$output->startElement($state->getURIMeta(), 'Type', $attrs);
				$output->characters($this->_contentType);
				$output->endElement($state->getURIMeta(), 'Type');
			}
			if (isset($this->_contentFormat)) {
                $output->startElement($state->getURIMeta(), 'Format', $attrs);
				$output->characters($this->_contentFormat);
				$output->endElement($state->getURIMeta(), 'Format');
			}
			if ($this->_contentSize) {
				$output->startElement($state->getURIMeta(), 'Size', $attrs);
				$output->characters(($this->_contentSize));
				$output->endElement($state->getURIMeta(), 'Size');
			}
			$output->endElement($state->getURI(), 'Meta');
        }

        if (isset($this->_content) || isset($this->_luid) || isset($this->_guid)) {
            $output->startElement($state->getURI(), 'Item', $attrs);

            // <command><Item><Source><LocURI>
            if (isset($this->_guid)) {
                $output->startElement($state->getURI(), 'Source', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $chars = substr($this->_guid, 0, $maxGUIDSize);
                $state->setUIDMapping($this->_guid, $chars);
                $output->characters($chars);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Source');
            }

            // <command><Item><Target><LocURI>
            if (isset($this->_luid)) {
                $output->startElement($state->getURI(), 'Target', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $output->characters($this->_luid);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Target');
            }


             // <command><Item><Data>
            if (isset($this->_content)) {
                $output->startElement($state->getURI(), 'Data', $attrs);
                #$chars = '<![CDATA['.$this->_content.']]>';
				$currentSize = $output->getOutputSize();
				Horde::logMessage("SyncML: $command: current = $currentSize, max = $maxMsgSize", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if (!$maxMsgSize ||
					(($currentSize + MIN_MSG_LEFT + $this->_contentSize) <= $maxMsgSize)) {
					$chars = $this->_content;
					unset($this->_content);
					$this->_moreData = false;
				} else {
					$sizeLeft = $maxMsgSize - $currentSize - MIN_MSG_LEFT;
					if ($sizeLeft < 0) {
						Horde::logMessage("SyncML: $command: split with $currentSize for $maxMsgSize, increase MIN_MSG_LEFT!", __FILE__, __LINE__, PEAR_LOG_WARNING);
						$sizeLeft = 0;
					}
					// don't let us loose characters by trimming
					while (($this->_contentSize > $sizeLeft) &&
							(strlen(trim(substr($this->_content, $sizeLeft - 1, 2))) < 2)) {
						Horde::logMessage("SyncML: $command: split at $sizeLeft hit WS!", __FILE__, __LINE__, PEAR_LOG_DEBUG);
						$sizeLeft++;
					}
					$chars = substr($this->_content, 0, $sizeLeft);
					$this->_content = substr($this->_content, $sizeLeft, $this->_contentSize - $sizeLeft);
					Horde::logMessage("SyncML: $command: "
						. $this->_contentSize . " split at $sizeLeft:\n"
						. $chars, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					$this->_moreData = true;
				}
               	$output->characters($chars);
                $output->endElement($state->getURI(), 'Data');

		 		// <command><Item><MoreData/>
				if ($this->_moreData) {
                	$output->startElement($state->getURI(), 'MoreData', $attrs);
                	$output->endElement($state->getURI(), 'MoreData');
				}
            }
            $output->endElement($state->getURI(), 'Item');
        }

        $output->endElement($state->getURI(), $command);

        $currentCmdID++;

        return $currentCmdID;
    }

}
