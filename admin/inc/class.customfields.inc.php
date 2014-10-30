<?php
/**
 * eGgroupWare admin - UI for adding custom fields
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Cornelius Weiss <nelius-AT-von-und-zu-weiss.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Customfields class -  manages customfield definitions in egw_config table
 *
 * The repository name (config_name) is 'customfields'.
 *
 * Applications can have customfields by sub-type by having a template
 * named '<appname>.admin.types'.  See admin.customfields.types as an
 * example, but the template can even be empty if types are handled by the
 * application in another way.
 *
 * Applications can extend this class to customize the custom fields and handle
 * extra information from the above template by extending and implementing
 * update() and app_index().
 */
class customfields
{

	/**
	* appname of app which want to add / edit its customfields
	*
	* @var string
	*/
	var $appname;

	/**
	 * Allow custom fields to be restricted to certain users/groups
	 */
	protected $use_private = false;

	/**
	* userdefiened types e.g. type of infolog
	*
	* @var array
	*/
	var $types2 = array();
	var $content_types,$fields;

	/**
	 * Currently selected content type (if used by app)
	 * @var string
	 */
	protected $content_type = null;

	var $public_functions = array(
		'index' => true,
		'edit' => True
	);
	/**
	 * Instance of etemplate class
	 *
	 * @var etemplate
	 */
	var $tmpl;

	/**
	 * Constructor
	 *
	 * @param string $appname
	 */
	function __construct($appname='')
	{
		if (($this->appname = $appname))
		{
			$this->fields = egw_customfields::get($this->appname,true);
			$this->content_types = config::get_content_types($this->appname);
		}
		$this->so = new so_sql('phpgwapi','egw_customfields',null,'',true);
	}

