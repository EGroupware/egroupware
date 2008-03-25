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
		'contact-value'    => 'Contact',
		'contact-account'  => 'Account contactdata',
		'contact-template' => 'Account template',
		'contact-fields'   => 'Contact fields',
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
		//echo "<p>contact_widget::pre_process('$name','$value',".print_r($cell,true).",...)</p>\n";
		switch($type = $cell['type'])
		{
			case 'contact-fields':
				$GLOBALS['egw']->translation->add_app('addressbook');
				$this->contacts->contacts();
				$cell['sel_options'] = $this->contacts->contact_fields;
				foreach($this->contacts->customfields as $name => $data)
				{
					$cell['sel_options']['#'.$name] = $data['label'];
				}
				$cell['type'] = 'select';
				$cell['no_lang'] = 1;
				$cell['size'] = 'None';
				break;

			case 'contact-account':
			case 'contact-template':
				if (substr($value,0,8) != 'account:')
				{
					$value = 'account:'.($cell['name'] != 'account:' ? $value : $GLOBALS['egw_info']['user']['account_id']);
				}
				// fall-throught
			case 'contact-value':
			default:
				if (substr($value,0,12) == 'addressbook:') $value = substr($value,12);	// link-entry syntax
				if (!$value || !$cell['size'] || (!is_array($this->contact) || 
					!($this->contact['id'] == $value || 'account:'.$this->contact['account_id'] == $value)) &&
					!($this->contact = $this->contacts->read($value)))
				{
					$cell = $tmpl->empty_cell();
					$value = '';
					break;
				}
				$type = $cell['size'];
				$cell['size'] = '';
				
				if ($cell['type'] == 'contact-template')
				{
					$name = $this->contact[$type];
					$cell['type'] = 'template';
					if (($prefix = $cell['label'])) $name = strpos($prefix,'%s') !== false ? str_replace('%s',$name,$prefix) : $prefix.$name;
					$cell['obj'] = new etemplate($name,$tmpl->as_array());
					return false;
				}
				$value = $this->contact[$type];
				$cell['no_lang'] = 1;
				$cell['readonly'] = true;
				
				switch($type)
				{
					// ToDo: pseudo types like address-label
					
					case 'bday':
						$cell['type'] = 'date';
						$cell['size'] = 'Y-m-d';
						break;
						
					case 'owner':
					case 'modifier':
					case 'creator':
						$cell['type'] = 'select-account';
						break;
					
					case 'modified':
					case 'created':
						$cell['type'] = 'date-time';
						break;
						
					case 'cat_id':
						$cell['type'] = 'select-cat';
						break;

					default:
						$cell['type'] = 'label';
						break;
				}
				break;
		}
		$cell['id'] = ($cell['id'] ? $cell['id'] : $cell['name'])."[$type]";

		return True;	// extra label ok
	}
}
