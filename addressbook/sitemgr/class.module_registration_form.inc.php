<?php
/**
 * Registration - Sitemgr registration form
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package registration
 * @copyright (c) 2010 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * SiteMgr registration form
 */
class module_registration_form extends sitemgr_module
{
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		$this->i18n = true;
		$this->arguments = array();	// get's set in get_user_interface
		$this->title = lang('Registration');
		$this->description = lang('This module displays a registration form, and sends a confirmation email.');

		$this->etemplate_method = 'registration.registration_sitemgr.display';
	}

	/**
	 * Reimplemented to add the registration translations 
	 *
	 * @return array
	 */
	function get_user_interface()
	{
		$GLOBALS['egw']->translation->add_app('registration');

		$uicontacts = new addressbook_ui();

		$default = $fields = array(
			'org_name'             => lang('Company'),
			'org_unit'             => lang('Department'),
			'n_fn'                 => lang('Prefix').', '.lang('Firstname').' + '.lang('Lastname'),
			'sep1'                 => '----------------------------',
		//	'email'                => lang('email'), // Required, so don't even make it optional
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

		$register_for = registration_bo::register_apps_list();
		// Only real (not sitemgr) admins can select account
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			unset($register_for['admin']);
		}
		$this->arguments = array(
			'register_for' => array(
				'type' => 'select',
				'label'	=> 'What are they registering for',
				'options'=> $register_for
			),
			'pending_addressbook' => array(
				'type' => 'select',
				'label' => lang('Addressbook the contacts should be saved to before they are confirmed.').' ('.lang('The anonymous user needs add rights for it!').')',
				'options' => array(
					'' => lang('None'),
				)+registration_bo::get_allowed_addressbooks(registration_bo::PENDING)
			),
			'confirmed_addressbook' => array(
				'type' => 'select',
				'label' => lang('Confirmed addressbook.').' ('.lang('The anonymous user needs add rights for it!').')',
				'options' => array(
					'' => lang('None'),
				)+registration_bo::get_allowed_addressbooks(registration_bo::CONFIRMED)
			),
			'expiry' => array(
				'type' => 'textfield',
				'label' => lang('How long to confirm before registration expires? (hours)'),
				'params' => array('size' => 5),
				'default' => 2,
			),
			'fields' => array(
				'type' => 'select',
				'label' => lang('Contact fields to show'),
				'multiple' => true,
				'options' => $fields,
				'default' => $default,
				'params' => array('size' => 9),
			),
			'etemplate' => array(
				'type' => 'textfield',
				'label' => lang('Custom eTemplate for the contactform'),
				'params' => array('size' => 40),
				'default' => 'registration.registration_form',
			),
		);
		return parent::get_user_interface();
	}
        function get_content(&$arguments, $properties) {
		$arguments['link'] = $this->link();
		$args['arg1'] = $this->block;
		$args['arg2'] = $properties;
		return parent::get_content($args, $properties);
        }
}
