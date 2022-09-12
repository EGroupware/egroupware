<?php
/**
 * eGroupWare API: egw class to include (and configure (basic)) htmLawed by Santosh Patnaik
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage html
 * @author Klaus Leithoff <kl-AT-stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Html;

use EGroupware\Api;

require_once(__DIR__.'/htmLawed/htmLawed.php');

/**
 * This class does NOT use anything EGroupware specific, it just calls htmLawed and supports autoloading
 * while matching egw namespace requirements. It also provides (as a non class function ) a hook_tag function
 * to do further tag / attribute validation
 */
class HtmLawed
{
	/**
	 * config options see constructor
	 *
	 * @var string|array
	 */
	var $Configuration;

	/**
	 * The $spec argument can be used to disallow an otherwise legal attribute for an element,
	 * or to restrict the attribute's values. This can also be helpful as a security measure
	 * (e.g., in certain versions of browsers, certain values can cause buffer overflows and
	 * denial of service attacks), or in enforcing admin policy compliance. $spec is specified
	 * as a string of text containing one or more rules, with multiple rules separated from each
	 * other by a semi-colon (;)
	 *
	 * @var string|array
	 */
	var $Spec;

	/**
	 * Constructor
	 */
	function __construct()
	{
		// may hold some Standard configuration
		/*
		$cfg = array(
			'abs_url'=>array('3', '0', 'absolute/relative URL conversion', '-1'),
			'and_mark'=>array('2', '0', 'mark original <em>&amp;</em> chars', '0', 'd'=>1), // 'd' to disable
			'anti_link_spam'=>array('1', '0', 'modify <em>href</em> values as an anti-link spam measure', '0', array(array('30', '1', '', 'regex for extra <em>rel</em>'), array('30', '2', '', 'regex for no <em>href</em>'))),
			'anti_mail_spam'=>array('1', '0', 'replace <em>@</em> in <em>mailto:</em> URLs', '0', '8', 'NO@SPAM', 'replacement'),
			'balance'=>array('2', '1', 'fix nestings and balance tags', '0'),
			'base_url'=>array('', '', 'base URL', '25'),
			'cdata'=>array('4', 'nil', 'allow <em>CDATA</em> sections', 'nil'),
			'clean_ms_char'=>array('3', '0', 'replace bad characters introduced by Microsoft apps. like <em>Word</em>', '0'),
			'comment'=>array('4', 'nil', 'allow HTML comments', 'nil'),
			'css_expression'=>array('2', 'nil', 'allow dynamic expressions in CSS style properties', 'nil'),
			'deny_attribute'=>array('1', '0', 'denied attributes', '0', '50', '', 'these'),
			'direct_list_nest'=>array('2', 'nil', 'allow direct nesting of a list within another without requiring it to be a list item', 'nil'),
			'elements'=>array('', '', 'allowed elements', '50'),
			'hexdec_entity'=>array('3', '1', 'convert hexadecimal numeric entities to decimal ones, or vice versa', '0'),
			'hook'=>array('', '', 'name of hook function', '25'),
			'hook_tag'=>array('', '', 'name of custom function to further check attribute values', '25'),
			'keep_bad'=>array('7', '6', 'keep, or remove <em>bad</em> tag content', '0'),
			'lc_std_val'=>array('2', '1', 'lower-case std. attribute values like <em>radio</em>', '0'),
			'make_tag_strict'=>array('3', 'nil', 'transform deprecated elements', 'nil'), 3 is a new own config value, to indicate that transformation is to be performed, but don't transform font as size transformation of numeric sizes to keywords alters the intended result too much
			'named_entity'=>array('2', '1', 'allow named entities, or convert numeric ones', '0'),
			'no_deprecated_attr'=>array('3', '1', 'allow deprecated attributes, or transform them', '0'),
			'parent'=>array('', 'div', 'name of parent element', '25'),
			'safe'=>array('2', '0', 'for most <em>safe</em> HTML', '0'),
			'schemes'=>array('', 'href: aim, feed, file, ftp, gopher, http, https, irc, mailto, news, nntp, sftp, ssh, telnet; *:file, http, https', 'allowed URL protocols', '50'),
			'show_setting'=>array('', 'htmLawed_setting', 'variable name to record <em>finalized</em> htmLawed settings', '25', 'd'=>1),
			'style_pass'=>array('2', 'nil', 'do not look at <em>style</em> attribute values', 'nil'),
			'tidy'=>array('3', '0', 'beautify/compact', '-1', '8', '1t1', 'format'),
			'unique_ids'=>array('2', '1', 'unique <em>id</em> values', '0', '8', 'my_', 'prefix'),
			'valid_xhtml'=>array('2', 'nil', 'auto-set various parameters for most valid XHTML', 'nil'),
			'xml:lang'=>array('3', 'nil', 'auto-add <em>xml:lang</em> attribute', '0'),
			'allow_for_inline' => array('table'),//block elements allowed for nesting when only inline is allowed; Example span does not allow block elements as table; table is the only element tested so far
		);
		*/

		$this->Configuration = array('comment'=>1, //remove comments
			'make_tag_strict'=>3,//3 is a new own config value, to indicate that transformation is to be performed, but don't transform font, as size transformation of numeric sizes to keywords alters the intended result too much
			'balance'=>0,//turn off tag-balancing (config['balance']=>0). That will not introduce any security risk; only standards-compliant tag nesting check/filtering will be turned off (basic tag-balance will remain; i.e., there won't be any unclosed tag, etc., after filtering)
			// tidy eats away even some wanted whitespace, so we switch it off;
			// we used it for its compacting and beautifying capabilities, which resulted in better html for further processing
			'tidy'=>0,
			'elements' => "* -script -meta -object",
			'deny_attribute' => 'on*',
			'schemes'=>'href: file, ftp, http, https, mailto, tel, phone; src: cid, data, file, ftp, http, https; *:file, http, https',
			'hook_tag' =>"hl_my_tag_transform",
		);
		$this->Spec = 'img=alt(noneof="image"/default="")';
	}

