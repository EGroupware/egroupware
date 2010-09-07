<?php
/**
 * eGroupWare importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Knut Moeller <k.moeller@metaways.de>
 * @copyright Knut Moeller <k.moeller@metaways.de>
 * @version $Id: $
 */

require_once(EGW_INCLUDE_ROOT. '/timesheet/inc/class.spreadsheet.inc.php');

/**
 * class export_cmslite
 *
 * special export class (metaways openoffice calc table)
 * adapter to spreadsheet class
 *
 */
class export_openoffice implements importexport_iface_export_record
{

	/**
	 * array with field mapping in form egw_field_name => exported_field_name
	 * @var array
	 */
	protected  $mapping = array();

	/**
	 * array with conversions to be done in form: egw_field_name => conversion_string
	 * @var array
	 */
	protected  $conversion = array();

	/**
	 * array holding the current record
	 * @access protected
	 */
	protected $record = array();

	/**
	 * holds (charset) translation object
	 * @var object
	 */
	protected $translation;

	/**
	 * holds number of exported records
	 * @var unknown_type
	 */
	protected $num_of_records = 0;

	/**
	 * stream resource of csv file
	 * @var resource
	 */
	protected  $handle;

	protected  $document;
	protected  $table;

	protected  $summarytable; 	// link to first table
	protected  $summary; 		// array, project -> user -> time
	protected  $summaryweekend; // same for weekend

	private $tablecount;
	private $columncount;
	private $rowcount;



	/**
	 * constructor
	 *
	 * @param object _handle resource where records are exported to.
	 * @param string _charset charset the records are exported to.
	 * @param array _options options for specific backends
	 * @return bool
	 * @access public
	 */
	public function __construct( $_handle, array $_options ) {
		$this->handle = $_handle;
	}

	/**
	 * sets field mapping
	 *
	 * @param array $_mapping egw_field_name => csv_field_name
	 */
	public function set_mapping( array $_mapping) {

	}

	/**
	 * Sets conversion.
	 * See import_export_helper_functions::conversion.
	 *
	 * @param array $_conversion
	 */
	public function set_conversion( array $_conversion) {

	}

	/**
	 * exports a record into resource of handle
	 *
	 * @param iface_egw_record record
	 * @return bool
	 * @access public
	 */
	public function export_record( importexport_iface_egw_record $_record ) {

	}

	public function create_summarytable($_tablename='Gesamtzeiten') {
		$this->summarytable = new SpreadSheetTable($this->document, $_tablename);
		$this->tablecount++;
		$this->summary = array();
		$this->summaryweekend = array();
	}


	/**
	 * @return weekdate array (1-7) for given week
	 */
	private function week_dates($_timestamp) {
		$week_dates = array();
		$day = (int) date("w", $_timestamp);

		if ($day != 1) {
			$diff = $day - 1;
			if ($day==0) $diff = 6;
			$monday = strtotime("-".$diff." days" , $_timestamp);
		}
		else {
			$monday = $_timestamp;
		}

		for ($i=0; $i < 7; $i++) {
			$week_dates[$i+1] = strtotime("+".$i." days", $monday);
		}
	   return($week_dates);
	}


