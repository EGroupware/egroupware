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

/**
 * generates html with methods representing html-tags or higher widgets
 *
 * @package api
 * @subpackage html
 * @author RalfBecker-AT-outdoor-training.de
 * @license GPL
 */
class html
{
	/**
	 * user-agent: 'mozilla','msie','konqueror', 'safari', 'opera'
	 * @var string
	 */
	var $user_agent;
	/**
	 * version of user-agent as specified by browser
	 * @var string
	 */
	var $ua_version;
	/**
	 * what attribute to use for the title of an image: 'title' for everything but netscape4='alt'
	 * @var string
	 */
	var $prefered_img_title;
	/**
	 * charset used by the page, as returned by $GLOBALS['phpgw']->translation->charset()
	 * @var string
	 */
	var $charset;
	/**
	 * URL (NOT path) of the js directory in the api
	 * @var string
	 */
	var $phpgwapi_js_url;
	/**
	 * do we need to set the wz_tooltip class, to be included at the end of the page
	 * @var boolean
	 */
	var $wz_tooltips_included = False;
	
	/**
	 * Constructor: initialised the class-vars
	 */
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
	* Created an input-field with an attached color-picker
	*
	* Please note: it need to be called before the call to phpgw_header() !!!
	*
	* @param string $name the name of the input-field
	* @param string $value the actual value for the input-field, default ''
	* @param string $title tooltip/title for the picker-activation-icon
	* @return string the html
	*/
	function inputColor($name,$value='',$title='')
	{
		$id = str_replace(array('[',']'),array('_',''),$name).'_colorpicker';
		$onclick = "javascript:window.open('".$this->phpgwapi_js_url.'/colorpicker/select_color.html?id='.urlencode($id)."&color='+document.getElementById('$id').value,'colorPicker','width=240,height=187,scrollbars=no,resizable=no,toolbar=no');";
		return '<input type="text" name="'.$name.'" id="'.$id.'" value="'.$this->htmlspecialchars($value).'" /> '.
			'<a href="#" onclick="'.$onclick.'">'.
			'<img src="'.$this->phpgwapi_js_url.'/colorpicker/ed_color_bg.gif'.'"'.($title ? ' title="'.$this->htmlspecialchars($title).'"' : '')." /></a>";
	}
	
	/**
	* Handles tooltips via the wz_tooltip class from Walter Zorn
	*
	* Note: The wz_tooltip.js file gets automaticaly loaded at the end of the page
	*
	* @param string/boolean $text text or html for the tooltip, all chars allowed, they will be quoted approperiate
	*	Or if False the content (innerHTML) of the element itself is used.
	* @param boolean $do_lang (default False) should the text be run though lang()
	* @param array $options param/value pairs, eg. 'TITLE' => 'I am the title'. Some common parameters:
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
		
		$opt_out = 'this.T_WIDTH = 200;';
		if (is_array($options))
		{
			foreach($options as $option => $value)
			{
				$opt_out .= 'this.T_'.strtoupper($option).'='.(is_numeric($value)?$value:"'".str_replace("'","\\'",$value)."'").'; ';
			}
		}
		if ($text === False) return ' onmouseover="'.$opt_out.'return escape(this.innerHTML);"';
		
		return ' onmouseover="'.$opt_out.'return escape(\''.str_replace(array("\n","\r","'",'"'),array('','',"\\'",'&quot;'),$text).'\')"';
	}
	
	/**
	 * activates URLs in a text, URLs get replaced by html-links
	 *
	 * @param string $content text containing URLs
	 * @return string html with activated links
	 */
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
		$Subdir = '([\w\-\.,@?^=%&;:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$Expr = '/' . $NotAnchor . $Protocol . $Domain . $Subdir . '/i';
		
		$result = preg_replace( $Expr, "<a href=\"$0\" target=\"_blank\">$2$3</a>", $result );
		
		//  Now match things beginning with www.
		$NotHTTP = '(?<!:\/\/)';
		$Domain = 'www(.[\w]+)';
		$Subdir = '([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$Expr = '/' . $NotAnchor . $NotHTTP . $Domain . $Subdir . '/i';
		