	/**
	 * Run htmLawed
	 *
	 * @param string $html2check =text input Text to check
	 * @param string|array $Config = text or array
	 * @param string|array $Spec =text or array; The '$spec' argument can be used to disallow an otherwise legal attribute for an element
	 * @return string cleaned/fixed html
	 */
	function run($html2check, $Config=null, $Spec=array())
	{
		//error_log(__METHOD__.__LINE__.' Input:'.$html2check);
		if (is_array($Config) && is_array($this->Configuration)) $Config = array_merge($this->Configuration, $Config);
		if (empty($Config)) $Config = $this->Configuration;
		if (empty($Spec)) $Spec = $this->Spec;
		// If we are processing mails, we take out stuff in <style> stuff </style> tags and
		// put it back in after purifying; styles are processed for known security risks
		// in self::getStyles
		// we allow filtered style sections now throughout egroupware
		/*if ($Config['hook_tag'] =="hl_email_tag_transform")*/ $styles = self::getStyles($html2check);
		//error_log(__METHOD__.__LINE__.array2string($styles));
		//error_log(__METHOD__.__LINE__.' Config:'.array2string($Config));

		// mind our namespace when defining a function as hook. we handle our own defined hooks here.
		if ($Config['hook_tag']=="hl_my_tag_transform" || $Config['hook_tag']=="hl_email_tag_transform")
		{
			$Config['hook_tag']=__NAMESPACE__.'\\'.$Config['hook_tag'];
		}
		return ($styles?$styles:'').htmLawed($html2check, $Config, $Spec);
	}