	/**
	 *
	 *
	 */
	public function fill_summarytable($_tstamp_min, $_tstamp_max) {

		// prepare data --------------------------

		uksort($this->summary, "strnatcasecmp");
		uksort($this->summaryweekend, "strnatcasecmp");

			// get user-array
		$users = array();
		foreach ($this->summary as $project => $user_time) {
			foreach($user_time as $user => $time) {
				if (!in_array($user, $users)) $users[] = $user;
			}
		}
		asort($users);
		$usersweekend = array();
		foreach ($this->summaryweekend as $project => $user_time) {
			foreach($user_time as $user => $time) {
				if (!in_array($user, $usersweekend)) $usersweekend[] = $user;
			}
		}
		asort($usersweekend);


			// get project-array (sites)
		$projects = array();
		foreach ($this->summary as $project => $user_time) {
			if (!in_array($project ,$projects)) $projects[] = $project;
		}
		foreach ($this->summaryweekend as $project => $user_time) {
			if (!in_array($project ,$projects)) $projects[] = $project;
		}
		asort($projects);

		$this->summarytable->addColumn('co20');
		for ($i=1; $i <=  count($users) + count($usersweekend) + 2; $i++) {
			$this->summarytable->addColumn();
		}


		// populate table --------------------------


			// create table-headlines
		$row = $this->summarytable->addRow();
		$this->summarytable->addCell($row, 'string', 'CMS Lite Support / Montag - Freitag / 08.00 Uhr - 18.00 Uhr', array('bold'));

		for ($i=0; $i<count($users); $i++) {
			$this->summarytable->addCell($row, 'string', '');
		}
		$this->summarytable->addCell($row, 'string', 'CMS Lite Support / Wochenende', array('bold'));


			// headline, row 1
		$row = $this->summarytable->addRow();
		$this->summarytable->addCell($row, 'string', 'Mitarbeiter:', array('bold'));

		foreach ($users as $user) {
			$this->summarytable->addCell($row, 'string', $user, array('bold'));
		}
		$this->summarytable->addCell($row, 'string', 'Mitarbeiter:', array('bold'));
		foreach ($usersweekend as $user) {
			$this->summarytable->addCell($row, 'string', $user, array('bold'));
		}

			// fixed date rows, row 2
		$row = $this->summarytable->addRow();
		$this->summarytable->addCell($row, 'string', 'Zeitraum:', array('bold'));
		$kw_min = strftime("%V", $_tstamp_min);
		$kw_max = strftime("%V", $_tstamp_max);
		$kw = ($kw_min == $kw_max) ? "KW $kw_min" : "KW $kw_min - KW $kw_max";
		for ($i=0; $i < count($users); $i++) {
			$this->summarytable->addCell($row, 'string', $kw, array('bold'));
		}
		$this->summarytable->addCell($row, 'string', 'Zeitraum:', array('bold'));
		for ($i=0; $i < count ($usersweekend); $i++) {
			$this->summarytable->addCell($row, 'string', $kw, array('bold'));
		}


			// weekdays,  row 3 left
		$row = $this->summarytable->addRow();
		$this->summarytable->addCell($row, 'string', '', array('bold'));

		$week_dates = $this->week_dates($_tstamp_min);

		if ($kw_min != $kw_max) {
			$days = strftime("%d.%m.%y", $_tstamp_min).' - '.strftime("%d.%m.%y", $_tstamp_max);
		}
		else {
			// monday-friday
			$days = strftime("%d.%m.%y", $week_dates[1]).' - '.strftime("%d.%m.%y", $week_dates[5]);
		}
		for ($i=0; $i < count($users); $i++) {
			$this->summarytable->addCell($row, 'string', $days, array('bold'));
		}

			// weekend,  row 3 right
		$this->summarytable->addCell($row, 'string', '', array('bold'));
		if ($kw_min != $kw_max) {
			$days = strftime("%d.%m.%y", $_tstamp_min).' - '.strftime("%d.%m.%y", $_tstamp_max);
		}
		else {
			$days = strftime("%d.%m.%y", $week_dates[6]).' - '.strftime("%d.%m.%y", $week_dates[7]);
		}
		for ($i=0; $i < count($usersweekend); $i++) {
			$this->summarytable->addCell($row, 'string', $days, array('bold'));
		}

		$this->rowcount = 4;



			// project lines (sitenames)
		foreach ($projects as $project) {

				// 1.Cell: projectname
			$row = $this->summarytable->addRow();
			$this->rowcount++;
			$this->summarytable->addCell($row, 'string', $project);

				// iterate all users for each line
			foreach ($users as $user) {

				if   (array_key_exists($project, $this->summary) && array_key_exists($user, $this->summary[$project])) {
					$this->summarytable->addCell($row, 'float', (float) ($this->summary[$project][$user] / 60) );
				}
				else { // add empty cell if no user-entry
					$this->summarytable->addCell($row, 'string', '');
				}
			}
			$this->summarytable->addCell($row, 'string', '');
			foreach ($usersweekend as $user) {

					// weekend
				if (array_key_exists($project, $this->summaryweekend) && array_key_exists($user, $this->summaryweekend[$project])) {
						$this->summarytable->addCell($row, 'float', (float) ($this->summaryweekend[$project][$user] / 60) );
				}
				else {  // add empty cell if no user-entry
					$this->summarytable->addCell($row, 'string', '');
				}
			}
		}



			// summary line 1
		$row = $this->summarytable->addRow();
		$this->rowcount++;
		$row = $this->summarytable->addRow();
		$this->rowcount++;
		$this->summarytable->addCell($row, 'string', 'Summe:');
		for ($i=1; $i <= count($users); $i++) {
			$this->table->addCell($row, 'formula', 'SUM([.'.chr(65+$i).'5:.'.chr(65 + $i).($this->rowcount - 1).'])');
		}
		$this->summarytable->addCell($row, 'string', 'Summe:');
		for ($i= count($users)+2; $i <= count($usersweekend)+count($users)+1; $i++) {
			$this->table->addCell($row, 'formula', 'SUM([.'.chr(65+$i).'5:.'.chr(65 + $i).($this->rowcount - 1).'])');
		}


			// summary line 2
		$row = $this->summarytable->addRow();
		$this->rowcount++;
		$row = $this->summarytable->addRow();
		$this->rowcount++;
		$this->summarytable->addCell($row, 'string', 'Gesamt:');
		$this->table->addCell($row, 'formula', 'SUM([.B'. ($this->rowcount - 2) . ':.' . chr(65 + count($users)) . ($this->rowcount - 2) . '])');
		for ($i=1; $i <= count($users)-1; $i++) {
			$this->table->addCell($row, 'string', '');
		}

		$this->summarytable->addCell($row, 'string', 'Gesamt:');
		$this->table->addCell($row, 'formula', 'SUM([.'.chr(65 + count($users) + 2) . ($this->rowcount - 2).
												   ':.'.chr(65 + count($users) + 2 + count($usersweekend) - 1) . ($this->rowcount - 2).'])');
	}


