<?php
/**
 * eGroupWare API: generates html with methods representing html-tags or higher widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright 2001-2009 by RalfBecker@outdoor-training.de
 * @package api
 * @subpackage html
 * @version $Id$
 */

/**
 * Generates html with methods representing html-tags or higher widgets
 *
 * The class has only static methods now, so there's no need to instanciate as object anymore!
 */
class html
{
	/**
	 * user-agent: 'firefox', 'msie', 'safari' (incl. iPhone), 'chrome', 'opera', 'konqueror', 'mozilla'
	 * @var string
	 */
	static $user_agent;
	/**
	 * User agent is a mobile browser
	 * @var boolean
	 */
	static $ua_mobile;
	/**
	 * version of user-agent as specified by browser
	 * @var string
	 */
	static $ua_version;
	/**
	 * what attribute to use for the title of an image: 'title' for everything but netscape4='alt'
	 * @var string
	 */
	static $netscape4;
	static private $prefered_img_title;
	/**
	 * charset used by the page, as returned by $GLOBALS['egw']->translation->charset()
	 * @var string
	 */
	static $charset;
	/**
	 * URL (NOT path) of the js directory in the api
	 * @var string
	 */
	static $api_js_url;

	/**
	 * Automatically turn on enhanced selectboxes if there's more than this many options
	 */
	const SELECT_ENHANCED_ROW_COUNT = 12;

	/**
	 * initialise our static vars
	 */
	static function _init_static()
	{
		// should be Ok for all HTML 4 compatible browsers
		if(!preg_match('/compatible; ([a-z]+)[\/ ]+([0-9.]+)/i',$_SERVER['HTTP_USER_AGENT'],$parts))
		{
			preg_match_all('/([a-z]+)\/([0-9.]+)/i',$_SERVER['HTTP_USER_AGENT'],$parts,PREG_SET_ORDER);
			$parts = $parts[2][1] == 'Chrome' ? $parts[2] : array_pop($parts);
		}
		list(,self::$user_agent,self::$ua_version) = $parts;
		if ((self::$user_agent = strtolower(self::$user_agent)) == 'version') self::$user_agent = 'opera';
		self::$ua_mobile = preg_match('/(iPhone|iPad|Android|SymbianOS)/i',$_SERVER['HTTP_USER_AGENT']);

		self::$netscape4 = self::$user_agent == 'mozilla' && self::$ua_version < 5;
		self::$prefered_img_title = self::$netscape4 ? 'alt' : 'title';
		//error_log("HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]', UserAgent: '".self::$user_agent."', Version: '".self::$ua_version."', isMobile=".array2string(self::$ua_mobile).", img_title: '".self::$prefered_img_title."'");

		if ($GLOBALS['egw']->translation)
		{
			self::$charset = $GLOBALS['egw']->translation->charset();
		}
		self::$api_js_url = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js';
	}

	/**
	* Created an input-field with an attached color-picker
	*
	* Please note: it need to be called before the call to egw_header() !!!
	*
	* @param string $name the name of the input-field
	* @param string $value='' the actual value for the input-field, default ''
	* @param string $title='' tooltip/title for the picker-activation-icon
	* @param string $options='' options for input
	* @return string the html
	*/
	static function inputColor($name,$value='',$title='',$options='')
	{
		$options .= ' id="'.htmlspecialchars($id=str_replace(array('[',']'),array('_',''),$name).'_colorpicker').'"';
		$onclick = "javascript:egw_openWindowCentered2('".self::$api_js_url.'/colorpicker/select_color.html?id='.urlencode($id)."&color='+encodeURIComponent(document.getElementById('$id').value),'colorPicker',240,187);";
		if (preg_match('/^#[0-9A-F]{6}$/i',$value))
		{
			$options .= ' style="background-color: '.$value.'"';
		}
		$options .= ' onChange="this.style.backgroundColor=this.value.match(/^(#[0-9A-F]+|[a-z]+)$/i)?this.value:\'#FFFFFF\'+(this.value=\'\')"';
		return self::input($name, $value, 'text', $options.' size="7" maxsize="7"').'&nbsp;'.
			'<a href="#" onclick="'.$onclick.'">'.
			'<img src="'.self::$api_js_url.'/colorpicker/ed_color_bg.gif'.'"'.($title ? ' title="'.self::htmlspecialchars($title).'"' : '')." /></a>";
	}

	/**
	* Handles tooltips via the wz_tooltip class from Walter Zorn
	*
	* @param string $text text or html for the tooltip, all chars allowed, they will be quoted approperiate
	* @param boolean $do_lang (default False) should the text be run though lang()
	* @param array $options param/value pairs, eg. 'TITLE' => 'I am the title'. Some common parameters:
	*  title (string) gives extra title-row, width (int,'auto') , padding (int), above (bool), bgcolor (color), bgimg (URL)
	*  For a complete list and description see http://www.walterzorn.com/tooltip/tooltip_e.htm
	* @param boolean $return_as_attributes true to return array(onmouseover, onmouseout) attributes
	* @return string|array to be included in any tag, like '<p'.html::tooltip('Hello <b>Ralf</b>').'>Text with tooltip</p>'
	*/
	static function tooltip($text,$do_lang=False,$options=False, $return_as_attributes=false)
	{
		// tell egw_framework to include wz_tooltip.js
		$GLOBALS['egw_info']['flags']['include_wz_tooltip'] = true;

		if ($do_lang) $text = lang($text);

		$ttip = 'Tip(\''.str_replace(array("\n","\r","'",'"'),array('','',"\\'",'&quot;'),$text).'\'';

		$sticky = false;
		if (is_array($options))
		{
			foreach($options as $option => $value)
			{
				$option = strtoupper($option);
				if ($option == 'STICKY') $sticky = (bool)$value;

				switch(gettype($value))
				{
					case 'boolean':
						$value = $value ? 'true' : 'false';
						break;
					case 'string':
						if (stripos($value,"'")===false) $value = "'$value'";
						break;
				}
				$ttip .= ','.$option.','.$value;
			}
		}
		$ttip .= ')';

		$untip = 'UnTip()';

		return $return_as_attributes ? array($ttip, $untip) :
			' onmouseover="'.self::htmlspecialchars($ttip).'" onmouseout="'.$untip.'"';
	}

