<?php
/**
 * eGroupWare import CSV plugin to import resources
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */


/**
 * class to import resources from CSV
 */
class resources_import_csv extends importexport_basic_import_csv  {


	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists' );

	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function init(importexport_definition $_definition ) {

		// fetch the resource bo
		$this->bo = new resources_bo();

		// For adding ACLs
		$this->acl_bo = CreateObject('resources.bo_acl',True);

		// For checking categories
		$this->start_time = time();
	}

	/**
	* Import a single record
	*
	* You don't need to worry about mappings or translations, they've been done already.
	* You do need to handle the conditions and the actions taken.
	*
	* Updates the count of actions taken
	*
	* @return boolean success
	*/
	protected function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{
		// Check for an un-matched accessory of, try again on just name
		if(!is_numeric($record->accessory_of))
		{
			$accessory_of = $record->accessory_of;

			// Look for exact match in just name
			$results = $this->bo->so->search(array('name' => $record->accessory_of),array('res_id','name'));
			if(count($results) >= 1)
			{
				// More than 1 result?  Bad names.  Pick one.
				foreach($results as $result)
				{
					if($result['name'] == $record->accessory_of)
					{
						$record->accessory_of = $result['res_id'];
						break;
					}
				}
				if(is_numeric($record->accessory_of))
				{
					// Import/Export conversion gave a warning, so cancel it
					$pattern = lang('Unable to link to %1 "%2"',lang('resources'),$accessory_of) . ' - ('.lang('too many matches') . '|'.lang('no matches') . ')';
					$this->warnings[$import_csv->get_current_position()] = preg_replace($pattern, '', $this->warnings[$import_csv->get_current_position()], 1);
					// If that was the only warning, clear it for this row
					if(trim($this->warnings[$import_csv->get_current_position()]) == '')
					{
						unset($this->warnings[$import_csv->get_current_position()]);
					}
				}
			}
		}


		// Check for a new category, it needs permissions set
		$category = $GLOBALS['egw']->categories->read($record->cat_id);

		if($category['last_mod'] >= $this->start_time) {
			// New category.  Give read & write permissions to the current user's default group
			$this->acl_bo->set_rights($record['cat_id'],
				array($GLOBALS['egw_info']['user']['account_primary_group']),
				array($GLOBALS['egw_info']['user']['account_primary_group']),
				array(),
				array(),
				array()
			);
			// Refresh ACL
			//$GLOBALS['egw']->acl->read_repository();
		}
		if(!$record->accessory_of) $record->accessory_of = -1;
		//error_log(__METHOD__.__LINE__.array2string($_definition->plugin_options['conditions']));
		if ($this->definition->plugin_options['conditions']) {
		
			foreach ( $this->definition->plugin_options['conditions'] as $condition ) {
				$results = array();
				switch ( $condition['type'] ) {
					// exists
					case 'exists' :
						if($record->$condition['string']) {
							$results = $this->bo->so->search(
								array( $condition['string'] => $record->$condition['string']),
								False
							);
						}

						if ( is_array( $results ) && count( array_keys( $results )) >= 1) {
							// apply action to all contacts matching this exists condition
							$action = $condition['true'];
							foreach ( (array)$results as $resource ) {
								$record->res_id = $resource['res_id'];
								if ( $_definition->plugin_options['update_cats'] == 'add' ) {
									if ( !is_array( $resource['cat_id'] ) ) $resource['cat_id'] = explode( ',', $resource['cat_id'] );
									if ( !is_array( $record->cat_id ) ) $record->cat_id = explode( ',', $record->cat_id );
									$record->cat_id = implode( ',', array_unique( array_merge( $record->cat_id, $resource['cat_id'] ) ) );
								}
								$success = $this->action(  $action['action'], $record, $import_csv->get_current_position() );
							}
						} else {
							$action = $condition['false'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						}
						break;

					// not supported action
					default :
						die('condition / action not supported!!!');
						break;
				}
				if ($action['last']) break;
			}
		} else {
			// unconditional insert
			$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
		}
		return $success;
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param importexport_iface_egw_record $record Entry record
	 * @return bool success or not
	 */
	protected function action ( $_action, importexport_iface_egw_record &$record, $record_num = 0 ) {
		$_data = $record->get_record_array();
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bo->read($_data['res_id']);

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				
				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Backend doesn't like inserting with ID specified, it can overwrite
					unset($_data['res_id']);
				}
				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					return true;
				} else {
					$result = $this->bo->save( $_data );
					if($result && !is_numeric($result)) {
						$this->errors[$record_num] = $result;
						return false;
					} else {
						$this->results[$_action]++;
						return true;
					}
				}
			default:
				throw new egw_exception('Unsupported action');
			
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Resources CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports a list of resources from a CSV file.");
	}

	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options => array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl() {
		// lets do it!
	}

	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public function get_selectors_etpl() {
		// lets do it!
	}

	/**
        * Returns warnings that were encountered during importing
        * Maximum of one warning message per record, but you can append if you need to
        *
        * @return Array (
        *       record_# => warning message
        *       )
        */
        public function get_warnings() {
		return $this->warnings;
	}
}
?>
