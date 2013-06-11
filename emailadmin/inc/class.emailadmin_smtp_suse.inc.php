<?php
/**
 * EGroupware: SUSE Mailserver support Postfix MTA
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @subpackage emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Support for Postfix with suse-mailserver schemas
 *
 * Used in SLES and openSUSE 10+
 */
class emailadmin_smtp_suse extends emailadmin_smtp_ldap
{
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * Name of schema, has to be in the right case
	 */
	const SCHEMA = 'suseMailRecipient';

	/**
	 * Attribute to enable mail for an account, OR false if existence of ALIAS_ATTR is enough for mail delivery
	 */
	const MAIL_ENABLE_ATTR = false;
	/**
	 * Attribute value to enable mail for an account, OR false if existense of attribute is enough to enable account
	 */
	const MAIL_ENABLED = false;

	/**
	 * Attribute for aliases OR false to use mail
	 */
	const ALIAS_ATTR = 'susemailacceptaddress';

	/**
	 * Primary mail address required as an alias too: true or false
	 */
	const REQUIRE_MAIL_AS_ALIAS = true;

	/**
	 * Attribute for forwards OR false if not possible
	 */
	const FORWARD_ATTR = 'susemailforwardaddress';

	/**
	 * Attribute to only forward mail, OR false if not available
	 */
	const FORWARD_ONLY_ATTR = false;
	/**
	 * Attribute value to only forward mail
	 */
	const FORWARD_ONLY = false;

	/**
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	const MAILBOX_ATTR = false;

	/**
	 * Log all LDAP writes / actions to error_log
	 */
	var $debug = false;
}
