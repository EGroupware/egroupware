<?php
/**
 * eGW jerryr template
 * 
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @author Pim Snel <pim@lingewoud.nl> author of the idots template set
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

require_once(EGW_SERVER_ROOT.'/phpgwapi/templates/idots/class.idots_framework.inc.php');

/**
 * eGW jerryr template
 */
class jerryr_framework extends idots_framework
{
	/**
	 * Constructor, calls the contstructor of the extended class
	 *
	 * @param string $template='jerryr'
	 * @return jerryr_framework
	 */
	function jerryr_framework($template='jerryr')
	{
		$this->idots_framework($template);
	}

	function topmenu()
	{}
}
