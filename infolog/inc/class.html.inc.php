<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog                                                   *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

class html
{
	function input_hidden($vars,$value='')
	{
		if (!is_array($vars))
		{
			$vars = array( $vars => $value );
		}
		while (list($name,$value) = each($vars))
		{
			if ($value != '')               // dont need to send all the empty vars
			{
				$html .= "<input type=hidden name=\"$name\" value=\"$value\">\n";
			}
		}
		return $html;
	}

	function submit_button($name,$lang)
	{
		return "<input type=\"submit\" name=\"$name\" value=\"".lang($lang)."\">\n";
	}

	function link($url,$vars='')
	{
		global $phpgw;
		if (is_array( $vars ))
		{
			$v = array( );
			while(list($name,$value) = each($vars))
			{
				if ($value != '')            // dont need to send all the empty vars
				{
					$v[] = "$name=$value";
				}
			}
			$vars = implode('&',$v);
		}
		return $phpgw->link($url,$vars);
	}

	function checkbox($name,$value='')
	{
		return "<input type=\"checkbox\" name=\"$name\" value=\"True\"" .($value ? ' checked' : '') . ">\n";
	}

	function form_1button($name,$lang,$hidden_vars,$url,$url_vars='',$method='POST')
	{
		$html = "<form method=\"$method\" action=\"".$this->link($url,$url_vars)."\">\n";
		$html .= $this->input_hidden($hidden_vars);
		$html .= $this->submit_button($name,$lang);
		$html .= "</form>\n";
		return $html;
	}
}
