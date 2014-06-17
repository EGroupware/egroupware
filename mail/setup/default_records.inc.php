<?php
/**
 * EGroupware - Mail - setup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage setup
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2014 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// change felamimail run rights to new mail app
$GLOBALS['egw_setup']->db->update('egw_acl', array(
	'acl_appname' => 'mail',
), array(
	'acl_appname' => 'felamimail',
	'acl_location' => 'run',
), __LINE__, __FILE__);

// if no rows/rights found, give Default group rights
if (!$GLOBALS['egw_setup']->db->affected_rows())
{
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
	$GLOBALS['egw_setup']->add_acl('mail','run',$defaultgroup);
}

// change common/default_app pref to mail, if it was felamimail
preferences::change_preference('common', 'default_app', 'mail', 'felamimail');