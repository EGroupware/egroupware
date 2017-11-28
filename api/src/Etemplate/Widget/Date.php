<?php
/**
 * EGroupware - eTemplate serverside date widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;

/**
 * eTemplate date widget
 *
 * Deals with date and time.  Overridden to handle date-houronly as a transform
 *
 * Supported attributes: dataformat[,mode]
 *  dataformat: '' = timestamps or automatic conversation, or eg. 'Y-m-d H:i:s' for 2002-12-31 23:59:59
 *  mode: &1 = year is int-input not selectbox, &2 = show a [Today] button, (html-UI always uses jscal and dont care for &1+&2)
 *           &4 = 1min steps for time (default is 5min, with fallback to 1min if value is not in 5min-steps),
 *           &8 = dont show time for readonly and type date-time if time is 0:00,
 *           &16 = prefix r/o display with dow
 *           &32 = prefix r/o display with week-number
 *			 &64 = prefix r/o display with weeknumber and dow
 *           &128 = no icon to trigger popup, click into input trigers it, also removing the separators to save space
 *
 * @todo validation of date-duration
 *
 * @info Communication between client and server is always done as a string in ISO8601/W3C
 * format ("Y-m-d\TH:i:sP").  If the application specifies a different format
 * for the field, the conversion is done as needed understand what the application
 * sends, and to give the application what it wants when the form is submitted.
 */
class Date extends Transformer
{
	protected static $transformation = array(
		'type' => array('date-houronly' => 'select-hour')
	);

	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = 'dataformat,mode';


	/**
	 * Convert the provided date into the format needed for unambiguous communication
	 * with browsers (Javascript).  We use W3C format to avoid timestamp issues.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		if($this->type == 'date-houronly')
		{
			return parent::beforeSendToClient($cname, $expand);
		}

		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name, false, true);

		if($this->type != 'date-duration' && $value)
		{
			$value = $this->format_date($value);
		}
	}

	/**
	 * Perform any needed data manipulation on each row
	 * before sending it to client.
	 *
	 * This is used by Nextmatch on each row to do any needed
	 * adjustments.  If not needed, don't implement it.
	 *
	 * @param type $cname
	 * @param array $expand
	 * @param array $data Row data
	 * @return type
	 */
	public function set_row_value($cname, Array $expand, Array &$data)
	{
		if($this->type == 'date-duration') return;

		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& $this->get_array($data, $form_name, true);

		if (true) $value = $this->format_date($value);
	}

	/**
	 * Put date in the proper format for sending to client
	 * @param string|int $value
	 * @param string $format
	 */
	public function format_date($value)
	{
		if (!$value) return $value;	// otherwise we will get current date or 1970-01-01 instead of an empty value

		if ($this->attrs['dataformat'])
		{
			$date = Api\DateTime::createFromFormat($this->attrs['dataformat'], $value, Api\DateTime::$user_timezone);
		}
		else
		{
			$date = new Api\DateTime($value);
		}
		if($this->type == 'date-timeonly' && $date)
		{
			$date->setDate(1970, 1, 1);
		}
		if($date)
		{
			// postfix date-string with "Z" so javascript doesn't add/subtract anything
			$value = $date->format('Y-m-d\TH:i:s\Z');
		}
		return $value;
	}

	/**
	 * Validate input
	 *
	 * For dates (except duration), it is always a full timestamp in W3C format,
	 * which we then convert to the format the application is expecting.  This can
	 * be either a unix timestamp, just a date, just time, or whatever is
	 * specified in the template.
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

		if (!$this->is_readonly($cname, $form_name) && $this->type != 'date-since')	// date-since is always readonly
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			if($value)
			{
				try
				{
					$date = new Api\DateTime($value);
				}
				catch(\Exception $e)
				{
					unset($e);
					$date = null;
					$value = '';
					// this is not really a user error, but one of the clientside engine
					self::set_validation_error($form_name,lang("'%1' is not a valid date !!!", $value).' '.$this->dataformat);
				}
			}

			if ((string)$value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'));
			}
			elseif (is_null($value))
			{
				$valid = null;
			}
			elseif ($this->type == 'date-duration')
			{
				$valid = (string)$value === '' ? '' : (int)$value;
			}

			if (!empty($this->attrs['min']))
			{
				if(is_numeric($this->attrs['min']))
				{
					$min = new Api\DateTime(strtotime( $this->attrs['min'] . 'days'));
				}
				elseif (preg_match('/[+-][[:digit:]]+[ymwd]/',$this->attrs['min']))
				{
					// Relative date with periods
					$min = new Api\DateTime(strtotime(str_replace(array('y','m','w','d'), array('years','months','weeks','days'), $this->attrs['min'])));
				}
				else
				{
					$min = new Api\DateTime(strtotime($this->attrs['min']));
				}
				if($date < $min)
				{
					self::set_validation_error($form_name,lang(
						"Value has to be at least '%1' !!!",
						$min->format($this->type != 'date')
					),'');
					$value = $min;
				}
			}
			if (!empty($this->attrs['max']))
			{
				if(is_numeric($this->attrs['max']))
				{
					$max = new Api\DateTime(strtotime( $this->attrs['max'] . 'days'));
				}
				elseif (preg_match('/[+-][[:digit:]]+[ymwd]/',$this->attrs['max']))
				{
					// Relative date with periods
					$max = new Api\DateTime(strtotime(str_replace(array('y','m','w','d'), array('years','months','weeks','days'), $this->attrs['max'])));
				}
				else
				{
					$max = new Api\DateTime(strtotime($this->attrs['max']));
				}
				if($date > $max)
				{
					self::set_validation_error($form_name,lang(
						"Value has to be at maximum '%1' !!!",
						$max->format($this->type != 'date')
					),'');
					$value = $max;
				}
			}
			if(!$value)
			{
				// Not null, blank
				$value = '';
			}
			elseif ($date && empty($this->attrs['dataformat']))	// integer timestamp
			{
				$valid = $date->format('ts');
			}
			// string with formatting letters like for php's date() method
			elseif ($date && ($valid = $date->format($this->attrs['dataformat'])))
			{
				// Nothing to do here
			}
			else
			{
				// this is not really a user error, but one of the clientside engine
				self::set_validation_error($form_name,lang("'%1' is not a valid date !!!", $value).' '.$this->dataformat);
			}
			//error_log("$this : ($valid)" . Api\DateTime::to($valid));
		}
	}
}