		return preg_replace( $Expr, "<a href=\"http://$0\" target=\"_blank\">$0</a>", $result );
	}
	
	/**
	 * escapes chars with special meaning in html as entities
	 *
	 * Allows to use and char in the html-output and prefents XSS attacks.
	 * Some entities are allowed and get NOT escaped: 
	 * - &# some translations (AFAIK the arabic ones) need this
	 * - &nbsp; &lt; &gt; for convinience
	 *
	 * @param string $str string to escape
	 * @return string
	 */
	function htmlspecialchars($str)
	{
		// add @ by lkneschke to supress warning about unknown charset
		$str = @htmlspecialchars($str,ENT_COMPAT,$this->charset);
		
		// we need '&#' unchanged, so we translate it back
		$str = str_replace(array('&amp;#','&amp;nbsp;','&amp;lt;','&amp;gt;'),array('&#','&nbsp;','&lt;','&gt;'),$str);

		return $str;
	}
	
	/**
	 * allows to show and select one item from an array
	 *
	 * @param string $name	string with name of the submitted var which holds the key of the selected item form array
	 * @param string/array $key key(s) of already selected item(s) from $arr, eg. '1' or '1,2' or array with keys
	 * @param array $arr array with items to select, eg. $arr = array ( 'y' => 'yes','n' => 'no','m' => 'maybe');
	 * @param boolean $no_lang NOT run the labels of the options through lang(), default false=use lang()
	 * @param string $options additional options (e.g. 'width')
	 * @param int $multiple number of lines for a multiselect, default 0 = no multiselect
	 * @return string to set for a template or to echo into html page
	 */
	function select($name, $key, $arr=0,$no_lang=false,$options='',$multiple=0)
	{
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
		foreach($arr as $k => $data)
		{
			if (!is_array($data))
			{
				$out .= $this->select_option($k,$data,$key,$no_lang);
			}
			else
			{
				$out .= '<optgroup label="'.$this->htmlspecialchars($no_lang || $k == '' ? $k : lang($k))."\">\n";
				
				foreach($data as $k => $label)
				{
					$out .= $this->select_option($k,$label,$key,$no_lang);
				}
				$out .= "</optgroup>\n";
			}
		}
		$out .= "</select>\n";
		
		return $out;
	}
	
	/**
	 * emulating a multiselectbox using checkboxes
	 *
	 * Unfortunaly this is not in all aspects like a multi-selectbox, eg. you cant select options via javascript
	 * in the same way. Therefor I made it an extra function.
	 *
	 * @param string $name	string with name of the submitted var which holds the key of the selected item form array
	 * @param string/array $key key(s) of already selected item(s) from $arr, eg. '1' or '1,2' or array with keys
	 * @param array $arr array with items to select, eg. $arr = array ( 'y' => 'yes','n' => 'no','m' => 'maybe');
	 * @param boolean $no_lang NOT run the labels of the options through lang(), default false=use lang()
	 * @param string $options additional options (e.g. 'width')
	 * @param int $multiple number of lines for a multiselect, default 3
	 * @return string to set for a template or to echo into html page
	 */
	function checkbox_multiselect($name, $key, $arr=0,$no_lang=false,$options='',$multiple=3)
	{
		if (!is_array($arr))
		{
			$arr = array('no','yes');
		}
		if ((int)$multiple <= 0) $multiple = 1;

		if (substr($name,-2) != '[]')
		{
			$name .= '[]';
		}
		$base_name = substr($name,0,-2);
		
		if (!is_array($key))
		{
			// explode on ',' only if multiple values expected and the key contains just numbers and commas
			$key = preg_match('/^[,0-9]+$/',$key) ? explode(',',$key) : array($key);
		}
		$html = '';
		$options_no_id = preg_replace('/id="[^"]+"/i','',$options);
		foreach($arr as $val => $label)
		{
			if ($label && !$no_lang) $label = lang($label);
			
			if (strlen($label) > $max_len) $max_len = strlen($label);
			
			$html .= $this->label($this->checkbox($name,in_array($val,$key),$val,$options_no_id.' id="'.$base_name.'['.$val.']'.'" ').
				$this->htmlspecialchars($label),$base_name.'['.$val.']')."<br />\n";
		}
		$style = 'height: '.(1.7*$multiple).'em; width: '.(4+0.6*$max_len).'em; background-color: white; overflow: auto; border: lightgray 2px inset;';
		
		return $this->div($html,$options,'',$style);
	}
	
	/**
	 * generates an option-tag for a selectbox
	 *
	 * @param string $value value
	 * @param string $label label
	 * @param mixed $selected value or array of values of options to mark as selected
	 * @param boolean $no_lang NOT running the label through lang(), default false=use lang()
	 * @return string html
	 */
	function select_option($value,$label,$selected,$no_lang=0)
	{
		return '<option value="'.$this->htmlspecialchars($value).'"'.
			(in_array($value,$selected) ? ' selected="1"' : '') . ">".
			$this->htmlspecialchars($no_lang || $label == '' ? $label : lang($label)) . "</option>\n";
	}
	
	/**
	 * generates a div-tag
	 *
	 * @param string $content of a div, or '' to generate only the opening tag
	 * @param string $options to include in the tag, default ''=none
	 * @param string $class css-class attribute, default ''=none
	 * @param string $style css-styles attribute, default ''=none
	 * @return string html
	 */
	function div($content,$options='',$class='',$style='')
	{
		if ($class) $options .= ' class="'.$class.'"';
		if ($style) $options .= ' style="'.$style.'"';
		
		return "<div $options>\n".($content ? "$content</div>\n" : '');
	}
	
	/**
	 * generate one or more hidden input tag(s)
	 *
	 * @param array/string $vars var-name or array with name / value pairs
	 * @param string $value value if $vars is no array, default ''
	 * @param boolean $ignore_empty if true all empty, zero (!) or unset values, plus filer=none
	 * @param string html
	 */
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
	
	/**
	 * generate a textarea tag
	 *
	 * @param string $name name attr. of the tag
	 * @param string $value default 
	 * @param boolean $ignore_empty if true all empty, zero (!) or unset values, plus filer=none
	 * @param string html
	 */
	function textarea($name,$value='',$options='' )
	{
		return "<textarea name=\"$name\" $options>".$this->htmlspecialchars($value)."</textarea>\n";
	}
	
	/**
	 * Checks if HTMLarea (or an other richtext editor) is availible for the used browser
	 *
	 * @return boolean
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
	 *
	 * @param string $name name and id of the input-field
	 * @param string $content of the htmlarea (will be run through htmlspecialchars !!!), default ''
	 * @param string $style inline styles, eg. dimension of textarea element
	 * @param string $base_href set a base href to get relative image-pathes working
	 * @param string $plugins plugins to load seperated by comma's, eg 'TableOperations,ContextMenu'
	 * (htmlarea breaks when a plugin calls a nonexisiting lang file)
	 * @param string $custom_toolbar when given this toolbar lay-out replaces the default lay-out.
	 * @return string the necessary html for the textarea
	 */
	function htmlarea($name,$content='',$style='',$base_href='',$plugins='',$custom_toolbar='')
	{
		// check if htmlarea is availible for the browser and use a textarea if not
		if (!$this->htmlarea_availible())
		{
			return $this->textarea($name,$content,'style="'.$style.'"');
		}
		
		$id = str_replace(array('[',']'),array('_',''),$name);	// no brakets in the id allowed by js
		
		if($custom_toolbar)
		{
			$custom_toolbar='htmlareaConfig_'.$id.'.toolbar = '.$custom_toolbar;
		}
		
		if (!$style) 
		{
			$style = 'width:100%; min-width:500px; height:300px;';
		}
		
		if (!$plugins)
		{
			$plugins = 'ContextMenu,TableOperations,SpellChecker';
		}
		
		if (!is_object($GLOBALS['phpgw']->js))
		{
			$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
		}
		
		/* do stuff once */
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
				}
			}		
		}
		
		/* do stuff for every htmlarea in the page */
		
		if (!empty($plugins)) 
		{
			foreach(explode(',',$plugins) as $plg_name)
			{
				//$load_plugin_string .= 'HTMLArea.loadPlugin("'.$plg_name.'");'."\n";
				$register_plugin_string .= 'ret_editor = editor.registerPlugin("'.$plg_name.'");'."\n";
				//			   $register_plugin_string .= 'editor.registerPlugin("'.$plg_name.'");'."\n";
			}
		}
		
		// FIXME strange bug when plugins are registered fullscreen editor don't work anymore
		$GLOBALS['phpgw_info']['flags']['java_script'] .=
			'<script type="text/javascript">
		