	public function create_usertable($_tablename, $_username, $_tstamp_min, $_tstamp_max) {
		$this->table = new SpreadSheetTable($this->document, $_tablename);
		for ($i=10; $i < 19; $i++) {
			$this->table->addColumn('co'.$i);
		}

		$row = $this->table->addRow();
		$this->table->addCell($row, 'string', 'Monat:' . strftime("%m/%Y", $_tstamp_min), array('bold'));
		$this->table->addCell($row, 'string', 'KW ' . strftime("%V", $_tstamp_min), array('bold'));

		$row = $this->table->addRow();
		$this->table->addCell($row, 'string', 'Mitarbeiter:', array('bold') );
		$this->table->addCell($row, 'string', $_username, array('bold'));

			// create table-headlines
		$headlines = array(
			'Datum',
			'Site',
			'Ansprechpartner',
			'Projekttyp',
			'Ticket#',
			'Std.',
			'Newsletter',
//			'SOW-Nr.',
			'Bemerkungen'
			);
		$row = $this->table->addRow('double');
		$this->table->addCells($row, 'string', $headlines, array('bold', 'border'));

		$this->tablecount++;
		$this->rowcount = 3;
		$this->columncount = count($headlines);
	}



	/**
	 * exports a record into resource of handle
	 *
	 * @param iface_egw_record record
	 * @return bool
	 * @access public
	 */
	public function add_record( $_record, $_extras ) {

		if (is_array($_record)) {
			$row = $this->table->addRow();
			$this->rowcount++;

			$this->table->addCell($row, 'date', strftime("%d.%m.%Y", $_record['ts_start']));
			$this->table->addCell($row, 'string', $_record['ts_project']);
			$this->table->addCell($row, 'string', $_extras['asp']);
			$this->table->addCell($row, 'string', $_record['cat_name']);// $_extras['typ']);
			$this->table->addCell($row, 'string', $_extras['ticket']);
			$this->table->addCell($row, 'float', (float) ($_record['ts_duration'] / 60) );
			$this->table->addCell($row, 'string', $_extras['newsletter']);
//			$this->table->addCell($row, 'string', $_extras['sow']);
			$this->table->addCell($row, 'string', $_record['ts_description']);


			// collect statistics

				// username
			$res = array();
//			$nameRegex = '/\s(.*)\s(.*)$/';  // z.B. "[admin] Richard Blume"
			$nameRegex = '/(.*),\s(.*)$/';  // z.B. "Blume, Richard"
			preg_match($nameRegex, $GLOBALS['egw']->common->grab_owner_name($_record['ts_owner']), $res);
//			$user = $res[1];
			$user = $res[0];  // full name

			$site = $_record['ts_project'];
			$time = $_record['ts_duration'];
			$weekday = (int) strftime("%w", $_record['ts_start']);

			if ( $weekday == 0 || $weekday == 6 ) {

				// weekend
				if (!array_key_exists($site, $this->summaryweekend)) {
					$this->summaryweekend[$site] = array();
					$this->summaryweekend[$site][$user] = $time;
				}
				elseif (!array_key_exists($user, $this->summaryweekend[$site])) {
					$this->summaryweekend[$site][$user] = $time;
				}
				else {
					$this->summaryweekend[$site][$user] += $time;
				}
			}
			else {
				// site -> user -> sum
				if (!array_key_exists($site, $this->summary)) {
					$this->summary[$site] = array();
					$this->summary[$site][$user] = $time;
				}
				elseif (!array_key_exists($user, $this->summary[$site])) {
					$this->summary[$site][$user] = $time;
				}
				else {
					$this->summary[$site][$user] += $time;
				}
			}

			$this->num_of_records++;
			$this->record = array();

		}
	}


