<?php
/**
 * EGroupware - download of customized java notifier
 *
 * @link http://www.egroupware.org
 * @author RalfBecker-at-outdoor-training.de
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'notifications',
		'noheader'		=> True,
		'nonavbar'		=> True,
	)
);

ini_set('zlib.output_compression',0);
include('../header.inc.php');

ob_start();

check_load_extension('zip', true);

$document = EGW_SERVER_ROOT.'/notifications/java/full-eGwNotifier.jar';
$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.jar').'-').'.jar';
$ret=copy($document, $archive);
error_log("copy('$document', '$archive' returned ".array2string($ret));
$document = 'zip://'.$archive.'#'.($config_file = 'lib/conf/egwnotifier.const.xml');

$xml_in = file_get_contents($document);
//Api\Header\Content::type('egwnotifier.const.xml', 'application/xml', bytes($xml_in)); echo $xml_in; exit;

function replace_callback($matches)
{
	$replacement = $matches[3];
	switch($matches[1])
	{
		case 'egw_dc_url':
			$replacement = Api\Framework::getUrl($GLOBALS['egw_info']['server']['webserver_url']);
			break;
		case 'egw_dc_logindomain':
			$replacement = $GLOBALS['egw_info']['user']['domain'];
			break;
		case 'egw_dc_username':
			$replacement = $GLOBALS['egw_info']['user']['account_lid'];
			break;
		case 'egw_dc_timeout_socket':
		case 'egw_dc_timeout_notify':
		case 'egw_debuging':
		case 'egw_debuging_level':
			break;
		default:
			$replacement = lang($r=$replacement);
			/* uncomment this to have missing translations add to en langfile
			// if no translation found, check if en langfile is writable and add phrase, if not already there
			if ($r === $replacement)
			{
				static $langfile;
				if (is_null($langfile)) $langfile = EGW_SERVER_ROOT.'/notifications/lang/egw_en.lang';
				if (is_writable($langfile) || is_writable(dirname($langfile)))
				{
					$content = file_get_contents($langfile);
					if (!preg_match('/^'.preg_quote($r)."\t/i", $content))
					{
						if (!is_writable($langfile)) unlink($langfile);
						$content .= "$r\tnotifications\ten\t$r\n";
						file_put_contents($langfile, $content);
					}
				}
			}*/
			break;
	}

	/**
	 * workaround
	 * Warning: htmlspecialchars() expects parameter 2 to be long, string given
	 */
	$htmlscflags = ENT_XML1;

	if (is_string($htmlscflags))
	{
		$htmlscflags = 16;	// #define ENT_XML1		16
	}

	return '<'.$matches[1].'>'.htmlspecialchars($replacement, $htmlscflags, Api\Translation::charset()).'</'.$matches[1].'>';
}

$xml = preg_replace_callback('/<((egw_|MI_)[^>]+)>(.*)<\/[a-z0-9_-]+>/iU', 'replace_callback', $xml_in);
//Api\Header\Content::type('egwnotifier.const.xml', 'application/xml', bytes($xml)); echo $xml; exit;

/* does NOT work, fails in addFromString :-(
$zip = new ZipArchive;
if ($zip->open($archive, ZIPARCHIVE::CHECKCONS) !== true)
{
	error_log(__METHOD__.__LINE__." !ZipArchive::open('$archive',ZIPARCHIVE::CHECKCONS) failed. Trying open without validating");
	if ($zip->open($archive) !== true) throw new Exception("!ZipArchive::open('$archive',|ZIPARCHIVE::CHECKCONS)");
}
if (($ret=$zip->addFromString($config_file, $xml)) !== true);// throw new Exception("ZipArchive::addFromString('$config_file', \$xml) returned ".array2string($ret));
if ($zip->close() !== true) throw new Exception("!ZipArchive::close()");
*/

check_load_extension('phar', true);
$zip = new PharData($archive);
$zip->addFromString($config_file, $xml);
unset($zip);
// clear stat cache, as otherwise filesize might report an earlier, smaller size!
clearstatcache();
ob_end_clean();

Api\Header\Content::type('egroupware-notifier-'.$GLOBALS['egw_info']['user']['account_lid'].'.jar', 'application/x-java-archive', filesize($archive));
readfile($archive,'rb');

@unlink($archive);
