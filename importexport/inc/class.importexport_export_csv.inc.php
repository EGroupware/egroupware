<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Acl;

/**
 * class export_csv
 * This an record exporter.
 * An record is e.g. a single address or or single event.
 * No mater where the records come from, at the end export_entry
 * stores it into the stream
 */
class importexport_export_csv implements importexport_iface_export_record
{
	/**
	 * @var array array with field mapping in form egw_field_name => exported_field_name
	 */
	protected  $mapping = array();

	/**
	 * @var array array with conversions to be done in form: egw_field_name => conversion_string
	 */
	protected  $conversion = array();

	/**
	 * @var Current record being processed
	 */
	public $record;

	/**
	 * @var array holding the current record
	 */
	protected $record_array = array();

	/**
	 * @var translation holds (charset) Api\Translation object
	 */
	protected $translation;

	/**
	 * @var string charset of csv file
	 */
	protected $csv_charset;

	/**
	 * @var int holds number of exported records
	 */
	protected $num_of_records = 0;

	/**
	 * @var int holds max. number of records allowed to be exported
	 */
	public $export_limit = 0;

	/**
	 * @var stream stream resource of csv file
	 */
	protected  $handle;

	/**
	 * @var array csv specific options
	 */
	protected $csv_options = array(
		'delimiter' => ';',
		'enclosure' => '"',
	);

	/**
	 * List of fields for data conversion
	 */
	public static $types = array(
		'select-account' => array('creator', 'modifier'),
		'date-time'	=> array('modified', 'created'),
		'date'		=> array(),
		'select-cat'	=> array('cat_id')
	);

	/**
	 * Cache of parsed custom field parameters
	 */
	protected static $cf_parse_cache = array();

	/**
	 * constructor
	 *
	 * @param stram $_stream resource where records are exported to.
	 * @param array _options options for specific backends
	 * @return bool
	 */
	public function __construct( $_stream, array $_options ) {
		if (!is_object($GLOBALS['egw']->translation)) {
			$GLOBALS['egw']->translation = new Api\Translation();
		}
		$this->translation = &$GLOBALS['egw']->translation;
		$this->handle = $_stream;
		if($_options['charset'] == 'user') $_options['charset'] = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		$this->csv_charset = $_options['charset'] ? $_options['charset'] : 'utf-8';
		if ( !empty( $_options ) ) {
			$this->csv_options = array_merge( $this->csv_options, $_options );
		}
		//error_log(__METHOD__.__LINE__.array2string($_options['appname']));
		if(!Api\Storage\Merge::is_export_limit_excepted()) {
			$this->export_limit = Api\Storage\Merge::getExportLimit($_options['appname']);
			//error_log(__METHOD__.__LINE__.' app:'.$_options['appname'].' limit:'.$this->export_limit);
			if($this->export_limit == 'no') throw new Api\Exception\NoPermission\Admin('Export disabled');
		}
	}

	/**
	 * sets field mapping
	 *
	 * @param array $_mapping egw_field_name => csv_field_name
	 */
	public function set_mapping( array &$_mapping) {
		if ($this->num_of_records > 0) {
			throw new Exception('Error: Field mapping can\'t be set during ongoing export!');
		}
		if($_mapping['all_custom_fields']) {
			// Field value is the appname, so we can pull the fields
			$custom = Api\Storage\Customfields::get($_mapping['all_custom_fields']);
			unset($_mapping['all_custom_fields']);
			foreach($custom as $field => $info) {
				$_mapping['#'.$field] = $this->csv_options['begin_with_fieldnames'] == 'label' ? $info['label'] : $field;
			}
		}
		$this->mapping = $_mapping;
	}

	/**
	 * Sets conversion.
	 * @see importexport_helper_functions::conversion.
	 *
	 * @param array $_conversion
	 */
	public function set_conversion( array $_conversion) {
		$this->conversion = $_conversion;
	}

