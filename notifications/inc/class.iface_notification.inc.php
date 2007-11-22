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
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_preferences
	 */
	public function __construct( $_sender=false, $_recipient=false, $_config=false, $_preferences=false );
	
	/**
	 * sends notification
	 *
	 * @abstract NOTE, $_message contains some html-tags (<p><a><b><br>)
	 * implementing class needs to handle them somehow.
	 * @param string $_message
	 */
	public function send( $_subject = false, $_messages, $_attachments = false);
}
