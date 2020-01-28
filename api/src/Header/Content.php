<?php
/**
 * EGroupware API: safe content type and disposition headers
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright 2001-2016 by RalfBecker@outdoor-training.de
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api\Header;

use EGroupware\Api;

/**
 * Safe content type and disposition headers
 */
class Content
{
	/**
	 * Output safe content headers for user-content, mitigating risk of javascript or html
	 *
	 * Mitigate risk of serving javascript or css from our domain,
	 * which will get around same origin policy and CSP!
	 *
	 * Mitigate risk of html downloads by using CSP or force download for IE
	 *
	 * @param resource|string& $content content might be changed by this call
	 * @param string $path filename or path for content-disposition header
	 * @param string& $mime ='' mimetype or '' (default) to detect it from filename, using mime_magic::filename2mime()
	 *	on return used, maybe changed, mime-type
	 * @param int $length =0 content length, default 0 = skip that header
	 *  on return changed size
	 * @param boolean $nocache =true send headers to disallow browser/proxies to cache the download
	 * @param boolean $force_download =true send content-disposition attachment header
	 * @param boolean $no_content_type =false do not send actual content-type and content-length header, just content-disposition
	 */
	public static function safe(&$content, $path, &$mime='', &$length=0, $nocache=true, $force_download=true, $no_content_type=false)
	{
		// change old/aliased mime-types to new one, eg. image/pdf to application/pdf
		$mime = Api\MimeMagic::fix_mime_type($mime);

		// mitigate risk of serving javascript or css via webdav from our domain,
		// which will get around same origin policy and CSP
		list($type, $subtype) = explode('/', strtolower($mime));
		if (!$force_download && in_array($type, array('application', 'text')) &&
			in_array($subtype, array('javascript', 'x-javascript', 'ecmascript', 'jscript', 'vbscript', 'css')))
		{
			// unfortunatly only Chrome and IE >= 8 allow to switch content-sniffing off with X-Content-Type-Options: nosniff
			if (UserAgent::type() == 'chrome' || UserAgent::type() == 'msie' && UserAgent::version() >= 8 ||
				UserAgent::type() == 'firefox' && UserAgent::version() >= 50)
			{
				$mime = 'text/plain';
				header('X-Content-Type-Options: nosniff');	// stop IE & Chrome from content-type sniffing
			}
			// for the rest we change mime-type to text/html and let code below handle it safely
			// this stops Safari and Firefox from using it as src attribute in a script tag
			// but only for "real" browsers, we dont want to modify data for our WebDAV clients
			elseif (isset($_SERVER['HTTP_REFERER']))
			{
				$mime = 'text/html';
				if (is_resource($content))
				{
					$data = fread($content, $length);
					fclose($content);
					$content = $data;
					unset($data);
				}
				$content = '<pre>'.$content;
				$length += 5;
			}
		}
		// mitigate risk of html (or SVG) downloads by using CSP or force download for IE
		if (!$force_download && in_array($mime, ['text/html', 'application/xhtml+xml', 'image/svg+xml']))
		{
			// use CSP only for current user-agents/versions I was able to positivly test
			if (UserAgent::type() == 'chrome' && UserAgent::version() >= 24 ||
				// mobile FF 24 on Android does NOT honor CSP!
				UserAgent::type() == 'firefox' && !UserAgent::mobile() && UserAgent::version() >= 24 ||
				UserAgent::type() == 'safari' && !UserAgent::mobile() && UserAgent::version() >= 536 ||	// OS X
				UserAgent::type() == 'safari' && UserAgent::mobile() && UserAgent::version() >= 9537)	// iOS 7
			{
				// forbid to execute any javascript (to be precise anything but images and styles)
				ContentSecurityPolicy::header("image-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; default-src 'none'");
			}
			else	// everything else get's a Content-dispostion: attachment, to be on save side
			{
				//error_log(__METHOD__."('$options[path]') ".UserAgent::type().'/'.UserAgent::version().(UserAgent::mobile()?'/mobile':'').": using Content-disposition: attachment");
				$force_download = true;
			}
		}
		// always tell browser to do no sniffing / use our content-type
		header('X-Content-Type-Options: nosniff');

		if ($no_content_type)
		{
			if ($force_download) self::disposition(Api\Vfs::basename($path), $force_download);
		}
		else
		{
			self::type(Api\Vfs::basename($path), $mime, $length, $nocache, $force_download);
		}
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
	 */
	public static function type($fn,$mime='',$length=0,$nocache=True,$forceDownload=true)
	{
		// if no mime-type is given or it's the default binary-type, guess it from the extension
		if(empty($mime) || $mime == 'application/octet-stream')
		{
			$mime = Api\MimeMagic::filename2mime($fn);
		}
		if($fn)
		{
			// Show this for all
			self::disposition($fn,$forceDownload);
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
	 * Output content-disposition header for file downloads
	 *
	 * @param string $fn filename
	 * @param boolean $forceDownload =true send headers to handle as attachment/download
	 */
	public static function disposition($fn, $forceDownload=true)
	{
		if ($forceDownload)
		{
			$attachment = ' attachment;';
		}
		else
		{
			$attachment = ' inline;';
		}

		header('Content-disposition:'.$attachment.' filename="'.Api\Translation::to_ascii($fn).'"; filename*=utf-8\'\''.rawurlencode($fn));
	}
}
