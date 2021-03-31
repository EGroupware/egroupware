<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch account filter header widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget\Nextmatch;

use EGroupware\Api\Etemplate\Widget;

/**
 * Extend selectbox and change type so proper users / groups get loaded, according to preferences
 */
class Accountfilter extends Widget\Taglist
{
	/**
	 * Parse and set extra attributes from xml in template object
	 *
	 * @param string|\XMLReader $xml
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 */
	public function set_attrs($xml, $cloned=true)
	{
		parent::set_attrs($xml, $cloned);

		$this->attrs['type'] = 'select-account';
	}
}
Widget::registerWidget(__NAMESPACE__.'\\Accountfilter', array('nextmatch-accountfilter'));