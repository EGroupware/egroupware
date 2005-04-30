<?php
  /**************************************************************************\
  * eGroupWare - Addressbook: CSV - Import                                   *
  * http://www.egroupware.org                                                *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['egw_info']['flags'] = array(
		'currentapp' => 'addressbook',
		'noheader'   => True,
		'enable_contacts_class' => True,
	);
	include('../header.inc.php');

	if (isset($_FILES['csvfile']['tmp_name']))
	{
		$csvfile = tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
		$GLOBALS['egw']->session->appsession('csvfile','',$csvfile);
		$_POST['action'] = move_uploaded_file($_FILES['csvfile']['tmp_name'],$csvfile) ?
			'download' : '';
	}
	else
	{
		$csvfile = $GLOBALS['egw']->session->appsession('csvfile');
	}
	if ($_POST['cancel'])
	{
		@unlink($csvfile);
		$GLOBALS['egw']->redirect_link('/addressbook/index.php');
	}
	$GLOBALS['egw_info']['flags']['app_header'] = lang('Import CSV-File into Addressbook');
	$GLOBALS['egw']->common->phpgw_header();

	$GLOBALS['egw']->contacts = createobject('phpgwapi.contacts');

	//$GLOBALS['egw']->template->set_unknowns('keep');
	$GLOBALS['egw']->template->set_file(array('import' => 'csv_import.tpl'));
	$GLOBALS['egw']->template->set_block('import','filename','filenamehandle');
	$GLOBALS['egw']->template->set_block('import','fheader','fheaderhandle');
	$GLOBALS['egw']->template->set_block('import','fields','fieldshandle');
	$GLOBALS['egw']->template->set_block('import','ffooter','ffooterhandle');
	$GLOBALS['egw']->template->set_block('import','imported','importedhandle');

	if(($_POST['action'] == 'download' || $_POST['action'] == 'continue') && (!$_POST['fieldsep'] || !$csvfile || !($fp=fopen($csvfile,'rb'))))
	{
		$_POST['action'] = '';
	}
	$GLOBALS['egw']->template->set_var('action_url',$GLOBALS['egw']->link('/addressbook/csv_import.php'));

	$PSep = '||'; // Pattern-Separator, separats the pattern-replacement-pairs in trans
	$ASep = '|>'; // Assignment-Separator, separats pattern and replacesment
	$VPre = '|#'; // Value-Prefix, is expanded to \ for ereg_replace
	$CPre = '|['; $CPreReg = '\|\['; // |{csv-fieldname} is expanded to the value of the csv-field
	$CPos = ']';  $CPosReg = '\]';	// if used together with @ (replacement is eval-ed) value gets autom. quoted

	// find in Addressbook, at least n_family AND (n_given OR org_name) have to match
	function addr_id($n_family,$n_given,$org_name)
	{
		$addrs = $GLOBALS['egw']->contacts->read(0,0,array('id'),'',"n_family=$n_family,n_given=$n_given,org_name=$org_name");
		if(!count($addrs))
		{
			$addrs = $GLOBALS['egw']->contacts->read(0,0,array('id'),'',"n_family=$n_family,n_given=$n_given");
		}
		if(!count($addrs))
		{
			$addrs = $GLOBALS['egw']->contacts->read(0,0,array('id'),'',"n_family=$n_family,org_name=$org_name");
		}

		if(count($addrs))
		{
			return $addrs[0]['id'];
		}

		return False;
	}

	$cat2id = array();

	function cat_id($cats)
	{
		if(!$cats)
		{
			return '';
		}

		$ids = array();
		foreach(split(' *[,;] *',$cats) as $cat)
		{
			if(isset($cat2id[$cat]))
			{
				$ids[$cat] = $cat2id[$cat];	// cat is in cache
			}
			else
			{
				if(!is_object($GLOBALS['egw']->categories))
				{
					$GLOBALS['egw']->categories = createobject('phpgwapi.categories');
				}
				if (is_numeric($cat) && $GLOBALS['egw']->categories->id2name($cat) != '--')
				{
					$cat2id[$cat] = $ids[$cat] = $cat;
				}	
				elseif ($id = $GLOBALS['egw']->categories->name2id(addslashes($cat)))
				{	// cat exists
					$cat2id[$cat] = $ids[$cat] = $id;
				}
				else
				{	// create new cat
					$GLOBALS['egw']->categories->add(array('name' => $cat,'descr' => $cat));
					$cat2id[$cat] = $ids[$cat] = $GLOBALS['egw']->categories->name2id(addslashes($cat));
				}
			}
		}
		$id_str = implode(',',$ids);

		if(count($ids) > 1)		// multiple cats need to be in ','
		{
			$id_str = ",$id_str,";
		}
		return $id_str;
	}
	if (!is_object($GLOBALS['egw']->html))
	{
		$GLOBALS['egw']->html = CreateObject('phpgwapi.html');
	}

	if ($_POST['next']) $_POST['action'] = 'next';

	switch($_POST['action'])
	{
		case '':	// Start, ask Filename
			$GLOBALS['egw']->template->set_var('lang_csvfile',lang('CSV-Filename'));
			$GLOBALS['egw']->template->set_var('lang_fieldsep',lang('Fieldseparator'));
			$GLOBALS['egw']->template->set_var('lang_charset',lang('Charset of file'));
			$GLOBALS['egw']->template->set_var('select_charset',
				$GLOBALS['egw']->html->select('charset','',
				$GLOBALS['egw']->translation->get_installed_charsets()+
				array('utf-8' => 'utf-8 (Unicode)'),True));
			$GLOBALS['egw']->template->set_var('fieldsep',$_POST['fieldsep'] ? $_POST['fieldsep'] : ',');
			$GLOBALS['egw']->template->set_var('submit',lang('Import'));
			$GLOBALS['egw']->template->set_var('csvfile',$csvfile);
			$GLOBALS['egw']->template->set_var('enctype','ENCTYPE="multipart/form-data"');

			$GLOBALS['egw']->template->parse('filenamehandle','filename');
			break;

		case 'continue':
		case 'download':
			$defaults = $GLOBALS['egw_info']['user']['preferences']['addressbook']['cvs_import'];
			if(!is_array($defaults))
			{
				$defaults = array();
			}
			$GLOBALS['egw']->template->set_var('lang_csv_fieldname',lang('CSV-Fieldname'));
			$GLOBALS['egw']->template->set_var('lang_addr_fieldname',lang('Addressbook-Fieldname'));
			$GLOBALS['egw']->template->set_var('lang_translation',lang("Translation").' <a href="#help">'.lang('help').'</a>');
			$GLOBALS['egw']->template->set_var('submit',
				$GLOBALS['egw']->html->submit_button('convert','Import') . '&nbsp;'.
				$GLOBALS['egw']->html->submit_button('cancel','Cancel'));
			$GLOBALS['egw']->template->set_var('lang_debug',lang('Test Import (show importable records <u>only</u> in browser)'));
			$GLOBALS['egw']->template->parse('fheaderhandle','fheader');

			$addr_names = $GLOBALS['egw']->contacts->stock_contact_fields + array(
				'cat_id' => 'Categories: id\'s or names, comma separated list',
				'access' => 'Access: public, private',
				'owner'  => 'Owner: user-id/-name, defaults to user',
				'address2' => 'address line 2',
				'address3' => 'address line 3',
				'ophone'   => 'Other Phone'
			);
			$config = CreateObject('phpgwapi.config','addressbook');
			$config->read_repository();
			while(list($name,$descr) = @each($config->config_data['custom_fields']))
			{
				$addr_names[$name] = $descr;
			}
			unset($config);

			foreach($addr_names as $field => $name)
			{
				if($dn = display_name($field))
				{
					$addr_names[$field] = $dn;
				}
			}
			$addr_name_options = "<option value=\"\">none\n";
			foreach($addr_names as $field => $name)
			{
				$addr_name_options .= "<option value=\"$field\">".$GLOBALS['egw']->strip_html($name)."\n";
			}
			$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
			$csv_fields = $GLOBALS['egw']->translation->convert($csv_fields,$_POST['charset']);
			$csv_fields[] = 'no CSV 1'; // eg. for static assignments
			$csv_fields[] = 'no CSV 2';
			$csv_fields[] = 'no CSV 3';
			foreach($csv_fields as $csv_idx => $csv_field)
			{
				$GLOBALS['egw']->template->set_var('csv_field',$csv_field);
				$GLOBALS['egw']->template->set_var('csv_idx',$csv_idx);
				if($def = $defaults[$csv_field])
				{
					list($addr,$_POST['trans']) = explode($PSep,$def,2);
					$GLOBALS['egw']->template->set_var('trans',$_POST['trans']);
					$GLOBALS['egw']->template->set_var('addr_fields',str_replace('="'.$addr.'">','="'.$addr.'" selected>',$addr_name_options));
				}
				else
				{
					$GLOBALS['egw']->template->set_var('trans','');
					$GLOBALS['egw']->template->set_var('addr_fields',$addr_name_options);
				}
				$GLOBALS['egw']->template->parse('fieldshandle','fields',True);
			}
			$GLOBALS['egw']->template->set_var('lang_start',lang('Startrecord'));
			$GLOBALS['egw']->template->set_var('start',get_var('start',array('POST'),1));
			$msg = ($safe_mode = ini_get('safe_mode') == 'On') ? lang('to many might exceed your execution-time-limit'):
				lang('empty for all');
			$GLOBALS['egw']->template->set_var('lang_max',lang('Number of records to read (%1)',$msg));
			$GLOBALS['egw']->template->set_var('max',get_var('max',array('POST'),$safe_mode ? 200 : ''));
			$GLOBALS['egw']->template->set_var('debug',get_var('debug',array('POST'),True)?' checked':'');
			$GLOBALS['egw']->template->parse('ffooterhandle','ffooter');
			fclose($fp);

			$hiddenvars = $GLOBALS['egw']->html->input_hidden(array(
				'action'  => 'import',
				'fieldsep'=> $_POST['fieldsep'],
				'charset' => $_POST['charset']
			));
			$mktime_lotus = "${PSep}0?([0-9]+)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*).*$ASep@mktime(${VPre}4,${VPre}5,${VPre}6,${VPre}2,${VPre}3,${VPre}1)";
			$help_on_trans = "<a name=\"help\"></a><b>How to use Translation's</b><p>".
				"Translations enable you to change / adapt the content of each CSV field for your needs. <br>".
				"General syntax is: <b>pattern1 ${ASep} replacement1 ${PSep} ... ${PSep} patternN ${ASep} replacementN</b><br>".
				"If the pattern-part of a pair is ommited it will match everything ('^.*$'), which is only ".
				"usefull for the last pair, as they are worked from left to right.<p>".
				"First example: <b>1${ASep}private${PSep}public</b><br>".
				"This will translate a '1' in the CSV field to 'privat' and everything else to 'public'.<p>".
				"Patterns as well as the replacement can be regular expressions (the replacement is done via ereg_replace). ".
				"If, after all replacements, the value starts with an '@' the whole value is eval()'ed, so you ".
				"may use all php, phpgw plus your own functions. This is quiet powerfull, but <u>circumvents all ACL</u>. ".
				"Therefor this feature is only availible to Adminstrators.<p>".
				"Example using regular expressions and '@'-eval(): <br><b>$mktime_lotus</b><br>".
				"It will read a date of the form '2001-05-20 08:00:00.00000000000000000' (and many more, see the regular expr.). ".
				"The&nbsp;[&nbsp;.:-]-separated fields are read and assigned in different order to @mktime(). Please note to use ".
				"${VPre} insted of a backslash (I couldn't get backslash through all the involved templates and forms.) ".
				"plus the field-number of the pattern.<p>".
				"In addintion to the fields assign by the pattern of the reg.exp. you can use all other CSV-fields, with the ".
				"syntax <b>${CPre}CSV-FIELDNAME$CPos</b>. Here is an example: <br>".
				"<b>.+$ASep${CPre}Company$CPos: ${CPre}NFamily$CPos, ${CPre}NGiven$CPos$PSep${CPre}NFamily$CPos, ${CPre}NGiven$CPos</b><br>".
				"It is used on the CSV-field 'Company' and constructs a something like <i>Company: FamilyName, GivenName</i> or ".
				"<i>FamilyName, GivenName</i> if 'Company' is empty.<p>".
				"You can use the 'No CSV #'-fields to assign csv-values to more than on field, the following example uses the ".
				"csv-field 'Note' (which gots already assingned to the description) and construct a short subject: ".
				"<b>@substr(${CPre}Note$CPos,0,60).' ...'</b><p>".
				"Their is one important user-function for the Addressbook:<br>".
				"<b>@cat_id(Cat1,...,CatN)</b> returns a (','-separated) list with the cat_id's. If a category isn't found, it ".
				"will be automaticaly added.<p>".
				"I hope that helped to understand the features, if not <a href='mailto:RalfBecker@outdoor-training.de'>ask</a>.";

			$GLOBALS['egw']->template->set_var('help_on_trans',lang($help_on_trans));	// I don't think anyone will translate this
			break;

		case 'next':
			$_POST['addr_fields'] = unserialize(stripslashes($_POST['addr_fields']));
			$_POST['trans']       = unserialize(stripslashes($_POST['trans']));
			// fall-through
		case 'import':
			$hiddenvars = $GLOBALS['egw']->html->input_hidden(array(
				'action'  => 'continue',
				'fieldsep'=> $_POST['fieldsep'],
				'charset' => $_POST['charset'],
				'start'   => $_POST['start']+(!$_POST['debug'] ? $_POST['max'] : 0),
				'max'     => $_POST['max'],
				'debug'   => $_POST['debug'],
				'addr_fields' => $_POST['addr_fields'],
				'trans'   => $_POST['trans']
			));
			@set_time_limit(0);
			$fp=fopen($csvfile,'rb');
			$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
			$csv_fields = $GLOBALS['egw']->translation->convert($csv_fields,$_POST['charset']);
			$csv_fields[] = 'no CSV 1'; // eg. for static assignments
			$csv_fields[] = 'no CSV 2';
			$csv_fields[] = 'no CSV 3';

			$addr_fields = array_diff($_POST['addr_fields'],array(''));	// throw away empty / not assigned entrys

			$defaults = array();
			foreach($addr_fields as $csv_idx => $addr)
			{	// convert $_POST['trans'][$csv_idx] into array of pattern => value
				$defaults[$csv_fields[$csv_idx]] = $addr;
				if($_POST['trans'][$csv_idx])
				{
					$defaults[$csv_fields[$csv_idx]] .= $PSep.$_POST['trans'][$csv_idx];
				}
			}
			$GLOBALS['egw']->preferences->read_repository();
			$GLOBALS['egw']->preferences->add('addressbook','cvs_import',$defaults);
			$GLOBALS['egw']->preferences->save_repository(True);

			$log = "<table border=1>\n\t<tr><td>#</td>\n";

			foreach($addr_fields as $csv_idx => $addr)
			{	// convert $_POST['trans'][$csv_idx] into array of pattern => value
				// if (!$_POST['debug']) echo "<p>$csv_idx: ".$csv_fields[$csv_idx].": $addr".($_POST['trans'][$csv_idx] ? ': '.$_POST['trans'][$csv_idx] : '')."</p>";
				$pat_reps = explode($PSep,stripslashes($_POST['trans'][$csv_idx]));
				$replaces = ''; $values = '';
				if($pat_reps[0] != '')
				{
					foreach($pat_reps as $k => $pat_rep)
					{
						list($pattern,$replace) = explode($ASep,$pat_rep,2);
						if($replace == '')
						{
							$replace = $pattern; $pattern = '^.*$';
						}
						$values[$pattern] = $replace;	// replace two with only one, added by the form
						$replaces .= ($replaces != '' ? $PSep : '') . $pattern . $ASep . $replace;
					}
					$_POST['trans'][$csv_idx] = $values;
				}
				else
				{
					unset( $_POST['trans'][$csv_idx] );
				}
				$log .= "\t\t<td><b>$addr</b></td>\n";
			}
			if (!in_array('fn',$addr_fields))	// autocreate full name, if not set by user
			{
				$log .= "\t\t<td><b>fn</b></td>\n";

				$auto_fn = array('n_prefix','n_given','n_middle','n_family','n_suffix');
			}
			if (!in_array('access',$addr_fields))	// autocreate public access if not set by user
			{
				$log .= "\t\t<td><b>access</b></td>\n";
			}
			$start = $_POST['start'] < 1 ? 1 : $_POST['start'];

			// ignore empty lines, is_null($fields[0]) is returned on empty lines !!!
			for($i = 1; $i < $start; ++$i) 	// overread lines before our start-record
			{
				while(($fields = fgetcsv($fp,8000,$_POST['fieldsep'])) && is_null($fields[0])) ;
			}
			for($anz = 0; !$_POST['max'] || $anz < $_POST['max']; ++$anz)
			{
				while(($fields = fgetcsv($fp,8000,$_POST['fieldsep'])) && is_null($fields[0])) ;
				if (!$fields)
				{
					break;	// EOF
				}
				$fields = $GLOBALS['egw']->translation->convert($fields,$_POST['charset']);

				$log .= "\t</tr><tr><td>".($start+$anz)."</td>\n";

				$values = array();
				foreach($addr_fields as $csv_idx => $addr)
				{
					//echo "<p>$csv: $addr".($_POST['trans'][$csv] ? ': '.$_POST['trans'][$csv] : '')."</p>";
					$val = $fields[$csv_idx];
					if(isset($_POST['trans'][$csv_idx]))
					{
						$trans_csv = $_POST['trans'][$csv_idx];
						while(list($pattern,$replace) = each($trans_csv))
						{
							if(ereg((string) $pattern,$val))
							{
								// echo "<p>csv_idx='$csv_idx',info='$addr',trans_csv=".print_r($trans_csv).",ereg_replace('$pattern','$replace','$val') = ";
								$val = ereg_replace((string) $pattern,str_replace($VPre,'\\',$replace),(string) $val);
								// echo "'$val'</p>";

								$reg = $CPreReg.'([a-zA-Z_0-9]+)'.$CPosReg;
								while(ereg($reg,$val,$vars))
								{	// expand all CSV fields
									$val = str_replace($CPre . $vars[1] . $CPos, $val[0] == '@' ? "'"
										. addslashes($fields[array_search($vars[1], $csv_fields)])
										. "'" : $fields[array_search($vars[1], $csv_fields)], $val);
								}
								if($val[0] == '@')
								{
									if (!$GLOBALS['egw_info']['user']['apps']['admin'])
									{
										echo lang('@-eval() is only availible to admins!!!');
									}
									else
									{
										// removing the $ to close security hole of showing vars, which contain eg. passwords
										$val = 'return '.substr(str_replace('$','',$val),1).';';
										// echo "<p>eval('$val')=";
										$val = eval($val);
										// echo "'$val'</p>";
									}
								}
								if($pattern[0] != '@' || $val)
								{
									break;
								}
							}
						}
					}
					$values[$addr] = $val;

					$log .= "\t\t<td>$val</td>\n";
				}
				$empty = !count($values);

				// convert the category name to an id
				if ($values['cat_id'] && !is_numeric($values['cat_id']) && $values['cat_id'][0] != ',')
				{
					$values['cat_id'] = cat_id($values['cat_id']);
				}

				// convert user-names to user-id's
				foreach(array('owner') as $user)
				{
					if (isset($values[$user]) && !is_numeric($user))
					{
						$values[$user] = $GLOBALS['egw']->accounts->name2id($values[$user]);
					}
				}
				if (is_array($auto_fn))	// autocreate full name
				{
					foreach($auto_fn as $name)
					{
						$values['fn'] .= ($values['fn'] != '' && $values[$name] != '' ? ' ' : '') . $values[$name];
					}
					$log .= "\t\t<td>".$values['fn']."</td>\n";
				}
				if (!in_array('access',$addr_fields))
				{
					$values['access'] = 'public';	// public access if not set by user
					$log .= "\t\t<td>".$values['access']."</td>\n";
				}
				if(!$_POST['debug'] && !$empty)	// dont import empty contacts
				{
					$GLOBALS['egw']->contacts->add( $values['owner'] ? $values['owner'] : $GLOBALS['egw_info']['user']['account_id'],
						$values);
					// echo "<p>adding: ".print_r($values)."</p>\n";
				}
			}
			$log .= "\t</tr>\n</table>\n";

			$GLOBALS['egw']->template->set_var('anz_imported',($_POST['debug'] ?
				lang('%1 records read (not yet imported, you may go %2back%3 and uncheck Test Import)',
				$anz,'','') :
				lang('%1 records imported',$anz)). '&nbsp;'.
				(!$_POST['debug'] && $fields ? $GLOBALS['egw']->html->submit_button('next','Import next set') . '&nbsp;':'').
				$GLOBALS['egw']->html->submit_button('continue','Back') . '&nbsp;'.
				$GLOBALS['egw']->html->submit_button('cancel','Cancel'));
			$GLOBALS['egw']->template->set_var('log',$log);
			$GLOBALS['egw']->template->parse('importedhandle','imported');
			break;
	}

	$GLOBALS['egw']->template->set_var('hiddenvars',str_replace('{','&#x7B;',$hiddenvars));
	$GLOBALS['egw']->template->pfp('out','import',True);
	$GLOBALS['egw']->common->phpgw_footer();
?>
