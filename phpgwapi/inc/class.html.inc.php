<?php
/**
 * EGroupware API: generates html with methods representing html-tags or higher widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright 2001-2016 by RalfBecker@outdoor-training.de
 * @package api
 * @subpackage html
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Generates html with methods representing html-tags or higher widgets
 *
 * @deprecated use methods of Api\Html, Api\Api\Header\Content or Api\Api\Header\UserAgent classes
 */
class html extends Api\Html
{
	/**
	 * @deprecated use Api\Api\Header\UserAgent::type()
	 */
	static $user_agent;
	/**
	 * @deprecated use Api\Api\Header\UserAgent::mobile()
	 */
	static $ua_mobile;
	/**
	 * @deprecated use Api\Api\Header\UserAgent::version()
	 */
	static $ua_version;
	/**
	 * @deprecated seriously Netscape4 ;-)
	 */
	static $netscape4;
	/**
	 * @deprecated seriously Netscape4 ;-)
	 */
	static private $prefered_img_title = 'title';
	/**
	 * @deprecated use Api\Translation::charset()
	 */
	static $charset;

	/**
	 * Output content headers for user-content, mitigating risk of javascript or html
	 *
	 * Mitigate risk of serving javascript or css from our domain,
	 * which will get around same origin policy and CSP!
	 *
	 * Mitigate risk of html downloads by using CSP or force download for IE
	 *
	 * @param resource|string &$content content might be changed by this call
	 * @param string $path filename or path for content-disposition header
	 * @param string &$mime ='' mimetype or '' (default) to detect it from filename, using mime_magic::filename2mime()
	 *	on return used, maybe changed, mime-type
	 * @param int $length =0 content length, default 0 = skip that header
	 *  on return changed size
	 * @param boolean $nocache =true send headers to disallow browser/proxies to cache the download
	 * @param boolean $force_download =true send content-disposition attachment header
	 * @param boolean $no_content_type =false do not send actual content-type and content-length header, just content-disposition
	 * @deprecated use Api\Api\Header\Content::safe()
	 */
	public static function safe_content_header(&$content, $path, &$mime='', &$length=0, $nocache=true, $force_download=true, $no_content_type=false)
	{
		Api\Api\Header\Content::safe($content, $path, $mime, $length, $nocache, $force_download, $no_content_type);
	}

	/**
	 * Output content-type headers for file downloads
	 *
	 * This function should only be used for non-user supplied content!
	 * For uploaded files, mail attachmentes, etc, you have to use safe_content_header!
	 *
	 * @author Miles Lott originally in browser class
	 * @param string $fn filename
	 * @param string $mime ='' mimetype or '' (default) to detect it from filename, using mime_magic::filename2mime()
	 * @param int $length =0 content length, default 0 = skip that header
	 * @param boolean $nocache =true send headers to disallow browser/proxies to cache the download
	 * @param boolean $forceDownload =true send headers to handle as attachment/download
	 * @deprecated use Api\Api\Header\Content::type()
	 */
	public static function content_header($fn,$mime='',$length=0,$nocache=True,$forceDownload=true)
	{
		Api\Api\Header\Content::type($fn, $mime, $length, $nocache, $forceDownload);
	}

	/**
	 * Output content-disposition header for file downloads
	 *
	 * @param string $fn filename
	 * @param boolean $forceDownload =true send headers to handle as attachment/download
	 * @deprecated use Api\Api\Header\Content::disposition()
	 */
	public static function content_disposition_header($fn,$forceDownload=true)
	{
		Api\Api\Header\Content::disposition($fn, $forceDownload);
	}

	/**
	 * Created an input-field with an attached color-picker
	 *
	 * @param string $name the name of the input-field
	 * @param string $value ='' the actual value for the input-field, default ''
	 * @param string $title ='' tooltip/title for the picker-activation-icon
	 * @param string $options ='' options for input
	 * @deprecated use html5 input type="color"
	 * @return string the html
	 */
	static function inputColor($name,$value='',$title='',$options='')
	{
		return self::input($name, $value, 'color', $options.' size="7" maxsize="7"').
			($title ? ' title="'.self::htmlspecialchars($title).'"' : '');
	}

	/**
	 * representates a b tab (bold)
	 *
	 * @param string $content of the link, if '' only the opening tag gets returned
	 * @deprecated use css
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
	 * @deprecated use css
	 * @return string the html
	 */
	static function italic($content)
	{
		return '<i>'.$content.'</i>';
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
	* @deprecated use something else ;-)
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
	 * initialise our static vars
	 */
	static function _init_static()
	{
		self::$user_agent = Api\Header\UserAgent::type();
		self::$ua_version = Api\Header\UserAgent::version();
		self::$ua_mobile = Api\Header\UserAgent::mobile();
		self::$netscape4 = self::$user_agent == 'mozilla' && self::$ua_version < 5;
		self::$prefered_img_title = self::$netscape4 ? 'alt' : 'title';
		//error_log("HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]', UserAgent: '".self::$user_agent."', Version: '".self::$ua_version."', isMobile=".array2string(self::$ua_mobile).", img_title: '".self::$prefered_img_title."'");

		self::$charset = Api\Translation::charset();
	}
}
html::_init_static();
