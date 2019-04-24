<?php
/**
 * EGroupware  eTemplate Extension - Contact Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;

/**
 * eTemplate Extension: Contact widget
 *
 * This widget can be used to fetch fields of a contact specified by contact-id
 */
class Contact extends Entry
{
	/**
	 * Instance of the contacts class
	 *
	 * @var Api\Contacts
	 */
	protected $contacts;

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
	 *
	 * @param string $template
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
		Api\Translation::add_app('addressbook');

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
				// fall-through
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

		if($contact && !$contact['n_fn'])
		{
			$this->contacts->fixup_contact($contact);
		}

		//error_log(__METHOD__."('$value') returning ".array2string($contact));
		return $contact;
	}
}
