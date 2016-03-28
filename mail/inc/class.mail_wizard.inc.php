<?php
/**
 * EGroupware Mail: Wizard to create mail accounts
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Wizard to create mail accounts
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
		egw_framework::includeCSS('admin','app');

		// and translations
		translation::add_app('admin');

		egw_framework::validate_file('/admin/js/app.js');
	}
}