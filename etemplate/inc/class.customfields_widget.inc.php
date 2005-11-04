<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Widget for custom fields                          *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de> and              *
	*            Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * This widget generates a template for customfields based on definitioins in phpgw_config table
	 *
	 * @package eTemplate
	 * @author RalfBecker-At-outdoor-training.de
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @copyright GPL - GNU General Public License
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
			//merge old config_name in phpgw_config table
			$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';
			$this->customfields = $config[$config_name];
			$this->advanced_search = $GLOBALS['egw_info']['etemplate']['advanced_search'];

		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			// infolog compability
			if ($this->appname == 'infolog')
			{
				$typ = $value['###typ###'];
				unset($value['###typ###']);
				$this->customfields = $value;
			}
			
			if(!is_array($this->customfields))
			{
				$cell['type'] = 'label';
				return True;
			}
			
			$tpl =& new etemplate;
			$tpl->init('*** generated custom fields','','',0,'',0,0);	// make an empty template
			
			//echo '<pre style="text-aling: left;">'; print_r($value); echo "</pre>\n";
			foreach($this->customfields as $name => $field)
			{
				if (!empty($field['typ']) && $field['typ'] != $typ)
				{
					continue;	// not for our typ
				}
				if(empty($field['type']))
				{
					if (count($field['values'])) $field['type'] = 'select'; // selectbox
					elseif ($field['rows'] > 1) $field['type'] = 'textarea'; // textarea
					elseif (intval($field['len']) > 0) $field['type'] = 'text'; // regular input field
					else $field['type'] = 'label'; // header-row
				}
				
				$row_class = 'row';
				$label = &$tpl->new_cell(++$n,'label',$field['label'],'',array(
					'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0
				));
				switch ($field['type'])
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
						
						$input = &$tpl->new_cell($n,'select','',$this->prefix.$name,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'no_lang'     => True
						));
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
					default :
						$field['len'] = $field['len'] ? $field['len'] : 20;
						if($field['rows'] < 1)
						{
							list($max,$shown) = explode(',',$field['len']);
							$input = &$tpl->new_cell($n,'text','',$this->prefix.$name,array(
								'size' => intval($shown > 0 ? $shown : $max).','.intval($max)
							));
						}
						else
						{
							$input = &$tpl->new_cell($n,'textarea','',$this->prefix.$name,array(
								'size' => $field['rows'].($field['len'] > 0 ? ','.intval($field['len']) : '')
						));
						}
						break;
				}
				
				if (!empty($field['help']) && $row_class != 'th')
				{
					$input['help'] = $field['help'];
					$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
				}
				$tpl->set_row_attributes($n,0,$row_class);
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
