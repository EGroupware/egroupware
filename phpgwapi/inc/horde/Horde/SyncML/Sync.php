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

class Horde_SyncML_Sync {

	/**
	 * Target, either contacts, notes, events,
	 */
	var $_targetLocURI;

	var $_sourceLocURI;

	var $_locName;

	/**
	 * The synchronization method, one of the ALERT_* constants.
	 *
	 * @var integer
	 */
	var $_syncType;

	/**
	 * Return if all commands success.
	 */
	var $globalSuccess;

	/**
	 * This is the content type to use to export data.
	 */
	var $preferedContentType;

	/**
     * Optional filter expression for this content.
     *
     * @var string
     */
    var $_filterExpression = '';



	/**
	 * Do have the sync data loaded from the database already?
	 */
	var $syncDataLoaded;

	function &factory($alert) {
		Horde::logMessage('SyncML: new sync for alerttype ' . $alert, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		switch ($alert) {
			case ALERT_TWO_WAY:
				include_once 'Horde/SyncML/Sync/TwoWaySync.php';
				return $sync = new Horde_SyncML_Sync_TwoWaySync();

			case ALERT_SLOW_SYNC:
				include_once 'Horde/SyncML/Sync/SlowSync.php';
				return $sync = new Horde_SyncML_Sync_SlowSync();

			case ALERT_ONE_WAY_FROM_CLIENT:
				include_once 'Horde/SyncML/Sync/OneWayFromClientSync.php';
				return $sync = new Horde_SyncML_Sync_OneWayFromClientSync();

			case ALERT_REFRESH_FROM_CLIENT:
				include_once 'Horde/SyncML/Sync/RefreshFromClientSync.php';
				return $sync = new Horde_SyncML_Sync_RefreshFromClientSync();

			case ALERT_ONE_WAY_FROM_SERVER:
				include_once 'Horde/SyncML/Sync/OneWayFromServerSync.php';
				return $sync = new Horde_SyncML_Sync_OneWayFromServerSync();

			case ALERT_REFRESH_FROM_SERVER:
				include_once 'Horde/SyncML/Sync/RefreshFromServerSync.php';
				return $sync = new Horde_SyncML_Sync_RefreshFromServerSync();
		}

		require_once 'PEAR.php';
		return PEAR::raiseError('Alert ' . $alert . ' not found.');
	}

	function nextSyncCommand($currentCmdID, &$syncCommand, &$output) {
		$this->runSyncCommand($syncCommand);
		if ($syncCommand->hasMoreData()) {
			Horde::logMessage('SyncML: moreData: TRUE', __FILE__, __LINE__, PEAR_LOG_DEBUG);
			$syncCommand->setStatus(RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED);
		}
		return $syncCommand->output($currentCmdID, $output);
	}

	function startSync($currentCmdID, &$output) {
		return $currentCmdID;
	}

	function endSync($currentCmdID, &$output) {
		return $currentCmdID;
	}

	/**
	 * Setter for property sourceURI.
	 *
	 * @param string $sourceURI  New value of property sourceLocURI.
	 */
	function setSourceLocURI($sourceURI) {
		$this->_sourceLocURI = $sourceURI;
	}

	/**
	 * Setter for property targetURI.
	 *
	 * @param string $targetURI  New value of property targetLocURI.
	 */
	function setTargetLocURI($targetURI) {
		$this->_targetLocURI = $targetURI;
	}

	/**
	 * Setter for property syncType.
	 *
	 * @param integer $syncType  New value of property syncType.
	 */
	function setSyncType($syncType)	{
		$this->_syncType = $syncType;
	}

	/**
	 * Setter for property locName.
	 *
	 * @param string $locName  New value of property locName.
	 */
	function setLocName($locName) {
		$this->_locName = $locName;
	}

	/**
	 * Setter for property filterExpression.
	 *
	 * @param string $expression  New value of property filterExpression.
	 */
	function setFilterExpression($expression) {
		$this->_filterExpression = $expression;
	}

	/**
	 * Here's where the actual processing of a client-sent Sync
	 * Command takes place. Entries are added, deleted or replaced
	 * from the server database by using Horde API (Registry) calls.
	 */
	function runSyncCommand(&$command) {
		global $registry;
		$history = $GLOBALS['egw']->contenthistory;
		$state = &$_SESSION['SyncML.state'];


		$type = $this->_targetLocURI;

		$syncml_prefs = $GLOBALS['egw_info']['user']['preferences']['syncml'];
		if (isset($syncml_prefs[$type])) {
			$sync_conflicts = $syncml_prefs[$type];
		} else {
			$sync_conflicts = CONFLICT_SERVER_WINNING;
		}

		$state->setLocName($this->_locName);
		$locName = $state->getLocName();
		$sourceURI = $state->getSourceURI();
		$hordeType = $state->getHordeType($type);
		$serverAnchorLast = $state->getServerAnchorLast($type);
		$changes = array();
		foreach($state->getChangedItems($type) as $change) {
			// now we have to remove the ones
			// that came from the last sync with this client
			$guid_ts = $state->getSyncTSforAction($change, 'modify');
			$sync_ts = $state->getChangeTS($type, $change);
			if ($sync_ts && $sync_ts == $guid_ts) {
				// Change was done by us upon request of client.
				Horde::logMessage("SyncML: change: $change ignored, " .
					"came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
				continue;
			}
			$changes[] = $change;
		}

		Horde::logMessage('SyncML: runSyncCommand found ' . count($changes) .
			" possible conflicts for $type", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if(!$contentType = $command->getContentType()) {
			$contentType = $state->getPreferedContentType($type);
		}

		if (($contentType == 'text/x-vcalendar' || $contentType == 'text/calendar')
				&& strpos($command->getContent(), 'BEGIN:VTODO') !== false) {
			$hordeType = 'tasks';
		}

		$syncElementItems = $command->getSyncElementItems();

		foreach($syncElementItems as $syncItem) {

			$guid = false;

			$contentSize = strlen($syncItem->_content);
			if ((($size = $syncItem->getContentSize()) !== false) &&
					abs($contentSize - $size) > 3) {
				Horde::logMessage('SyncML: content size mismatch for LocURI ' . $syncItem->_luid .
					": $contentSize ($size) : " . $syncItem->_content,
					__FILE__, __LINE__, PEAR_LOG_WARNING);
				//$command->setStatus(RESPONSE_SIZE_MISMATCH);
				continue;
			}

			if (is_a($command, 'Horde_SyncML_Command_Sync_Add')) {
				if ($sync_conflicts > CONFLICT_RESOLVED_WITH_DUPLICATE) {
					// We enforce the client not to change anything
					if ($sync_conflicts > CONFLICT_CLIENT_CHANGES_IGNORED) {
						// delete this item from client
						Horde::logMessage('SyncML: Server RO! REMOVE '
							. $syncItem->getLocURI() . ' from client',
							__FILE__, __LINE__, PEAR_LOG_WARNING);
						$state->addConflictItem($type, $syncItem->getLocURI());
					} else {
						Horde::logMessage('SyncML: Server RO! '
							. 'REJECT all client changes',
							__FILE__, __LINE__, PEAR_LOG_WARNING);
						$state->log('Client-AddReplaceIgnored');
					}
					continue;
				}

				$guid = $registry->call($hordeType . '/import',
					array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));

				if (!is_a($guid, 'PEAR_Error') && $guid != false) {
					$ts = $state->getSyncTSforAction($guid, 'add');
					$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
					$state->log('Client-Add');
					Horde::logMessage('SyncML: added client entry as '
						. $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
				} else {
					$state->log('Client-AddFailure');
					Horde::logMessage('SyncML: Error in adding client entry: '
						. $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
				}
			} elseif (is_a($command, 'Horde_SyncML_Command_Sync_Delete')) {
				$guid = $state->removeUID($type, $syncItem->getLocURI());
				if (!$guid) {
					// the entry is no longer on the server
					$state->log('Client-DeleteFailure');
					Horde::logMessage('SyncML: Failure deleting client entry, '
						. 'gone already on server!',
						__FILE__, __LINE__, PEAR_LOG_ERR);
					continue;
				}
				if ($sync_conflicts > CONFLICT_RESOLVED_WITH_DUPLICATE) {
					// We enforce the client not to change anything
					if ($sync_conflicts > CONFLICT_CLIENT_CHANGES_IGNORED) {
						Horde::logMessage('SyncML: Server RO! ADD '
							. $guid . ' to client again',
							__FILE__, __LINE__, PEAR_LOG_WARNING);
						$state->pushAddedItem($type, $guid);
					} else {
						Horde::logMessage('SyncML: '.
							'Server RO! REJECT all client changes',
							__FILE__, __LINE__, PEAR_LOG_WARNING);
					}
					$state->log('Client-DeleteIgnored');
					continue;
				}
				elseif ($sync_conflicts == CONFLICT_RESOLVED_WITH_DUPLICATE &&
					in_array($guid, $changes))
				{
					Horde::logMessage('SyncML: '.
						'Server has updated version to keep',
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					$state->log('Client-DeleteIgnored');
					continue;
				}
				elseif ($sync_conflicts == CONFLICT_MERGE_DATA)
				{
					Horde::logMessage('SyncML: Server Merge Only: ADD '
							. $guid . ' to client again',
							__FILE__, __LINE__, PEAR_LOG_WARNING);
					$state->pushAddedItem($type, $guid);
					$state->log('Client-DeleteIgnored');
					continue;
				}

				Horde::logMessage('SyncML: about to delete entry '
					. $type .' / '. $guid . ' due to client request '
					. $syncItem->getLocURI(), __FILE__, __LINE__, PEAR_LOG_DEBUG);

				if (!is_a($guid, 'PEAR_Error') && $guid != false) {
					$registry->call($hordeType . '/delete', array($guid));
					$ts = $state->getSyncTSforAction($guid, 'delete');
					$state->setUID($type, $syncItem->getLocURI(), $guid, $ts, 1);
					$state->log('Client-Delete');
					Horde::logMessage('SyncML: deleted entry '
						. $guid . ' due to client request',
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
				} else {
					$state->log('Client-DeleteFailure');
					Horde::logMessage('SyncML: Failure deleting client entry, '
						. 'maybe gone already on server. msg: '
						. $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
				}
			} elseif (is_a($command, 'Horde_SyncML_Command_Sync_Replace')) {
				$guid = $state->getGlobalUID($type, $syncItem->getLocURI());
				$replace = true;
				$ok = false;
				$merge = false;
				if ($guid)
				{
					Horde::logMessage('SyncML: locuri '. $syncItem->getLocURI() . ' guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_DEBUG);
					if (($sync_conflicts > CONFLICT_RESOLVED_WITH_DUPLICATE) || in_array($guid, $changes))
					{
						Horde::logMessage('SyncML: CONFLICT for locuri '. $syncItem->getLocURI() . ' guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_WARNING);
						switch ($sync_conflicts)
						{
							case CONFLICT_CLIENT_WINNING:
								$command->setStatus(RESPONSE_CONFLICT_RESOLVED_WITH_CLIENT_WINNING);
								break;
							case CONFLICT_SERVER_WINNING:
								Horde::logMessage('SyncML: REJECT client change for locuri ' .
									$syncItem->getLocURI() . ' guid ' . $guid ,
									__FILE__, __LINE__, PEAR_LOG_WARNING);
								$ok = true;
								$replace = false;
								$state->log('Client-AddReplaceIgnored');
								break;
							case CONFLICT_MERGE_DATA:
								Horde::logMessage('SyncML: Merge server and client data for locuri ' .
									$syncItem->getLocURI() . ' guid ' . $guid ,
									__FILE__, __LINE__, PEAR_LOG_WARNING);
							    $merge = true;
								break;
							case CONFLICT_RESOLVED_WITH_DUPLICATE:
								$replace = false;
								break;
							case CONFLICT_CLIENT_CHANGES_IGNORED:
								Horde::logMessage('SyncML: Server RO! REJECT client change for locuri ' .
									$syncItem->getLocURI() . ' guid ' . $guid ,
									__FILE__, __LINE__, PEAR_LOG_WARNING);
								$ok = true;
								$replace = false;
								$ts = $state->getSyncTSforAction($guid, 'modify');
								$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
								$state->log('Client-AddReplaceIgnored');
								break;
							default: // We enforce our data on client
								Horde::logMessage('SyncML: Server RO! UNDO client change for locuri ' .
									$syncItem->getLocURI() . ' guid ' . $guid ,
									__FILE__, __LINE__, PEAR_LOG_WARNING);
								$state->pushChangedItem($type, $guid);
								$ok = true;
								$replace = false;
						}
					}
					elseif ($sync_conflicts == CONFLICT_MERGE_DATA)
					{
						Horde::logMessage('SyncML: Merge server and client data for locuri ' .
							$syncItem->getLocURI() . ' guid ' . $guid ,
							__FILE__, __LINE__, PEAR_LOG_WARNING);
						$merge = true;
					}

					if ($replace)
					{
						// Entry exists: replace/merge with current one.
						$ok = $registry->call($hordeType . '/replace',
							array($guid, $state->convertClient2Server($syncItem->getContent(),
							$contentType), $contentType, $type, $merge));
						if (!is_a($ok, 'PEAR_Error') && $ok != false)
						{
							$ts = $state->getSyncTSforAction($guid, 'modify');
							$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
							if ($merge)
							{
								Horde::logMessage('SyncML: Merged entry due to client request guid: ' .
									$guid . ' ts: ' . $ts, __FILE__, __LINE__, PEAR_LOG_DEBUG);
							}
							else
							{
								Horde::logMessage('SyncML: replaced entry due to client request guid: ' .
									$guid . ' ts: ' . $ts, __FILE__, __LINE__, PEAR_LOG_DEBUG);
							}
							$state->log('Client-Replace');
							$ok = true;
						}
						else
						{
							// Entry may have been deleted; try adding it.
							$ok = false;
						}
					}
				}

				if (!$ok) {
					// Entry does either not exist in map or database, or should be added due to a conflict

					if ($sync_conflicts > CONFLICT_RESOLVED_WITH_DUPLICATE) {
						// We enforce the client not to change anything
						if ($sync_conflicts > CONFLICT_CLIENT_CHANGES_IGNORED) {
							// delete this item from client
							Horde::logMessage('SyncML: Server RO! REMOVE ' . $syncItem->getLocURI() . ' from client',
								__FILE__, __LINE__, PEAR_LOG_WARNING);
							$state->addConflictItem($type, $syncItem->getLocURI());
						} else {
							Horde::logMessage('SyncML: Server RO! REJECT all client changes',
								__FILE__, __LINE__, PEAR_LOG_WARNING);
						}
						continue;
					}
					Horde::logMessage('SyncML: try to add contentype '
						. $contentType . ' for locuri ' . $syncItem->getLocURI(),
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					//continue;
					$oguid = $guid;
					$guid = $registry->call($hordeType . '/import',
						array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType, $guid));
					if (!is_a($guid, 'PEAR_Error')) {
						if ($oguid != $guid) {
							// We add a new entry
							$ts = $state->getSyncTSforAction($guid, 'add');
							Horde::logMessage('SyncML: added entry '
								. $guid . ' from client',
								__FILE__, __LINE__, PEAR_LOG_DEBUG);
							$state->log('Client-Add');
						} else {
							// We replaced an entry
							$ts = $state->getSyncTSforAction($guid, 'modify');
							Horde::logMessage('SyncML: replaced entry '
								. $guid . ' from client',
								__FILE__, __LINE__, PEAR_LOG_DEBUG);
							$state->log('Client-Replace');
						}
						$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
					} else {
						Horde::logMessage('SyncML: Error in replacing/'
							. 'add client entry:' . $guid->message,
							__FILE__, __LINE__, PEAR_LOG_ERR);
						$state->log('Client-AddFailure');
					}
				}
			}
		}
	}
}