	/**
	 * exports a record into resource of handle
	 *
	 * @param importexport_iface_egw_record record
	 * @return bool
	 */
	public function export_record( importexport_iface_egw_record $_record ) {
		$this->record = $_record;
		$this->record_array = $_record->get_record_array();

		// begin with fieldnames ?
		if ($this->num_of_records == 0 && $this->csv_options['begin_with_fieldnames'] ) {
			if($this->csv_options['begin_with_fieldnames'] == 'label') {
				// Load translations for app
				list($appname, $part2) = explode('_', get_class($_record));
				if(!$GLOBALS['egw_info']['apps'][$appname]) $appname .= $part2; // Handle apps with _ in the name

				// Get translations from wizard, if possible
				if(!$this->csv_options['no_header_translation'])
				{
					$backtrace = debug_backtrace();
					$plugin = $backtrace[1]['class'];
					$wizard_name = $appname . '_wizard_' . str_replace($appname . '_', '', $plugin);
					try {
						$wizard = new $wizard_name;
						$fields = $wizard->get_export_fields();
						foreach($this->mapping as $field => &$label)
						{
							if($fields[$field])
							{
								$label = $label != $fields[$field] ? $fields[$field] : lang($label);
							}
							// Make sure no *
							if(substr($label,-1) == '*') $label = substr($label,0,-1);
						}
					} catch (Exception $e) {
						Api\Translation::add_app($appname);
						foreach($this->mapping as $field => &$label) {
							$label = lang($label);
						}
					}
				}
			}
			$mapping = ! empty( $this->mapping ) ? $this->mapping : array_keys ( $this->record_array );
			self::fputcsv( $this->handle ,$mapping ,$this->csv_options['delimiter'], $this->csv_options['enclosure'] );
		}

		// Check for limit
		if($this->export_limit && $this->num_of_records >= $this->export_limit) {
			return;
		}

		// do conversions
		if ( !empty( $this->conversion )) {
			$this->record_array = importexport_helper_functions::conversion( $this->record_array, $this->conversion );
		}

		// do fieldmapping
		if ( !empty( $this->mapping ) ) {
			$record_data = $this->record_array;
			$this->record_array = array();
			foreach ($this->mapping as $egw_field => $csv_field) {
				$this->record_array[$csv_field] = $record_data[$egw_field];
			}
		}

		self::fputcsv( $this->handle, $this->record_array, $this->csv_options['delimiter'], $this->csv_options['enclosure'] );
		$this->num_of_records++;
	}

	/**
	 * Retruns total number of exported records.
	 *
	 * @return int
	 */
	public function get_num_of_records() {
		return $this->num_of_records;
	}

	/**
	 * Parse custom fields for an app, so a more human friendly value can be exported
	 *
	 * @param importexport_iface_egw_record|Array $record
	 * @param appname Name of the app to fetch the custom fields for
	 * @param array $selects
	 * @param array $links
	 * @param array $methods
	 * @return Array of fields to be added to list of fields needing conversion
	 */
	public static function convert_parse_custom_fields(&$record, $appname, &$selects = array(), &$links = array(), &$methods = array()) {
		if(!$appname) return;

		$fields = array();
		$custom = Api\Storage\Customfields::get($appname);
		foreach($custom as $name => $c_field) {
			$name = '#' . $name;
			switch($c_field['type']) {
				case 'float' :
					$fields['float'][] = $name;
					break;
				case 'date':
				case 'date-time':
					if (!empty($c_field['values']['format']))
					{
						// Date has custom format.  Convert so it's standard, don't do normal processing
						$type = $c_field['type'];
						$format = $c_field['values']['format'];
						$methods[$name] = function($val) use ($type, $format)
						{
							$date = Api\DateTime::createFromFormat($format, $val, Api\DateTime::$user_timezone);
							if($date)
							{
								return Api\DateTime::to($date, $type == 'date' ? true : '');
							}
						};
					}
					else
					{
						// Process as normal
						$fields[$c_field['type']][] = $name;
					}
					break;
				case 'select-account':
					$fields['select-account'][] = $name;
					break;
				case 'ajax_select':
					if($c_field['values']['get_title']) {
						$methods[$name] = $c_field['values']['get_title'];
						break;
					}
					// Fall through for other settings
				case 'select':
					if (!empty($c_field['values']) && count($c_field['values']) == 1 && isset($c_field['values']['@']))
					{
						$c_field['values'] = Api\Storage\Customfields::get_options_from_file($c_field['values']['@']);
					}
					$fields['select'][] = $name;
					$selects[$name] = $c_field['values'];
					break;
				default:
					list($type) = explode('-',$c_field['type'],2);
					if (in_array($type, array_keys($GLOBALS['egw_info']['apps'] ?? []))) {
						$fields['links'][] = $name;
						$links[$name] = $c_field['type'];
					}
					break;
			}
		}
		return $fields;
	}