	/**
	 * get all style tag definitions, <style> stuff </style> of the html passed in
	 * and remove it from input
	 * @author Leithoff, Klaus
	 * @param string html
	 * @return string the style css
	 */
	static function getStyles(&$html)
	{
		$ct=0;
		$newStyle = null;
		if (stripos($html,'<style')!==false)  $ct = preg_match_all('#<style(?:\s.*)?>(.+)</style>#isU', $html, $newStyle);
		if ($ct>0)
		{
			//error_log(__METHOD__.__LINE__.array2string($newStyle[0]));
			$style2buffer = implode('',$newStyle[0]);
			// only replace what we have found, we use it here, as we use the same routine in Api\Mail\Html::replaceTagsCompletley
			// no need to do the extra routine
			$html = str_ireplace($newStyle[0],'',$html);
		}
		if (!empty($style2buffer))
		{
			//error_log(__METHOD__.__LINE__.array2string($style2buffer));
			$test = json_encode($style2buffer);
			//error_log(__METHOD__.__LINE__.'#'.$test.'# ->'.strlen($style2buffer).' Error:'.json_last_error());
			//if (json_last_error() != JSON_ERROR_NONE && strlen($style2buffer)>0)
			if ($test=="null" && strlen($style2buffer)>0)
			{
				// this should not be needed, unless something fails with charset detection/ wrong charset passed
				error_log(__METHOD__.__LINE__.' Found Invalid sequence for utf-8 in CSS:'.$style2buffer.' Carset Detected:'.Api\Translation::detect_encoding($style2buffer));
				$style2buffer = utf8_encode($style2buffer);
			}
		}
		$style = $style2buffer ?? '';
		// clean out comments and stuff
		$search = array(
			'@url\(https?:\/\/[^\)].*?\)@si',  // url calls e.g. in style definitions
//			'@<!--[\s\S]*?[ \t\n\r]*-->@',   // Strip multi-line comments including CDATA
//			'@<!--[\s\S]*?[ \t\n\r]*--@',    // Strip broken multi-line comments including CDATA
		);
		$style = preg_replace($search,"",$style);

		// CSS Security
		// http://code.google.com/p/browsersec/wiki/Part1#Cascading_stylesheets
		$css = preg_replace('/(javascript|expession|-moz-binding)/i','',$style);
		if (stripos($css,'script')!==false) Api\Mail\Html::replaceTagsCompletley($css,'script'); // Strip out script that may be included
		// we need this, as styledefinitions are enclosed with curly brackets; and template stuff tries to replace everything between curly brackets that is having no horizontal whitespace
		// as the comments as <!-- styledefinition --> in stylesheet are outdated, and ck-editor does not understand it, we remove it
		$css_no_comment = str_replace(array(':','<!--','-->'),array(': ','',''),$css);
		//error_log(__METHOD__.__LINE__.$css);
		// we already removed what we have found, above, as we used pretty much the same routine as in Api\Mail\Html::replaceTagsCompletley
		// no need to do the extra routine
		// TODO: we may have to strip urls and maybe comments and ifs
		//if (stripos($html,'style')!==false) Api\Mail\Html::replaceTagsCompletley($html,'style'); // clean out empty or pagewide style definitions / left over tags
		return $css_no_comment;
	}

	/**
	 * Runs HTMLPurifier over supplied html to remove malicious code
	 *
	 * @param string $html
	 * @param array|string $config =null - config to influence the behavior of current purifying engine
	 * @param array|string $spec =null - spec to influence the behavior of current purifying engine
	 *		The $spec argument can be used to disallow an otherwise legal attribute for an element,
	 *		or to restrict the attribute's values
	 * @param boolean $_force =null - force the config passed to be used without merging to the default
	 */
	static function purify($html,$config=null,$spec=array(),$_force=false)
	{
		$defaultConfig = array('valid_xhtml'=>1,'safe'=>1);

		if (empty($html)) return $html;	// no need to process further
		if (!empty($config) && is_string($config))
		{
			//error_log(__METHOD__.__LINE__.$config);
			$config = json_decode($config,true);
			if (is_null($config)) error_log(__METHOD__.__LINE__." decoding of config failed; standard will be applied");
		}

		// User preferences
		$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
		$font_size = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'];

		// Check for "blank" = just user preference span - for some reason we can't match on the entity, so approximate
		$regex = '#^<span style="[^"]*font-family:'.$font.'; font-size:'.$font_size.'pt;[^"]*">.?</span>$#us';
		if(preg_match($regex,$html))
		{
			return '';
		}
		$htmLawed = new HtmLawed();
		if (is_array($config) && $_force===false) $config = array_merge($defaultConfig, $config);
		if (empty($config)) $config = $defaultConfig;
		//error_log(__METHOD__.__LINE__.array2string($config));
		return $htmLawed->run($html,$config,$spec);
	}
}

