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
