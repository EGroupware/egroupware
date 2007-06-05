<?php
/**
 * Addressbook - Sitemgr contact form
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

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
	 * @param int $addressbook
	 * @param array $fields
	 * @param string $msg
	 * @return string html content
	 */
	function display($content=null,$addressbook=null,$fields=null,$msg=null)
	{
		//echo "<p>addressbook_contactform::display($content,$addressbook,".print_r($fields,true).",$msg)</p>\n";
		$tpl = new etemplate('addressbook.contactform');

		if (is_array($content))
		{
			if ($content['captcha'] != $content['captcha_result'])
			{
				$tpl->set_validation_error('captcha',lang('Wrong - try again ...'));
			}
			elseif ($content['submitit'])
			{
				if ($content['owner'])	// save the contact in the addressbook
				{
					$content['addressbook'] = $addressbook;
					require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.bocontacts.inc.php');
					$contact = new bocontacts();
					if ($contact->save($content))
					{
						return '<p align="center">'.$content['msg'].'</p>';
					}
					else
					{
						return '<p align="center">'.lang('There was an error saving your data :-(').'<br />'.
							lang('The anonymous user has probably no add rights for this addressbook.').'</p>';
					}
				}
				else	// todo email
				{
					return 'email not yet implemented!';
				}
			}
		}
		else
		{
			$preserv['owner'] = $addressbook;
			$preserv['msg'] = $msg;
			if (!$fields) $fields = array('org_name','n_fn','email','tel_work','url','note','captcha');
			$custom = 1;
			foreach($fields as $name)
			{
				if ($name{0} == '#')	// custom field
				{
					static $contact;
					if (is_null($contact))
					{
						require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.bocontacts.inc.php');
						$contact = new bocontacts();
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
		$content['captcha_task'] = sprintf('%d - %d =',$num1,$num2);
		$preserv['captcha_result'] = $num1-$num2;
		
		return $tpl->exec('addressbook.addressbook_contactform.display',$content,$sel_options,$readonlys,$preserv);
	}
}
