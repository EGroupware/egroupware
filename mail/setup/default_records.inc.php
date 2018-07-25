<?php
/**
 * EGroupware - Mail - setup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage setup
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2014-16 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

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
$prefs = new Api\Preferences();
$prefs->read_repository(false);
$prefs->add('mail', 'nextmatch-mail.index.rows-autorefresh', '300', 'default');
$prefs->save_repository(false, 'default');

// change common/default_app pref to mail, if it was felamimail
Api\Preferences::change_preference('common', 'default_app', 'mail', 'felamimail');

// copy felamimail Api\Preferences to new mail app, if they still exist there
Api\Preferences::copy_preferences('felamimail', 'mail', array(
	'htmlOptions',
	'allowExternalIMGs',
	'message_forwarding',
	'composeOptions',
	'replyOptions',
	'disableRulerForSignatureSeparation',
	'insertSignatureAtTopOffMessage',
	'attachVCardAtCompose',
	'deleteOptions',
	'sendOptions',
	'trustServerUnseenInfo',
	'showAllFoldersInFolderPane',
	'prefaskformove',
	'saveAsOptions',
));