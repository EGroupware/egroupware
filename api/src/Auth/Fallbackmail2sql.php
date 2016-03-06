<?php
/**
 * EGroupware API - Mail Authentication with fallback to SQL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage auth
 * @version $Id$
 */

namespace EGroupware\Api\Auth;

/**
 * Authentication agains a mail Server with fallback to SQL
 *
 * For other fallback types, simply change auth backends in constructor call
 */
class Fallbackmail2sql extends Fallback
{
	/**
	 * Constructor
	 */
	function __construct($primary='mail', $fallback='sql')
	{
		parent::__construct($primary, $fallback);
	}
}