	/**
	 *
	 *
	 */
	public function summarize() {
		if ($this->rowcount > 1) {
			$row = $this->table->addRow();
			$this->rowcount++;
			$row = $this->table->addRow();
			$this->rowcount++;

			$this->table->addCell($row, 'string', '');
			$this->table->addCell($row, 'string', '');
			$this->table->addCell($row, 'string', '');
			$this->table->addCell($row, 'string', '');
			$this->table->addCell($row, 'string', '');
			$this->table->addCell($row, 'formula', 'SUM([.F2:.F'.($this->rowcount-2).'])');
		}
	}



	public function init() {
		$this->document = new SpreadSheetDocument($this->handle);

			// global usertable styles
		$columnwidth = array(
			'2.767cm',
			'5.067cm',
			'5.067cm',
			'3.267cm',
			'2.267cm',
			'2.267cm',
			'2.267cm',
//			'2.267cm',
			'6.267cm'
			);

		for ($i=0; $i < count($columnwidth); $i++) {
			$this->document->addColumnStyle('co'.($i + 10), $columnwidth[$i]);
		}

			// first column, summary-table
		$this->document->addColumnStyle('co20', '5.2cm');
	}

	public function finalize() {
		$this->document->finalize();
	}

	/**
	 * Returns total number of exported records.
	 *
	 * @return int
	 * @access public
	 */
	public function get_num_of_records() {
		return $this->num_of_records;
	}

	/**
	 * destructor
	 *
	 * @return
	 * @access public
	 */
	public function __destruct() {

	}
}



?>
