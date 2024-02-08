<?php
/**
 * EGroupware API - Html mail handling
 *
 * @link http://www.egroupware.org
 * @author Klaus Leithoff <kl@stylite.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

/**
 * Html mail handling
 *
 * @todo clean up IDE warnings, mostly multiple assigments
 */
class Html
{
	/**
	 * Return the decoded string meeting some additional requirements for mailheaders
	 *
	 * @param string $_string -> part of an mailheader
	 * @param string $displayCharset the charset parameter specifies the character set to represent the result by (if iconv_mime_decode is to be used)
	 * @param integer $reclevel param to control recursive calls (to prevent endless recursion)
	 * @return string
	 */
	static function decodeMailHeader($_string, $displayCharset='utf-8', $reclevel=0)
	{
		$maxreclevel=25;
		if ($reclevel > $maxreclevel) {
			error_log( __METHOD__.__LINE__." Recursion Level Exeeded ($reclevel) while decoding $_string ");
			return $_string;
		}
		$reclevel++;

		//error_log(__FILE__.','.__METHOD__.':'."called with $_string and CHARSET $displayCharset");
		if(function_exists('imap_mime_header_decode'))
		{
			// some characterreplacements, as they fail to translate
			$sar = array(
				'@(\x84|\x93|\x94)@',
				'@(\x96|\x97|\x1a)@',
				'@(\x91|\x92)@',
				'@(\x85)@',
				'@(\x86)@',
			);
			$rar = array(
				'"',
				'-',
				'\'',
				'...',
				'+',
			);

			$elements = imap_mime_header_decode(preg_replace('/\?=\s+=\?/', '?= =?', $_string));

			$convertAtEnd = false;
			$string = '';
			foreach((array)$elements as $element)
			{
				if ($element->charset == 'default') $element->charset = Api\Translation::detect_encoding($element->text);
				if ($element->charset != 'x-unknown')
				{
					if( strtoupper($element->charset) != 'UTF-8') $element->text = preg_replace($sar,$rar,$element->text);
					// check if there is a possible nested encoding; make sure that the inputstring and the decoded result are different to avoid loops
					$openTags = substr_count($element->text,'?=');
					if(preg_match('/\?=.+=\?/', $element->text) && $openTags>0 && $openTags==substr_count($element->text,'=?') && $element->text != $_string)
					{
						$element->text = self::decodeMailHeader($element->text, $element->charset, $reclevel);
						$element->charset = $displayCharset;
					}
					$string .= Api\Translation::convert($element->text,$element->charset);
				}
				else
				{
					$string .= $element->text;
					$convertAtEnd = true;
				}
			}
			if ($convertAtEnd) $string = self::decodeMailHeader($string, $displayCharset, $reclevel);
		}
		elseif(function_exists('mb_decode_mimeheader'))
		{
			$matches = null;
			if(preg_match_all('/=\?.*\?Q\?.*\?=/iU', $string=$_string, $matches))
			{
				foreach($matches[0] as $match)
				{
					$fixedMatch = str_replace('_', ' ', $match);
					$string = str_replace($match, $fixedMatch, $string);
				}
				$string = str_replace('=?ISO8859-','=?ISO-8859-',
					str_replace('=?windows-1258','=?ISO-8859-1',$string));
			}
			$string = mb_decode_mimeheader($string);
		}
		elseif(function_exists('iconv_mime_decode'))
		{
			// continue decoding also if an error occurs
			$string = @iconv_mime_decode($_string, 2, $displayCharset);
		}
		else
		{
			// no decoding function available
			$string = $_string;
		}
		// remove unprintable chars
		return preg_replace('/([\000-\012\015\016\020-\037])/', '', $string);
	}

