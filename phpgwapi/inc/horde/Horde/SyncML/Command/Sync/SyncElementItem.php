<?php

include_once 'Horde/SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync/SyncElement.php,v 1.11 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 * Copyright 2005-2006 Lars Kneschke <l.kneschke@metaways.de>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Sync_SyncElementItem {

	var $_luid;
	var $_guid;
	var $_content;
	var $_contentSize;
	var $_contentType;
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
	
	function getContent() {
		return $this->_content;
	}
	
	function setLocURI($luid) {
		$this->_luid = $luid;
	}
	
	function setGUID($guid) {
		$this->_guid = $guid;
	}
	
	function setContent($content) {
		$this->_content = $content;
	}

	function setContentSize($_size) {
		$this->_contentSize = $_size;
	}

	function setContentType($_contentType) {
		$this->_contentType = $_contentType;
	}

	function setMoreData($_status) {
		$this->_moreData = $_status;
	}
}
