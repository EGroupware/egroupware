<?php
/**
 * EGroupware API: VFS sharing
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

require_once('./phpgwapi/inc/class.egw_sharing.inc.php');

// set PHP_AUTH_USER from token in URL, must be called before header include, to be able to verify session
egw_sharing::init();

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader'  => true,
		'nonavbar' => 'always',	// true would cause eTemplate to reset it to false for non-popups!
		'currentapp' => 'filemanager',
		'autocreate_session_callback' => 'egw_sharing::create_session',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	)
);

include('./header.inc.php');

if (!$GLOBALS['egw']->sharing)
{
	egw_sharing::create_session();
}
$GLOBALS['egw']->sharing->ServeRequest();
