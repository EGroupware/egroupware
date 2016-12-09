<?php
/**
 * Addressbook - Sitemgr contact form
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-15 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * SiteMgr contact form for the addressbook
 *
 */
class module_addressbook_contactform extends sitemgr_module
{
	/**
	 * Constructor
	 *
	 * @return module_addressbook_contactform
	 */
	function __construct()
	{
		$this->i18n = true;
		$this->arguments = array();	// get's set in get_user_interface
		$this->title = lang('Contactform');
		$this->description = lang('This module displays a contactform, that stores direct into the addressbook.');

		$this->etemplate_method = 'addressbook.addressbook_contactform.display';
	}

	function get_content (&$arguments,$properties)
	{
		$parent = parent::get_content($arguments, $properties);

		//Make sure that recaptcha keys are set before include it
		if (($recaptcha = sitemgr_module::get_recaptcha()))
		{
			$extra .= '<script src="https://www.google.com/recaptcha/api.js" type="text/javascript"></script>'."\n";
			return $extra.$parent;
		}
		// fallback to basic captcha
		return $parent;
	}

	/**
	 * Reimplemented to add the addressbook translations and fetch the addressbooks only if needed for the user-interface
	 *
	 * @return array
	 */
	function get_user_interface()
	{
		Api\Translation::add_app('addressbook');

		$uicontacts = new addressbook_ui();

		$default = $fields = array(
			'org_name'             => lang('Company'),
			'org_unit'             => lang('Department'),
			'n_fn'                 => lang('Prefix').', '.lang('Firstname').' + '.lang('Lastname'),
			'sep1'                 => '----------------------------',
			'email'                => lang('email'),
			'tel_work'             => lang('work phone'),
			'tel_cell'             => lang('mobile phone'),
			'tel_fax'              => lang('fax'),
			'tel_home'             => lang('home phone'),
			'url'                  => lang('url'),
			'sep2'                 => '----------------------------',
			'adr_one_street'       => lang('street'),
			'adr_one_street2'      => lang('address line 2'),
			'adr_one_locality'     => lang('city').' + '.lang('zip code'),
			'sep3'                 => '----------------------------',
		);
		foreach($uicontacts->customfields as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}
		$fields += array(
			'sep4'                 => '----------------------------',
			'note'                 => lang('message'),
			'sep5'                 => '----------------------------',
			'captcha'              => lang('Verification'),
		);
		$this->i18n = True;
		$this->arguments = array(
			'arg1' => array(
				'type' => 'select',
				'label' => lang('Addressbook the contact should be saved to').' ('.lang('The anonymous user needs add rights for it!').')',
				'options' => array(
					'' => lang('None'),
				)+$uicontacts->get_addressbooks(Acl::ADD)	// add to not show the accounts!
			),
			'arg4' => array(
				'type' => 'textfield',
				'label' => lang('Email addresses (comma separated) to send the contact data'),
				'params' => array('size' => 80),
			),
			'arg6' => array(
				'type' => 'textfield',
				'label' => lang('Subject for email'),
				'params' => array('size' => 80),
				'default' => lang('Contactform'),
				'i18n' => True,
			),
			'arg2' => array(
				'type' => 'select',
				'label' => lang('Contact fields to show'),
				'multiple' => true,
				'options' => $fields,
				'default' => $default,
				'params' => array('size' => 9),
			),
			'arg3' => array(
				'type' => 'textfield',
				'label' => lang('Message after submitting the form'),
				'params' => array('size' => 80),
				'default' => lang('Thank you for contacting us.'),
				'i18n' => True,
			),
			'arg5' => array(
				'type' => 'textfield',
				'label' => lang('Custom eTemplate for the contactform'),
				'params' => array('size' => 40),
				'default' => 'addressbook.contactform',
			),
			'arg7' => array(
				'type' => 'checkbox',
				'label' => lang('Send emailcopy to receiver'),
				'params' => array('size' => 1),
			),
		);
		return parent::get_user_interface();
	}
}
