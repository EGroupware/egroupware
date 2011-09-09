<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id: class.etemplate_widget_button.inc.php 36221 2011-08-20 10:27:38Z ralfbecker $
 */

/**
 * eTemplate image widget
 * Displays image from URL, vfs, or finds by name
 */
class etemplate_widget_nextmatch extends etemplate_widget
{

	public function __construct($xml='') {
		if($xml) parent::__construct($xml);
	}

	static public function ajax_get_rows($fetchList)
	{
		error_log(__METHOD__."(".array2string($fetchList).")");

		// Force the array to be associative
		$result = array("null" => null);

		foreach ($fetchList as $entry)
		{
			for ($i = 0; $i < $entry["count"]; $i++)
			{
				$result[$entry["startIdx"] + $i] = json_decode('{"info_id":"5","info_type":"email","info_from":"tracker","info_addr":"tracker","info_subject":"InfoLog grid view: problem with Opera; \'permission denied\' while saving","info_des":"<snip>","info_owner":"5","info_responsible":[],"info_access":"public","info_cat":"0","info_datemodified":1307112528,"info_startdate":1306503000,"info_enddate":"0", "info_id_parent":"0","info_planned_time":"0","info_replanned_time":"0","info_used_time":"0","info_status":"done", "info_confirm":"not","info_modifier":"5","info_link_id":0,"info_priority":"1","pl_id":"0","info_price":null, "info_percent":"100%","info_datecompleted":1307112528,"info_location":"","info_custom_from":1, "info_uid":"infolog-5-18d12c7bf195f6b9d602e1fa5cde28f1","info_cc":"","caldav_name":"5.ics","info_etag":"0", "info_created":1307112528,"info_creator":"5","links":[],"info_anz_subs":0,"sub_class":"normal_done","info_link":{"title":"tracker"},"class":"rowNoClose rowNoCloseAll ","info_type_label":"E-Mail","info_status_label":"done","info_number":"5"}');
			}
		}

		$response = egw_json_response::get();
		$response->data($result);
	}

}

