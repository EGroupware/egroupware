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
		$this->arguments = array();	// get's set in get_user_interface
		$this->title = lang('Contactform');
		$this->description = lang('This module displays a contactform, that stores direct into the addressbook.');

		$this->etemplate_method = 'addressbook.addressbook_contactform.display';
	}

	/**
	 * Reimplemented to add the addressbook translations and fetch the addressbooks only if needed for the user-interface
	 *
	 * @return array
	 */
	function get_user_interface()
	{
		$GLOBALS['egw']->translation->add_app('addressbook');

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
				)+$uicontacts->get_addressbooks(EGW_ACL_ADD)	// add to not show the accounts!
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