	/**
	 * replace emailaddresses enclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
	 *    as well as those emailadresses in links, and within broken links
	 * @param string the text to process
	 * @return 1
	 */
	static function replaceEmailAdresses(&$text)
	{
		//error_log($text);
		//replace CRLF with something other to be preserved via preg_replace as CRLF seems to vanish
		$text2 = str_replace("\r\n",'<#cr-lf#>',$text);
			// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		$text3 = preg_replace("/(<|&lt;a href=\")*(mailto:([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i","$2 ", $text2);
		//$text = preg_replace_callback("/(<|&lt;a href=\")*(mailto:([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i",'self::transform_mailto2text',$text);
		//$text = preg_replace('~<a[^>]+href=\"(mailto:)+([^"]+)\"[^>]*>~si','$2 ',$text);
		$text4 = preg_replace_callback('~<a[^>]+href=\"(mailto:)+([^"]+)\"[^>]*>([ @\w\.,-.,_.,0-9.]+)<\/a>~si','self::transform_mailto2text',$text3);
		$text5 = preg_replace("/(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))( |\s)*(<\/a>)*( |\s)*(>|&gt;)*/i","$1 ", $text4);
		$text6 = preg_replace("/(<|&lt;)*(([\w\.,-.,_.,0-9.]+)@([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i","$2 ", $text5);
		$text = str_replace('<#cr-lf#>',"\r\n",$text6);
		return 1;
	}

	/**
	 * strip tags out of the message completely with their content
	 * @param string $_body is the text to be processed
	 * @param string $tag is the tagname which is to be removed. Note, that only the name of the tag is to be passed to the function
	 *				without the enclosing brackets
	 * @param string $endtag can be different from tag  but should be used only, if begin and endtag are known to be different e.g.: <!-- -->
	 * @param bool $addbracesforendtag if endtag is given, you may decide if the </ and > braces are to be added,
	 *				or if you want the string to be matched as is
	 * @return void the modified text is passed via reference
	 */
	static function replaceTagsCompletley(&$_body,$tag,$endtag='',$addbracesforendtag=true)
	{
		if ($tag) $tag = strtolower($tag);
		$singleton = false;
		if ($endtag=='/>') $singleton =true;
		if ($endtag == '' || empty($endtag) || !isset($endtag))
		{
			$endtag = $tag;
		} else {
			$endtag = strtolower($endtag);
			//error_log(__METHOD__.' Using EndTag:'.$endtag);
		}
		// strip tags out of the message completely with their content
		if ($_body) {
			if ($singleton)
			{
				//$_body = preg_replace('~<'.$tag.'[^>].*? '.$endtag.'~simU','',$_body);
				$_body = preg_replace('~<?'.$tag.'[^>].* '.$endtag.'~simU','',$_body); // we are in Ungreedy mode, so we expect * to be ungreedy without specifying ?
			}
			else
			{
				$found=null;
				if ($addbracesforendtag === true )
				{
					if (stripos($_body,'<'.$tag)!==false)  $ct = preg_match_all('#<'.$tag.'(?:\s.*)?>(.+)</'.$endtag.'>#isU', $_body, $found);
					if (isset($ct) && $ct>0)
					{
						//error_log(__METHOD__.__LINE__.array2string($found[0]));
						// only replace what we have found
						$_body = str_ireplace($found[0],'',$_body);
					}
					// remove left over tags, unfinished ones, and so on
					$_body = preg_replace('~<'.$tag.'[^>]*?>~si','',$_body);
				}
				if ($addbracesforendtag === false )
				{
					if (stripos($_body,'<'.$tag)!==false)  $ct = preg_match_all('#<'.$tag.'(?:\s.*)?>(.+)'.$endtag.'#isU', $_body, $found);
					if (isset($ct) && $ct > 0)
					{
						//error_log(__METHOD__.__LINE__.array2string($found[0]));
						// only replace what we have found
						$_body = str_ireplace($found[0],'',$_body);
					}
/*
					$_body = preg_replace('~<'.$tag.'[^>]*?>(.*?)'.$endtag.'~simU','',$_body);
*/
					// remove left over tags, unfinished ones, and so on
					$_body = preg_replace(array('~<'.$tag.'[^>]*?>~si', '~'.$endtag.'~'), '', $_body);
				}
			}
		}
	}

	static function transform_mailto2text($matches)
	{
		//error_log(__METHOD__.__LINE__.array2string($matches));
		// this is the actual url
		$matches[2] = trim(strip_tags($matches[2]));
		$matches[3] = trim(strip_tags($matches[3]));
		$matches[2] = str_replace(array('%40','%20'),array('@',' '),$matches[2]);
		$matches[3] = str_replace(array('%40','%20'),array('@',' '),$matches[3]);
		return $matches[1].$matches[2].($matches[2]==$matches[3]?' ':' -> '.$matches[3].' ');
	}

