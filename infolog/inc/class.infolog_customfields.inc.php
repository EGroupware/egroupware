<?php
/**
 * InfoLog - Custom fields
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * Administration of custom fields, type and status
 */
class infolog_customfields extends admin_customfields
{
	public $appname = 'infolog';
	/**
	 * Instance of the infolog BO class
	 *
	 * @var infolog_bo
	 */
	var $bo;
	/**
	 * instance of the config class for infolog
	 *
	 * @var Api\Config
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
		parent::__construct('infolog');

		$this->bo = new infolog_bo();
		$this->tmpl = new Etemplate();
		$this->content_types = &$this->bo->enums['type'];
		$this->status = &$this->bo->status;
		$this->config_data = Api\Config::read('infolog');
		$this->fields = &$this->bo->customfields;
		$this->group_owners =& $this->bo->group_owners;

		Api\Translation::add_app('infolog');
	}


	/**
	 * Hook from parent class so we can interfere and add owner & status
	 */
	protected function app_index(&$content, &$sel_options, &$readonlys, &$preserve)
	{
		unset($sel_options);	// not used, but required by function signature

		$n = 0;
		foreach($this->status[$this->content_type] as $name => $label)
		{
			$content['content_type_options']['status'][++$n] = array(
				'name'     => $name,
				'label'    => $label,
				'disabled' => False
			);
			$preserve['content_type_options']['status'][$n]['old_name'] = $name;
			if (isset($this->bo->stock_status[$this->content_type][$name]))
			{
				$readonlys['content_type_options']['status']["delete[$name]"] =
				$readonlys['content_type_options']['status'][$n.'[name]'] = True;
			}
			$readonlys['content_type_options']['status']["create$name"] = True;
		}
		$content['content_type_options']['status'][++$n] = array(
			'name'     => '',
			'label'    => '',
			'disabled' => False
		);
		$content['content_type_options']['status']['default'] = $this->status['defaults'][$this->content_type];
		$content['content_type_options']['group_owner'] = $this->group_owners[$this->content_type];
		$readonlys['content_types']['delete'] = isset($this->bo->stock_enums['type'][$this->content_type]);
	}

	function update_fields(&$content)
	{
		$fields = &$content['fields'];

		$create = $fields['create'];
		unset($fields['create']);

		if (!empty($fields['delete']))
		{
			$delete = key($fields['delete']);
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
					list($var2,$value) = explode('=',trim($line),2);
					$var = trim($var2);
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
		foreach(array_keys($this->fields) as $name)
		{
			$this->fields[$name]['order'] = ($n += 10);
		}
	}

	function update_status(&$content)
	{
		$typ = $this->content_type;
		$status = &$content['content_type_options']['status'];

		$default = $status['default'];
		unset($status['default']);

		$create = $status['create'];
		unset($status['create']);

		if (!empty($status['delete']))
		{
			$delete = key($status['delete']);
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
			$this->status['defaults'][$typ] = @key($this->status[$typ]);
		}
	}

	function update(&$content)
	{
		$old = array(
			'status' => $this->status,
			'group_owners' => $this->group_owners
		);
		$this->update_status($content);

		if ($content['content_type_options']['group_owner'])
		{
			$this->group_owners[$this->content_type] = $content['content_type_options']['group_owner'];
		}
		else
		{
			unset($this->group_owners[$this->content_type]);
		}
		$changed = array();
		foreach($old as $key => $value)
		{
			if($this->$key != $value)
			{
				// NB: Statuses are monolithic - we can't record just the one type
				// that was changed, or we loose the other types.  All status must
				// be recorded.
				$changed[$key] = $this->$key;
			}
			else
			{
				unset($old[$key]);
			}
		}
		if($changed)
		{
			$cmd = new admin_cmd_config('infolog',$changed, $old);
			$cmd->run();
		}
	}

	function delete(&$content)
	{
		if (isset($this->bo->stock_enums['type'][$content['type2']]))
		{
			$content['error_msg'] .= lang("You can't delete one of the stock types !!!");
			return;
		}
		unset($this->content_types[$content['type2']]);
		unset($this->status[$content['type2']]);
		unset($this->status['defaults'][$content['type2']]);
		unset($this->group_owners[$content['type2']]);
		$content['type2'] = key($this->content_types ?? []);

		// save changes to repository
		$this->save_repository();
	}

	function create_content_type(&$content)
	{
		$new_name = trim($content['content_types']['name']);
		if (empty($new_name))
		{
			$this->tmpl->set_validation_error('content_types[name]',lang('You have to enter a name, to create a new type!'));
			return false;
		}
		else
		{
			foreach($this->content_types as $name)
			{
				if($name == $new_name)
				{
					$this->tmpl->set_validation_error('content_types[name]',lang("type '%1' already exists !!!",$new_name));
					return false;
				}
			}
		}
		$this->content_types[$new_name] = $new_name;
		$this->status[$new_name] = array(
			'ongoing' => 'ongoing',
			'done' => 'done'
		);
		$this->status['defaults'][$new_name] = 'ongoing';

		// save changes to repository
		$this->save_repository();
		return $new_name;
	}

	function save_repository()
	{
		// save changes to repository
		Api\Config::save_value('types',$this->content_types,'infolog');
		//echo '<p>'.__METHOD__.'() \$this->status=<pre style="text-aling: left;">'; print_r($this->status); echo "</pre>\n";
		Api\Config::save_value('status',$this->status,'infolog');
		//echo '<p>'.__METHOD__.'() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
		Api\Storage\Customfields::save('infolog', $this->fields);
		Api\Config::save_value('group_owners',$this->group_owners,'infolog');
	}

	/**
	 * Change account_ids in group_owners configuration hook called from admin_cmd_change_account_id
	 *
	 * @param array $changes
	 * @return int number of changed account_ids
	 */
	public static function change_account_ids(array $changes)
	{
		unset($changes['location']);	// no change, but the hook name
		$changed = 0;

		// migrate staff account_ids
		$config = Api\Config::read('infolog');
		$old = $config['group_owners'];
		if (!empty($config['group_owners']))
		{
			foreach($config['group_owners'] as &$account_id)
			{
				if (isset($changes[$account_id]))
				{
					$account_id = $changes[$account_id];
					$changed++;
					$needs_save = true;
				}
			}
			if ($needs_save)
			{
				$cmd = new admin_cmd_config('infolog',
						array('group_owners' => $config['group_owners']),
						array('group_owners' => $old)
				);
				$cmd->run();
			}
		}

		return $changed;
	}
}
