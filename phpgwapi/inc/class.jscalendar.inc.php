<?php
  /**************************************************************************\
  * eGroupWare - API jsCalendar wrapper-class                                *
  * http://www.eGroupWare.org                                                *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/*!
	@class jscalendar
	@author ralfbecker
	@abstract wrapper for the jsCalendar
	@discussion the constructor load the necessary javascript-files
	*/
	class jscalendar
	{
		/*!
		@function jscalendar
		@syntax jscalendar( $do_header=True )
		@author ralfbecker
		@abstract constructor of the class
		@param $do_header if true, necessary javascript and css gets loaded, only needed for input
		*/
		function jscalendar($do_header=True,$path='jscalendar')
		{
			$this->jscalendar_url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/phpgwapi/js/'.$path;
			$this->dateformat = $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'];

			if ($do_header && !strstr($GLOBALS['phpgw_info']['flags']['java_script'],'jscalendar'))
			{
				$GLOBALS['phpgw_info']['flags']['java_script'] .=
'<link rel="stylesheet" type="text/css" media="all" href="'.$this->jscalendar_url.'/calendar-win2k-cold-1.css" title="win2k-cold-1" />
<script type="text/javascript" src="'.$this->jscalendar_url.'/calendar.js"></script>
<script type="text/javascript" src="'.ereg_replace('[?&]*click_history=[0-9a-f]*','',$GLOBALS['phpgw']->link('/phpgwapi/inc/jscalendar-setup.php')).'"></script>
';
			}
		}

		/*!
		@function input
		@syntax input( $name,$date,$year=0,$month=0,$day=0 )
		@author ralfbecker
		@abstract creates an inputfield for the jscalendar (returns the necessary html and js)
		@param $name name and id of the input-field (it also names the id of the img $name.'-toggle')
		@param $date date as string or unix timestamp (in users localtime)
		@param $year,$month,$day if $date is not used
		@param $helpmsg a helpmessage for the statusline of the browser
		@param $options any other options to the inputfield
		*/
		function input($name,$date,$year=0,$month=0,$day=0,$helpmsg='',$options='')
		{
			//echo "<p>jscalendar::input(name='$name', date='$date'='".date('Y-m-d',$date)."', year='$year', month='$month', day='$day')</p>\n";

			if ($date && (is_int($date) || is_numeric($date)))
			{
				$year  = (int)$GLOBALS['phpgw']->common->show_date($date,'Y');
				$month = (int)$GLOBALS['phpgw']->common->show_date($date,'n');
				$day   = (int)$GLOBALS['phpgw']->common->show_date($date,'d');
			}
			if ($year && $month && $day)
			{
				$date = date($this->dateformat,$ts = mktime(12,0,0,$month,$day,$year));
				if (strpos($this->dateformat,'M') !== False)
				{
					$short = lang(date('M',$ts));	// check if we have a translation of the short-cut
					if (substr($short,-1) == '*')	// if not generate one by truncating the translation of the long name
					{
						$short = substr(lang(date('F',$ts)),0,(int) lang('3 number of chars for month-shortcut'));
					}
					$date = str_replace(date('M',$ts),$short,$date);
				}
			}
			if ($helpmsg !== '')
			{
				$options .= " onFocus=\"self.status='".addslashes($helpmsg)."'; return true;\"" .
				" onBlur=\"self.status=''; return true;\"";
			}
			return
'<input type="text" id="'.$name.'" name="'.$name.'" size="10" value="'.$date.'"'.$options.'/>
<script type="text/javascript">
	document.writeln(\'<img id="'.$name.'-trigger" src="'.$GLOBALS['phpgw']->common->find_image('phpgwpai','datepopup').'" title="'.lang('Select date').'" style="cursor:pointer; cursor:hand;"/>\');
	Calendar.setup(
	{
		inputField  : "'.$name.'",
		button      : "'.$name.'-trigger"
	}
	);
</script>
';
		}

		function flat($url,$date=False,$weekUrl=False,$weekTTip=False,$monthUrl=False,$monthTTip=False,$id='calendar-container')
		{
			if ($date)	// string if format YYYYmmdd or timestamp
			{
				$date = is_int($date) ? date('m/d/Y',$date) :
					substr($date,4,2).'/'.substr($date,6,2).'/'.substr($date,0,4);
			}
			return '
<div id="'.$id.'"></div>

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
' : '').'    }
  );
</script>';
		}

		/*!
		@function input2date
		@syntax input2date( $datestr,$raw='raw',$day='day',$month='month',$year='year' )
		@author ralfbecker
		@abstract converts the date-string back to an array with year, month, day and a timestamp
		@param $datestr content of the inputfield generated by jscalendar::input()
		@param $raw key of the timestamp-field in the returned array or False of no timestamp
		@param $day,$month,$year keys for the array, eg. to set mday instead of day
		*/
		function input2date($datestr,$raw='raw',$day='day',$month='month',$year='year')
		{
			//echo "<p>jscalendar::input2date('$datestr') ".print_r($fields,True)."</p>\n";
			if ($datestr === '')
			{
				return False;
			}
			$fields = split('[./-]',$datestr);
			foreach(split('[./-]',$this->dateformat) as $n => $field)
			{
				if ($field == 'M')
				{
					if (!is_numeric($fields[$n]))
					{
						$partcial_match = 0;
						for($i = 1; $i <= 12; $i++)
						{
							$long_name  = lang(date('F',mktime(12,0,0,$i,1,2000)));
							$short_name = lang(date('M',mktime(12,0,0,$i,1,2000)));	// do we have a translation of the short-cut
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
							if (strstr($long_name,$fields[$n]) == $long_name)	// partcial match => multibyte saver
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
				$ret[$raw] = mktime(12,0,0,$date['m'],$date['d'],$date['Y']);
			}
			//echo "<p>jscalendar::input2date('$datestr','$raw',$day','$month','$year') = "; print_r($ret); echo "</p>\n";

			return $ret;
		}
	}
