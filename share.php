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

require_once(__DIR__.'/api/src/Vfs/Sharing.php');

use EGroupware\Api\Vfs\Sharing;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader'  => true,
		'nonavbar' => 'always',	// true would cause eTemplate to reset it to false for non-popups!
		'currentapp' => 'filemanager',
		'autocreate_session_callback' => 'EGroupware\\Api\\Vfs\\Sharing::create_session',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	)
);

include('./header.inc.php');

if (!$GLOBALS['egw']->sharing)
{
	Sharing::create_session(true);	// true = mount into existing session
}
$GLOBALS['egw']->sharing->ServeRequest();
