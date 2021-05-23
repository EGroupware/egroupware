<?php
/**
 * EGroupware - CSV export for eT2 nextmatch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-21 by RalfBecker@outdoor-training.de
 */

namespace EGroupware\Api\Etemplate;

use EGroupware\Api;
use EGroupware\Api\Storage\Merge;

/**
 * CSV export for eT2 nextmatch widget
 *
 * Ported from old eTemplate nextmatch widget
 */
class Export extends Widget\Nextmatch
{
	/**
	 * Export the list as csv file download
	 *
	 * @param array $value array('get_rows' => $method), further values see nextmatch widget $query parameter
	 * @param string $separator=';'
	 * @return boolean false=error, eg. get_rows callback does not exits, true=nothing to export, otherwise we do NOT return!
	 */
	static public function csv(&$value,$separator=';')
	{
		$exportLimitExempted = Merge::is_export_limit_excepted();
		if (!$exportLimitExempted)
		{
			$name = is_object($value['template']) ? $value['template']->name : $value['template'];
			list($app) = explode('.',$name);
			$export_limit = Merge::getExportLimit($app);
			//if (isset($value['export_limit'])) $export_limit = $value['export_limit'];
		}
		$charset = $charset_out = Api\Translation::charset();
		if (isset($value['csv_charset']))
		{
			$charset_out = $value['csv_charset'];
		}
		elseif ($GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'])
		{
			$charset_out = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		}
		$backup_start = $value['start'];
		$backup_num_rows = $value['num_rows'];

		$value['start'] = 0;
		$value['num_rows'] = 500;
		$value['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
		do
		{
			$rows = [];
			if (!($total = self::call_get_rows($value,$rows)))
			{
				break;	// nothing to export
			}
			if (!$exportLimitExempted && (!Merge::hasExportLimit($export_limit,'ISALLOWED') || (Merge::hasExportLimit($export_limit) && (int)$export_limit < $total)))
			{
				etemplate::set_validation_error($name,lang('You are not allowed to export more than %1 entries!',(int)$export_limit));
				return false;
			}
			if (!isset($value['no_csv_support'])) $value['no_csv_support'] = !is_array($value['csv_fields']);

			//echo "<p>start=$value[start], num_rows=$value[num_rows]: total=$total, count(\$rows)=".count($rows)."</p>\n";
			if (!$value['start'])	// send the necessary headers
			{
				// skip empty data row(s) used to adjust to number of header-lines
				foreach($rows as $row0)
				{
					if (is_array($row0) && count($row0) > 1) break;
				}
				$fp = self::csvOpen($row0,$value['csv_fields'],$app,$charset_out,$charset,$separator);
			}
			foreach($rows as $key => $row)
			{
				if (!is_numeric($key) || !$row) continue;	// not a real rows
				fwrite($fp,self::csvEncode($row,$value['csv_fields'],true,$rows['sel_options'],$charset_out,$charset,$separator)."\n");
			}
			$value['start'] += $value['num_rows'];

			@set_time_limit(10);	// 10 more seconds
		}
		while($total > $value['start']);

		unset($value['csv_export']);
		$value['start'] = $backup_start;
		$value['num_rows'] = $backup_num_rows;
		if ($value['no_csv_support'])	// we need to call the get_rows method in case start&num_rows are stored in the session
		{
			self::call_get_rows($value);
		}
		if ($fp)
		{
			fclose($fp);
			exit();
		}
		return true;
	}

	/**
	 * Opens the csv output (download) and writes the header line
	 *
	 * @param array $row0 first row to guess the available fields
	 * @param array $fields name=>label or name=>array('lable'=>label,'type'=>type) pairs
	 * @param string $app app-name
	 * @param string $charset_out=null output charset
	 * @param string $charset data charset
	 * @param string $separator=';'
	 * @return FILE
	 */
	private static function csvOpen($row0, &$fields, $app, $charset_out=null, $charset=null, $separator=';')
	{
		if (!is_array($fields) || !count($fields))
		{
			$fields = self::autodetect_fields($row0,$app);
		}
		Api\Header\Content::type('export.csv','text/comma-separated-values');
		//echo "<pre>";

		if (($fp = fopen('php://output','w')))
		{
			$labels = array();
			foreach($fields as $field => $label)
			{
				if (is_array($label)) $label = $label['label'];
				$labels[$field] = $label ? $label : $field;
			}
			fwrite($fp,self::csvEncode($labels,$fields,false,null,$charset_out,$charset,$separator)."\n");
		}
		return $fp;
	}

	/**
	 * CSV encode a single row, including some basic type conversation
	 *
	 * @param array $data
	 * @param array $fields
	 * @param boolean $use_type=true
	 * @param array $extra_sel_options=null
	 * @param string $charset_out=null output charset
	 * @param string $charset data charset
	 * @param string $separator=';'
	 * @return string
	 */
	private static function csvEncode($data, $fields, $use_type=true, array $extra_sel_options=null, $charset_out=null, $charset=null, $separator=';')
	{
		$sel_options = Api\Etemplate::$request->sel_options;

		$out = array();
		foreach($fields as $field => $label)
		{
			$value = (array)$data[$field];
			if ($use_type && is_array($label) && in_array($label['type'],array('select-account','select-cat','date-time','date','select','int','float')))
			{
				foreach($value as $key => $val)
				{
					switch($label['type'])
					{
						case 'select-account':
							if ($val) $value[$key] = Api\Accounts::username($val);
							break;
						case 'select-cat':
							if ($val)
							{
								$cats = array();
								foreach(is_array($val) ? $val : explode(',',$val) as $cat_id)
								{
									$cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
								}
								$value[$key] = implode('; ',$cats);
							}
							break;
						case 'date-time':
						case 'date':
							if ($val)
							{
								try {
									$value[$key] = Api\DateTime::to($val,$label['type'] == 'date' ? true : '');
								}
								catch (\Exception $e) {
									// ignore conversation errors, leave value unchanged (might be a wrongly as date(time) detected field
								}
							}
							break;
						case 'select':
							if (isset($sel_options[$field]))
							{
								if ($val) $value[$key] = self::getLabel($val, $sel_options[$field]);
							}
							elseif(is_array($extra_sel_options) && isset($extra_sel_options[$field]))
							{
								if ($val) $value[$key] = self::getLabel($val, $extra_sel_options[$field]);
							}
							break;
						case 'int':		// size: [min],[max],[len],[precission/sprint format]
						case 'float':
							list(,,,$pre) = explode(',',$label['size']);
							if (($label['type'] == 'float' || !is_numeric($pre)) && $val && $pre)
							{
								$val = str_replace(array(' ',','),array('','.'),$val);
								$value[$key] = is_numeric($pre) ? round($value,$pre) : sprintf($pre,$value);
							}
					}
				}
			}
			$value = implode(', ',$value);

			if (strpos($value,$separator) !== false || strpos($value,"\n") !== false || strpos($value,"\r") !== false)
			{
				$value = '"'.str_replace(array('\\', '"',),array('\\\\','""'),$value).'"';
				$value = str_replace("\r\n", "\n", $value); // to avoid early linebreak by Excel
			}
			$out[] = $value;
		}
		$out = implode($separator,$out);

		if ($charset_out && $charset != $charset_out)
		{
			$out = Api\Translation::convert($out,$charset,$charset_out);
		}
		return $out;
	}

	/**
	 * Get label for given value
	 *
	 * @param $value
	 * @param array $options either value => label pairs or [['value'=>$value,'label'=>$label], ...]
	 * @return string
	 */
	protected static function getLabel($value, array &$options)
	{
		if (!is_array($options)) return;

		if (!isset($options[$value]) && isset($options[0]))
		{
			$options = array_combine(
				array_map(static function($data)
				{
					return $data['value'];
				}, $options),
				array_map(static function($data)
				{
					return $data['label'];
				}, $options)
			);
		}
		return lang($options[$value]);
	}

	/**
	 * Try to autodetect the fields from the first data-row and the app-name
	 *
	 * @param array $row0 first data-row
	 * @param string $app
	 */
	private static function autodetect_fields($row0,$app)
	{
		$fields = array_combine(array_keys($row0),array_keys($row0));

		foreach($fields as $name => $label)
		{
			// try to guess field-type from the fieldname
			if (preg_match('/(modified|created|start|end)/',$name) && strpos($name,'by')===false &&
				(!$row0[$name] || is_numeric($row0[$name])))	// only use for real timestamps
			{
				$fields[$name] = array('label' => $label,'type' => 'date-time');
			}
			elseif (preg_match('/(cat_id|category|cat)/',$name))
			{
				$fields[$name] = array('label' => $label,'type' => 'select-cat');
			}
			elseif (preg_match('/(owner|creator|modifier|assigned|by|coordinator|responsible)/',$name))
			{
				$fields[$name] = array('label' => $label,'type' => 'select-account');
			}
			elseif(preg_match('/(jpeg|photo)/',$name))
			{
				unset($fields[$name]);
			}
		}
		if ($app)
		{
			$customfields = Api\Storage\Customfields::get($app);

			if (is_array($customfields))
			{
				foreach($customfields as $name => $data)
				{
					$fields['#'.$name] = array(
						'label' => $data['label'],
						'type'  => $data['type'],
					);
				}
			}
		}
		//_debug_array($fields);
		return $fields;
	}
}