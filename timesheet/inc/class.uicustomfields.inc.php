<?php
	/**************************************************************************\
	* eGroupWare - TimeSheet: Administration of custom fields                  *
	* http://www.egroupware.org                                                *
	* Written and (c) by Christoph Mueller Metaways Infosystems GmbH           *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; version 2 of the License.                     *
	\**************************************************************************/

	/* $Id: class.uicustomfields.inc.php 19761 2005-11-12 13:25:59Z ralfbecker $ */

	/**
	 * Administration of custom fields
	 *
	 * @package timesheet
	 * @author Christoph Mueller
	 * @copyright (c) by Ralf Becker <RalfBecker@outdoor-training.de>
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	 
	if (!defined('TIMESHEET_APP'))
	{
		define('TIMESHEET_APP','timesheet');
	}	 
	 
	 
	class uicustomfields
	{
		var $public_functions = array
		(
			'edit' => True
		);

		/**
		 * Customfield types, without the link app-names
		 *
		 * @var array
		 */
		var $cf_types = array(
			'text'     => 'Text',
			'label'    => 'Label',
			'select'   => 'Selectbox',
			'radio'    => 'Radiobutton',
			'checkbox' => 'Checkbox',
		);		
		
		var $config;
		var $bo;
		var $tmpl;
		var $fields;
		
		
		function uicustomfields( )
		{
			$this->bo =& CreateObject('timesheet.botimesheet');
			$this->tmpl =& CreateObject('etemplate.etemplate');
			$this->config = &$this->bo->config;
			$this->fields = &$this->bo->customfields;
			
			$GLOBALS['egw']->translation->add_app('etemplate');
			foreach($this->cf_types as $name => $label) $this->cf_types[$name] = lang($label);
			
		}

		/**
		 * Edit/Create an Timesheet Custom field
		 *
		 * @param array $content Content from the eTemplate Exec
		 */
		function edit($content=null)
		{
		
			$GLOBALS['egw_info']['flags']['app_header'] = lang(TIMESHEET_APP).' - '.lang('Custom fields');
						
			if (is_array($content))
			{
				//echo '<pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
				list($action) = @each($content['button']);
				switch($action)
				{
					default:
						if(!$content['fields']['create'] && !$content['fields']['delete']) {
							break;	
						}
					case 'save':
					case 'apply':
						$this->update($content);
						if ($action != 'save')
						{
							break;
						}
					case 'cancel':
						$GLOBALS['egw']->redirect_link('/timesheet/index.php?menuaction=timesheet.uitimesheet.index');
						exit;
				}
			}
			$readonlys = array();

			//echo 'customfields=<pre style="text-align: left;">'; print_r($this->fields); echo "</pre>\n";
			$content['fields'] = array();
			$n = 0;
			if(is_array($this->fields)) {
				foreach($this->fields as $name => $data)
				{
					if (is_array($data['values']))
					{
						$values = '';
						foreach($data['values'] as $var => $value)
						{
							$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
						}
						$data['values'] = $values;
					}
					$content['fields'][++$n] = $data + array(
						'typ' => '',
						'name'   => $name
					);
					$preserv_fields[$n]['old_name'] = $name;
					$readonlys['fields']["create$name"] = True;
				}
			}

			//$content['fields'][++$n] = array('typ'=>'','order' => 10 * $n);	// new line for create
			$content['fields'][++$n] = array('typ' => '', 'label'=>'', 'help'=>'', 'values'=>'', 'len'=>'', 'rows'=>'', 'order'=>10 * $n, 'name'=>'');			
			
			$readonlys['fields']["delete[]"] = True;

			//echo '<p>uicustomfields.edit(content = <pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
			//echo 'readonlys = <pre style="text-align: left;">'; print_r($readonlys); echo "</pre>\n";
			$this->tmpl->read('timesheet.customfields');
			$this->tmpl->exec('timesheet.uicustomfields.edit',$content,array(
				'type'      => $this->cf_types,			
			),$readonlys,array('fields' => $preserv_fields));
		}

		function update_fields(&$content)
		{				
			$fields = &$content['fields'];
			$create = $fields['create'];
			unset($fields['create']);

			if ($fields['delete'])
			{
				list($delete) = each($fields['delete']);
				unset($fields['delete']);
			}

			foreach($fields as $field)
			{
				$name = trim($field['name']);
				$old_name = $field['old_name'];

				if (!empty($delete) && $delete == $old_name)
				{
					//delete all timesheet extra entries with that certain name
					$this->bo->delete_extra('',$old_name);
					unset($this->fields[$old_name]);
					continue;
				}
				if (isset($field['name']) && empty($name) && ($create || !empty($old_name)))	// empty name not allowed
				{
					$content['error_msg'] = lang('Name must not be empty !!!');
				}
				if (isset($field['old_name']))
				{
					if (!empty($name) && $old_name != $name)	// renamed
					{
						//update all timesheet_extra entries with that certain name
						$this->bo->save_extra(True,$old_name,$name);	
						unset($this->fields[$old_name]);
					}
					elseif (empty($name))
					{
						$name = $old_name;
					}
				}
				elseif (empty($name))		// new item and empty ==> ignore it
				{
					continue;
				}
				$values = array();
				if (!empty($field['values']))
				{
					foreach(explode("\n",$field['values']) as $line)
					{
						list($var,$value) = split('=',trim($line),2);
						$var = trim($var);
						$values[$var] = empty($value) ? $var : $value;
					}
				}
				$this->fields[$name] = array(
					'type'  => $field['type'],
					'label' => empty($field['label']) ? $name : $field['label'],
					'help'  => $field['help'],
					'values'=> $values,
					'len'   => $field['len'],
					'rows'  => intval($field['rows']),
					'order' => intval($field['order'])
				);
			}
			if (!function_exists('sort_by_order'))
			{
				function sort_by_order($arr1,$arr2)
				{
					return $arr1['order'] - $arr2['order'];
				}
			}
			uasort($this->fields,sort_by_order);

			$n = 0;
			foreach($this->fields as $name => $data)
			{
				$this->fields[$name]['order'] = ($n += 10);
			}
		}

		function update(&$content)
		{
			$this->update_fields($content);
			// save changes to repository
			$this->save_repository();
		}


		function save_repository()
		{		
			// save changes to repository

			//echo '<p>uicustomfields::save_repository() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
			$this->config->value('customfields',$this->fields);
			$this->config->save_repository();
		}
	}
