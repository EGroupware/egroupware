<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* The file written by Miles Lott <milosch@phpgroupware.org>                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include ('../phpgwapi/inc/class.Template.inc.php');

	if (!isset($GLOBALS['HTTP_GET_VARS']['object_type']))
	{
		$GLOBALS['object_type'] = 'function';
	}
	else
	{
		$GLOBALS['object_type'] = $GLOBALS['HTTP_GET_VARS']['object_type'];
	}

	function _debug_array($array)
	{
		if(floor(phpversion()) == 4)
		{
			ob_start(); 
			echo '<pre>'; print_r($array); echo '</pre>';
			$contents = ob_get_contents(); 
			ob_end_clean();
			echo $contents;
//			return $contents;
		}
		else
		{
			echo '<pre>'; var_dump($array); echo '</pre>';
		}
	}

	function parseobject($input)
	{
		$types = array('abstract','param','example','syntax','result','description','discussion','author','copyright','package','access');
		$new = explode("@",$input);
		while (list($x,$y) = each($new))
		{
			if (!isset($object) || trim($new[0]) == $object)
			{
				$t = trim($new[0]);
				$t = trim(ereg_replace('#'.'function'.' ','',$t));
				reset($types);
				while(list($z,$type) = each($types))
				{
					if(ereg('#'.$type.' ',$y))
					{
						$xkey = $type;
						$out = $y;
						$out = trim(ereg_replace('#'.$type.' ','',$out));
						break;
					}
					else
					{
						$xkey = 'unknown';
						$out = $y;
					}
				}
				if($out != $new[0])
				{
					$output[$t][$xkey][] = $out;
				}
			}
		}
		
		if ($GLOBALS['object_type'].' '.$GLOBALS['HTTP_GET_VARS']['object'] == $t)
		{
			$GLOBALS['special_request'] = $output[$t];
		}
		return Array('name' => $t, 'value' => $output[$t]);
	}

	function parsesimpleobject($input)
	{
		
		$types = array('abstract','param','example','syntax','result','description','discussion','author','copyright','package','access');
		$input = ereg_replace ("@", "@#", $input);
		$new = explode("@",$input);
		if (count($new) < 3)
		{
			return False;
		}
		unset ($new[0], $new[1]);
		while (list($x,$y) = each($new))
		{
			if (!isset($object) || trim($new[0]) == $object)
			{
				$t = trim($new[0]);
				reset($types);
				while(list($z,$type) = each($types))
				{
					if(ereg('#'.$type.' ',$y))
					{
						$xkey = $type;
						$out = $y;
						$out = trim(ereg_replace('#'.$type.' ','',$out));
						break;
					}
					else
					{
						$xkey = 'unknown';
						$out = $y;
					}
				}
				if($out != $new[0])
				{
					$output[$t][$xkey][] = $out;
				}
			}
		}
		if ($GLOBALS['object_type'].' '.$GLOBALS['HTTP_GET_VARS']['object'] == $t)
		{
			$GLOBALS['special_request'] = $output[$t];
		}
		return Array('name' => $t, 'value' => $output[$t]);
	}

	
	
	$app = $GLOBALS['HTTP_GET_VARS']['app'];
	$fn  = $GLOBALS['HTTP_GET_VARS']['fn'];

	if($app)
	{
		if (!preg_match("/^[a-zA-Z0-9-_]+$/i",$app))
		{
			echo 'Invalid application<br>';
			exit;
		}
	}
	else
	{
		$app = 'phpgwapi';
	}

	if ($fn)
	{
		if (preg_match("/^class\.([a-zA-Z0-9-_]*)\.inc\.php+$/",$fn) || preg_match("/^functions\.inc\.php+$/",$fn) || preg_match("/^xml_functions\.inc\.php+$/",$fn))
		{
			$files[] = $fn;
		}
		else
		{
			echo 'No valid file selected';
			exit;
		}
	}
	else
	{
		$d = dir('../'.$app.'/inc/');
		while ($x = $d->read())
		{
			if (preg_match("/^class\.([a-zA-Z0-9-_]*)\.inc\.php+$/",$x) || preg_match("/^functions\.inc\.php+$/",$x))
			{
				$files[] = $x;
			}
		}
		$d->close;

		sort($files);
		//reset($files);
	}

	while (list($p,$fn) = each($files))
	{
		$matches = $elements = $data = $startstop = array();
		$string = $t = $out = $xkey = $new = '';
		//$matches = $elements = $data = $class = $startstop = array();
		//$string = $t = $out = $class = $xkey = $new = '';
		$file = '../'.$app.'/inc/' . $fn;
		echo '<br>Looking at: ' . $file . "\n";

		$f = fopen($file,'r');
		while (!feof($f))
		{
			$string .= fgets($f,8000);
		}
		fclose($f);

		preg_match_all("#\*\!(.*)\*/#sUi",$string,$matches,PREG_SET_ORDER);

		/* Now that I have the list of found inline docs, I need to figure out which group they belong to. */
		$idx = 0;
		$ssmatches = $matches;
		reset($ssmatches);
		while (list($sskey,$ssval) = each($ssmatches))
		{
			if (preg_match ("/@class_start/i", $ssval[1]))
			{
				$ssval[1] = ereg_replace ("@", "@#", $ssval[1]);
				$ssval[1] = explode("@",$ssval[1]);
				$ssresult = trim(ereg_replace ("#class_start", "", $ssval[1][1]));
				$sstype = 'class';
				unset($matches[$idx][1][0], $matches[$idx][1][1]);
				$matches_starts[$sstype.' '.$ssresult] = $matches[$idx][1];
				unset($matches[$idx]);
			}
			elseif (preg_match ("/@class_end $ssresult/i", $ssval[1]))
			{
				unset($ssresult);
				unset($matches[$idx]);
			}
			elseif (preg_match ("/@collection_start/i", $ssval[1]))
			{
				$ssval[1] = ereg_replace ("@", "@#", $ssval[1]);
				$ssval[1] = explode("@",$ssval[1]);
				$ssresult = trim(ereg_replace ("#collection_start", "", $ssval[1][1]));
				$sstype = 'collection';
				unset($matches[$idx][1][0], $matches[$idx][1][1]);
				$matches_starts[$sstype.' '.$ssresult] = $matches[$idx][1];
				unset($matches[$idx]);
			}
			elseif (preg_match ("/@collection_end $ssresult/i", $ssval[1]))
			{
				unset($ssresult);
				unset($matches[$idx]);
			}
			else
			{
				if (isset($ssresult))
				{
					$startstop[$idx] = $sstype.' '.$ssresult;
				}
				else
				{
					$startstop[$idx] = 'some_lame_string_that_wont_be_used_by_a_function';
				}
			}
			$idx = $idx + 1;
		}
		unset($ssmatches, $sskey, $ssval, $ssresult, $sstype, $idx);
		reset($startstop);
		while (list($key,$val) = each($matches))
		{
			preg_match_all("#@(.*)$#sUi",$val[1],$data);
			$data[1][0] = ereg_replace ("@", "@#", $data[1][0]);
			$returndata = parseobject($data[1][0], $fn);
			if ($startstop[$key] == 'some_lame_string_that_wont_be_used_by_a_function')
			{
				$class['file '.$fn][0]['file'][] = $fn;
				$class['file '.$fn][0]['file'] = array_unique($class['file '.$fn][0]['file']);
				$class['file '.$fn][$returndata['name']] = $returndata['value'];
			}
			else
			{
				if (!isset($class[$startstop[$key]][0]) && isset($matches_starts[$startstop[$key]]))
				{
					$returndoc = parsesimpleobject($matches_starts[$startstop[$key]]);
					if ($returndoc != False)
					{
						$returndoc['value']['file'][] = $fn;
						$returndoc['value']['file'] = array_unique($returndoc['value']['file']);
					}
					$class[$startstop[$key]][0] = $returndoc['value'];
				}
				$class[$startstop[$key]][$returndata['name']] = $returndata['value'];
			}
		}

	}
	if(isset($GLOBALS['HTTP_GET_VARS']['object']))
	{
		$class = Array($GLOBALS['HTTP_GET_VARS']['object'] => $GLOBALS['special_request']);
	}
	_debug_array($class);
?>
