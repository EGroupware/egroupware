<?php
	/**************************************************************************\
	* eGroupWare - admin: Custom fields                                        *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	* class custiomfields
	* manages customfield definitions in phpgw_config table
	*
	* the repository name (config_name) will be 'customfields'.
	*/
	class customfields
	{
	
		/**
		* @var $appname string appname of app which want to add / edit its customfields
		*/
		var $appname = false;
		
		/**
		* @var $types array with allowd types of customfields
		*/
		var $types = array();
		
		/**
		* @var $types2 array with userdefiened types e.g. type of infolog
		*/
		var $types2 = array();
		
		
		var $public_functions = array
		(
			'edit' => True
		);

		function customfields($appname='')
		{
			$this->types = array(
				'text' => lang('textfield'),
				'label' => lang('label'),
				'select' => lang('selectbox'),
				'radio' => lang('radiobox'),
				'checkbox' => lang('checkbox'),
			);
			$this->appname = $_GET['appname'] ? $_GET['appname'] : $appname;
			$this->tmpl =& CreateObject('etemplate.etemplate');
			$this->config =& CreateObject('phpgwapi.config',$this->appname);
		}

		/**
		 * @author ralfbecker
		 * Edit/Create Custom fields with type
		 *
		 * @param $content Content from the eTemplate Exec
		 */
		function edit($content = 0)
		{
			$this->appname = $this->appname ? $this->appname : $content['appname'];
			$this->config =& CreateObject('phpgwapi.config',$this->appname);
			$this->fields = $this->get_customfields();

			if (is_array($content))
			{
				//echo '<pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
				if($content['fields']['delete'] || $content['fields']['create'])
				{
					if($content['fields']['delete'])
					{
						$this->delete($content);
					}
					elseif($content['fields']['create'])
					{
						$this->create($content);
					}
				}
				else
				{
					list($action) = @each($content['button']);
					switch($action)
					{
						default:
							if (!$content['fields']['create'] && !$content['fields']['delete'])
							{
								break;	// type change
							}
						case 'save':
						case 'apply':
							$this->update($content);
							if ($action != 'save')
							{
								break;
							}
						case 'cancel':
							$GLOBALS['egw']->redirect_link('/admin/');
							exit;
					}
				}
			}
			else
			{
				list($type) = each($this->types);
				$content = array(
					'type' => $type,
				);
			}
			$GLOBALS['egw_info']['flags']['app_header'] = lang($this->appname).' - '.lang('Custom fields');

			$readonlys = array();

			//echo 'customfields=<pre style="text-align: left;">'; print_r($this->fields); echo "</pre>\n";
			$content['fields'] = array();
			$n = 0;
			foreach($this->fields as $name => $data)
			{
				if(!is_array($data))
				{
					$data = array();
					$data['label'] = $name;
					$data['order'] = ($n+1) * 10;
				}
				if (is_array($data['values']))
				{
					$values = '';
					foreach($data['values'] as $var => $value)
					{
						$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
					}
					$data['values'] = $values;
				}
				$content['fields'][++$n] = (array)$data + array(
					'name'   => $name
				);
				$preserv_fields[$n]['old_name'] = $name;
				$readonlys['fields']["create$name"] = True;
			}
			$content['fields'][++$n] = array('name'=>'','order' => 10 * $n);	// new line for create
			$readonlys['fields']["delete[]"] = True;
			//echo '<p>uicustomfields.edit(content = <pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
			//echo 'readonlys = <pre style="text-align: left;">'; print_r($readonlys); echo "</pre>\n";
			$this->tmpl->read('admin.customfields');
			$this->tmpl->exec('admin.customfields.edit',$content,array(
				'type'     => $this->types,
				'type2' => $this->types2,
			),$readonlys,array(
				'fields' => $preserv_fields,
				'appname' => $this->appname,
			));
		}

		function update_fields(&$content)
		{
			foreach($content['fields'] as $field)
			{
				$name = trim($field['name']);
				$old_name = $field['old_name'];

				if (!empty($delete) && $delete == $old_name)
				{
					unset($this->fields[$old_name]);
					continue;
				}
				if (empty($name) && !empty($old_name))	// empty name not allowed
				{
					$content['error_msg'] = lang('Name must not be empty !!!');
				}
				if (isset($field['old_name']))
				{
					if (!empty($name) && $old_name != $name)	// renamed
					{
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
					'type2'	=> $field['type2'],
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

		/**
		* deletes custom field from customfield definitions
		*/
		function delete(&$content)
		{
			unset($this->fields[key($content['fields']['delete'])]);
			
			// save changes to repository
			$this->save_repository();
		}

		function create(&$content)
		{
			$new_name = trim($content['fields'][count($content['fields'])-1]['name']);
			if (empty($new_name) || isset($this->fields[$new_name]))
			{
				$content['error_msg'] .= empty($new_name) ?
					lang('You have to enter a name, to create a new field!!!') :
					lang("Field '%1' already exists !!!",$new_name);
			}
			else
			{
				$this->fields[$new_name] = $content['fields'][count($content['fields'])-1];
				if(!$this->fields[$new_name]['label']) $this->fields[$new_name]['label'] = $this->fields[$new_name]['name'];
				$this->save_repository();
			}
			return;
		}

		/**
		* save changes to repository
		*/
		function save_repository()
		{
			//echo '<p>uicustomfields::save_repository() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
			$this->config->value('customfields',$this->fields);

			$this->config->save_repository();
		}
		
		/**
		* get customfields of using application
		*
		* @author Cornelius Weiss
		* @retrun array with customfields
		*/
		function get_customfields()
		{
			$config = $this->config->read_repository();
			//merge old config_name in phpgw_config table
			$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';
			
			return $config[$config_name];
		}
	}