	/**
	 * activates URLs in a text, URLs get replaced by html-links
	 *
	 * @param string $content text containing URLs
	 * @return string html with activated links
	 */
	static function activate_links($content)
	{
		if (!$content || strlen($content) < 20) return $content;	// performance

		// Exclude everything which is already a link
		$NotAnchor = '(?<!"|href=|href\s=\s|href=\s|href\s=)';

		// spamsaver emailaddress
		$result = preg_replace('/'.$NotAnchor.'mailto:([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i',
			'<a href="#" onclick="document.location=\'mai\'+\'lto:\\1\'+unescape(\'%40\')+\'\\2.\\3\'; return false;">\\1 AT \\2 DOT \\3</a>',
			$content);

		//  First match things beginning with http:// (or other protocols)
		$Protocol = '(http:\/\/|(ftp:\/\/|https:\/\/))';	// only http:// gets removed, other protocolls are shown
		$Domain = '([\w-]+\.[\w-.]+)';
		$Subdir = '([\w\-\.,@?^=%&;:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$optBracket = '';
		$Expr = '/' . $NotAnchor . $Protocol . $Domain . $Subdir . $optBracket . '/i';

		$result = preg_replace( $Expr, "<a href=\"$0\" target=\"_blank\">$2$3$4</a>", $result );

		//  Now match things beginning with www.
		$NotHTTP = '(?<!:\/\/|" target=\"_blank\">)';	//	avoid running again on http://www links already handled above
		$Domain = 'www(\.[\w-.]+)';
		$Subdir = '([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?';
		$Expr = '/' . $NotAnchor . $NotHTTP . $Domain . $Subdir . '/i';

		return preg_replace( $Expr, "<a href=\"http://$0\" target=\"_blank\">$0</a>", $result );
	}

	/**
	 * escapes chars with special meaning in html as entities
	 *
	 * Allows to use and char in the html-output and prevents XSS attacks.
	 * Some entities are allowed and get NOT escaped: -> prevented by 4th param = doubleencode=false
	 * - &# some translations (AFAIK: the arabic ones) need this;
	 * - &nbsp; &lt; &gt; for convenience -> should not happen anymore, as we do not doubleencode anymore (20101020)
	 *
	 * @param string $str string to escape
	 * @param boolean $double_encoding=false do we want double encoding or not, default no
	 * @return string
	 */
	static function htmlspecialchars($str, $double_encoding=false)
	{
		//if (!is_scalar($str) && !is_null($str)) error_log(__METHOD__.'('.array2string($str).') '.function_backtrace());
		// as EGroupware supports only utf-8 we should not need to worry about wrong charsets
		//if (is_array($str)) error_log(__METHOD__.__LINE__.' string expected -> array given:'.array2string($str).'->'.function_backtrace());
		return htmlspecialchars($str,ENT_COMPAT,self::$charset,$double_encoding);
		// we need '&#' unchanged, so we translate it back -> this is provided by 4th param = false -> do not doubleencode
		//$str = str_replace(array('&amp;#','&amp;nbsp;','&amp;lt;','&amp;gt;'),array('&#','&nbsp;','&lt;','&gt;'),$str);

		//return $str;
	}

