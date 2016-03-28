<?php
/**
 * EGroupware API - Translations
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * Copyright (C) 2000, 2001 Joseph Engo
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

use EGroupware\Api;

/**
 * EGroupware API - Translations
 *
 * @deprecated use Api\Translation for non-mail specific methods or Api\Mail\Html for others
 */
class translation extends Api\Translation
{
	/**
	 * Return the decoded string meeting some additional requirements for mailheaders
	 *
	 * @param string $_string -> part of an mailheader
	 * @param string $displayCharset the charset parameter specifies the character set to represent the result by (if iconv_mime_decode is to be used)
	 * @return string
	 * @deprecated use Api\Mail\Html::decodeMailHeader
	 */
	static function decodeMailHeader($_string, $displayCharset='utf-8')
	{
		return Api\Mail\Html::decodeMailHeader($_string, $displayCharset);
	}

	/**
	 * replace emailaddresses enclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
	 *    as well as those emailadresses in links, and within broken links
	 * @param string the text to process
	 * @return 1
	 * @deprecated use Api\Mail\Html::replaceEmailAdresses
	 */
	static function replaceEmailAdresses(&$text)
	{
		return Api\Mail\Html::replaceEmailAdresses($text);
	}

	/**
	 * strip tags out of the message completely with their content
	 * @param string $_body is the text to be processed
	 * @param string $tag is the tagname which is to be removed. Note, that only the name of the tag is to be passed to the function
	 *				without the enclosing brackets
	 * @param string $endtag can be different from tag  but should be used only, if begin and endtag are known to be different e.g.: <!-- -->
	 * @param bool $addbracesforendtag if endtag is given, you may decide if the </ and > braces are to be added,
	 *				or if you want the string to be matched as is
	 * @deprecated use Api\Mail\Html::replaceTagsCompletley
	 */
	static function replaceTagsCompletley(&$_body,$tag,$endtag='',$addbracesforendtag=true)
	{
		Api\Mail\Html::replaceTagsCompletley($_body, $tag, $endtag, $addbracesforendtag);
	}

	static function transform_mailto2text($matches)
	{
		return Api\Mail\Html::transform_mailto2text($matches);
	}

	static function transform_url2text($matches)
	{
		return Api\Mail\Html::transform_url2text($matches);
	}

	/**
	 * convertHTMLToText
	 * @param string $_html : Text to be stripped down
	 * @param string $displayCharset : charset to use; should be a valid charset
	 * @param bool $stripcrl :  flag to indicate for the removal of all crlf \r\n
	 * @param bool $stripalltags : flag to indicate wether or not to strip $_html from all remaining tags
	 * @return text $_html : the modified text.
	 * @deprecated use Api\Mail\Html::convertHTMLToText
	 */
	static function convertHTMLToText($_html,$displayCharset=false,$stripcrl=false,$stripalltags=true)
	{
		return Api\Mail\Html::convertHTMLToText($_html, $displayCharset, $stripcrl, $stripalltags);
	}

	/**
	 * split html by PRE tag, return array with all content pre-sections isolated in array elements
	 * @author Leithoff, Klaus
	 * @param string html
	 * @return mixed array of parts or unaffected html
	 * @deprecated use Api\Mail\Html::splithtmlByPRE
	 */
	static function splithtmlByPRE($html)
	{
		return Api\Mail\Html::splithtmlByPRE($html);
	}
}