	/**
	 * List custom fields
	 */
	public function index($content = array())
	{
		// determine appname
		$this->appname = $this->appname ? $this->appname : ($_GET['appname'] ? $_GET['appname'] : ($content['appname'] ? $content['appname'] : false));
		if(!$this->appname) die(lang('Error! No appname found'));

		$this->use_private = !isset($_GET['use_private']) || (boolean)$_GET['use_private'] || $content['use_private'];

		// Read fields, constructor doesn't always know appname
		$this->fields = egw_customfields::get($this->appname,true);

		$this->tmpl = new etemplate_new();
		$this->tmpl->read('admin.customfields');

		// do we manage content-types?
		$test = new etemplate_new();
		if($test->read($this->appname.'.admin.types')) $this->manage_content_types = true;

		// Handle incoming - types, options, etc.
		if($this->manage_content_types)
		{
			if(count($this->content_types) == 0)
			{
				$this->content_types = config::get_content_types($this->appname);
			}
			if (count($this->content_types)==0)
			{
				// if you define your default types of your app with the search_link hook, they are available here, if no types were found
				$this->content_types = (array)egw_link::get_registry($this->appname,'default_types');
			}
			// Set this now, we need to know it for updates
			$this->content_type = $content['content_types']['types'];

			// Common type changes - add, delete
			if($content['content_types']['delete'])
			{
				$this->delete_content_type($content);
			}
			elseif($content['content_types']['create'])
			{
				$content['content_types']['types'] = $this->create_content_type($content);
				unset($content['content_types']['name']);
			}
			// No common type change and type didn't change, try an update
			elseif($this->content_type && is_array($content) && $this->content_type == $content['old_content_type'])
			{
				$this->update($content);
			}
		}

		// Custom field deleted from nextmatch
		if($content['nm']['action'] == 'delete')
		{
			foreach($this->fields as $name => $data)
			{
				if(in_array($data['id'],$content['nm']['selected']))
				{
					unset($this->fields[$name]);
				}
			}
			// save changes to repository
			$this->save_repository();
		}
		
		$content['nm']= $GLOBALS['egw']->session->appsession('customfield-index','admin');
		if (!is_array($content['nm']))
		{
			// Initialize nextmatch
			$content['nm'] = array(
				'get_rows'       =>	'admin.customfields.get_rows',
				'no_cat'         => 'true',
				'no_filter'      => 'true',
				'no_filter2'     => 'true',
				'row_id'         => 'cf_id',
				'order'          =>	'cf_order',// IO name of the column to sort
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'actions'        => $this->get_actions()
			);
		}
		$content['nm']['appname'] = $this->appname;
		$content['nm']['use_private'] = $this->use_private;
		
		// Set up sub-types
		if($this->manage_content_types)
		{
			foreach($this->content_types as $type => $entry)
			{
				if(!is_array($entry))
				{
					$this->content_types[$type] = array('name' => $entry);
					$entry = $this->content_types[$type];
				}
				$this->types2[$type] = $entry['name'];
			}
			$sel_options['types'] = $sel_options['cf_type2'] = $this->types2;

			$content['type_template'] = $this->appname . '.admin.types';
			$content['content_types']['appname'] = $this->appname;
			$content_types = array_keys($this->content_types);
			$this->content_type = $content['content_types']['types'] ? $content['content_types']['types'] : $content_types[0];

			$content['content_type_options'] = $this->content_types[$this->content_type]['options'];
			$content['content_type_options']['type'] = $this->types2[$this->content_type];
			if ($this->content_types[$this->content_type]['non_deletable'])
			{
				$content['content_types']['non_deletable'] = true;
			}
			if ($this->content_types['']['no_add'])
			{
				$content['content_types']['no_add'] = true;
			}
			if ($content['content_types']['non_deletable'] && $content['content_types']['no_add'])
			{
				// Hide the whole line if you can't add or delete
				$content['content_types']['no_edit_types'] = true;
			}
			// do NOT allow to delete original contact content-type for addressbook,
			// as it only creates support problems as users incidently delete it
			if ($this->appname == 'addressbook' && $this->content_type == 'n')
			{
				$readonlys['content_types']['delete'] = true;
			}
			$content['nm']['type2'] = true;
		}
		else
		{
			// Disable content types
			$this->tmpl->disableElement('content_types', true);
		}
		$preserve = array(
			'appname' => $this->appname,
			'use_private' => $this->use_private,
			'old_content_type' => $this->content_type
		);

		// Allow extending app a change to change content before display
		static::app_index($content, $sel_options, $readonlys, $preserve);

		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps'][$this->appname]['title'].' - '.lang('Custom fields');

		// Some logic to make sure extending class (if there is one) gets called
		// when etemplate2 comes back instead of parent class
		$exec = get_class() == get_called_class() ? 'admin.customfields.index' : $this->appname . '.' . get_called_class() . '.index';
		
		$this->tmpl->exec($exec,$content,$sel_options,$readonlys,$preserve);
	}

