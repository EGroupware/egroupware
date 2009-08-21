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
 * If status is already used for a field, you can also set options to an other name, eg. 'labels' or 'fields'
 *
 * If you have a 1:N relation the 'status-widget' entry should be an array with widget types as values (corresponding
 * to order and fields used in bo_tracking's field2history array).
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

	/**
	 * pre-processing of the history logging extension
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		static $status_widgets;

		if ($cell['type'] == 'historylog-helper')
		{
			if (empty($value) && (string)$value !== '0')
			{
				$cell = etemplate::empty_cell();
				return true;
			}
			//echo $value.'/'.$cell['size']; _debug_array($status_widgets);
			$type = isset($status_widgets[$cell['size']]) ? $status_widgets[$cell['size']] : 'label';
			$cell = etemplate::empty_cell($type,$cell['name'],array('readonly' => true));
			if (is_array($type))
			{
				if (isset($type[0]))	// numeric indexed array --> multiple values of 1:N releation
				{
					$cell['type'] = 'vbox';
					$cell['size'] = '0,,0,0';
					$value = explode(bo_tracking::ONE2N_SEPERATOR,$value);
					foreach($type as $n => $t)
					{
						$child = etemplate::empty_cell($t,$cell['name']."[$n]",array('readonly' => true,'no_lang' => true));
						etemplate::add_child($cell,$child);
						unset($child);
					}
				}
				else
				{
					$cell['sel_options'] = $cell['type'];
					$cell['type'] = 'select';
				}
			}
			if ($cell['type'] == 'label') $cell['no_lang'] = 'true';
			return true;
		}
		$app = is_array($value) ? $value['app'] : $GLOBALS['egw_info']['flags']['currentapp'];
		$status_widgets = is_array($value) && isset($value['status-widgets']) ? $value['status-widgets'] : null;
		$id = is_array($value) ? $value['id'] : $value;

		$historylog = new historylog($app);
		if (!$id || method_exists($historylog,'search'))
		{
			$value = $id ? $historylog->search($id) : false;
		}
		unset($historylog);

		$tpl = new etemplate;
		$tpl->init('*** generated fields for historylog','','',0,'',0,0);	// make an empty template
		// keep the editor away from the generated tmpls
		$tpl->no_onclick = true;

		// header rows
		$tpl->new_cell(1,'label','Date');
		$tpl->new_cell(1,'label','User');
		$tpl->new_cell(1,'label','Changed');
		$tpl->new_cell(1,'label','New value');
		$tpl->new_cell(1,'label','Old value');

		$status = 'status';
		// allow to set a diffent name for status (field-name), eg. because status is already used for something else
		if (!empty($cell['size']) && isset($tmpl->sel_options[$cell['size']]))
		{
			$status = $cell['size'];
			foreach($value as &$row)
			{
				$row[$status] = $row['status'];
			}
		}
		if ($value)	// autorepeated data-row only if there is data
		{
			$tpl->new_cell(2,'date-time','','${row}[user_ts]',array('readonly' => true));
			$tpl->new_cell(2,'select-account','','${row}[owner]',array('readonly' => true));


			// if $sel_options[status] is set, use them and a readonly selectbox
			if (isset($tmpl->sel_options[$status]))
			{
				$tpl->new_cell(2,'select','','${row}['.$status.']',array('readonly' => true));
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
