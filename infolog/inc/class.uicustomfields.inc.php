<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog: Custom fields, typ and status                    *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uicustomfields
	{
		var $public_functions = array
		(
			'edit' => True
		);

		function uicustomfields( )
		{
			$this->bo = CreateObject('infolog.boinfolog');
			$this->tmpl = CreateObject('etemplate.etemplate');
			$this->types  = &$this->bo->enums['type'];
			$this->status = &$this->bo->status;
			$this->config = &$this->bo->config;
			$this->fields = &$this->bo->customfields;
		}

		/*!
		@function edit
		@syntax edit( $content=0 )
		@author ralfbecker
		@abstract Edit/Create an InfoLog Custom fields, typ and status
		@param $content Content from the eTemplate Exec
		*/
		function edit($content = 0)
		{
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Custom fields, typ and status');
			if (is_array($content))
			{
				//echo '<pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
				list($action) = @each($content['button']);
				switch($action)
				{
					case 'create':
						$this->create($content);
						break;
					case 'delete':
						$this->delete($content);
						break;
					default:
						if (!$content['status']['create'] && !$content['status']['delete'] &&
						    !$content['fields']['create'] && !$content['fields']['delete'])
						{
							break;	// typ change
						}
					case 'save':
					case 'apply':
						$this->update($content);
						if ($action != 'save')
						{
							break;
						}
					case 'cancel':
						$GLOBALS['phpgw']->redirect_link('/admin/');
						exit;
				}
			}
			else
			{
				list($typ) = each($this->types);
				$content = array(
					'typ' => $typ,
				);
			}
			$readonlys = array();
			$readonlys['button[delete]'] = isset($this->bo->stock_enums['type'][$content['typ']]);

			$content['status'] = array(
				'default' => $this->status['defaults'][$content['typ']]
			);
			$n = 0;
			foreach($this->status[$content['typ']] as $name => $label)
			{
				$content['status'][++$n] = array(
					'name'     => $name,
					'label'    => $label,
					'disabled' => False
				);
				$preserv_status[$n]['old_name'] = $name;
				if (isset($this->bo->stock_status[$content['typ']][$name]))
				{
					$readonlys['status']["delete[$name]"] =
					$readonlys['status'][$n.'[name]'] = True;
				}
				$readonlys['status']["create$name"] = True;
			}
			$content['status'][++$n] = array('name'=>'');	// new line for create
			$readonlys['status']["delete[]"] = True;

			//echo 'customfields=<pre style="text-align: left;">'; print_r($this->fields); echo "</pre>\n";
			$content['fields'] = array();
			$n = 0;
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
					'name'   => $name
				);
				$preserv_fields[$n]['old_name'] = $name;
				$readonlys['fields']["create$name"] = True;
			}
			$content['fields'][++$n] = array('typ'=>'','order' => 10 * $n);	// new line for create
			$readonlys['fields']["delete[]"] = True;

			//echo '<p>uicustomfields.edit(content = <pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
			//echo 'readonlys = <pre style="text-align: left;">'; print_r($readonlys); echo "</pre>\n";
			$this->tmpl->read('infolog.customfields');
			$this->tmpl->exec('infolog.uicustomfields.edit',$content,array(
				'typ'     => $this->types,
			),$readonlys,array(
				'status' => $preserv_status,
				'fields' => $preserv_fields
			));
		}

		function update_fields(&$content)
		{
			$typ = $content['typ'];
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
					'typ'   => $field['typ'],
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

		function update_status(&$content)
		{
			$typ = $content['typ'];
			$status = &$content['status'];

			$default = $status['default'];
			unset($status['default']);

			$create = $status['create'];
			unset($status['create']);

			if ($status['delete'])
			{
				list($delete) = each($status['delete']);
				unset($status['delete']);
			}

			foreach($status as $stat)
			{
				$name = trim($stat['name']);
				$old_name = $stat['old_name'];

				if (!empty($delete) && $delete == $old_name)
				{
					unset($this->status[$typ][$old_name]);
					continue;
				}
				if (isset($stat['name']) && empty($name) && ($create || !empty($old_name)))	// empty name not allowed
				{
					$content['error_msg'] = lang('Name must not be empty !!!');
				}
				if (isset($stat['old_name']))
				{
					if (!empty($name) && $old_name != $name)	// renamed
					{
						unset($this->status[$typ][$old_name]);

						if ($default == $old_name)
						{
							$default = $name;
						}
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
				$this->status[$typ][$name] = empty($stat['label']) ? $name : $stat['label'];
			}
			$this->status['defaults'][$typ] = empty($default) ? $name : $default;
			if (!isset($this->status[$typ][$this->status['defaults'][$typ]]))
			{
				list($this->status['defaults'][$typ]) = @each($this->status[$typ]);
			}
		}

		function update(&$content)
		{
			$this->update_status($content);
			$this->update_fields($content);

			// save changes to repository
			$this->save_repository();
		}

		function delete(&$content)
		{
			if (isset($this->bo->stock_enums['type'][$content['typ']]))
			{
				$content['error_msg'] .= lang("You can't delete one of the stock types !!!");
				return;
			}
			unset($this->types[$content['typ']]);
			unset($this->status[$content['typ']]);
			unset($this->status['defaults'][$content['typ']]);
			$content['typ'] = '';

			// save changes to repository
			$this->save_repository();
		}

		function create(&$content)
		{
			$new_name = trim($content['new_name']);
			unset($content['new_name']);
			if (empty($new_name) || isset($this->types[$new_name]))
			{
				$content['error_msg'] .= empty($new_name) ?
					lang('You have to enter a name, to create a new typ!!!') :
					lang("Typ '%1' already exists !!!",$new_name);
			}
			else
			{
				$this->types[$new_name] = $new_name;
				$this->status[$new_name] = array(
					'ongoing' => 'ongoing',
					'done' => 'done'
				);
				$this->status['defaults'][$new_name] = 'ongoing';

				// save changes to repository
				$this->save_repository();

				$content['typ'] = $new_name;	// show the new entry
			}
		}

		function save_repository()
		{
			// save changes to repository
			$this->config->value('types',$this->types);
			//echo '<p>uicustomfields::save_repository() \$this->status=<pre style="text-aling: left;">'; print_r($this->status); echo "</pre>\n";
			$this->config->value('status',$this->status);
			//echo '<p>uicustomfields::save_repository() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
			$this->config->value('customfields',$this->fields);

			$this->config->save_repository();
		}
	}