	static function transform_url2text($matches)
	{
		//error_log(__METHOD__.__LINE__.array2string($matches));
		$linkTextislink = false;
		// this is the actual url
		$matches[2] = trim(strip_tags($matches[2]));
		if ($matches[2]==$matches[1]) $linkTextislink = true;
		$matches[1] = str_replace(' ','%20',$matches[1]);
		return ($linkTextislink?' ':'[ ').$matches[1].($linkTextislink?'':' -> '.$matches[2]).($linkTextislink?' ':' ]');
	}

	/**
	 * convertHTMLToText
	 * @param string $_html : Text to be stripped down
	 * @param string $displayCharset : charset to use; should be a valid charset
	 * @param bool $stripcrl :  flag to indicate for the removal of all crlf \r\n
	 * @param bool $stripalltags : flag to indicate wether or not to strip $_html from all remaining tags
	 * @param bool $noRepEmailAddr = false, if true email addresses will be intact
	 * @return text $_html : the modified text.
	 */
	static function convertHTMLToText($_html,$displayCharset=false,$stripcrl=false,$stripalltags=true,$noRepEmailAddr = false)
	{
		// assume input isHTML, but test the input anyway, because,
		// if it is not, we may not want to strip whitespace
		$isHTML = true;
		if (strlen(strip_tags($_html)) == strlen($_html))
		{
			$isHTML = false;
			// return $_html; // maybe we should not proceed at all
		}
		if ($displayCharset === false) $displayCharset = Api\Translation::charset();
		//error_log(__METHOD__.$_html);
		#print '<hr>';
		#print "<pre>"; print htmlspecialchars($_html);
		#print "</pre>";
		#print "<hr>";
		if (stripos($_html,'style')!==false) self::replaceTagsCompletley($_html,'style'); // clean out empty or pagewide style definitions / left over tags
		if (stripos($_html,'head')!==false) self::replaceTagsCompletley($_html,'head'); // Strip out stuff in head
		if (stripos($_html,'![if')!==false && stripos($_html,'<![endif]>')!==false) self::replaceTagsCompletley($_html,'!\[if','<!\[endif\]>',false); // Strip out stuff in ifs
		if (stripos($_html,'!--[if')!==false && stripos($_html,'<![endif]-->')!==false) self::replaceTagsCompletley($_html,'!--\[if','<!\[endif\]-->',false); // Strip out stuff in ifs
		$Rules = array ('@<script[^>]*?>.*?</script>@siU', // Strip out javascript
			'@&(quot|#34);@i',                // Replace HTML entities
			'@&(amp|#38);@i',                 //   Ampersand &
			'@&(lt|#60);@i',                  //   Less Than <
			'@&(gt|#62);@i',                  //   Greater Than >
			'@&(nbsp|#160);@i',               //   Non Breaking Space
			'@&(iexcl|#161);@i',              //   Inverted Exclamation point
			'@&(cent|#162);@i',               //   Cent
			'@&(pound|#163);@i',              //   Pound
			'@&(copy|#169);@i',               //   Copyright
			'@&(reg|#174);@i',                //   Registered
			'@&(trade|#8482);@i',             //   trade
			'@&#39;@i',                       //   singleQuote
			'@(\xc2\xa0)@',                   //   nbsp or tab (encoded windows-style)
			'@(\xe2\x80\x8b)@',               //   ZERO WIDTH SPACE
		);
		$Replace = array ('',
			'"',
			'#amper#sand#',
			'<',
			'>',
			' ',
			chr(161),
			chr(162),
			chr(163),
			'(C)',//chr(169),// copyright
			'(R)',//chr(174),// registered
			'(TM)',// trade
			"'",
			' ',
			'',
		);
		$_html = preg_replace($Rules, $Replace, $_html);

		// replace fieldset with legend used for original message header
		$_html = preg_replace_callback('#<fieldset[^>]*>\s*<legend>(.*?)</legend>\s*(.*?)\s*</fieldset>#sm',
			static function($matches) use ($displayCharset)
			{
				$len_legend = strlen($legend = html_entity_decode($matches[1], ENT_QUOTES|ENT_SUBSTITUTE, $displayCharset));
				$content = preg_replace('/<([^@> ]+@[^> ]+)>/', '#lower#than#$1#greater#than#', $matches[2]);
				return "<br>".str_repeat('-', max(0, (64-$len_legend-2)>>1)).' '.$legend.' '.
					str_repeat('-', max(0, (64-$len_legend-2+1)>>1))."<br>".
					$content."<br>".str_repeat('-', 64)."<br>";
			}, $_html);

		//   removing carriage return linefeeds, preserve those enclosed in <pre> </pre> tags
		if ($stripcrl === true )
		{
			if (stripos($_html,'<pre ')!==false || stripos($_html,'<pre>')!==false)
			{
				$contentArr = self::splithtmlByPRE($_html);
				foreach ($contentArr as $k =>&$elem)
				{
					if (stripos($elem,'<pre ')===false && stripos($elem,'<pre>')===false)
					{
						//$elem = str_replace('@(\r\n)@i',' ',$elem);
						$elem = str_replace(array("\r\n","\n"),($isHTML?'':' '),$elem);
					}
				}
				$_html = implode('',$contentArr);
			}
			else
			{
				$_html = str_replace(array("\r\n","\n"),($isHTML?'':' '),$_html);
			}
		}

		// Handle lists
		if(stripos($_html, '<li') !== false)
		{
			$_html = self::replaceLists($_html);
		}

		$tags = array (
			0 => '~<h[123][^>]*>\r*\n*~si',
			1 => '~<h[456][^>]*>\r*\n*~si',
			2 => '~<table[^>]*>\r*\n*~si',
			3 => '~<tr[^>]*>\r*\n*~si',
			4 => '~<li[^>]*>\r*\n*~si',
			5 => '~<br[^>]*>\r*\n*~si',
			6 => '~<br[^>]*>~si',
			7 => '~<\/p[^>r]*>\r*\n*~si',
			8 => '~<div[^>]*>\r*\n*~si',
			9 => '~<span[^>]*>\r*\n*~si',
			10 => '~<hr[^>]*>\r*\n*~si',
			11 => '/<blockquote type="cite">/',
			12 => '/<blockquote>/',
			13 => '~</blockquote>~si',
			14 => '~<blockquote[^>]*>~si',
			15 => '/<=\s([1234567890])/',
			16 => '/>=\s([1234567890])/',
			17 => '/<\s([1234567890])/',
			18 => '/>\s([1234567890])/',
		);
		$Replace = array (
			0 => "\r\n",
			1 => "\r\n",
			2 => "\r\n",
			3 => "\r\n",
			4 => "\r\n",
			5 => "\r\n",
			6 => "\r\n",
			7 => "\r\n",
			8 => "\r\n",
			9 => "",
			10 => "\r\n__________________________________________________\r\n",
			11 => '#blockquote#type#cite#',
			12 => '#blockquote#type#cite#',
			13 => '#blockquote#end#cite#',
			14 => '#blockquote#type#cite#',
			15 => '#lowerorequal#than#$1',
			16 => '#greaterorequal#than#$1',
			17 => '#lower#than#$1',
			18 => '#greater#than#$1',
		);

		$_html = preg_replace($tags,$Replace,$_html);
		$_html = preg_replace('~</t(d|h)>\s*<t(d|h)[^>]*>~si',' - ',$_html);
		$_html = preg_replace('~<img[^>]+>~s','',$_html);
		// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		if (!$noRepEmailAddr) self::replaceEmailAdresses($_html);
		//convert hrefs to description -> URL
		//$_html = preg_replace('~<a[^>]+href=\"([^"]+)\"[^>]*>(.*)</a>~si','[$2 -> $1]',$_html);
		$_html = preg_replace_callback('~<a[^>]+href=\"([^"]+)\"[^>]*>(.*?)</a>~si','self::transform_url2text',$_html);

		// reducing double \r\n to single ones, dont mess with pre sections
		if ($stripcrl === true && $isHTML)
		{
			if (stripos($_html,'<pre ')!==false || stripos($_html,'<pre>')!==false)
			{
				$contentArr = self::splithtmlByPRE($_html);
				foreach ($contentArr as $k =>&$elem)
				{
					if (stripos($elem,'<pre ')===false && stripos($elem,'<pre>')===false)
					{
						//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
						if ( $stripalltags ) {
							$_html = preg_replace('~<[^>^@]+>~s','',$_html);
						}
						// strip out whitespace inbetween CR/LF
						$elem = preg_replace('~\r\n\s+\r\n~si', "\r\n\r\n", $elem);
						// strip out / reduce exess CR/LF
						$elem = preg_replace('~\r\n{3,}~si',"\r\n\r\n",$elem);
					}
				}
				$_html = implode('',$contentArr);
			}
			else
			{
				//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
				if ( $stripalltags ) {
					$_html = preg_replace('~<[^>^@]+>~s','',$_html);
				}
				// strip out whitespace inbetween CR/LF
				$_html = preg_replace('~\r\n\s+\r\n~si', "\r\n\r\n", $_html);
				// strip out / reduce exess CR/LF
				$_html = preg_replace('~(\r\n){3,}~si',"\r\n\r\n",$_html);
			}
		}
		//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
		if ( $stripalltags ) {
			$_html = preg_replace('~<[^>^@]+>~s','',$_html);
			//$_html = strip_tags($_html, '<a>');
		}
		// reducing spaces (not for input that was plain text from the beginning)
		if ($isHTML) $_html = preg_replace('~ +~s',' ',$_html);
		// restoring ampersands
		$_html = str_replace('#amper#sand#','&',$_html);
		// restoring lower|greater[or equal] than
		$_html = str_replace('#lowerorequal#than#','<=',$_html);
		$_html = str_replace('#greaterorequal#than#','>=',$_html);
		$_html = str_replace('#lower#than#','<',$_html);
		$_html = str_replace('#greater#than#','>',$_html);
		//error_log(__METHOD__.__LINE__.' Charset:'.$displayCharset.' -> '.$_html);
		$_html = Api\Translation::convert($_html, $displayCharset, 'utf-8');
		$_html = html_entity_decode($_html, ENT_COMPAT, 'utf-8');
		//error_log(__METHOD__.__LINE__.' Charset:'.$displayCharset.' After html_entity_decode: -> '.$_html);
		//self::replaceEmailAdresses($_html);
		$pos = strpos($_html, 'blockquote');
		//error_log("convert HTML2Text: $_html");
		if($pos === false) {
			return $_html;
		} else {
			$indent = 0;
			$indentString = '';

			$quoteParts = preg_split('/#blockquote#type#cite#/', $_html, -1, PREG_SPLIT_OFFSET_CAPTURE);
			foreach($quoteParts as $quotePart) {
				if($quotePart[1] > 0) {
					$indent++;
					$indentString .= '>';
				}
				$quoteParts2 = preg_split('/#blockquote#end#cite#/', $quotePart[0], -1, PREG_SPLIT_OFFSET_CAPTURE);

				foreach($quoteParts2 as $quotePart2) {
					if($quotePart2[1] > 0) {
						$indent--;
						$indentString = substr($indentString, 0, $indent);
					}

					$quoteParts3 = explode("\r\n", $quotePart2[0]);

					foreach($quoteParts3 as $quotePart3) {
						//error_log(__METHOD__.__LINE__.'Line:'.$quotePart3);
						$allowedLength = 76-strlen("\r\n$indentString");
						// only break lines, if not already indented
						if (substr($quotePart3,0,strlen($indentString)) != $indentString)
						{
							if (strlen($quotePart3) > $allowedLength) {
								$s=explode(" ", $quotePart3);
								$quotePart3 = "";
								$linecnt = 0;
								foreach ($s as $k=>$v) {
									$cnt = strlen($v);
									// only break long words within the wordboundaries,
									// but it may destroy links, so we check for href and dont do it if we find it
									if($cnt > $allowedLength && stripos($v,'href=')===false) {
										//error_log(__METHOD__.__LINE__.'LongWordFound:'.$v);
										$v=wordwrap($v, $allowedLength, "\r\n$indentString", true);
									}
									// the rest should be broken at the start of the new word that exceeds the limit
									if ($linecnt+$cnt > $allowedLength) {
										$v="\r\n$indentString$v";
										//error_log(__METHOD__.__LINE__.'breaking here:'.$v);
										$linecnt = 0;
									} else {
										$linecnt += $cnt;
									}
									if (strlen($v))  $quotePart3 .= (strlen($quotePart3) ? " " : "").$v;
								}
							}
						}
						//error_log(__METHOD__.__LINE__.'partString to return:'.$indentString . $quotePart3);
						$asciiTextBuff[] = $indentString . $quotePart3 ;
					}
				}
			}
			return implode("\r\n",$asciiTextBuff);
		}
	}

