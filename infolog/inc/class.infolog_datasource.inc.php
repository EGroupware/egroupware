<?php
/**
 * InfoLog - Datasource for ProjektManager
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @subpackage projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for InfoLog
 *
 * The InfoLog datasource set's only real start- and endtimes, plus planned and used time and
 * the responsible user as resources (not always the owner too!).
 * The read method of the extended datasource class sets the planned start- and endtime:
 *  - planned start from the end of a start constrain
 *  - planned end from the planned time and a start-time
 *  - planned start and end from the "real" values
 */
class infolog_datasource extends datasource
{
	/**
	 * Reference to infolog_bo
	 *
	 * @var infolog_bo
	 */
	var $infolog_bo;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct('infolog');

		$this->valid = PM_COMPLETION|PM_PLANNED_START|PM_PLANNED_END|PM_REAL_END|PM_PLANNED_TIME|PM_REPLANNED_TIME|PM_USED_TIME|PM_RESOURCES|PM_CAT_ID;

		// we use $GLOBALS['infolog_bo'] as an already running instance might be availible there
		if (!is_object($GLOBALS['infolog_bo']))
		{
			$GLOBALS['infolog_bo'] = new infolog_bo();
		}
		$this->infolog_bo =& $GLOBALS['infolog_bo'];
	}

	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		if (!is_array($data_id))
		{
			$data =& $this->infolog_bo->read((int) $data_id);

			if (!is_array($data)) return false;
		}
		else
		{
			$data =& $data_id;
		}
		return array(
			'pe_title'        => $this->infolog_bo->link_title($data),
			'pe_completion'   => $data['info_percent'],
			'pe_planned_start'=> $data['info_startdate'] ? $data['info_startdate'] : null,
			'pe_planned_end'  => $data['info_enddate'] ? $data['info_enddate'] : null,
			'pe_real_end'     => $data['info_datecompleted'] ? $data['info_datecompleted'] : null,
			'pe_planned_time' => $data['info_planned_time'],
			'pe_replanned_time' => $data['info_replanned_time'],
			'pe_used_time'    => $data['info_used_time'],
			'pe_resources'    => count($data['info_responsible']) ? $data['info_responsible'] : array($data['info_owner']),
			'pe_details'      => $data['info_des'] ? nl2br($data['info_des']) : '',
			'pl_id'           => $data['pl_id'],
			'pe_unitprice'    => $data['info_price'],
			'pe_planned_quantity' => $data['info_planned_time'] / 60,
			'pe_planned_budget'   => $data['info_planned_time'] / 60 * $data['info_price'],
			'pe_used_quantity'    => $data['info_used_time'] / 60,
			'pe_used_budget'      => $data['info_used_time'] / 60 * $data['info_price'],
			'cat_id'              => $data['info_cat'],
		);
	}

	/**
	 * Copy the datasource of a projectelement (InfoLog entry) and re-link it with project $target
	 *
	 * @param array $element source project element representing an InfoLog entry, $element['pe_app_id'] = info_id
	 * @param int $target target project id
	 * @param array $target_data=null data of target-project, atm not used by the infolog datasource
	 * @return array/boolean array(info_id,link_id) on success, false otherwise
	 */
	function copy($element,$target,$extra=null)
	{
		$info =& $this->infolog_bo->read((int) $element['pe_app_id']);

		if (!is_array($info)) return false;

		// unsetting info_link_id and evtl. info_from
		if ($info['info_link_id'])
		{
			$this->infolog_bo->link_id2from($info);		// unsets info_from and sets info_link_target
			unset($info['info_link_id']);
		}
		// we need to unset a view fields, to get a new entry
		foreach(array('info_id','info_owner','info_modified','info_modifierer') as $key)
		{
			unset($info[$key]);
		}
		if(!($info['info_id'] = $this->infolog_bo->write($info))) return false;

		// link the new infolog against the project and setting info_link_id and evtl. info_from
		$info['info_link_id'] = egw_link::link('projectmanager',$target,'infolog',$info['info_id'],$element['pe_remark'],0,0,1);
		if (!$info['info_from'])
		{
			$info['info_from'] = egw_link::title('projectmanager',$target);
		}
		if ($info['info_status'] == 'template')
		{
			$info['info_status'] = $this->infolog_bo->activate($info);
		}
		$this->infolog_bo->write($info);

		// creating again all links, beside the one to the source-project
		foreach(egw_link::get_links('infolog',$element['pe_app_id']) as $link)
		{
			if ($link['app'] == 'projectmanager' && $link['id'] == $element['pm_id'] ||		// ignoring the source project
				$link['app'] == egw_link::VFS_APPNAME)					// ignoring files attachments for now
			{
				continue;
			}
			egw_link::link('infolog',$info['info_id'],$link['app'],$link['id'],$link['remark']);
		}
		$ret = array($info['info_id'],$info['info_link_id']);

		// if we have a parent set, return our callback to modify the parent id, after all entries are copied
		if ($info['info_id_parent'])
		{
			$ret[] = array($this,'copy_callback');	// callback
			$ret[] = array($info['info_id'],$info['info_id_parent']);	// $param
		}
		return $ret;
	}

	/**
	 * Callback called after copying of all datasource, used to:
	 * - fix parent id's
	 *
	 * @param array $param array($info_id,$info_id_parent)
	 * @param array $apps_copied array('infolog' => array($old_info_id => $new_info_id))
	 */
	public function copy_callback(array $param, array $apps_copied)
	{
		//error_log(__METHOD__."(".array2string($param).', '.array2string($apps_copied).')');
		list($info_id,$parent_id) = $param;
		if (isset($apps_copied['infolog'][$parent_id]) && ($info = $this->infolog_bo->read($info_id)))
		{
			$info['info_id_parent'] = $apps_copied['infolog'][$parent_id];
			$this->infolog_bo->write($info,false,true,true,true);	// no default and no notification
		}
	}

	/**
	 * Delete the datasource of a project element
	 *
	 * @param int $id
	 * @return boolean true on success, false on error
	 */
	function delete($id)
	{
		if (!is_object($GLOBALS['infolog_bo']))
		{
			include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.infolog_bo.inc.php');
			$GLOBALS['infolog_bo'] = new infolog_bo();
		}
		// dont delete infolog, which are linked to other elements, but their project
		if (count(egw_link::get_links('infolog',$id)) > 1)
		{
			return false;
		}
		return $this->infolog_bo->delete($id);
	}

	/**
	 * Change the status of an infolog entry according to the project status
	 *
	 * @param int $id
	 * @param string $status
	 * @return boolean true if status changed, false otherwise
	 */
	function change_status($id,$status)
	{
		//error_log("datasource_infolog::change_status($id,$status)");
		if (($info = $this->infolog_bo->read($id)) && $this->infolog_bo->check_access($info,EGW_ACL_EDIT))
		{
			if ($status == 'active' && in_array($info['info_status'],array('template','nonactive','archive')))
			{
				$status = $this->infolog_bo->activate($info);
			}
			if($info['info_status'] != $status && isset($this->infolog_bo->status[$info['info_type']][$status]))
			{
				//error_log("datasource_infolog::change_status($id,$status) setting status from ".$info['info_status']);
				$info['info_status'] = $status;
				return $this->infolog_bo->write($info) !== false;
			}
		}
		return false;
	}
}