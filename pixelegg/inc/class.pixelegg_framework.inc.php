<?php
/**
 * EGroupware: Stylite Pixelegg template
 *
 * et2 Messages
 *
 * Please do NOT change css-files directly, instead change less-files and compile them!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Stefan Reinhard <stefan.reinhard@pixelegg.de>
 * @package pixelegg
 * @version $Id$
 */

/**
* Stylite Pixelegg template
*/
class pixelegg_framework extends jdots_framework
{
	/**
	 * Appname used for everything but JS includes, which we re-use from jdots
	 */
	const APP = 'pixelegg';

	/**
	* Constructor
	*
	* @param string $template='pixelegg' name of the template
	* @return idots_framework
	*/
	function __construct($template='pixelegg')
	{
		parent::__construct($template);		// call the constructor of the extended class

		$this->template_dir = '/pixelegg';		// we are packaged as an application
	}
}
