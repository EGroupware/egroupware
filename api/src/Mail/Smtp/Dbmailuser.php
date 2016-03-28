<?php
/**
 * EGroupware Api: Postfix with dbmailUser schema
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail\Smtp;

/**
 * Postfix with dbmailUser schema
 */
class Dbmailuser extends Ldap
{
	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * Name of schema, has to be the correct case!
	 */
	const SCHEMA = 'dbmailUser';

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
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	//const MAILBOX_ATTR = 'deliveryprogrampath';
	//const MAILBOX_ATTR = 'dbmailuid';
	const MAILBOX_ATTR = false;
}
