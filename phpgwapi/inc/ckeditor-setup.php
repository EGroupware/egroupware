<?php
/**
 * eGroupWare - API ckeditor setup (set up ckeditor with user prefs)
 *
 * @link http://www.egroupware.org
 * @author RalfBecker-AT-outdoor-training.de
 * @author Andreas Stoeckel <as-AT-stylite.de>
 * @package api
 * @subpackage tools
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

//Disable framework and cachecontrol
$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'  => 'home',
		'noheader'    => True,
		'nonavbar'    => True,
		'noappheader' => True,
		'noappfooter' => True,
		'nofooter'    => True,
		'nocachecontrol' => True			// allow caching
	)
);

try {
	include('../../header.inc.php');
} 
catch (egw_exception_no_permission_app $e) {
	// ignore exception, if home is not allowed, eg. for sitemgr
}

include('../../header.inc.php');

header('Content-type: text/javascript; charset='.translation::charset());

/**
 * Returns an array which contains both, the current user language and the current
 * user country setting.
 */
function get_lang_country()
{
	//use the lang and country information to construct a possible lang info for CKEditor UI and scayt_slang
	$lang = ($GLOBALS['egw_info']['user']['preferences']['common']['spellchecker_lang'] ?
		$GLOBALS['egw_info']['user']['preferences']['common']['spellchecker_lang']:
		$GLOBALS['egw_info']['user']['preferences']['common']['lang']);

	$country = $GLOBALS['egw_info']['user']['preferences']['common']['country'];

	if (!(strpos($lang,'-')===false))
		list($lang,$country) = explode('-',$lang);

	return array(
		'lang' => $lang,
		'country' => $country
	);
}

/**
 * Returns the ckeditor basepath
 */
function get_base_path()
{
	//Get the ckeditor base url
	return $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/ckeditor3/';
}

/**
 * Returns the ckeditor enter mode which defaults to "BR"
 */
function get_enter_mode()
{
	//Get the input name
	$enterMode = "CKEDITOR.ENTER_BR";
	if (isset($GLOBALS['egw_info']['user']['preferences']['common']['rte_enter_mode']))
	{
		switch ($GLOBALS['egw_info']['user']['preferences']['common']['rte_enter_mode'])
		{
			case 'p':
				$enterMode = "CKEDITOR.ENTER_P";
				break;
			case 'br':
				$enterMode = "CKEDITOR.ENTER_BR";
				break;
			case 'div':
				$enterMode = "CKEDITOR.ENTER_DIV";
				break;
		}
	}

	return $enterMode;
}

/**
 * Returns the skin the ckeditor should us
 */
function get_skin()
{
	//Get the skin name
	$skin = $GLOBALS['egw_info']['user']['preferences']['common']['rte_skin'];

	//Convert old fckeditor skin names to new ones
	switch ($skin)
	{
		case 'silver':
			$skin = "v2";
			break;
		case 'default':
			$skin = "kama";
			break;
		case 'office2003':
			$skin = "office2003";
			break;				
	}

	//Check whether the skin actually exists, if not, switch to a default
	if (!(file_exists($basePath.'skins/'.$skin) || file_exists($skin) || !empty($skin)))
		$skin = "office2003";

	return $skin;
}

/**
 * Returns a string containing the ckeditor spellchecker settings.
 */
function get_spellcheck_settings(&$spell)
{
	// Now setting the admin settings
	$res = '';
	$spell = false;
	$lang_country = get_lang_country();
	if (isset($GLOBALS['egw_info']['server']['enabled_spellcheck']))
	{
		$spell = ",'SpellChecker'";
		if (!empty($GLOBALS['egw_info']['server']['aspell_path']) &&
			is_executable($GLOBALS['egw_info']['server']['aspell_path']))
		{
			$spell = ",'SpellCheck'";
			$res .= '	config.extraPlugins = "aspell";'."\n";
		}
		else
		{
			$res .= "	config.scaty_autoStartup = true;\n";
			$res .= "	config.scaty_sLang = '".$lang_country['lang'].'_'.strtoupper($lang_country['country'])."';\n";
		}
	}

	return $res;
}

/**
 * Returns a string containing the ckeditor toolbar settings
 */
function get_toolbar($toolbar, $spell)
{
	switch ($toolbar)
	{
		case 'simple':
			return
"	config.toolbar = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Maximize'".($spell ? $spell : '')."],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	];\n";

		case 'extended':
			return
"	config.toolbar = [
		['Bold','Italic','Underline'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','Outdent','Indent','Undo','Redo'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'],
		['Link','Unlink','Anchor'],
		['Find','Replace'],
		['Maximize'".($spell ? $spell : '').",'Image','Table'],
		'/',
		['Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	];\n";

		case 'advanced':
			return
"	config.toolbar = [
		['Source','DocProps','-','Save','NewPage','Preview','-','Templates'],
		['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'".($spell ? $spell : '')."],
		['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
		'/',
		['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['BulletedList','NumberedList','-','Outdent','Indent'],
		['Link','Unlink','Anchor'],
		['Maximize','Image','SpecialChar','PageBreak'],
		'/',
		['Style','Format','Font','FontSize'],
		['TextColor','BGColor'],
		['ShowBlocks','-','About']
	];\n";
	}
}
		
//Initialize the user parameters
$height = '400px';
$width = '100%';
$start_path = '';
$expanded_toolbar = true;
$mode = 'simple';

//Read some parameters from the $_GET array
if (isset($_GET['height']))
	$height = htmlspecialchars($_GET['height']);
if (isset($_GET['width']))
	$width = htmlspecialchars($_GET['width']);
if (isset($_GET['start_path']))
	$start_path = urlencode($_GET['start_path']);
if (isset($_GET['expanded_toolbar']))
	$expanded_toolbar = $_GET['expanded_toolbar'] == '1';
if (isset($_GET['mode']) && in_array($_GET['mode'], array('simple', 'advanced', 'extended')))
	$mode = $_GET['mode'];

$lang_country = get_lang_country();
$spell = false;

//Output the configuration
echo(
'
/**
 * CKEditor user based configuration
 */

window.CKEDITOR_BASEPATH = "'.get_base_path().'";

CKEDITOR.editorConfig = function(config)
{
	config.resize_enabled = false;

	config.entities = true;
	config.entities_latin = true;
	config.entities_processNumerical = true;

	config.editingBlock = true;

	config.filebrowserBrowseUrl = "'.$GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=filemanager.filemanager_select.select&mode=open&method=ckeditor_return'
	.($start_path != '' ? '&path='.$start_path : '').'";
	config.filebrowserWindowWidth = "640";
	config.filebrowserWindowHeight = "580";

	config.language = "'.$lang_country['lang'].'";

	config.expanded_toolbar = '.($expanded_toolbar ? 'true' : 'false').';

	config.height = "'.$height.'";

	config.enterMode = '.get_enter_mode().';
	config.skin = "'.get_skin().'";
'.get_spellcheck_settings($spell).
'	config.disableNativeSpellchecker = true;
'.get_toolbar($mode, $spell).'}'
);


