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

	/**************************************************************************\
	* These are the few functions needed for parsing the inline comments       *
	\**************************************************************************/
	$phpgw_info['flags']['noapi'] = True;
	include ('../header.inc.php');
	if (floor(phpversion()) == 3)
	{
		include (PHPGW_API_INC.'/php3_support_functions.inc.php');
	}

	/*!
	 @function array_print
	 @abstract output an array for HTML.
	 @syntax array_print($array);
	 @example array_print($my_array);
	*/
	function array_print($array)
	{
		if(floor(phpversion()) == 4)
		{
			ob_start(); 
			echo '<pre>'; print_r($array); echo '</pre>';
			$contents = ob_get_contents(); 
			ob_end_clean();
			echo $contents;
		}
		else
		{
			echo '<pre>'; var_dump($array); echo '</pre>';
		}
	}

	/*!
	 @function parseobject
	 @abstract Parses inline comments for a single function
	 @author seek3r
	 @syntax parseobject($input);
	 @example $return_data = parseobject($doc_data);
	*/
	function parseobject($input)
	{
		$types = array('abstract','param','example','syntax','result','description','discussion','author','copyright','package','access','required','optional');
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

	/*!
	 @function parsesimpleobject
	 @abstract Parses inline comments for a single function, in a more limited fashion
	 @author seek3r
	 @syntax parsesimpleobject($input);
	 @example $return_data = parsesimpleobject($simple_doc_data);
	*/
	function parsesimpleobject($input)
	{
		$types = array('abstract','param','example','syntax','result','description','discussion','author','copyright','package','access','required','optional');
		$input = ereg_replace ("@", "@#", $input);
		$new = explode("@",$input);
		if (count($new) < 3)
		{
			return False;
		}
		unset ($new[0]);
		unset ($new[1]);
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

	/**************************************************************************\
	* This section handles processing most of the input params for             *
	* limiting and selecting what to print                                     *
	\**************************************************************************/

	/* Prevents passing files[]=../../../secret_file or files[]=/etc/passwd */
	if (is_array($GLOBALS['files']))
	{
		while (list($p, $fn) = each ($GLOBALS['files']))
		{
			if (ereg('\.\.', $fn) || ereg('^/', $fn))
			{
				unset($GLOBALS['files'][$p]);
			}
		}
	}

	if (!isset($GLOBALS['HTTP_GET_VARS']['object_type']))
	{
		$GLOBALS['object_type'] = 'function';
	}
	else
	{
		$GLOBALS['object_type'] = $GLOBALS['HTTP_GET_VARS']['object_type'];
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
	}

	/**************************************************************************\
	* Now that I have the list of files, I loop thru all of them and get the   *
	* inline comments from them and load each of them into an array            *
	\**************************************************************************/ 

	while (list($p,$fn) = each($files))
	{
		$matches = $elements = $data = $startstop = array();
		$string = $t = $out = $xkey = $new = '';
		$file = '../'.$app.'/inc/' . $fn;
//		echo 'Looking at: ' . $file . "<br>\n";
		$f = fopen($file,'r');
		while (!feof($f))
		{
			$string .= fgets($f,8000);
		}
		fclose($f);

		preg_match_all("#\*\!(.*)\*/#sUi",$string,$matches,PREG_SET_ORDER);

		/**************************************************************************\
		* Now that I have the list of found inline docs, I need to figure out      *
		* which group they belong to.                                              *
		\**************************************************************************/ 
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
				unset($matches[$idx][1][0]);
				unset($matches[$idx][1][1]);
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
				unset($matches[$idx][1][0]);
				unset($matches[$idx][1][1]);
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
		unset($ssmatches);
		unset($sskey);
		unset($ssval);
		unset($ssresult);
		unset($sstype);
		unset($idx);
		reset($startstop);

		/**************************************************************************\
		* Now that I have the list groups and which records belong in which groups *
		* its time to parse each function and stick it under the appropriate group *
		* if there is no defined group for a function, then it gets tossed under   *
		* a special group named by the file it was found in                        *
		\**************************************************************************/ 
		while (list($key,$val) = each($matches))
		{
			preg_match_all("#@(.*)$#sUi",$val[1],$data);
			$data[1][0] = ereg_replace ("\n([[:space:]]+)\*", "\n\\1", $data[1][0]);
			$data[1][0] = ereg_replace ("@", "@#", $data[1][0]);
			$returndata = parseobject($data[1][0], $fn);
			if ($startstop[$key] == 'some_lame_string_that_wont_be_used_by_a_function')
			{
				if (!is_array($doc_array['file '.$fn][0]['file']))
				{
					$doc_array['file '.$fn][0]['file'] = Array();
				}

				if (!in_array($fn,$doc_array['file '.$fn][0]['file']))
				{
					$doc_array['file '.$fn][0]['file'][] = $fn;
				}
				$doc_array['file '.$fn][$returndata['name']] = $returndata['value'];
			}
			else
			{
				if (!isset($doc_array[$startstop[$key]][0]) && isset($matches_starts[$startstop[$key]]))
				{
					$returndoc = parsesimpleobject($matches_starts[$startstop[$key]]);
					if ($returndoc != False)
					{
						if (!is_array($returndoc['value']['file']))
						{
							$returndoc['value']['file'] = Array();
						}
						if (!in_array($fn, $returndoc['value']['file']))
						{
							$returndoc['value']['file'][] = $fn;
						}
					}
					if (@isset($returndoc['value']) && is_array($returndoc['value']))
					{
						$doc_array[$startstop[$key]][0] = $returndoc['value'];
					}
					else
					{
						$doc_array[$startstop[$key]][0] = '';
					}
				}
				else
				{
					if (!is_array($doc_array[$startstop[$key]][0]['file']))
					{
						$doc_array[$startstop[$key]][0]['file'] = Array();
					}
					if (!in_array($fn, $doc_array[$startstop[$key]][0]['file']))
					{
						$doc_array[$startstop[$key]][0]['file'][] = $fn;
					}
				}
				$doc_array[$startstop[$key]][$returndata['name']] = $returndata['value'];
			}
		}

	}
	if(isset($GLOBALS['HTTP_GET_VARS']['object']))
	{
		$doc_array = Array($GLOBALS['HTTP_GET_VARS']['object'] => $GLOBALS['special_request']);
	}

	include (PHPGW_API_INC.'/class.Template.inc.php');
	$curdir = PHPGW_SERVER_ROOT.'/doc';
	$GLOBALS['template'] = new Template($curdir);

	$output_format = 'html';
	$GLOBALS['template']->set_file(array('tpl_file' => 'inlinedocparser_'.$output_format.'.tpl'));
	$GLOBALS['template']->set_block('tpl_file','border_top');
	$GLOBALS['template']->set_block('tpl_file', 'group');
	$GLOBALS['template']->set_block('tpl_file', 'object');
	$GLOBALS['template']->set_block('tpl_file', 'object_name');
	$GLOBALS['template']->set_block('tpl_file','border_bottom');
	$GLOBALS['template']->set_block('tpl_file','generic');
	$GLOBALS['template']->set_block('tpl_file','generic_para');
	$GLOBALS['template']->set_block('tpl_file','generic_pre');
	$GLOBALS['template']->set_block('tpl_file','abstract');
	$GLOBALS['template']->set_block('tpl_file','params');
	$GLOBALS['template']->set_block('tpl_file','param_entry');
	$GLOBALS['template']->set_var('PHP_SELF',$PHP_SELF);

	function parsedetails($array, $output_name = 'object_contents')
	{
		while(list($key, $value) = each($array))
		{
			switch ($key)
			{
				case 'author':
				case 'file':
					$num = count($value);
					if ($num > 1)
					{
						$GLOBALS['template']->set_var('generic_name',ucwords($key.'s'));
						for ($idx = 0; $idx < $num; ++$idx)
						{
							if($idx > 0)
							{
								$new_value .= ', '.$value[$idx];
							}
							else
							{
								$new_value = $value[$idx];
							}
						}
						$GLOBALS['template']->set_var('generic_value',$new_value);
					}
					else
					{
						$GLOBALS['template']->set_var('generic_name',ucwords($key));
						$GLOBALS['template']->set_var('generic_value',$value[0]);
					}
					$GLOBALS['template']->fp($output_name,'generic',True);
					break;
				case 'discussion':
					$GLOBALS['template']->set_var('generic_name',ucwords($key));
					$GLOBALS['template']->set_var('generic_value',$value[0]);
					$GLOBALS['template']->fp($output_name,'generic_para',True);
					break;
				case 'syntax':
				case 'example':
					while(list($sub_key, $sub_value) = each($value))
					{
						$GLOBALS['template']->set_var('generic_name',ucwords($key));
						$GLOBALS['template']->set_var('generic_value',$value[$sub_key]);
						$GLOBALS['template']->fp($output_name,'generic_pre',True);
					}
					break;
				case 'param':
					while(list($sub_key, $sub_value) = each($value))
					{
						$GLOBALS['template']->set_var('generic_name',ucwords($key.($sub_key+1)));
						$GLOBALS['template']->set_var('generic_value',$value[$sub_key]);
						$GLOBALS['template']->fp($output_name,'generic',True);
					}
					break;
				case 'abstract':
				case 'description':
				case 'result':
				case 'package':
				case 'copyright':
				case 'access':
				default:
					$GLOBALS['template']->set_var('generic_name',ucwords($key));
					$GLOBALS['template']->set_var('generic_value',$value[0]);
					$GLOBALS['template']->fp($output_name,'generic',True);
			}
		}
	}

	$GLOBALS['template']->fp('doc','border_top',True);
	reset($doc_array);
	while(list($group_key, $group_value) = each($doc_array))
	{
		$GLOBALS['template']->set_var('group_name',$group_key);
		/* This is where most of the work in creating the output gets done */
		while(list($object_key, $object_value) = each($group_value))
		{
			if ($object_key == '0')
			{
				$GLOBALS['template']->set_var('object_id','');
				$GLOBALS['template']->set_var('object_name','');
			}
			else
			{
				$GLOBALS['template']->set_var('object_id',trim(ereg_replace ("function ", "", $object_key)));
				$GLOBALS['template']->set_var('object_name',$object_key);
			}
			if(is_array($object_value))
			{
				parsedetails($object_value);
				$GLOBALS['template']->set_var('generic_name',$docline_key);
				$GLOBALS['template']->set_var('generic_value',$docline_value[0]);
				$GLOBALS['template']->fp('group_contents','object',True);
				$GLOBALS['template']->set_var('object_contents','');
			}
		}
		$GLOBALS['template']->fp('doc','group',True);
		$GLOBALS['template']->set_var('group_contents','');
	}
	$GLOBALS['template']->fp('doc','border_bottom',True);
	$GLOBALS['template']->pfp('out', 'doc');
	echo '<a name="array">';
	array_print($doc_array);
?>
