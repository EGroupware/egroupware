<?php
/**
 * Addressbook - Sitemgr contact form
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * SiteMgr contact form for the addressbook
 *
 */
class addressbook_contactform
{
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
	function display($content=null,$addressbook=null,$fields=null,$msg=null,$email=null,$tpl_name=null,$subject=null,$copytoreceiver=false)
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
			if ((isset($content['captcha_result']) && $content['captcha'] != $content['captcha_result']) ||	// no correct captcha OR
				(time() - $content['start_time'] < 10 &&				// bot indicator (less then 10 sec to fill out the form and
				!$GLOBALS['egw_info']['etemplate']['java_script']))	// javascript disabled)
			{
				$submitted = "truebutfalse";
				$tpl->set_validation_error('captcha',lang('Wrong - try again ...'));
			}
			elseif ($content['submitit'])
			{
				$submitted = true;
				$contact = new addressbook_bo();
				if ($content['owner'])	// save the contact in the addressbook
				{
					if ($content['email_contactform'])	// only necessary as long addressbook is not doing this itself
					{
						$tracking = new addressbook_tracking($contact);
					}
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
								egw_vfs::$is_root = true;
								egw_link::link('addressbook',$id,egw_link::VFS_APPNAME,$value,$name);
								egw_vfs::$is_root = false;
							}
						}
						unset($content['modified']); unset($content['modifier']);	// not interesting for new entries

						$tracking->do_notifications($content,null);	// only necessary as long addressbook is not doing this itself

						return '<p align="center">'.$content['msg'].'</p>';
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
						require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.addressbook_tracking.inc.php');
						$tracking = new addressbook_tracking($contact);
					}
					if ($tracking->do_notifications($content,null))
					{
						return '<p align="center">'.$content['msg'].'</p>';
					}
					else
					{
						return '<p align="center">'.lang('There was an error saving your data :-(').'<br />'.
							lang('Either the configured email addesses are wrong or the mail configuration.').'</p>';
					}
				}
			}
		}
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
				if ($name{0} == '#')	// custom field
				{
					static $contact;
					if (is_null($contact))
					{
						$contact = new addressbook_bo();
					}
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
		}
		if (is_array($content) && $submitted == 'truebutfalse') {
			$preserv['tpl_form_name'] = $tpl_name;
			unset($content['submitit']);
			$custom = 1;
			// fieldnames are "defined" by the commit attempt, that way, we do not have to remember them
			foreach($content as $name => $value) {
				$preserv[$name]=$value;
				if ($name{0} == '#')     // custom field
				{
					static $contact;
					if (is_null($contact)) $contact = new addressbook_bo();
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
		return $tpl->exec('addressbook.addressbook_contactform.display',$content,$sel_options,$readonlys,$preserv);
	}
}
