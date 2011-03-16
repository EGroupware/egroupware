<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/** bo to define {im|ex}ports
 *
 * @todo make this class an egw_record_pool!
 */
class importexport_definitions_bo {

	const _appname = 'importexport';
	const _defintion_table = 'egw_importexport_definitions';

	/**
	 * @var so_sql holds so_sql
	 */
	private $so_sql;

	/**
	 * @var array hold definitions
	 */
	private $definitions;

	public function __construct($_query=false, $ignore_acl = false)
	{
		$this->so_sql = new so_sql(self::_appname, self::_defintion_table );
		if ($_query) {
			$definitions = $this->so_sql->search($_query, false);
			foreach ((array)$definitions as $definition) {
				if(self::is_permitted($definition) || $ignore_acl) $this->definitions[] = $definition['definition_id'];
			}
		}
	}

	/**
	 * gets array of definition ids
	 *
	 * @return array
	 */
	public function get_definitions() {
		return $this->definitions;
	}
	public function read($definition_id) {
            $definition = new importexport_definition( $definition_id['name'] );
            return $definition->get_record_array();
	}
	/**
	 * deletes a defintion
	 *
	 * @param array $keys
	 */
	public function delete($keys) {
		$this->so_sql->delete(array('definition_id' => $keys));
		// clear private cache
		foreach ($keys as $key) {
			unset($this->definitions[array_search($key,$this->definitions)]);
		}
	}

	/**
	* Save a definition
	*
	* @param definition $definition
	*/
	public function save(Array $data) {
		$definition = new importexport_definition();
		$definition->set_record($data);
		$definition->save($data['definition_id']);
	}

	/**
	 * checkes if user if permitted to access given definition
	 *
	 * @param array $_definition
	 * @return bool
	 */
	static public function is_permitted($_definition) {
		$allowed_user = is_array($_definition['allowed_users']) ? $_definition['allowed_users'] : explode(',',$_definition['allowed_users']);
		$this_user_id = $GLOBALS['egw_info']['user']['account_id'];
		$this_membership = $GLOBALS['egw']->accounts->memberships($this_user_id, true);
		$this_membership[] = $this_user_id;
		$alluser = array_intersect($allowed_user,$this_membership);
		return ($this_user_id == $_definition['owner'] || count($alluser) > 0);
	}

	/**
	 * exports definitions
	 *
	 * @param array $keys to export
	 */
	public function export($keys)
	{
		$export_data = array('metainfo' => array(
			'type' => 'importexport definitions',
			'charset' => translation::charset(),
			'entries' => count($keys),
		));

		$export_data['definitions'] = array();
		foreach ($keys as $definition_id) {
			$definition = new importexport_definition( $definition_id );
			$export_data['definitions'][$definition->name] = $definition->get_record_array();
			$export_data['definitions'][$definition->name]['allowed_users'] =
				importexport_helper_functions::account_id2name(
					$export_data['definitions'][$definition->name]['allowed_users']
				);
			if($export_date['definitions'][$definition->name]['owner']) {
				$export_data['definitions'][$definition->name]['owner'] =
					importexport_helper_functions::account_id2name(
						$export_data['definitions'][$definition->name]['owner']
					);
			} else {
				unset($export_data['definitions'][$definition->name]['owner']);
			}
			unset($export_data['definitions'][$definition->name]['definition_id']);
			unset($export_data['definitions'][$definition->name]['description']);
			unset($export_data['definitions'][$definition->name]['user_timezone_read']);
			unset($export_data['definitions'][$definition->name]['plugin_options']['user_timezone_read']);
			unset($definition);
		}


		$xml = new importexport_arrayxml();
		return $xml->array2xml($export_data, 'importExportDefinitions');
	}

	/**
	 * imports definitions from file
	 *
	 * @param string $import_file
	 * @throws Exeption
	 * @return void
	 */
	public static function import( $_import_file )
	{
		if ( !is_file( $_import_file ) ) {
			throw new Exception("'$_import_file' does not exist or is not readable" );
		}

		$data = importexport_arrayxml::xml2array( file_get_contents( $_import_file ) );

		$metainfo = $data['importExportDefinitions']['metainfo'];
		$definitions = $data['importExportDefinitions']['definitions'];
		unset ( $data );

		// convert charset into internal used charset
		$definitions = translation::convert(
			$definitions,
			$metainfo['charset'],
			translation::charset()
		);

		// save definition(s) into internal table
		foreach ( $definitions as $name => $definition_data )
		{
			// convert allowed_user
			$definition_data['allowed_users'] = importexport_helper_functions::account_name2id( $definition_data['allowed_users'] );
			$definition_data['owner'] = importexport_helper_functions::account_name2id( $definition_data['owner'] );

			$definition = new importexport_definition( $definition_data['name'] );

			// Only update if the imported is newer
			if($definition->modified < $definition_data['modified'] || $definition->modified == 0)
			{
				$definition_id = $definition->get_identifier() ? $definition->get_identifier() : NULL;

				$definition->set_record( $definition_data );
				$definition->save( $definition_id );
			}
		}
	}

}

