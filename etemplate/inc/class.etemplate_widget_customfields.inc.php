<?php
/**
 * EGroupware - eTemplate custom fields widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * Widgets for custom fields and listing custom fields
 *
 */
class etemplate_widget_customfields extends etemplate_widget_transformer
{

	/**
	 * Allowd types of customfields
	 *
	 * The additionally allowed app-names from the link-class, will be add by the edit-method only,
	 * as the link-class has to be called, which can NOT be instanciated by the constructor, as
	 * we get a loop in the instanciation.
	 *
	 * @var array
	 */
	protected static $cf_types = array(
		'text'     => 'Text',
		'float'    => 'Float',
		'label'    => 'Label',
		'select'   => 'Selectbox',
		'ajax_select' => 'Search',
		'radio'    => 'Radiobutton',
		'checkbox' => 'Checkbox',
		'date'     => 'Date',
		'date-time'=> 'Date+Time',
		'select-account' => 'Select account',
		'button'   => 'Button',         // button to execute javascript
		'url'      => 'Url',
		'url-email'=> 'EMail',
		'url-phone'=> 'Phone number',
		'htmlarea' => 'Formatted Text (HTML)',
		'link-entry' => 'Select entry',         // should be last type, as the individual apps get added behind
	);

	/**
	 * @var $prefix string Prefix for every custiomfield name returned in $content (# for general (admin) customfields)
	 */
	protected static $prefix = '#';
	
	const GLOBAL_VALS = '~custom_fields~';

	protected $legacy_options = 'sub-type,use-private,field-names';

	protected static $transformation = array(
		'type' => array(
			'customfields-types' => array(
				'type'	=>	'select',
				'sel_options'	=> array()
			),
			'customfields-list' => array(
				'readonly'	=> true
			)
		)
	);

	public function __construct($xml) 
	{
		parent::__construct($xml);
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		// No name, no way to get parameters client-side.
		if(!$this->id) $this->id = 'custom_fields';

		$form_name = self::form_name($cname, $this->id);

		// Store properties at top level, so all customfield widgets can share
		$app =& $this->getElementAttribute(self::GLOBAL_VALS, 'app');
		if($this->getElementAttribute($form_name, 'app'))
		{
			$app =& $this->getElementAttribute($form_name, 'app');
		} else {
			// Checking creates it even if it wasn't there
			unset(self::$request->modifications[$form_name]['app']);
		}

		if(!$app)
		{
			$app =& $this->setElementAttribute(self::GLOBAL_VALS, 'app', $GLOBALS['egw_info']['flags']['currentapp']);
			$customfields =& $this->setElementAttribute(self::GLOBAL_VALS, 'customfields', config::get_customfields($app));
		}

		// if we are in the etemplate editor or the app has no cf's, load the cf's from the app the tpl belongs too
		if ($app && $app != 'stylite' && $app != $GLOBALS['egw_info']['flags']['currentapp'] && (
			$GLOBALS['egw_info']['flags']['currentapp'] == 'etemplate' || !$this->attrs['customfields'] || 
			etemplate::$hooked
		))
		{
			// app changed
			$customfields =& config::get_customfields($app);
		}

		// Filter fields
                if($this->attrs['field-names'])
                {
                        if($this->attrs['field-names'][0] == '!') {
                                $negate_field_filter = true;
                                $this->attrs['field-names'] = substr($this->attrs['field_names'],1);
                        }
                        $field_filter = explode(',', $this->attrs['field_names']);
                }
		$fields = $customfields;
		foreach((array)$fields as $key => $field)
		{
			// remove private or non-private cf's, if only one kind should be displayed
			if ((string)$this->attrs['use-private'] !== '' && (boolean)$field['private'] != (boolean)$this->attrs['use-private'])
			{
				unset($fields[$key]);
			}

			// Remove filtered fields
			if($field_filter && (!$negate_field_filter && !in_array($key, $field_filter) ||
				$negate_field_filter && in_array($key, $field_filter)))
			{
				unset($fields[$key]);
			}
		}
		// check if name refers to a single custom field --> show only that
		if (($pos=strpos($form_name,self::$prefix)) !== false && // allow the prefixed name to be an array index too
			preg_match("/$this->prefix([^\]]+)/",$form_name,$matches) && isset($fields[$name=$matches[1]]))
		{
			$fields = array($name => $fields[$name]);
			$value = array($this->prefix.$name => $value);
			$singlefield = true;
			$form_name = substr($form_name,0,-strlen("[$this->prefix$name]"));
		}

		if(!is_array($fields)) $fields = array();
		switch($type = $this->type)
		{
			case 'customfields-types':
				foreach(self::$cf_types as $lname => $label)
				{
					$sel_options[$lname] = lang($label);
					$fields_with_vals[]=$lname;
				}
				$link_types = egw_link::app_list();
				ksort($link_types);
				foreach($link_types as $lname => $label) $sel_options[$lname] = '- '.$label;

				self::$transformation['type'][$type]['sel_options'] = $sel_options;
				self::$transformation['type'][$type]['no_lang'] = true;
				return parent::beforeSendToClient($form_name);
			case 'customfields-list':
				foreach(array_reverse($fields) as $lname => $field)
				{
					if (!empty($this->attrs['sub-type']) && !empty($field['type2']) && strpos(','.$field['type2'].',',','.$type2.',') === false) continue;    // not for our content type//
					if (isset($value[$this->prefix.$lname]) && $value[$this->prefix.$lname] !== '') //break;
					{
						$fields_with_vals[]=$lname;
					}
					//$stop_at_field = $name;
				}
				break;
			default:
				foreach(array_reverse($fields) as $lname => $field)
				{
					$fields_with_vals[]=$lname;
				}
		}
		if($fields != $customfields)
		{
			// This widget has different settings from global
			$this->setElementAttribute($form_name, 'customfields', $fields);
		}
		parent::beforeSendToClient($cname);
	}
}
