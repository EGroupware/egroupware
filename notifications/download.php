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

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'notifications',
		'noheader'		=> True,
		'nonavbar'		=> True,
	)
);

include('../header.inc.php');

check_load_extension('zip', true);

$document = EGW_SERVER_ROOT.'/notifications/java/full-eGwNotifier.jar';
$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.jar').'-').'.jar';
$ret=copy($document, $archive);
error_log("copy('$document', '$archive' returned ".array2string($ret));
$document = 'zip://'.$archive.'#'.($config_file = 'lib/conf/egwnotifier.const.xml');

$xml = file_get_contents($document);
//html::content_header('egwnotifier.const.xml', 'application/xml', bytes($xml)); echo $xml; exit;

function replace_callback($matches)
{
	$replacement = $matches[3];
	switch($matches[1])
	{
		case 'egw_dc_url':
			$replacement = $GLOBALS['egw_info']['server']['webserver_url'];
			if (empty($replacement) || $replacement[0] == '/')
			{
				$replacement = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$replacement;
			}
			break;
		case 'egw_dc_logindomain':
			$replacement = $GLOBALS['egw_info']['user']['domain'];
			break;
		case 'egw_dc_username':
			$replacement = $GLOBALS['egw_info']['user']['account_lid'];
			break;
		case 'egw_dc_timeout_socket':
		case 'egw_dc_timeout_notify':
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
	return '<'.$matches[1].'>'.htmlspecialchars($replacement, ENT_XML1, translation::charset()).'</'.$matches[1].'>';
}

$xml = preg_replace_callback('/<((egw_|MI_)[^>]+)>(.*)<\/[a-z0-9_-]+>/iU', 'replace_callback', $xml);
//html::content_header('egwnotifier.replace.xml', 'application/xml', bytes($xml)); echo $xml; exit;

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

html::content_header('egroupware-notifier-'.$GLOBALS['egw_info']['user']['account_lid'].'.jar', 'application/x-java-archive', filesize($archive));
readfile($archive,'r');
