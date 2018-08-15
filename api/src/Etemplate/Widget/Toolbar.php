<?php
/**
 * EGroupware - eTemplate serverside toolbar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;

/**
 * eTemplate button widget
 */
class Toolbar extends Etemplate\Widget
{
	/**
	 * Validate toolbar
	 *
	 * Readonly buttons can NOT be pressed!
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}

	/**
	 * Set up what we know on the server side.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$form_name = self::form_name($cname, $this->id, $expand);
			$value = &self::get_array(self::$request->modifications, $form_name, true);
			$value['is_admin'] = true;
		}
	}
}
