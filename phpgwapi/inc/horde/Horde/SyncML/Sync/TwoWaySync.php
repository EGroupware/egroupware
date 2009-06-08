<?php

include_once 'Horde/SyncML/Sync.php';
include_once 'Horde/SyncML/Command/Sync/ContentSyncElement.php';

/**
 * $Horde: framework/SyncML/SyncML/Sync/TwoWaySync.php,v 1.12 2004/07/26 09:24:38 jan Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @author  Karsten Fourmont <fourmont@gmx.de>
 *
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Sync_TwoWaySync extends Horde_SyncML_Sync {

	function endSync($currentCmdID, &$output) {
		global $registry;

		$state = &$_SESSION['SyncML.state'];

		$syncType = $this->_targetLocURI;

		$hordeType = $state->getHordeType($syncType);

		$refts = $state->getServerAnchorLast($syncType);
		$currentCmdID = $this->handleSync($currentCmdID,
                                          $hordeType,
                                          $syncType,
                                          $output,
                                          $refts);

		return $currentCmdID;
	}

	function handleSync($currentCmdID, $hordeType, $syncType,&$output, $refts) {
		global $registry;

		// array of Items which got modified, but got never send to the client before
		$missedAdds = array();

		$history = $GLOBALS['egw']->contenthistory;
		$state = &$_SESSION['SyncML.state'];
		$counter = 0;

		$changes = &$state->getChangedItems($hordeType);
		$deletes = &$state->getDeletedItems($hordeType);
		$adds = &$state->getAddedItems($hordeType);

		Horde::logMessage("SyncML: ".count($changes).' changed items found for '.$hordeType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage("SyncML: ".count($deletes).' deleted items found for '.$hordeType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage("SyncML: ".count($adds).   ' added items found for '.$hordeType  , __FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle changes
		if(is_array($changes)) {
			while($guid = array_shift($changes)) {
				$guid_ts = $state->getSyncTSforAction($guid, 'modify');
				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde::logMessage("SyncML: timestamp modify guid_ts: $guid_ts sync_ts: $sync_ts", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde::logMessage("SyncML: change: $guid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				Horde::logMessage("SyncML: change $guid hs_ts:$guid_ts dt_ts:" . $state->getChangeTS($syncType, $guid), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$locid = $state->getLocID($syncType, $guid);
				if (!$locid) {
					// somehow we missed to add, lets store the uid, so we add this entry later
					$missedAdds[] = $guid;
					Horde::logMessage("SyncML: unable to create change for $guid: locid not found in map", __FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				// Create a replace request for client.
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$c = $registry->call($hordeType. '/export',
					array('guid' => $guid, 'contentType' => $contentType));
				if (!is_a($c, 'PEAR_Error')) {
					// Item in history but not in database. Strange, but can happen.
					Horde::logMessage("SyncML: change: $guid export content: $c", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
# LK					$cmd->setContent($state->convertServer2Client($c, $contentType));
					$cmd->setContent($c);
					$cmd->setSourceURI($guid);
					$cmd->setTargetURI($locid);
					$cmd->setContentType($contentType['ContentType']);
					if (isset($contentType['ContentFormat']))
					{
						$cmd->setContentFormat($contentType['ContentFormat']);
					}

					$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Replace');
					$state->log('Server-Replace');

					// return if we have to much data
					if (++$counter >= MAX_ENTRIES
						&& isset($contentType['mayFragment'])
						&& $contentType['mayFragment'])
					{
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
				}
			}
		}
		Horde::logMessage("SyncML: handling sync (changes done) ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle deletes
		if(is_array($deletes)) {
			while($guid = array_shift($deletes)) {
				$guid_ts = $state->getSyncTSforAction($guid, 'delete');
				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde::logMessage("SyncML: timestamp delete guid_ts: $guid_ts sync_ts: $sync_ts", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde::logMessage("SyncML: delete $guid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$locid = $state->getLocID($syncType, $guid);
				if (!$locid) {
					Horde::logMessage("SyncML: unable to create delete for $guid: locid not found in map", __FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				Horde::logMessage("SyncML: delete: $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				// Create a Delete request for client.
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setTargetURI($locid);
				$cmd->setSourceURI($guid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Delete');
				$state->log('Server-Delete');
				$state->removeUID($syncType, $locid);

				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
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
		#Horde::logMessage("SyncML: handling sync ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle missing  adds.
		if(count($missedAdds) > 0) {
			Horde::logMessage("SyncML: add missed changes as adds ".count($adds).' / '.$missedAdds[0], __FILE__, __LINE__, PEAR_LOG_DEBUG);
			$state->setAddedItems($hordeType, array_merge($adds, $missedAdds));
			$adds = &$state->getAddedItems($hordeType);
			Horde::logMessage("SyncML: merged adds counter ".count($adds).' / '.$adds[0], __FILE__, __LINE__, PEAR_LOG_DEBUG);
		}

		if(is_array($adds)) {
			while($guid = array_shift($adds)) {
				$guid_ts = $state->getSyncTSforAction($guid, 'add');
				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde::logMessage("SyncML: timestamp add $guid guid_ts: $guid_ts sync_ts: $sync_ts", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde::logMessage("SyncML: add: $guid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$locid = $state->getLocID($syncType, $guid);

				if ($locid && $refts == 0) {
					// For slow sync (ts=0): do not add data for which we
					// have a locid again.  This is a heuristic to avoid
					// duplication of entries.
					Horde::logMessage("SyncML: skipping add of guid $guid as there already is a locid $locid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				Horde::logMessage("SyncML: add: $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

				// Create an Add request for client.
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);

				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$c = $registry->call($hordeType . '/export',
					array(
						'guid'		=> $guid ,
						'contentType'	=> $contentType ,
					)
				);

				if (!is_a($c, 'PEAR_Error')) {
					// Item in history but not in database. Strange, but can happen.
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
		$refts = $state->getServerAnchorLast($syncType);

		Horde::logMessage("SyncML: reading changed items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setChangedItems($hordeType, $registry->call($hordeType. '/listBy', array('action' => 'modify', 'timestamp' => $refts)));

		Horde::logMessage("SyncML: reading deleted items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setDeletedItems($hordeType, $registry->call($hordeType. '/listBy', array('action' => 'delete', 'timestamp' => $refts)));

		Horde::logMessage("SyncML: reading added items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setAddedItems($hordeType, $registry->call($hordeType. '/listBy', array('action' => 'add', 'timestamp' => $refts)));

		$this->_syncDataLoaded = TRUE;

		return count($state->getChangedItems($hordeType)) +
			count($state->getDeletedItems($hordeType)) +
			count($state->getAddedItems($hordeType));
	}
}
