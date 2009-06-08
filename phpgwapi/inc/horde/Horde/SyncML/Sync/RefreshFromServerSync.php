<?php

include_once 'Horde/SyncML/Sync.php';

/**
 * $Horde: framework/SyncML/SyncML/Sync/RefreshFromServerSync.php,v 1.9 2004/07/03 15:21:15 chuck Exp $
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
class Horde_SyncML_Sync_RefreshFromServerSync extends Horde_SyncML_Sync_TwoWaySync {
	function handleSync($currentCmdID, $hordeType, $syncType, &$output, $refts) {
		global $registry;
		
		$state = &$_SESSION['SyncML.state'];
		
		$adds = &$state->getAddedItems($hordeType);
		
		Horde::logMessage("SyncML: ".count($adds).   ' added items found for '.$hordeType  , __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$serverAnchorNext = $state->getServerAnchorNext($syncType);
		$counter = 0;
		
		if(is_array($adds)) {
			while($guid = array_shift($adds)) {
				if ($locID = $state->getLocID($syncType, $guid)) {
					Horde::logMessage("SyncML: RefreshFromServerSync add to client: $guid ignored, already at client($locID)", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$c = $registry->call($hordeType . '/export', array('guid' => $guid, 'contentType' => $contentType));
				Horde::logMessage("SyncML: slowsync add $guid to client ". print_r($c, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if (!is_a($c, 'PEAR_Error')) {
					$cmd->setContent($c);
					$cmd->setContentType($contentType['ContentType']);
					if (isset($contentType['ContentFormat']))
					{
						$cmd->setContentFormat($contentType['ContentFormat']);
					}
					
					$cmd->setSourceURI($guid);
					$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
					$state->log('Server-Add');
					
					// return if we have to much data
					if(++$counter >= MAX_ENTRIES
						&& isset($contentType['mayFragment'])
						&& $contentType['mayFragment'])
					{
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
				}
			}
		}
		
		#Horde::logMessage("SyncML: handling sync ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		
		$state->clearSync($syncType);
		
		return $currentCmdID;
	}
    
	function loadData() {
		global $registry;
		
		$state = &$_SESSION['SyncML.state'];
		$syncType = $this->_targetLocURI;
		$hordeType = $state->getHordeType($syncType);
		
		Horde::logMessage("SyncML: reading added items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setAddedItems($hordeType, $registry->call($hordeType. '/list', array()));
		$adds = &$state->getAddedItems($hordeType);
		$this->_syncDataLoaded = TRUE;
		
		return count($state->getAddedItems($hordeType));
	}
}
