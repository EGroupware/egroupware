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
		global $phpgw;
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
				$html .= "<form method=\"post\" action=\""
				    	 . $phpgw->link()  
						 . "&editable=true\">\n";
				$html .= "<input type=\"submit\" name=\"submit\" value=\"Edit Table Format\">";
			}
			
		}
		$params = $head['_table_parms'];
		if ($head == '')
		{
			$frow = $rows[0];
			$cnam = array_keys($frow);
			while(list(,$fn)=each($cnam))
			{
				$head[$fn] = array();
			}
		};

		if ( gettype($head['_cols'])=="NULL")
		{
			$cols = array_keys($rows[0]);
		}
		else
		{
			$cols = $head['_cols'];
		};
		$html .= "<table $params>\n";
		// Build Header Row...
		$html .= "\t<tr> ";		
		reset($cols);
		while (list(,$name) = each($cols))
		{
			$values = $head[$name];
			$title = $values['title'];
			if ($title == '')
			{
				$title = $name;
			}
			$html .= "\t\t<td ".$values['parms_hdr'].">".$title."</td>\n";
		}
		
		$html .= "\t</tr>\n";
		


		reset($rows);
		while(list($rno,$r)=each($rows))
		{
			$row = $obj->$frtn($rno,$r);
			$rparms = $row['_rparms'];
			$html .= "\t<tr $rparms> ";		
			reset($cols);
			while (list(,$name) = each($cols))
			{
				$values = $head[$name];
				$parms = $values['parms'];
				$fparms = $row['_'.$name];
				$html .= "\t\t<td ".$parms.$fparms.">".$row[$name]."</td>\n";
			}
			$html .= "\t</tr>\n";
		}
		$html .= "</table>\n";
		$html .= "</form>";
		return $html;	
	}
	

	function edit_table($rows,$head='',$obj, $frtn)
	{
		global $phpgw, $nocols;
		$html = '';
		$html .= "<form method=\"post\" action=\""
				 . $phpgw->link()
				 . "&editable=true"  
				 . "\">\n";
			
		$params = $head['_table_parms'];
		$frow = $rows[0];
		$cnam = array_keys($frow);
		if ($head == '')
		{
			while(list(,$fn)=each($cnam))
			{
				$head[$fn] = array();
			}
		};

		if ( gettype($head['_cols'])=="NULL")
		{
			$cols = array_keys($rows[0]);
		}
		else
		{
			$cols = $head['_cols'];
		};
		if (!isset($nocols))
		{
			$nocols = count($cols);
		}
		// Build Header Row...
		$html .= "<p>Number of Columns: ";
		$html .= "<input type=\"input\" name=\"nocols\" value=\"$nocols\">";
		$html .= "<input type=\"submit\" name=\"submit\" value=\"Update Display\">";
		$html .= "</p>\n";
		$html .= "\t<tr> ";		
		$html .= "<table width=\"98%\", bgcolor=\"D3DCFF\">\n";
		$html .= "\t\t<td width=\"2%\", align=\"center\">Del</td>\n";
		$html .= "\t\t<td width=\"5%\">Column</td>\n";
		$html .= "\t\t<td>Value</td>\n";
		$html .= "\t</tr>\n";


		// Add Table Rows...
		reset($cols);
//		while (list($cno,$name) = each($cols))
		for ($cno=0;$cno<$nocols;$cno++)
		{
			$name = $cols[$cno];
			$values = $head[$name];
			$title = $values['title'];
			if ($title == '')
			{
				$title = $name;
			}
			$html .= "\t</tr>\n";	
			$html .= "\t\t<td bgcolor=\"FFFFFF\"><input type=\"checkbox\" name=\"_delcol[]\" value=\"$cno\"></td>\n";
			$html .= "\t\t<td bgcolor=\"FFFFFF\">".$this->dropdown($cnam,'_cols[]',$name)."</td>\n";
			$value = $rows[0][$name];
			$html .= "\t\t<td bgcolor=\"FFFFFF\">$value</td>\n";
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
			$html .= '\t<option value="'.$itm.'" ';
			if ($itm == $sel)
			{
				$html .= 'selected ';
			}
			$html .= '>'.$itm."</option>\n";
		}
		$html .= "</select>\n";
		return $html;
	}

}


