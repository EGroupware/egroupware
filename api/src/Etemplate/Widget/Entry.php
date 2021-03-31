<?php
/**
 * EGroupware eTemplate - Entry Widget
 *
 * This is the server side for a widget that loads a particular entry from an
 * application, and displays the value of a particular field
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

/**
 * eTemplate  Entry widget
 *
 * This widget can be used to fetch fields of any entry specified by its ID.
 * The entry is loaded once and shared amoung widget that need it.
 */
abstract class Entry extends Transformer
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
	protected $legacy_options = 'field,compare,alternate_fields,options'; // field, compare, alternate_fields

	/**
	 * Array with a transformation description, based on attributes to modify.
	 *
	 * @see Transformer::$transformation
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
	public function beforeSendToClient($cname, array $expand=array())
	{
		$attrs = $this->attrs;

		$attrs['type'] = $this->type;
		$attrs['id'] = $this->id;

		$form_name = self::form_name($cname, $this->id);
		$prefixed_id = (substr($this->id, 0, 1) == self::ID_PREFIX ? $this->id : self::ID_PREFIX . $this->id);
		$data_id = $attrs['value'] ? self::form_name($cname, $attrs['value']) : self::form_name($cname, $prefixed_id);

		// No need to proceed
		if(!$data_id) return;

		// Find out which record to load
		$value = self::get_array(self::$request->content, $form_name, false, true);
		if(!$value)
		{
			// Try here...  legacy / fallback / just make it work
			$value = self::get_array(self::$request->content, $data_id, true, false);
			$data = static::get_entry($value, $attrs);
		}
		else
		{
			// Get the record itself
			$data = self::get_array(self::$request->content, $data_id, true, false);
			if(!$data)
			{
				$data = static::get_entry($value, $attrs);
			}
		}

		// Set the new value so transformer can find it.  Use prefix to avoid changing the original value
		$new_value =& self::get_array(self::$request->content, $prefixed_id, true, false);
		if ($data) $new_value = $data;

		// Check for missing field
		if(!$attrs['field'] && !$attrs['alternate_fields'])
		{
			self::set_validation_error(self::ID_PREFIX . $this->id, lang('Unable to find field attribute'));
			return;
		}

		// Field is reference to customfield?
		$this->customfield($attrs, $new_value);

		$this->regex($attrs, $new_value);

		// Change this before parent so client gets what it needs
		// Client is expecting to find data with the prefix
		if(substr($this->id, 0, 1) !== self::ID_PREFIX)
		{
			$this->id = self::ID_PREFIX . $this->id . "[{$attrs['field']}]";
		}

		parent::beforeSendToClient($cname, $expand);


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
	 * @return array
	 */
	protected static function get_field_list()
	{
		return array();
	}

	/**
	 * Using ID, field and alternate fields, find the one to use
	 *
	 * @param array $attrs
	 * @param array $data
	 * @return mixed Reference into data array for value
	 */
	protected function &get_data_field($attrs, &$data)
	{
		$value =& $data;
		if(!is_array($value)) return $value;

		foreach(array($attrs['field']) + explode(':',$attrs['alternate_fields']) as $field)
		{
			if($value[$field])
			{
				return $value[$field];
			}
		}
		$ret = null;
		return $ret;
	}

	/**
	 * Handle if field is a reference to a customfield, or a sub-field if the custom
	 * field is an application entry.  Allowed forms are similar to merge:
	 *	#customfield
	 *	#customfield/n_fn
	 *  #infolog_cf/#addressbook_cf/n_fn
	 *  etc.
	 *
	 * @param array $attrs Current field attributes
	 * @param array $data Current entry data
	 */
	protected function customfield($attrs, &$data)
	{
		list($app, $type) = explode('-',$attrs['type']);
		$data_id = $attrs['value'] ?: $attrs['id'];
		$id = is_array($data) ? static::get_array($data, $data_id) : $data;
		if(!$app || !$type || !$GLOBALS['egw_info']['apps'][$app] || !$id ||
			// Simple CF, already there
			$data[$attrs['field']]
		)
		{
			return;
		}

		if(substr($attrs['field'], 0, 1) == '#' && $cfs = \EGroupware\Api\Storage\Customfields::get($app))
		{
			try
			{
				$classname = "{$app}_merge";
				$merge = new $classname();
				$replacement_field = '$$'.$attrs['field'].'$$';
				$replacements = $merge->get_app_replacements($app, $id, $replacement_field);
				$data[$attrs['field']] = $replacements[$replacement_field];
			}
			catch(\Exception $e)
			{
				$this->set_validation_error($this->id, $e->getMessage());
			}
		}
	}

	/**
	 * Handle regex attribute that allows modifying the value via regex replace
	 *
	 * @param array $attrs Current field attributes
	 * @param array $data Current entry data
	 */
	protected function regex($attrs, &$data)
	{
		$value =& $this->get_data_field($attrs, $data);
		if(!$attrs['regex'] || !$value)
		{
			return;
		}
		$regex = $attrs['regex'];
		$replace = $attrs['regex_replace'];
		if(!$replace)
		{
			$regex = explode(',', $attrs['regex']);
			$replace = array_pop($regex);
			$regex = implode(',', $regex);
		}
		$data[$attrs['field']] = preg_replace($regex, $replace, $value);
	}
}