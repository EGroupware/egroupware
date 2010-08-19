<?php
/**
 * EGroupware EMailAdmin: Postfix with old qmailUser schema
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Postfix with old qmailUser schema
 */
class postfixldap extends emailadmin_smtp_ldap
//class emailadmin_smtp_qmailuser extends emailadmin_smtp_ldap
{
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * Name of schema, has to be in the right case!
	 */
	const SCHEMA = 'qmailUser';

	/**
	 * Attribute to enable mail for an account, OR false if existence of ALIAS_ATTR is enough for mail delivery
	 */
	const MAIL_ENABLE_ATTR = 'accountstatus';
	/**
	 * Attribute value to enable mail for an account, OR false if existense of attribute is enough to enable account
	 */
	const MAIL_ENABLED = 'active';

	/**
	 * Attribute for aliases OR false to use mail
	 */
	const ALIAS_ATTR = 'mailalternateaddress';

	/**
	 * Primary mail address required as an alias too: true or false
	 */
	const REQUIRE_MAIL_AS_ALIAS=false;

	/**
	 * Attribute for forwards OR false if not possible
	 */
	const FORWARD_ATTR = 'mailforwardingaddress';

	/**
	 * Attribute to only forward mail, OR false if not available
	 */
	const FORWARD_ONLY_ATTR = 'deliverymode';
	/**
	 * Attribute value to only forward mail
	 */
	const FORWARD_ONLY = 'forwardOnly';

	/**
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	const MAILBOX_ATTR = 'mailmessagestore';

	/**
	 * Log all LDAP writes / actions to error_log
	 */
	var $debug = false;

	/**
	 * LDAP schema configuration
	 *
	 * Parent can NOT use constants direct as we have no late static binding in currenlty required PHP 5.2
	 *
	 * @var array
	 */
	protected $config = array(
		'schema' => self::SCHEMA,
		'mail_enable_attr' => self::MAIL_ENABLE_ATTR,
		'mail_enabled' => self::MAIL_ENABLED,
		'alias_attr' => self::ALIAS_ATTR,
		'require_mail_as_alias' => self::REQUIRE_MAIL_AS_ALIAS,
		'forward_attr' => self::FORWARD_ATTR,
		'forward_only_attr' => self::FORWARD_ONLY_ATTR,
		'forward_only' => self::FORWARD_ONLY,
		'mailbox_attr' => self::MAILBOX_ATTR,
	);
}
