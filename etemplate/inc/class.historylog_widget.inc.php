<?php
/**
 * eTemplate Widget for the historylog
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author RalfBecker-At-outdoor-training.de
 * @copyright 2006-13 by RalfBecker-At-outdoor-training.de
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
 *       widget types for custom fields do NOT need to be added, they are automatically detected!
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

	static $status_widgets;

	/**
	 * pre-processing of the historylog-helper
	 *
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	private function pre_process_helper(&$value, &$cell, $tmpl)
	{
		if (empty($value) && (string)$value !== '0')
		{
			$cell = boetemplate::empty_cell();
			return true;
		}
		//echo $value.'/'.$cell['size']; _debug_array(self::$status_widgets);
		$type = isset(self::$status_widgets[$cell['size']]) ? self::$status_widgets[$cell['size']] : 'label';
		$options = '';
		if (!is_array($type) && strpos($type,':') !== false)
		{
			list($type,$options) = explode(':',$type,2);
		}
		// For all select-cats, show missing entries as IDs
		if($type == 'select-cat')
		{
			list($rows,$type1,$type2,$type3,$type4,$type5,$type6) = explode(',',$options);
			$type6 = 2;
			$options = implode(',',array($rows,$type1,$type2,$type3,$type4,$type5,$type6));
		}
		$cell = boetemplate::empty_cell($type,$cell['name'],array('readonly' => true,'size' => $options));
		// display unsupported iCal properties, which have multiple values or attributes, or multiple components
		if ($type === 'label' && $value[1] === ':' && ($values = unserialize($value)))
		{
			if (isset($values['values']))
			{
				foreach((array)$values['params'] as $name => $val)
				{
					$values['values'][] = $name.': '.$val;
				}
				$values = $values['values'];
			}
			$value = implode("\n", $values);
		}
		elseif (is_array($type))
		{
			list($t) = explode(':',$type[0]);
			if (isset($type[0]) &&	// numeric indexed array --> multiple values of 1:N releation
				$tmpl->widgetExists($t))
			{
				$cell['type'] = 'vbox';
				$cell['size'] = '0,,0,0';
				$value = explode(bo_tracking::ONE2N_SEPERATOR,$value);
				foreach($type as $n => $t)
				{
					$opt = '';
					if(is_array($t))
					{
						$sel_options = $t;
						$t = 'select';
					}
					else
					{
						list($t,$opt) = explode(':',$t);
					}
					$child = boetemplate::empty_cell($t,$cell['name']."[$n]",array('readonly' => true,'no_lang' => true,'size' => $opt));
					$child['sel_options'] = $sel_options;
					boetemplate::add_child($cell,$child);
					unset($sel_options);
					unset($child);
				}
			}
			else
			{
				$cell['sel_options'] = $cell['type'];
				$cell['type'] = 'select';
			}
		}
		// For all times, show time in user time
		elseif ($type == 'date-time' && $value)
		{
			$value = egw_time::server2user($value);
		}
		if ($cell['type'] == 'label') $cell['no_lang'] = 'true';

		return true;
	}

	/**
	 * pre-processing of the history logging extension
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param etemplate $tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,$tmpl)
	{
		switch ($cell['type'])
		{
			case 'historylog-helper':
				return $this->pre_process_helper($value, $cell, $tmpl);
		}
		$app = is_array($value) ? $value['app'] : $GLOBALS['egw_info']['flags']['currentapp'];
		self::$status_widgets = is_array($value) && isset($value['status-widgets']) ? $value['status-widgets'] : null;

		$id = is_array($value) ? $value['id'] : $value;
		$filter = is_array($value) ? $value['filter'] : array();

		$historylog = new historylog($app);
		if (!$id || method_exists($historylog,'search'))
		{
			$value = $id ? $historylog->search($filter ? array('history_record_id'=>$id) + $filter : $id) : false;
		}
		unset($historylog);

		$tpl = new etemplate_old;
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
		if (!empty($cell['size']) && isset($tmpl->sel_options[$cell['size']]) && is_array($value))
		{
			$status = $cell['size'];
			foreach($value as &$row)
			{
				$row[$status] = $row['status'];
			}
		}
		// Add in links
		self::$status_widgets['~link~'] = 'link-entry';
		$tmpl->sel_options[$status]['~link~'] = lang('link');
		$tmpl->sel_options[$status]['user_agent_action'] = lang('User-agent & action');

		// adding custom fields automatically to status-widgets, no need for each app to do that
		foreach(config::get_customfields($app,true) as $cf_name => $cf_data)
		{
			// add cf label, if not set by app
			if (!isset($tmpl->sel_options[$status]['#'.$cf_name]))
			{
				$tmpl->sel_options[$status]['#'.$cf_name] = lang($cf_data['label']);
			}
			if (isset(self::$status_widgets['#'.$cf_name])) continue;	// app set a status widget --> use that

			if(!is_array($cf_data['values']) || !$cf_data['values'])
			{
				if (isset($GLOBALS['egw_info']['apps'][$cf_data['type']]))
				{
					self::$status_widgets['#'.$cf_name] = 'link-entry:'.$cf_data['type'];
				}
				else
				{
					self::$status_widgets['#'.$cf_name] = !in_array($cf_data['type'], array('text', 'client-ip')) ?
						$cf_data['type'] : 'label';
				}
			}
			elseif($cf_data['values']['@'])
			{
				self::$status_widgets['#'.$cf_name] = customfields_widget::_get_options_from_file($cf_data['values']['@']);
			}
			elseif(count($cf_data['values']))
			{
				self::$status_widgets['#'.$cf_name] = $cf_data['values'];
			}
		}

		if ($value)	// autorepeated data-row only if there is data
		{
			// add "labels" for unsupported iCal properties, we just remove the '##' prefix
			foreach($value as &$row)
			{
				if ($row['status'][0] == '#' && $row['status'][1] == '#' &&
					isset($tmpl->sel_options[$status]) && !isset($tmpl->sel_options[$status][$row['status']]))
				{
					$tmpl->sel_options[$status][$row['status']] = substr($row['status'], 2);
				}
			}

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
			if (self::$status_widgets)
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
