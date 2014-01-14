<?php
/**
 * EGroupware - Mail - setup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage setup
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// give Default group rights for new mail app
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
$GLOBALS['egw_setup']->add_acl('mail','run',$defaultgroup);
