<?php
/**
 * EGroupware EMailAdmin: some base functionality
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class emailadmin_base
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
		$retData = array();
		foreach($GLOBALS['egw']->hooks->process(array(
			'location' => 'smtp_server_types',
			'extended' => $extended,
		), array('managementserver', 'emailadmin'), true) as $data)
		{
			if ($data) $retData += $data;
		}
		uksort($retData, function($a, $b) {
			static $prio = array(	// not explicitly mentioned get 0
				'emailadmin_smtp' => 9,
				'emailadmin_smtp_sql' => 8,
				'smtpplesk' => -1,
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
	 * @param boolean $extended=true
	 * @return array classname => label pairs
	 */
	static public function getIMAPServerTypes($extended=true)
	{
		$retData = array();
		foreach($GLOBALS['egw']->hooks->process(array(
			'location' => 'imap_server_types',
			'extended' => $extended,
		), array('managementserver', 'emailadmin'), true) as $data)
		{
			if ($data) $retData += $data;
		}
		uksort($retData, function($a, $b) {
			static $prio = array(	// not explicitly mentioned get 0
				'emailadmin_imap' => 9,
				'emailadmin_oldimap' => 9,
				'managementserver_imap' => 8,
				'emailadmin_dovecot' => 7,
				'emailadmin_imap_dovecot' => 7,
				'cyrusimap' => 6,
				'emailadmin_imap_cyrus' => 6,
				'pleskimap' => -1,
			);
			return (int)$prio[$b] - (int)$prio[$a];
		});
		return $retData;
	}
}