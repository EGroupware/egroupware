<?php
/**************************************************************************\
* eGroupWare - HTML creation class                                         *
* http://www.eGroupWare.org                                                *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
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
	var $need_footer = False;		// do we need to be called at the end of the page

	function html()
	{
		// should be Ok for all HTML 4 compatible browsers
		if (!eregi('(Safari)/([0-9.]+)',$_SERVER['HTTP_USER_AGENT'],$parts) &&
			!eregi('compatible; ([a-z_]+)[/ ]+([0-9.]+)',$_SERVER['HTTP_USER_AGENT'],$parts))
		{
			eregi('^([a-z_]+)/([0-9.]+)',$_SERVER['HTTP_USER_AGENT'],$parts);
		}
		list(,$this->user_agent,$this->ua_version) = $parts;
		$this->user_agent = strtolower($this->user_agent);

		$this->netscape4 = $this->user_agent == 'mozilla' && $this->ua_version < 5;
		$this->prefered_img_title = $this->netscape4 ? 'alt' : 'title';
		//echo "<p>HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]', UserAgent: '$this->user_agent', Version: '$this->ua_version', img_title: '$this->prefered_img_title'</p>\n";

		if ($GLOBALS['phpgw']->translation)
		{
			$this->charset = $GLOBALS['phpgw']->translation->charset();
		}
		$this->phpgwapi_js_url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/phpgwapi/js';
	}

	/**
	 * Created an input-field with an attached tigra color-picker
	 *
	 * Please note: it need to be called before the call to phpgw_header() !!!
	 *
	 * @param $name string the name of the input-field
	 * @param $value string the actual value for the input-field, default ''
	 * @param $title string tooltip/title for the picker-activation-icon
	 */
	function inputColor($name,$value='',$title='')
	{
		$id = str_replace(array('[',']'),array('_',''),$name).'_colorpicker';
		$onclick = "if (this != '') { window.open(this+'&color='+encodeURIComponent(document.getElementById('$id').value),this.target,'width=240,height=187,scrollbars=no,resizable=no'); return false; } else { return true; }";
		return '<input type="text" name="'.$name.'" id="'.$id.'" value="'.$this->htmlspecialchars($value).'" /> '.
			'<a href="'.$this->phpgwapi_js_url.'/colorpicker/select_color.html?id='.urlencode($id).'" target="_blank" onclick="'.$onclick.'">'.
			'<img src="'.$this->phpgwapi_js_url.'/colorpicker/ed_color_bg.gif'.'"'.($title ? ' title="'.$this->htmlspecialchars($title).'"' : '')." /></a>";
	}

	/**
	 * Handles tooltips via the wz_tooltip class from Walter Zorn
	 *
	 * Note: The wz_tooltip.js file gets automaticaly loaded at the end of the page
	 *
	 * @param $text string/boolean text or html for the tooltip, all chars allowed, they will be quoted approperiate
	 *	Or if False the content (innerHTML) of the element itself is used.
	 * @param $do_lang boolean (default False) should the text be run though lang()
	 * @param $options array param/value pairs, eg. 'TITLE' => 'I am the title'. Some common parameters:
	 *  title (string) gives extra title-row, width (int,'auto') , padding (int), above (bool), bgcolor (color), bgimg (URL)
	 *  For a complete list and description see http://www.walterzorn.com/tooltip/tooltip_e.htm
	 * @return string to be included in any tag, like '<p'.$html->tooltip('Hello <b>Ralf</b>').'>Text with tooltip</p>'
	 */
	function tooltip($text,$do_lang=False,$options=False)
	{
		if (!$this->wz_tooltip_included)
		{
			if (!strstr('wz_tooltip',$GLOBALS['phpgw_info']['flags']['need_footer']))
			{
				$GLOBALS['phpgw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript" src="'.$this->phpgwapi_js_url.'/wz_tooltip/wz_tooltip.js"></script>'."\n";
			}
			$this->wz_tooltip_included = True;
		}
		if ($do_lang) $text = lang($text);

		$opt_out = '';
		if (is_array($options))
		{
			foreach($options as $option => $value)
			{
				$opt_out .= 'this.T_'.strtoupper($option).'='.(is_numeric($value)?$value:"'$value'").'; ';
			}
		}
		if ($text === False) return ' onmouseover="'.$opt_out.'return escape(this.innerHTML);"';

		return ' onmouseover="'.$opt_out.'return escape(\''.str_replace(array("\n","\r","'",'"'),array('<br/>','',"\\'",'&quot;'),$text).'\')"';
	}

	function activate_links($content)
	{
		// Exclude everything which is already a link
		$NotAnchor = '(?<!"|href=|href\s=\s|href=\s|href\s=)';

		// spamsaver emailaddress
		$result = preg_replace('/'.$NotAnchor.'mailto:([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i',
		'<a href="#" onclick="document.location=\'mai\'+\'lto:\\1\'+unescape(\'%40\')+\'\\2.\\3\'; return false;">\\1 AT \\2 DOT \\3</a>',
		$content);

		//  First match things beginning with http:// (or other protocols)
		$Protocol = '(http|ftp|https):\/\/';
		$Domain = '([\w]+.[\w]+)';
		$Subdir = '([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$Expr = '/' . $NotAnchor . $Protocol . $Domain . $Subdir . '/i';

		$result = preg_replace( $Expr, "<a href=\"$0\" target=\"_blank\">$2$3</a>", $result );

		//  Now match things beginning with www.
		$NotHTTP = '(?<!:\/\/)';
		$Domain = 'www(.[\w]+)';
		$Subdir = '([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$Expr = '/' . $NotAnchor . $NotHTTP . $Domain . $Subdir . '/i';

		return preg_replace( $Expr, "<a href=\"http://$0\" target=\"_blank\">$0</a>", $result );
	}

	function htmlspecialchars($str)
	{
		// add @ by lkneschke to supress warning about unknown charset
		$str = @htmlspecialchars($str,ENT_COMPAT,$this->charset);
		
		// we need '&#' unchanged, so we translate it back
		$str = str_replace('&amp;#','&#',$str);

		return $str;
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
			$options .= ' multiple="1" size="'.(int)$multiple.'"';
			if (substr($name,-2) != '[]')
			{
				$name .= '[]';
			}
		}
		$out = "<select name=\"$name\" $options>\n";

		if (!is_array($key))
		{
			// explode on ',' only if multiple values expected and the key contains just numbers and commas
			$key = $multiple && preg_match('/^[,0-9]+$/',$key) ? explode(',',$key) : array($key);
		}
		foreach($arr as $k => $text)
		{
			$out .= '<option value="'.$this->htmlspecialchars($k).'"';

			if(in_array($k,$key))
			{
				$out .= ' selected="1"';
			}
			$out .= ">" . ($no_lang || $text == '' ? $text : lang($text)) . "</option>\n";
		}
		$out .= "</select>\n";

		return $out;
	}

	function div($content,$options='',$class='',$style='')
	{
		if ($class) $options .= ' class="'.$class.'"';
		if ($style) $options .= ' style="'.$style.'"';

		return "<div $options>\n".($content ? "$content</div>\n" : '');
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
	@function htmlarea_avalible
	@author ralfbecker
	@abstract Checks if HTMLarea (or an other richtext editor) is availible for the used browser
	*/
	function htmlarea_availible()
	{
		switch($this->user_agent)
		{
			case 'msie':
			return $this->ua_version >= 5.5;
			case 'mozilla':
			return $this->ua_version >= 1.3;
			default:
			return False;
		}
	}

	/**
	 * creates a textarea inputfield for the htmlarea js-widget (returns the necessary html and js)
	 *
	 * Please note: it need to be called before the call to phpgw_header() !!!
	 * @author ralfbecker
	 * @param $name string name and id of the input-field
	 * @param $content string of the htmlarea (will be run through htmlspecialchars !!!), default ''
	 * @param $style string inline styles, eg. dimension of textarea element
	 * @param $base_href string set a base href to get relative image-pathes working
	 * @param $plugins string plugins to load seperated by comma's, eg 'TableOperations,ContextMenu'
	 * (htmlarea breaks when a plugin calls a nonexisiting lang file)
	 * @return the necessary html for the textarea
	 */
	function htmlarea($name,$content='',$style='',$base_href='',$plugins='')
	{
		if (!$plugins) $plugins = 'ContextMenu,TableOperations,SpellChecker';
		if (!$style) $style = 'width:100%; min-width:500px; height:300px;';

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
					'<script type="text/javascript" src="'.ereg_replace('[?&]*click_history=[0-9a-f]*','',
					$GLOBALS['phpgw']->link('/phpgwapi/inc/htmlarea-lang.php',array('lang'=>$lang))).'"></script>'."\n";
			}

			$GLOBALS['phpgw_info']['flags']['java_script_thirst'] .=
				'<style type="text/css">@import url(' . $this->phpgwapi_js_url . '/htmlarea/htmlarea.css);</style>
<script type="text/javascript">

_editor_url = "'."$this->phpgwapi_js_url/htmlarea/".'";
//	var htmlareaConfig = new HTMLArea.Config();
//  htmlareaConfig.editorURL = '."'$this->phpgwapi_js_url/htmlarea/';
</script>\n";

			// set a base href to get relative image-pathes working
			if ($base_href && $this->user_agent != 'msie')	// HTMLarea does not work in IE with base href set !!!
			{
				$GLOBALS['phpgw_info']['flags']['java_script_thirst'] .= '<base href="'.
					($base_href[0] != '/' && substr($base_href,0,4) != 'http' ? $GLOBALS['phpgw_info']['server']['webserver_url'].'/' : '').
					$base_href.'" />'."\n";
			}


			if (!empty($plugins)) 
			{
				foreach(explode(',',$plugins) as $plg_name)
				{
					$plg_name = trim($plg_name);
					$plg_dir = PHPGW_SERVER_ROOT.'/phpgwapi/js/htmlarea/plugins/'.$plg_name;
					if (!@is_dir($plg_dir) || !@file_exists($plg_lang_script="$plg_dir/lang/lang.php") && !@file_exists($plg_lang_file="$plg_dir/lang/$lang.js"))
					{
						//echo "$plg_dir or $plg_lang_file not found !!!";
						continue;	// else htmlarea fails with js errors
					}
					$script_name = strtolower(preg_replace('/([A-Z][a-z]+)([A-Z][a-z]+)/','\\1-\\2',$plg_name));
					$GLOBALS['phpgw']->js->validate_file('htmlarea',"plugins/$plg_name/$script_name");
					if ($lang == 'en' || !@file_exists($plg_lang_script))	// other lang-files are utf-8 only and incomplete (crashes htmlarea as of 3.0beta)
					{
						$GLOBALS['phpgw']->js->validate_file('htmlarea',"plugins/$plg_name/lang/$lang");
					}
					else
					{
						$GLOBALS['phpgw_info']['flags']['java_script'] .=
							'<script type="text/javascript" src="'.ereg_replace('[?&]*click_history=[0-9a-f]*','',
							$GLOBALS['phpgw']->link("/phpgwapi/js/htmlarea/plugins/$plg_name/lang/lang.php",array('lang'=>$lang))).'"></script>'."\n";
					}
					//$load_plugin_string .= 'HTMLArea.loadPlugin("'.$plg_name.'");'."\n";
					$register_plugin_string .= 'ret_editor = editor.registerPlugin("'.$plg_name.'");'."\n";
				}
			}

			$GLOBALS['phpgw_info']['flags']['java_script'] .=
'<script type="text/javascript">

/** Replacement for the replace-helperfunction to make it possible to include plugins. */
HTMLArea.replace = function(id, config)
{
	var ta = HTMLArea.getElementById("textarea", id);

	if(ta)
	{
		editor = new HTMLArea(ta, config);
		'.$register_plugin_string.'
		ret_editor = editor.generate();
		return ret_editor;
	}
	else
	{
		return null;
	}
};

'.$load_plugin_string.'

var htmlareaConfig = new HTMLArea.Config();
htmlareaConfig.editorURL = '."'$this->phpgwapi_js_url/htmlarea/';";

			$GLOBALS['phpgw_info']['flags']['java_script'] .="</script>\n";
		}
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

	function submit_button($name,$lang,$onClick='',$no_lang=0,$options='',$image='',$app='phpgwapi')
	{
		// workaround for idots and IE button problem (wrong cursor-image)
		if ($this->user_agent == 'msie')
		{
			$options .= ' style="cursor: pointer; cursor: hand;"';
		}
		if ($image != '')
		{
			$image = str_replace(array('.gif','.GIF','.png','.PNG'),'',$image);

			if (!($path = $GLOBALS['phpgw']->common->image($app,$image)))
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

	/**
	 * creates an absolut link + the query / get-variables
	 *
	 * Example link('/index.php?menuaction=infolog.uiinfolog.get_list',array('info_id' => 123))
	 *	gives 'http://domain/phpgw-path/index.php?menuaction=infolog.uiinfolog.get_list&info_id=123'
	 * @param $url phpgw-relative link, may include query / get-vars
	 * @param $vars query or array ('name' => 'value', ...) with query
	 * @return string absolut link already run through $phpgw->link
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

	/**
	 * creates table from array of rows
	 *
	 * abstracts the html stuff for the table creation
	 * Example: $rows = array (
	 *	'1'  => array(
	 *		1 => 'cell1', '.1' => 'colspan=3',
	 *		2 => 'cell2',
	 *		3 => 'cell3', '.3' => 'width="10%"'
	 *	),'.1' => 'BGCOLOR="#0000FF"' );
	 *	table($rows,'width="100%"') = '<table width="100%"><tr><td colspan=3>cell1</td><td>cell2</td><td width="10%">cell3</td></tr></table>'
	 * @param $rows array with rows, each row is an array of the cols
	 * @param $options options for the table-tag
	 * @result string with html-code of the table
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
		$html = str_replace('<select','<select onchange="this.form.submit()" ',$sbox);
		if ($no_script)
		{
		$html .= '<noscript>'.$this->submit_button('send','>').'</noscript>';
		}
		return $html;
	}

	function progressbar( $percent,$title='',$options='',$width='',$color='',$height='' )
	{
		$percent = (int) $percent;
		if (!$width) $width = '30px';
		if (!$height)$height= '5px';
		if (!$color) $color = '#D00000';
		$title = $title ? $this->htmlspecialchars($title) : $percent.'%';

		if ($this->netscape4)
		{
			return $title;
		}
		return '<div title="'.$title.'" '.$options.
			' style="height: '.$height.'; width: '.$width.'; border: 1px solid black; padding: 1px;'.
			(stristr($options,'onclick="') ? ' cursor: pointer; cursor: hand;' : '').'">'."\n\t".
			'<div style="height: '.$height.'; width: '.$percent.'%; background: '.$color.';"></div>'."\n</div>\n";
	}

	function image( $app,$name,$title='',$options='' )
	{
		$name = str_replace(array('.gif','.GIF','.png','.PNG'),'',$name);

		if (!($path = $GLOBALS['phpgw']->common->image($app,$name)))
		{
			$path = $name;		// name may already contain absolut path
		}
		if (!@is_readable(str_replace($GLOBALS['phpgw_info']['server']['webserver_url'],PHPGW_SERVER_ROOT,$path)))
		{
			// if the image-name is a percentage, use a progressbar
			if (substr($name,-1) == '%' && is_numeric($percent = substr($name,0,-1)))
			{
				return $this->progressbar($percent,$title);
			}
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
		//echo "<p>html::a_href('".htmlspecialchars($content)."','$url',".print_r($vars,True).") = ".$this->link($url,$vars)."</p>";
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

	/**
	 * formats option-string for most of the above functions
	 *
	 * Example: formatOptions('100%,,1','width,height,border') = ' width="100%" border="1"'
	 * @param $options mixed String (or Array) with option-values eg. '100%,,1'
	 * @param $names mixed String (or Array) with the option-names eg. 'WIDTH,HEIGHT,BORDER'
	 * @result string with options/attributes
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

	/**
	 * returns simple stylesheet (incl. <STYLE> tags) for nextmatch row-colors
	 * @result the classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
	 */
	function themeStyles()
	{
		return $this->style($this->theme2css());
	}

	/**
	 * returns simple stylesheet for nextmatch row-colors
	 * @result the classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
	 */
	function theme2css()
	{
		return ".th { background: ".$GLOBALS['phpgw_info']['theme']['th_bg']."; }\n".
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
