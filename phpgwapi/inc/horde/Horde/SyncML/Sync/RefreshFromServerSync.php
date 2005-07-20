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
class Horde_SyncML_Sync_RefreshFromServerSync extends Horde_SyncML_Sync {

    function endSync($currentCmdID, &$output)
    {
        global $registry;
        $state = &$_SESSION['SyncML.state'];
        
        // counter for synced items
        $syncItems = 0;

	if(!$adds = &$state->getAddedItems($hordeType))
	{
		Horde::logMessage("SyncML: reading list of added items", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$adds = $registry->call($this->targetLocURI, '/list', array());
		$adds = &$state->getAddedItems($hordeType);
	}
	Horde::logMessage("SyncML: ....... ".count($adds).' items to send ..............', __FILE__, __LINE__, PEAR_LOG_DEBUG);
	
	#foreach ($add as $adds) {
	while($guid = array_shift($adds))
	{
            $locid = $this->_currentState->getLocID($this->targetLocURI, $guid);
            // Add a replace.
            $add = &new Horde_SyncML_Command_Sync_ContentSyncElement();

            $add->setContent($registry->call($this->targetLocURI . '/listByAction',
                                             array($this->_currentState->getPreferedContentType($this->targetLocURI))));
                                             
		$currentCmdID = $add->outputCommand($currentCmdID, $output, 'Add');
		
		$syncItems++;
		// return if we have to much data
		if($syncItems >= MAX_ENTRIES)
		{
			$state->setMoreDataPending();
			return $currentCmdID;
		}
	}
	
	// TODO deletes
	
	// TODO modifies

        return $currentCmdID;
    }

}
