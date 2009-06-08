<?php
/**
 * Wrapper for the jsCalendar
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Wrapper for the jsCalendar
 *
 * The constructor loads the necessary javascript-files.
 *
 * @package api
 * @subpackage html
 * @access public
 */
class jscalendar
{
	/**
	 * url to the jscalendar files
	 *
	 * @var string
	 */
	var $jscalendar_url;
	/**
	 * dateformat from the user-prefs
	 *
	 * @var string
	 */
	var $dateformat;

	/**
	 * Constructor
	 *
	 * @param boolean $do_header=true if true, necessary javascript and css gets loaded, only needed for input
	 * @param string $path='jscalendar'
	 * @return jscalendar
	 */
	function jscalendar($do_header=True,$path='jscalendar',$scriptreturn=false)
	{
		$this->jscalendar_url = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/'.$path;
		$this->dateformat = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];

		$js=
'<link rel="stylesheet" type="text/css" media="all" href="'.$this->jscalendar_url.'/calendar-blue.css" title="blue" />
<script type="text/javascript" src="'.$this->jscalendar_url.'/calendar.js"></script>
<script type="text/javascript" src="'.$GLOBALS['egw']->link('/phpgwapi/inc/jscalendar-setup.php',
	array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'],array('lang'=>1,'dateformat'=>1))).'"></script>
';
		if ($do_header && (strpos($GLOBALS['egw_info']['flags']['java_script'],'jscalendar')===false))
		{
			$GLOBALS['egw_info']['flags']['java_script'] .=$js;
		}
		if ($scriptreturn==true) return $js;  //if the header is already send to the Browser
	}

	/**
	 * Creates an inputfield for the jscalendar (returns the necessary html and js)
	 *
	 * @param string $name name and id of the input-field (it also names the id of the img $name.'-toggle')
	 * @param int/string $date date as string or unix timestamp (in server timezone)
	 * @param int $year=0 if $date is not used
	 * @param int $month=0 if $date is not used
	 * @param int $day=0 if $date is not used
	 * @param string $helpmsg='' a helpmessage for the statusline of the browser
	 * @param string $options='' any other options to the inputfield
	 * @param boolean $jsreturn=false
	 * @return string html
	 */
	function input($name,$date,$year=0,$month=0,$day=0,$helpmsg='',$options='',$jsreturn=false)
	{
		//echo "<p>jscalendar::input(name='$name', date='$date'='".date('Y-m-d',$date)."', year='$year', month='$month', day='$day')</p>\n";

		if ($date && (is_int($date) || is_numeric($date)))
		{
			$year  = (int)adodb_date('Y',$date);
			$month = (int)adodb_date('n',$date);
			$day   = (int)adodb_date('d',$date);
		}
		if ($year && $month && $day)
		{
			$date = adodb_date($this->dateformat,$ts = adodb_mktime(12,0,0,$month,$day,$year));
			if (strpos($this->dateformat,'M') !== false)
			{
				static $substr;
				if (is_null($substr)) $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
				static $chars_shortcut;
				if (is_null($chars_shortcut)) $chars_shortcut = (int)lang('3 number of chars for month-shortcut');	// < 0 to take the chars from the end

				$short = lang($m = adodb_date('M',$ts));	// check if we have a translation of the short-cut
				if ($short == $m || $substr($short,-1) == '*')	// if not generate one by truncating the translation of the long name
				{
					$short = $chars_shortcut > 0 ? $substr(lang(adodb_date('F',$ts)),0,$chars_shortcut) :
						$substr(lang(adodb_date('F',$ts)),$chars_shortcut);
				}
				$date = str_replace(adodb_date('M',$ts),$short,$date);
			}
		}
		if ($helpmsg !== '')
		{
			$options .= " onFocus=\"self.status='".addslashes($helpmsg)."'; return true;\"" .
			" onBlur=\"self.status=''; return true;\"";
		}

		if ($jsreturn)
		{
			$return_array = array(
				'html' => '<input type="text" id="'.$name.'" name="'.$name.'" size="10" value="'.$date.'"'.$options.'/><img id="'.$name.'-trigger" src="'.$GLOBALS['egw']->common->find_image('phpgwpai','datepopup').'" title="'.lang('Select date').'" style="cursor:pointer; cursor:hand;"/>',
				'js'   => 'Calendar.setup({inputField : "'.$name.'",button: "'.$name.'-trigger"	});'
				);

			return $return_array;
		}
		return
'<input type="text" id="'.$name.'" name="'.$name.'" size="10" value="'.$date.'"'.$options.'/>
<script type="text/javascript">
document.writeln(\'<img id="'.$name.'-trigger" src="'.$GLOBALS['egw']->common->find_image('phpgwpai','datepopup').'" title="'.lang('Select date').'" style="cursor:pointer; cursor:hand;"/>\');
Calendar.setup(
{
	inputField  : "'.$name.'",
	button      : "'.$name.'-trigger"
}
);
</script>
';
	}

	/**
	 * Flat jscalendar with tooltips and url's for days, weeks and month
	 *
	 * @param string $url url to call if user clicks on a date (&date=YYYYmmdd is appended automatically)
	 * @param string/int $date=null format YYYYmmdd or timestamp
	 * @param string $weekUrl=''
	 * @param string $weekTTip=''
	 * @param string $monthUrl=''
	 * @param string $monthTTip=''
	 * @param string $id='calendar-container'
	 * @return string html
	 */
	function flat($url,$date=null,$weekUrl='',$weekTTip='',$monthUrl='',$monthTTip='',$id='calendar-container')
	{
		$javascript=$this->jscalendar(false,'jscalendar',true);
		if ($date)	// string if format YYYYmmdd or timestamp
		{
			$date = is_int($date) ? adodb_date('m/d/Y',$date) :
				substr($date,4,2).'/'.substr($date,6,2).'/'.substr($date,0,4);
		}
		return '
<div id="'.$id.'"></div>
'.$javascript.'
<script type="text/javascript">
function dateChanged(calendar) {
'.  // Beware that this function is called even if the end-user only
// changed the month/year.  In order to determine if a date was
// clicked you can use the dateClicked property of the calendar:
// redirect to $url extended with a &date=YYYYMMDD
'    if (calendar.dateClicked) {
 window.location = "'.$url.'&date=" + calendar.date.print("%Y%m%d");
}
};
'.($weekUrl ? '
function weekClicked(calendar,weekstart) {
 window.location = "'.$weekUrl.'&date=" + weekstart.print("%Y%m%d");
}
' : '').($monthUrl ? '
function monthClicked(calendar,monthstart) {
 window.location = "'.$monthUrl.'&date=" + monthstart.print("%Y%m%d");
}
' : '').'

	Calendar.setup(
	{
  		flat         : "'.$id.'",
  		flatCallback : dateChanged'.($weekUrl ? ',
  		flatWeekCallback : weekClicked' : '').($weekTTip ? ',
  		flatWeekTTip : "'.addslashes($weekTTip).'"' : '').($monthUrl ? ',
  		flatMonthCallback : monthClicked' : '').($monthTTip ? ',
  		flatMonthTTip : "'.addslashes($monthTTip).'"' : '').($date ? ',
 		date : "'.$date.'"
		' : '').'
	}
	);

</script>';
	}

	/**
	 * Converts the date-string back to an array with year, month, day and a timestamp
	 *
	 * @param string $datestr content of the inputfield generated by jscalendar::input()
	 * @param boolean/string $raw='raw' key of the timestamp-field in the returned array or False of no timestamp
	 * @param string $day='day' keys for the array, eg. to set mday instead of day
	 * @param string $month='month' keys for the array
	 * @param string $year='year' keys for the array
	 * @return array/boolean array with the specified keys and values or false if $datestr == ''
	 */
	function input2date($datestr,$raw='raw',$day='day',$month='month',$year='year')
	{
		//echo "<p>jscalendar::input2date('$datestr') ".print_r($fields,True)."</p>\n";
		if ($datestr === '')
		{
			return False;
		}
		$fields = preg_split('/[.\\/-]/',$datestr);
		foreach(preg_split('/[.\\/-]/',$this->dateformat) as $n => $field)
		{
			if ($field == 'M')
			{
				if (!is_numeric($fields[$n]))
				{
					$partcial_match = 0;
					for($i = 1; $i <= 12; $i++)
					{
						$long_name  = lang(adodb_date('F',mktime(12,0,0,$i,1,2000)));
						$short_name = lang(adodb_date('M',mktime(12,0,0,$i,1,2000)));	// do we have a translation of the short-cut
						if (substr($short_name,-1) == '*')	// if not generate one by truncating the translation of the long name
						{
							$short_name = substr($long_name,0,(int) lang('3 number of chars for month-shortcut'));
						}
						//echo "<br>checking '".$fields[$n]."' against '$long_name' or '$short_name'";
						if ($fields[$n] == $long_name || $fields[$n] == $short_name)
						{
							//echo " ==> OK<br>";
							$fields[$n] = $i;
							break;
						}
						if (@strstr($long_name,$fields[$n]) == $long_name)	// partcial match => multibyte saver
						{
							$partcial_match = $i;
						}
					}
					if ($i > 12 && $partcial_match)	// nothing found, but a partcial match
					{
						$fields[$n] = $partcial_match;
					}
				}
				$field = 'm';
			}
			$date[$field] = (int)$fields[$n];
		}
		$ret = array(
			$year  => $date['Y'],
			$month => $date['m'],
			$day   => $date['d']
		);
		if ($raw)
		{
			$ret[$raw] = adodb_mktime(12,0,0,$date['m'],$date['d'],$date['Y']);
		}
		//echo "<p>jscalendar::input2date('$datestr','$raw',$day','$month','$year') = "; print_r($ret); echo "</p>\n";

		return $ret;
	}
}

if (!function_exists('array_intersect_key'))	// php5.1 function
{
	function array_intersect_key($array1,$array2)
	{
		$intersection = $keys = array();
		foreach(func_get_args() as $arr)
		{
			$keys[] = array_keys((array)$arr);
		}
		foreach(call_user_func_array('array_intersect',$keys) as $key)
		{
			$intersection[$key] = $array1[$key];
		}
		return $intersection;
	}
}
