<?php
/**
 * Addressbook - export to csv
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * export to csv
 *
 * @package addressbook
 * @subpackage export
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class csv_export
{
	var $obj;
	var $charset;
	var $charset_out;
	var $separator;

	function csv_export($obj,$charset=null,$separator=';')
	{
		$this->obj =& $obj;
		$this->separator = $separator;
		$this->charset_out = $charset;
		$this->charset = $GLOBALS['egw']->translation->charset();
	}

	/**
	 * Exports some contacts as CSV: download or write to a file
	 *
	 * @param array $ids contact-ids
	 * @param array $fields 
	 * @param string $file filename or null for download
	 */
	function export($ids,$fields,$file=null)
	{
		unset($fields['jpegphoto']);

		if (!$file)
		{
			$browser =& CreateObject('phpgwapi.browser');
			$browser->content_header('addressbook.csv','text/comma-separated-values');
		}
		if (!($fp = fopen($file ? $file : 'php://output','w')))
		{
			return false;
		}
		fwrite($fp,$this->csv_encode($fields,$fields)."\n");

		foreach($ids as $id)
		{
			if (!($data = $this->obj->read($id)))
			{
				return false;
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
			$out = $GLOBALS['egw']->translation->convert($out,$this->charset,$this->charset_out);
		}
		return $out;
	}
	
	function csv_prepare(&$data,$fields)
	{
		foreach(array('owner','creator','modifier') as $name)
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
		foreach(array('modified','created') as $name)
		{
			if ($data[$name]) $data[$name] = date('Y-m-d H:i:s',$data[$name]);
		}
		if ($data['tel_prefer']) $data['tel_prefer'] = $fields[$data['tel_prefer']];
		
		if (!is_object($GLOBALS['egw']->categories))
		{
			$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories','addressbook');
		}		
		$cats = array();
		foreach(explode(',',$data['cat_id']) as $cat_id)
		{
			if ($cat_id) $cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
		}
		$data['cat_id'] = implode('; ',$cats);
		
		$data['private'] = $data['private'] ? lang('yes') : lang('no');
		
		$data['n_fileas'] = $this->obj->fileas($data);
		$data['n_fn'] = $this->obj->fullname($data);
	}
}