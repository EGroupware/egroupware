<?php
/**
 * Horde Application Framework core services file.
 *
 * This file sets up any necessary include path variables and includes
 * the minimum required Horde libraries.
 *
 * $Horde: horde/lib/core.php,v 1.27 2005/01/03 14:35:14 jan Exp $
 *
 * Copyright 1999-2005 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

/* Turn PHP stuff off that can really screw things up. */
ini_set('magic_quotes_sybase', 0);
ini_set('magic_quotes_runtime', 0);

/* If the Horde Framework packages are not installed in PHP's global
 * include_path, you must add an ini_set() call here to add their location to
 * the include_path. */
// ini_set('include_path', dirname(__FILE__) . PATH_SEPARATOR . ini_get('include_path'));
//set_include_path(dirname(__FILE__). '/../../horde/' . PATH_SEPARATOR . dirname(__FILE__). '/../../../../egw-pear/' . PATH_SEPARATOR . get_include_path());

@define('EGW_BASE', dirname(dirname(__FILE__) . '/../../../../rpc.php'));
// Check for a prior definition of HORDE_BASE (perhaps by an
// auto_prepend_file definition for site customization).
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', EGW_BASE . '/phpgwapi/inc/horde/');
}

set_include_path(HORDE_BASE . PATH_SEPARATOR . EGW_BASE . '/egw-pear/' . PATH_SEPARATOR . get_include_path());
/* PEAR base class. */
include_once 'PEAR.php';

/* Horde core classes. */
include_once 'Horde.php';
include_once 'Horde/Registry.php';
#include_once 'Horde/DataTree.php';
include_once 'Horde/String.php';
include_once 'Horde/Date.php';
include_once 'Horde/NLS.php';
include_once 'Horde/iCalendar.php';
//include_once 'Horde/Notification.php';
//include_once 'Horde/Auth.php';
//include_once 'Horde/Browser.php';
//include_once 'Horde/Perms.php';

/* Browser detection object. *
if (class_exists('Browser')) {
    $browser = &Browser::singleton();
}
*/