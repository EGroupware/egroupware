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
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Sync_SyncElementItem {

	var $_luid;
	var $_guid;
	var $_content = '';
	var $_contentSize;
	var $_contentType;
	var $_contentFormat;
	var $_command;
	var $_moreData = false;

	function getLocURI() {
		return $this->_luid;
	}

	function getGUID() {
		return $this->_guid;
	}

	function getContentType() {
		return $this->_contentType;
	}

	function getContentFormat() {
		return $this->_contentFormat;
	}

	function getContent() {
		return $this->_content;
	}

	function getContentSize() {
		if (isset($this->_contentSize)) {
			return $this->_contentSize;
		}
		return false;
	}

	function getCommand() {
		return $this->_command;
	}

	function setLocURI($luid) {
		$this->_luid = $luid;
	}

	function setGUID($guid) {
		$this->_guid = $guid;
	}

	function setContent($_content) {
		$this->_content = $_content;
	}

	function setContentSize($_size) {
		$this->_contentSize = $_size;
	}

	function setContentType($_type) {
		$this->_contentType = $_type;
	}

	function setContentFormat($_format) {
		$this->_contentFormat = $_format;
	}

	function setMoreData($_status) {
		$this->_moreData = $_status;
	}

	function hasMoreData() {
		return $this->_moreData;
	}

	function setCommand($_command) {
		$this->_command = $_command;
	}
}
