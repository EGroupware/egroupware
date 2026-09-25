<?php
/**
 * EGroupware Api: mail body/HTML decoding and cleanup utilities
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Html\HtmLawed;
use EGroupware\Api\Mail;
use EGroupware\Api\Translation;

/**
 * Pure body/HTML transform helpers, extracted from Api\Mail (mail_bo).
 *
 * Deliberately has NO dependency on an IMAP connection, session, or any other Api\Mail instance
 * state - every method here operates only on the string/array it's given (plus the small set of
 * config statics still on Api\Mail itself: $displayCharset, $htmLawed_config). Api\Mail no longer
 * has methods of these names at all - every in-repo call site was repointed here directly
 * (mail_compose, mail_ui, mail_zpush); none of these five were called from tracker's mail-handler
 * (the one external/separate-repo consumer that does need Api\Mail compatibility wrappers kept for
 * a few other methods), so no wrapper was needed here - see doc/ai/projects/mail-bo-decoupling.md.
 *
 * getMimePartCharset() and decodeMimePart() were NOT extracted here - a repo-wide search found
 * their only reference anywhere is inside a disabled, commented-out line in Api\Mail itself
 * ("RB: not sure what this is"), so they were dead code and got removed instead of moved.
 */
class BodyDecoding
{
	/**
	 * htmlentities() that falls back to detecting/converting the source encoding if the direct
	 * conversion produces nothing (eg. the string wasn't actually in the given charset)
	 *
	 * @param string $_string
	 * @param string|false $_charset defaults to Mail::$displayCharset
	 * @return string
	 */
	public static function htmlentities($_string, $_charset=false)
	{
		if ($_charset === false)
		{
			$_charset = Mail::$displayCharset;
		}
		$string = @htmlentities($_string, ENT_QUOTES, $_charset, false);
		if (empty($string) && !empty($_string))
		{
			$string = @htmlentities(
				Translation::convert($_string, Translation::detect_encoding($_string), $_charset),
				ENT_QUOTES | ENT_IGNORE, $_charset, false
			);
		}
		return $string;
	}

