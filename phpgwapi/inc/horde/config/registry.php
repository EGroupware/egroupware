<?php
/**
 * registry.php -- Horde application registry.
 *
 * $Horde: horde/config/registry.php.dist,v 1.243 2004/10/05 20:08:13 chuck Exp $
 *
 * This configuration file is used by Horde to determine which Horde
 * applications are installed and where, as well as how they interact.
 *
 * Application registry
 * --------------------
 * The following settings register installed Horde applications.
 * By default, Horde assumes that the application directories live
 * inside the horde directory.
 *
 * Attribute     Type     Description
 * ---------     ----     -----------
 * fileroot      string   The base filesystem path for the module's files
 * webroot       string   The base URI for the module
 * graphics      string   The base URI for the module images
 * icon          string   The URI for an icon to show in menus for the module
 * name          string   The name used in menus and descriptions for a module
 * status        string   'inactive', 'hidden', 'notoolbar', 'heading',
 *                        'block', 'admin', or 'active'.
 * provides      string   Service types the module provides.
 * initial_page  string   The initial (default) page (filename) for the module
 * templates     string   The filesystem path to the templates directory
 * menu_parent   string   The name of the 'heading' group that this app should
 *                        show up under.
 * target        string   The (optional) target frame for the link.
 */

// We try to automatically determine the proper webroot for Horde
// here. This still assumes that applications live under horde/. If
// this results in incorrect results for you, simply change the two
// uses of the $webroot variable in the 'horde' stanza below.
//
// Note for Windows users: the below assumes that your PHP_SELF
// variable uses forward slashes. If it does not, you'll have to tweak
// this.
if (isset($_SERVER['PHP_SELF'])) {
    $parts = preg_split(';/;', $_SERVER['PHP_SELF'], 2, PREG_SPLIT_NO_EMPTY);
    $webroot = strstr(dirname(__FILE__), '/' . array_shift($parts));
    if ($webroot !== false) {
        $webroot = preg_replace(';/config$;', '', $webroot);
    } else {
        $webroot = '/horde';
    }
} else {
    $webroot = '/horde';
}

$this->applications['horde'] = array(
    'fileroot' => dirname(__FILE__) . '/..',
    'webroot' => $webroot,
    'initial_page' => 'login.php',
    'icon' => $webroot . '/graphics/horde.png',
    'name' => _("Horde"),
    'status' => 'active',
    'templates' => dirname(__FILE__) . '/../templates',
    'provides' => 'horde'
);

#$this->applications['mnemo'] = array(
#    'fileroot' => dirname(__FILE__) . '/../mnemo',
#    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
#    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
#    'name' => _("Notes"),
#    'status' => 'active',
#    'provides' => 'notes',
#    'menu_parent' => 'organizing'
#);

$this->applications['egwnotessync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/notes',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Notes"),
    'status' => 'active',
    'provides' => array('notes', 'sifnotes', 'snote'),
    'menu_parent' => 'organizing'
);

$this->applications['egwcontactssync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/contacts',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Contacts"),
    'status' => 'active',
    'provides' => array('contacts', 'sifcontacts', 'scard', 'card'),
    'menu_parent' => 'organizing'
);

#$this->applications['egwsifcontactssync'] = array(
#    'fileroot' => EGW_SERVER_ROOT.'/syncml/sifcontacts',
#    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
#    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
#    'name' => _("SIF Contacts"),
#    'status' => 'active',
#    'provides' => 'sifcontacts',
#    'menu_parent' => 'organizing'
#);

$this->applications['egwcalendarsync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/calendar',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Calendar"),
    'status' => 'active',
    'provides' => array('calendar', 'sifcalendar', 'scal', 'events'),
    'menu_parent' => 'organizing'
);

#$this->applications['egwsifcalendarsync'] = array(
#    'fileroot' => EGW_SERVER_ROOT.'/syncml/sifcalendar',
#    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
#    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
#    'name' => _("Calendar"),
#    'status' => 'active',
#    'provides' => 'sifcalendar',
#    'menu_parent' => 'organizing'
#);

$this->applications['egwtaskssync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/tasks',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Tasks"),
    'status' => 'active',
    'provides' => array('tasks', 'siftasks', 'stask', 'jobs'),
    'menu_parent' => 'organizing'
);

#$this->applications['egwsiftaskssync'] = array(
#    'fileroot' => EGW_SERVER_ROOT.'/syncml/siftasks',
#    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
#    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
#    'name' => _("SIFTasks"),
#    'status' => 'active',
#    'provides' => array('siftasks', 'stask'),
#    'menu_parent' => 'organizing'
#);

$this->applications['egwcaltaskssync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/caltasks',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Calendar and Tasks"),
    'status' => 'active',
    'provides' => 'caltasks',
    'menu_parent' => 'organizing'
);

$this->applications['egwconfigurationsync'] = array(
    'fileroot' => EGW_SERVER_ROOT.'/syncml/configuration',
    'webroot' => $this->applications['horde']['webroot'] . '/mnemo',
    'icon' => $this->applications['horde']['webroot'] . '/mnemo/graphics/mnemo.gif',
    'name' => _("Funambol Configurations"),
    'status' => 'active',
    'provides' => array('configuration'),
    'menu_parent' => 'organizing'
);