	/**
	 * allows to show and select one item from an array
	 *
	 * @param string $name	string with name of the submitted var which holds the key of the selected item form array
	 * @param string/array $key key(s) of already selected item(s) from $arr, eg. '1' or '1,2' or array with keys
	 * @param array $arr array with items to select, eg. $arr = array ( 'y' => 'yes','n' => 'no','m' => 'maybe');
	 * @param boolean $no_lang NOT run the labels of the options through lang(), default false=use lang()
	 * @param string $options additional options (e.g. 'width')
	 * @param int $multiple number of lines for a multiselect, default 0 = no multiselect, < 0 sets size without multiple
	 * @param boolean $enhanced Use enhanced selectbox with search.  Null for default yes if more than 12 options.
	 * @return string to set for a template or to echo into html page
	 */
	static function select($name, $key, $arr=0,$no_lang=false,$options='',$multiple=0,$enhanced=null)
	{
		if(is_null($enhanced)) $enhanced = (count($arr) > self::SELECT_ENHANCED_ROW_COUNT);

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
		elseif($multiple < 0)
		{
			$options .= ' size="'.abs($multiple).'"';
		}
		// fix width for MSIE < 9 in/for selectboxes
		if (self::$user_agent == 'msie' && self::$ua_version < 9)
		{
			if (stripos($options,'onfocus="') === false)
			{
				$options .= ' onfocus="window.dropdown_menu_hack(this);" ';
			}
			else
			{
				$options = str_ireplace('onfocus="','onfocus="window.dropdown_menu_hack(this);',$options);
			}
		}
		$out = "<select name=\"$name\" $options>\n";

		if (!is_array($key))
		{
			// explode on ',' only if multiple values expected and the key contains just numbers and commas
			$key = $multiple > 0 && preg_match('/^[,0-9]+$/',$key) ? explode(',',$key) : array($key);
		}
		foreach($arr as $k => $data)
		{
			if (!is_array($data) || count($data) == 2 && isset($data['label']) && isset($data['title']))
			{
				$out .= self::select_option($k,is_array($data)?$data['label']:$data,$key,$no_lang,
					is_array($data)?$data['title']:'');
			}
			else
			{
				if (isset($data['lable']))
				{
					$k = $data['lable'];
					unset($data['lable']);
				}
				$out .= '<optgroup label="'.self::htmlspecialchars($no_lang || $k == '' ? $k : lang($k))."\">\n";

				foreach($data as $k => $label)
				{
					$out .= self::select_option($k,is_array($label)?$label['label']:$label,$key,$no_lang,
						is_array($label)?$lable['title']:'');
				}
				$out .= "</optgroup>\n";
			}
		}
		$out .= "</select>\n";

		if($enhanced) {
			egw_framework::validate_file('/phpgwapi/js/jquery/chosen/chosen.jquery.js');
			egw_framework::includeCSS('/phpgwapi/js/jquery/chosen/chosen.css',null,false);
			$out .= "<script>\$j(function() {if(\$j().chosen) \$j('select[name=\"$name\"]').chosen();});</script>\n";
		}
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
	 * @param boolean $selected_first show the selected items before the not selected ones, default true
	 * @param string $style='' extra style settings like "width: 100%", default '' none
	 * @return string to set for a template or to echo into html page
	 */
	static function checkbox_multiselect($name, $key, $arr=0,$no_lang=false,$options='',$multiple=3,$selected_first=true,$style='',$enhanced = null)
	{
		//echo "<p align=right>checkbox_multiselect('$name',".array2string($key).",".array2string($arr).",$no_lang,'$options',$multiple,$selected_first,'$style')</p>\n";
		if(is_null($enhanced)) $enhanced = (count($arr) > self::SELECT_ENHANCED_ROW_COUNT);

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

		if($enhanced) return self::select($name, $key, $arr,$no_lang,$options." style=\"$style\" ",$multiple,$enhanced);

		if (!is_array($key))
		{
			// explode on ',' only if multiple values expected and the key contains just numbers and commas
			$key = preg_match('/^[,0-9]+$/',$key) ? explode(',',$key) : array($key);
		}
		$html = '';
		$options_no_id = preg_replace('/id="[^"]+"/i','',$options);

		if ($selected_first)
		{
			$selected = $not_selected = array();
			foreach($arr as $val => $label)
			{
				if (in_array((string)$val,$key))
				{
					$selected[$val] = $label;
				}
				else
				{
					$not_selected[$val] = $label;
				}
			}
			$arr = $selected + $not_selected;
		}
		foreach($arr as $val => $label)
		{
			if (is_array($label))
			{
				$title = $label['title'];
				$label = $label['label'];
			}
			else
			{
				$title = '';
			}
			if ($label && !$no_lang) $label = lang($label);
			if ($title && !$no_lang) $title = lang($title);

			if (strlen($label) > $max_len) $max_len = strlen($label);

			$html .= self::label(self::checkbox($name,in_array((string)$val,$key),$val,$options_no_id.
				' id="'.$base_name.'['.$val.']'.'"').self::htmlspecialchars($label),
				$base_name.'['.$val.']','',($title ? 'title="'.self::htmlspecialchars($title).'" ':''))."<br />\n";
		}
		if ($style && substr($style,-1) != ';') $style .= '; ';
		if (strpos($style,'height')===false) $style .= 'height: '.(1.7*$multiple).'em; ';
		if (strpos($style,'width')===false)  $style .= 'width: '.(4+$max_len*($max_len < 15 ? 0.65 : 0.6)).'em; ';
		$style .= 'background-color: white; overflow: auto; border: lightgray 2px inset; text-align: left;';

		return self::div($html,$options,'',$style);
	}

	/**
	 * generates an option-tag for a selectbox
	 *
	 * @param string $value value
	 * @param string $label label
	 * @param mixed $selected value or array of values of options to mark as selected
	 * @param boolean $no_lang NOT running the label through lang(), default false=use lang()
	 * @param string $extra extra text, e.g.: style="", default: ''
	 * @return string html
	 */
	static function select_option($value,$label,$selected,$no_lang=0,$title='',$extra='')
	{
		// the following compares strict as strings, to archive: '0' == 0 != ''
		// the first non-strict search via array_search, is for performance reasons, to not always search the whole array with php
		if (($found = ($key = array_search($value,$selected)) !== false) && (string) $value !== (string) $selected[$key])
		{
			$found = false;
			foreach($selected as $sel)
			{
				if ($found = (((string) $value) === ((string) $selected[$key]))) break;
			}
		}
		return '<option value="'.self::htmlspecialchars($value).'"'.($found  ? ' selected="selected"' : '') .
			($title ? ' title="'.self::htmlspecialchars($no_lang ? $title : lang($title)).'"' : '') .
			($extra ? ' ' . $extra : '') . '>'.
			self::htmlspecialchars($no_lang || $label == '' ? $label : lang($label)) . "</option>\n";
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
	static function div($content,$options='',$class='',$style='')
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
	static function input_hidden($vars,$value='',$ignore_empty=True)
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
				$html .= "<input type=\"hidden\" name=\"$name\" value=\"".self::htmlspecialchars($value)."\" />\n";
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
	 * @param boolean $double_encoding=false do we want double encoding or not, default no
	 * @param string html
	 */
	static function textarea($name,$value='',$options='',$double_encoding=false)
	{
		return "<textarea name=\"$name\" $options>".self::htmlspecialchars($value,$double_encoding)."</textarea>\n";
	}

	/**
	 * Checks if HTMLarea (or an other richtext editor) is availible for the used browser
	 *
	 * @return boolean
	 */
	static function htmlarea_availible()
	{
		//error_log(__METHOD__.__LINE__.' userAgent:'.$_SERVER[HTTP_USER_AGENT]);
		// we check for the useragent to be able to recognize andoid machines that do not support ckeditor yet.
		// ckeditors fallback is rather unpleaseant.
		if (stripos($_SERVER[HTTP_USER_AGENT],'android') !== false) return false;

		// this one is for testing how it will turn out, if you do not have the device or agent ready at your fingertips
		// if (stripos($_SERVER[HTTP_USER_AGENT],'mozilla') !== false) return false;

		// CKeditor3 will doublecheck availability for us, but its fallback does not look nice, and you will get
		// no conversion of html content to plain text, so we provide a check for known USER_AGENTS to fail the test
		return true;
	}

	/**
	 * compability static function for former used htmlarea. Please use static function fckeditor now!
	 *
	 * creates a textarea inputfield for the htmlarea js-widget (returns the necessary html and js)
	 */
	static function htmlarea($name,$content='',$style='',$base_href='',$plugins='',$custom_toolbar='',$set_width_height_in_config=false)
	{
		/*if (!self::htmlarea_availible())
		{
			return self::textarea($name,$content,'style="'.$style.'"');
		}*/
		return self::fckEditor($name, $content, $style, array('toolbar_expanded' =>'true'), '400px', '100%', $base_href);
	}

	/**
	* this static function is a wrapper for fckEditor to create some reuseable layouts
	*
	* @param string $_name name and id of the input-field
	* @param string $_content of the tinymce (will be run through htmlspecialchars !!!), default ''
	* @param string $_mode display mode of the tinymce editor can be: simple, extended or advanced
	* @param array  $_options (toolbar_expanded true/false)
	* @param string $_height='400px'
	* @param string $_width='100%'
	* @param string $_start_path='' if passed activates the browser for image at absolute path passed
	* @param boolean $_purify=true run $_content through htmlpurifier before handing it to fckEditor
	* @param mixed (boolean/string) $_focusToBody USED only for CKEDIOR true means yes, focus on top, you may specify TOP or BOTTOM (to focus on the end of the editor area)
	* @return string the necessary html for the textarea
	*/
	static function fckEditor($_name, $_content, $_mode, $_options=array('toolbar_expanded' =>'true'),
		$_height='400px', $_width='100%',$_start_path='',$_purify=true, $_focusToBody=false)
	{
		if (!self::htmlarea_availible() || $_mode == 'ascii')
		{
			return self::textarea($_name,$_content,'style="width: '.$_width.'; height: '.$_height.';" id="'.htmlspecialchars($_name).'"');
		}

		//include the ckeditor js file
		egw_framework::validate_file('ckeditor','ckeditor','phpgwapi');

		// run content through htmlpurifier
		if ($_purify && !empty($_content))
			$_content = self::purify($_content);

		// By default the editor start expanded
		$expanded = isset($_options['toolbar_expanded']) ?
			$_options['toolbar_expanded'] == 'true' : true;

		//Get the height in pixels from the pixels parameter
		$pxheight = (strpos('px', $_height) === false) ?
			(empty($_height) ? 400 : $_height) : str_replace('px', '', $_height);

		// User preferences
		$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
		$font_size = egw_ckeditor_config::font_size_from_prefs();
		$font_span = '<span '.($font||$font_size?'style=\"':'').($font?'font-family:'.$font.'; ':'').($font_size?'font-size:'.$font_size.'; ':'').'\">';
		if (empty($font) && empty($font_size)) $font_span = '';

		// we need to enable double encoding here, as ckEditor has to undo one level of encoding
		// otherwise < and > chars eg. from html markup entered in regular (not source) input, will turn into html!
		return self::textarea($_name,$_content,'id="'.htmlspecialchars($_name).'"',true).	// true = double encoding
'
<script type="text/javascript">
	window.CKEDITOR_BASEPATH="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/ckeditor/";
	CKEDITOR.replace("'.$_name.'", '.egw_ckeditor_config::get_ckeditor_config($_mode,
		$pxheight, $expanded, $_start_path).');
	CKEDITOR.addCss("body { margin: 5px; }");
	CKEDITOR.instances["'.$_name.'"].on(
		"instanceReady",
		function (ev)
		{
'.($_focusToBody?'
			ev.editor.focus();':'').'
			var d = ev.editor.document;
			var r = new CKEDITOR.dom.range(d);
			r.collapse(true);
			r.selectNodeContents(d.getBody());
			r.collapse('.($_focusToBody==='BOTTOM'?'false':'true').');
			r.select();'.($font_span?'
			//this stuff is needed, as the above places the caret just before the span tag
			var sN = r.startContainer.getNextSourceNode();
			//FF is selecting the span with getNextSourceNode, other browsers need to fetch it with getNext
			r.selectNodeContents(((typeof sN.getName==="function") && sN.getName()=="span"?r.startContainer.getNextSourceNode():r.startContainer.getNextSourceNode().getNext()));
			r.collapse(true);
			r.select();'.'':'').'
			ev.editor.resize("100%", '.str_replace('px', '', $pxheight).');
		}
	);'.
	(trim($_content) == '' && $font_span ? 'CKEDITOR.instances["'.$_name.'"].setData("'.$font_span.'&#8203;</span>");' : '').
'</script>
';
	}

	/**
	* this static function is a wrapper for tinymce to create some reuseable layouts
	*
	* Please note: if you did not run init_tinymce already you this static function need to be called before the call to phpgw_header() !!!
	*
	* @param string $_name name and id of the input-field
	* @param string $_mode display mode of the tinymce editor can be: simple, extended or advanced
	* @param string $_content='' of the tinymce (will be run through htmlspecialchars !!!), default ''
	* @param string $_height='400px'
	* @param string $_width='100%'
	* @param boolean $_purify=true
	* @param string $_border='0px' NOT used for CKEditor
	* @param mixed (boolean/string) $_focusToBody USED only for CKEDIOR true means yes, focus on top, you may specify TOP or BOTTOM (to focus on the end of the editor area)
	* @return string the necessary html for the textarea
	*/
	static function fckEditorQuick($_name, $_mode, $_content='', $_height='400px', $_width='100%',$_purify=true, $_border='0px',$_focusToBody=false)
	{
		if (!self::htmlarea_availible() || $_mode == 'ascii')
		{
			//TODO: use self::textarea
			return "<textarea name=\"$_name\" style=\"".($_width?" width:".$_width.';':" width:100%;").($_height?" height:".$_height.';':" height:400px;").($_border?" border:".$_border.';':" border:0px;")."\">$_content</textarea>";
		}
		else
		{
			return self::fckEditor($_name, $_content, $_mode, array(), $_height, $_width,'',$_purify,$_focusToBody);
		}
	}

	/**
	 * represents html's input tag
	 *
	 * @param string $name name
	 * @param string $value default value of the field
	 * @param string $type type, default ''=not specified = text
	 * @param string $options attributes for the tag, default ''=none
	 */
	static function input($name,$value='',$type='',$options='' )
	{
		switch ((string)$type)
		{
			case 'color':
				return self::inputColor($name, $value, '', $options);
			case '';
				break;
			default:
				$type = 'type="'.htmlspecialchars($type).'"';
		}
		return "<input $type name=\"$name\" value=\"".self::htmlspecialchars($value)."\" $options />\n";
	}

	/**
	 * represents html's button (input type submit or input type button or image)
	 *
	 * @param string $name name
	 * @param string $label label of the button
	 * @param string $onClick javascript to call, when button is clicked
	 * @param boolean $no_lang NOT running the label through lang(), default false=use lang()
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $image to show instead of the label, default ''=none
	 * @param string $app app to search the image in
	 * @param string $buttontype which type of html button (button|submit), default ='submit'
	 * @return string html
	 */
	static function submit_button($name,$label,$onClick='',$no_lang=false,$options='',$image='',$app='phpgwapi', $buttontype='submit')
	{
		// workaround for idots and IE button problem (wrong cursor-image)
		if (self::$user_agent == 'msie')
		{
			$options .= ' style="cursor: pointer;"';
		}
		if ($image != '')
		{
			$image = str_replace(array('.gif','.GIF','.png','.PNG'),'',$image);

			if (!($path = $GLOBALS['egw']->common->image($app,$image)))
			{
				$path = $image;		// name may already contain absolut path
			}
			$image = ' src="'.$path.'"';
		}
		if (!$no_lang)
		{
			$label = lang($label);
		}
		if (($accesskey = @strstr($label,'&')) && $accesskey[1] != ' ' &&
			(($pos = strpos($accesskey,';')) === false || $pos > 5))
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

		// <button> is not working in all cases if (self::$user_agent == 'mozilla' && self::$ua_version < 5 || $image)
		{
			return self::input($name,$label,$image != '' ? 'image' : $buttontype,$options.$image);
		}
		return '<button type="'.$buttontype.'" name="'.$name.'" value="'.$label.'" '.$options.' />'.
			($image != '' ? /*self::image($app,$image,$label,$options)*/'<img'.$image.' '.self::$prefered_img_title.'="'.$label.'"> ' : '').
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
	static function link($url,$vars='')
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
		return $GLOBALS['egw']->link($url,$vars);
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
	static function checkbox($name,$checked=false,$value='True',$options='')
	{
		return '<input type="checkbox" name="'.$name.'" value="'.self::htmlspecialchars($value).'"' .($checked ? ' checked="1"' : '') . "$options />\n";
	}

	/**
	 * represents a html form
	 *
	 * @param string $content of the form, if '' only the opening tag gets returned
	 * @param array $hidden_vars array with name-value pairs for hidden input fields
	 * @param string $url eGW relative URL, will be run through the link function, if empty the current url is used
	 * @param string/array $url_vars parameters for the URL, send to link static function too
	 * @param string $name name of the form, defaul ''=none
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $method method of the form, default 'POST'
	 * @return string html
	 */
	static function form($content,$hidden_vars,$url,$url_vars='',$name='',$options='',$method='POST')
	{
		$url = $url ? self::link($url,$url_vars) : $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
		$html = "<form method=\"$method\" ".($name != '' ? "name=\"$name\" " : '')."action=\"$url\" $options>\n";
		$html .= self::input_hidden($hidden_vars);

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
	 * @param string/array $url_vars parameters for the URL, send to link static function too
	 * @param string $options attributes for the tag, default ''=none
	 * @param string $form_name name of the form, defaul ''=none
	 * @param string $method method of the form, default 'POST'
	 * @return string html
	 */
	static function form_1button($name,$label,$hidden_vars,$url,$url_vars='',$form_name='',$method='POST')
	{
		return self::form(self::submit_button($name,$label),$hidden_vars,$url,$url_vars,$form_name,'',$method);
	}

	const THEAD = 1;
	const TFOOT = 2;
	const TBODY = 3;
	static $part2tag = array(
		self::THEAD => 'thead',
		self::TFOOT => 'tfoot',
		self::TBODY => 'tbody',
	);

	/**
	 * creates table from array of rows
	 *
	 * abstracts the html stuff for the table creation
	 * Example: $rows = array (
	 *  'h1' => array(	// optional header row(s)
	 *  ),
	 *  'f1' => array(	// optional footer row(s)
	 *  ),
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
	static function table($rows,$options = '',$no_table_tr=False)
	{
		$html = $no_table_tr ? '' : "<table $options>\n";

		$part = 0;
		foreach($rows as $key => $row)
		{
			if (!is_array($row))
			{
				continue;					// parameter
			}
			// get the current part from the optional 'h' or 'f' prefix of the key
			$p = $key[0] == 'h' ? html::THEAD : ($key[0] == 'f' ? html::TFOOT : html::TBODY);
			if ($part < $p && ($part || $p < self::TBODY))	// add only allowed and neccessary transitions
			{
				if ($part) $html .= '</'.self::$part2tag[$part].">\n";
				$html .= '<'.self::$part2tag[$part=$p].">\n";
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
		if ($part)	// close current part
		{
			$html .= "</".self::$part2tag[$part].">\n";
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
	static function sbox_submit( $sbox,$no_script=false )
	{
		$html = str_replace('<select','<select onchange="this.form.submit()" ',$sbox);
		if ($no_script)
		{
			$html .= '<noscript>'.self::submit_button('send','>').'</noscript>';
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
	static function progressbar( $percent,$title='',$options='',$width='',$color='',$height='' )
	{
		$percent = (int) $percent;
		if (!$width) $width = '30px';
		if (!$height)$height= '5px';
		if (!$color) $color = '#D00000';
		$title = $title ? self::htmlspecialchars($title) : $percent.'%';

		if (self::$netscape4)
		{
			return $title;
		}
		return '<div class="onlyPrint">'.$title.'</div><div class="noPrint" title="'.$title.'" '.$options.
			' style="height: '.$height.'; width: '.$width.'; border: 1px solid black; padding: 1px; text-align: left;'.
			(@stristr($options,'onclick="') ? ' cursor: pointer;' : '').'">'."\n\t".
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
	static function image( $app,$name,$title='',$options='' )
	{
		if (is_array($name))	// menuaction and other get-vars
		{
			$name = $GLOBALS['egw']->link('/index.php',$name);
		}
		if (substr($name,0,5) == 'vfs:/')	// vfs pseudo protocoll
		{
			$name = egw::link(egw_vfs::download_url(substr($name,4)));
		}
		if ($name[0] == '/' || substr($name,0,7) == 'http://' || substr($name,0,8) == 'https://' || stripos($name,'etemplate/thumbnail.php') )
		{
			if (!($name[0] == '/' || substr($name,0,7) == 'http://' || substr($name,0,8) == 'https://')) $name = '/'.$name;
			$url = $name;
		}
		else	// no URL, so try searching the image
		{
			$name = str_replace(array('.gif','.GIF','.png','.PNG'),'',$name);

			if (!($url = $GLOBALS['egw']->common->image($app,$name)))
			{
				$url = $name;		// name may already contain absolut path
			}
			if($GLOBALS['egw_info']['server']['webserver_url'])
			{
				list(,$path) = explode($GLOBALS['egw_info']['server']['webserver_url'],$url);

				if (!is_null($path)) $path = EGW_SERVER_ROOT.$path;
			}
			else
			{
				$path = EGW_SERVER_ROOT.$url;
			}

			if (is_null($path) || (!@is_readable($path) && stripos($path,'webdav.php')===false))
			{
				// if the image-name is a percentage, use a progressbar
				if (substr($name,-1) == '%' && is_numeric($percent = substr($name,0,-1)))
				{
					return self::progressbar($percent,$title);
				}
				return $title;
			}
		}
		if ($title)
		{
			$options .= ' '.self::$prefered_img_title.'="'.self::htmlspecialchars($title).'"';
		}

		// This block makes pngfix.js useless, adding a check on disable_pngfix to have pngfix.js do its thing
		if (self::$user_agent == 'msie' && self::$ua_version < 7.0 && substr($url,-4) == '.png' && ($GLOBALS['egw_info']['user']['preferences']['common']['disable_pngfix'] || !isset($GLOBALS['egw_info']['user']['preferences']['common']['disable_pngfix'])))
		{
			$extra_styles = "display: inline-block; filter:progid:DXImageTransform.Microsoft.AlphaImageLoader(src='$url',sizingMethod='image'); width: 1px; height: 1px;";
			if (false!==strpos($options,'style="'))
			{
				$options = str_replace('style="','style="'.$extra_styles, $options);
			}
			else
			{
				$options .= ' style="'.$extra_styles.'"';
			}
			return "<span $options></span>";
		}
		return "<img src=\"$url\" $options />";
	}

	/**
	 * representates a html link
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @param string $url eGW relative URL, will be run through the link function
	 * @param string/array $vars parameters for the URL, send to link static function too
	 * @param string $options attributes for the tag, default ''=none
	 * @return string the html
	 */
	static function a_href( $content,$url,$vars='',$options='')
	{
		if (is_array($url))
		{
			$vars = $url;
			$url = '/index.php';
		}
		elseif (strpos($url,'/')===false &&
			count(explode('.',$url)) >= 3 &&
			!(strpos($url,'mailto:')!==false ||
			strpos($url,'://')!==false ||
			strpos($url,'javascript:')!==false))
		{
			$url = "/index.php?menuaction=$url";
		}
		if ($url[0] == '/')		// link relative to eGW
		{
			$url = self::link($url,$vars);
		}
		//echo "<p>html::a_href('".self::htmlspecialchars($content)."','$url',".print_r($vars,True).") = ".self::link($url,$vars)."</p>";
		return '<a href="'.self::htmlspecialchars($url).'" '.$options.'>'.$content.'</a>';
	}

	/**
	 * representates a b tab (bold)
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @return string the html
	 */
	static function bold($content)
	{
		return '<b>'.$content.'</b>';
	}

	/**
	 * representates a i tab (bold)
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @return string the html
	 */
	static function italic($content)
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
	static function hr($width='',$options='')
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
	static function formatOptions($options,$names)
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
	static function themeStyles()
	{
		return self::style(self::theme2css());
	}

	/**
	 * returns simple stylesheet for nextmatch row-colors
	 *
	 * @deprecated included now always by the framework
	 * @return string classes 'th' = nextmatch header, 'row_on'+'row_off' = alternating rows
	 */
	static function theme2css()
	{
		return ".th { background: ".$GLOBALS['egw_info']['theme']['th_bg']."; }\n".
			".row_on,.th_bright { background: ".$GLOBALS['egw_info']['theme']['row_on']."; }\n".
			".row_off { background: ".$GLOBALS['egw_info']['theme']['row_off']."; }\n";
	}

	/**
	 * html style tag (incl. type)
	 *
	 * @param string $styles css-style definitions
	 * @return string html
	 */
	static function style($styles)
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
	static function label($content,$id='',$accesskey='',$options='')
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
	static function fieldset($content,$legend='',$options='')
	{
		$html = "<fieldset $options>".($legend ? '<legend>'.self::htmlspecialchars($legend).'</legend>' : '')."\n";

		if ($content)
		{
			$html .= $content;
			$html .= "\n</fieldset>\n";
		}
		return $html;
	}

	/**
	 * tree widget using dhtmlXtree
	 *
	 * Code inspired by Lars's Felamimail uiwidgets::createFolderTree()
	 *
	 * @author Lars Kneschke <lars-AT-kneschke.de> original code in felamimail
	 * @param array $_folders array of folders: pairs path => node (string label or array with keys: label, (optional) image, (optional) title, (optional) checked)
	 * @param string $_selected path of selected folder
	 * @param mixed $_topFolder=false node of topFolder or false for none
	 * @param string $_onNodeSelect='alert' js static function to call if node gets selected
	 * @param string $_tree='foldertree' id of the div and name of the variable containing the tree object
	 * @param string $_divClass='' css class of the div
	 * @param string $_leafImage='' default image of a leaf-node, ''=default of foldertree, set it eg. 'folderClosed.gif' to show leafs as folders
	 * @param boolean|string $_onCheckHandler=false string with handler-name to display a checkbox for each folder, or false (default), 'null' switches checkboxes on without an handler!
	 * @param string $delimiter='/' path-delimiter, default /
	 * @param string $folderImageDir=null string path to the tree menu images, null uses default path
	 * @param string|array $autoLoading=null EGw relative path or array with get parameter, both send through egw::link
	 * @param string $dataMode='JSON' data type for autoloading: XML, JSON, CSV
	 * @param boolean $dragndrop=false true to enable drag-n-drop (must be before autoloading get enabled!)
	 *
	 * @return string the html code, to be added into the template
	 */
	static function tree($_folders,$_selected,$_topFolder=false,$_onNodeSelect="null",$tree='foldertree',$_divClass='',
		$_leafImage='',$_onCheckHandler=false,$delimiter='/',$folderImageDir=null,$autoLoading=null,$dataMode='JSON',
		$dragndrop=false)
	{
		if(is_null($folderImageDir))
		{
			$folderImageDir = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/templates/default/images';
		}

		static $tree_initialised=false;
		if (!$tree_initialised)
		{
			egw_framework::includeCSS('/phpgwapi/js/dhtmlxtree/dhtmlxTree/codebase/dhtmlxtree.css');
			egw_framework::validate_file('dhtmlxtree','dhtmlxTree/codebase/dhtmlxcommon');
			egw_framework::validate_file('dhtmlxtree','dhtmlxTree/codebase/dhtmlxtree');
			if ($autoLoading && $dataMode != 'XML') egw_framework::validate_file('dhtmlxtree','dhtmlxTree/codebase/ext/dhtmlxtree_json');
			$tree_initialised = true;
			if (!$_folders && !$autoLoading) return $html;
		}
		$html = self::div("\n",'id="'.$tree.'"',$_divClass).$html;
		$html .= "<script type='text/javascript'>\n";
		$html .= "var $tree = new dhtmlXTreeObject('$tree','100%','100%',0);\n";
		$html .= "$tree.parentObject.style.overflow='auto';\n";	// dhtmlXTree constructor has hidden hardcoded
		if (translation::charset() == 'utf-8') $html .= "if ($tree.setEscapingMode) $tree.setEscapingMode('utf8');\n";
		$html .= "$tree.setImagePath('$folderImageDir/dhtmlxtree/');\n";

		if($_onCheckHandler)
		{
			$html .= "$tree.enableCheckBoxes(1);\n";
			$html .= "$tree.setOnCheckHandler('$_onCheckHandler');\n";
		}

		if ($dragndrop) $html .= "$tree.enableDragAndDrop(true);\n";

		if ($autoLoading)
		{
			$autoLoading = is_array($autoLoading) ?
				egw::link('/index.php',$autoLoading) : egw::link($autoLoading);
			$html .= "$tree.setXMLAutoLoading('$autoLoading');\n";
			if ($dataMode != 'XML') $html .= "$tree.setDataMode('$dataMode');\n";

			// if no folders given, use xml url to load root, incl. setting of selected folder
			if (!$_folders)
			{
				if ($_selected) $autoLoading .= '&selected='.urlencode($_selected);
				unset($_selected);
				$html .= "$tree.loadXML('$autoLoading');\n";
				return $html."</script>\n";
			}
		}

		$top = 0;
		if ($_topFolder)
		{
			$top = '--topfolder--';
			$topImage = '';
			if (is_array($_topFolder))
			{
				$label = $_topFolder['label'];
				if (isset($_topFolder['image']))
				{
					$topImage = $_topFolder['image'];
				}
			}
			else
			{
				$label = $_topFolder;
			}
			$html .= "\n$tree.insertNewItem(0,'$top','".addslashes($label)."',$_onNodeSelect,'$topImage','$topImage','$topImage','CHILD,TOP');\n";

			if (is_array($_topFolder) && isset($_topFolder['title']))
			{
				$html .= "$tree.setItemText('$top','".addslashes($label)."','".addslashes($_topFolder['title'])."');\n";
			}
		}
		if (is_string($_folders))
		{
			switch($dataMode)
			{
				case 'JSON':
					$html .= "$tree.loadJSONObject($_folders);\n"; break;
				case 'XML':
					$html .= "$tree.loadXMLString('$_folders');\n"; break;
			}
		}
		else
		{
			// evtl. remove leading delimiter
			if ($_selected[0] == $delimiter) $_selected = substr($_selected,1);

			$n = 0;
			foreach($_folders as $path => $data)
			{
				if (!is_array($data))
				{
					$data = array('label' => $data);
				}
				$image1 = $image2 = $image3 = '0';

				// if _leafImage given, set it only for leaves, not for folders containing children
				if ($_leafImage)
				{
					$image1 = $image2 = $image3 = "'".$_leafImage."'";
					if ($next_item = array_slice($_folders, $n+1, 1, true))
					{
						list($next_path) = each($next_item);
						if (substr($next_path,0,strlen($path)+1) == $path.'/')
						{
							$image1 = $image2 = $image3 = '0';
						}
					}
				}
				if (isset($data['image']))
				{
					$image1 = $image2 = $image3 = "'".$data['image']."'";
				}
				// evtl. remove leading delimiter
				if ($path[0] == $delimiter) $path = substr($path,1);
				$folderParts = explode($delimiter,$path);

				//get rightmost folderpart
				$label = array_pop($folderParts);
				if (isset($data['label'])) $label = $data['label'];

				// the rest of the array is the name of the parent
				$parentName = implode((array)$folderParts,$delimiter);
				if(empty($parentName)) $parentName = $top;

				$entryOptions = !isset($data['child']) || $data['child'] ? 'CHILD' : '';
				if ($_onCheckHandler && $_selected)	// check selected items on multi selection
				{
					if (!is_array($_selected)) $_selected = explode(',',$_selected);
					if (array_search("$path",$_selected)!==false) $entryOptions .= ',CHECKED';
					//echo "<p>path=$path, _selected=".print_r($_selected,true).": $entryOptions</p>\n";
				}
				// highlight current item
				elseif ((string)$_selected === (string)$path)
				{
					$entryOptions .= ',SELECT';
				}
				$html .= "$tree.insertNewItem('".addslashes($parentName)."','".addslashes($path)."','".addslashes($label).
					"',$_onNodeSelect,$image1,$image2,$image3,'$entryOptions');\n";
				if (isset($data['title']))
				{
					$html .= "$tree.setItemText('".addslashes($path)."','".addslashes($label)."','".addslashes($data['title'])."');\n";
				}
				++$n;
			}
		}
		$html .= "$tree.closeAllItems(0);\n";
		if ($_selected)
		{
			foreach(is_array($_selected)?$_selected:array($_selected) as $path)
			{
				$html .= "$tree.openItem('".addslashes($path)."');\n";
			}
		}
		else
		{
				$html .= "$tree.openItem('$top');\n";
		}
		$html .= "</script>\n";

		return $html;
	}

	/**
	 * Runs HTMLPurifier over supplied html to remove malicious code
	 *
	 * @param string $html
	 * @param HTMLPurifier_Config $config=null
	 */
	static function purify($html,$config=null,$spec=array(),$_force=false)
	{
		$defaultConfig = array('valid_xhtml'=>1,'safe'=>1);

		if (empty($html)) return $html;	// no need to process further

		// User preferences
		$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
		$font_size = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'];

		// Check for "blank" = just user preference span - for some reason we can't match on the entity, so approximate
		$regex = '#^<span style="font-family:'.$font.';font-size:'.$font_size.';">.?</span>$#us';
		if(preg_match($regex,$html))
		{
			return '';
		}
		$htmLawed = new egw_htmLawed();
		if (is_array($config) && $_force===false) $config = array_merge($defaultConfig, $config);
		if (empty($config)) $config = $defaultConfig;
		//error_log(__METHOD__.__LINE__.array2string($config));
		return $htmLawed->egw_htmLawed($html,$config,$spec);
	}

	/**
	 * Output content headers for file downloads
	 *
	 * @author Miles Lott originally in browser class
	 * @param string $fn filename
	 * @param string $mime='' mimetype or '' (default) to detect it from filename, using mime_magic::filename2mime()
	 * @param int $length=0 content length, default 0 = skip that header
	 * @param boolean $nocache=true send headers to disallow browser/proxies to cache the download
	 */
	public static function content_header($fn,$mime='',$length=0,$nocache=True)
	{
		// if no mime-type is given or it's the default binary-type, guess it from the extension
		if(empty($mime) || $mime == 'application/octet-stream')
		{
			$mime = mime_magic::filename2mime($fn);
		}
		if($fn)
		{
			// limit IE hack (no attachment in Content-disposition header) to IE < 9
			if(self::$user_agent == 'msie' && self::$ua_version < 9)
			{
				$attachment = '';
			}
			else
			{
				$attachment = ' attachment;';
			}

			// Show this for all
			header('Content-disposition:'.$attachment.' filename="'.$fn.'"');
			header('Content-type: '.$mime);

			if($length)
			{
				header('Content-length: '.$length);
			}

			if($nocache)
			{
				header('Pragma: no-cache');
				header('Pragma: public');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			}
		}
	}

	/**
	 * split html by PRE tag, return array with all content pre-sections isolated in array elements
	 * @author Leithoff, Klaus
	 * @param string html
	 * @return mixed array of parts or unaffected html
	 */
	static function splithtmlByPRE($html)
	{
		if (($pos = stripos($html,'<pre>')) === false)
		{
			return $html;
		}
		$html2ret[] = substr($html,0,$pos);
		while ($pos!==false)
		{
			$endofpre = stripos($html,'</pre>',$pos);
			$length = $endofpre-$pos+6;
			$html2ret[] = substr($html,$pos,$length);
			$pos =  stripos($html,'<pre>', $endofpre+6);
			$html2ret[] = ($pos ? substr($html,$endofpre+6,$pos-($endofpre+6)): substr($html,$endofpre+6));
			//$pos=false;
		}
		return $html2ret;
	}
}
html::_init_static();
