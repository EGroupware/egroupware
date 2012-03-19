<?php
/**
 * API: loading translation from from browser
 *
 * Usage: /egroupware/phpgwapi/lang.php?app=infolog&lang=de
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// just to be sure, noone tries something nasty ...
if (!preg_match('/^[a-z0-9_]+$/i', $_GET['app'])) die('No valid application-name given!');
if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $_GET['lang'])) die('No valid lang-name given!');

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => in_array($_GET['app'],array('etemplate','common','custom')) ? 'home' : $_GET['app'],
		'noheader' => true,
		'load_translations' => false,	// do not automatically load translations
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// use an etag with app, lang and a hash over the creation-times of all lang-files
if (!in_array($_GET['app'], translation::$instance_specific_translations))
{
	$etag = '"'.$_GET['app'].'-'.$_GET['lang'].'-'.md5(serialize($GLOBALS['egw_info']['server']['lang_ctimes'])).'"';
}
else
{
	translation::add_app($_GET['app'], $_GET['lang']);
	if (!count(translation::$lang_arr))
	{
		translation::add_app($_GET['app'], 'en');
	}
	$etag = '"'.$_GET['app'].'-'.$_GET['lang'].'-'.md5(serialize(translation::$lang_arr));
}

// headers to allow caching of one month
Header('Content-Type: text/javascript; charset=utf-8');
Header('Cache-Control: public, no-transform');
Header('Pragma: cache');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

if (!in_array($_GET['app'], translation::$instance_specific_translations))
{
	translation::add_app($_GET['app'], $_GET['lang']);
	if (!count(translation::$lang_arr))
	{
		translation::add_app($_GET['app'], 'en');
	}
}

$content = 'egw.set_lang_arr("'.$_GET['app'].'", '.json_encode(translation::$lang_arr).');';

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
