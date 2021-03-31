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
 * Value for countdown is an expiry datetime or a duration like '+123seconds'.
 * Both are converted by beforeSendToClient to integer duration in seconds.
 * Integer are treated as timestamps not duration!
 *
 * The duration has the benefit, that it does not depend on the correct set time and timezone of the browser / computer of the user.
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
			$value = Api\DateTime::to($value, 'utc') - time(); // =Api\DateTime::to('now', 'utc');
		}
	}

	/**
	 * Perform any needed data manipulation on each row
	 * before sending it to client.
	 *
	 * This is used by Nextmatch on each row to do any needed
	 * adjustments.  If not needed, don't implement it.
	 *
	 * @param string $cname
	 * @param array $expand
	 * @param array $data Row data
	 */
	public function set_row_value($cname, Array $expand, Array &$data)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& $this->get_array($data, $form_name, true);

		if ($value) $value = Api\DateTime::to($value, 'utc') - time(); // =Api\DateTime::to('now', 'utc');
	}

}
\EGroupware\Api\Etemplate\Widget::registerWidget(Countdown::class, 'countdown');