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

	public function get_rows(&$query, &$rows, &$readonlys)
	{
		// Filter only definitions user is allowed to use
		if(!$GLOBALS['egw_info']['user']['apps']['admin']) {
			$this_membership = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
			$this_membership[] = $GLOBALS['egw_info']['user']['account_id'];
			$sql .= ' (';
			$read = array('all');
			foreach($this_membership as $id)
			{
				$read[] = 'allowed_users '.
					$GLOBALS['egw']->db->capabilities['case_insensitive_like'].' '.
					$GLOBALS['egw']->db->quote('%,'.str_replace('_','\\_',$id) .',%');
			}
			$sql .= implode(' OR ', $read);
			$sql .= ') OR owner = '.$GLOBALS['egw_info']['user']['account_id'];
			$query['col_filter'][] = $sql;
		}

		$total = $this->so_sql->get_rows($query, $rows, $readonlys);
		$ro_count = 0;
		foreach($rows as &$row) {
			// Strip off leading + trailing ,
			$row['allowed_users'] = substr($row['allowed_users'],1,-1);

			$readonlys["edit[{$row['definition_id']}]"] = $readonlys["delete[{$row['definition_id']}]"] =
				($row['owner'] != $GLOBALS['egw_info']['user']['account_id']) &&
				!$GLOBALS['egw_info']['user']['apps']['admin'];
			if($readonlys["edit[{$row['definition_id']}]"])
			{
				$row['class'] .= 'rowNoEdit';
				$ro_count++;
			}
			$row['class'] .= ' ' . $row['type'];
		}
		$readonlys['delete_selected'] = $ro_count == count($rows);
		return $total;
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
		if(is_numeric($definition_id)) {
			$this->so_sql->read($definition_id);
			$definition = new importexport_definition($this->so_sql->data['name']);
		} else {
			$definition = new importexport_definition( $definition_id['name'] );
		}
		return $definition->get_record_array();
	}
	/**
	 * deletes a defintion
	 *
	 * @param array $keys
	 */
	public function delete($keys) {
		foreach ($keys as $index => $key) {
			// Check for ownership
			$definition = $this->read($key);
			if($definition['owner'] && $definition['owner'] == $GLOBALS['egw_info']['user']['account_id'] || $GLOBALS['egw_info']['user']['apps']['admin']) {
				// clear private cache
				unset($this->definitions[array_search($key,$this->definitions)]);
			} else {
				unset($keys[$index]);
			}
		}
		if(count($keys) > 0) {
			$this->so_sql->delete(array('definition_id' => $keys));
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
		$this_membership[] = 'all';
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
			if(is_array($export_data['definitions'][$definition->name]['plugin_options'])) {
				unset($export_data['definitions'][$definition->name]['plugin_options']['user_timezone_read']);
			}
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

		// Avoid warning if no definitions found
		if(!is_array($definitions)) return lang('None found');

		// save definition(s) into internal table
		foreach ( $definitions as $name => $definition_data )
		{
			// convert allowed_user
			$definition_data['allowed_users'] = importexport_helper_functions::account_name2id( $definition_data['allowed_users'] );
			if($definition_data['all_users'] && !$definition_data['allowed_users']) $definition_data['allowed_users'] = 'all';

			$definition_data['owner'] = importexport_helper_functions::account_name2id( $definition_data['owner'] );

			$definition = new importexport_definition( $definition_data['name'] );

			// Only update if the imported is newer
			if(strtotime($definition->modified) < strtotime($definition_data['modified']) || $definition->modified == 0)
			{
				$definition_id = $definition->get_identifier() ? $definition->get_identifier() : NULL;

				$definition->set_record( $definition_data );
				$definition->save( $definition_id );
			}
		}
		return $definitions;
	}

	/**
	 * Create a matching export definition from the given import definition.
	 *
	 * Sets up the field mapping as closely as possible, and sets charset,
	 * header, conversion, etc. using the associated export plugin.  Plugin
	 * is determined by replacing 'import' with 'export'.
	 *
	 * It is not possible to handle some plugin options automatically, because they
	 * just don't have equivalents.  (eg: What to do with unknown categories)
	 *
	 * @param importexport_definition $definition Import definition
	 *
	 * @return importexport_definition Export definition
	 */
	public static function export_from_import(importexport_definition $import)
	{
		// Only operates on import definitions
		if($import->type != 'import') throw new egw_exception_wrong_parameter('Only import definitions');
		
		// Find export plugin
		$plugin = str_replace('import', 'export',$import->plugin);
		$plugin_list = importexport_helper_functions::get_plugins($import->application, 'export');
		foreach($plugin_list as $appname => $type)
		{
			$plugins = $type['export'];
			foreach($plugins as $name => $label)
			{
				if($plugin == $name) break;
			}
			if($plugin !== $name) $plugin = $name;
		}

		$export = new importexport_definition();
		
		// Common settings
		$export->name = str_replace('import', 'export',$import->name);
		if($export->name == $import->name) $export->name = $export->name . '-export';
		$test = new importexport_definition($export->name);
		if($test->name) $export->name = $export->name .'-'.$GLOBALS['egw_info']['user']['account_lid'];

		$export->application = $import->application;
		$export->plugin = $plugin;
		$export->type = 'export';
		
		// Options
		$i_options = $import->plugin_options;
		$e_options = array(
			'delimiter'	=> $i_options['fieldsep'],
			'charset'	=> $i_options['charset'],
			'begin_with_fieldnames' => $i_options['num_header_lines'] ? ($i_options['convert'] ? 'label' : 1) : 0,
			'convert'	=> $i_options['convert']
		);

		// Mapping
		foreach($i_options['field_mapping'] as $col_num => $field)
		{
			// Try to use heading from import file, if possible
			$e_options['mapping'][$field] = $i_options['csv_fields'][$col_num] ? $i_options['csv_fields'][$col_num] : $field;
		}
		// Keep field names
		$e_options['no_header_translation'] = true;

		$export->plugin_options = $e_options;

		// Permissions
		$export->owner = $import->owner;
		$export->allowed_users = $import->allowed_users;

		return $export;
	}
}

