<?php
/**
 * EGroupware - download document merged with contact(s)
 *
 * Usage: curl --user $username[:$passwd] -L https://domain.com/egroupware/addressbook/merge.php?path=/templates/addressbook/document.txt&ids=123
 *
 * Supported GET parameters:
 * - path: full VFS path of document to merge
 * - ids: one or more id(s): ids[]=123&ids[]=456
 * - search: search criteria or array with field specific criteria, eg. search[account_id]=123
 * - limit: max. number of search results to return, default 1
 * - order: default last modified first
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
 * @copyright (c) 2015-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\Contacts\Merge;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'addressbook',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'autocreate_session_callback' => 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback',
		'auth_realm' => 'EGroupware document merge',
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(dirname(__FILE__));
include($egw_dir.'/header.inc.php');

$merge = new Merge();

if (!isset($_REQUEST['ids']) && isset($_REQUEST['search']))
{
	if (is_array($_REQUEST['search']))
	{
		$criteria = array();
		foreach($_REQUEST['search'] as $name => $value)
		{
			if (isset($merge->contacts->contact_fields[$name]))
			{
				$criteria[$name] = $value;
			}
		}
		$wildcard = '';
	}
	else
	{
		$criteria = $_REQUEST['search'];
		$wildcard = '*';
	}
	$order_by = 'contact_modified DESC';
	if (isset($_REQUEST['order']) && preg_match('/^[a-z0-9_, ]+$/', $_REQUEST['order']))
	{
		$order_by = $_REQUEST['order'];
	}
	$ids = array();
	foreach($merge->contacts->search($criteria, true, $order_by, '', $wildcard, true, 'AND',
		array(0, isset($_REQUEST['limit']) && $_REQUEST['limit'] > 1 ? (int)$_REQUEST['limit'] : 1)) as $row)
	{
		$ids[] = $row['id'];
	}
}
else
{
	$ids = (array)$_REQUEST['ids'];
}
if (($err = $merge->download($_REQUEST['path'], $ids)))
{
	header("HTTP/1.1 500 $err");
}
