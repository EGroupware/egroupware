<?php
/**
 * EGroupware Api: Support for Sieve scripts via ManageSieve
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @author Klaus Leithoff
 * @author Lars Kneschke
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Sieve;

use EGroupware\Api\Translation;
use EGroupware\Api\Mail;
use Horde;

/**
 * Support for Sieve scripts
 *
 * Constructor and setters will throw exceptions for connection, login or other errors.
 *
 * retrieveRules and getters will not throw an exception if there's no script currently.
 *
 * Most methods incl. constructor accepts a script-name, but by default the current active script is used
 * and if there's no script Sieve::DEFAULT_SCRIPT_NAME.
 */
class ManageSieve extends Horde\ManageSieve implements Connection
{
	use Logic;

	/**
	 * Constructor
	 *
	 * @param array|Imap $params =array()
	 */
	function __construct($params=array())
	{
		if (is_a($params, Mail\Imap::class))
		{
			$params = array(
				'host'     => $params->acc_sieve_host ? $params->acc_sieve_host : $params->acc_imap_host,
				'port'     => $params->acc_sieve_port,
				'secure'   => Mail\Account::ssl2secure($params->acc_sieve_ssl),
				'user'     => $params->isAdminConnection ? $params->acc_imap_admin_username : $params->acc_imap_username,
				'password' => $params->isAdminConnection ? $params->acc_imap_admin_password : $params->acc_imap_password,
				'euser'    => $params->isAdminConnection ? $params->acc_imap_username : null,
				//'logger' => new \admin_mail_logger('/tmp/sieve.log'),
			);
		}
		// try "PLAIN" first, in case IMAP wrongly reports some digest, it does not (correctly) implement
		array_unshift($this->supportedAuthMethods, self::AUTH_PLAIN);

		parent::__construct($params);

		$this->displayCharset	= Translation::charset();
	}
}