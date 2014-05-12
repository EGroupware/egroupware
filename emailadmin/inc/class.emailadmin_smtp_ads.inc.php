<?php
/**
 * EGroupware EMailAdmin: Postfix using Active Directorys Exchange attributes
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2013 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Postfix using Active Directorys proxyAddresses attribute
 * (available without installing Exchange schemas).
 *
 * This plugin is NOT meant to administrate an Exchange Server using AD!
 *
 * Aliases, forwards, forward only and quota is stored in
 * multivalued attribute proxyAddresses with different prefixes.
 *
 * Primary mail address is additionally stored in proxyAddresses.
 * Disabling mail removes proxyAddresses completly.
 *
 * @link http://msdn.microsoft.com/en-us/library/ms679424(v=vs.85).aspx
 * @link http://www.dovecot.org/list/dovecot/2010-February/046763.html
 */
class emailadmin_smtp_ads extends emailadmin_smtp_ldap
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'Active Directory';
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * Name of schema, has to be in the right case!
	 */
	const SCHEMA = 'top';

	/**
	 * Filter for users
	 *
	 * objectCategory is indexed, while objectclass is not!
	 */
	const USER_FILTER = '(objectCategory=person)';

	/**
	 * Name of schema for groups, has to be in the right case!
	 */
	const GROUP_SCHEMA = 'group';

	/**
	 * Attribute to enable mail for an account, OR false if existence of ALIAS_ATTR is enough for mail delivery
	 */
	const MAIL_ENABLE_ATTR = false;

	/**
	 * Attribute for aliases OR false to use mail
	 */
	const ALIAS_ATTR = 'proxyaddresses';

	/**
	 * Caseinsensitive prefix for aliases (eg. "smtp:"), aliases get added with it and only aliases with it are reported
	 */
	const ALIAS_PREFIX = 'smtp:';

	/**
	 * Primary mail address required as an alias too: true or false
	 */
	const REQUIRE_MAIL_AS_ALIAS = true;

	/**
	 * Attribute for forwards OR false if not possible
	 */
	const FORWARD_ATTR = 'proxyaddresses';

	/**
	 * Caseinsensitive prefix for forwards (eg. "forward:"), forwards get added with it and only forwards with it are reported
	 */
	const FORWARD_PREFIX = 'forward:';

	/**
	 * Attribute to only forward mail, OR false if not available
	 */
	const FORWARD_ONLY_ATTR = 'proxyaddresses';

	/**
	 * Value of forward-only attribute, if not set any value will switch forward only on (checked with =*)
	 */
	const FORWARD_ONLY_VALUE = 'forwardOnly';

	/**
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	const MAILBOX_ATTR = false;

	/**
	 * Attribute for quota limit of user in MB
	 */
	const QUOTA_ATTR = 'proxyaddresses';

	/**
	 * Caseinsensitive prefix for quota (eg. "quota:"), quota get added with it and only quota with it are reported
	 */
	const QUOTA_PREFIX = 'quota:';

	/**
	 * Internal quota in MB is multiplicated with this factor before stored in LDAP
	 */
	const QUOTA_FACTOR = 1048576;

	/**
	 * Attribute for user name
	 */
	const USER_ATTR = 'samaccountname';

	/**
	 * Attribute for numeric user id (optional)
	 *
	 * No single uidNumber attribute, as we use RID (last part of objectSid attribute) for it.
	 */
	const USERID_ATTR = false;

	/**
	 * Return LDAP connection
	 */
	protected function getLdapConnection()
	{
		static $ldap;

		if (is_null($ldap))
		{
			if (!is_a($GLOBALS['egw']->accounts->backend, 'accounts_ads'))
			{
				throw new egw_exception_wrong_userinput('Postfix with Active Directory requires accounts stored in ADS!');
			}
			$ldap = $GLOBALS['egw']->accounts->backend->ldap_connection();
		}
		return $ldap;
	}

	/**
	 * Constructor
	 *
	 * @param string $defaultDomain=null
	 */
	function __construct($defaultDomain=null)
	{
		parent::__construct($defaultDomain);

		$this->setBase($GLOBALS['egw']->accounts->backend->ads_context());
	}
	/**
	 * Return description for EMailAdmin
	 *
	 * @return string
	 */
	public static function description()
	{
		return static::DESCRIPTION;
	}
}
