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
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/Sync.php';
include_once 'Horde/SyncML/Command/Sync/ContentSyncElement.php';

class Horde_SyncML_Sync_TwoWaySync extends Horde_SyncML_Sync {

	function endSync($currentCmdID, & $output) {
		global $registry;

		$state = & $_SESSION['SyncML.state'];

		$syncType = $this->_targetLocURI;

		$hordeType = $state->getHordeType($syncType);

		$refts = $state->getServerAnchorLast($syncType);
		$currentCmdID = $this->handleSync($currentCmdID, $hordeType, $syncType, $output, $refts);

		return $currentCmdID;
	}

	function handleSync($currentCmdID, $hordeType, $syncType, & $output, $refts) {
		global $registry;

		// array of Items which got modified, but got never send to the client before
		$missedAdds = array ();

		$history = $GLOBALS['egw']->contenthistory;
		$state = & $_SESSION['SyncML.state'];
		$maxMsgSize = $state->getMaxMsgSizeClient();
		$deviceInfo = $state->getClientDeviceInfo();

		if (isset($deviceInfo['maxEntries'])) {
			$maxEntries = $deviceInfo['maxEntries'];
			if (!$maxMsgSize && !$maxEntries) {
				// fallback to default
				$maxEntries = MAX_ENTRIES;
			}
		} else {
			$maxEntries = MAX_ENTRIES;
		}

		$serverAnchorNext = $state->getServerAnchorNext($syncType);


		if (isset ($state->curSyncItem)) {
			// Finish the pending sync item
			$cmd = & $state->curSyncItem;
			unset ($state->curSyncItem);
			$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Sync');

			// moreData split; save in session state and end current message
			if ($cmd->hasMoreData()) {
				$state->curSyncItem = & $cmd;
				$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
				return $currentCmdID;
			}
			$state->incNumberOfElements();
		}

		$changes =& $state->getChangedItems($syncType);
		$deletes =& $state->getDeletedItems($syncType);
		$adds =& $state->getAddedItems($syncType);
		$conflicts =& $state->getConflictItems($syncType);

		Horde::logMessage('SyncML: ' . count($changes) . ' changed items found for ' . $syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage('SyncML: ' . count($deletes) . ' deleted items found for ' . $syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage('SyncML: ' . count($conflicts) . ' items to delete on client found for ' . $syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage('SyncML: ' . count($adds) . ' added items found for ' . $syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle changes
		if (is_array($changes)) {
			while ($guid = array_shift($changes)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries
					&& ($state->getNumberOfElements() >= $maxEntries)
					&& isset ($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize +MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$changes[] = $guid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}

				$guid_ts = $state->getSyncTSforAction($guid, 'modify');
				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde :: logMessage("SyncML: timestamp modify $guid guid_ts: $guid_ts sync_ts: $sync_ts",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde :: logMessage("SyncML: change: $guid ignored, came from client",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				if ($guid_ts > $serverAnchorNext) {
					// Change was made after we started this sync.
					// Don't sent this now to the client.
					Horde :: logMessage("SyncML: change $guid is in our future: $serverAnchorNext",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				$locid = $state->getLocID($syncType, $guid);
				if (!$locid) {
					// somehow we missed to add, lets store the uid, so we add this entry later
					$missedAdds[] = $guid;
					Horde :: logMessage("SyncML: unable to create change for $guid: locid not found in map",
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				// Create a replace request for client.
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$c = $registry->call($hordeType . '/export', array (
					'guid' => $guid,
					'contentType' => $contentType
				));
				if (is_a($c, 'PEAR_Error')) {
					// Item in history but not in database. Strange, but can happen.
					Horde :: logMessage("SyncML: change: export of guid $guid failed:\n" . print_r($c, true),
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				$size = strlen($c);
				// return if we have to much data
				if ($maxMsgSize && !$deviceInfo['supportLargeObjs']) {
					if (($size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
						Horde :: logMessage("SyncML: change: export of guid $guid failed due to size $size",
							__FILE__, __LINE__, PEAR_LOG_ERROR);
						$state->log('Server-ExportFailed');
						continue;
					}
					if (($currentSize + $size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
						// put the item back in the queue
						$changes[] = $guid;
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
				}

				Horde :: logMessage("SyncML: change: export guid $guid, content:\n$c",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				# LK				$cmd->setContent($state->convertServer2Client($c, $contentType));
				$cmd->setContent($c);
				$cmd->setLocURI($locid);
				$cmd->setContentType($contentType['ContentType']);
				if (isset ($contentType['ContentFormat'])) {
					$cmd->setContentFormat($contentType['ContentFormat']);
				}
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Replace');
				$state->log('Server-Replace');

				// moreData split; save in session state and end current message
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = & $cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}
		Horde :: logMessage("SyncML: handling sync (changes done) " . $currentCmdID,
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle deletes
		if (is_array($deletes)) {
			while ($guid = array_shift($deletes)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset ($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize + MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$deletes[] = $guid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}

				$guid_ts = $state->getSyncTSforAction($guid, 'delete');
				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde :: logMessage("SyncML: timestamp delete guid_ts: $guid_ts sync_ts: $sync_ts",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde :: logMessage("SyncML: delete $guid ignored, came from client",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					if ($sync_ts < $serverAnchorNext
						&& ($locid = $state->getLocID($syncType, $guid))) {
						// Now we can remove the past
						$state->removeUID($syncType, $locid);
					}
					continue;
				}
				if ($guid_ts > $serverAnchorNext) {
					// Change was made after we started this sync.
					// Don't sent this now to the client.
					Horde :: logMessage("SyncML: delete $guid is in our future: $serverAnchorNext", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				$locid = $state->getLocID($syncType, $guid);
				if (!$locid) {
					Horde :: logMessage("SyncML: unable to delete $guid: locid not found in map", __FILE__, __LINE__, PEAR_LOG_INFO);
					$state->log("Server-DeleteFailure");
					continue;
				}

				Horde :: logMessage("SyncML: delete: $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				// Create a Delete request for client.
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setLocURI($locid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Delete');
				$state->log('Server-Delete');
				$state->removeUID($syncType, $locid);

				// moreData split; save in session state and end current message
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = & $cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}

		// handle remote deletes due to conflicts
		if (count($conflicts) > 0) {
			while ($locid = array_shift($conflicts)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset ($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize +MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$conflicts[] = $locid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				Horde :: logMessage("SyncML: delete client locid: $locid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				// Create a Delete request for client.
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setLocURI($locid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Delete');
				$state->log('Server-DeletedConflicts');
				$state->removeUID($syncType, $locid);

				// moreData split; save in session state and end current message
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = & $cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}
		// Horde::logMessage("SyncML: handling sync ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		// handle missing  adds.
		if (count($missedAdds) > 0) {
			Horde :: logMessage("SyncML: add missed changes as adds " . count($adds) . ' / ' . $missedAdds[0],
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
			$adds = array_merge($adds, $missedAdds);
			Horde :: logMessage("SyncML: merged adds counter " . count($adds) . ' / ' . $adds[0],
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
		}

		if (is_array($adds)) {
			while ($guid = array_shift($adds)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset ($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize +MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$adds[] = $guid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}

				// first we try the modification timestamp then the creation ts
				if (!($guid_ts = $state->getSyncTSforAction($guid, 'modify'))) {
					$guid_ts = $state->getSyncTSforAction($guid, 'add');
				}

				$sync_ts = $state->getChangeTS($syncType, $guid);
				Horde :: logMessage("SyncML: timestamp add $guid guid_ts: $guid_ts sync_ts: $sync_ts", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if ($sync_ts && $sync_ts == $guid_ts) {
					// Change was done by us upon request of client.
					// Don't mirror that back to the client.
					Horde :: logMessage("SyncML: add: $guid ignored, came from client",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				if ($guid_ts > $serverAnchorNext && !in_array($guid, $conflicts)) {
					// Change was made after we started this sync.
					// Don't sent this now to the client.
					Horde :: logMessage("SyncML: add $guid is in our future: $serverAnchorNext",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$locid = $state->getLocID($syncType, $guid);

				if ($locid && $refts == 0) {
					// For slow sync (ts=0): do not add data for which we
					// have a locid again.  This is a heuristic to avoid
					// duplication of entries.
					Horde :: logMessage("SyncML: skipping add of guid $guid as there already is a locid $locid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				Horde :: logMessage("SyncML: add: $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

				// Create an Add request for client.
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);

				$c = $registry->call($hordeType . '/export', array (
					'guid' => $guid,
					'contentType' => $contentType,

				));

				if (is_a($c, 'PEAR_Error')) {
					// Item in history but not in database. Strange, but can happen.
					Horde :: logMessage("SyncML: add: export of guid $guid failed:\n" . print_r($c, true),
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				$size = strlen($c);
				// return if we have to much data
				if ($maxMsgSize && !$deviceInfo['supportLargeObjs']) {
					if (($size +MIN_MSG_LEFT * 2) > $maxMsgSize) {
						Horde :: logMessage("SyncML: add: export of guid $guid failed due to size $size", __FILE__, __LINE__, PEAR_LOG_ERROR);
						$state->log("Server-ExportFailed");
						continue;
					}
					if (($currentSize + $size +MIN_MSG_LEFT * 2) > $maxMsgSize) {
						// put the item back in the queue
						$adds[] = $guid;
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
				}

				Horde :: logMessage("SyncML: add guid $guid to client\n$c",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setContent($c);
				$cmd->setContentType($contentType['ContentType']);
				if (isset ($contentType['ContentFormat'])) {
					$cmd->setContentFormat($contentType['ContentFormat']);
				}
				$cmd->setGUID($guid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
				$state->log('Server-Add');

				// moreData split; put the guid back in the list and return
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = & $cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}
		Horde::logMessage("SyncML: All items handled for sync $syncType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		$state->removeExpiredUID($syncType, time());
		$state->clearSync($syncType);

		return $currentCmdID;
	}

	function loadData() {
		global $registry;

		$state = & $_SESSION['SyncML.state'];
		$syncType = $this->_targetLocURI;
		$hordeType = $state->getHordeType($syncType);
		$state->setTargetURI($syncType);
		$refts = $state->getServerAnchorLast($syncType);
		$future = $state->getServerAnchorNext($syncType);
		$delta_mod = 0;
		$delta_add = 0;

		Horde :: logMessage("SyncML: reading changed items from database for $hordeType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);
		$delta_mod = count($registry->call($hordeType . '/listBy', array (
			'action' => 'modify',
			'timestamp' => $future,
			'type' => $syncType,
			'filter' => $this->_filterExpression
		)));
		$state->mergeChangedItems($syncType, $registry->call($hordeType . '/listBy', array (
			'action' => 'modify',
			'timestamp' => $refts,
			'type' => $syncType,
			'filter' => $this->_filterExpression
		)));

		Horde :: logMessage("SyncML: reading deleted items from database for $hordeType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setDeletedItems($syncType, $registry->call($hordeType . '/listBy', array (
			'action' => 'delete',
			'timestamp' => $refts,
			'type' => $syncType,
			'filter' => $this->_filterExpression
		)));

		Horde :: logMessage("SyncML: reading added items from database for $hordeType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);
		/* The items, which now match the filter criteria are show here, too
		$delta_add = count($registry->call($hordeType . '/listBy', array (
			'action' => 'add',
			'timestamp' => $future,
			'type' => $syncType,
			'filter' => $this->_filterExpression
		)));
		*/
		$state->mergeAddedItems($syncType, $registry->call($hordeType . '/listBy', array (
			'action' => 'add',
			'timestamp' => $refts,
			'type' => $syncType,
			'filter' => $this->_filterExpression
		)));

		$this->_syncDataLoaded = TRUE;

		return count($state->getChangedItems($syncType)) - $delta_mod + count($state->getDeletedItems($syncType)) + count($state->getAddedItems($syncType)) - $delta_add + count($state->getConflictItems($syncType));
	}
}