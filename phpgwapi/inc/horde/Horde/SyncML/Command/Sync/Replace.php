<?php

include_once 'Horde/SyncML/Command/Sync/SyncElement.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync/Replace.php,v 1.9 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Sync_Replace extends Horde_SyncML_Command_Sync_SyncElement {
	function output($currentCmdID, &$output) {
		$status = new Horde_SyncML_Command_Status($this->_status, 'Replace');
		$status->setCmdRef($this->_cmdID);
		
		if (isset($this->_luid)) {
			$status->setSourceRef($this->_luid);
		}
		
		#$status->setItemSourceLocURI($this->_sourceLocURI);
		#$status->setItemTargetLocURI(isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI);
		
		return $status->output($currentCmdID, $output);
	}

}
