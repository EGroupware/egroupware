<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * Instant user notification
 */
interface iface_notification {

	/**
	 * constructor 
	 *
	 * @param object $_account
	 * @param object $_preferences
	 */
	public function __construct( $_account, $_preferences );
	
	/**
	 * sends notification
	 *
	 * @param string $_message
	 */
	public function send( $_message );
}
