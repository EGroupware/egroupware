<?php
/**
 * eGroupWare  eTemplate Extension - Contact Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eTemplate Extension: Contact widget
 *
 * This widget can be used to fetch fields of a contact specified by contact-id 
 */
class contact_widget
{
	/** 
	 * exported methods of this class
	 * 
	 * @var array $public_functions
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 *
	 * @var string/array $human_name
	 */
	var $human_name = array(
		'contact-value'  => 'Contact',
		'contact-fields' => 'Contact fields',
	);
	/**
	 * Instance of the contacts class
	 * 
	 * @var contacts
	 */
	var $contacts;
	/**
	 * Cached contact
	 *
	 * @var array
	 */
	var $contact;
	
	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function contact_widget($ui)
	{
		$this->ui = $ui;
		if (!is_object($GLOBALS['egw']->contacts))
		{
			$GLOBALS['egw']->contacts =& CreateObject('phpgwapi.contacts');
		}
		$this->contacts =& $GLOBALS['egw']->contacts;
	}

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
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		switch($cell['type'])
		{
			case 'contact-fields':
				$GLOBALS['egw']->translation->add_app('addressbook');
				$this->contacts->contacts();
				$cell['sel_options'] = $this->contacts->contact_fields;
				$cell['type'] = 'select';
				$cell['no_lang'] = 1;
				break;

			case 'contact-value':
			default:
				if (substr($value,0,12) == 'addressbook:') $value = substr($value,12);	// link-entry syntax
				if (!$value || !$cell['size'] || (!is_array($this->contact) || $this->contact['id'] != $value) &&
					!($this->contact = $this->contacts->read($value)))
				{
					$cell = $tmpl->empty_cell();
					$value = '';
					break;
				}
				$value = $this->contact[$cell['size']];
				$cell['size'] = '';
				$cell['no_lang'] = 1;
				$cell['readonly'] = true;
				
				switch($cell['size'])
				{
					// ToDo: pseudo types like address-label

					default:
						$cell['type'] = 'label';
						break;
				}
				break;
		}
		return True;	// extra label ok
	}
}
