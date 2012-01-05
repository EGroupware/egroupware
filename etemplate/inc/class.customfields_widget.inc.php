<?php
/**
 * eGroupWare eTemplate Widget for custom fields
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @version $Id$
 */

/**
 * This widget generates a template for customfields based on definitions in egw_config table
 *
 * All widgets here have 2+ comma-separated options ($cell[size]):
 * - sub-type to display only the cf's without subtype or with a matching one
 * - use-private to display only (non-)private cf's (0=regular ones, 1=private ones, default both)
 * - field-name to display only the named custom field(s).  Use ! before to display all but given field(s).
 * Additional fields can be added with a comma between them
 *
 * Private cf's the user has no right to see (neither him nor his memberships are mentioned) are never displayed.
 */
class customfields_widget
{
	var $public_functions = array(
		'pre_process' => True,
	);
	var $human_name = array(
		'customfields' => 'custom fields',
		'customfields-types' => 'custom field types',
		'customfields-list'  => 'custom field list',
		'customfields-no-label' => 'custom fields without label',
	);

	/**
	 * Allowd types of customfields
	 *
	 * The additionally allowed app-names from the link-class, will be add by the edit-method only,
	 * as the link-class has to be called, which can NOT be instanciated by the constructor, as
	 * we get a loop in the instanciation.
	 *
	 * @var array
	 */
	var $cf_types = array(
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
		'link-entry' => 'Select entry',		// should be last type, as the individual apps get added behind
	);

	/**
	 * @var $prefix string Prefix for every custiomfield name returned in $content (# for general (admin) customfields)
	 */
	var $prefix = '#';
	/**
	 * Current application
	 *
	 * @var string
	 */
	var $appname;
	/**
	 * Instance of the config class for $appname
	 *
	 * @var config
	 */
	var $config;
	/**
	 * Our customfields as name => data array
	 *
	 * @var array
	 */
	var $customfields;
	var $types;
	var $advanced_search;


