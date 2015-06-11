<?php
/**
 * EGroupware - download document merged with contact(s)
 *
 * Usage: curl --user $username[:$passwd] -L https://domain.com/egroupware/addressbook/merge.php?path=/templates/addressbook/document.txt&ids=123
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage addressbook
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2015 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'addressbook',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'autocreate_session_callback' => array('egw_digest_auth','autocreate_session_callback'),
		'auth_realm' => 'EGroupware document merge',
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(dirname(__FILE__));
require_once($egw_dir.'/phpgwapi/inc/class.egw_digest_auth.inc.php');
include($egw_dir.'/header.inc.php');

$merge = new addressbook_merge();
if (($err = $merge->download($_REQUEST['path'], $_REQUEST['ids'])))
{
	header("HTTP/1.1 500 $err");
}
