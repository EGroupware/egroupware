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
	* Custiomfields class -  manages customfield definitions in egw_config table
	*
	* The repository name (config_name) is 'customfields'.
	*
	* @license GPL
	* @author Ralf Becker <ralfbecker-AT-outdoor-training.de>
	* @author Cornelius Weiss <nelius-AT-von-und-zu-weiss.de>
	* @package admin
	*/
	class customfields
	{
	
		/**
		* @var string $appname string appname of app which want to add / edit its customfields
		*/
		var $appname;
		
		/**
		* @var array $types array with allowd types of customfields
		*/
		var $types = array(
			'text'     => 'Text',
			'label'    => 'Label',
			'select'   => 'Selectbox',
			'radio'    => 'Radiobutton',
			'checkbox' => 'Checkbox',
		);
		
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
			$this->appname = $appname ? $appname : $_GET['appname'];
			$this->tmpl =& CreateObject('etemplate.etemplate');
			$this->config =& CreateObject('phpgwapi.config',$this->appname);
			
			$GLOBALS['egw']->translation->add_app('infolog');	// til we move the translations
		}

		/**
		 * Edit/Create Custom fields with type
		 *
		 * @author Ralf Becker <ralfbecker-AT-outdoor-training.de>
		 * @param array $content Content from the eTemplate Exec
		 */
		function edit($content = null)
		{
			if (is_array($content))
			{
				// setting our app again
				$this->config->config($this->appname = $content['appname']);
				$this->fields = $this->get_customfields();

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
							$GLOBALS['egw']->redirect_link($content['referer'] ? $content['referer'] : '/admin/index.php');
							exit;
					}
				}
				$referer = $content['referer'];
			}
			else
			{
				$this->fields = $this->get_customfields();

				list($type) = each($this->types);
				$content = array(
					'type' => $type,
				);
				$referer = $GLOBALS['egw']->common->get_referer();
			}
			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps'][$this->appname]['title'].' - '.lang('Custom fields');

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
				'referer' => $referer,
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
				if (isset($field['old_name']))
				{
					if (empty($name))	// empty name not allowed
					{
						$content['error_msg'] = lang('Name must not be empty !!!');
						$name = $old_name;
					}
					if (!empty($name) && $old_name != $name)	// renamed
					{
						unset($this->fields[$old_name]);
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

		/**
		* create a new custom field
		*/
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
		* @return array with customfields
		*/
		function get_customfields()
		{
			$config = $this->config->read_repository();
			//merge old config_name in phpgw_config table
			$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';
			
			return is_array($config[$config_name]) ? $config[$config_name] : array();
		}
	}
