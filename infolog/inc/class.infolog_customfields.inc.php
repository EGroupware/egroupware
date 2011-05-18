<?php
/**
 * InfoLog - Custom fields
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-6 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Administration of custom fields, type and status
 */
class infolog_customfields
{
	var $public_functions = array(
		'edit' => True
	);
	/**
	 * Instance of the infolog BO class
	 *
	 * @var boinfolog
	 */
	var $bo;
	/**
	 * Instance of the etemplate class
	 *
	 * @var etemplate
	 */
	var $tmpl;
	/**
	 * instance of the config class for infolog
	 *
	 * @var config
	 */
	var $config_data;
	/**
	 * Group owners for certain types read from the infolog config
	 *
	 * @var array
	 */
	var $group_owners;

	function __construct( )
	{
		$this->bo = new infolog_bo();
		$this->tmpl = new etemplate();
		$this->types  = &$this->bo->enums['type'];
		$this->status = &$this->bo->status;
		$this->config_data = config::read('infolog');
		$this->fields = &$this->bo->customfields;
		$this->group_owners =& $this->bo->group_owners;
	}

	/**
	 * Edit/Create an InfoLog Custom fields, typ and status
	 *
	 * @param array $content Content from the eTemplate Exec
	 */
	function edit($content=null)
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Custom fields, typ and status');
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
					$GLOBALS['egw']->redirect_link('/admin/');
					exit;
			}
		}
		else
		{
			list($typ) = each($this->types);
			$content = array(
				'type2' => $typ,
			);
		}
		$readonlys = array();
		$readonlys['button[delete]'] = isset($this->bo->stock_enums['type'][$content['type2']]);

		$content['status'] = array(
			'default' => $this->status['defaults'][$content['type2']]
		);
		$n = 0;
		foreach($this->status[$content['type2']] as $name => $label)
		{
			$content['status'][++$n] = array(
				'name'     => $name,
				'label'    => $label,
				'disabled' => False
			);
			$preserv_status[$n]['old_name'] = $name;
			if (isset($this->bo->stock_status[$content['type2']][$name]))
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
		foreach($this->fields as $name => $field)
		{
			if (is_array($field['values']))
			{
				$values = '';
				foreach($field['values'] as $var => $value)
				{
					$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
				}
				$field['values'] = $values;
			}
			if (!$field['type2']) $field['type2']='';	// multiselect returns NULL for nothing selected (=All) which stops the autorepeat
			$content['fields'][++$n] = $field + array(
				'name'   => $name
			);
			$preserv_fields[$n]['old_name'] = $name;
			$readonlys['fields']["create$name"] = True;
		}
		$content['fields'][++$n] = array('type2'=>'','order' => 10 * $n);	// new line for create
		$readonlys['fields']["delete[]"] = True;

		$content['group_owner'] = $this->group_owners[$content['type2']];

		//echo '<p>'.__METHOD__'.(content = <pre style="text-align: left;">'; print_r($content); echo "</pre>\n";
		//echo 'readonlys = <pre style="text-align: left;">'; print_r($readonlys); echo "</pre>\n";
		$this->tmpl->read('infolog.customfields');
		$this->tmpl->exec('infolog.infolog_customfields.edit',$content,array(
			'type2'     => $this->types,
		),$readonlys,array(
			'status' => $preserv_status,
			'fields' => $preserv_fields,
		));
	}

	function update_fields(&$content)
	{
		$typ = $content['type2'];
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
					list($var,$value) = explode('=',trim($line),2);
					$var = trim($var);
					$values[$var] = empty($value) ? $var : $value;
				}
			}
			$this->fields[$name] = array(
				'type2'   => $field['type2'],
				'type'  => $field['type'],
				'label' => empty($field['label']) ? $name : $field['label'],
				'help'  => $field['help'],
				'values'=> $values,
				'len'   => $field['len'],
				'rows'  => (int)$field['rows'],
				'order' => (int)$field['order'],
				'needed' => $field['needed'],
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
		$typ = $content['type2'];
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

		if ($content['group_owner'])
		{
			$this->group_owners[$content['type2']] = $content['group_owner'];
		}
		else
		{
			unset($this->group_owners[$content['type2']]);
		}
		// save changes to repository
		$this->save_repository();
	}

	function delete(&$content)
	{
		if (isset($this->bo->stock_enums['type'][$content['type2']]))
		{
			$content['error_msg'] .= lang("You can't delete one of the stock types !!!");
			return;
		}
		unset($this->types[$content['type2']]);
		unset($this->status[$content['type2']]);
		unset($this->status['defaults'][$content['type2']]);
		unset($this->group_owners[$content['type2']]);
		list($content['type2']) = each($this->types);

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

			$content['type2'] = $new_name;	// show the new entry
		}
	}
	function save_repository()
	{
		// save changes to repository
		config::save_value('types',$this->types,'infolog');
		//echo '<p>'.__METHOD__.'() \$this->status=<pre style="text-aling: left;">'; print_r($this->status); echo "</pre>\n";
		config::save_value('status',$this->status,'infolog');
		//echo '<p>'.__METHOD__.'() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
		config::save_value('customfields',$this->fields,'infolog');
		config::save_value('group_owners',$this->group_owners,'infolog');
	}
}