	/**
	 * Replace HTML lists with a plain text equivalent
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	static function replaceLists($html)
	{
		if(!$html || stripos($html, '<li') === False)
		{
			return $html;
		}
		// try to cleanup not encoded ampersands used possibily in urls
		$html =  preg_replace('/&(?!#?[a-zA-Z0-9]+;)/', '&amp;', $html);

		$dom = new \DOMDocument('1.0','UTF-8');
		if (!@$dom->loadHTML(
			'<?xml encoding="UTF-8">'. Api\Translation::convert($html,preg_match('/<meta[^>]+content="[^>"]+charset=([^;"]+)/i', $html, $matches) ? $matches[1] : false, 'utf8'),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS
		))
		{
			// do not sent possible xml errors in buffer further otherwise it may get caught as et2 template error in Widget
			libxml_clear_errors();
			// Failed to parse
			return $html;
		}

		$dom->normalizeDocument();

		foreach(array('ol','ul') as $list_type)
		{
			$lists = $dom->getElementsByTagName($list_type);
			foreach($lists as $list)
			{
				$list_text = "\r\n";
				$item_count = 0;
				$prefix = $list_type == 'ul' ? ' * ' : '. ';
				$elements = $list->getElementsByTagName('li');
				foreach($elements as $element)
				{
					if($element->tagName == 'li')
					{
						$list_text .= ($list_type == 'ol' ? ' '. ++$item_count : '') . $prefix . $element->textContent . "\r\n";
					}
					else
					{
						$list_text .= $element->nodeValue;
					}
				}

				$list->parentNode->replaceChild($dom->createTextNode($list_text), $list);
			}
		}
		$dom->removeChild($dom->firstChild);

		// do not sent possible xml errors in buffer further otherwise it may get caught as et2 template error in Widget
		libxml_clear_errors();

		return $dom->saveHTML();
	}

	/**
	 * split html by PRE tag, return array with all content pre-sections isolated in array elements
	 * @author Leithoff, Klaus
	 * @param string html
	 * @return mixed array of parts or unaffected html
	 */
	static function splithtmlByPRE($html)
	{
		$searchFor = '<pre ';
		$pos = stripos($html,$searchFor);
		if ($pos===false)
		{
			$searchFor = '<pre>';
			$pos = stripos($html,$searchFor);
		}
		if ($pos === false || !$html)
		{
			return $html;
		}
		$html2ret[] = substr($html,0,$pos);
		while ($pos!==false)
		{
			// avoid infinit loop in case the endof pre can't be found, just give the
			// end position to return the rest of content as return html
			if (($endofpre = stripos($html, '</pre>', $pos)) === false)
			{
				$html2ret[] = substr($html, $pos);
				break;
			}
			$length = $endofpre-$pos+6;
			$html2ret[] = substr($html,$pos,$length);
			$searchFor = '<pre ';
			$pos = stripos($html,$searchFor, $endofpre+6);
			if ($pos===false)
			{
				$searchFor = '<pre>';
				$pos = stripos($html,$searchFor, $endofpre+6);
			}
			$html2ret[] = ($pos ? substr($html,$endofpre+6,$pos-($endofpre+6)): substr($html,$endofpre+6));
			//$pos=false;
		}
		//error_log(__METHOD__.__LINE__.array2string($html2ret));
		return $html2ret;
	}

	/**
	 * Convert plain text into HTML replacing empty lines (double newline) with paragraphs and single newlines with <br>
	 *
	 * @param string $text
	 * @return string
	 */
	public static function convertTextToHtml($text)
	{
		return '<p>'.implode("</p>\n<p>", array_map('nl2br', preg_split("/\r?\n\r?\n/", $text)))."</p>\n";
	}
}