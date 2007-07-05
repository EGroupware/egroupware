<?php
/**
 * eTemplate Widget for the historylog
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author RalfBecker-At-outdoor-training.de
 * @copyright 2006 by RalfBecker-At-outdoor-training.de
 * @license GPL - GNU General Public License
 * @version $Id$
 */

/**
 * This widget shows the historylog for one entry of an application
 *
 * It takes as parameter either just the id or an array with the following keys:
 *  - 'id' integer id of the entry
 *  - 'app' string app-name, defaults to $GLOBALS['egw_info']['flags']['currentapp']
 *  - 'status-widgets' array with status-values as key and widget names or array with select-options as value,
 *       all not set stati are displayed via a label-widget - just as text
 * You can set $sel_options['status'] to translate the status-values to meaningful labels.
 * 
 * @package etemplate
 * @subpackage extensions
 * @author RalfBecker-At-outdoor-training.de
 */
class historylog_widget
{
	/** 
	 * @var array exported methods of this class
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * @var array/string availible extensions and there names for the editor
	 */
	var $human_name = array(
		'historylog' => 'History Log',
//		'historylog-helper' => '',
	);
	
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		$status_widgets =& $GLOBALS['egw_info']['flags']['etemplate']['historylog-helper'];

		if ($cell['type'] == 'historylog-helper')
		{
			//echo $value.'/'.$cell['size']; _debug_array($status_widgets);
			$cell = array('type' => isset($status_widgets[$cell['size']]) ? $status_widgets[$cell['size']] : 'label','readonly' => true);
			if (is_array($cell['type']))
			{
				$cell['sel_options'] = $cell['type'];
				$cell['type'] = 'select';
			}
			if ($cell['type'] == 'label') $cell['no_lang'] = 'true';
			return true;
		}
		$app = is_array($value) ? $value['app'] : $GLOBALS['egw_info']['flags']['currentapp'];
		$status_widgets = is_array($value) && isset($value['status-widgets']) ? $value['status-widgets'] : null;
		$id = is_array($value) ? $value['id'] : $value;
		
		$historylog =& CreateObject('phpgwapi.historylog',$app);
		if (!$id || method_exists($historylog,'search'))
		{
			$value = $id ? $historylog->search($id) : false;
		}
		else // compatibilty code for 1.2, can be removed after
		{
			if (!is_object($GLOBALS['egw']->datetime))
			{
				$GLOBALS['egw']->datetime =& CreateObject('phpgwapi.datetime');
			}
			$value = $historylog->return_array('','','history_id','DESC',$id);
			foreach($value as $key => $val)
			{
				$value[$key]['owner'] = $GLOBALS['egw']->accounts->name2id($val['owner'],'account_lid','u');
				$value[$key]['user_ts'] = $val['datetime'] + $GLOBALS['egw']->datetime->tz_offset;
			}
		}
		unset($historylog);

		$tpl =& new etemplate;
		$tpl->init('*** generated fields for historylog','','',0,'',0,0);	// make an empty template
		// keep the editor away from the generated tmpls
		$tpl->no_onclick = true;

		// header rows
		$tpl->new_cell(1,'label','Date');
		$tpl->new_cell(1,'label','User');
		$tpl->new_cell(1,'label','Changed');
		$tpl->new_cell(1,'label','New value');
		$tpl->new_cell(1,'label','Old value');
		
		if ($value)	// autorepeated data-row only if there is data
		{
			$tpl->new_cell(2,'date-time','','${row}[user_ts]',array('readonly' => true));
			$tpl->new_cell(2,'select-account','','${row}[owner]',array('readonly' => true));
			// if $sel_options[status] is set, use them and a readonly selectbox
			if (isset($tmpl->sel_options['status']))
			{
				$tpl->new_cell(2,'select','','${row}[status]',array('readonly' => true));
			}
			else
			{
				$tpl->new_cell(2,'label','','${row}[status]',array('no_lang' => true));
			}
			// if $value[status-widgets] is set, use them together with the historylog-helper 
			// to display new_ & old_value in the specified widget, otherwise use a label
			if ($status_widgets)
			{
				$tpl->new_cell(2,'historylog-helper','','${row}[new_value]',array('size' => '$row_cont[status]','no_lang' => true,'readonly' => true));
				$tpl->new_cell(2,'historylog-helper','','${row}[old_value]',array('size' => '$row_cont[status]','no_lang' => true,'readonly' => true));
			}
			else
			{
				$tpl->new_cell(2,'label','','${row}[new_value]',array('no_lang' => true));
				$tpl->new_cell(2,'label','','${row}[old_value]',array('no_lang' => true));
			}
			array_unshift($value,false);	// addjust index to start with 1, as we have a header-row
		}
		$tpl->data[0] = array(
			'c1' => 'th',
			'c2' => 'row',
		);
		$tpl->size = '100%';

		$cell['size'] = $cell['name'];
		$cell['type'] = 'template';
		$cell['name'] = $tpl->name;
		$cell['obj'] = &$tpl;

		return True;	// extra Label is ok
	}
}
