<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook: CSV - Import                                 *
  * http://www.phpgroupware.org                                              *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'addressbook';
	$GLOBALS['phpgw_info']['flags']['enable_contacts_class'] = True;
	include('../header.inc.php');

	$GLOBALS['phpgw']->contacts = createobject('phpgwapi.contacts');

	$GLOBALS['phpgw']->template->set_file(array('import' => 'csv_import.tpl'));
	$GLOBALS['phpgw']->template->set_block('import','filename','filenamehandle');
	$GLOBALS['phpgw']->template->set_block('import','fheader','fheaderhandle');
	$GLOBALS['phpgw']->template->set_block('import','fields','fieldshandle');
	$GLOBALS['phpgw']->template->set_block('import','ffooter','ffooterhandle');
	$GLOBALS['phpgw']->template->set_block('import','imported','importedhandle');

	if ($action == 'download' && (!$fieldsep || !$csvfile || !($fp=fopen($csvfile,'r'))))
	{
		$action = '';
	}		
	$GLOBALS['phpgw']->template->set_var('action_url',$GLOBALS['phpgw']->link('/addressbook/csv_import.php'));
	$GLOBALS['phpgw']->template->set_var('lang_addr_action',lang('Import CSV-File into Addressbook'));

	$PSep = '||'; // Pattern-Separator, separats the pattern-replacement-pairs in trans
	$ASep = '|>'; // Assignment-Separator, separats pattern and replacesment
	$VPre = '|#'; // Value-Prefix, is expanded to \ for ereg_replace
	$CPre = '|['; $CPreReg = '\|\['; // |{csv-fieldname} is expanded to the value of the csv-field
	$CPos = ']';  $CPosReg = '\]';	// if used together with @ (replacement is eval-ed) value gets autom. quoted

	function dump_array( $arr )
	{
		while (list($key,$val) = each($arr))
		{
			$ret .= ($ret ? ',' : '(') . "'$key' => '$val'\n";
		}
		return $ret.')';
	}

	function index( $value,$arr )
	{
		while (list ($key,$val) = each($arr))
		{
			if ($value == $val)
			{
				return $key;
			}
		}
		return False;
	}

	// find in Addressbook, at least n_family AND (n_given OR org_name) have to match
	function addr_id( $n_family,$n_given,$org_name )
	{
		$addrs = $GLOBALS['phpgw']->contacts->read( 0,0,array('id'),'',"n_family=$n_family,n_given=$n_given,org_name=$org_name" );
		if (!count($addrs))
		{
			$addrs = $GLOBALS['phpgw']->contacts->read( 0,0,array('id'),'',"n_family=$n_family,n_given=$n_given" );
		}
		if (!count($addrs))
		{
			$addrs = $GLOBALS['phpgw']->contacts->read( 0,0,array('id'),'',"n_family=$n_family,org_name=$org_name" );
		}

		if (count($addrs))
		{
			return $addrs[0]['id'];
		}

		return False;	
	}

	$cat2id = array( );

	function cat_id($cats)
	{
		if (!$cats)
		{
			return '';
		}

		$cats = split('[,;]',$cats);

		while (list($k,$cat) = each($cats))
		{
			if (isset($cat2id[$cat]))
			{
				$ids[$cat] = $cat2id[$cat];	// cat is in cache
			}
			else
			{
				if (!is_object($GLOBALS['phpgw']->categories))
				{
					$GLOBALS['phpgw']->categories = createobject('phpgwapi.categories');
				}			
				if ($id = $GLOBALS['phpgw']->categories->name2id( addslashes($cat) ))
				{	// cat exists
					$cat2id[$cat] = $ids[$cat] = $id;
				}
				else
				{	// create new cat
					$GLOBALS['phpgw']->categories->add( array('name' => $cat,'descr' => $cat ));
					$cat2id[$cat] = $ids[$cat] = $GLOBALS['phpgw']->categories->name2id( addslashes($cat) );
				}
			}
		}
		$id_str = implode( ',',$ids );

		if (count($ids) > 1)		// multiple cats need to be in ','
		{
			$id_str = ",$id_str,";
		}
		return  $id_str;
	}

	switch ($action)
	{
		case '':	// Start, ask Filename
			$GLOBALS['phpgw']->template->set_var('lang_csvfile',lang('CSV-Filename'));
			$GLOBALS['phpgw']->template->set_var('lang_fieldsep',lang('Fieldseparator'));
			$GLOBALS['phpgw']->template->set_var('fieldsep',$fieldsep ? $fieldsep : ',');
			$GLOBALS['phpgw']->template->set_var('submit',lang('Download'));
			$GLOBALS['phpgw']->template->set_var('csvfile',$csvfile);
			$GLOBALS['phpgw']->template->set_var('enctype','ENCTYPE="multipart/form-data"');
			$hiddenvars .= '<input type="hidden" name="action" value="download">'."\n";

			$GLOBALS['phpgw']->template->parse('filenamehandle','filename');
			break;

		case 'download':
			$pref_file = '/tmp/csv_import_addrbook.php';
			if (is_readable($pref_file) && ($prefs = fopen($pref_file,'r')))
			{
				eval("fread\(\$prefs,8000\);");
				// echo "<p>defaults = array".dump_array($defaults)."</p>\n";
			}		
			$GLOBALS['phpgw']->template->set_var('lang_csv_fieldname',lang('CSV-Fieldname'));
			$GLOBALS['phpgw']->template->set_var('lang_addr_fieldname',lang('Addressbook-Fieldname'));
			$GLOBALS['phpgw']->template->set_var('lang_translation',lang("Translation").' <a href="#help">'.lang('help').'</a>');
			$GLOBALS['phpgw']->template->set_var('submit',lang('Import'));
			$GLOBALS['phpgw']->template->set_var('lang_debug',lang('Test Import (show importable records <u>only</u> in browser)'));
			$GLOBALS['phpgw']->template->parse('fheaderhandle','fheader');
			$hiddenvars .= '<input type="hidden" name="action" value="import">'."\n"
				. '<input type="hidden" name="fieldsep" value="'.$fieldsep."\">\n"
				. '<input type="hidden" name="pref_file" value="'.$pref_file."\">\n";

			$addr_names = $GLOBALS['phpgw']->contacts->stock_contact_fields + array(
				'cat_id' => 'Categories: @cat_id(Cat1,Cat2)',
				'access' => 'Access: public,private',
				'owner'	=>	'Owner: defaults to user'
			);

			while (list($field,$name) = each($addr_names))
			{
				if ($dn = display_name($field))
				{
					$addr_names[$field] = $dn;
				}
			}
			$addr_name_options = "<option value=\"\">none\n";
			reset($addr_names);
			while (list($field,$name) = each($addr_names))
			{
				$addr_name_options .= "<option value=\"$field\">".$GLOBALS['phpgw']->strip_html($name)."\n";
			}
			$csv_fields = fgetcsv($fp,8000,$fieldsep);
			$csv_fields[] = 'no CSV 1'; 						// eg. for static assignments
			$csv_fields[] = 'no CSV 2'; 
			$csv_fields[] = 'no CSV 3'; 
			while (list($csv_idx,$csv_field) = each($csv_fields))
			{
				$GLOBALS['phpgw']->template->set_var('csv_field',$csv_field);
				$GLOBALS['phpgw']->template->set_var('csv_idx',$csv_idx);
				if ($def = $defaults[$csv_field])
				{
					list( $addr,$trans ) = explode($PSep,$def,2);
					$GLOBALS['phpgw']->template->set_var('trans',$trans);
					$GLOBALS['phpgw']->template->set_var('addr_fields',str_replace('="'.$addr.'">','="'.$addr.'" selected>',$addr_name_options));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('trans','');
					$GLOBALS['phpgw']->template->set_var('addr_fields',$addr_name_options);
				}			
				$GLOBALS['phpgw']->template->parse('fieldshandle','fields',True); 
			}		
			$GLOBALS['phpgw']->template->set_var('lang_start',lang('Startrecord'));
			$GLOBALS['phpgw']->template->set_var('start',$start);
			$GLOBALS['phpgw']->template->set_var('lang_max',lang('Number of records to read (<=200)'));
			$GLOBALS['phpgw']->template->set_var('max',200);
			$GLOBALS['phpgw']->template->parse('ffooterhandle','ffooter'); 
			fclose($fp);
			$old = $csvfile; $csvfile = $GLOBALS['phpgw_info']['server']['temp_dir'].'/addrbook_import_'.basename($csvfile);
			rename($old,$csvfile); 
			$hiddenvars .= '<input type="hidden" name="csvfile" value="'.$csvfile.'">';
			$help_on_trans = "<a name='help'><b>How to use Translation's</b><p>".
							"Translations enable you to change / adapt the content of each CSV field for your needs. <br>".
							"General syntax is: <b>pattern1 ${ASep} replacement1 ${PSep} ... ${PSep} patternN ${ASep} replacementN</b><br>".
							"If the pattern-part of a pair is ommited it will match everything ('^.*$'), which is only ".
							"usefull for the last pair, as they are worked from left to right.<p>".
							"First example: <b>1${ASep}private${PSep}public</b><br>".
							"This will translate a '1' in the CSV field to 'privat' and everything else to 'public'.<p>".
							"Patterns as well as the replacement can be regular expressions (the replacement is done via ereg_replace). ".
							"If, after all replacements, the value starts with an '@' the whole value is eval()'ed, so you ".
							"may use all php, phpgw plus your own functions. This is quiet powerfull, but <u>circumvents all ACL</u>.<p>".
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
							"Their is one important user-function for the Info Log:<br>".
							"<b>@addr_id(${CPre}NFamily$CPos,${CPre}NGiven$CPos,${CPre}Company$CPos)</b> ".
							"searches the addressbook for an address and returns the id if it founds an exact match of at least ".
							"<i>NFamily</i> AND (<i>NGiven</i> OR <i>Company</i>). This is necessary to link your imported InfoLog-entrys ".
							"with the addressbook.<br>".
							"<b>@cat_id(Cat1,...,CatN)</b> returns a (','-separated) list with the cat_id's. If a category isn't found, it ".
							"will be automaticaly added.<p>".
							"I hope that helped to understand the features, if not <a href='mailto:RalfBecker@outdoor-training.de'>ask</a>.";

			$GLOBALS['phpgw']->template->set_var('help_on_trans',lang($help_on_trans));	// I don't think anyone will translate this
			break;
		case 'import':
			$fp=fopen($csvfile,"r");
			$csv_fields = fgetcsv($fp,8000,$fieldsep);

			$addr_fields = array_diff($addr_fields,array( '' ));	// throw away empty / not assigned entrys

			if ($pref_file)
			{
				// echo "writing pref_file ...<p>";
				if (file_exists($pref_file))
				{
					rename($pref_file,$pref_file.'.old');
				}
				$pref = fopen($pref_file,'w');
				while (list($csv_idx,$addr) = each($addr_fields))
				{	// convert $trans[$csv_idx] into array of pattern => value
					$defaults[$csv_fields[$csv_idx]] = $addr;
					if ($trans[$csv_idx])
					{
						$defaults[$csv_fields[$csv_idx]] .= $PSep.$trans[$csv_idx];
					}
				}
				fwrite($pref,'$defaults = array'.dump_array( $defaults ).';');
				fclose($pref);
			}
			$log = "<table border=1>\n\t<tr><td>#</td>\n";

			reset($addr_fields);
			while (list($csv_idx,$addr) = each($addr_fields))
			{	// convert $trans[$csv_idx] into array of pattern => value
				// if (!$debug) echo "<p>$csv_idx: ".$csv_fields[$csv_idx].": $addr".($trans[$csv_idx] ? ': '.$trans[$csv_idx] : '')."</p>";
				$pat_reps = explode($PSep,stripslashes($trans[$csv_idx]));
				$replaces = ''; $values = '';
				if ($pat_reps[0] != '')
				{
					while (list($k,$pat_rep) = each($pat_reps))
					{
						list($pattern,$replace) = explode($ASep,$pat_rep,2);
						if ($replace == '')
						{
							$replace = $pattern; $pattern = '^.*$';
						}
						$values[$pattern] = $replace;	// replace two with only one, added by the form
						$replaces .= ($replaces != '' ? $PSep : '') . $pattern . $ASep . $replace;
					}
					$trans[$csv_idx] = $values;
				}
				else
				{
					unset( $trans[$csv_idx] );
				}
				$log .= "\t\t<td><b>$addr</b></td>\n";		
			}
			if ($start < 1) $start = 1;
			for ($i = 1; $i < $start && fgetcsv($fp,8000,$fieldsep); ++$i); 	// overread lines before our start-record

			for ($anz = 0; $anz < $max && ($fields = fgetcsv($fp,8000,$fieldsep)); ++$anz)
			{
				$log .= "\t</tr><tr><td>".($start+$anz)."</td>\n";

				reset($addr_fields); $values = array();
				while (list($csv_idx,$addr) = each($addr_fields))
				{
					//echo "<p>$csv: $addr".($trans[$csv] ? ': '.$trans[$csv] : '')."</p>";
					$val = $fields[$csv_idx];
					if (isset($trans[$csv_idx]))
					{
						$trans_csv = $trans[$csv_idx];
						while (list($pattern,$replace) = each($trans_csv))
						{
							if (ereg((string) $pattern,$val))
							{
								// echo "<p>csv_idx='$csv_idx',info='$addr',trans_csv=".dump_array($trans_csv).",ereg_replace('$pattern','$replace','$val') = ";
								$val = ereg_replace((string) $pattern,str_replace($VPre,'\\',$replace),(string) $val);
								// echo "'$val'</p>";

								$reg = $CPreReg.'([a-zA-Z_0-9]+)'.$CPosReg;
								while (ereg($reg,$val,$vars))
								{	// expand all CSV fields
									$val = str_replace($CPre.$vars[1].$CPos,$val[0] == '@' ? "'".addslashes($fields[index($vars[1],$csv_fields)])."'" : $fields[index($vars[1],$csv_fields)],$val);
								}
								if ($val[0] == '@')
								{
									$val = 'return '.substr($val,1).';';
									// echo "<p>eval('$val')=";
									$val = eval($val);
									// echo "'$val'</p>";
								}
								if ($pattern[0] != '@' || $val)
								{
									break;
								}
							}
						}
					}
					$values[$addr] = $val;

					$log .= "\t\t<td>$val</td>\n";
				}
				// if (!isset($values['datecreated'])) $values['datecreated'] = $values['startdate'];

				if (!$debug)
				{
					$GLOBALS['phpgw']->contacts->add( $values['owner'] ? $values['owner'] : $GLOBALS['phpgw_info']['user']['account_id'],
						$values,$values['access'],$values['cat_id']);
					// echo "<p>adding: ".dump_array($values)."</p>\n";
				}
			}
			$log .= "\t</tr>\n</table>\n";

			$GLOBALS['phpgw']->template->set_var('anz_imported',$debug ? lang( '%1 records read (not yet imported, you may go back and uncheck Test Import)',
				$anz,'<a href="javascript:history.back()">','</a>' ) :
				lang( '%1 records imported',$anz ));
			$GLOBALS['phpgw']->template->set_var('log',$log);
			$GLOBALS['phpgw']->template->parse('importedhandle','imported');
			break;
	}

	$GLOBALS['phpgw']->template->set_var('hiddenvars',$hiddenvars);
	$GLOBALS['phpgw']->template->pfp('out','import',True);
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
