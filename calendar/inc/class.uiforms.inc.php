<?php
/**************************************************************************\
* eGroupWare - Calendar - forms of the UserInterface                       *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(PHPGW_INCLUDE_ROOT . '/calendar/inc/class.uical.inc.php');

/**
 * calendar UserInterface forms
 *
 * @package calendar
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */
class uiforms extends uical
{
	var $public_functions = array(
		'freetimesearch'  => True,
	);

	/**
	 * Constructor
	 */
	function uiforms()
	{
		$this->uical();	// call the parent's constructor
	}

	/**
	 * Freetime search
	 *
	 * As the function is called in a popup via javascript, parametes get initialy transfered via the url
	 * @param $content array/boolean array with parameters or false (default) to use the get-params
	 * @param start[str] string start-date
	 * @param start[hour] string start-hour
	 * @param start[min] string start-minutes
	 * @param end[str] string end-date
	 * @param end[hour] string end-hour
	 * @param end[min] string end-minutes
	 * @param participants string ':' delimited string of user-id's
	 */
	function freetimesearch($content = false)
	{
		if (!is_array($content))
		{
			if ($this->debug) echo "<pre>".print_r($_GET,true)."</pre>";

			foreach(array('start','end') as $name)
			{
				$arr = $this->jscal->input2date($_GET[$name]['str'],false);
				$arr += $_GET[$name];
				$content[$name] = $this->bo->date2ts($arr);
			}
			$duration = $content['end'] - $content['start'];
			
			if ($duration <= 12*HOUR_s)
			{
				$content['duration_h'] = (int) ($duration / HOUR_s);
				$content['duration_min'] = ($duration/60) % 60;
				$content['end'] = 0;
			}
			else
			{
				$content['duration_h'] = $content['duration_min'] = 0;
			}
			foreach(explode(':',$_GET['participants']) as $uid)
			{
				if ((int) $uid) $content['participants'][] = (int) $uid;
			}
			$content['cal_id'] = $_GET['cal']['id'];
			$content['recur_type'] = $_GET['cal']['recur_type'];
			
			// default search parameters
			$content['start_time'] = $this->cal_prefs['workdaystarts'];
			$content['end_time'] = $this->cal_prefs['workdayends'];
			if ($this->cal_prefs['workdayends']*HOUR_s < $this->cal_prefs['workdaystarts']*HOUR_s+$duration)
			{
				$content['end_time'] = 0;	// no end-time limit, as duration would never fit
			}
			$content['weekdays'] = MCAL_M_WEEKDAYS;
			$content['search_window'] = 7 * DAY_s;
		}
		else
		{
			$duration = $content['end'] ? $content['end']-$content['start'] : 60*(60*$content['duration_h']+$content['duration_min']); 
			
			if (is_array($content['freetime']['select']))
			{
				list($selected) = each($content['freetime']['select']);
				echo "$selected = ".date('D d.m.Y H:i',$content['freetime'][$selected]['start']);
				$start = (int) $content['freetime'][$selected]['start'];
				$end = $start + $duration;
				$fields_to_set = array(
					'start[str]'	=> date($this->common_prefs['dateformat'],$start),
					'start[min]'	=> date('i',$start),
					'end[str]'		=> date($this->common_prefs['dateformat'],$end),
					'end[min]'		=> date('i',$end),
				);
				if ($this->common_prefs['timeformat'] == 12)
				{
					$fields_to_set += array(
						'start[hour]'	=> date('h',$start),
						'start[ampm]'	=> date('a',$start),
						'end[hour]'		=> date('h',$end),
						'end[ampm]'		=> date('a',$end),
					);
				}
				else	
				{
					$fields_to_set += array(
						'start[hour]'	=> date('H',$start),
						'end[hour]'		=> date('H',$end),
					);
				}
				echo "<html>
<script>
	var fields = Array('".implode("','",array_keys($fields_to_set))."');
	var values = Array('".implode("','",$fields_to_set)."');
	for (i=0; i < fields.length; ++i) {
		elements = opener.document.getElementsByName(fields[i]);
		if (elements) {
			if (elements.length == 1)
				elements[0].value = values[i];
			else
				for (n=0; n < elements.length; ++n) {
					if (elements[n].value == values[i]) elements[n].checked = true;
				}
		}
	}
	window.close();	
</script>
</html>\n";
				exit;
			}
		}
		if ($content['recur_type'])
		{
			$content['msg'] .= lang('Only the initial date of that recuring event is checked!');
		}
		$content['freetime'] = $this->freetime($content['participants'],$content['start'],$content['start']+$content['search_window'],$duration,$content['cal_id']);
		$content['freetime'] = $this->split_freetime_daywise($content['freetime'],$duration,$content['weekdays'],$content['start_time'],$content['end_time'],$sel_options);
		$sel_options['search_window'] = array(
			7*DAY_s		=> lang('one week'),
			14*DAY_s	=> lang('two weeks'),
			31*DAY_s	=> lang('one month'),
			92*DAY_s	=> lang('three month'),
			365*DAY_s	=> lang('one year'),
		);

		$etpl = CreateObject('etemplate.etemplate','calendar.freetimesearch');

		//echo "<pre>".print_r($content,true)."</pre>\n";
		$GLOBALS['phpgw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('freetime search');
		// let the window popup, if its already there
		$GLOBALS['phpgw_info']['flags']['java_script'] .= "<script>\nwindow.focus();\n</script>\n";

		$etpl->exec('calendar.uiforms.freetimesearch',$content,$sel_options,'',array(
				'participants'	=> $content['participants'],
				'cal_id'		=> $content['cal_id'],
				'recur_type'	=> $content['recur_type'],
			),2);		
	}
	
	/**
	 * calculate the freetime of given $participants in a certain time-span
	 *
	 * @param $start int start-time timestamp in user-time
	 * @param $end int end-time timestamp in user-time
	 * @param $participants array of user-id's
	 * @param $duration int min. duration in sec, default 1
	 * @param $cal_id int own id for existing events, to exclude them from being busy-time, default 0
	 * @return array of free time-slots: array with start and end values
	 */
	function freetime($participants,$start,$end,$duration=1,$cal_id=0)
	{
		$busy = $this->bo->search(array(
			'start' => $start,
			'end'	=> $end,
			'users'	=> $participants,
		));
		$busy[] = array(	// add end-of-search-date as event, to cope with empty search and get freetime til that date
			'start'	=> array('raw'=>$end),
			'end'	=> array('raw'=>$end),
		);	
		$ft_start = $start;
		$freetime = array();
		$n = 0;
		foreach($busy as $event)
		{
			if ((int)$cal_id && $event['id'] == (int)$cal_id) continue;	// ignore our own event

			if ($this->debug)
			{
				echo "<p>ft_start=".date('D d.m.Y H:i',$ft_start)."<br>\n";
				echo "event[title]=$event[title]<br>\n";
				echo "event[start]=".date('D d.m.Y H:i',$event['start']['raw'])."<br>\n";
				echo "event[end]=".date('D d.m.Y H:i',$event['end']['raw'])."<br>\n";
			}
			// $events ends before our actual position ==> ignore it
			if ($event['end']['raw'] < $ft_start)
			{
				//echo "==> event ends before ft_start ==> continue<br>\n";
				continue;
			}
			// $events starts before our actual position ==> set start to it's end and go to next event
			if ($event['start']['raw'] < $ft_start)
			{
				//echo "==> event starts before ft_start ==> set ft_start to it's end & continue<br>\n";
				$ft_start = $event['end']['raw'];
				continue;
			}
			$ft_end = $event['start']['raw'];

			// only show slots equal or bigger to min_length
			if ($ft_end - $ft_start >= $duration)
			{
				$freetime[++$n] = array(
					'start'	=> $ft_start,
					'end'	=> $ft_end,
				);
				if ($this->debug) echo "<p>freetime: ".date('D d.m.Y H:i',$ft_start)." - ".date('D d.m.Y H:i',$ft_end)."</p>\n";
			}	
			$ft_start = $event['end']['raw'];
		}
		return $freetime;
	}
	
	/**
	 * split the freetime in daywise slot, taking into account weekdays, start- and stop-times
	 *
	 * @param $freetime array of free time-slots: array with start and end values
	 * @param $duration int min. duration in sec
	 * @param $weekdays int allowed weekdays, bitfield of MCAL_M_...
	 * @param $start_time int minimum start-hour 0-23
	 * @param $end_time int maximum end-hour 0-23, or 0 for none
	 * @param $sel_options array on return options for start-time selectbox
	 * @return array of free time-slots: array with start and end values
	 */
	function split_freetime_daywise($freetime,$duration,$weekdays,$start_time,$end_time,&$sel_options)
	{
		$freetime_daywise = $sel_options = array();
		$time_format = $this->common_prefs['timeformat'] == 12 ? 'h:i a' : 'H:i';

		$n = 0;
		foreach($freetime as $ft)
		{
			$daybegin = $this->bo->date2array($ft['start']);
			$daybegin['hour'] = $daybegin['minute'] = $daybegin['second'] = 0;
			unset($daybegin['raw']);
			$daybegin = $this->bo->date2ts($daybegin);
			
			for($t = $daybegin; $t < $ft['end']; $t += DAY_s,$daybegin += DAY_s)
			{
				$dow = date('w',$daybegin+DAY_s/2);	// 0=Sun, .., 6=Sat
				$mcal_dow = pow(2,$dow);
				if (!($weekdays & $mcal_dow))
				{
					continue;	// wrong day of week
				}
				$start = $t < $ft['start'] ? $ft['start'] : $t;
				
				if ($start-$daybegin < $start_time*HOUR_s)	// start earlier then start_time
				{
					$start = $daybegin + $start_time*HOUR_s;
				}
				// if end_time given use it, else the original slot's end
				$end = $end_time ? $daybegin + $end_time*HOUR_s : $ft['end'];
				if ($end > $ft['end']) $end = $ft['end'];

				// slot to small for duration
				if ($end - $start < $duration)
				{
					continue;
				}
				$freetime_daywise[++$n] = array(
					'start'	=> $start,
					'end'	=> $end,
				);
				$times = array();
				for ($s = $start; $s+$duration <= $end; $s += 60*$this->cal_prefs['interval'])
				{
					$e = $s + $duration;
					$end_date = $e-$daybegin > DAY_s ? lang(date('l',$e)).' '.date($this->common_prefs['dateformat'],$e).' ' : '';
					$times[$s] = date($time_format,$s).' - '.$end_date.date($time_format,$e);
				}
				$sel_options[$n.'[start]'] = $times;
			}
		}
		return $freetime_daywise;
	}
}