	/**
	 * Edit/Create Custom fields with type
	 *
	 * @author Ralf Becker <ralfbecker-AT-outdoor-training.de>
	 * @param array $content Content from the eTemplate Exec
	 */
	function edit($content = null)
	{
		$cf_id = $_GET['cf_id'] ? (int)$_GET['cf_id'] : (int)$content['cf_id'];

		// determine appname
		$this->appname = $this->appname ? $this->appname : ($_GET['appname'] ? $_GET['appname'] : ($content['cf_app'] ? $content['cf_app'] : false));
		if(!$this->appname)
		{
			if($cf_id && $this->so)
			{
				$content = $this->so->read($cf_id);
				$this->appname = $content['cf_app'];
			}
		}
		if(!$this->appname)
		{
			die(lang('Error! No appname found'));
		}
		$this->use_private = !isset($_GET['use_private']) || (boolean)$_GET['use_private'] || $content['use_private'];

		// Read fields, constructor doesn't always know appname
		$this->fields = egw_customfields::get($this->appname,true);

		// Update based on info returned from template
		if (is_array($content))
		{
			list($action) = @each($content['button']);
			switch($action)
			{
				case 'delete':
					$this->so->delete($cf_id);
					egw_framework::refresh_opener('Saved', 'admin', $cf_id /* Conflicts with accounts 'delete'*/);
					egw_framework::window_close();
					break;
				case 'save':
				case 'apply':
					if (!empty($content['cf_values']))
					{
						$values = array();
						foreach(explode("\n",$content['cf_values']) as $line)
						{
							list($var,$value) = explode('=',trim($line),2);
							$var = trim($var);
							$values[$var] = empty($value) ? $var : $value;
						}
						$content['cf_values'] = $values;
					}
					$update_content = array();
					foreach($content as $key => $value)
					{
						if(substr($key,0,3) == 'cf_')
						{
							$update_content[substr($key,3)] = $value;
						}
					}
					egw_customfields::update($update_content);
					egw_framework::refresh_opener('Saved', 'admin', $cf_id, 'edit');
					if ($action != 'save')
					{
						break;
					}
				//fall through
				case 'cancel':
					egw_framework::window_close();
			}
		}
		else
		{
			$content['use_private'] = !isset($_GET['use_private']) || (boolean)$_GET['use_private'];
		}


		// do we manage content-types?
		$test = new etemplate_new();
		if($test->read($this->appname.'.admin.types')) $this->manage_content_types = true;

		$this->tmpl = new etemplate_new();
		$this->tmpl->read('admin.customfield_edit');

		translation::add_app('infolog');	// til we move the translations

		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps'][$this->appname]['title'].' - '.lang('Custom fields');
		$sel_options = array();
		$readonlys = array();

		//echo 'customfields=<pre style="text-align: left;">'; print_r($this->fields); echo "</pre>\n";
		$content['cf_order'] = (count($this->fields)+1) * 10;
		$content['use_private'] = $this->use_private;

		if($cf_id)
		{
			$content = array_merge($content, $this->so->read($cf_id));
			$this->appname = $content['cf_app'];
			if($content['cf_private'])
			{
				$content['cf_private'] = explode(',',$content['cf_private']);
			}
		}
		$content['cf_values'] = json_decode($content['cf_values'], true);
		if (is_array($content['cf_values']))
		{
			$values = '';
			foreach($content['cf_values'] as $var => $value)
			{
				$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
			}
			$content['cf_values'] = $values;
		}

		// Show sub-type row, and get types
		if($this->manage_content_types)
		{
			if(count($this->content_types) == 0)
			{
				$this->content_types = config::get_content_types($this->appname);
			}
			if (count($this->content_types)==0)
			{
				// if you define your default types of your app with the search_link hook, they are available here, if no types were found
				$this->content_types = (array)egw_link::get_registry($this->appname,'default_types');
			}
			foreach($this->content_types as $type => $entry)
			{
				$this->types2[$type] = is_array($entry) ? $entry['name'] : $entry;
			}
			$sel_options['cf_type2'] = $this->types2;
		}
		else
		{
			$content['no_types'] = true;
		}

		$this->tmpl->exec('admin.customfields.edit',$content,$sel_options,$readonlys,array(
			'cf_id' => $cf_id,
			'cf_app' => $this->appname,
			'use_private' => $this->use_private,
		),2);
	}

	/**
	 * Allow extending apps a change to interfere and add content to support
	 * their custom template.  This is called right before etemplate->exec().
	 */
	protected function app_index(&$content, &$sel_options, &$readonlys)
	{
		// This is just a stub.
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	protected function get_actions()
	{
		$actions = array(
			'open' => array(	// does edit if allowed, otherwise view
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=admin.customfields.edit&cf_id=$id&use_private='.$this->use_private,
				'popup' => '450x380',
				'group' => $group=1,
				'disableClass' => 'th',
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=admin.customfields.edit&appname='.$this->appname.'&use_private='.$this->use_private,
				'popup' => '450x380',
				'group' => $group,
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this entry',
				'confirm_multiple' => 'Delete these entries',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
			),
		);
		return $actions;
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
					list($var,$value) = explode('=',trim($line),2);
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
				'rows'  => (int)$field['rows'],
				'order' => (int)$field['order'],
				'private' => $field['private'],
				'needed' => $field['needed'],
			);
			if(!$this->fields[$name]['type2'] && $this->manage_content_types)
			{
				$this->fields[$name]['type2'] = (string)0;
			}
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
		$this->content_types[$this->content_type]['options'] = $content['content_type_options'];
		// save changes to repository
		$this->save_repository();
	}

