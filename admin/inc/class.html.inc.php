<?php
	/**************************************************************************\
	* phpGroupWare - html                                                      *
	* http://www.phpgroupware.org                                              *
	* Written by Jerry Westrick <jerry@westrick.com>                           *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class html
	{
		function hash_table($rows,$head='',$obj, $frtn)
		{
			$start = $GLOBALS['HTTP_POST_VARS']['start'] ? $GLOBALS['HTTP_POST_VARS']['start'] : $GLOBALS['HTTP_GET_VARS']['start'];

			$html = '';
			$edittable =$head['_edittable'];
			if (isset($edittable))
			{
				if ($edittable)
				{
					// Generate the customization table...
					return $this->edit_table($rows,$head,$obj,$frtn);
				}
				else
				{
					#$html .= '<form method="post" action="'
					#	 . $GLOBALS['phpgw']->link('/index.php')
					#	 . '">' . "\n";
					$bo = CreateObject('admin.bolog',True);
					if (!isset($start))
					{
						$start = 0;
					}
					$num_rows = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
					$stop = $start + $num_rows;
					if ($stop > count($rows))
					{
						$stop = count($rows);
					}
					$nextmatchs	= CreateObject('phpgwapi.nextmatchs');
					$total_records = $bo->get_no_errors();
					$left = $nextmatchs->left('/index.php',$start,$total_records,'menuaction=admin.uilog.list_log');
					$right = $nextmatchs->right('/index.php',$start,$total_records,'menuaction=admin.uilog.list_log');
					$hits =	$nextmatchs->show_hits($total_records,$start);

					$html .= '<table width="98%"><tr>';
					$html .= $left;
					$html .= '<td align="right"> ' . $hits . ' </td>';
					$html .= '<td align="left"> <a href=' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uilog.list_log&editable=true') .'> ' . lang('Edit Table format') . '</a></td>';
					$html .= $right;
					$html .= '</tr></table>';
				}
			}

			if ($head == '')
			{
				$frow = $rows[0];
				$cnam = $this->arr_keys($frow);
				while(list(,$fn)=each($cnam))
				{
					$head[$fn] = array();
				}
			}

			if ( gettype($head['_cols'])=="NULL")
			{
				$cols = $this->arr_keys($rows[0]);
			}
			else
			{
				$cols = $head['_cols'];
			}

			// Build Header Row...

			// First Get the layout arrays...
			$layout = $head['#layout'];
			if (!is_array($layout))
			{
				$layout = $this->arr_keys($cols);
			} 

			// printlist, a list of all columns in a logical row, 
			// with Row/ColSpawn values, in print order...

			$printlist = $this->make_printlist($layout,$cols);

			// $table contains data for header row....
			$table = $this->make_tblhead($printlist,$head);

			// get GroupBy 
			$groupby = $head['_groupby'];
			$supres = $head['_supres'];
			$lastgroup = '';

			// build actual Rows...
			$rparms = array();
			$mrow = $stop;
			for ($rno=0;$rno<$mrow;$rno++)
			{
				// Build GroupKey
				if (isset($groupby))
				{
					$gkey = '';
					reset($groupby);
					while (list($gname,)=each($groupby))
					{
						$gkey .= $rows[$rno][$gname]['value'];
					}
					$rows[$rno]['#gkey'] = $gkey;
				}

				reset($printlist);
				while(list($pc,$pcol)=each($printlist))
				{
					$cname = $pcol['#name'];
					$cparms = $this->arr_merge($head[$cname],$pcol,array('bgcolor'=>'#FFFFFF'),$rows[$rno][$cname]);
					$rows[$rno][$cname] = $cparms;
				}
			}

			// Grouping Suppression

			if (isset($groupby))
			{
				$grno = $start;
				$gkey = $rows[$start]['#gkey'];
				for ($rno=$start+1;$rno<$stop;$rno++)
				{
					$rowspan = 1;
					$rkey = $rows[$rno]['#gkey'];

					while ( $gkey == $rkey)
					{
						//echo "<p>grno:$grno ($gkey) rno:$rno ($rkey) are equal</p>";
						$rowspan = $rowspan + 1;
						$row = $rows[$rno];

						for ($pc=0;$pc<count($row);$pc++)
						{
							$c = $row[$cols[$pc]];
							$cno = $c['#colno'];
							$cname = $c['#name'];

							if ($supres[$cname])
							{
								$rows[$rno][$cname]['#supres']='yes';
								$rows[$rno][$cname]['value']='&nbsp ';
								$rows[$grno][$cname]['rowspan']=$printlist[$cno]['rowspan']*$rowspan;
							}
						}
						$rno++;
						$rkey = $rows[$rno]['#gkey'];
					}
					//echo "<p>grno:$grno ($gkey) rno:$rno ($rkey) are not equal</p>";
					$grno=$rno;
					$gkey=$rkey;
				}
			}
			/*
			** Now Generate the Html For the Table Header
			*/
			//print_r($table);

			$html .= $this->html_head($head,$table,$printlist);
			/*
			** Now (finaly) Generate the Html For the Table
			*/
			//print_r($rows);
			for ($rno=$start;$rno<$stop;$rno++)
			{
				// let user have a hack at the row...
				$row = $obj->$frtn($rno,$rows[$rno]);
	//			$row = $rows[$rno];

	//			$rp = $this->makeparms($row[$rno]['#row_parms']);
				$rp = '';
				$gkey = $row['#gkey'];
	//			$html .= "\t<tr $rp> <comment $gkey>\n";
				$html .= "\t<tr $rp> \n";
				reset($printlist);
				while(list($pc,$pcol)=each($printlist))
				{
					$cname = $pcol['#name'];

					$cp = $this->makeparms($row[$cname]);
					if($row[$cname]['#supres'] != 'yes')
					{
						$html .= "\t\t<td $cp>".$row[$cname]['value']."</td>\n";
					}
					if($pcol['#eor']=='1')
					{
						$html .= "\t</tr>\n"; // \t<tr $rp>\n
					}
				}
			}
			$html .= "</table>\n";
			#$html .= "</form>";
			return $html;
		}

		function makeparms($parmlist)
		{
			$html = '';
			$comma = ' ';
			if (!is_array($parmlist))
			{
				return '';
			}
			reset($parmlist);
			while(list($pname,$pvalue)=each($parmlist))
			{
				switch($pname)
				{
					case 'value':
						break;
					case 'colspan':
					case 'rowspan':
						if ($pvalue != 1)
						{
							$html .= $comma . $pname . '="' . $pvalue . '"';
							#$comma = ', ';
							$comma = ' ';
						};
						break;
					default:
						if (substr($pname,0,1) != '#')
						{
							$html .= $comma . $pname . '="' . $pvalue . '"';
							#$comma = ', ';
							$comma = ' ';
						}
				}
			}
			return $html;
		}

		function edit_table($rows,$head='',$obj, $frtn)
		{
			$nocols = $GLOBALS['HTTP_POST_VARS']['nocols'];
			$noflds = $GLOBALS['HTTP_POST_VARS']['noflds'];
			$norows = $GLOBALS['HTTP_POST_VARS']['norows'];
			$layout = $GLOBALS['HTTP_POST_VARS']['layout'];
			$_cols  = $GLOBALS['HTTP_POST_VARS']['_cols'];

			$html = '';
			$html .= '<form method="post" action="'
				 . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uilog.list_log&editable=true')
				 . '">' . "\n";

			$params = $head['_table_parms'];
			$frow = $rows[0];
			$cnam = $this->arr_keys($frow);
			if ($head == '')
			{
				while(list(,$fn)=each($cnam))
				{
					$head[$fn] = array();
				}
			}

			if (isset($_cols))
			{
				$cols = $_cols;
			}
			else
			{
				if ( gettype($head['_cols'])=="NULL")
				{
					$cols = $this->arr_keys($rows[0]);
				}
				else
				{
					$cols = $head['_cols'];
				}
			}

			if (!isset($noflds))
			{
				$noflds = count($cols);
			}
			if (!isset($layout))
			{
				$layout = $head['#layout'];
			}
			if (!isset($norows))
			{
				$norows = count($layout);
			}
			if (!isset($nocols))
			{
				$nocols = count($layout[0]);
			}
			// Table Excmple

			// Build Header Row...
			$html .= "<h2>Table Size</h2>";
	//		$html .= "<p>";
			$html .= "Rows: ";
			$html .= "<input type=\"input\" name=\"norows\" value=\"$norows\">";
			$html .= "Columns: ";
			$html .= "<input type=\"input\" name=\"nocols\" value=\"$nocols\">";
			$html .= "Fields: ";
			$html .= "<input type=\"input\" name=\"noflds\" value=\"$noflds\">";
	//		$html .= "\t<tr> ";

			// Column Defintions...
			$html .= "<h2>Column Definition</h2>";
			$html .= "<table width=\"98%\" bgcolor=\"#000000\">\n";
			$f	= array();
			for ($fno=0;$fno<$noflds;$fno++)
			{
				$f[]=$fno;
			}
			// Column Headings
			$html .= "\t<tr bgcolor=\"#D3DCFF\">\n";
			for ($cno=0;$cno<$nocols;$cno++)
			{
				$html .= "\t\t<td align=\"center\">$cno</td>\n";
			}
			$html .= "\t</tr >\n";
			for ($rno=0;$rno<$norows;$rno++)
			{
				$html .= "\t<tr bgcolor=\"#D3DCFF\">\n";
				for ($cno=0;$cno<$nocols;$cno++)
				{
					$c = $layout[$rno][$cno];
					$tname = "layout[$rno][]";
					$t = $this->DropDown($f,$tname,$c);
					$html .= "\t\t<td align=\"center\">$t</td>\n";
				}
				$html .= "\t</tr >\n";
			}
			$html .= "</table>\n";
			$html .= "<p>\n";

			// Header of Table...
			$printlist = $this->make_printlist($layout,$cols);
			$table = $this->make_tblhead($printlist,$head);
			$html .= $this->html_head($head,$table,$printlist);
			$html .= "</table>\n";

			$html .= "<input type=\"submit\" name=\"submit\" value=\"Update\">";
			//Field Definitions
			$html .= "<h2>Field Definitions</h2>";
			$html .= "<table width=\"98%\" bgcolor=\"#D3DCFF\">\n";
			$html .= "\t\t<td width=\"2%\" align=\"center\">No</td>\n";
			$html .= "\t\t<td width=\"2%\" align=\"center\">Del</td>\n";
			$html .= "\t\t<td width=\"5%\">Field</td>\n";
			$html .= "\t\t<td>Value</td>\n";
			$html .= "\t</tr>\n";

			// Add Table Rows...
			reset($cols);
	//		while (list($cno,$name) = each($cols))
			for ($fno=0;$fno<$noflds;$fno++)
			{
				$name = $cols[$fno];
				$values = $head[$name];
				$title = $values['title'];
				if ($title == '')
				{
					$title = $name;
				}
				$html .= "\t</tr>\n";
				$html .= "\t\t<td bgcolor=\"#FFFFFF\">$fno</td>\n";
				$html .= "\t\t<td bgcolor=\"#FFFFFF\"><input type=\"checkbox\" name=\"_delcol[]\" value=\"$fno\"></td>\n";
				$html .= "\t\t<td bgcolor=\"#FFFFFF\">".$this->dropdown($cnam,'_cols[]',$name)."</td>\n";
				$value = $rows[0][$name]['value'];
				$html .= "\t\t<td bgcolor=\"#FFFFFF\">$value</td>\n";
				$html .= "\t</tr>\n";
			}
			$html .= "</table>\n";
			$html .= "<input type=\"submit\" name=\"modifytable\" value=\"Save Changes\">";
			$html .= "</form>";
			return $html;
		}

		function dropdown($opts,$name='',$sel='')
		{
			$items = $opts;
			$html = '<select ';
			if ($name != '')
			{
				$html .= 'name="'.$name.'"';
			}
			$html .= ">\n";

			while (list(,$itm)=each($opts))
			{
				$html .= '<option value="'.$itm.'" ';
				if ($itm == $sel)
				{
					$html .= 'selected ';
				}
				$html .= '>'.$itm."</option>\n";
			}
			$html .= "</select>\n";
			return $html;
		}

		function make_printlist($layout,$cols)
		{
			// Build Printlist... (Col and Row Spans...)
			$tlayout = $layout;
			$printlist = array();
			$mrows = count($tlayout);
			$mcols = count($tlayout[0]);
			for($pr=0;$pr<$mrows;$pr++)
			{
				for($pc=0;$pc<$mcols;$pc++)
				{
					if (isset($tlayout[$pr][$pc]))
					{
						$cno = $tlayout[$pr][$pc];
						$cname = $cols[$cno];
						$colspan=1;
						$rowspan=1;
						while(($pr + $rowspan < $mrows) && ($tlayout[$pr + $rowspan][$pc] == $cno))
						{
							unset($tlayout[$pr + $rowspan][$pc]);
							$rowspan++;
						}
						while(($pc + $colspan < $mcols) && ($tlayout[$pr][$pc+$colspan] == $cno))
						{
							unset($tlayout[$pr][$pc+$colspan]);
							$colspan++;
						}
						if ($colspan > 1 && $rowspan > 1)
						{
							for($r=$pr+1;$r<$pr+$rowspan;$r++)
							{
								for($c=$pc+1;$c<$pc+$colspan;$c++)
								{
									unset($tlayout[$r][$c]);
								}
							}
						}
						$printlist[] = array(
							'#name'   =>$cname,
							'rowspan' =>$rowspan,
							'colspan' =>$colspan,
							'valign'  =>'top',
							'#colno'  =>$cno,
							'#eor'    =>0
						);
					}
				}
				$printlist[count($printlist)-1]['#eor'] = '1';
			}
			return $printlist;
		}

		function make_tblhead($printlist,$head)
		{
			// Build Title Row
			$table = array();
			reset($printlist);
			while(list($pc,$pcol)=each($printlist))
			{
				$cname = $pcol['#name'];
				$values = $head[$cname];
				$title = $values['#title'];
				if ($title == '')
				{
					$title = $cname;
				}
				$cparms = $this->arr_merge($values['#parms_hdr'],$pcol);
				$cparms['value']=$title;
				$table[0][$pc] = $cparms;
			}
			return $table;
		}

		function html_head($head,$table,$printlist)
		{
			$html = '';
			$tparams = $this->makeparms($head['#table_parms']);
			$html .= "<table $tparams>\n";
			$rp = $this->makeparms($head['#head_parms']);
	//		$html .= "\t<tr $rp> <comment header>\n";
			$html .= "\t<tr $rp> \n";

			$row = $table[0];
			reset($row);
			$intr = true;
			while(list(,$col)=each($row))
			{
				if (!$intr)
				{
					$html .= "\t<tr $rp>\n";
					$intr = true;
				}
				$cname = $col['#name'];
				$cp = $this->makeparms($col);
				$html .= "\t\t<td $cp>".$col['value']."</td>\n";
				if($col['#eor']=='1')
				{
					$html .= "\t</tr>\n";
					$intr = false;
				}
			}
			return $html;
		}

		function arr_merge($a1='',$a2='',$a3='',$a4='',$a5='',$a6='',$a7='',$a8='')
		{
			$out = array();
			$test = array($a1,$a2,$a3,$a4,$a5,$a6,$a7,$a8);
			while(list(,$val) = each($test))
			{
				if(is_array($val))
				{
					$out += $val;
				}
			}
			return $out;
		}

		function arr_keys($fields,$array=True)
		{
			@reset($fields);
			while(list($key,$val) = @each($fields))
			{
				$fkeys .= $key . ',';
			}
			$fkeys = substr($fkeys,0,-1);
			if($array)
			{
				$ex = explode(',',$fkeys);
				return $ex;
			}
			else
			{
				return $fkeys;
			}
		}
	}
