<?php
/**
 * EGroupware - eTemplate serverside countdown widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2021 by rb@egroupware.org
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;

/**
 * eTemplate countdown widget
 *
 */
class Countdown extends Api\Etemplate\Widget
{
	/**
	 * Convert end-datetime of countdown into a difference in seconds to avoid errors due to wrong time&timezone on client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name, false, true);

		if ($value)
		{
			$value = Api\DateTime::to($value, 'utc') - Api\DateTime::to('now', 'utc');
		}
	}
}
\EGroupware\Api\Etemplate\Widget::registerWidget(Countdown::class, 'countdown');