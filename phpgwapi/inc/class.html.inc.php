<?php
	/**************************************************************************\
	* eGroupWare - HTML creation class                                         *
	* http://www.eGroupWare.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class html
	{
		var $user_agent,$ua_version;	// 'mozilla','msie','konqueror'
		var $prefered_img_title;
		var $charset,$phpgwapi_js_url;

		function html()
		{
			// should be Ok for all HTML 4 compatible browsers
			if (!eregi('compatible; ([a-z_]+)[/ ]+([0-9.]+)',$_SERVER['HTTP_USER_AGENT'],$parts))
			{
				eregi('^([a-z_]+)/([0-9.]+)',$_SERVER['HTTP_USER_AGENT'],$parts);
			}
			list(,$this->user_agent,$this->ua_version) = $parts;
			$this->user_agent = strtolower($this->user_agent);

			$this->prefered_img_title = $this->user_agent == 'mozilla' && $this->ua_version < 5 ? 'alt' : 'title';
			//echo "<p>HTTP_USER_AGENT='$GLOBALS[HTTP_USER_AGENT]', UserAgent: '$this->user_agent', Version: '$this->ua_version', img_title: '$this->prefered_img_title'</p>\n";

			$this->document_root = $_SERVER['DOCUMENT_ROOT'];
			// this is because some webservers report their docroot without the leading slash
			if (!is_dir($this->document_root) && is_dir('/'.$this->document_root))
			{
				$this->document_root = '/' . $this->document_root;
			}
			//echo "<p>_SERVER[DOCUMENT_ROOT]='$_SERVER[DOCUMENT_ROOT]', this->document_root='$this->document_root'</p>\n";

			if ($GLOBALS['phpgw']->translation)
			{
				$this->charset = $GLOBALS['phpgw']->translation->charset();
			}
			$this->phpgwapi_js_url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/phpgwapi/js';
		}

		function htmlspecialchars($str)
		{
			return htmlspecialchars($str,ENT_COMPAT,$this->charset);
		}

		/*!
		@function select
		@abstract allows to show and select one item from an array
		@param $name	string with name of the submitted var which holds the key of the selected item form array
		@param $key		key(s) of already selected item(s) from $arr, eg. '1' or '1,2' or array with keys
		@param $arr		array with items to select, eg. $arr = array ( 'y' => 'yes','n' => 'no','m' => 'maybe');
		@param $no_lang	if !$no_lang send items through lang()
		@param $options	additional options (e.g. 'width')
		@param $multiple number of lines for a multiselect, default 0 = no multiselect
		@returns string to set for a template or to echo into html page
		*/
		function select($name, $key, $arr=0,$no_lang=0,$options='',$multiple=0)
		{
			// should be in class common.sbox
			if (!is_array($arr))
			{
				$arr = array('no','yes');
			}
			if ((int)$multiple > 0)
			{
				$options .= ' multiple size="'.(int)$multiple.'"';
				if (substr($name,-2) != '[]')
				{
					$name .= '[]';
				}
			}
			$out = "<select name=\"$name\" $options>\n";

			if (is_array($key))
			{
				$key = implode(',',$key);
			}
			foreach($arr as $k => $text)
			{
				$out .= '<option value="'.$this->htmlspecialchars($k).'"';

				if("$k" == "$key" || strstr(",$key,",",$k,"))
				{
					$out .= ' selected="1"';
				}
				$out .= ">" . ($no_lang || $text == '' ? $text : lang($text)) . "</option>\n";
			}
			$out .= "</select>\n";

			return $out;
		}

		function div($content,$options='')
		{
			return "<div $options>\n$content</div>\n";
		}

		function input_hidden($vars,$value='',$ignore_empty=True)
		{
			if (!is_array($vars))
			{
				$vars = array( $vars => $value );
			}
			foreach($vars as $name => $value)
			{
				if (is_array($value))
				{
					$value = serialize($value);
				}
				if (!$ignore_empty || $value && !($name == 'filter' && $value == 'none'))	// dont need to send all the empty vars
				{
					$html .= "<input type=\"hidden\" name=\"$name\" value=\"".$this->htmlspecialchars($value)."\" />\n";
				}
			}
			return $html;
		}

		function textarea($name,$value='',$options='' )
		{
			return "<textarea name=\"$name\" $options>".$this->htmlspecialchars($value)."</textarea>\n";
		}

		/*!
		@function htmlarea
		@syntax htmlarea( $name,$content='',$width=False,$height=False )
		@author ralfbecker
		@abstract creates a textarea inputfield for the htmlarea js-widget (returns the necessary html and js)
		@param $name string name and id of the input-field
		@param $content string of the htmlarea (will be run through htmlspecialchars !!!), default ''
		@param $style string inline styles, eg. dimension of textarea element
		*/
		function htmlarea($name,$content='',$style='width:100%; min-width:500px; height:300px;')
		{
			if (!is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			if (!strstr($GLOBALS['phpgw_info']['flags']['java_script'],'htmlarea'))
			{

								
				$GLOBALS['phpgw']->js->validate_file('htmlarea','htmlarea');
				$GLOBALS['phpgw']->js->validate_file('htmlarea','dialog');
				$lang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
				if ($lang == 'en')	// other lang-files are utf-8 only and incomplete (crashes htmlarea as of 3.0beta)
				{
					$GLOBALS['phpgw']->js->validate_file('htmlarea',"lang/$lang");
				}
				else
				{
					$GLOBALS['phpgw_info']['flags']['java_script'] .=
'<script type="text/javascript" src="'.ereg_replace('[?&]*click_history=[0-9a-f]*','',$GLOBALS['phpgw']->link('/phpgwapi/inc/htmlarea-lang.php',array('lang'=>$lang))).'"></script>'."\n";
				}

				$GLOBALS['phpgw_info']['flags']['java_script_thirst'] .=
				'<style type="text/css">@import url(/egroupware/phpgwapi/js/htmlarea/htmlarea.css);</style>
				<script type="text/javascript">
				var _editor_url = "'."$this->phpgwapi_js_url/htmlarea/".'";
				var htmlareaConfig = new HTMLArea.Config();
				htmlareaConfig.editorURL = '."'$this->phpgwapi_js_url/htmlarea/';
				</script>\n";
				
			}
//			echo $GLOBALS['phpgw_info']['flags']['java_script'];
//			die('test');
			$id = str_replace(array('[',']'),array('_',''),$name);	// no brakets in the id allowed by js

			$GLOBALS['phpgw']->js->set_onload("HTMLArea.replace('$id',htmlareaConfig);");

			if (!empty($style)) $style = " style=\"$style\"";

			return "<textarea name=\"$name\" id=\"$id\"$style>".$this->htmlspecialchars($content)."</textarea>\n";
		}

		function input($name,$value='',$type='',$options='' )
		{
			if ($type)
			{
				$type = 'type="'.$type.'"';
			}
			return "<input $type name=\"$name\" value=\"".$this->htmlspecialchars($value)."\" $options />\n";
		}

		function submit_button($name,$lang,$onClick='',$no_lang=0,$options='',$image='',$app='')
		{
			if ($image != '')
			{
				if (strpos($image,'.'))
				{
					$image = substr($image,0,strpos($image,'.'));
				}
				if (!($path = $GLOBALS['phpgw']->common->image($app,$image)) &&
				    !($path = $GLOBALS['phpgw']->common->image('phpgwapi',$image)))
				{
					$path = $image;		// name may already contain absolut path
				}
				$image = ' src="'.$path.'"';
			}
			if (!$no_lang)
			{
				$lang = lang($lang);
			}
			if (($accesskey = strstr($lang,'&')) && $accesskey[1] != ' ' &&
			(($pos = strpos($accesskey,';')) === False || $pos > 5))
			{
				$lang_u = str_replace('&'.$accesskey[1],'<u>'.$accesskey[1].'</u>',$lang);
				$lang = str_replace('&','',$lang);
				$options = 'accesskey="'.$accesskey[1].'" '.$options;
			}
			else
			{
				$accesskey = '';
				$lang_u = $lang;
			}
			if ($onClick) $options .= " onclick=\"$onClick\"";

			// <button> is not working in all cases if ($this->user_agent == 'mozilla' && $this->ua_version < 5 || $image)
			{
				return $this->input($name,$lang,$image != '' ? 'image' : 'submit',$options.$image);
			}
			return '<button type="submit" name="'.$name.'" value="'.$lang.'" '.$options.' />'.
			($image != '' ? "<img$image $this->prefered_img_title=\"$lang\"> " : '').
			($image == '' || $accesskey ? $lang_u : '').'</button>';
		}

		/*!
		@function link
		@abstract creates an absolut link + the query / get-variables
		@param $url phpgw-relative link, may include query / get-vars
		@parm $vars query or array ('name' => 'value', ...) with query
		@example link('/index.php?menuaction=infolog.uiinfolog.get_list',array('info_id' => 123))
		@example  = 'http://domain/phpgw-path/index.php?menuaction=infolog.uiinfolog.get_list&info_id=123'
		@result absolut link already run through $phpgw->link
		*/
		function link($url,$vars='')
		{
			//echo "<p>html::link(url='$url',vars='"; print_r($vars); echo "')</p>\n";
			if (!is_array($vars))
			{
				parse_str($vars,$vars);
			}
			list($url,$v) = explode('?',$url);	// url may contain additional vars
			if ($v)
			{
				parse_str($v,$v);
				$vars += $v;
			}
			return $GLOBALS['phpgw']->link($url,$vars);
		}

		function checkbox($name,$value='')
		{
			return "<input type=\"checkbox\" name=\"$name\" value=\"True\"" .($value ? ' checked="1"' : '') . " />\n";
		}

		function form($content,$hidden_vars,$url,$url_vars='',$name='',$options='',$method='POST')
		{
			$html = "<form method=\"$method\" ".($name != '' ? "name=\"$name\" " : '')."action=\"".$this->link($url,$url_vars)."\" $options>\n";
			$html .= $this->input_hidden($hidden_vars);

			if ($content)
			{
				$html .= $content;
				$html .= "</form>\n";
			}
			return $html;
		}

		function form_1button($name,$lang,$hidden_vars,$url,$url_vars='',$form_name='',$method='POST')
		{
			return $this->form($this->submit_button($name,$lang),$hidden_vars,$url,$url_vars,$form_name,'',$method);
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
		@result string with html-code of the table
		*/
		function table($rows,$options = '',$no_table_tr=False)
		{
			$html = $no_table_tr ? '' : "<table $options>\n";

			foreach($rows as $key => $row)
			{
				if (!is_array($row))
				{
					continue;					// parameter
				}
				$html .= $no_table_tr && $key == 1 ? '' : "\t<tr ".$rows['.'.$key].">\n";

				foreach($row as $key => $cell)
				{
					if ($key[0] == '.')
					{
						continue;				// parameter
					}
					$table_pos = strpos($cell,'<table');
					$td_pos = strpos($cell,'<td');
					if ($td_pos !== False && ($table_pos === False || $td_pos < $table_pos))
					{
						$html .= $cell;
					}
					else
					{
						$html .= "\t\t<td ".$row['.'.$key].">$cell</td>\n";
					}
				}
				$html .= "\t</tr>\n";
			}
			$html .= "</table>\n";

			if ($no_table_tr)
			{
				$html = substr($html,0,-16);
			}
			return $html;
		}

		function sbox_submit( $sbox,$no_script=0 )
		{
			$html = str_replace('<select','<select onchange="this.form.submit()" ',
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
			if (!@is_readable($this->document_root . $path))
			{
				return $title;
			}
			if ($title)
			{
				$options .= " $this->prefered_img_title=\"".$this->htmlspecialchars($title).'"';
			}
			return "<img src=\"$path\" $options />";
		}

		function a_href( $content,$url,$vars='',$options='')
		{
			if (!strstr($url,'/') && count(explode('.',$url)) == 3)
			{
				$url = "/index.php?menuaction=$url";
			}
			if (is_array($url))
			{
				$vars = $url;
				$url = '/index.php';
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
			if ($width) $options .= " width=\"$width\"";
			return "<hr $options />\n";
		}

		/*!
		@function formatOptions
		@abstract formats option-string for most of the above functions
		@param $options String (or Array) with option-values eg. '100%,,1'
		@param $names String (or Array) with the option-names eg. 'WIDTH,HEIGHT,BORDER'
		@example formatOptions('100%,,1','WIDTH,HEIGHT,BORDER') = ' WIDTH="100%" BORDER="1"'
		@result option string
		*/
		function formatOptions($options,$names)
		{
			if (!is_array($options)) $options = explode(',',$options);
			if (!is_array($names))   $names   = explode(',',$names);

			foreach($options as $n => $val)
			{
				if ($val != '' && $names[$n] != '')
				{
					$html .= ' '.strtolower($names[$n]).'="'.$val.'"';
				}
			}
			return $html;
		}

		/*!
		@function themeStyles
		@abstract returns simple stylesheet (incl. <STYLE> tags) for nextmatch row-colors
		@result the classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
		*/
		function themeStyles()
		{
			return $this->style($this->theme2css());
		}

		/*!
		@function theme2css
		@abstract returns simple stylesheet for nextmatch row-colors
		@result the classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
		*/
		function theme2css()
		{
			return
			".th { background: ".$GLOBALS['phpgw_info']['theme']['th_bg']."; }\n".
			".row_on,.th_bright { background: ".$GLOBALS['phpgw_info']['theme']['row_on']."; }\n".
			".row_off { background: ".$GLOBALS['phpgw_info']['theme']['row_off']."; }\n";
		}

		function style($styles)
		{
			return $styles ? "<style type=\"text/css\">\n<!--\n$styles\n-->\n</style>" : '';
		}

		function label($content,$id='',$accesskey='',$options='')
		{
			if ($id != '')
			{
				$id = " for=\"$id\"";
			}
			if ($accesskey != '')
			{
				$accesskey = " accesskey=\"$accesskey\"";
			}
			return "<label$id$accesskey $options>$content</label>";
		}
	}