/**
 * hl_my_tag_transform
 *
 * function to provide individual checks for element attribute pairs
 * implemented so far:	img checking for alt attribute == image; set this to empty
 * 						a checking for title, replacing @
 * 						blockquote checking for cite, replacing @
 */
function hl_my_tag_transform($element, $attribute_array=0)
{
	// If second argument is not received, it means a closing tag is being handled
	if(is_numeric($attribute_array)){
		return "</$element>";
	}

	//if ($element=='img') error_log(__METHOD__.__LINE__." ".$element.'->'.array2string($attribute_array));
	if ($element=='td' && isset($attribute_array['background']))
	{
		if (is_object($GLOBALS['egw']) && stripos($attribute_array['background'],$GLOBALS['egw']->link('/index.php'))!==false)
		{
			//error_log(__METHOD__.__LINE__.array2string($attribute_array));
			//$attribute_array['background'] = 'url('.$attribute_array['background'].');';
		}
		else
		{
			// $attribute_array['background']='denied:'.$attribute_array['background'];
			unset($attribute_array['background']);// only internal background images are allowed
		}
	}
	// Elements other than 'img' or 'img' without a 'img' attribute are returned unchanged
	if($element == 'img')
	{
		// Re-build 'alt'
		if (isset($attribute_array['alt'])) $attribute_array['alt'] = ($attribute_array['alt']=='image'?'':$attribute_array['alt']);
		if (isset($attribute_array['alt'])&&strpos($attribute_array['alt'],'@')!==false) $attribute_array['alt']=str_replace('@','(at)',$attribute_array['alt']);
	}
	if (isset($attribute_array['title']))
	{
		if (strpos($attribute_array['title'],'@')!==false) $attribute_array['title']=str_replace('@','(at)',$attribute_array['title']);
	}
	if ($element == 'blockquote')
	{
		if (isset($attribute_array['cite']))
		{
			if (strpos($attribute_array['cite'],'@')!==false) $attribute_array['cite']=str_replace('@','(at)',$attribute_array['cite']);
		}
	}
	/*
	// Elements other than 'span' or 'span' without a 'style' attribute are returned unchanged
	if($element == 'span' && isset($attribute_array['style']))
	{
		// Identify CSS properties and values
		$css = explode(';', $attribute_array['style']);
		$style = array();
		foreach($css as $v){
			if(($p = strpos($v, ':')) > 1 && $p < strlen($v)){
				$css_property_name = trim(substr($v, 0, $p));
				$css_property_value = trim(substr($v, $p+1));
				$style[] = "$css_property_name: $css_property_value";
			}
		}

		// Alter the CSS property as required

		// Black Arial must be at a font-size of 24
		if(isset($style['font-family']) && $style['font-family'] == 'Arial' && isset($style['color']) && $style['color'] == '#000000'){
			$style['font-size'] == '24';
		}

		// And so on for other criteria
		// ...

		// Re-build 'style'
		$attribute_array['style'] = implode('; ', $style);
	}
	*/
	if (isset($attribute_array['style']) && stripos($attribute_array['style'],'script')!==false) $attribute_array['style'] = str_ireplace('script','',$attribute_array['style']);
	if($element == 'a')
	{
		//error_log(__METHOD__.__LINE__.array2string($attribute_array));
		// rebuild Anchors, if processed by hl_email_tag_transform
		if (strpos($attribute_array['href'],"denied:javascript:GoToAnchor('")===0)
		{
			$attribute_array['href']=str_ireplace("');",'',str_ireplace("denied:javascript:GoToAnchor('","#",$attribute_array['href']));
		}
		if (strpos($attribute_array['href'],"javascript:GoToAnchor('")===0)
		{
			$attribute_array['href']=str_ireplace("');",'',str_ireplace("javascript:GoToAnchor('","#",$attribute_array['href']));
		}
		if (strpos($attribute_array['href'],'denied:javascript')===0) $attribute_array['href']='';
	}

	// Build the attributes string
	$attributes = '';
	foreach($attribute_array as $k=>$v){
		$attributes .= " {$k}=\"{$v}\"";
	}

	// Return the opening tag with attributes
	static $empty_elements = array('area'=>1, 'br'=>1, 'col'=>1, 'embed'=>1, 'hr'=>1, 'img'=>1, 'input'=>1, 'isindex'=>1, 'param'=>1);
	return "<{$element}{$attributes}". (isset($empty_elements[$element]) ? ' /' : ''). '>';
}

