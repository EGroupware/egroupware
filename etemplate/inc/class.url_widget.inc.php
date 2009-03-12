<?php
/**
 * eGroupWare  eTemplate Extension - URL widget: email addresses, external url's, clickable telephone numbers
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare  eTemplate Extension - URL widget: email addresses, external url's, clickable telephone numbers
 *
 * url:
 * External URL with target _blank (http:// get's added, if there's no protocol)
 * Uses our redirect.php script, if session is not stored in cookie!
 *
 * url-email:
 * The value is an RFC822 email address or an interger account_id.
 * If the value is empty or 0, the content of the options field is used instead.
 * The address get displayed as name with the email as tooltip and a link to compose a mail.
 *
 * url-telefon:
 * Displays a telephone number.
 * If telephony integration is configured in addressbook, make the phone number a link to allow to call it.
 * @todo Input validation and normalization of phone numbers
 */
class url_widget
{
	/**
	 * exported methods of this class
	 *
	 * @var array
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 * @var string
	 */
	var $human_name = array(
		'url' => 'Url',
		'url-email' => 'EMail',
		'url-phone' => 'Phone number',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function __construct($ui)
	{
		$this->ui = $ui;
	}

	/**
	 * pearl regular expression for email address
	 *
	 * has to be used case insensitive: /i
	 */
	const EMAIL_PREG = '[a-z0-9][a-z0-9._-]*[a-z0-9]@(([a-z0-9]*[a-z0-9]\.)|([a-z0-9][a-z0-9_-]*[a-z0-9]\.))+[a-z0-9][a-z0-9_-]*[a-z0-9]';

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		$readonly = $cell['readonly'] || $readonlys;

		switch($cell['type'])
		{
			case 'url-email':	// size: size,max-size,{2=no validation,1=rfc822 name part allowed, default only email},default if value empty
				list($size,$max_size,$validation_type,$default) = explode(',',$cell['size'],4);	// 4 = allow default to contain commas
				if (!$value) $value = $default;	// use default from size/options if empty
				$cell['no_lang'] = 1;

				if (!$readonly)
				{
					$cell['type'] = 'text';
					$cell['size'] = $size.','.$max_size.',';
					if ((int)$validation_type < 2)
					{
						$cell['size'] .= $cell['needed'] ? '/^(' : '/^(|';	// if not needed allow empty, as EMAIL_PREG does not
						$cell['size'] .= self::EMAIL_PREG;
						if ($validation_type == 1)	// allow rfc822 name part too: Ralf Becker <ralf@out.de>
						{
							$cell['size'] .= '|[^<]+ ?<'.self::EMAIL_PREG.'>';
						}
						$cell['size'] .= ')$/i';
					}
					#_debug_array($cell);
					break;
				}
				$rfc822 = $value;
				if (!(int)$size) $size = 22;	// default size for display
				if (is_numeric($value))
				{
					$email = $GLOBALS['egw']->accounts->id2name($value,'account_email');
					$value = $GLOBALS['egw']->accounts->id2name($value,'account_fullname');
					$rfc822 = $value.' <'.$email.'>';
				}
				elseif (preg_match('/^(.*) ?<(.*)>/',$value,$matches))
				{
					$value = $matches[1];
					$email = $matches[2];
				}
				elseif (strpos($email=$value,'@') !== false)
				{
					if (strpos($email=$value,'&') !== false)
					{
						list($email,$addoptions) = explode('&',$value,2);
						$rfc822 = $value = $email;
					}
					if (strlen($value) > $size)		// shorten the name to size-2 plus '...'
					{
						$value = substr($value,0,$size-2).'...';
					}
				}
				$link = $this->email2link($email,$rfc822).($addoptions ? '&'.$addoptions : '');

				$cell['type'] = 'label';
				$cell['size'] = ','.$link.',,,,'.($link[0]=='f'?'700x750':'').($value != $email ? ','.$email : '');
				break;

			case 'url':		// options: [size[,max-size[,preg]]]
				list($size,$max_size,$preg) = explode(',',$cell['size'],3); // 3 = allow default to contain commas
				if (!$readonly)
				{
					$cell['type'] = 'text';
					// todo: (optional) validation
					break;
				}
				if (!(int)$size) $size = 24;	// default size for display
				$cell['type'] = 'label';
				if ($value)
				{
					$link = (strpos($value,'://') === false) ? 'http://'.$value : $value;
					if (!$GLOBALS['egw_info']['server']['usecookies'])	// if session-id is in url, use redirect.php to remove it from referrer
					{
						$link = $GLOBALS['egw_info']['server']['webserver_url'].'/redirect.php?go='.$link;
						if ($link[0] == '/') $link = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$link;
					}
					$cell['size'] = ','.$link.',,,_blank';
				}
				if (substr($value,0,7) == 'http://')
				{
					$value = substr($value,7);		// cut off http:// in display
				}
				if (strlen($value) > $size)		// shorten the name to size-2 plus '...'
				{
					$value = substr($value,0,$size-2).'...';
				}
				break;

			case 'url-phone':		// options: [size[,max-size[,preg]]]
				list($size,$max_size,$preg) = explode(',',$cell['size'],3); // 3 = allow default to contain commas
				if (!$readonly)
				{
					$cell['type'] = 'text';
					// todo: (optional) validation
					break;
				}
				$cell['type'] = 'label';
				if ($value)
				{
					$link = self::phone2link($value);
					if (!$GLOBALS['egw_info']['server']['usecookies'] && substr($link,0,4) == 'http')	// if session-id is in url, use redirect.php to remove it from referrer
					{
						$link = $GLOBALS['egw_info']['server']['webserver_url'].'/redirect.php?go='.$link;
						if ($link[0] == '/') $link = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$link;
					}
					$cell['size'] = ','.$link.',,,calling,'.$GLOBALS['egw_info']['server']['call_popup'];
				}
				break;
		}
		return True;	// extra Label Ok
	}

