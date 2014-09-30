<?php
/**
 * EGroupware  eTemplate Extension - Contact Widget
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
class contact_widget extends etemplate_widget_entry
{
	/**
	 * exported methods of this class
	 *
	 * @var array $public_functions
	 * @deprecated only used for old etemplate
	 */
	public $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 *
	 * @var string|array $human_name
	 * @deprecated only used for old etemplate
	 */
	public $human_name = array(
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
	private $contacts;

	/**
	 * Array with a transformation description, based on attributes to modify.
	 *
	 * Exampels:
	 *
	 * * 'type' => array('some' => 'other')
	 *   if 'type' attribute equals 'some' replace it with 'other'
	 *
	 * * 'type' => array('some' => array('type' => 'other', 'options' => 'otheroption')
	 *   same as above, but additonally set 'options' attr to 'otheroption'
	 *
	 * --> leaf element is the action, if previous filters are matched:
	 *     - if leaf is scalar, it just replaces the previous filter value
	 *     - if leaf is an array, it contains assignments for (multiple) attributes: attr => value pairs
	 *
	 * * 'type' => array(
	 *      'some' => array(...),
	 *      'other' => array(...),
	 *      '__default__' => array(...),
	 *   )
	 *   it's possible to have a list of filters with actions to run, plus a '__default__' which matches all not explicitly named values
	 *
	 * * 'value' => array('__callback__' => 'app.class.method' || 'class::method' || 'method')
	 *   run value through a *serverside* callback, eg. reading an entry based on it's given id
	 *
	 * * 'value' => array('__js__' => 'function(value) { return value+5; }')
	 *   run value through a *clientside* callback running in the context of the widget
	 *
	 * * 'name' => '@name[@options]'
	 *   replace value of 'name' attribute with itself (@name) plus value of options in square brackets
	 *
	 * --> attribute name prefixed with @ sign means value of given attribute
	 *
	 * @var array
	 */
	protected static $transformation = array(
		'type' => array(
			'contact-fields' => array(	// contact-fields widget
				'sel_options' => array('__callback__' => 'get_contact_fields'),
				'type' => 'select',
				'no_lang' => true,
				'options' => 'None',
			),
			'contact-template' => array(
				'type' => 'template',
				'options' => '',
				'template' => array('__callback__' => 'parse_template'),
			),
			'__default__' => array(
				'options' => array(
					'bday' => array('type' => 'date', 'options' => 'Y-m-d'),
					'owner' => array('type' => 'select-account', 'options' => ''),
					'modifier' => array('type' => 'select-account', 'options' => ''),
					'creator' => array('type' => 'select-account', 'options' => ''),
					'modifed' => array('type' => 'date-time', 'options' => ''),
					'created' => array('type' => 'date-time', 'options' => ''),
					'cat_id' => array('type' => 'select-cat', 'options' => ''),
					'__default__' => array('type' => 'label', 'options' => ''),
				),
				'no_lang' => 1,
			),
		),
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $xml or 'html' for old etemplate
	 */
	public function __construct($xml)
	{
		if (is_a($xml, 'XMLReader') || $xml != '' && $xml != 'html')
		{
			parent::__construct($xml);
		}
		$this->contacts = $GLOBALS['egw']->contacts;
	}

	/**
	 * Legacy support for putting the template name in 'label' param
	 * @param string $label
	 * @param array $attrs
	 */
	public function parse_template($template, &$attrs)
	{
		return sprintf($template ? $template : $attrs['label'], $attrs['value']);
	}

	/**
	 * Get all contact-fields
	 *
	 * @return array
	 */
	public function get_contact_fields()
	{
		translation::add_app('addressbook');
		$this->contacts->__construct();
		$options = $this->contacts->contact_fields;
		foreach($this->contacts->customfields as $name => $data)
		{
			$options['#'.$name] = $data['label'];
		}
		return $options;
	}

	public function get_entry($value, array $attrs)
	{
		return $this->get_contact($value, $attrs);
	}
	/**
	 * Get contact data, if $value not already contains them
	 *
	 * @param int|string|array $value
	 * @param array $attrs
	 * @return array
	 */
	public function get_contact($value, array $attrs)
	{
		$field = $attrs['field'] ? $attrs['field'] : '';
		if (is_array($value) && !(array_key_exists('app',$value) && array_key_exists('id', $value))) return $value;

		if(is_array($value) && array_key_exists('app', $value) && array_key_exists('id', $value)) $value = $value['id'];
		switch($attrs['type'])
		{
			case 'contact-account':
			case 'contact-template':
				if (substr($value,0,8) != 'account:')
				{
					$value = 'account:'.($attrs['name'] != 'account:' ? $value : $GLOBALS['egw_info']['user']['account_id']);
				}
				// fall-throught
			case 'contact-value':
			default:
				if (substr($value,0,12) == 'addressbook:') $value = substr($value,12);	// link-entry syntax
				if (!($contact = $this->contacts->read($value)))
				{
					$contact = array();
				}
				break;
		}
		unset($contact['jpegphoto']);	// makes no sense to return binary image

		//error_log(__METHOD__."('$value') returning ".array2string($contact));
		return $contact;
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
				$cell['sel_options'] = $this->get_contact_fields();
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
				echo "<p>$name: $value</p>\n";
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
// register widgets for etemplate2
etemplate_widget::registerWidget('contact_widget',array('contact-value', 'contact-account', 'contact-template', 'contact-fields'));
