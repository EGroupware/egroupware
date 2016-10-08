<?php
/**
 * EGroupware Mail: Wizard to create mail accounts
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;

/**
 * Wizard to create mail Api\Accounts
 *
 * Extends admin_mail to allow non-admins to use it.
 */
class mail_wizard extends admin_mail
{
	/**
	 * Prefix for callback names
	 */
	const APP_CLASS = 'mail.mail_wizard.';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// need emailadmin's app.css file
		Framework::includeCSS('admin','app');

		// and translations
		Api\Translation::add_app('admin');

		Framework::includeJS('/admin/js/app.js');
	}
}