	/**
	 * convert email-address in compose link
	 *
	 * @todo make the called link configurable in the user prefs, to eg. allow to allways use mailto ...
	 * @param string $email email-addresse
	 * @param string $rfc822 complete rfc822 addresse to use eg. for fmail
	 * @return array/string array with get-params or mailto:$email, or '' or no mail addresse
	 */
	static function email2link($email,$rfc822='')
	{
		if (!$email || strpos($email,'@') === false) return '';

		if($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			//return 'felamimail.uicompose.compose&preset[to]='.($rfc822 ? $rfc822 : $email);
			return 'felamimail.uicompose.compose&send_to='.base64_encode($rfc822 ? $rfc822 : $email);
		}
		if($GLOBALS['egw_info']['user']['apps']['email'])
		{
			return 'email.uicompose.compose&to='.$email;
		}
		return 'mailto:' . $email;
	}

	/**
	 * returns link to call the given phonenumber
	 *
	 * replaces '%1' with the phonenumber to call, '%u' with the user's account_lid and '%t' with his work-phone-number
	 *
	 * @param string $number phone number
	 * @return string|boolean string with link or false if no no telephony integration configured
	 */
	static function phone2link($number)
	{
		if (!$number || !$GLOBALS['egw_info']['server']['call_link']) return false;

		static $userphone;
		if (is_null($userphone) && strpos($GLOBALS['egw_info']['server']['call_link'],'%t') !== false)
		{
			$user = $GLOBALS['egw']->contacts->read('account:'.$GLOBALS['egw_info']['user']['account_id']);
			$userphone = is_array($user) ? ($user['tel_work'] ? $user['tel_work'] : $user['tel_home']) : false;
		}
		$number = preg_replace('/[^0-9+]+/','',str_replace('&#9829;','',$number));	// remove number formatting chars messing up the links

		return str_replace(array('%1','%u','%t'),array(urlencode($number),$GLOBALS['egw_info']['user']['account_lid'],$userphone),
			$GLOBALS['egw_info']['server']['call_link']);
	}
}
