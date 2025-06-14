<?php
/**
 * EGroupware - callback for jmap push subscriptions
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2025 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array('flags' => array(
	'disable_Template_class'  => True,
	'noheader'                => True,
	// misuse session creation callback to send the image, in case we have no session
	'autocreate_session_callback' => 'handle_push',
	'currentapp'              => 'api',
));

require('../header.inc.php');

handle_push();

function handle_push()
{
	Api\Mail\Imap\Jmap::pushCallback();
	exit;
}