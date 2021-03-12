<?php
/**
 * EGroupware API: VFS sharing
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

require_once(__DIR__.'/api/src/Sharing.php');

use EGroupware\Api\Sharing;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader'  => true,
		'nonavbar' => 'always',	// true would cause eTemplate to reset it to false for non-popups!
		'currentapp' => 'api',
		'autocreate_session_callback' => 'EGroupware\\Api\\Sharing::create_session',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	)
);

include('./header.inc.php');

if (!isset($GLOBALS['egw']->sharing) || !array_key_exists(Sharing::get_token(), $GLOBALS['egw']->sharing))
{
	Sharing::create_session(true);	// true = mount into existing session
}
$GLOBALS['egw']->sharing[Sharing::get_token()]->ServeRequest();
