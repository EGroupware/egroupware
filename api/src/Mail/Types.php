<?php
/**
 * EGroupware Api: some base mail functionality
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

class Types
{
	/**
	 * Get a list of supported SMTP servers
	 *
	 * Calls hook "smtp_server_types" to allow applications to supply own server-types
	 *
	 * @return array classname => label pairs
	 */
	static public function getSMTPServerTypes($extended=true)
	{
		$retData = self::server_types(false, $extended);
		foreach(Api\Hooks::process(array(
			'location' => 'smtp_server_types',
			'extended' => $extended,
		), array('managementserver'), true) as $app => $data)
		{
			if ($data && $app != 'emailadmin') $retData += $data;
		}

		uksort($retData, function($a, $b) {
			static $prio = array(	// not explicitly mentioned get 0
				'EGroupware\\Api\\Mail\\Smtp' => 9,
				'EGroupware\\Api\\Mail\\Smtp\\Sql' => 8,
				'EGroupware\\Api\\Mail\\Smtp\\Ads' => 7,
			);
			return (int)$prio[$b] - (int)$prio[$a];
		});
		return $retData;
	}

	/**
	 * Get a list of supported IMAP servers
	 *
	 * Calls hook "imap_server_types" to allow applications to supply own server-types
	 *
	 * @param boolean $extended =true
	 * @return array classname => label pairs
	 */
	static public function getIMAPServerTypes($extended=true)
	{
		$retData = self::server_types(true, $extended);
		foreach(Api\Hooks::process(array(
			'location' => 'imap_server_types',
			'extended' => $extended,
		), array('managementserver'), true) as $app => $data)
		{
			if ($data && $app != 'emailadmin') $retData += $data;
		}
		uksort($retData, function($a, $b) {
			static $prio = array(	// not explicitly mentioned get 0
				'EGroupware\\Api\\Mail\\Imap' => 9,
				'managementserver_imap' => 8,
				'EGroupware\\Api\\Mail\\Imap\\Dovecot' => 7,
				'EGroupware\\Api\\Mail\\Imap\\Cyrus' => 6,
			);
			return (int)$prio[$b] - (int)$prio[$a];
		});
		return $retData;
	}

	/**
	 * Detect imap and smtp server plugins from EMailAdmin's inc directory
	 *
	 * @param boolean $imap =true
	 * @param boolean $extended =false
	 * @return array
	 */
	protected static function server_types($imap=true, $extended=false)
	{
		$types = array();
		$prefix = $imap ? 'Imap' : 'Smtp';
		foreach(scandir($dir=__DIR__.'/'.$prefix) as $file)
		{
			if ($file == '..')
			{
				$class_name = __NAMESPACE__.'\\'.$prefix;
			}
			elseif (substr($file, -4) == '.php' && $file != 'Iface.php')
			{
				$class_name = __NAMESPACE__.'\\'.$prefix.'\\'.substr($file, 0, -4);
			}
			else
			{
				continue;
			}
			if (!class_exists($class_name)) continue;

			$type = array(
				'classname' => $class_name,
				'description' => is_callable($function=$class_name.'::description') ? call_user_func($function) : $class_name,
			);
			if ($imap) $type['protocol'] = 'imap';

			$types[$class_name] = $extended ? $type : $type['description'];
		}
		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($types));
		return $types;
	}
}