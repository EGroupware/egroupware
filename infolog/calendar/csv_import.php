<?php
  /**************************************************************************\
  * phpGroupWare - Calendar: CSV - Import                                    *
  * http://www.phpgroupware.org                                              *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = Array(
		'currentapp'					=>	'calendar',
		'noappheader'					=>	True,
		'noappfooter'					=>	True
	);
	include('../header.inc.php');

	$GLOBALS['phpgw']->contacts = createobject('phpgwapi.contacts');

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL); // $t->unknows = 'keep'; $t->debug = 1;
	$t->set_file(array('import' => 'csv_import.tpl'));
	$t->set_block('import','filename','filenamehandle');
	$t->set_block('import','fheader','fheaderhandle');
	$t->set_block('import','fields','fieldshandle');
	$t->set_block('import','ffooter','ffooterhandle');
	$t->set_block('import','imported','importedhandle');
	
	// $t->set_var("navbar_bg",$phpgw_info["theme"]["navbar_bg"]);
	// $t->set_var("navbar_text",$phpgw_info["theme"]["navbar_next"]);
	
	if ($action == 'download' && (!$fieldsep || !$csvfile || !($fp=fopen($csvfile,'r'))))
	{
		$action = '';
	}		
	$t->set_var('action_url',$GLOBALS['phpgw']->link('/calendar/csv_import.php'));
	$t->set_var('lang_cal_action',lang('Import CSV-File into Calendar'));

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
				$ids[$cat] = $cat2id[$cat];								// cat is in cache
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
					$GLOBALS['phpgw']->categories->add(array('name' => $cat,'descr' => $cat));
					$cat2id[$cat] = $ids[$cat] = $GLOBALS['phpgw']->categories->name2id( addslashes($cat) );
				}
			}
		}
		$id_str = implode( ',',$ids );

		if (count($ids) > 1)
		{
			$id_str = ','.$id_str.',';
		}
		return $id_str;
	}

	switch ($action)
	{
		case '':	// Start, ask Filename
			$t->set_var('lang_csvfile',lang('CSV-Filename'));
			$t->set_var('lang_fieldsep',lang('Fieldseparator'));
			$t->set_var('fieldsep',$fieldsep ? $fieldsep : ',');
			$t->set_var('submit',lang('Download'));
			$t->set_var('csvfile',$csvfile);
			$t->set_var('enctype','ENCTYPE="multipart/form-data"');
			$hiddenvars .= '<input type="hidden" name="action" value="download">'."\n";

			$t->parse('filenamehandle','filename');
			break;

		case 'download':
			$GLOBALS['phpgw']->preferences->read_repository();
			$defaults = $GLOBALS['phpgw_info']['user']['preferences']['calendar']['csv_import'];
			if (!is_array($defaults))
			{
				$defaults = array();
			}
			$t->set_var('lang_csv_fieldname',lang('CSV-Fieldname'));
			$t->set_var('lang_cal_fieldname',lang('Calendar-Fieldname'));
			$t->set_var('lang_translation',lang("Translation").' <a href="#help">'.lang('help').'</a>');
			$t->set_var('submit',lang('Import'));
			$t->set_var('lang_debug',lang('Test Import (show importable records <u>only</u> in browser)'));
			$t->parse('fheaderhandle','fheader');
			$hiddenvars .= '<input type="hidden" name="action" value="import">'."\n"
				. '<input type="hidden" name="fieldsep" value="'.$fieldsep."\">\n";

			$mktime_lotus = "${PSep}0?([0-9]+)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*).*$ASep@mktime(${VPre}4,${VPre}5,${VPre}6,${VPre}2,${VPre}3,${VPre}1)";

			$defaults = array (
				'VERTRAULICH'	=> "is_public${PSep}0${ASep}1${PSep}0",
				'VONDATUM'		=> 'datetime'.$mktime_lotus,
				'BISDATUM'		=> 'edatetime'.$mktime_lotus,
				'SCHLUSSELID'	=> "category$PSep@cat_id(${CPre}SCHLUSSELID$CPos)",
				'NOTIZEN'		=> 'title',
			);

			$cal_names = array(
				'owner'		=> 'Owner of the calendar-entry',
				'category' 	=> 'Category: @cat_id(CatName)',
				'groups' 	=> 'Groups (dont know what this field is for)',
				'datetime' 	=> 'Startdate: @mktime( )',
				'mdatetime' => 'Modification date',
				'edatetime' => 'Enddate of the entry',
				'priority'  => 'Priority: 1=Low, 2=Normal, 3=High',
				'cal_type'	=> 'cal_type: single_event=E else M',
				'is_public'	=> 'Access: 1=public, 0=private',
				'title'		=> 'Title varchar(80)',
				'description' => 'Description text'
			);

			$cal_name_options = "<option value=\"\">none\n";
			reset($cal_names);
			while (list($field,$name) = each($cal_names))
			{
				$cal_name_options .= "<option value=\"$field\">".$GLOBALS['phpgw']->strip_html($name)."\n";
			}
			$csv_fields = fgetcsv($fp,8000,$fieldsep);
			$csv_fields[] = 'no CSV 1'; 					// eg. for static assignments
			$csv_fields[] = 'no CSV 2';
			$csv_fields[] = 'no CSV 3';
			while (list($csv_idx,$csv_field) = each($csv_fields))
			{
				$t->set_var('csv_field',$csv_field);
				$t->set_var('csv_idx',$csv_idx);
				if ($def = $defaults[$csv_field])
				{
					list( $cal,$trans ) = explode($PSep,$def,2);
					$t->set_var('trans',$trans);
					$t->set_var('cal_fields',str_replace('="'.$cal.'">','="'.$cal.'" selected>',$cal_name_options));
				}
				else
				{
					$t->set_var('trans','');
					$t->set_var('cal_fields',$cal_name_options);
				}
				$t->parse('fieldshandle','fields',True);
			}
			$t->set_var('lang_start',lang('Startrecord'));
			$t->set_var('start',$start);
			$t->set_var('lang_max',lang('Number of records to read (<=200)'));
			$t->set_var('max',200);
			$t->parse('ffooterhandle','ffooter');
			fclose($fp);
			$old = $csvfile; $csvfile = $phpgw_info['server']['temp_dir'].'/cal_import_'.basename($csvfile);
			rename($old,$csvfile);
			$hiddenvars .= '<input type="hidden" name="csvfile" value="'.$csvfile.'">';
			$help_on_trans = 	"<a name='help'><b>How to use Translation's</b><p>".
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

			$t->set_var('help_on_trans',lang($help_on_trans));	// I don't think anyone will translate this
			break;

		case 'import':
			// echo "<p>cal_fields=array(".dump_array($cal_fields).")</p>\n";
			$fp=fopen($csvfile,"r");
			$csv_fields = fgetcsv($fp,8000,$fieldsep);
			$csv_fields[] = 'no CSV 1';
			$csv_fields[] = 'no CSV 2';
			$csv_fields[] = 'no CSV 3';

			$cal_fields = array_diff($cal_fields,array( '' ));	// throw away empty / not assigned entrys

			$defaults = array();
			while (list($csv_idx,$addr) = each($cal_fields))
			{	// convert $trans[$csv_idx] into array of pattern => value
				$defaults[$csv_fields[$csv_idx]] = $cal;
				if ($trans[$csv_idx])
				{
					$defaults[$csv_fields[$csv_idx]] .= $PSep.$trans[$csv_idx];
				}
			}
			$GLOBALS['phpgw']->preferences->read_repository();
			$GLOBALS['phpgw']->preferences->add('calendar','csv_import',$defaults);
			$GLOBALS['phpgw']->preferences->save_repository(True);

			$log = "<table border=1>\n\t<tr><td>#</td>\n";

			reset($cal_fields);
			while (list($csv_idx,$cal) = each($cal_fields))
			{	// convert $trans[$csv_idx] into array of pattern => value
				// if (!$debug) echo "<p>$csv_idx: ".$csv_fields[$csv_idx].": $cal".($trans[$csv_idx] ? ': '.$trans[$csv_idx] : '')."</p>";
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
				$log .= "\t\t<td><b>$cal</b></td>\n";
			}

			$user = $phpgw_info['user']['account_id'];
			$calendar = $GLOBALS['phpgw']->calendar;
		   $calendar->open('INBOX',$user,'');

			if ($start < 1) $start = 1;
			for ($i = 1; $i < $start && fgetcsv($fp,8000,$fieldsep); ++$i); 	// overread lines before our start-record

			for ($anz = 0; $anz < $max && ($fields = fgetcsv($fp,8000,$fieldsep)); ++$anz)
			{
				$log .= "\t</tr><tr><td>".($start+$anz)."</td>\n";

				reset($cal_fields); $values = array();
				while (list($csv_idx,$cal) = each($cal_fields))
				{
					// echo "<p>$csv_idx: $cal".($trans[$csv] ? ': '.$trans[$csv] : '')."</p>";
					$val = $fields[$csv_idx];
					if (isset($trans[$csv_idx]))
					{
						$trans_csv = $trans[$csv_idx];
						while (list($pattern,$replace) = each($trans_csv))
						{
							if (ereg((string) $pattern,$val))
							{
								// echo "<p>csv_idx='$csv_idx',info='$cal',trans_csv=".dump_array($trans_csv).",ereg_replace('$pattern','$replace','$val') = ";
								$val = ereg_replace((string) $pattern,str_replace($VPre,'\\',$replace),(string) $val);
								// echo "'$val'</p>";

								$quote = $val[0] == '@' ? "'" : '';

								$reg = $CPreReg.'([a-zA-Z_0-9]+)'.$CPosReg;
								while (ereg($reg,$val,$vars))
								{	// expand all CSV fields
									$val = str_replace($CPre.$vars[1].$CPos,$val[0]=='@'?"'".addslashes($fields[index($vars[1],$csv_fields)])."'":
										$fields[index($vars[1],$csv_fields)],$val);
								}
								if ($val[0] == '@')
								{
									// removing the $ to close security hole of showing vars, which contain eg. passwords
									$val = 'return '.substr(str_replace('$','',$val),1).';';
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
					$values[$cal] = $val;

					$log .= "\t\t<td>$val</td>\n";
				}
				// if (!isset($values['datecreated'])) $values['datecreated'] = $values['startdate'];

				if (!$debug)
				{
					// echo "<p>adding: ".dump_array($values)."</p>\n";
					$calendar->event_init();		// set some reasonable defaults
					$calendar->set_recur_none();
					$calendar->event->owner = $user;
					$calendar->event->priority = 2;
					$calendar->event->title = $calendar->event->description = '';
					$calendar->event->participants = array ( $user => 'A' );

					while (list($cal,$val) = each($values)) {
						switch ($cal) {
						case 'is_public':
							$calendar->event->public = $val;
							break;
						case 'datetime': case 'edatetime':
							$date = $calendar->datetime->localdates($val);
							$func = $cal == 'datetime' ? 'set_start' : 'set_end';
							$calendar->$func($date['year'],$date['month'],$date['day'],
										   $date['hour'],$date['minute'],$date['second']);
							break;
						default:
							$calendar->event->$cal = $val;
							break;
						}
					}
					$calendar->save_event( $calendar->event );
				}
			}
			$log .= "\t</tr>\n</table>\n";

			$t->set_var('anz_imported',$debug ? lang( '%1 records read (not yet imported, you may go back and uncheck Test Import)',
				$anz,'<a href="javascript:history.back()">','</a>' ) :
				lang( '%1 records imported',$anz ));
			$t->set_var('log',$log);
			$t->parse('importedhandle','imported');
			break;
	}

	$t->set_var('hiddenvars',$hiddenvars);
	$t->pfp('out','import',True);
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
