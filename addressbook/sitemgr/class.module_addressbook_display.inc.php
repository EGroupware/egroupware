<?php
/**
 * Addressbook - Sitemgr contact form
 *
 * @link http://www.egroupware.org
 * @author stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2008 by stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.module_addressbook_display.inc.php 24028 2008-02-18 09:04:36Z stefanbecker $
 */

/**
 * SiteMgr contact form for the addressbook
 */
class module_addressbook_display extends sitemgr_module
{
	/**
	 * Constructor
	 *
	 * @return module_addressbook_showcontactblock
	 */
	function __construct()
	{
		$this->arguments = array();	// get's set in get_user_interface
		$this->title = lang('Display Contact');
		$this->description = lang('This module displays Block from a Adddressbook Group.');

		$this->etemplate_method = 'addressbook.addressbook_display.display';
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
			'country'               => lang('country'),
		);
		foreach($uicontacts->customfields as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}

		$this->arguments = array(
			'arg1' => array(
				'type' => 'select',
				'label' => lang('Addressbook the contact should be shown').' ('.lang('The anonymous user needs read it!').')',
				'options' => array(
					'' => lang('All'),
				)+$uicontacts->get_addressbooks(EGW_ACL_ADD)	// add to not show the accounts!
			),
			'arg2' => array(
				'type' => 'select',
				'label' => lang('Contact fields to show'),
				'multiple' => true,
				'options' => $fields,
				'default' => $default,
				'params' => array('size' => 9),
			),
			'arg5' => array(
				'type' => 'textfield',
				'label' => lang('Custom eTemplate for the contactform'),
				'params' => array('size' => 40),
				'default' => 'addressbook.display',
			),
		);
		return parent::get_user_interface();
	}
}
