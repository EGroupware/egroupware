<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.iface_egw_record.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/**
 * class definition
 * 
 * definitions are ojects with all nessesary information to do 
 * complete import or export. All options needed for an explicit {Im|Ex}port
 * are in one assiozative array which is complely managed by {Im|Ex}port plugins
 * @todo testing
 */
class definition implements iface_egw_record {
	
	const _appname = 'importexport';
	const _defintion_talbe = 'egw_importexport_definitions';
	
	private $attributes = array(
		'definition_id' => 'string',
		'name' => 'string',
		'application' => 'string',
		'plugin' => 'string',
		'type' => 'string',
		'allowed_users' => 'array',
		'options' => 'array',
		'owner' => 'int',
	);
	
	/**
	 * holds so_sql object
	 *
	 * @var so_sql
	 */
	private $so_sql;
	
	/**
	 * internal representation of definition
	 *
	 * @var unknown_type
	 */
	private $definition = array();
	
	/**
	 * holds current user
	 *
	 * @var int
	 */
	private $user;
	
	/**
	 * is current user an admin?
	 *
	 * @var bool
	 */
	private $is_admin;
	
	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' ) {
		$this->so_sql = new so_sql(self::_appname ,self::_defintion_talbe);
		$this->user = $GLOBALS['egw_info']['user']['user_id'];
		$this->is_admin = $GLOBALS['egw_info']['user']['apps']['admin'] ? true : false;
		// compability to string identifiers
		if (is_string($_identifier) && strlen($_identifier) > 3) $_identifier = $this->name2identifier($_identifier);
		
		if ((int)$_identifier != 0) {
			$this->so_sql->read(array('definition_id' => $_identifier));
			if (empty($this->so_sql->data)) {
				throw new Exception('Error: No such definition with identifier :"'.$_identifier.'"!');
			}
			if (!(in_array($this->user,$this->get_allowed_users()) ||	$this->get_owner() == $this->user || $this->is_admin)) {
				throw new Exception('Error: User "'.$this->user.'" is not permitted to get definition with identifier "'.$_identifier.'"!');
				$this->definition = $this->so_sql->data;
			}
			$this->definition = $this->so_sql->data;
		}
	}
	
	/**
	 * compability function for string identifiers e.g. used by cli
	 *
	 * @param string $_name
	 * @return int
	 */
	private function name2identifier($_name) {
		$identifiers = $this->so_sql->search(array('name' => $_name),true);
		if (isset($identifiers[1])) {
			throw new Exception('Error: Definition: "'.$_name. '" is not unique! Can\'t convert to identifier');
		}
		return $identifiers[0]['definition_id'];
	}
	
	public function __get($_attribute_name) {
		if (!array_key_exists($_attribute_name,$this->attributes)) {
			throw new Exception('Error: "'. $_attribute_name. '" is not an attribute defintion');
		}
		switch ($_attribute_name) {
			case 'allowed_users' :
				return $this->get_allowed_users();
			case 'options' :
				return $this->get_options();
			default :
				return $this->definition[$_attribute_name];
		}
	}
	
	public function __set($_attribute_name,$_data) {
		if (!array_key_exists($_attribute_name,$this->attributes)) {
			throw new Exception('Error: "'. $_attribute_name. '" is not an attribute defintion');
		}
		switch ($_attribute_name) {
			case 'allowed_users' :
				return $this->set_allowed_users($_data);
			case 'options' :
				return $this->set_options($_data);
			default :
				$this->definition[$_attribute_name] = $_data;
				return;
		}
	}
	
	/**
	 * gets users who are allowd to use this definition
	 *
	 * @return array
	 */
	private function get_allowed_users() {
		return explode(',',$this->definition['allowed_users']);
	}
	
	/**
	 * sets allowed users
	 *
	 * @param array $_allowed_users
	 */
	private function set_allowed_users($_allowed_users) {
		$this->definition['allowed_users'] = implode(',',(array)$_allowed_users);
	}
	
	/**
	 * gets options
	 *
	 * @return array
	 */
	private function get_options() {
		// oh compat funct to be removed!
		if(array_key_exists('plugin_options',$this->definition)) {
			$this->definition['options'] = $this->definition['plugin_options'];
			unset($this->definition['plugin_options']);
		}
		return unserialize($this->definition['options']);
	}
	
	/**
	 * sets options
	 *
	 * @param array $options
	 */
	private function set_options(array $_options) {
		$this->definition['options'] = serialize($_options);
	}
	
	/**
	 * converts this object to array. 
	 * @abstract We need such a function cause PHP5
	 * dosn't allow objects do define it's own casts :-(
	 * once PHP can deal with object casts we will change to them!
	 *
	 * @return array complete record as associative array
	 */
	public function get_record_array() {
		$definition = $this->definition;
		$definition['allowed_users'] = $this->get_allowed_users();
		$definition['options'] = $this->get_options();
		return $definition;
	}
	
	/**
	 * gets title of record
	 * 
	 *@return string tiltle
	 */
	public function get_title() {
		return $this->definition['name'];
	}
	
	/**
	 * sets complete record from associative array
	 *
	 * @return void
	 */
	public function set_record(array $_record) {
		$this->definition = $_record;
	}
	
	/**
	 * gets identifier of this record
	 *
	 * @return string identifier of this record
	 */
	public function get_identifier() {
		return $this->definition['definition_id'];
	}
	
	
	/**
	 * saves record into backend
	 * 
	 * @return string identifier
	 */
	public function save ( $_dst_identifier ) {
		if ($owner = $this->get_owner() && $owner != $this->user && !$this->is_admin) {
			throw ('Error: User '. $this->user. ' is not allowed to save this definition!');
		}
		if ($this->so_sql->save($_dst_identifier)) {
			throw('Error: so_sql was not able to save definition: '.$this->get_identifier());
		}
		return $this->definition['definition_id'];
	}
	
	/**
	 * copys current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier ) {
		$dst_object = clone $this;
		try {
			$dst_object->set_owner($this->user);
			$dst_identifier = $dst_object->save($_dst_identifier);
		}
		catch(exception $Exception) {
			unset($dst_object);
			throw $Exception;
		}
		unset ($dst_object);
		return $dst_identifier;
	}
	
	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier ) {
		if ($this->user != $this->get_owner() && !$this->is_admin) {
			throw('Error: User '. $this->user. 'does not have permissions to move definition '.$this->get_identifier());
		}
		$old_object = clone $this;
		try {
			$dst_identifier = $this->save($_dst_identifier);
			$old_object->delete();
		}
		catch(exception $Exception) {
			unset($old_object);
			throw $Exception;
		}
		unset($old_object);
		return $dst_identifier;
	}
	
	/**
	 * delets current record from backend
	 * @return void
	 * 
	 */
	public function delete () {
		if($this->user != $this->get_owner() && !$this->is_admin) {
			throw('Error: User '. $this->user. 'does not have permissions to delete definition '.$this->get_identifier());
		}
		if(!$this->so_sql->delete()) {
			throw('Error: so_sql was not able to delete definition: '.$this->get_identifier());
		}
	}
	
	/**
	 * destructor
	 *
	 */
	public function __destruct() {
		unset($this->so_sql);
	}
	
}