// Replacement for the replace-helperfunction to make it possible to include plugins.
HTMLArea.replace_'.$id.' = function(id, config)
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

var htmlareaConfig_'.$id.' = new HTMLArea.Config();

'.$custom_toolbar.'

htmlareaConfig_'.$id.'.editorURL = '."'$this->phpgwapi_js_url/htmlarea/';";
		
		$GLOBALS['phpgw_info']['flags']['java_script'] .="</script>\n";
		
		
		$GLOBALS['phpgw']->js->set_onload("HTMLArea.replace_$id('$id',htmlareaConfig_$id);");
		
		if (!empty($style)) $style = " style=\"$style\"";
		
		return "<textarea name=\"$name\" id=\"$id\"$style>".$this->htmlspecialchars($content)."</textarea>\n";
	}
	
	/**
	 * represents html's input tag
	 *
	 * @param string $name name
	 * @param string $value default value of the field
	 * @param string $type type, default ''=not specified = text
	 * @param string $options attributes for the tag, default ''=none
	 */
	function input($name,$value='',$type='',$options='' )
	{
		if ($type)
		{
			$type = 'type="'.$type.'"';
		}
		return "<input $type name=\"$name\" value=\"".$this->htmlspecialchars($value)."\" $options />\n";
	}
	
	/**
	 * represents html's button (input type submit or image)
	 *
	 * @param string $name name
	 * @param string $label label of the button
	 * @param string $onClick javascript to call, when button is clicked
	 * @param boolean $no_lang NOT running the label through lang(), default false=use lang()
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $image to show instead of the label, default ''=none
	 * @param string $app app to search the image in
	 * @return string html
	 */
	function submit_button($name,$label,$onClick='',$no_lang=false,$options='',$image='',$app='phpgwapi')
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
			$label = lang($label);
		}
		if (($accesskey = strstr($label,'&')) && $accesskey[1] != ' ' &&
			(($pos = strpos($accesskey,';')) === False || $pos > 5))
		{
			$label_u = str_replace('&'.$accesskey[1],'<u>'.$accesskey[1].'</u>',$label);
			$label = str_replace('&','',$label);
			$options = 'accesskey="'.$accesskey[1].'" '.$options;
		}
		else
		{
			$accesskey = '';
			$label_u = $label;
		}
		if ($onClick) $options .= ' onclick="'.str_replace('"','\\"',$onClick).'"';
	
		// <button> is not working in all cases if ($this->user_agent == 'mozilla' && $this->ua_version < 5 || $image)
		{
			return $this->input($name,$label,$image != '' ? 'image' : 'submit',$options.$image);
		}
		return '<button type="submit" name="'.$name.'" value="'.$label.'" '.$options.' />'.
			($image != '' ? "<img$image $this->prefered_img_title=\"$label\"> " : '').
			($image == '' || $accesskey ? $label_u : '').'</button>';
	}
	
	/**
	 * creates an absolut link + the query / get-variables
	 *
	 * Example link('/index.php?menuaction=infolog.uiinfolog.get_list',array('info_id' => 123))
	 *	gives 'http://domain/phpgw-path/index.php?menuaction=infolog.uiinfolog.get_list&info_id=123'
	 *
	 * @param string $url phpgw-relative link, may include query / get-vars
	 * @param array/string $vars query or array ('name' => 'value', ...) with query
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
	
	/**
	 * represents html checkbox
	 *
	 * @param string $name name
	 * @param boolean $checked box checked on display
	 * @param string $value value the var should be set to, default 'True'
	 * @param string $options attributes for the tag, default ''=none
	 * @return string html
	 */
	function checkbox($name,$checked=false,$value='True',$options='')
	{
		return '<input type="checkbox" name="'.$name.'" value="'.$this->htmlspecialchars($value).'"' .($checked ? ' checked="1"' : '') . "$options />\n";
	}
	
	/**
	 * represents a html form
	 *
	 * @param string $content of the form, if '' only the opening tag gets returned
	 * @param array $hidden_vars array with name-value pairs for hidden input fields
	 * @param string $url eGW relative URL, will be run through the link function
	 * @param string/array $url_vars parameters for the URL, send to link function too
	 * @param string $name name of the form, defaul ''=none
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $method method of the form, default 'POST'
	 * @return string html
	 */
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
	
	/**
	 * represents a html form with one button
	 *
	 * @param string $name name of the button
	 * @param string $label label of the button
	 * @param array $hidden_vars array with name-value pairs for hidden input fields
	 * @param string $url eGW relative URL, will be run through the link function
	 * @param string/array $url_vars parameters for the URL, send to link function too
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $form_name name of the form, defaul ''=none
	 * @param string $method method of the form, default 'POST'
	 * @return string html
	 */
	function form_1button($name,$label,$hidden_vars,$url,$url_vars='',$form_name='',$method='POST')
	{
		return $this->form($this->submit_button($name,$label),$hidden_vars,$url,$url_vars,$form_name,'',$method);
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
	 *
	 * @param array $rows with rows, each row is an array of the cols
	 * @param string $options options for the table-tag
	 * @param boolean $no_table_tr dont return the table- and outmost tr-tabs, default false=return table+tr
	 * @return string with html-code of the table
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
		if (!is_array($rows))
		{
			echo "<p>".function_backtrace()."</p>\n";
		}
		$html .= "</table>\n";
		
		if ($no_table_tr)
		{
			$html = substr($html,0,-16);
		}
		return $html;
	}
	
	/**
	 * changes a selectbox to submit the form if it gets changed, to be used with the sbox-class
	 *
	 * @param string $sbox html with the select-box
	 * @param boolean $no_script if true generate a submit-button if javascript is off
	 * @return string html
	 */
	function sbox_submit( $sbox,$no_script=false )
	{
		$html = str_replace('<select','<select onchange="this.form.submit()" ',$sbox);
		if ($no_script)
		{
			$html .= '<noscript>'.$this->submit_button('send','>').'</noscript>';
		}
		return $html;
	}
	
	/**
	 * html-widget showing progessbar with a view div's (html4 only, textual percentage otherwise)
	 *
	 * @param mixed $percent percent-value, gets casted to int
	 * @param string $title title for the progressbar, default ''=the percentage itself
	 * @param string $options attributes for the outmost div (may include onclick="...")
	 * @param string $width width, default 30px
	 * @param string $color color, default '#D00000' (dark red)
	 * @param string $height height, default 5px
	 * @return string html
	 */	 
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
	
	/**
	 * representates a html img tag, output a picture
	 *
	 * If the name ends with a '%' and the rest is numeric, a progressionbar is shown instead of an image.
	 * The vfs:/ pseudo protocoll allows to access images in the vfs, eg. vfs:/home/ralf/me.png
	 * Instead of a name you specify an array with get-vars, it is passed to eGW's link function.
	 * This way session-information gets passed, eg. $name=array('menuaction'=>'myapp.class.image','id'=>123).
	 *
	 * @param string $app app-name to search the image
	 * @param string/array $name image-name or URL (incl. vfs:/) or array with get-vars
	 * @param string $title tooltip, default '' = none
	 * @param string $options further options for the tag, default '' = none
	 * @return string the html
	 */
	function image( $app,$name,$title='',$options='' )
	{
		if (substr($name,0,5) == 'vfs:/')	// vfs pseudo protocoll
		{
			$parts = explode('/',substr($name,4));
			$file = array_pop($parts);
			$path = implode('/',$parts);
			$name = array(
				'menuaction' => 'filemanager.uifilemanager.view',
				'path'       => rawurlencode(base64_encode($path)),
				'file'       => rawurlencode(base64_encode($file)),
			);
		}
		if (is_array($name))	// menuaction and other get-vars
		{
			$name = $GLOBALS['phpgw']->link('/index.php',$name);
		}
		if ($name[0] == '/' || substr($name,0,7) == 'http://' || substr($name,0,8) == 'https://')
		{
			$url = $name;
		}
		else	// no URL, so try searching the image
		{
			$name = str_replace(array('.gif','.GIF','.png','.PNG'),'',$name);
			
			if (!($url = $GLOBALS['phpgw']->common->image($app,$name)))
			{
				$url = $name;		// name may already contain absolut path
			}
			if(!$GLOBALS['phpgw_info']['server']['webserver_url'])
			{
				$base_path = "./";
			}
			if (!@is_readable($base_path . str_replace($GLOBALS['phpgw_info']['server']['webserver_url'],PHPGW_SERVER_ROOT,$url)))
			{
				// if the image-name is a percentage, use a progressbar
				if (substr($name,-1) == '%' && is_numeric($percent = substr($name,0,-1)))
				{
					return $this->progressbar($percent,$title);
				}
				return $title;
			}
		}
		if ($title)
		{
			$options .= " $this->prefered_img_title=\"".$this->htmlspecialchars($title).'"';
		}
		return "<img src=\"$url\" $options />";
	}
	
	/**
	 * representates a html link
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @param string $url eGW relative URL, will be run through the link function
	 * @param string/array $vars parameters for the URL, send to link function too
	 * @param string $options attributes for the tag, default ''=none
	 * @return string the html
	 */
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
	
	/**
	 * representates a b tab (bold)
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @return string the html
	 */
	function bold($content)
	{
		return '<b>'.$content.'</b>';
	}
	
	/**
	 * representates a i tab (bold)
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @return string the html
	 */
	function italic($content)
	{
		return '<i>'.$content.'</i>';
	}
	
	/**
	 * representates a hr tag (horizontal rule)
	 *
	 * @param string $width default ''=none given
	 * @param string $options attributes for the tag, default ''=none
	 * @return string the html
	 */
	function hr($width='',$options='')
	{
		if ($width) $options .= " width=\"$width\"";

		return "<hr $options />\n";
	}
	
	/**
	 * formats option-string for most of the above functions
	 *
	 * Example: formatOptions('100%,,1','width,height,border') = ' width="100%" border="1"'
	 *
	 * @param mixed $options String (or Array) with option-values eg. '100%,,1'
	 * @param mixed $names String (or Array) with the option-names eg. 'WIDTH,HEIGHT,BORDER'
	 * @return string with options/attributes
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
	 *
	 * @deprecated  included now always by the framework
	 * @return string classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
	 */
	function themeStyles()
	{
		return $this->style($this->theme2css());
	}
	
	/**
	 * returns simple stylesheet for nextmatch row-colors
	 *
	 * @deprecated included now always by the framework
	 * @return string classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
	 */
	function theme2css()
	{
		return ".th { background: ".$GLOBALS['phpgw_info']['theme']['th_bg']."; }\n".
			".row_on,.th_bright { background: ".$GLOBALS['phpgw_info']['theme']['row_on']."; }\n".
			".row_off { background: ".$GLOBALS['phpgw_info']['theme']['row_off']."; }\n";
	}
	
	/**
	 * html style tag (incl. type)
	 *
	 * @param string $styles css-style definitions
	 * @return string html
	 */
	function style($styles)
	{
		return $styles ? "<style type=\"text/css\">\n<!--\n$styles\n-->\n</style>" : '';
	}
	
	/**
	 * html label tag
	 *
	 * @param string $content the label
	 * @param string $id for the for attribute, default ''=none
	 * @param string $accesskey accesskey, default ''=none
	 * @param string $options attributes for the tag, default ''=none
	 * @return string the html
	 */	 
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
	
	/**
	 * html fieldset, eg. groups a group of radiobuttons
	 *
	 * @param string $content the content
	 * @param string $legend legend / label of the fieldset, default ''=none
	 * @param string $options attributes for the tag, default ''=none
	 * @return string the html
	 */	 
	function fieldset($content,$legend='',$options='')
	{
		$html = "<fieldset $options>".($legend ? '<legend>'.$this->htmlspecialchars($legend).'</legend>' : '')."\n";
		
		if ($content)
		{
			$html .= $content;
			$html .= "\n</fieldset>\n";
		}
		return $html;
	}		
}
