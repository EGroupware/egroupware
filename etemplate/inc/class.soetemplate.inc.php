<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates - Storage Objects                       *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/*!
	@class soetemplate
	@author ralfbecker
	@abstract Storage Objects: Everything to store and retrive the eTemplates.
	@discussion eTemplates are stored in the db in table 'phpgw_etemplate' and gets distributed
	@discussion through the file 'etemplates.inc.php' in the setup dir of each app. That file gets
	@discussion automatically imported in the db, whenever you show a eTemplate of the app. For
	@discussion performace reasons the timestamp of the file is stored in the db, so 'new'
	@discussion eTemplates need to have a newer file. The distribution-file is generated with the
	@discussion function dump, usually by pressing a button in the editor.
	@discussion writeLangFile writes an lang-file with all Labels, incorporating an existing one.
	@discussion Beside a name eTemplates use the following keys to find the most suitable template
	@discussion for an user (in order of precedence):
	@discussion  1) User-/Group-Id (not yet implemented)
	@discussion  2) preferd languages of the user (templates for all langs have $lang='')
	@discussion  3) selected template: verdilak, ... (the default is called '' in the db, not default)
	@discussion  4) a version-number of the form, eg: '0.9.13.001' (filled up with 0 same size)
	*/
	class soetemplate
	{
		var $public_functions = array(
			'init'	=> True,
			'read'	=> True,
			'search'	=> True,
			'save'	=> True,
			'delete'	=> True,
			'dump2setup'	=> True,
			'import_dump'	=> True,
			'writeLangFile' => True
		);
		var $name;		// name of the template, e.g. 'infolog.edit'
		var $template;	// '' = default (not 'default')
		var $lang;		// '' if general template else language short, e.g. 'de'
		var $group;		// 0 = not specific else groupId or if < 0  userId
		var $version;	// like 0.9.13.001
		var $size;		// witdh,height,border of table
		var $style;		// embeded CSS style-sheet
		var $db,$db_name = 'phpgw_etemplate'; // DB name
		var $db_key_cols = array(
			'et_name' => 'name',
			'et_template' => 'template',
			'et_lang' => 'lang',
			'et_group' => 'group',
			'et_version' => 'version'
		);
		var $db_data_cols = array(
			'et_data' => 'data',
			'et_size' => 'size',
			'et_style' => 'style'
		);
		var $db_cols;

		/*!
		@function soetemplate
		@abstract constructor of the class
		@param as read
		*/
		function soetemplate($name='',$template='',$lang='',$group=0,$version='',$rows=2,$cols=2)
		{
			$this->db = $GLOBALS['phpgw']->db;
			$this->db_cols = $this->db_key_cols + $this->db_data_cols;

			$this->read($name,$template,$lang,$group,$version,$rows,$cols);
		}

		/*!
		@function num2chrs
		@abstract generates column-names from index: 'A', 'B', ..., 'AA', 'AB', ..., 'ZZ' (not more!)
		@param $num index to generate name from 1 => 'A'
		@result the name
		*/
		function num2chrs($num)
		{
			$min = ord('A');
			$max = ord('Z') - $min + 1;
			if ($num >= $max)
			{
				$chrs = chr(($num / $max) + $min - 1);
			}
			$chrs .= chr(($num % $max) + $min);

			return $chrs;
		}

		/*!
		@function empty_cell
		@abstracts constructor for a new / empty cell (nothing fancy so far)
		@result the cell
		*/
		function empty_cell()
		{
			return array('type' => 'label', 'name' => '');
		}

		/*!
		@function init
		@abstract initialises all internal data-structures of the eTemplate and sets the keys
		@param $name name of the eTemplate or array with the keys or all data
		@param $template,$lang,$group,$version see class
		@param $rows,$cols initial size of the template
		*/
		function init($name='',$template='',$lang='',$group=0,$version='',$rows=1,$cols=1)
		{
			reset($this->db_cols);
			while (list($db_col,$col) = each($this->db_cols))
			{
				$this->$col = is_array($name) ? $name[$col] : $$col;
			}
			if ($this->template == 'default')
			{
				$this->template = '';
			}
			if ($this->lang == 'default')
			{
				$this->lang = '';
			}
			if (is_array($name) && isset($name['data']))
			{
				return;	// data already set
			}
			$this->size = $this->style = '';
			$this->data = array();
			$this->rows = $rows < 0 ? 1 : $rows;
			$this->cols = $cols < 0 ? 1 : $cols;
			for ($row = 1; $row <= $rows; ++$row)
			{
				for ($col = 0; $col < $cols; ++$col)
				{
					$this->data[$row][$this->num2chrs($col)] = $this->empty_cell();
				}
			}
		}

		/*!
		@function read
		@abstract Reads an eTemplate from the database
		@param as discripted with the class, with the following exeptions
		@param $template as '' loads the prefered template 'default' loads the default one '' in the db
		@param $lang as '' loads the pref. lang 'default' loads the default one '' in the db
		@param $group is NOT used / implemented yet
		@result True if a fitting template is found, else False
		*/
		function read($name,$template='default',$lang='default',$group=0,$version='')
		{
			$this->init($name,$template,$lang,$group,$version);

			if ($GLOBALS['phpgw_info']['server']['eTemplate-source'] == 'files' && $this->readfile())
			{
				return True;
			}
			if ($this->name)
			{
				$this->test_import($this->name);	// import updates in setup-dir
			}
			$pref_lang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			$pref_templ = $GLOBALS['phpgw_info']['server']['template_set'];

			$sql = "SELECT * FROM $this->db_name WHERE et_name='$this->name' AND ";
			if (is_array($name))
			{
				$template = $name['template'];
			}
			if ($template == 'default')
			{
				$sql .= "(et_template='$pref_templ' OR et_template='')";
			}
			else
			{
				$sql .= "et_template='$this->template'";
			}
			$sql .= ' AND ';
			if (is_array($name))
			{
				$lang = $name['lang'];
			}
			if ($lang == 'default' || $name['lang'] == 'default')
			{
				$sql .= "(et_lang='$pref_lang' OR et_lang='')";
			}
			else
			{
				$sql .= "et_lang='$this->lang'";
			}
			if ($this->version != '')
			{
				$sql .= "AND et_version='$this->version'";
			}
			$sql .= " ORDER BY et_lang DESC,et_template DESC,et_version DESC";

			$this->db->query($sql,__LINE__,__FILE__);
			if (!$this->db->next_record())
			{
				return $this->readfile();
			}
			$this->db2obj();

			return True;
		}

		/*!
		@function readfile
		@abstract Reads an eTemplate from the filesystem, the keys are already set by init in read
		@result True if a template is found, else False
		*/
		function readfile()
		{
			list($app,$name) = split("\.",$this->name,2);
			$template = $this->template == '' ? 'default' : $this->template;
			$file = PHPGW_SERVER_ROOT . "/$app/templates/$template/$name";
			if ($this->lang)
			{
				$file .= '.' . $this->lang;
			}
			$file .= '.xul';

			if ($this->name == '' || $app == '' || $name == '' || !@file_exists($file) || !($f = @fopen($file,'r')))
			{
				//echo "<p>Can't open '$file' !!!</p>\n";
				return False;
			}
			$xul = fread ($f, filesize ($file));
			fclose($f);

			if (!is_object($this->xul_io))
			{
				$this->xul_io = CreateObject('etemplate.xul_io');
			}
			if ($this->xul_io->import(&$this,$xul) != '')
			{
				return False;
			}
			$this->name = $app . '.' . $name;	// if template was copied or app was renamed

			return True;
		}

		/*!
		@function search
		@syntax search($name,$template='default',$lang='default',$group=0,$version='')
		@author ralfbecker
		@abstract Lists the eTemplates matching the given criteria
		@param as discripted with the class, with the following exeptions
		@param $template as '' loads the prefered template 'default' loads the default one '' in the db
		@param $lang as '' loads the pref. lang 'default' loads the default one '' in the db
		@param $group is NOT used / implemented yet
		@result array of arrays with the template-params
		*/
		function search($name,$template='default',$lang='default',$group=0,$version='')
		{
			if ($this->name)
			{
				$this->test_import($this->name);	// import updates in setup-dir
			}
			$pref_lang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			$pref_templ = $GLOBALS['phpgw_info']['server']['template_set'];

			if (is_array($name))
			{
				$template = $name['template'];
				$lang = $name['lang'];
				$group = $name['group'];
				$version = $name['version'];
				$name = $name['name'];
			}
			$sql = "SELECT et_name,et_template,et_lang,et_group,et_version FROM $this->db_name WHERE et_name LIKE '$name%'";

			if ($template != '' && $template != 'default')
			{
				$sql .= " AND et_template LIKE '$template%'";
			}
			if ($lang != '' && $lang != 'default')
			{
				$sql .= " AND et_lang LIKE '$lang%'";
			}
			if ($this->version != '')
			{
				$sql .= " AND et_version LIKE '$version%'";
			}
			$sql .= " ORDER BY et_name DESC,et_lang DESC,et_template DESC,et_version DESC";

			$this->db->query($sql,__LINE__,__FILE__);

			$result = array();
			while ($this->db->next_record())
			{
				$result[] = $this->db->Record;
			}
			return $result;
		}

		/*!
		@function db2obj
		@abstract copies all cols into the obj and unserializes the data-array
		*/
		function db2obj()
		{
			for (reset($this->db_cols); list($db_col,$name) = each($this->db_cols); )
			{
				$this->$name = $this->db->f($db_col);
			}
			$this->data = unserialize(stripslashes($this->data));

			if ($this->name[0] != '.')
			{
				reset($this->data); each($this->data);
				while (list($row,$cols) = each($this->data))
				{
					while (list($col,$cell) = each($cols))
					{
						if (is_array($cell['type']))
						{
							$this->data[$row][$col]['type'] = $cell['type'][0];
							//echo "corrected in $this->name cell $col$row attribute type<br>\n";
						}
						if (is_array($cell['align']))
						{
							$this->data[$row][$col]['align'] = $cell['align'][0];
							//echo "corrected in $this->name cell $col$row attribute align<br>\n";
						}
					}
				}
			}
			$this->rows = count($this->data) - 1;
			$this->cols = count($this->data[1]); // 1 = first row, not 0
		}

		/*!
		@function compress_array
		@syntax compress_array( $arr )
		@author ralfbecker
		@abstract to save space in the db all empty values in the array got unset
		@discussion The never-'' type field ensures a cell does not disapear completely.
		@discussion Calls it self recursivly for arrays / the rows
		@param $arr the array to compress
		@result the compressed array
		*/
		function compress_array($arr)
		{
			if (!is_array($arr))
			{
				return $arr;
			}
			while (list($key,$val) = each($arr))
			{
				if (is_array($val))
				{
					$arr[$key] = $this->compress_array($val);
				}
				elseif ($val == '' || $val == '0')
				{
					unset($arr[$key]);
				}
			}
			return $arr;
		}

		/*!
		@function as_array
		@abstract returns obj-data as array
		@param $data_too 0 = no data array, 1 = data array too, 2 = serialize data array
		@result the array
		*/
		function as_array($data_too=0)
		{
			$arr = array();
			reset($this->db_cols);
			while (list($db_col,$col) = each($this->db_cols))
			{
				if ($col != 'data' || $data_too)
				{
					$arr[$col] = $this->$col;
				}
			}
			if ($data_too == 2)
			{
				$arr['data'] = serialize($arr['data']);
			}
			return $arr;
		}

		/*!
		@function save
		@abstract saves eTemplate-object to db, can be used as saveAs by giving keys as params
		@params keys see class
		@result the number of affected rows, 1 should be ok, 0 somethings wrong
		*/
		function save($name='',$template='.',$lang='.',$group='',$version='.')
		{
			if ($name != '')
			{
				$this->name = $name;
			}
			if ($lang != '.')
			{
				$this->lang = $lang;
			}
			if ($template != '.')
			{
				$this->template = $template;
			}
			if ($group != '')
			{
				$this->group = $group;
			}
			if ($version != '.')
			{
				$this->version = $version;
			}
			if ($this->name == '')	// name need to be set !!!
			{
				return False;
			}
			$this->delete();	// so we have always a new insert

			if ($this->name[0] != '.')		// correct up old messed up templates
			{
				reset($this->data); each($this->data);
				while (list($row,$cols) = each($this->data))
				{
					while (list($col,$cell) = each($cols))
					{
						if (is_array($cell['type'])) {
							$this->data[$row][$col]['type'] = $cell['type'][0];
							//echo "corrected in $this->name cell $col$row attribute type<br>\n";
						}
						if (is_array($cell['align'])) {
							$this->data[$row][$col]['align'] = $cell['align'][0];
							//echo "corrected in $this->name cell $col$row attribute align<br>\n";
						}
					}
				}
			}
			$data = $this->as_array(1);
			$data['data'] = serialize($this->compress_array($data['data']));

			$sql = "INSERT INTO $this->db_name (";
			for (reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
			{
				$sql .= $db_col . ',';
				$vals .= "'" . addslashes($data[$col]) . "',";
			}
			$sql[strlen($sql)-1] = ')';
			$sql .= " VALUES ($vals";
			$sql[strlen($sql)-1] = ')';

			$this->db->query($sql,__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		/*!
		@function delete
		@abstract Deletes the eTemplate from the db, object itself is unchanged
		@result the number of affected rows, 1 should be ok, 0 somethings wrong
		*/
		function delete()
		{
			for (reset($this->db_key_cols); list($db_col,$col) = each($this->db_key_cols); )
			{
				$vals .= ($vals ? ' AND ' : '') . $db_col . "='" . $this->$col . "'";
			}
			$this->db->query("DELETE FROM $this->db_name WHERE $vals",__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		/*!
		@function dump2setup
		@abstract dumps all eTemplates to <app>/setup/etemplates.inc.php for distribution
		@param $app app- or template-name
		@result the number of templates dumped as message
		*/
		function dump2setup($app)
		{
			list($app) = explode('.',$app);

			$this->db->query("SELECT * FROM $this->db_name WHERE et_name LIKE '$app%'");

			$dir = PHPGW_SERVER_ROOT . "/$app/setup";
			if (!is_writeable($dir))
			{
				return "Error: webserver is not allowed to write into '$dir' !!!";
			}
			$file = "$dir/etemplates.inc.php";
			if (file_exists($file))
			{
				rename($file,"$dir/etemplates.old.inc.php");
			}

			if (!($f = fopen($file,'w')))
			{
				return 0;
			}
			fwrite($f,"<?php\n// eTemplates for Application '$app', generated by etemplate.dump() ".date('Y-m-d H:i')."\n\n");
			for ($n = 0; $this->db->next_record(); ++$n)
			{
				$str = '$templ_data[] = array(';
				for (reset($this->db_cols); list($db_col,$name) = each($this->db_cols); )
				{
					$str .= "'$name' => '".addslashes($this->db->f($db_col))."',";
				}
				$str .= ");\n\n";
				fwrite($f,$str);
			}
			fclose($f);

			return "$n eTemplates for Application '$app' dumped to '$file'";
		}

		/*!
		@function getToTranslate
		@abstract extracts all texts: labels and helptexts from an eTemplate-object
		@discussion some extensions use a '|' to squezze multiple texts in a label or help field
		@result array with messages as key AND value
		*/
		function getToTranslate()
		{
			$to_trans = array();

			reset($this->data); each($this->data); // skip width
			while (list($row,$cols) = each($this->data))
			{
				while (list($col,$cell) = each($cols))
				{
					$all = explode('|',$cell['help'].($cell['type'] != 'image'?'|'.$cell['label']:''));
					while (list(,$str) = each($all))
					{
						if (strlen($str) > 1)
						{
							$to_trans[strtolower($str)] = $str;
						}
					}
				}
			}
			return $to_trans;
		}

		/*!
		@function getToTranslateApp
		@abstract Read all eTemplates of an app an extracts the texts to an array
		@param $app name of the app
		@result the array with texts
		*/
		function getToTranslateApp($app)
		{
			$to_trans = array();

			$tmpl = new soetemplate;	// to not alter our own data
			$tmpl->db->query("SELECT * FROM $this->db_name WHERE et_name LIKE '$app.%'");

			for ($n = 0; $tmpl->db->next_record(); ++$n)
			{
				$tmpl->db2obj();
				$to_trans += $tmpl->getToTranslate();
			}
			return $to_trans;
		}

		/*!
		@function writeLangFile
		@abstract Write new lang-file using the existing one and all text from the eTemplates
		@param $app app- or template-name
		@param $lang language the messages in the template are, defaults to 'en'
		@param $additional extra texts to translate, if you pass here an array with all messages and
		@param             select-options they get writen too (form is <unique key> => <message>)
		@result message with number of messages written (total and new)
		*/
		function writeLangFile($app,$lang='en',$additional='')
		{
			if (!$additional)
			{
				$addtional = array();
			}
			list($app) = explode('.',$app);

			$solangfile = CreateObject('developer_tools.solangfile');

			$langarr = $solangfile->load_app($app,$lang);

			$to_trans = $this->getToTranslateApp($app);
			if (is_array($additional))
			{
				//echo "writeLangFile: additional ="; _debug_array($additional);
				reset($additional);
				while (list($nul,$msg) = each($additional))
				{
					$to_trans[strtolower($msg)] = $msg;
				}
			}
			for ($new = $n = 0; list($message_id,$content) = each($to_trans); ++$n) {
				if (!isset($langarr[$content]) && !isset($langarr[$message_id]))
				{	// caused by not lowercased-message_id's
					$langarr[$message_id] = $langarr[$content];
					unset($langarr[$content]);
				}
				if (!isset($langarr[$message_id]))
				{
					$langarr[$message_id] = array(
						'message_id' => $message_id,
						'app_name' => $app,
						'content' => $content
					);
					++$new;
				}
			}
			ksort($langarr);

			$dir = PHPGW_SERVER_ROOT . "/$app/setup";
			if (!is_writeable($dir))
			{
				return "Error: webserver is not allowed to write into '$dir' !!!";
			}
			$file = "$dir/phpgw_$lang.lang";
			if (file_exists($file))
			{
				rename($file,"$dir/phpgw_$lang.old.lang");
			}
			$solangfile->write_file($app,$langarr,$lang);
			$solangfile->loaddb($app,$lang);

			return "$n ($new new) Messages writen for Application '$app' and Languages '$lang'";
		}

		/*!
		@function import_dump
		@abstract Imports the dump-file /$app/setup/etempplates.inc.php unconditional (!)
		@param $app app name
		@result message with number of templates imported
		*/
		function import_dump($app)
		{
			include(PHPGW_SERVER_ROOT."/$app/setup/etemplates.inc.php");
			$templ = new etemplate($app);

			for ($n = 0; isset($templ_data[$n]); ++$n)
			{
				for (reset($this->db_cols); list($db_col,$name) = each($this->db_cols); )
				{
					$templ->$name = $templ_data[$n][$name];
				}
				$templ->data = unserialize(stripslashes($templ->data));
				$templ->save();
			}
			return "$n new eTemplates imported for Application '$app'";
		}

		/*!
		@function test_import
		@abstract test if new template-import necessary for app and does the import
		@discussion Get called on every read of a eTemplate, caches the result in phpgw_info.
		@discussion The timestamp of the last import for app gets written into the db.
		@param $app app- or template-name
		*/
		function test_import($app)	// should be done from the setup-App
		{
			list($app) = explode('.',$app);

			if (!$app || $GLOBALS['phpgw_info']['etemplate']['import_tested'][$app])
			{
				return '';	// ensure test is done only once per call and app
			}
			$GLOBALS['phpgw_info']['etemplate']['import_tested'][$app] = True;	// need to be done before new ...

			$path = PHPGW_SERVER_ROOT."/$app/setup/etemplates.inc.php";

			if ($time = filemtime($path))
			{
				$templ = new etemplate(".$app",'','##');
				if ($templ->lang != '##' || $templ->data[0] < $time) // need to import
				{
					$ret = $this->import_dump($app);
					$templ->data = array($time);
					$templ->save(".$app",'','##');
				}
			}
			return $ret;
		}
	};