	/**
	* deletes custom field from customfield definitions
	*/
	function delete_field(&$content)
	{
		unset($this->fields[key($content['fields']['delete'])]);
		// save changes to repository
		$this->save_repository();
	}

	function delete_content_type(&$content)
	{
		unset($this->content_types[$content['content_types']['types']]);
		// save changes to repository
		$this->save_repository();
	}

	/**
	* create a new custom field
	*/
	function create_field(&$content)
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

	function create_content_type(&$content)
	{
		$new_name = trim($content['content_types']['name']);
		$new_type = false;
		if (empty($new_name))
		{
			$this->tmpl->set_validation_error('content_types[name]','You have to enter a name, to create a new type!!!');
		}
		else
		{
			foreach($this->content_types as $letter => $type)
			{
				if($type['name'] == $new_name)
				{
					$this->tmpl->set_validation_error('content_types[name]',lang("type '%1' already exists !!!",$new_name));
					return false;
				}
			}
			// search free type character
			for($i=97;$i<=122;$i++)
			{
				if (!$this->content_types[chr($i)] &&
					// skip letter of deleted type for addressbook content-types, as it gives SQL error
					// content-type are lowercase, addressbook_so::DELETED_TYPE === 'D', but DB is case-insensitive
					($this->appname !== 'addressbook' || chr($i) !== strtolower(addressbook_so::DELETED_TYPE)))
				{
					$new_type = chr($i);
					break;
				}
			}
			$this->content_types[$new_type] = array('name' => $new_name);
			$this->save_repository();
		}
		return $new_type;
	}

	/**
	* save changes to repository
	*/
	function save_repository()
	{
		//echo '<p>uicustomfields::save_repository() \$this->fields=<pre style="text-aling: left;">'; print_r($this->fields); echo "</pre>\n";
		$config = new config($this->appname);
		$config->read_repository();
		$config->value('types',$this->content_types);
		$config->save_repository();

		egw_customfields::save($this->appname, $this->fields);
	}

	/**
	* get customfields of using application
	*
	* @deprecated use egw_customfields::get() direct, no need to instanciate this UI class
	* @author Cornelius Weiss
	* @param boolean $all_private_too=false should all the private fields be returned too
	* @return array with customfields
	*/
	function get_customfields($all_private_too=false)
	{
		return egw_customfields::get($this->appname,$all_private_too);
	}

	/**
	* get_content_types of using application
	*
	* @deprecated use config::get_content_types() direct, no need to instanciate this UI class
	* @author Cornelius Weiss
	* @return array with content-types
	*/
	function get_content_types()
	{
		return config::get_content_types($this->appname);
	}

	/**
	 * Get list of customfields for the nextmatch
	 */
	public function get_rows(&$query, &$rows, &$readonlys)
	{
		$rows = array();

		$query['col_filter']['cf_app'] = $query['appname'];
		$total = $this->so->get_rows($query, $rows, $readonlys);
		unset($query['col_filter']['cf_app']);

		foreach($rows as &$row)
		{
			$row['cf_values'] = json_decode($row['cf_values'], true);
			if (is_array($row['cf_values']))
			{
				$values = '';
				foreach($row['cf_values'] as $var => $value)
				{
					$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
				}
				$row['cf_values'] = $values;
			}
		}
		return $total;
	}
}
