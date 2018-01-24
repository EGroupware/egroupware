<?php
/**
 * EGroupware - Notifications
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
interface notifications_iface {

	/**
	 * constructor
	 *
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null);

	/**
	 * sends one notification to one recipient
	 *
	 * @abstract NOTE, $_messages is an array that contains
	 * the notification message in plain and html
	 * @param array $_messages
	 * @param string $_subject
	 * @param array $_links
	 * @param array $_attachments
	 * @param array $_data
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false, $_data = false);
}
