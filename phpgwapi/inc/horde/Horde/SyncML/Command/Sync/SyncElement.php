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
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Sync_SyncElement extends Horde_SyncML_Command {

	var $_luid;
	var $_guid;
	var $_contentSize = 0;
	var $_contentType;
	var $_contentFormat;
	var $_status = RESPONSE_OK;
	var $_curItem;
	var $_items = array();
	var $_failed = array();
	var $_moreData = false;
	var $_command = false;

	function &factory($command, $params = null) {
		include_once 'Horde/SyncML/Command/Sync/SyncElementItem.php';
		@include_once 'Horde/SyncML/Command/Sync/' . $command . '.php';

		$class = 'Horde_SyncML_Command_Sync_' . $command;

		if (class_exists($class)) {
			#Horde::logMessage('SyncML: Class definition of ' . $class . ' found in SyncElement::factory.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
			return $element = new $class($params);
		} else {
			Horde::logMessage('SyncML: Class definition of ' . $class . ' not found in SyncElement::factory.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
			require_once 'PEAR.php';
			return PEAR::raiseError('Class definition of ' . $class . ' not found.');
		}
	}

	function startElement($uri, $element, $attrs) {
		parent::startElement($uri, $element, $attrs);
		$state = &$_SESSION['SyncML.state'];

		switch (count($this->_stack)) {
			case 1:
				$this->_command = $element;
				break;
			case 2:
				if ($element == 'Item') {
					if (isset($state->curSyncItem)) {
						// Copy from state in case of <MoreData>.
						$this->_curItem = &$state->curSyncItem;
						if (isset($this->_luid) &&
							($this->_luid != $this->_curItem->_luid)) {
							Horde::logMessage('SyncML: moreData mismatch for LocURI ' .
								$this->_curItem->_luid . ' (' . $this->_luid . ')', __FILE__, __LINE__, PEAR_LOG_ERROR);
						} else {
							Horde::logMessage('SyncML: moreData item found for LocURI ' . $this->_curItem->_luid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
						}
						unset($state->curSyncItem);
					} else {
						$this->_curItem = new Horde_SyncML_Command_Sync_SyncElementItem();
					}
					$this->_moreData = false;
				}
				break;
		}
	}

	function endElement($uri, $element) {
		$state = &$_SESSION['SyncML.state'];
		$search = array('/ *\n/','/ *$/m');
		$replace = array('','');

		switch (count($this->_stack)) {
			case 1:
				$this->_command = false;
				// Need to add sync elements to the Sync method?
				#error_log('total # of items: '.count($this->_items));
				#error_log(print_r($this->_items[10], true));
				break;
			case 2;
				if($element == 'Item') {
					if ($this->_luid) {
						$this->_curItem->setLocURI($this->_luid);
						$this->_curItem->setContentType($this->_contentType);
						$this->_curItem->setContentFormat($this->_contentFormat);
						$this->_curItem->setCommand($this->_command);

						if ($this->_contentSize)
							$this->_curItem->setContentSize($this->_contentSize);
						if ($this->_moreData) {
							$state->curSyncItem = &$this->_curItem;
							Horde::logMessage('SyncML: moreData item saved for LocURI ' . $this->_curItem->_luid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
						} else {
							$content = $this->_curItem->getContent();
							$contentSize = strlen($content);
							if ((($size = $this->_curItem->getContentSize()) !== false) &&
								abs($contentSize - $size) > 3) {
								Horde::logMessage('SyncML: content size mismatch for LocURI ' . $this->_luid .
								": $contentSize ($size) : " . $content,
								__FILE__, __LINE__, PEAR_LOG_WARNING);
								$this->_failed[$this->_luid] = $this->_curItem;
							} else {
								if (strtolower($this->_curItem->getContentFormat()) == 'b64') {
									$content =  ($content ? base64_decode($content) : '');
									$this->_curItem->setContent($content);
									#Horde::logMessage('SyncML: BASE64 encoded item for LocURI '
									#	. $this->_curItem->_luid . ":\n $content", __FILE__, __LINE__, PEAR_LOG_DEBUG);
								}
								#Horde::logMessage('SyncML: Data for ' . $this->_luid . ': ' . $this->_curItem->getContent(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
								$this->_items[$this->_luid] = $this->_curItem;
							}
						}
					}
					unset($this->_contentSize);
					unset($this->_luid);
				}
				break;
			case 3:
				switch ($element) {
					case 'Data':
						$content = $this->_chars;
						if ($this->_contentFormat == 'b64') $content = trim($content);
						#Horde::logMessage('SyncML: Data for ' . $this->_luid . ': ' . $content, __FILE__, __LINE__, PEAR_LOG_DEBUG);
						$this->_curItem->_content .= $content;
						break;
					case 'MoreData':
						$this->_moreData = true;
						break;
					case 'Type':
						if (empty($this->_contentType)) {
							$this->_contentType = trim($this->_chars);
						}
						break;
					case 'Format':
						$this->_contentFormat = strtolower(trim($this->_chars));
						break;
					case 'Size':
						$this->_contentSize = trim($this->_chars);
						break;
				}
				break;

			case 4:
				switch ($element) {
					case 'LocURI':
						if ($this->_stack[2] == 'Source') {
							$this->_luid = trim($this->_chars);
						}
						break;
					case 'Type':
						$this->_contentType = trim($this->_chars);
						break;
					case 'Format':
						$this->_contentFormat = strtolower(trim($this->_chars));
						break;
					case 'Size':
						$this->_contentSize = trim($this->_chars);
						break;
				}
				break;
		}

		parent::endElement($uri, $element);
	}

    function getSyncElementItems() {
         return (array)$this->_items;
    }
    
    function getSyncElementFailures() {
         return (array)$this->_failed;
    }

    function getLocURI()
    {
        return $this->_luid;
    }

    function getGUID()
    {
        return $this->_guid;
    }

    function setLocURI($luid)
    {
        $this->_luid = $luid;
    }

    function setGUID($guid)
    {
        $this->_guid = $guid;
    }

    function setContentType($c)
    {
        $this->_contentType = $c;
    }

    function getContentType()
    {
        return $this->_contentType;
    }

    function getContent()
    {
	if ($this->_curItem) {
        	return $this->_curItem->getcontent();
	}
	return false;
    }

    function hasMoreData()
    {
        return $this->_moreData;
    }

    function setStatus($_status)
    {
    	$this->_status = $_status;
    }
}
