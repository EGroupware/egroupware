<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class definition
 *
 * definitions are ojects with all nessesary information to do
 * complete import or export. All options needed for an explicit {Im|Ex}port
 * are in one assiozative array which is complely managed by {Im|Ex}port plugins
 * @todo testing
 */
class importexport_definition implements importexport_iface_egw_record {

	const _appname = 'importexport';
	const _defintion_talbe = 'egw_importexport_definitions';

	private $attributes = array(
		'definition_id' => 'string',
		'name' => 'string',
		'application' => 'string',
		'plugin' => 'string',
		'type' => 'string',
		'allowed_users' => 'array',
		'plugin_options' => 'array',
		'owner' => 'int',
		'description' => 'string',
		'modified' => 'timestamp'
	);

	/**
	 * @var so_sql holds so_sql object
	 */
	private $so_sql;

	/**
	 * @var array internal representation of definition
	 */
	private $definition = array();

	/**
	 * @var int holds current user
	 */
	private $user;

	/**
	 * @var bool is current user an admin?
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
		$this->is_admin = $GLOBALS['egw_info']['user']['apps']['admin'] || $GLOBALS['egw_setup'] ? true : false;
		// compability to string identifiers
		if (is_string($_identifier) && strlen($_identifier) > 3) $_identifier = $this->name2identifier($_identifier);

		if ((int)$_identifier != 0) {
			$this->definition = $this->so_sql->read(array('definition_id' => $_identifier));
			if ( empty( $this->definition ) ) {
				throw new Exception('Error: No such definition with identifier :"'.$_identifier.'"!');
			}
			if ( !( importexport_definitions_bo::is_permitted($this->get_record_array()) || $this->is_admin)) {
				throw new Exception('Error: User "'.$this->user.'" is not permitted to get definition with identifier "'.$_identifier.'"!');
			}
			$options_data = importexport_arrayxml::xml2array( $this->definition['plugin_options'] );
			$this->definition['plugin_options'] = $options_data['root'];
		}
	}

	/**
	 * compability function for string identifiers e.g. used by cli
	 *
	 * @param string $_name
	 * @return int
	 */
	private function name2identifier( $_name ) {
		$identifiers = $this->so_sql->search(array('name' => $_name),true);
		if (isset($identifiers[1])) {
			throw new Exception('Error: Definition: "'.$_name. '" is not unique! Can\'t convert to identifier');
		}
		if ( empty( $identifiers[0] ) ) {
			// not a good idea, till we don't have different exceptions so far
			// throw new Exception('Error: No such definition :"'.$_name.'"!');
			$identifiers = array( array( 'definition_id' => 0 ) );
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
			case 'plugin_options' :
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
			case 'plugin_options' :
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
		return explode(',',substr($this->definition['allowed_users'],1,-1));
	}

	/**
	 * sets allowed users
	 *
	 * @param array $_allowed_users
	 */
	private function set_allowed_users( $_allowed_users ) {
		$this->definition['allowed_users'] = ','.implode(',',(array)$_allowed_users) .',';
	}

	/**
	 * gets options
	 *
	 * @return array
	 */
	private function get_options() {
		return $this->definition['plugin_options'];
	}

	/**
	 * sets options
	 *
	 * @param array $options
	 */
	private function set_options(array $_plugin_options) {
		$this->definition['plugin_options'] = $_plugin_options;
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
		$definition['plugin_options'] = $this->get_options();
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
	public function set_record( array $_record ) {
		$this->definition = array_intersect_key( $_record, $this->attributes );

		// anything which is not an attribute is perhaps a plugin_option.
		// If not, it gets whiped out on save time.
		foreach ( $_record as $attribute => $value) {
			if ( !array_key_exists( $attribute, $this->attributes ) ) {
				$this->definition['plugin_options'][$attribute] = $value;
			}
		}

		$this->plugin = $_record['plugin'];

		// convert plugin_options into internal representation
		$this->set_allowed_users( $this->definition['allowed_users'] );
		$this->set_options( $this->definition['plugin_options'] ? $this->definition['plugin_options'] : array());
	}

	/**
	 * gets identifier of this record
	 *
	 * @return int identifier of this record
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
		if ( strlen($this->definition['name']) < 3 ) {
			throw new Exception('Error: Can\'t save definition, no valid name given!');
		}

		$this->so_sql->data = $this->definition;
		$this->so_sql->data['plugin_options'] = importexport_arrayxml::array2xml( $this->definition['plugin_options'] );
		$this->so_sql->data['modified'] = time();
		if ($this->so_sql->save( array( 'definition_id' => $_dst_identifier ))) {
			throw new Exception('Error: so_sql was not able to save definition: '.$this->get_identifier());
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
