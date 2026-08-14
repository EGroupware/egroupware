<?php
/**
 * EGroupware Mail: inline (cid:) image resolution for mail body HTML
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use Horde_Mime_Part;
use mail_ui;

/**
 * Extracted from mail_ui - the only coupling to mail_ui itself is a single read of the public
 * static `mail_ui::$icServerID` (the same light-touch pattern ProfileHandler uses), so this class
 * needs no `mail_ui` instance.
 *
 * `mail_ui::resolve_inline_image_byType()` is called from tracker's `tracker_bo` (a separate repo)
 * by that exact name, so `mail_ui` keeps a thin wrapper for it - see
 * doc/ai/projects/mail-bo-decoupling.md's extraction discipline. `resolve_inline_images()` has no
 * external callers and was removed from `mail_ui` outright.
 */
class BodyHandler
{
	/**
	 * @param string $_body content of message
	 * @param string $_mailbox mail box
	 * @param string $_uid uid
	 * @param string $_partID part id
	 * @param string $_messageType 'html' or 'plain'
	 * @return string
	 */
	public static function resolveInlineImages($_body, $_mailbox, $_uid, $_partID, $_messageType='html') : string
	{
		if ($_messageType === 'plain')
		{
			return self::resolveInlineImageByType($_body, $_mailbox, $_uid, $_partID, 'plain');
		}
		foreach (['src', 'url', 'background'] as $type)
		{
			$_body = self::resolveInlineImageByType($_body, $_mailbox, $_uid, $_partID, $type);
		}
		return $_body;
	}

	/**
	 * Replace CID with proper type of content understandable by browser
	 *
	 * @param string $_body content of message
	 * @param string $_mailbox mail box
	 * @param string $_uid uid
	 * @param string $_partID part id
	 * @param string $_type = 'src' type of inline image that needs to be resolved and replaced
	 *	- types: {plain|src|url|background}
	 * @param callable $_link_callback Function to generate the link to the image. If
	 *	not provided, a default (using mail) will be used.
	 * @return string returns body content including all CID replacements
	 */
	public static function resolveInlineImageByType($_body, $_mailbox, $_uid, $_partID, $_type='src', callable $_link_callback=null)
	{
		if (is_null($_link_callback))
		{
			$_link_callback = function ($_cid) use ($_mailbox, $_uid, $_partID)
			{
				$linkData = [
					'menuaction' => 'mail.mail_ui.displayImage',
					'uid' => base64_encode($_uid),
					'mailbox' => base64_encode($_mailbox),
					'cid' => base64_encode($_cid),
					'partID' => $_partID,
				];
				return Api\Egw::link('/index.php', $linkData);
			};
		}

		$replace_callback = function ($matches) use ($_mailbox, $_uid, $_partID, $_type, $_link_callback)
		{
			if (!$_type)
			{
				return false;
			}
			$CID = '';
			// Build up matches according to selected type
			switch ($_type)
			{
				case "plain":
					$CID = $matches[1];
					break;
				case "src":
					// as src:cid contains some kind of url, it is likely to be urlencoded
					$CID = urldecode($matches[2]);
					break;
				case "url":
					$CID = $matches[1];
					break;
				case "background":
					$CID = $matches[2];
					break;
			}

			static $cache = [];	// some caching, if mails containing the same image multiple times

			if (is_array($matches) && $CID)
			{
				$imageURL = call_user_func($_link_callback, $CID);
				// to test without data uris, comment the if close incl. it's body
				if (Api\Header\UserAgent::type() != 'msie' || Api\Header\UserAgent::version() >= 8)
				{
					if (!isset($cache[$imageURL]))
					{
						if ($_type != "background" && !$imageURL)
						{
							$bo = Mail::getInstance(false, mail_ui::$icServerID);
							$attachment = $bo->getAttachmentByCID($_uid, $CID, $_partID);

							// only use data uri for "smaller" images, as otherwise the first display of the mail takes to long
							if (($attachment instanceof Horde_Mime_Part) && $attachment->getBytes() < 8192)	// msie=8 allows max 32k data uris
							{
								$bo->fetchPartContents($_uid, $attachment);
								$cache[$imageURL] = 'data:'.$attachment->getType().';base64,'.base64_encode($attachment->getContents());
							}
							else
							{
								$cache[$imageURL] = $imageURL;
							}
						}
						else
						{
							$cache[$imageURL] = $imageURL;
						}
					}
					$imageURL = $cache[$imageURL];
				}

				// Decides the final result of replacement according to the type
				switch ($_type)
				{
					case "plain":
						return '<img src="'.$imageURL.'" />';
					case "src":
						return 'src="'.$imageURL.'"';
					case "url":
						return 'url('.$imageURL.');';
					case "background":
						return 'background="'.$imageURL.'"';
				}
			}
			return false;
		};

		// return new body content base on chosen type
		switch ($_type)
		{
			case "plain":
				return preg_replace_callback("/\[cid:(.*)\]/iU", $replace_callback, $_body);
			case "src":
				return preg_replace_callback("/src=(\"|\')cid:(.*)(\"|\')/iU", $replace_callback, $_body);
			case "url":
				return preg_replace_callback("/url\(cid:(.*)\);/iU", $replace_callback, $_body);
			case "background":
				return preg_replace_callback("/background=(\"|\')cid:(.*)(\"|\')/iU", $replace_callback, $_body);
		}
	}
}
