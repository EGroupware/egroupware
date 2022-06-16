<?php
/**
 * API: loading categories and setting styles
 *
 * Usage: /egroupware/api/categories.php[?app=calendar]
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'api',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// release session, as we dont need it and it blocks parallel requests
$GLOBALS['egw']->session->commit_session();

// Get appname
$appname = $_GET['app'] && $GLOBALS['egw_info']['apps'][$_GET['app']] ? $_GET['app'] : Api\Categories::GLOBAL_APPNAME;

$cats = new Api\Categories('', $appname);
$categories = $cats->return_array('all',0, false, '', 'ASC','',$appname==Api\Categories::GLOBAL_APPNAME);

$content = "/* Category CSS for $appname */\n\n";

foreach($categories as $cat)
{
	if (!isset($cat['data'])) continue;
	if (!empty($cat['data']['color']))
	{
		// Use slightly more specific selector that just class, to allow defaults
		// if the category has no color
		$content .= "/** {$cat['name']} **/\n/*webComponent*/\n";
		$content .= ":root,:host {--cat-{$cat['id']}-color: {$cat['data']['color']};}, :host.cat_{$cat['id']}, .cat_{$cat['id']} {--category-color: {$cat['data']['color']};} et2-select-cat > .cat_{$cat['id']} {--category-color: {$cat['data']['color']};} \n";
		$content .= "/*legacy*/\n.egwGridView_scrollarea tr.row_category.cat_{$cat['id']} > td:first-child, .select-cat li.cat_{$cat['id']}, .et2_selectbox ul.chzn-results li.cat_{$cat['id']}, .et2_selectbox ul.chzn-choices li.cat_{$cat['id']}, .nextmatch_header_row .et2_selectbox.select-cat.cat_{$cat['id']} a.chzn-single {border-left-color: {$cat['data']['color']};} .cat_{$cat['id']}.fullline_cat_bg, div.cat_{$cat['id']}, span.cat_{$cat['id']} { background-color: {$cat['data']['color']};}  \n";
	}
	if (!empty($cat['data']['icon']))
	{
		$content .= ".cat_{$cat['id']} .cat_icon { background-image: url('". admin_categories::icon_url($cat['data']['icon']) ."');} /*{$cat['name']}*/\n";
	}
}

// use an etag over categories
$etag = '"'.md5($content).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
Api\Session::cache_control(86400);	// cache for 1 day
Header('Content-Type: text/css; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
