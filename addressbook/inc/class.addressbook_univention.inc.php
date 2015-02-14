<?php
/**
 * Addressbook - Univention Backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Univention backend for addressbook
 *
 * Different mail attribute is only difference to LDAP backend
 */
class addressbook_univention extends addressbook_ldap
{
	function __construct($ldap_config = null, $ds = null)
	{
		$this->schema2egw['univentionmail'] = array(
			'email'			=> accounts_univention::MAIL_ATTR,
		);
		parent::__construct($ldap_config, $ds);
	}
}
