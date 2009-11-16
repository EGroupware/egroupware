<?php

include_once 'Horde/SyncML/Sync/SlowSync.php';

/**
 * $Horde: framework/SyncML/SyncML/Sync/RefreshFromClientSync.php,v 1.8 2004/09/14 04:27:06 chuck Exp $
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
class Horde_SyncML_Sync_RefreshFromClientSync extends Horde_SyncML_Sync_SlowSync {
    /**
     * We needed to erase the current server contents, then we can add
     * the client's contents.
     */
}
