<?php
	/**
	 * eGroupWare eTemplate Widget for custom fields
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @version $Id$
	 */

	/**
	 * This widget generates a template for customfields based on definitions in egw_config table
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-At-outdoor-training.de
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @license GPL - GNU General Public License
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
			'label'    => 'Label',
			'select'   => 'Selectbox',
			'radio'    => 'Radiobutton',
			'checkbox' => 'Checkbox',
			'date'     => 'Date',
			'date-time'=> 'Date+Time',
			'select-account' => 'Select account',
			'link-entry' => 'Select entry',		// should be last type, as the individual apps get added behind
		);

		/**
		* @var $prefix string Prefix for every custiomfield name returned in $content (# for general (admin) customfields)
		*/
		var $prefix = '#';

		function customfields_widget($ui)
		{
			$this->appname = $GLOBALS['egw_info']['flags']['currentapp'];
			$this->config =& CreateObject('phpgwapi.config',$this->appname);
			$this->config->appname = $this->appname;
			$config = $this->config->read_repository();
			//merge old config_name in egw_config table
			$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';
			$this->customfields = $config[$config_name] ? $config[$config_name] : array('test'=>array('type'=>'label'));
			$this->types = $config['types'];
			$this->advanced_search = $GLOBALS['egw_info']['etemplate']['advanced_search'];

		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			switch($type = $cell['type'])
			{
				case 'customfields-types':
					$cell['type'] = 'select';
					foreach($this->cf_types as $name => $label) $cell['sel_options'][$name] = lang($label);
					$link_types = ExecMethod('phpgwapi.bolink.app_list','');
					ksort($link_types);
					foreach($link_types as $name => $label) $cell['sel_options'][$name] = '- '.$label;
					$cell['no_lang'] = true;
					return true;

				case 'customfields-list':
					foreach(array_reverse($this->customfields) as $name => $field)
					{
						if (!empty($field['type2']) && strpos(','.$field['type2'].',',','.$value.',') === false) continue;	// not for our content type
						if (isset($value[$this->prefix.$name]) && $value[$this->prefix.$name] !== '') break;
						$stop_at_field = $name;
					}
					break;
			}
			$readonly = $cell['readonly'] || $readonlys[$name] || $type == 'customfields-list';

			if(!is_array($this->customfields))
			{
				$cell['type'] = 'label';
				return True;
			}
			// making the cell an empty grid
			$cell['type'] = 'grid';
			$cell['data'] = array(array());
			$cell['rows'] = $cell['cols'] = 0;
			
			$n = 1;
			foreach($this->customfields as $name => $field)
			{
				if ($stop_at_field && $name == $stop_at_field) break;	// no further row necessary

				// check if the customfield get's displayed for type $value, we can have multiple comma-separated types now
				if (!empty($field['type2']) && strpos(','.$field['type2'].',',','.$value.',') === false)
				{
					continue;	// not for our content type
				}
				$new_row = null; etemplate::add_child($cell,$new_row);

				if ($type != 'customfields-list')
				{
					$row_class = 'row';
					etemplate::add_child($cell,$label =& etemplate::empty_cell('label','',array(
						'label' => $field['label'],
						'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0
					)));
				}
				switch ((string)$field['type'])
				{
					case 'select' :
						if($this->advanced_search) $field['values'][''] = lang('doesn\'t matter');
						foreach($field['values'] as $key => $val)
						{
							if (substr($val = lang($val),-1) != '*')
							{
								$field['values'][$key] = $val;
							}
						}
						$input =& etemplate::empty_cell('select',$this->prefix.$name,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'no_lang'     => True								
						));
						if($this->advanced_search)
						{
							$select =& $input; unset($input);
							$input =& etemplate::empty_cell('hbox');
							etemplate::add_child($input, $select); unset($select);
							etemplate::add_child($input, etemplate::empty_cell('select',$this->prefix.$name,array(
								'sel_options' => $field['values'],
								'size'        => $field['rows'],
								'no_lang'     => True
							)));
						}
						break;
					case 'label' :
						$label['span'] = 'all';
						$row_class = 'th';
						break;
					case 'checkbox' :
						$input =& etemplate::empty_cell('checkbox',$this->prefix.$name);
						break;
					case 'radio' :
						$input =& etemplate::empty_cell('groupbox');
						$m = 0;
						foreach ($field['values'] as $key => $val)
						{
							$radio = $tpl->empty_cell('radio',$this->prefix.$name);
							$radio['label'] = $val;
							$radio['size'] = $key;
							etemplate::add_child($input,$radio);
							unset($radio);
						}
						break;
					case 'text' :
					case 'textarea' :
					case '' :	// not set
						$field['len'] = $field['len'] ? $field['len'] : 20;
						if($field['rows'] <= 1)
						{
							list($max,$shown) = explode(',',$field['len']);
							$input =& etemplate::empty_cell('text',$this->prefix.$name,array(
								'size' => intval($shown > 0 ? $shown : $max).','.intval($max)
							));
						}
						else
						{
							$input =& etemplate::empty_cell('textarea',$this->prefix.$name,array(
								'size' => $field['rows'].($field['len'] > 0 ? ','.(int)$field['len'] : '')
							));
						}
						break;
					case 'date':
					case 'date-time':
						$input =& etemplate::empty_cell($field['type'],$this->prefix.$name,array(
							'size' => $field['len'] ? $field['len'] : ($field['type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'),
						));
						break;
					case 'select-account':
						list($opts) = explode('=',$field['values'][0]);
						$input =& etemplate::empty_cell('select-account',$this->prefix.$name,array(
							'size' => ($field['rows']>1?$field['rows']:lang('None')).','.$opts,
						));
						break;
					case 'link-entry':
					default :	// link-entry to given app
						$input =& etemplate::empty_cell('link-entry',$this->prefix.$name,array(
							'size' => $field['type'] == 'link-entry' ? '' : $field['type'],
						));
				}
				if ($readonly) $input['readonly'] = true;
				
				if (!empty($field['help']) && $row_class != 'th')
				{
					$input['help'] = $field['help'];
					$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
				}
				$cell['data'][0]['c'.$n++] = $row_class.',top';
				etemplate::add_child($cell,$input);
				unset($input);
				unset($label);
			}
			if ($type != 'customfields-list')
			{
				$cell['data'][0]['A'] = '100';
			}
			list($span,$class) = explode(',',$cell['span']);	// msie (at least 5.5) shows nothing with div overflow=auto
			$cell['size'] = '100%,100%,0,'.$class.','.($type=='customfields-list'?',0':',').($tpl->html->user_agent != 'msie' ? ',auto' : '');

			return True;	// extra Label is ok
		}
	}