/**
 * hl_email_tag_transform
 *
 * function to provide individual checks for element attribute pairs
 * implemented so far:	img -checking for alt attribute == image; set this to empty
 *							-control for/on external Images and src-length
 * 						a -checking for title and href, replacing @ accordingly
 *						  -navigate to local anchors without reloading the page
 * 						blockquote -checking for cite, replacing @
 * 						throwing away excess div elements, that carry no style or class or id info
 */
function hl_email_tag_transform($element, $attribute_array=0)
{
	//error_log(__METHOD__.__LINE__.$element.'=>'.array2string($attribute_array));
	static $lastelement = null;
	static $throwawaycounter = null;
	if (is_null($lastelement)) $lastelement='';
	if (is_null($throwawaycounter)) $throwawaycounter = 0;
	//if ($throwawaycounter>1) error_log(__METHOD__.__LINE__.' '.$throwawaycounter.$element.array2string($attribute_array));
	if ($element=='div' && $element==$lastelement && ($attribute_array==0 || empty($attribute_array)))
	{
		if (is_array($attribute_array)) $throwawaycounter++;
		if ($attribute_array==0 && $throwawaycounter>0) $throwawaycounter--;
		if ($throwawaycounter>1) return '';
	}
	if ($lastelement=='div' && $element!=$lastelement && is_array($attribute_array)) $throwawaycounter = 0;
	if (is_array($attribute_array) && !empty($attribute_array) && $element=='div')
	{
		$lastelement = 'div_with_attr';
	}
	else
	{
		if (is_array($attribute_array)) $lastelement = $element;
	}
	// If second argument is not received, it means a closing tag is being handled
	if(is_numeric($attribute_array)){
		if($element==$lastelement) $lastelement='';
		return "</$element>";
	}

	//if ($element=='a') error_log(__METHOD__.__LINE__." ".$element.'->'.array2string($attribute_array));
	if ($element=='td' && isset($attribute_array['background']))
	{
		if (stripos($attribute_array['background'],'cid:')!==false)
		{
			//error_log(__METHOD__.__LINE__.array2string($attribute_array));
			//$attribute_array['background'] = 'url('.$attribute_array['background'].');';
		}
		else
		{
			// $attribute_array['background']='denied:'.$attribute_array['background'];
			unset($attribute_array['background']);// only cid style background images are allowed
		}
	}
	// Elements other than 'img' or 'img' without a 'img' attribute are returned unchanged
	if($element == 'img')
	{
		// Re-build 'alt'
		if (isset($attribute_array['alt'])) $attribute_array['alt'] = ($attribute_array['alt']=='image'?'':$attribute_array['alt']);
		if (isset($attribute_array['alt'])&&strpos($attribute_array['alt'],'@')!==false) $attribute_array['alt']=str_replace('@','(at)',$attribute_array['alt']);
		// $GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalIMGs'] ? '' : 'match' => '/^cid:.*/'),
		if (isset($attribute_array['src']))
		{
			if (!(strlen($attribute_array['src'])>4 && strlen($attribute_array['src'])<800))
			{
					$attribute_array['alt']= $attribute_array['alt'].' [blocked (reason: url length):'.$attribute_array['src'].']';
					if (!isset($attribute_array['title'])) $attribute_array['title']=$attribute_array['alt'];
					$attribute_array['src']=Api\Image::find('api','error');
			}
			if (!preg_match('/^cid:.*/',$attribute_array['src']))
			{
				$url = explode('/', preg_replace('/^(http|https):\/\//','',$attribute_array['src']));
				$domains = is_array($GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalDomains']) ?
						$GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalDomains'] :
						array();
				if ($GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalIMGs'] != 1
						&& !in_array($url[0], $domains) || substr($attribute_array['src'],0, 5) == 'http:')
				{
					//the own webserver url is not external, so it should be allowed
					if (empty($GLOBALS['egw_info']['server']['webserver_url'])||!preg_match("$^".$GLOBALS['egw_info']['server']['webserver_url'].".*$",$attribute_array['src']))
					{
						$attribute_array['alt']= $attribute_array['alt'].' [blocked external image:'.$attribute_array['src'].']';
						if (!isset($attribute_array['title'])) $attribute_array['title']=$attribute_array['alt'];
						$attribute_array['src']=Api\Image::find('mail','no-image-shown');
						$attribute_array['border'] = 1;
						if ($attribute_array['style'])
						{
							if (stripos($attribute_array['style'],'border')!==false) $attribute_array['style'] = preg_replace('~border(:|-left:|-right:|-bottom:|-top:)+ (0px)+ (none)+;~si','',$attribute_array['style']);
						}
					}
				}
			}
		}
	}
	if (isset($attribute_array['style']) && stripos($attribute_array['style'],'script')!==false) $attribute_array['style'] = str_ireplace('script','',$attribute_array['style']);
	if (isset($attribute_array['title']))
	{
		if (strpos($attribute_array['title'],'@')!==false) $attribute_array['title']=str_replace('@','(at)',$attribute_array['title']);
	}
	if ($element == 'blockquote')
	{
		if (isset($attribute_array['cite']))
		{
			if (strpos($attribute_array['cite'],'@')!==false) $attribute_array['cite']=str_replace('@','(at)',$attribute_array['cite']);
		}
	}
	if($element == 'a')
	{
		//error_log(__METHOD__.__LINE__.array2string($attribute_array));
		if (strpos($attribute_array['href'],'denied:javascript')===0) $attribute_array['href']='';
		if (isset($attribute_array['name']) && isset($attribute_array['id'])) $attribute_array['id'] = $attribute_array['name'];
		if (strpos($attribute_array['href'],'@')!==false) $attribute_array['href'] = str_replace('@','%40',$attribute_array['href']);
		if (strpos($attribute_array['href'],'#')===0 && (isset(Api\Mail::$htmLawed_config['transform_anchor']) && Api\Mail::$htmLawed_config['transform_anchor']===true))
		{
			$attribute_array['href'] = "javascript:GoToAnchor('".trim(substr($attribute_array['href'],1))."');";
		}

	}

	// Build the attributes string
	$attributes = '';
	foreach($attribute_array as $k=>$v){
		$attributes .= " {$k}=\"{$v}\"";
	}

	// Return the opening tag with attributes
	static $empty_elements = array('area'=>1, 'br'=>1, 'col'=>1, 'embed'=>1, 'hr'=>1, 'img'=>1, 'input'=>1, 'isindex'=>1, 'param'=>1);
	return "<{$element}{$attributes}". (isset($empty_elements[$element]) ? ' /' : ''). '>';
}