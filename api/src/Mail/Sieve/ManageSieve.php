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
	 * acc_id/raw acc_sieve_ssl value, kept for the one-time certificate-verification upgrade in
	 * connect() - only set when constructed from a Mail\Imap instance (not a plain array), since
	 * that's the only case with an EGroupware account to persist the resolved state onto
	 *
	 * @var int|null
	 */
	protected ?int $verify_acc_id = null;
	protected ?int $verify_ssl = null;

	/**
	 * Constructor
	 *
	 * @param array|Imap $params =array()
	 */
	function __construct($params=array())
	{
		if (is_a($params, Mail\Imap::class))
		{
			$this->verify_acc_id = $params->acc_id;
			$this->verify_ssl = (int)$params->acc_sieve_ssl;
			$params = array(
				'host'     => $params->acc_sieve_host ? $params->acc_sieve_host : $params->acc_imap_host,
				'port'     => $params->acc_sieve_port,
				'secure'   => Mail\Account::ssl2secure($params->acc_sieve_ssl),
				'context'  => Mail\Account::sslContext($params->acc_sieve_ssl),
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

	/**
	 * @inheritDoc
	 */
	public function connect($host = null, $port = null, $context = null, $secure = null)
	{
		parent::connect($host, $port, $context, $secure);

		// one-time silent certificate-verification upgrade for an account still in
		// VERIFY_UNDECIDED state (see Mail\Account::resolveVerification()'s docblock)
		if ($this->verify_acc_id &&
			($this->verify_ssl & Mail\Account::VERIFY_MASK) === Mail\Account::VERIFY_UNDECIDED)
		{
			$this->verify_ssl = Mail\Account::resolveVerification($this->verify_acc_id, 'acc_sieve_ssl',
				$this->verify_ssl, $this->_params['host'], (int)$this->_params['port'],
				$this->_params['secure'] ?? false, "STARTTLS\r\n");
		}
	}
}