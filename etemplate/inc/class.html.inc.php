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
		var $prefered_img_title;

		function html()
		{
			global $HTTP_USER_AGENT;
			// should be Ok for all HTML 4 compatible browsers
			$this->prefered_img_title = stristr($HTTP_USER_AGENT,'konqueror') ? 'title' : 'alt';
		}

		function input_hidden($vars,$value='')
		{
			if (!is_array($vars))
			{
				$vars = array( $vars => $value );
			}
			while (list($name,$value) = each($vars))
			{
				if (is_array($value))
				{
					$value = serialize($value);
				}
				$del = strchr($value,'"') ? "'" : '"';
				if ($value && !($name == 'filter' && $value == 'none'))	// dont need to send all the empty vars
				{
					$html .= "<INPUT TYPE=HIDDEN NAME=\"$name\" VALUE=$del$value$del>\n";
				}
			}
			return $html;
		}

		function textarea($name,$value='',$options='' )
		{
			return "<TEXTAREA name=\"$name\" $options>$value</TEXTAREA>\n";
		}

		function input($name,$value='',$type='',$options='' )
		{
			if ($type)
			{
				$type = "TYPE=$type";
			}

			return "<INPUT $type NAME=\"$name\" VALUE=\"$value\" $options>\n";
		}

		function submit_button($name,$lang,$onClick='',$no_lang=0,$options='')
		{
			if (!$no_lang)
			{
				$lang = lang($lang);
			}
			if ($onClick)
			{
				$options .= " onClick=\"$onClick\"";
			}
			return $this->input($name,$lang,'SUBMIT',$options);
		}

		/*!
		@function link
		@abstract creates an absolut link + the query / get-variables
		@param $url phpgw-relative link, may include query / get-vars
		@parm $vars query or array ('name' => 'value', ...) with query
		@example link('/index.php?menuaction=infolog.uiinfolog.get_list',array('info_id' => 123))
		@example  = 'http://domain/phpgw-path/index.php?menuaction=infolog.uiinfolog.get_list&info_id=123'
		@returns absolut link already run through $phpgw->link
		*/
		function link($url,$vars='')
		{
			if (is_array( $vars ))
			{
				$v = array( );
				while(list($name,$value) = each($vars))
				{
					if ($value && !($name == 'filter' && $value == 'none'))	// dont need to send all the empty vars
					{
						$v[] = "$name=$value";
					}
				}
				$vars = implode('&',$v);
			}
			list($url,$v) = explode('?',$url);	// url may contain additional vars
			if ($v)
			{
				$vars .= ($vars ? '&' : '') . $v;
			}

			return $GLOBALS['phpgw']->link($url,$vars);
		}

		function checkbox($name,$value='')
		{
			return "<input type=\"checkbox\" name=\"$name\" value=\"True\"" .($value ? ' checked' : '') . ">\n";
		}

		function form($content,$hidden_vars,$url,$url_vars='',$method='POST')
		{
			$html = "<form method=\"$method\" action=\"".$this->link($url,$url_vars)."\">\n";
			$html .= $this->input_hidden($hidden_vars);

			if ($content)
			{
				$html .= $content;
				$html .= "</form>\n";
			}
			return $html;
		}

		function form_1button($name,$lang,$hidden_vars,$url,$url_vars='',$method='POST')
		{
			return $this->form($this->submit_button($name,$lang),
			$hidden_vars,$url,$url_vars,$method);
		}

		/*!
		@function table
		@abstracts creates table from array with rows
		@discussion abstract the html stuff
		@param $rows array with rows, each row is an array of the cols
		@param $options options for the table-tag
		@example $rows = array ( '1'  => array( 1 => 'cell1', '.1' => 'colspan=3',
		@example                                2 => 'cell2', 3 => 'cell3', '.3' => 'width="10%"' ),
		@example                 '.1' => 'BGCOLOR="#0000FF"' );
		@example table($rows,'WIDTH="100%"') = '<table WIDTH="100%"><tr><td colspan=3>cell1</td><td>cell2</td><td width="10%">cell3</td></tr></table>'
		@returns string with html-code of the table
		*/
		function table($rows,$options = '')
		{
			$html = "<TABLE $options>\n";

			while (list($key,$row) = each($rows))
			{
				if (!is_array($row))
				{
					continue;					// parameter
				}
				$html .= "\t<TR ".$rows['.'.$key].">\n";
				while (list($key,$cell) = each($row))
				{
					if ($key[0] == '.')
					{
						continue;				// parameter
					}
					$html .= "\t\t<TD ".$row['.'.$key].">$cell</TD>\n";
				}
				$html .= "\t</TR>\n";
			}
			$html .= "</TABLE>\n";

			return $html;
		}

		function sbox_submit( $sbox,$no_script=0 )
		{
			$html = str_replace('<select','<select onChange="this.form.submit()" ',
			$sbox);
			if ($no_script)
			{
				$html .= '<noscript>'.$this->submit_button('send','>').'</noscript>';
			}
			return $html;
		}

		function image( $app,$name,$title='',$options='' )
		{
			if (!($path = $GLOBALS['phpgw']->common->image($app,$name)))
			{
				$path = $name;		// name may already contain absolut path
			}

			if ($title)
			{
				$options .= " $this->prefered_img_title=\"$title\"";
			}
			return "<IMG SRC=\"$path\" $options>";
		}

		function a_href( $content,$url,$vars='',$options='')
		{
			if (!strstr($url,'/') && count(explode('.',$url)) == 3)
			{
				$url = "/index.php?menuaction=$url";
			}

			return '<a href="'.$this->link($url,$vars).'" '.$options.'>'.$content.'</a>';
		}

		function bold($content)
		{
			return '<b>'.$content.'</b>';
		}

		function italic($content)
		{
			return '<i>'.$content.'</i>';
		}

		function hr($width,$options='')
		{
			if ($width)
			{
				$options .= " WIDTH=$width";
			}
			return "<hr $options>\n";
		}

		/*!
		@function formatOptions
		@abstract formats option-string for most of the above functions
		@param $options String (or Array) with option-values eg. '100%,,1'
		@param $names String (or Array) with the option-names eg. 'WIDTH,HEIGHT,BORDER'
		@example formatOptions('100%,,1','WIDTH,HEIGHT,BORDER') = ' WIDTH="100%" BORDER="1"'
		@returns option string
		*/
		function formatOptions($options,$names)
		{
			if (!is_array($options))
			{
				$options = explode(',',$options);
			}
			if (!is_array($names))
			{
				$names   = explode(',',$names);
			}

			while (list($n,$val) = each($options))
			{
				if ($val != '' && $names[$n] != '')
				{
					$html .= ' '.$names[$n].'="'.$val.'"';
				}
			}

			return $html;
		}

		/*!
		@function nextMatchStyles
		@abstract returns simple stylesheet for nextmatch row-colors
		@returns the classes 'nmh' = nextmatch header, 'nmr0'+'nmr1' = alternating rows
		*/
		function nextMatchStyles()
		{
			return $this->style(
				".nmh { background: ".$GLOBALS['phpgw_info']['theme']['th_bg']."; }\n".
				".nmr1 { background: ".$GLOBALS['phpgw_info']['theme']['row_on']."; }\n".
				".nmr0 { background: ".$GLOBALS['phpgw_info']['theme']['row_off']."; }\n"
			);
		}

		function style($styles)
		{
			return $styles ? "<STYLE type=\"text/css\">\n<!--\n$styles\n-->\n</STYLE>" : '';
		}

		function label($content,$options='')
		{
			return "<LABEL $options>$content</LABEL>";
		}
	}
