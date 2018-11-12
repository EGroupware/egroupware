<?php
/**
 * EGroupware  eTemplate extension - InfoLog widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * Infolog widget et2 representation:
 * Both infolog-value and infolog-fields widgets are using client-side et2_widget_entry
 * and the format to address them in template is as follows:
 *
 * <infolog-value id="fieldname" or value="@fieldname"
 *   options="[field(e.g. sum), compare, alternate_fields(e.g. (-)#customfileds, use '-' if we need subtraction)]"
 * />
 *
 */

/**
 * eTemplate extension: InfoLog widget
 *
 * This widget can be used to display data from an InfoLog specified by it's id
 *
 * The infolog-value widget takes 3 comma-separated arguments (beside the name) in the options/size field:
 * 1) name of the field (as provided by the infolog-fields widget)
 * 2) an optional compare value: if given the selected field is compared with its value and an X is printed on equality, nothing otherwise
 * 3) colon (:) separted list of alternative fields: the first non-empty one is used if the selected value is empty
 * There's a special field "sum" in 1), which sums up all fields given in alternatives.
 */
class infolog_widget extends Etemplate\Widget\Entry
{

	/**
	 * Array with a transformation description, based on attributes to modify.
	 * @see etemplate_widget_transformer
	 *
	 * @var array
	 */
	protected static $transformation = array(
		'type' => array(
			'infolog-fields' => array(
				'sel_options' => array('__callback__' => '_get_fields'),
				'type' => 'select',
				'no_lang' => true,
				'options' => 'None',
			),
			'__default__' => array(
				'options' => array(
					'' => array('id' => '@value[@id]'),
					// Others added automatically in constructor
					'__default__' => array('type' => 'label', 'options' => ''),
				),
				'no_lang' => 1,
			),
		),
	);
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
		'infolog-value'  => 'InfoLog',
		'infolog-fields' => 'InfoLog fields',
	);
	/**
	 * Instance of the infolog_bo class
	 *
	 * @var infolog_bo
	 */
	var $infolog;
	/**
	 * Cached infolog
	 *
	 * @var array
	 */
	var $data;

	/**
	 * Constructor of the extension
	 */
	function __construct($xml)
	{
		parent::__construct($xml);

		$this->infolog = new infolog_bo();
	}

	public function get_entry($value, array $attrs)
	{
		unset($attrs);	// not used

		// Already done
		if (is_array($value) && !(array_key_exists('app',$value) && array_key_exists('id', $value))) return $value;

		// Link entry, already in array format
		if(is_array($value) && array_key_exists('app', $value) && array_key_exists('id', $value)) $value = $value['id'];

		// Link entry, in string format
		if (substr($value,0,8) == 'infolog:') $value = substr($value,8);
		if($value)
		{
			return $this->infolog->read($value);
		}
		return array();
	}

	function _get_fields()
	{
		static $fields=null;

		if (!isset($fields))
		{
			$fields = array(
				'' => lang('Sum'),
				'info_type' => lang('Type'),
				'info_subject' => lang('Subject'),
				'info_des' => lang('Description'),
				'info_cat' => lang('Category'),
				'info_from' => lang('Contact'),
				'info_responsible' => lang('Responsible'),
				'info_startdate' => lang('Startdate'),
				'info_enddate' => lang('Enddate'),
				'info_status' => lang('Status'),
				'info_priority' => lang('Priority'),
				'info_location' => lang('Location'),
				'info_percent' => lang('Completed'),
				'info_datecompleted' => lang('Date completed'),
				// meta data
				// PM fields
				'info_planned_time' => lang('planned time'),
				'info_used_time' => lang('used time'),
				'pl_id' => lang('Pricelist'),
				'info_price' => lang('Price'),
				// other
				'info_owner' => lang('Owner'),
				'info_access' => lang('Access'),
				'info_id' => lang('Id#'),
				'info_link_id' => lang('primary link'),
				'info_modifier' => lang('Modifierer'),
				'info_datemodified' => lang('Last modified'),
	//			'info_id_parent' => lang('Parent'),
	//			'info_confirm' => lang('Confirm'),
	//			'info_custom_from' => lang('Custom from'),
			);
			foreach(Api\Storage\Customfields::get('infolog') as $name => $data)
			{
				$fields['#'.$name] = lang($data['label']);
			}
		}
		return $fields;
	}
}

// register widgets for etemplate2
Etemplate\Widget::registerWidget('infolog_widget',array('infolog-value', 'infolog-fields'));
