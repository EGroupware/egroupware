<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Knut Moeller <k.moeller@metaways.de>
 * @copyright Knut Moeller <k.moeller@metaways.de>
 * @version $Id: $
 */

/**
 * export plugin of addressbook
 */
class timesheet_export_openoffice implements importexport_iface_export_plugin {



	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {

		$options = $_definition->options;

		$botimesheet = new timesheet_bo();

			// get current display selection

		$query = $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP);

		$bo_pm = CreateObject('projectmanager.boprojectmanager');
	    $childs = $bo_pm->children( $query['col_filter']['pm_id'] );
	    $childs[] = $query['col_filter']['pm_id'];
	    $pmChilds = implode(",",$childs);
	    $botimesheet->db->select(    'egw_links','link_id, link_id1','',
	                   __LINE__,__FILE__,False,
	                   '',False,0,
	                   'JOIN egw_pm_projects ON (pm_id = link_id2)
						JOIN egw_timesheet ON (ts_id=link_id1)
	                    WHERE
	                       link_app1 = \'timesheet\' AND
	                       link_app2 = \'projectmanager\' AND
	                       link_id2 IN ('.$pmChilds.') AND ts_start >= '.$query['startdate'].' AND ts_start < '.$query['enddate'] );

		while($row = $botimesheet->db->row(true)) {
	       $tslist[$row['link_id']] = $row['link_id1'];
	    }

//error_log(print_r($query,true));
//error_log(print_r($tslist,true));

		$ids = implode(',', $tslist);

		// no result
		if (strlen($ids)==0) return false;

//error_log('IDS: '.$ids);

			// get full result

		$rows = $botimesheet->search(array("egw_timesheet.ts_id IN ($ids)"),
									false, 'ts_owner,ts_start', array('cat_name') ,
									'', false, 'AND', false, null,
					'LEFT JOIN egw_categories ON (egw_categories.cat_id=egw_timesheet.cat_id AND egw_categories.cat_appname=\'timesheet\')');


		if (is_array($rows) && count($rows)>0 ) {
//error_log(print_r($rows,true));
				// export rows

			$export_object = new export_openoffice($_stream, $charset, (array)$options);
			$export_object->init();


				// get date values

			$tstamp_min = 0; 				// date range for table date entries (KW...)
			$tstamp_max = 0;

			foreach($rows as $row)  {
				if ($row['ts_start']<$tstamp_min || $tstamp_min == 0) $tstamp_min = $row['ts_start'];
				if ($row['ts_start']>$tstamp_max || $tstamp_max == 0) $tstamp_max = $row['ts_start'];
			}


				// init summarytable
			$export_object->create_summarytable();


				// user tables
			$last_username = 0;
			$first_table = true;

			foreach($rows as $row)  {

					// read in extra values (custom fields)
				$extrarows = $botimesheet->search(array("egw_timesheet.ts_id=".$row['ts_id']), false, '', 'ts_extra_name,ts_extra_value' ,
											'', false, 'AND', false, null,
											'LEFT JOIN egw_timesheet_extra ON (egw_timesheet_extra.ts_id=egw_timesheet.ts_id)'  );
				$extras = array();
				foreach($extrarows as $extrarow) {
					$extras[$extrarow['ts_extra_name']] = $extrarow['ts_extra_value'];
				}

				// change projectname
				$titleRegex = '/^.*:.*-\s(.*)$/';
				preg_match($titleRegex,  $row['ts_project'], $title);
				$row['ts_project'] = $title[1];

				// get firstname, lastname
				$res = array();
//				$nameRegex = '/\s(.*)\s(.*)$/';  // z.B. "[admin] Richard Blume"
				$nameRegex = '/(.*),\s(.*)$/';  // z.B. "Blume, Richard"
				preg_match($nameRegex, $GLOBALS['egw']->common->grab_owner_name($row['ts_owner']), $res);
// error_log('|'.$name . '| -> ' . print_r($res,true));
				$firstname = $res[2];
				$lastname = $res[1];
				$fullname = $firstname.' '.$lastname;

				// new table on username change
				if ($row['ts_owner'] != $last_username) {

						// create sum as last tablerow before creating new table
					if (!$first_table) {
						$export_object->summarize();
					}
					else {
						$first_table = false;
					}

						// create new table sheet
					$export_object->create_usertable($lastname,
										$fullname, $tstamp_min, $tstamp_max);

				}
				$export_object->add_record($row, $extras);
				$last_username = $row['ts_owner'];
			}
			$export_object->summarize();  // for last table

				// fill collected sums into sum-table
			$export_object->fill_summarytable($tstamp_min, $tstamp_max);

				// write to zipfile, cleanup
			$export_object->finalize();
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Timesheet OpenOffice export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Export to OpenOffice Spreadsheet");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'ods';
	}

	/**
	 * return html for options.
	 * this way the plugin has all opertunities for options tab
	 *
	 * @return string html
	 */
	public function get_options_etpl() {
		return 'timesheet.export_openoffice_options';
	}

	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl() {
		return '<b>Selectors:</b>';
	}
}


