<?php
/**
 * Addressbook - Sitemgr contact form
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;

/**
 * SiteMgr contact form for the addressbook
 *
 */
class addressbook_contactform
{
	/**
	 * Callback as variable for easier extending
	 *
	 * @var string
	 */
	var $callback = 'addressbook.addressbook_contactform.display';

	/**
	 * Shows the contactform and stores the submitted data
	 *
	 * @param array $content=null submitted eTemplate content
	 * @param int $addressbook=null int owner-id of addressbook to save contacts too
	 * @param array $fields=null field-names to show
	 * @param string $msg=null message to show after submitting the form
	 * @param string $email=null comma-separated email addresses
	 * @param string $tpl_name=null custom etemplate to use
	 * @param string $subject=null subject for email
	 * @param string $copytoreceiver=false send a copy of notification to receiver
	 * @return string html content
	 */
	function display(array $content=null,$addressbook=null,$fields=null,$msg=null,$email=null,$tpl_name=null,$subject=null,$copytoreceiver=false,$sel_options=array())
	{
		return $this->display_var($content,$addressbook,$fields,$msg,$email,$tpl_name,$subject,$copytoreceiver,$sel_options);
	}

	/**
	 * Shows the contactform and stores the submitted data ($content is a var parameter, eg. for extending classes)
	 *
	 * @param array &$content=null submitted eTemplate content
	 * @param int $addressbook=null int owner-id of addressbook to save contacts too
	 * @param array $fields=null field-names to show
	 * @param string $msg=null message to show after submitting the form
	 * @param string $email=null comma-separated email addresses
	 * @param string $tpl_name=null custom etemplate to use
	 * @param string $subject=null subject for email
	 * @param string $copytoreceiver=false send a copy of notification to receiver
	 * @return string html content
	 */
	function display_var(array &$content=null,$addressbook=null,$fields=null,$msg=null,$email=null,$tpl_name=null,$subject=null,$copytoreceiver=false,$sel_options=array())
	{
		#error_log( "<p>addressbook_contactform::display(".print_r($content,true).",$addressbook,".print_r($fields,true).",$msg,$tpl_name)</p>\n");
		if (empty($tpl_name) && !empty($content['tpl_form_name'])) $tpl_name =$content['tpl_form_name'];
		$tpl = new etemplate($tpl_name ? $tpl_name : 'addressbook.contactform');
		// initializing some fields
		if (!$fields) $fields = array('org_name','n_fn','email','tel_work','url','note','captcha');
		$submitted = false;
		// check if submitted
		if (is_array($content))
		{
			if (isset($_POST['g-recaptcha-response'])) $recaptcha = sitemgr_module::verify_recaptcha ($_POST['g-recaptcha-response']);
			$captcha = (isset($content['captcha_result']) && $content['captcha'] != $content['captcha_result']) || ($recaptcha && $recaptcha->success == false);
			if ($captcha || // no correct captcha OR
				(time() - $content['start_time'] < 10 &&				// bot indicator (less then 10 sec to fill out the form and
				!$GLOBALS['egw_info']['etemplate']['java_script']))	// javascript disabled)
			{
				$submitted = "truebutfalse";
				$tpl->set_validation_error('captcha',lang('Wrong - try again ...'));
			}
			elseif ($content['submitit'])
			{
				$submitted = true;
				$contact = new Api\Contacts();
				if ($content['owner'])	// save the contact in the addressbook
				{
					$content['private'] = 0;	// in case default_private is set
					if (($id = $contact->save($content)))
					{
						// check for fileuploads and attach the found files
						foreach($content as $name => $value)
						{
							if (is_array($value) && isset($value['tmp_name']) && is_readable($value['tmp_name']))
							{
								// do no further permission check, as this would require_once
								// the anonymous user to have run rights for addressbook AND
								// edit rights for the addressbook used to store the new entry,
								// which is clearly not wanted securitywise
								Vfs::$is_root = true;
								Link::link('addressbook',$id,Link::VFS_APPNAME,$value,$name);
								Vfs::$is_root = false;
							}
						}

						return '<p align="center">'.($msg ? $msg : $content['msg']).'</p>';
					}
					else
					{
						return '<p align="center">'.lang('There was an error saving your data :-(').'<br />'.
							lang('The anonymous user has probably no add rights for this addressbook.').'</p>';
					}
				}
				else	// this is only called, if we send only email and dont save it
				{
					if ($content['email_contactform'])
					{
						$tracking = new Api\Contacts\Tracking($contact);
					}
					if ($tracking->do_notifications($contact->data2db($content),null))
					{
						return '<p align="center">'.$content['msg'].'</p>';
					}
					else
					{
						return '<p align="center">'.lang('There was an error saving your data :-(').'<br />'.
							lang('Either the configured email addresses are wrong or the mail configuration.').'</p>';
					}
				}
			}
		}
		static $contact = null;
		if (!is_array($content))
		{
			$preserv['tpl_form_name'] = $tpl_name;
			$preserv['owner'] = $addressbook;
			$preserv['msg'] = $msg;
			$preserv['is_contactform'] = true;
			$preserv['email_contactform'] = $email;
			$preserv['subject_contactform'] = $subject;
			$preserv['email_copytoreceiver'] = $copytoreceiver;
			#if (!$fields) $fields = array('org_name','n_fn','email','tel_work','url','note','captcha');
			$custom = 1;
			foreach($fields as $name)
			{
				if ($name[0] == '#')	// custom field
				{
					if (!isset($contact)) $contact = new Api\Contacts();
					$content['show']['custom'.$custom] = true;
					$content['customfield'][$custom] = $name;
					$content['customlabel'][$custom] = $contact->customfields[substr($name,1)]['label'];
					++$custom;
				}
				elseif($name == 'adr_one_locality')
				{
					if (!($content['show'][$name] = $GLOBALS['egw_info']['user']['preferences']['addressbook']['addr_format']))
					{
						$content['show'][$name] = 'postcode_city';
					}
				}
				else
				{
					$content['show'][$name] = true;
				}
			}
			$preserv['start_time'] = time();
			$content['lang'] = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		}
		elseif ($submitted == 'truebutfalse')
		{
			$preserv['tpl_form_name'] = $tpl_name;
			unset($content['submitit']);
			$custom = 1;
			// fieldnames are "defined" by the commit attempt, that way, we do not have to remember them
			foreach($content as $name => $value) {
				$preserv[$name]=$value;
				if ($name[0] == '#')     // custom field
				{
					if (!isset($contact)) $contact = new Api\Contacts();
					$content['show']['custom'.$custom] = true;
					$content['customfield'][$custom] = $name;
					$content['customlabel'][$custom] = $contact->customfields[substr($name,1)]['label'];
					++$custom;
				}
				elseif($name == 'adr_one_locality')
				{
					if (!($content['show'][$name] = $GLOBALS['egw_info']['user']['preferences']['addressbook']['addr_format']))
					{
						$content['show'][$name] = 'postcode_city';
					}
				}
				else
				{
					$content['show'][$name] = true;
				}
			}
			// reset the timestamp
			$preserv['start_time'] = time();
		}
		$content['addr_format'] = $GLOBALS['egw_info']['user']['preferences']['addressbook']['addr_format'];

		if ($addressbook) $preserv['owner'] = $addressbook;
		if ($msg) $preserv['msg'] = $msg;
		if (!sitemgr_module::get_recaptcha())
		{
			// a simple calculation captcha
			$num1 = rand(1,99);
			$num2 = rand(1,99);
			if ($num2 > $num1)	// keep the result positive
			{
				$n = $num1; $num1 = $num2; $num2 = $n;
			}
			if (in_array('captcha',$fields))
			{
				$content['captcha_task'] = sprintf('%d - %d =',$num1,$num2);
				$preserv['captcha_result'] = $num1-$num2;
			}
		}
		else
		{
			$content['show']['captcha'] = false;
			$content['show']['recaptcha'] = true;
			$recaptcha = sitemgr_module::get_recaptcha();
			$content['recaptcha'] = '<div class="g-recaptcha" data-sitekey="'.$recaptcha['site'].'"></div>';
		}
		// allow to preset variables via get parameters
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$content = array_merge($_GET, (array)$content);
		}

		return $tpl->exec($this->callback,$content,$sel_options,array(),$preserv);
	}
}