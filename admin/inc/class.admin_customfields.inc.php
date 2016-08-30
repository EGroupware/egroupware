<?php
/**
 * EGgroupware admin - UI for adding custom fields
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Cornelius Weiss <nelius-AT-von-und-zu-weiss.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;

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
class admin_customfields
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
	 * Does App uses content-types
	 *
	 * @var boolean
	 */
	protected $manage_content_types = false;

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
	 * @var Description of the options or value format for each cf_type
	 */
	public static $type_option_help = array(
		'search'	=> 'set get_rows, get_title and id_field, or use @path to read options from a file in EGroupware directory',
		'select'	=> 'each value is a line like id[=label], or use @path to read options from a file in EGroupware directory',
		'radio'		=> 'each value is a line like id[=label], or use @path to read options from a file in EGroupware directory',
		'button'	=> 'each value is a line like label=[javascript]'
	);

	/**
	 * Custom fields can also have length and rows set, but these are't used for all types
	 * If not set to true here, the field will be disabled when selecting the type
	 */
	public static $type_attribute_flags = array(
		'text'		=> array('cf_len' => true, 'cf_rows' => true),
		'float'		=> array('cf_len' => true),
		'label'		=> array('cf_values' => true),
		'select'	=> array('cf_len' => false, 'cf_rows' => true, 'cf_values' => true),
		'date'		=> array('cf_len' => true, 'cf_rows' => false, 'cf_values' => true),
		'date-time'	=> array('cf_len' => true, 'cf_rows' => false, 'cf_values' => true),
		'select-account'	=> array('cf_len' => false, 'cf_rows' => true),
		'htmlarea'	=> array('cf_len' => true, 'cf_rows' => true),
		'button'	=> array('cf_values' => true),
		'ajax_select' => array('cf_values' => true),
		'radio'		=> array('cf_values' => true),
		'checkbox'	=> array('cf_values' => true),
		'filemanager' => array('cf_values' => true),
	);

	/**
	 * Constructor
	 *
	 * @param string $appname
	 */
	function __construct($appname='')
	{
		if (($this->appname = $appname))
		{
			$this->fields = Api\Storage\Customfields::get($this->appname,true);
			$this->content_types = Api\Config::get_content_types($this->appname);
		}
		$this->so = new Api\Storage\Base('phpgwapi','egw_customfields',null,'',true);
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
		$this->fields = Api\Storage\Customfields::get($this->appname,true);

		$this->tmpl = new Etemplate();
		$this->tmpl->read('admin.customfields');

		// do we manage content-types?
		$test = new Etemplate();
		if($test->read($this->appname.'.admin.types')) $this->manage_content_types = true;

		// Handle incoming - types, options, etc.
		if($this->manage_content_types)
		{
			if(count($this->content_types) == 0)
			{
				$this->content_types = Api\Config::get_content_types($this->appname);
			}
			if (count($this->content_types)==0)
			{
				// if you define your default types of your app with the search_link hook, they are available here, if no types were found
				$this->content_types = (array)Api\Link::get_registry($this->appname,'default_types');
			}
			// Set this now, we need to know it for updates
			$this->content_type = $content['content_types']['types'] ? $content['content_types']['types'] : (array_key_exists(0,$this->content_types) ? $this->content_types[0] : key($this->content_types));

			// Common type changes - add, delete
			if($content['content_types']['delete'])
			{
				$this->delete_content_type($content);
			}
			elseif($content['content_types']['create'])
			{
				if(($new_type = $this->create_content_type($content)))
				{
					$content['content_types']['types'] = $this->content_type = $new_type;
				}
				unset($content['content_types']['create']);
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

		$content['nm']= Api\Cache::getSession('admin', 'customfield-index');
		if (!is_array($content['nm']))
		{
			// Initialize nextmatch
			$content['nm'] = array(
				'get_rows'       =>	'admin.admin_customfields.get_rows',
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
		$readonlys = null;
		static::app_index($content, $sel_options, $readonlys, $preserve);

		// Make sure app css gets loaded, extending app might cause et2 to miss it
		Framework::includeCSS('admin','app');

		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps'][$this->appname]['title'].' - '.lang('Custom fields');

		// Some logic to make sure extending class (if there is one) gets called
		// when etemplate2 comes back instead of parent class
		$exec = get_class() == get_called_class() ? 'admin.admin_customfields.index' : $this->appname . '.' . get_called_class() . '.index';

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
		$this->fields = Api\Storage\Customfields::get($this->appname,true);

		// Update based on info returned from template
		if (is_array($content))
		{
			list($action) = @each($content['button']);
			switch($action)
			{
				case 'delete':
					$this->so->delete($cf_id);
					Framework::refresh_opener('Deleted', 'admin', $cf_id /* Conflicts with Api\Accounts 'delete'*/);
					Framework::window_close();
					break;
				case 'save':
				case 'apply':
					if(!$cf_id && $this->fields[$content['cf_name']])
					{
						Framework::message(lang("Field '%1' already exists !!!",$content['cf_name']),'error');
						$content['cf_name'] = '';
						break;
					}
					if(empty($content['cf_label']))
					{
						$content['cf_label'] = $content['cf_name'];
					}
					if (!empty($content['cf_values']))
					{
						$values = array();
						if($content['cf_values'][0] === '@')
						{
							$values['@'] = substr($content['cf_values'], $content['cf_values'][1] === '=' ? 2:1);
						}
						else
						{
							foreach(explode("\n",trim($content['cf_values'])) as $line)
							{
								list($var_raw,$value) = explode('=',trim($line),2);
								$var = trim($var_raw);
								$values[$var] = trim($value)==='' ? $var : $value;
							}
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
					Api\Storage\Customfields::update($update_content);
					if(!$cf_id)
					{
						$this->fields = Api\Storage\Customfields::get($this->appname,true);
						$cf_id = (int)$this->fields[$content['cf_name']]['id'];
					}
					Framework::refresh_opener('Saved', 'admin', $cf_id, 'edit');
					if ($action != 'save')
					{
						break;
					}
				//fall through
				case 'cancel':
					Framework::window_close();
			}
		}
		else
		{
			$content['use_private'] = !isset($_GET['use_private']) || (boolean)$_GET['use_private'];
		}


		// do we manage content-types?
		$test = new Etemplate();
		if($test->read($this->appname.'.admin.types')) $this->manage_content_types = true;

		$this->tmpl = new Etemplate();
		$this->tmpl->read('admin.customfield_edit');

		Api\Translation::add_app('infolog');	// til we move the translations

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
			if($content['cf_name'])
			{
				$readonlys['cf_name'] = true;
			}
			$content['cf_values'] = json_decode($content['cf_values'], true);
		}
		else
		{
			$readonlys['button[delete]'] = true;
		}
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
				$this->content_types = Api\Config::get_content_types($this->appname);
			}
			if (count($this->content_types)==0)
			{
				// if you define your default types of your app with the search_link hook, they are available here, if no types were found
				$this->content_types = (array)Api\Link::get_registry($this->appname, 'default_types');
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

		// Include type-specific value help
		foreach(self::$type_option_help as $key => $value)
		{
			$content['options'][$key] = lang($value);
		}
		$content['statustext'] = $content['options'][$content['cf_type']];
		$content['attributes'] = self::$type_attribute_flags;

		$this->tmpl->exec('admin.admin_customfields.edit',$content,$sel_options,$readonlys,array(
			'cf_id' => $cf_id,
			'cf_app' => $this->appname,
			'cf_name' => $content['cf_name'],
			'use_private' => $this->use_private,
		),2);
	}

	/**
	 * Allow extending apps a change to interfere and add content to support
	 * their custom template.  This is called right before etemplate->exec().
	 */
	protected function app_index(&$content, &$sel_options, &$readonlys, &$preserve)
	{
		unset($content, $sel_options, $readonlys, $preserve);	// not used, as this is a stub
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
				'url' => 'menuaction=admin.admin_customfields.edit&cf_id=$id&use_private='.$this->use_private,
				'popup' => '500x380',
				'group' => $group=1,
				'disableClass' => 'th',
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=admin.admin_customfields.edit&appname='.$this->appname.'&use_private='.$this->use_private,
				'popup' => '500x380',
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
		unset($this->status[$content['content_types']['types']]);
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

	/**
	 * Validate and create a new content type
	 *
	 * @param array $content
	 * @return string|boolean New type ID, or false for error
	 */
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
			foreach($this->content_types as $type)
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
					// content-type are lowercase, Api\Contacts::DELETED_TYPE === 'D', but DB is case-insensitive
					($this->appname !== 'addressbook' || chr($i) !== strtolower(Api\Contacts::DELETED_TYPE)))
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
		$config = new Api\Config($this->appname);
		$config->read_repository();
		$config->value('types',$this->content_types);
		$config->save_repository();

		Api\Storage\Customfields::save($this->appname, $this->fields);
	}

	/**
	* get customfields of using application
	*
	* @deprecated use Api\Storage\Customfields::get() direct, no need to instanciate this UI class
	* @author Cornelius Weiss
	* @param boolean $all_private_too =false should all the private fields be returned too
	* @return array with customfields
	*/
	function get_customfields($all_private_too=false)
	{
		return Api\Storage\Customfields::get($this->appname,$all_private_too);
	}

	/**
	* get_content_types of using application
	*
	* @deprecated use Api\Config::get_content_types() direct, no need to instanciate this UI class
	* @author Cornelius Weiss
	* @return array with content-types
	*/
	function get_content_types()
	{
		return Api\Config::get_content_types($this->appname);
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
