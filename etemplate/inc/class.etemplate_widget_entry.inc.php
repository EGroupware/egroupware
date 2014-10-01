<?php
/**
 * EGroupware  eTemplate Extension - Entry Widget
 *
 * This is the server side for a widget that loads a particular entry from an
 * application, and displays the value of a particular field
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id: class.contact_widget.inc.php 46844 2014-05-07 09:00:59Z ralfbecker $
 */

/**
 * eTemplate Extension: Entry widget
 *
 * This widget can be used to fetch fields of any entry specified by its ID.
 * The entry is loaded once and shared amoung widget that need it.
 */
abstract class etemplate_widget_entry extends etemplate_widget_transformer
{

	/**
	 * @var $prefix string Prefix for the ID to avoid conflicts between the
	 * record and the original value
	 */
	const ID_PREFIX = '~';

	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = 'field';

	/**
	 * Array with a transformation description, based on attributes to modify.
	 *
	 * @see etemplate_widget_transformer::$transformation
	 *
	 * @var array
	 */
	protected static $transformation = array(
		'type' => array(
			'entry-fields' => array(	// List of fields
				'sel_options' => array('__callback__' => 'get_field_list'),
				'type' => 'select',
				'no_lang' => true,
				'options' => 'None',
			),
		)
	);

	/**
	 * Load entry
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$attrs = $this->attrs;

		$attrs['type'] = $this->type;
		$attrs['id'] = $this->id;
		
		$form_name = self::form_name($cname, $this->id);
		$data_id = $attrs['value'] ? self::form_name($cname, $attrs['value']) : self::form_name($cname, self::ID_PREFIX . $this->id);
		
		// No need to proceed
		if(!$data_id) return;

		// Find out which record to load
		$value = self::get_array(self::$request->content, $form_name, false, true);

		// Get the record itself
		$data =& self::get_array(self::$request->content, $data_id, true, false);
		if(!$data)
		{
			$data =& static::get_entry($value, $attrs);
		}
		$this->id = self::ID_PREFIX . $this->id . "[{$attrs['field']}]";

		$old_type = self::getElementAttribute($this->id, 'type');

		parent::beforeSendToClient($cname);

		// Check for conflict - more than one with same id/field and different type
		if($old_type && $old_type != $this->type)
		{
			//self::set_validation_error($this->id, lang('%1, duplicate ID', $this));
		}
	}

	/**
	 * Get entry data
	 *
	 * @param int|string|array $value
	 * @param array $attrs
	 * @return array
	 */
	abstract function get_entry($value, array $attrs);

	/**
	 * Get a list of fields available for display
	 *
	 * @return Array
	 */
	protected static function get_field_list()
	{
		return array();
	}
}