	function __construct($ui=null,$appname=null)
	{
		$this->appname = $appname ? $appname : $GLOBALS['egw_info']['flags']['currentapp'];
		$this->customfields = config::get_customfields($this->appname);
		$this->types = config::get_content_types($this->appname);
		$this->advanced_search = $GLOBALS['egw_info']['etemplate']['advanced_search'];
	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $form_name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($form_name,&$value,&$cell,&$readonlys,&$extension_data,etemplate $tmpl)
	{
		list($app) = explode('.',$tmpl->name);

		// if we are in the etemplate editor or a different app, load the cf's from the app the tpl belongs to
		if ($app && $app != 'stylite' && $app != $this->appname)
		{
			self::__construct(null,$app); 	// app changed
		}

		list($type2,$use_private,$field_names) = explode(',',$cell['size'],3);
		$fields_with_vals=array();

		// Filter fields
		if($field_names)
		{
			if($field_names[0] == '!') {
				$negate_field_filter = true;
				$field_names = substr($field_names,1);
			}
			$field_filter = explode(',', $field_names);
		}

		$fields = $this->customfields;

		foreach((array)$fields as $key => $field)
		{
			// remove private or non-private cf's, if only one kind should be displayed
			if ((string)$use_private !== '' && (boolean)$field['private'] != (boolean)$use_private)
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
		if (($pos=strpos($cell['name'],$this->prefix)) !== false && // allow the prefixed name to be an array index too
			preg_match("/$this->prefix([^\]]+)/",$cell['name'],$matches) && isset($fields[$name=$matches[1]]))
		{
			$fields = array($name => $fields[$name]);
			$value = array($this->prefix.$name => $value);
			$singlefield = true;
			$form_name = substr($form_name,0,-strlen("[$this->prefix$name]"));
		}
		switch($type = $cell['type'])
		{
			case 'customfields-types':
				$cell['type'] = 'select';
				foreach($this->cf_types as $lname => $label)
				{
					$cell['sel_options'][$lname] = lang($label);
					$fields_with_vals[]=$lname;
				}
				$link_types = array_intersect(egw_link::app_list('query'),egw_link::app_list('title'));
				ksort($link_types);
				foreach($link_types as $lname => $label) $cell['sel_options'][$lname] = '- '.$label;
				$cell['no_lang'] = true;
				return true;

			case 'customfields-list':
				foreach(array_reverse($fields) as $lname => $field)
				{
					if (!empty($type2) && !empty($field['type2']) && strpos(','.$field['type2'].',',','.$type2.',') === false) continue;	// not for our content type//
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
		$readonly = $cell['readonly'] || $readonlys[$name] || $type == 'customfields-list';

		if(!is_array($fields))
		{
			$cell['type'] = 'label';
			return True;
		}
		// making the cell an empty grid
		$cell['type'] = 'grid';
		$cell['data'] = array(array());
		$cell['rows'] = $cell['cols'] = 0;
		$cell['size'] = '';

		$n = 1;
		foreach($fields as $lname => $field)
		{
			if (array_search($lname,$fields_with_vals) !== false)
			{
				if ($stop_at_field && $lname == $stop_at_field) break;	// no further row necessary

				// check if the customfield get's displayed for type $value, we can have multiple comma-separated types now
				if (!empty($type2) && !empty($field['type2']) && strpos(','.$field['type2'].',',','.$type2.',') === false)
				{
					continue;	// not for our content type
				}
				$new_row = null; etemplate::add_child($cell,$new_row);
				if ($type != 'customfields-list' && $type == 'customfields')
				{
					$row_class = 'row';
					etemplate::add_child($cell,$label =& etemplate::empty_cell('label','',array(
						'label' => $field['label'],
						'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0,
						'span' => $field['type'] === 'label' ? '2' : '',
					)));
				}
				elseif ($type == 'customfields-list')
				{
					if (isset($value[$this->prefix.$lname]) && $value[$this->prefix.$lname] !== '')
					{
						switch ((string)$field['type'])
						{
							case 'checkbox':
								if ($value[$this->prefix.$lname]==0) break;
							default:
								etemplate::add_child($cell,$input =& etemplate::empty_cell('image','info.png',
									array('label'=> $field['label'],'width'=>"16px")));
						}
					}
				}

				switch ((string)$field['type'])
				{
					case 'select' :
						if (count($field['values']) == 1 && isset($field['values']['@']))
						{
							$field['values'] = $this->_get_options_from_file($field['values']['@']);
						}
						if($this->advanced_search && $field['rows'] <= 1) $field['values'][''] = lang('doesn\'t matter');
						foreach($field['values'] as $key => $val)
						{
							if (substr($val = lang($val),-1) != '*')
							{
								$field['values'][$key] = $val;
							}
						}
						$input =& etemplate::empty_cell('select',$this->prefix.$lname,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'no_lang'     => True,
						));
						if($this->advanced_search)
						{
							$select =& $input; unset($input);
							$input =& etemplate::empty_cell('hbox');
							etemplate::add_child($input, $select); unset($select);
							/* the following seem to double the select fields in advanced search.
							etemplate::add_child($input, etemplate::empty_cell('select',$this->prefix.$lname,array(
								'sel_options' => $field['values'],
								'size'        => $field['rows'],
								'no_lang'     => True
							)));
							*/
						}
						break;
					case 'ajax_select' :
						// Set some reasonable defaults for the widget
						$options = array(
							'get_title'	=> 'etemplate.ajax_select_widget.array_title',
							'get_rows'	=> 'etemplate.ajax_select_widget.array_rows',
							'id_field'	=> ajax_select_widget::ARRAY_KEY,
						);
						if($field['rows']) {
							$options['num_rows'] = $field['rows'];
						}

						// If you specify an option known to the AJAX Select widget, it will be pulled from the list of values
						// and used as such.  All unknown values will be used for selection, not passed through to the query
						if (isset($field['values']['@']))
						{
							$options['values'] = $this->_get_options_from_file($field['values']['@']);
							unset($field['values']['@']);
						} else {
							$options['values'] = array_diff_key($field['values'], array_flip(ajax_select_widget::$known_options));
						}
						$options = array_merge($options, array_intersect_key($field['values'], array_flip(ajax_select_widget::$known_options)));

						$input =& etemplate::empty_cell('ajax_select', $this->prefix.$lname, array(
							'readonly'	=> $readonly,
							'no_lang'	=> True,
							'size'		=> $options
						));
						break;
					case 'label' :
						$row_class = 'th';
						break;
					case 'radio' :
						$showthis = '#a#l#l#';
						if (count($field['values']) == 1 && isset($field['values']['@']))
						{
							$field['values'] = $this->_get_options_from_file($field['values']['@']);
						}
						if($this->advanced_search && $field['rows'] <= 1) $field['values'][''] = lang('doesn\'t matter');
						if ($readonly)
						{
							$showthis = $value[$this->prefix.$lname];
							$input =& etemplate::empty_cell('hbox');
						}
						else
						{
							$input =& etemplate::empty_cell('groupbox');
						}
						$m = 0;
						foreach ($field['values'] as $key => $val)
						{
							$radio = etemplate::empty_cell('radio',$this->prefix.$lname);
							$radio['label'] = $val;
							$radio['size'] = $key;
							if ($showthis == '#a#l#l#' || $showthis == $key) etemplate::add_child($input,$radio);
							unset($radio);
						}
						break;
					case 'float':
						$known_options = array('min'=>null, 'max'=>null,'size' => $field['len'], 'precision' => null);
						$options = array_merge($known_options, $field['values']);
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname,array(
							'size' => implode(',',$options)
						));
						break;
					case 'text' :
					case 'textarea' :
					case '' :	// not set
						$field['len'] = $field['len'] ? $field['len'] : 20;
						if ($type != 'customfields-list')
						{
							if($field['rows'] <= 1)
							{//text
								list($max,$shown) = explode(',',$field['len']);
								$tmparray=array(
									'size' => intval($shown > 0 ? $shown : $max).','.intval($max),
									'maxlength'=>intval($max),
									'no_lang' => True,
								);
								if (is_array($field['values']))
								{
									if (array_key_exists('readonly',$field['values']))
									{
										if(!$this->advanced_search) $tmparray['readonly']='readonly';
									}
								}
								$input =& etemplate::empty_cell('text',$this->prefix.$lname,$tmparray);
							}
							else
							{//textarea
								$tmparray=array(
									'size' => $field['rows'].($field['len'] >0 ? ','.(int)$field['len'] : ''),
									'no_lang' => True,
								);
								if (is_array($field['values']) && array_key_exists('readonly',$field['values']))
								{
									if(!$this->advanced_search) $tmparray['readonly']='readonly';
								}
								$input =& etemplate::empty_cell('textarea',$this->prefix.$lname,$tmparray);
							}
						}
						else
						{
							$input =& etemplate::empty_cell('label',$this->prefix.$lname,array('no_lang' => True));
						}
						break;
					case 'date':
					case 'date-time':
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname,array(
							'size' => $field['len'] ? $field['len'] : ($field['type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'),
						));
						break;
					case 'select-account':
						list($opts) = explode('=',$field['values'][0]);
						$input =& etemplate::empty_cell('select-account',$this->prefix.$lname,array(
							'size' => ($field['rows']>1?$field['rows']:lang('None')).','.$opts,
						));
						break;
					case 'button':  // button(s) to execute javascript (label=onclick) or textinputs (empty label, readonly with neg. length)
						// a button does not seem to be helpful in advanced search ???,
						if($this->advanced_search) break;
						$input =& etemplate::empty_cell('hbox');
						foreach($field['values'] as $label => $js)
						{
							if (!$label)    // display an readonly input
							{
								$tmparray = array(
									'size' => $field['len'] ? $field['len'] : 20,
									'readonly' => $field['len'] < 0,
									'onchange' => $js,
								);
								$widget =& etemplate::empty_cell('text',$this->prefix.$lname.$label,$tmparray);
							}
							else
							{
								if ($readonly) continue;        // dont display buttons if we're readonly
								$widget =& etemplate::empty_cell('buttononly',$this->prefix.$lname.$label,array(
									'label' => $label ? $label : lang('Submit'),
									'onclick' => $js,
									'no_lang' => True
								));
							}
							etemplate::add_child($input,$widget);
							unset($widget);
						}
						break;
					case 'url-email':
						list($max,$shown,$validation_type,$default) = explode(',',$field['len'],4);
						if (empty($max)) $max =128;
						if (empty($shown)) $shown = 28;
						if (empty($validation_type)) $validation_type = 1;
						$field['len'] = implode(',',array($shown, $max, $validation_type, $default));
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname,array(
							'size' => $field['len']
						));
						break;
					case 'url':
					case 'url-phone':
						list($max,$shown,$validation_type) = explode(',',$field['len'],3);
						if (empty($max)) $max =128;
						if (empty($shown)) $shown = 28;
						$field['len']=implode(',',array( $shown, $max, $validation_type));
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname,array(
							 'size' => $field['len']
						));
						break;
					case 'htmlarea':	// defaults: len: width=100%,mode=simple,tooldbar=false; rows: 5
						list($width,$mode,$toolbar) = explode(',',$field['len']);
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname,array(
							'size' => $mode.','.(($field['rows'] ? $field['rows'] : 5)*16).'px,'.$width.','.($toolbar=='true'?'true':'false'),
						));
						break;
					// other etemplate types, which are used just as is
					case 'checkbox' :
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$lname);
						break;
					case 'link-entry':
					default :	// link-entry to given app
						$input =& etemplate::empty_cell('link-entry',$this->prefix.$lname,array(
							'size' => $field['type'] == 'link-entry' ? '' : $field['type'],
						));
						// register post-processing of link widget to get eg. needed/required validation
						etemplate::$request->set_to_process(etemplate::form_name($form_name,$this->prefix.$lname), 'ext-link');
				}
				$cell['data'][0]['c'.$n++] = $row_class.',top';

				if (!is_null($input))
				{
					if ($readonly) $input['readonly'] = true;

					$input['needed'] = $cell['needed'] || $field['needed'];

					if (!empty($field['help']) && $row_class != 'th')
					{
						$input['help'] = $field['help'];
						$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
					}
					if ($singlefield)	// a single field, can & need to be returned instead of the cell (no grid)
					{
						$input['span'] = $cell['span'];	// set span & class from original cell
						$cell = $input;
						if ($type == 'customfields') $cell['label'] = $field['label'];
						$value = $value[$this->prefix.$lname];
						return true;
					}
					etemplate::add_child($cell,$input);
					unset($input);
				}
				unset($label);
			}
		}
		if ($type != 'customfields-list')
		{
			$cell['data'][0]['A'] = '100';
		}
		list($span,$class) = explode(',',$cell['span']);	// msie (at least 5.5) shows nothing with div overflow=auto
		// we dont want to use up the full space for the table created, so we skip the line below
		//$cell['size'] = '100%,100%,0,'.$class.','.(in_array($type,array('customfields-list','customfields-no-label'))?'0,0':',').(html::$user_agent != 'msie' ? ',auto' : '');

		return True;	// extra Label is ok
	}

	/**
	 * Read the options of a 'select' or 'radio' custom field from a file
	 *
	 * For security reasons that file has to be relative to the eGW root
	 * (to not use that feature to explore arbitrary files on the server)
	 * and it has to be a php file setting one variable called options,
	 * (to not display it to anonymously by the webserver).
	 * The $options var has to be an array with value => label pairs, eg:
	 *
	 * <?php
	 * $options = array(
	 *      'a' => 'Option A',
	 *      'b' => 'Option B',
	 *      'c' => 'Option C',
	 * );
	 *
	 * @param string $file file name inside the eGW server root, either relative to it or absolute
	 * @return array in case of an error we return a single option with the message
	 */
	function _get_options_from_file($file)
	{
		if (!($path = realpath($file{0} == '/' ? $file : EGW_SERVER_ROOT.'/'.$file)) ||	// file does not exist
			substr($path,0,strlen(EGW_SERVER_ROOT)+1) != EGW_SERVER_ROOT.'/' ||	// we are NOT inside the eGW root
			basename($path,'.php').'.php' != basename($path) ||	// extension is NOT .php
			basename($path) == 'header.inc.php')	// dont allow to include our header again
		{
			return array(lang("'%1' is no php file in the eGW server root (%2)!".': '.$path,$file,EGW_SERVER_ROOT));
		}
		include($path);

		return $options;
	}

	/**
	 * Get the customfield types containing links
	 *
	 * @return array with customefield types as values
	 */
	public static function get_customfield_link_types()
	{
		static $link_types;

		if (is_null($link_types))
		{
			$link_types = array_keys(egw_link::app_list());
			$link_types[] = 'link-entry';
		}
		return $link_types;
	}

	/**
	 * Check if there are links in the custom fields and update them
	 *
	 * This function have to be called manually by an application, if cf's linking
	 * to other entries should be stored as links too (beside as cf's).
	 *
	 * @param string $own_app own appname
	 * @param array $values new values including the custom fields
	 * @param array $old=null old values before the update, if existing
	 * @param string $id_name='id' name/key of the (link-)id in $values
	 */
	public static function update_customfield_links($own_app,array $values,array $old=null,$id_name='id')
	{
		$link_types = self::get_customfield_link_types();

		foreach(config::get_customfields($own_app) as $name => $data)
		{
			if (!in_array($data['type'],$link_types)) continue;

			// do we have a different old value --> delete that link
			if ($old && $old['#'.$name] && $old['#'.$name] != $values['#'.$name])
			{
				if ($data['type'] == 'link-entry')
				{
					list($app,$id) = explode(':',$old['#'.$name]);
				}
				else
				{
					$app = $data['type'];
					$id = $old['#'.$name];
				}
				egw_link::unlink(false,$own_app,$values[$id_name],'',$app,$id);
			}
			if ($data['type'] == 'link-entry')
			{
				list($app,$id) = explode(':',$values['#'.$name]);
			}
			else
			{
				$app = $data['type'];
				$id = $values['#'.$name];
			}
			if ($id)	// create new link, does nothing for already existing links
			{
				egw_link::link($own_app,$values[$id_name],$app,$id);
			}
		}
	}
}
