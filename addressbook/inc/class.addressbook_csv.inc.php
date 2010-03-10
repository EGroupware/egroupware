<?php
/**
 * Addressbook - export to csv
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @subpackage export
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * export to csv
 */
class addressbook_csv
{
	/**
	 * Addressbook Instance
	 *
	 * @var addressbook_bo
	 */
	var $obj;
	var $charset;
	var $charset_out;
	var $separator;
	static $types = array(
		'select-account' => array('owner','creator','modifier'),
		'date-time' => array('modified','created','last_event','next_event'),
		'select-cat' => array('cat_id'),
	);

	/**
	 * Number of individual category fields
	 */
	const CAT_MAX = 10;

	/**
	 * Constructor
	 *
	 * @param addressbook_bo $obj
	 * @param string $charset
	 * @param string $separator
	 */
	function __construct(addressbook_bo $obj,$charset=null,$separator=';')
	{
		$this->obj = $obj;
		$this->separator = $separator;
		$this->charset_out = $charset;
		$this->charset = translation::charset();
	}

	/**
	 * Exports some contacts as CSV: download or write to a file
	 *
	 * @param array $ids contact-ids
	 * @param array $fields=null default csv_fields() = all fields
	 * @param string $file filename or null for download
	 */
	function export($ids,$fields=null,$file=null)
	{
		if (is_null($fields))
		{
			$fields = $this->csv_fields();
		}
		// add fields for single categories
		if (isset($fields['cat_id']))
		{
			for($n = 1; $n <= self::CAT_MAX; ++$n)
			{
				$fields['cat_'.$n] = lang('Category').' '.$n;
			}
		}
		if (!$file)
		{
			$browser = new browser();
			$browser->content_header('addressbook.csv','text/comma-separated-values');
		}
		if (!($fp = fopen($file ? $file : 'php://output','w')))
		{
			return false;
		}
		fwrite($fp,$this->csv_encode($fields,$fields)."\n");

		if (isset($fields['last_event']) || isset($fields['next_event']))
		{
			$events = $this->obj->read_calendar($ids);
		}
		foreach($ids as $id)
		{
			if (!($data = $this->obj->read($id)))
			{
				return false;
			}
			if ($events && isset($events[$id]) && is_array($events[$id]))
			{
				$data += $events[$id];
			}
			$this->csv_prepare($data,$fields);

			fwrite($fp,$this->csv_encode($data,$fields)."\n");
		}
		fclose($fp);

		if (!$file)
		{
			$GLOBALS['egw']->common->egw_exit();
		}
		return true;
	}

	/**
	 * export and encode one row
	 *
	 * @param array $data
	 * @param array $fields
	 * @return string
	 */
	function csv_encode($data,$fields)
	{
		$out = array();
		foreach($fields as $field => $label)
		{
			$value = $data[$field];
			if (strpos($value,$this->separator) !== false || strpos($value,"\n") !== false || strpos($value,"\r") !== false)
			{
				$value = '"'.str_replace(array('\\','"'),array('\\\\','\\"'),$value).'"';
			}
			$out[] = $value;
		}
		$out = implode($this->separator,$out);

		if ($this->charset_out && $this->charset != $this->charset_out)
		{
			$out = translation::convert($out,$this->charset,$this->charset_out);
		}
		return $out;
	}

	/**
	 * Prepare a line of the export: replace id's and timestamps with more readable values
	 *
	 * @param array &$data
	 * @param array $fields
	 */
	function csv_prepare(&$data,$fields)
	{
		foreach(self::$types['select-account'] as $name)
		{
			if ($data[$name])
			{
				$data[$name] = $GLOBALS['egw']->common->grab_owner_name($data[$name]);
			}
			elseif ($name == 'owner')
			{
				$data[$name] = lang('Accounts');
			}
		}
		foreach(self::$types['date-time'] as $name)
		{
			if ($data[$name]) $data[$name] = date('Y-m-d H:i:s',$data[$name]);
		}
		if ($data['tel_prefer']) $data['tel_prefer'] = $fields[$data['tel_prefer']];

		$cats = array();
		foreach(explode(',',$data['cat_id']) as $n => $cat_id)
		{
			if ($cat_id) $cats[] = $data['cat_'.($n+1)] = $GLOBALS['egw']->categories->id2name($cat_id);
		}
		$data['cat_id'] = implode('; ',$cats);

		$data['private'] = $data['private'] ? lang('yes') : lang('no');

		$data['n_fileas'] = $this->obj->fileas($data);
		$data['n_fn'] = $this->obj->fullname($data);
	}

	/**
	 * Return the fields to export
	 *
	 * @param string $csv_pref 'home', 'business' or default all
	 * @param boolean $include_type=false include type information for nextmatchs csv export
	 * @return array with name => label pairs
	 */
	function csv_fields($csv_pref=null,$include_type=false)
	{
		switch ($csv_pref)
		{
			case 'business':
				$fields = $this->obj->business_contact_fields;
				break;
			case 'home':
				$fields = $this->obj->home_contact_fields;
				break;
			default:
				$fields = $this->obj->contact_fields;
				foreach($this->obj->customfields as $name => $data)
				{
					$fields['#'.$name] = $data['label'];
				}
				$fields['last_event'] = lang('Last date');
				$fields['next_event'] = lang('Next date');
				break;
		}
		unset($fields['jpegphoto']);

		if ($include_type)
		{
			foreach(self::$types as $type => $names)
			{
				foreach($names as $name)
				{
					if (isset($fields[$name]))
					{
						$fields[$name] = array(
							'type'  => $type,
							'label' => $fields[$name],
						);
					}
				}
			}
		}
		return $fields;
	}
}