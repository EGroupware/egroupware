<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Miles Lott <milos@groupwhere.org>                             *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class boXport
	{
		var $public_functions = array(
			'import' => True,
			'export' => True
		);

		var $so;
		var $contacts;

		var $start;
		var $limit;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;

		var $use_session = False;
		var $debug = False;

		function boXport($session=False)
		{
			$this->contacts = &$GLOBALS['phpgw']->contacts;
			$this->so = CreateObject('addressbook.soaddressbook');
			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			$_start   = get_var('_start',array('POST','GET'));
			$_query   = get_var('_query',array('POST','GET'));
			$_sort    = get_var('_sort',array('POST','GET'));
			$_order   = get_var('_order',array('POST','GET'));
			$_filter  = get_var('_filter',array('POST','GET'));
			$_cat_id  = get_var('_cat_id',array('POST','GET'));
			$_fcat_id = get_var('_fcat_id',array('POST','GET'));

			if(!empty($_start) || ($_start == '0') || ($_start == 0))
			{
				if($this->debug) { echo '<br>overriding $start: "' . $this->start . '" now "' . $_start . '"'; }
				$this->start = $_start;
			}
			if($_limit)
			{
				$this->limit  = $_limit;
			}
			if((empty($_query) && !empty($this->query)) || !empty($_query))
			{
				$this->query  = $_query;
			}

			if(isset($_POST['fcat_id']) || isset($_POST['fcat_id']))
			{
				$this->cat_id = $_fcat_id;
			}
			else
			{
				$this->cat_id = -1;
			}

			if(isset($_sort) && !empty($_sort))
			{
				if($this->debug) { echo '<br>overriding $sort: "' . $this->sort . '" now "' . $_sort . '"'; }
				$this->sort   = $_sort;
			}

			if(isset($_order) && !empty($_order))
			{
				if($this->debug) { echo '<br>overriding $order: "' . $this->order . '" now "' . $_order . '"'; }
				$this->order  = $_order;
			}

			if(isset($_filter) && !empty($_filter))
			{
				if($this->debug) { echo '<br>overriding $filter: "' . $this->filter . '" now "' . $_filter . '"'; }
				$this->filter = $_filter;
			}
		}

		function save_sessiondata($data)
		{
			if($this->use_session)
			{
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$GLOBALS['phpgw']->session->appsession('session_data','addressbook',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','addressbook');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->limit  = $data['limit'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			if($this->debug) { echo '<br>read_sessiondata();'; $this->_debug_sqsof(); }
		}

		function import($tsvfile,$conv_type,$private,$fcat_id)
		{
			if($conv_type == 'none')
			{
				return False;
			}
			include(PHPGW_APP_INC . '/import/' . $conv_type);

			if($private == '')
			{
				$private = 'public';
			}
			$row = 0;
			$buffer = array();
			$contacts = new import_conv;

			$buffer = $contacts->import_start_file($buffer);

			if($tsvfile['type'] == 'application/zip')
			{
				$fp = $this->unzip($tsvfile['tmp_name'],$contacts->type);
			}
			else
			{
				$fp = fopen($tsvfile['tmp_name'],'r');
			}
			if($contacts->type == 'csv')
			{
				while($data = fgetcsv($fp,8000,','))
				{
					$num = count($data);
					$row++;
					if($row == 1)
					{
						$header = $data;
						/* Changed here to ignore the header, set to our array
						while(list($lhs,$rhs) = each($contacts->import))
						{
							$header[] = $lhs;
						}
						*/
					}
					else
					{
						$buffer = $contacts->import_start_record($buffer);
						for($c=0; $c<$num; $c++ )
						{
							//Send name/value pairs along with the buffer
							if($contacts->import[$header[$c]] != '' && $data[$c] != '')
							{
								$buffer = $contacts->import_new_attrib($buffer, $contacts->import[$header[$c]],$data[$c]);
							}
						}
						$buffer = $contacts->import_end_record($buffer,$private);
					}
				}
			}
			elseif($contacts->type == 'ldif')
			{
				while($data = fgets($fp,8000))
				{
					$url = '';
					list($name,$value,$extra) = split(':', $data);
					$name = strtolower($name);
					if(substr($name,0,2) == 'dn')
					{
						$buffer = $contacts->import_start_record($buffer);
					}
					
					$test = trim($value);
					if($name && !empty($test) && $extra)
					{
						// Probable url string
						$url = $test;
						$value = $extra;
					}
					elseif($name && empty($test) && $extra)
					{
						// Probable multiline encoding
						$newval = base64_decode(trim($extra));
						$value = $newval;
						//echo $name.':'.$value;
					}

					if($name && $value)
					{
						$test = split(',mail=',$value);
						if($test[1])
						{
							$name = 'mail';
							$value = $test[1];
						}
						if($url)
						{
							$name = 'homeurl';
							$value = $url. ':' . $value;
						}
						//echo '<br>'.$j.': '.$name.' => '.$value;
						if($contacts->import[$name] != '' && $value != '')
						{
							$buffer = $contacts->import_new_attrib($buffer, $contacts->import[$name],$value);
						}
					}
					else
					{
						$buffer = $contacts->import_end_record($buffer,$private);
					}
				}
			}
			else
			{
				$needToCallEndRecord = 0;
				while($data = fgets($fp,8000))
				{
					$data = trim($data);
					// RB 2001/05/07 added for Lotus Organizer
					while(substr($data,-1) == '=')
					{
						// '=' at end-of-line --> line to be continued with next line
						$data = substr($data,0,-1) . trim(fgets($fp,8000));
					}
					if(strstr($data,';ENCODING=QUOTED-PRINTABLE'))
					{
						// RB 2001/05/07 added for Lotus Organizer
						$data = quoted_printable_decode(str_replace(';ENCODING=QUOTED-PRINTABLE','',$data));
					}
					list($name,$value) = explode(':', $data,2); // RB 2001/05/09 to allow ':' in Values (not only in URL's)

					if(strtolower(substr($name,0,5)) == 'begin')
					{
						$buffer = $contacts->import_start_record($buffer);
						$needToCallEndRecord = 1;
					}
					if($name && $value)
					{
						reset($contacts->import);
						while(list($fname,$fvalue) = each($contacts->import))
						{
							if(strstr(strtolower($name), $contacts->import[$fname]))
							{
								$buffer = $contacts->import_new_attrib($buffer,$name,$value);
							}
						}
					}
					else
					{
						$buffer = $contacts->import_end_record($buffer);
						$needToCallEndRecord = 0;
					}
				}
				if($needToCallEndRecord)
				{
					$buffer = $contacts->import_end_record($buffer);
				}
			}

			fclose($fp);
			$buffer = $contacts->import_end_file($buffer,$private,$fcat_id);
			return $buffer;
		}

		/* Use the Zip extension to open a zip file, hopefully containing multiple .vcf files
		 * Return a pointer to a temporary file that will then contain all files concatenated.
		 */
		function unzip($filename,$type)
		{
			$ext = '';
			switch($type)
			{
				case 'vcard':
					$ext = '.vcf';
					break;
				case 'csv':
					$ext = '.csv';
					break;
				case 'ldif':
					$ext = '.ldif';
					break;
				default:
					return False;
			}

			/* Open the (uploaded) zip file */
			$zip  = zip_open($filename);

			$temp = tempnam('/tmp','zip2vcf');
			/* Open a temp file for read/write */
			$fp   = fopen($temp, 'w+');

			$out = '';
			/* Now read each entry in the zip file */
			while($dirent = zip_read($zip))
			{
				if(zip_entry_open($zip,$dirent,'r'))
				{
					//echo '<br>zip_entry_name==' . zip_entry_name($dirent);
					/* If an allowed extenstion based on conversion type */
					if(ereg($ext,zip_entry_name($dirent)))
					{
						/* Write the data to our temp file */
						$data = zip_entry_read($dirent,zip_entry_filesize($dirent));
						//echo $data;
						fwrite($fp,$data);
					}
					zip_entry_close($dirent);
				}
			}
			rewind($fp);
			return $fp;
		}

		function export($conv_type,$cat_id='')
		{
			if($conv_type == 'none')
			{
				return False;
			}
			include(PHPGW_APP_INC . '/export/' . $conv_type);

			$buffer = array();
			$contacts = new export_conv;

			// Read in user custom fields, if any
			$customfields = array();
			while(list($col,$descr) = @each($GLOBALS['phpgw_info']['user']['preferences']['addressbook']))
			{
				if(substr($col,0,6) == 'extra_')
				{
					$field = ereg_replace('extra_','',$col);
					$field = ereg_replace(' ','_',$field);
					$customfields[$field] = ucfirst($field);
				}
			}
			$extrafields = array(
				'ophone'   => 'ophone',
				'address2' => 'address2',
				'address3' => 'address3'
			);

			if($contacts->type != 'vcard')
			{
				$contacts->qfields = $contacts->stock_contact_fields;# + $extrafields;# + $customfields;
			}

			if(!empty($cat_id))
			{
				$buffer = $contacts->export_start_file($buffer,$cat_id);
			}
			else
			{
				$buffer = $contacts->export_start_file($buffer);
			}

			for($i=0;$i<count($contacts->ids);$i++)
			{
				$buffer = $contacts->export_start_record($buffer);
				while(list($name,$value) = each($contacts->currentrecord))
				{
					$buffer = $contacts->export_new_attrib($buffer,$name,$value);
				}
				$buffer = $contacts->export_end_record($buffer);
			}

			// Here, buffer becomes a string suitable for printing
			$buffer = $contacts->export_end_file($buffer);

			$tsvfilename = $GLOBALS['phpgw_info']['server']['temp_dir'] . SEP . $tsvfilename;

			return $buffer;
		}
	}
?>