	/**
	 * Convert system info into a format with a little more transferable meaning
	 *
	 * Uses the static variable $types to convert various datatypes.
	 *
	 * @param importexport_iface_egw_record $record Record to be converted
	 * @param string[] $fields List of field types => field names to be converted
	 * @param string $appname Current appname if you want to do custom fields too
	 * @param array[] $selects Select box options
	 */
	public static function convert(importexport_iface_egw_record &$record, Array $fields = array(), $appname = null, $selects = array())
	{
		if($appname)
		{
			if(empty(self::$cf_parse_cache[$appname]))
			{
				$c_fields = self::convert_parse_custom_fields($record, $appname, $selects, $links, $methods);
				self::$cf_parse_cache[$appname] = array($c_fields, $selects, $links, $methods);
			}
			list($c_fields, $c_selects, $links, $methods) = self::$cf_parse_cache[$appname];

			// Add in any fields that are keys to another app
			foreach($fields['links'] ?? [] as $link_field => $app)
			{
				if(is_numeric($link_field)) continue;
				$links[$link_field] = $app;
				// Set it as a normal link field
				$fields['links'][] = $link_field;
				unset($fields['links'][$link_field]);
			}

			// Not quite a recursive merge, since only one level
			foreach($fields as $type => &$list)
			{
				if (!empty($c_fields[$type]))
				{
					$list = array_merge($c_fields[$type], $list);
					unset($c_fields[$type]);
				}
			}
			$fields += $c_fields;
			$selects += $c_selects;
		}
		foreach($fields['select'] ?? [] as $name)
		{
			if($record->$name != null && is_array($selects) && !empty($selects[$name]))
			{
				$record->$name = is_string($record->$name) ? explode(',', $record->$name) : $record->$name;
				if(is_array($record->$name))
				{
					$names = array();
					foreach($record->$name as $_name)
					{
						$option = $selects[$name][$_name];
						$names[] = lang(is_array($option) && $option['label'] ? $option['label'] : $option);
					}
					$record->$name = implode(', ', $names);
				}
				else
				{
					$record->$name = lang($selects[$name][$record->$name]);
				}
			}
			else
			{
				$record->$name = '';
			}
		}
		foreach($fields['links'] ?? [] as $name) {
			if($record->$name) {
				if(is_numeric($record->$name) && empty($links[$name])) {
					$link = Link::get_link($record->$name);
					$links[$name] = ($link['link_app1'] == $appname ? $link['link_app2'] : $link['link_app1']);
					$record->$name = ($link['link_app1'] == $appname ? $link['link_id2'] : $link['link_id1']);
				}
				if($links[$name])
				{
					$record->$name = Link::title($links[$name], $record->$name);
				}
				else if ( is_array($record->$name) && $record->$name['app'] && $record->$name['id'])
				{
					$record->$name = Link::title($record->$name['app'], $record->$name['id']);
				}
			}
			else
			{
				$record->$name = '';
			}
		}
		foreach($fields['select-account'] ?? [] as $name)
		{
			// Compare against null to deal with empty arrays
			if ($record->$name !== null)
			{
				$names = array();
				foreach((array)$record->$name as $_name) {
					$names[] = Api\Accounts::title((int)$_name ?: $_name);
				}
				$record->$name = implode(', ', $names);
			}
			else
			{
				$record->$name = '';
			}
		}
		foreach($fields['select-bool'] ?? [] as $name) {
			if($record->$name !== null) {
				$record->$name = $record->$name ? lang('Yes') : lang('No');
			}
		}
		foreach($fields['date-time'] ?? [] as $name) {
			//if ($record->$name) $record->$name = date('Y-m-d H:i:s',$record->$name); // Standard date format
			if ($record->$name && !is_numeric($record->$name)) $record->$name = strtotime($record->$name); // Custom fields stored as string
			if ($record->$name && is_numeric($record->$name)) $record->$name = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] . ' '.
				($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '24' ? 'H:i:s' : 'h:i:s a'),$record->$name); // User date format
			if (!$record->$name) $record->$name = '';
		}
		foreach($fields['date'] ?? [] as $name) {
			//if ($record->$name) $record->$name = date('Y-m-d',$record->$name); // Standard date format
			if ($record->$name && !is_numeric($record->$name)) $record->$name = strtotime($record->$name); // Custom fields stored as string
			if ($record->$name && is_numeric($record->$name)) $record->$name = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'], $record->$name); // User date format
			if (!$record->$name) $record->$name = '';
		}
		foreach($fields['float'] ?? [] as $name)
		{
			static $dec_separator,$thousands_separator;
			if (is_null($dec_separator))
			{
				$dec_separator = $GLOBALS['egw_info']['user']['preferences']['common']['number_format'][0];
				if (empty($dec_separator)) $dec_separator = '.';
				$thousands_separator = $GLOBALS['egw_info']['user']['preferences']['common']['number_format'][1] ?? '';
			}
			if($record->$name && (string)$record->$name != '')
			{
				if(!is_numeric($record->$name))
				{
					$record->$name = (float)(str_replace($dec_separator, '.', preg_replace('/[^\d'.preg_quote($dec_separator, '/').']/', '', $record->$name)));
				}
				$record->$name = number_format(str_replace(' ','',$record->$name), 2,
					$dec_separator,$thousands_separator
				);
			}
		}

		// Some custom methods for conversion
		foreach($methods ?? [] as $name => $method) {
			if ($record->$name)
			{
				if(is_string($method))
				{
					$record->$name = ExecMethod($method, $record->$name);
				}
				else if (is_callable($method))
				{
					$record->$name = $method($record->$name);
				}
			}
		}

		static $cat_object;
		if(is_null($cat_object)) $cat_object = new Api\Categories(false,$appname);
		foreach((array)$fields['select-cat'] as $name) {
			if($record->$name) {
				$cats = array();
				$ids = is_array($record->$name) ? $record->$name : explode(',', $record->$name);
				foreach($ids as $n => $cat_id) {

					if ($cat_id && $cat_object->check_perms(Acl::READ,$cat_id))
					{
						$cats[] = $cat_object->id2name($cat_id);
					}
				}
				$record->$name = implode(', ',$cats);
			}
			else
			{
				$record->$name = '';
			}
		}
	}

	/**
	 * destructor
	 *
	 * @return
	 */
	public function __destruct() {

	}

	/**
	 * The php build in fputcsv function is buggy, so we need an own one :-(
	 * It ignores encoding, and outputs in UTF-8 / system charset
	 *
	 * @param resource $filePointer
	 * @param array $dataArray
	 * @param char $delimiter
	 * @param char $enclosure
	 */
	protected function fputcsv($filePointer, Array $dataArray, $delimiter, $enclosure){
		$string = "";
		$writeDelimiter = false;
		foreach($dataArray as $dataElement) {
			if($writeDelimiter) $string .= $delimiter;
			$string .= $enclosure . str_replace(array("\r\n", '"'), array("\n",'""'), $dataElement) . $enclosure;
			$writeDelimiter = true;
		}
		$string .= "\n";

		// do charset translation
		$string = $this->translation->convert( $string, $this->translation->charset(), $this->csv_charset );

		fwrite($filePointer, $string);

	}
} // end  export_csv_record
?>