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
		var $human_name = 'custom fields';
		
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
			$this->customfields = $config[$config_name];
			$this->types = $config['types'];
			$this->advanced_search = $GLOBALS['egw_info']['etemplate']['advanced_search'];

		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			$readonly = $cell['readonly'] || $readonlys[$name];

			if(!is_array($this->customfields))
			{
				$cell['type'] = 'label';
				return True;
			}
			
			$tpl =& new etemplate;
			$tpl->init('*** generated custom fields','','',0,'',0,0);	// make an empty template
			
			//echo '<pre style="text-align: left;">'; print_r($value); echo "</pre>\n";
			foreach($this->customfields as $name => $field)
			{
				if (!empty($field['type2']) && $field['type2'] != $value)
				{
					continue;	// not for our content type
				}
				$row_class = 'row';
				$label = &$tpl->new_cell(++$n,'label',$field['label'],'',array(
					'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0
				));
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
						$input = &$tpl->new_cell($n,'hbox');
						if($this->advanced_search)
						{
							$not = &$tpl->add_child($input, $check = &$tpl->empty_cell('checkbox','!'.$this->prefix.$name,array(
								'label' => 'NOT',
								'no_lang'     => True
							)));
							unset($not);
							unset($check);
						}
						$select = &$tpl->add_child($input, $item = &$tpl->empty_cell('select',$this->prefix.$name,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'no_lang'     => True
						)));
						unset($select);
						unset($item);
						break;
					case 'label' :
						$label['span'] = 'all';
						$tpl->new_cell($n);		// is needed even if its over-span-ed
						$row_class = 'th';
						break;
					case 'checkbox' :
						$input = &$tpl->new_cell($n,'checkbox','',$this->prefix.$name);
						break;
					case 'radio' :
						$input = &$tpl->new_cell($n,'groupbox','','','');
						$m = 0;
						foreach ($field['values'] as $key => $val)
						{
							$radio = $tpl->empty_cell('radio',$this->prefix.$name);
							$radio['label'] = $val;
							$radio['size'] = $key;
							$tpl->add_child($input,$radio);
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
							$input = &$tpl->new_cell($n,'text','',$this->prefix.$name,array(
								'size' => intval($shown > 0 ? $shown : $max).','.intval($max)
							));
						}
						else
						{
							$input = &$tpl->new_cell($n,'textarea','',$this->prefix.$name,array(
								'size' => $field['rows'].($field['len'] > 0 ? ','.(int)$field['len'] : '')
							));
						}
						break;
					case 'link-entry':
					default :	// link-entry to given app
						$input = &$tpl->new_cell($n,'link-entry','',$this->prefix.$name,array(
							'size' => $field['type'] == 'link-entry' ? '' : $field['type'],
						));
				}
				if ($readonly) $input['readonly'] = true;
				
				if (!empty($field['help']) && $row_class != 'th')
				{
					$input['help'] = $field['help'];
					$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
				}
				$tpl->set_row_attributes($n,0,$row_class,'top');
			}
			// create an empty line which (should) take all the remaining height
			$tpl->new_cell(++$n,'label','','',array(
				'span' => 'all'
			));
			$tpl->set_row_attributes($n,'99%','row');

			// set width of 1. (label) column to 100
			$tpl->set_column_attributes(0,'100');

			$tpl->set_rows_cols();		// msie (at least 5.5 shows nothing with div overflow=auto)
			$tpl->size = '100%,100%'.($tpl->html->user_agent != 'msie' ? ',,,,,auto' : '');
			//echo '<pre style="text-align: left;">'; print_r($tpl); echo "</pre>\n";

			if (count($tpl->data) < 2)
			{
				$cell['type'] = 'label';
				return True;
			}
			$cell['size'] = '';	// no separate namespace
			$cell['type'] = 'template';
			$cell['name'] = $tpl->name;
			$cell['obj'] = &$tpl;

			return True;	// extra Label is ok
		}
	}
