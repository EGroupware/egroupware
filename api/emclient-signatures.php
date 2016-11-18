<?php
/**
 * EGroupware signatures for eM Client
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
 * @subpackage mail
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2016 by Ralf Becker <rb-AT-egroupware.org>
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'mail',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'autocreate_session_callback' => 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback',
		// use same REALM as CalDAV/CardDAV eM Client already uses
		'auth_realm' => 'EGroupware CalDAV/CardDAV/GroupDAV server',	// cant use groupdav::REALM as autoloading and include path not yet setup!
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(__DIR__);
include($egw_dir.'/header.inc.php');

header('Content-type: text/xml; charset=UTF-8');

$xml = new XMLWriter;
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('signatures');

foreach(Api\Mail\Account::identities(array(), true, 'params') as $identity)
{
	$xml->startElement('signature');
	$xml->writeAttribute('name', Api\Mail\Account::identity_name($row, true));
	$xml->writeAttribute('allow-edit', 'true');
	$xml->writeAttribute('overwrite', 'true');
	$xml->writeAttribute('targetMail', $identity['ident_email'].' <mailto:'.$identity['ident_email'].'>');
	$xml->writeCdata($identity['ident_signature']);
	$xml->endElement();
}
$xml->endElement();
$xml->endDocument();

echo $xml->outputMemory();
