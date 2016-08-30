#!/usr/bin/php
<?php
/**
 * EGroupWare: Convert eagerterrier mime icons
 *
 * @link https://github.com/eagerterrier/MimeTypes-Link-Icons
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2015/16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\MimeMagic;

require_once '../../../../api/src/autoload.php';

/* compare current Horde $mime_extension_map with ours
include_once 'Horde/Mime/mime.mapping.php';

foreach($mime_extension_map as $ext => $type)
{
	if (!isset(MimeMagic::$mime_extension_map[$ext]))
	{
		echo "+\t$ext\t$type\n";
	}
	elseif(strtolower($type) === strtolower(MimeMagic::$mime_extension_map[$ext]))
	{
		echo "\t$ext\t$type\n";
	}
	else
	{
		echo "-\t$ext\t".MimeMagic::$mime_extension_map[$ext]."\n";
		echo "+\t$ext\t$type\n";
	}
}
exit;
 */

// make sure these mime-type get their default extensions icon, not some alias
$overwrites = array(
	'txt' => 'text/plain',
	'ogg' => 'audio/ogg',
	'ppt' => 'application/vnd.ms-powerpoint',
	'qt' => 'video/quicktime',
);
$src_dir=__DIR__.'/MimeTypes-Link-Icons/images';
$dst_dir=__DIR__;
foreach(scandir($src_dir) as $file)
{
	if (preg_match('/^([^-]+)-icon-128x128.png$/', $file, $matches))
	{
		if (!isset(MimeMagic::$mime_extension_map[$matches[1]]))
		{
			echo "Unknown extension '$matches[1]'!\n";
			continue;
		}
		$type = MimeMagic::$mime_extension_map[$matches[1]];
		$dst_file = 'mime128_'.str_replace('/', '_', $type).'.png';
		if (file_exists($dst_dir.'/'.$dst_file) && !isset($overwrites[$matches[1]]))
		{
			echo "Icon for extension '$matches[1]' = $type already exists!\n";
			continue;
		}
		copy($src_dir.'/'.$file, $dst_dir.'/'.$dst_file);
		echo "$file --> $dst_file\n";
	}
	//else echo "Ignoring $file\n";
}