	/**
	 * Clean a message from elements regarded as potentially harmful
	 *
	 * @param string $_html reference, modified in place
	 */
	public static function getCleanHTML(&$_html)
	{
		// repair double-encoded ampersands, and some stuff htmLawed stumbles upon with balancing switched on
		$_html = str_replace(
			['&amp;amp;', '<DIV><BR></DIV>', '<DIV>&nbsp;</DIV>', '<div>&nbsp;</div>', '</td></font>', '<br><td>', '<tr></tr>', '<o:p></o:p>', '<o:p>', '</o:p>'],
			['&amp;',     '<BR>',            '<BR>',              '<BR>',              '</font></td>', '<td>',    '',          '',             '',     ''],
			$_html
		);
		if (stripos($_html, 'style') !== false)
		{
			Html::replaceTagsCompletley($_html, 'style'); // clean out empty or pagewide style definitions / left over tags
		}
		if (stripos($_html, 'head') !== false)
		{
			Html::replaceTagsCompletley($_html, 'head'); // strip out stuff in head
		}
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() === 1)
		{
			$_html = stripslashes($_html);
		}
		// strip out doctype in head, as htmLawed cannot handle it
		if (stripos($_html, '!doctype') !== false)
		{
			Html::replaceTagsCompletley($_html, '!doctype');
		}
		if (stripos($_html, '?xml:namespace') !== false)
		{
			Html::replaceTagsCompletley($_html, '\?xml:namespace', '/>', false);
		}
		if (stripos($_html, '?xml version') !== false)
		{
			Html::replaceTagsCompletley($_html, '\?xml version', '\?>', false);
		}
		if (strpos($_html, '!CURSOR') !== false)
		{
			Html::replaceTagsCompletley($_html, '!CURSOR');
		}
		// htmLawed filters only the 'body'
		$_html = HtmLawed::purify($_html, Mail::$htmLawed_config, [], true);
		// clean out comments, should not be needed as purify should do the job
		$search = [
			'@url\(http:\/\/[^\)].*?\)@si', // url calls e.g. in style definitions
			'@<!--[\s\S]*?[ \t\n\r]*-->@',  // strip multi-line comments including CDATA
		];
		$_html = preg_replace($search, '', $_html);
		// remove non-printable chars
		$_html = preg_replace('/([\000-\011])/', '', $_html);
	}

	/**
	 * Flatten a (possibly nested) array of body parts into a single-level array of parts that
	 * each have a 'body' key
	 *
	 * @param array|mixed $_bodyParts
	 * @return array|mixed|null
	 */
	public static function normalizeBodyParts($_bodyParts)
	{
		if (!is_array($_bodyParts))
		{
			return $_bodyParts;
		}
		$body2return = [];
		foreach ($_bodyParts as $singleBodyPart)
		{
			if (!isset($singleBodyPart['body']))
			{
				foreach ((array)self::normalizeBodyParts($singleBodyPart) as $val)
				{
					$body2return[] = $val;
				}
				continue;
			}
			$body2return[] = $singleBodyPart;
		}
		return $body2return;
	}

	/**
	 * Extract the (cleaned, security-checked) CSS styles from the given bodyparts
	 *
	 * @param array $_bodyParts
	 * @return string
	 */
	public static function &getStyles($_bodyParts)
	{
		$style = $ret = '';
		if (empty($_bodyParts))
		{
			return $ret;
		}
		foreach ((array)$_bodyParts as $singleBodyPart)
		{
			if (!isset($singleBodyPart['body']))
			{
				$singleBodyPart['body'] = self::getStyles($singleBodyPart);
				$style .= $singleBodyPart['body'];
				continue;
			}

			if (empty($singleBodyPart['charSet']))
			{
				$singleBodyPart['charSet'] = Translation::detect_encoding($singleBodyPart['body']);
			}
			$singleBodyPart['body'] = Translation::convert($singleBodyPart['body'], strtolower($singleBodyPart['charSet']));

			$style2buffer = '';
			if (stripos($singleBodyPart['body'], '<style') !== false &&
				preg_match_all('#<style(?:\s.*)?>(.+)</style>#isU', $singleBodyPart['body'], $newStyle) > 0)
			{
				$style2buffer = implode('', $newStyle[0]);
			}
			if (!empty($style2buffer) && strtoupper(Mail::$displayCharset) == 'UTF-8' &&
				@json_encode($style2buffer) === 'null' && strlen($style2buffer) > 0)
			{
				// this should not be needed, unless something fails with charset detection/wrong charset passed
				error_log(__METHOD__.' ('.__LINE__.') Found Invalid sequence for utf-8 in CSS:'.$style2buffer.
					' Charset Reported:'.$singleBodyPart['charSet'].' Charset Detected:'.Translation::detect_encoding($style2buffer));
				$style2buffer = utf8_encode($style2buffer);
			}
			$style .= $style2buffer;
		}
		// clean out url() calls e.g. in style definitions
		$style = preg_replace('@url\(http:\/\/[^\)].*?\)@si', '', $style);

		// CSS security - http://code.google.com/p/browsersec/wiki/Part1#Cascading_stylesheets
		$css = preg_replace('/(javascript|expression|-moz-binding)/i', '', $style);
		if (stripos($css, 'script') !== false)
		{
			Html::replaceTagsCompletley($css, 'script'); // strip out script that may be included
		}
		// styledefinitions are enclosed with curly brackets; template stuff tries to replace everything
		// between curly brackets that has no horizontal whitespace, so widen the colons a bit; the
		// <!-- style --> comment wrapper is outdated and ck-editor does not understand it, so remove it
		$css = str_replace([':', '<!--', '-->'], [': ', '', ''], $css);

		// the outlook style fix sets line-height:0, which breaks all tr lines in the content - restore it
		if (preg_match('/Outlook 2016 Height Fix/i', $css))
		{
			$css .= '<style>tr {line-height: initial} </style>';
		}
		return $css;
	}

	/**
	 * Recover a part's real bytes when its Content-Transfer-Encoding was never declared at all, but
	 * the content is still literally base64 TEXT - found live (ticket #125171 follow-up): a bare
	 * whole-message PDF/image with a "Content-Type: application/pdf" header and NO
	 * Content-Transfer-Encoding header whatsoever, whose body is nonetheless the base64-encoded PDF
	 * (the sender's own mail system encoded the binary but forgot to declare it). With no encoding
	 * declared, Horde_Mime_Part treats the content as literal 7bit passthrough and never reverses
	 * it - every caller that just trusts getContents() (classic Api\Mail::getMessageBody()'s own
	 * "message is just a pdf" echo, and Jmap\Imap::structureToHtml()'s identical bare-content
	 * branch) would otherwise embed/stream the base64 TEXT itself as if it were the real binary.
	 *
	 * Deliberately has no per-format magic-byte table (checking for "%PDF-"/PNG/JPEG signatures
	 * specifically) - genuine binary content (a real PDF or image, of any size worth looking at) is
	 * virtually certain to contain at least one byte outside the base64 alphabet, so "the ENTIRE
	 * content, once whitespace is stripped, matches the base64 charset with valid padding" is
	 * already a safe, strong, format-independent signal on its own that this is undecoded base64
	 * text, not real bytes - and is what actually let this same fix cover an image just as well as
	 * a PDF without needing separate detection logic per type.
	 *
	 * A PDF/image can be large (tens of MB) and this is only ever reached for the "whole message IS
	 * the attachment" case, which already holds a base64-inflated copy of the whole thing in memory
	 * for the data: URI embed - so this deliberately checks only a small prefix first (genuine
	 * binary content fails that cheaply, almost always within the first few hundred bytes) before
	 * ever running a regex/copy over the FULL content, to avoid adding a second full-size
	 * scan-and-copy on top of that existing memory cost for the overwhelmingly common (real binary,
	 * not this malformed shape) case.
	 *
	 * @param string $bytes getContents()'s own (possibly still-encoded) output
	 * @return string the decoded bytes if this shape was detected, else $bytes unchanged
	 */
	public static function decodeIfStillBase64(string $bytes) : string
	{
		if ($bytes === '')
		{
			return $bytes;
		}
		$sample = preg_replace('/\s+/', '', substr($bytes, 0, 256));
		if ($sample !== '' && !preg_match('/^[A-Za-z0-9+\/=]*$/', $sample))
		{
			return $bytes;
		}
		// for short content, $sample (already computed above) covers the ENTIRE string - a cheap,
		// precise padding-length check here guards a short garbage/non-base64 fragment from
		// decoding to meaningless bytes instead of correctly staying unchanged, same as the full
		// strlen($trimmed) % 4 check this method used to always do - just skipped for anything
		// long enough that computing it would mean an extra full-string pass of its own.
		if (strlen($bytes) <= 256 && strlen($sample) % 4 !== 0)
		{
			return $bytes;
		}
		// only reached for content that at least LOOKS like it could be base64 text (a rare case
		// in practice) - decode via a php://temp-backed stream + the 'convert.base64-decode'
		// filter (STREAM_FILTER_WRITE - applying it on both read AND write, this filter's default,
		// would decode twice and produce garbage) rather than a second full-string preg_replace()+
		// base64_decode() pair: the filter tolerates the MIME line-wrap whitespace itself (no
		// separate strip pass needed first), and php://temp only holds its content in RAM up to
		// ~2MB before spilling to a temp file - keeping a large malformed attachment (tens of MB of
		// base64 TEXT) from ever needing multiple full-size copies in memory at once on top of
		// $bytes itself (ralf, live: "we must be careful not to exceed PHP memory_limit, as PDFs
		// can be quite big").
		$stream = fopen('php://temp', 'r+');
		stream_filter_append($stream, 'convert.base64-decode', STREAM_FILTER_WRITE);
		fwrite($stream, $bytes);
		rewind($stream);
		$decoded = stream_get_contents($stream);
		fclose($stream);
		return $decoded !== '' ? $decoded : $bytes;
	}

	/**
	 * Wordwrap that avoids breaking lines containing links (or optionally a given prefix)
	 *
	 * @param string $str
	 * @param int $cols
	 * @param string $cut prefix added to a wrapped continuation line
	 * @param string|false $dontbreaklinesstartingwith never wrap lines starting with this prefix
	 * @return string
	 */
	public static function wordwrap($str, $cols, $cut, $dontbreaklinesstartingwith=false)
	{
		$lines = explode("\n", $str);
		$newStr = '';
		foreach ($lines as $line)
		{
			$allowedLength = $cols - strlen($cut);
			// don't try to break lines with links, chance is we mess up the text is way too big
			if (strlen($line) > $allowedLength && stripos($line, 'href=') === false &&
				($dontbreaklinesstartingwith == false ||
					($dontbreaklinesstartingwith &&
						strlen($dontbreaklinesstartingwith) >= 1 &&
						substr($line, 0, strlen($dontbreaklinesstartingwith)) != $dontbreaklinesstartingwith
					)
				)
			)
			{
				$s = explode(' ', $line);
				$line = '';
				$linecnt = 0;
				foreach ($s as &$v)
				{
					$cnt = strlen($v);
					// only break long words within the word boundaries, but it may destroy links, so we
					// check for href and don't do it if we find one, or any html within the word, because
					// we do not want to break html by accident, and don't break apart links like https://...
					if ($cnt > $allowedLength && !preg_match('#(https?|www\.)#', $v) &&
						stripos($v, 'href=') === false && stripos($v, 'onclick=') === false &&
						$cnt == strlen(html_entity_decode($v)))
					{
						$v = wordwrap($v, $allowedLength, $cut, true);
					}
					// the rest should be broken at the start of the new word that exceeds the limit
					if ($linecnt + $cnt > $allowedLength)
					{
						$v = $cut.$v;
						$linecnt = strlen($v) - strlen($cut);
					}
					else
					{
						$linecnt += $cnt;
					}
					if (strlen($v))
					{
						$line .= (strlen($line) ? ' ' : '').$v;
					}
				}
			}
			$newStr .= $line."\n";
		}
		return $newStr;
	}
}
