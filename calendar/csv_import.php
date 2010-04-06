<?php
/**
 * Calendar - CSV import
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package calendar
 * @copyright (c) 2003-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'calendar',
		'noheader'   => True
	),
);
include('../header.inc.php');

$is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']) && $GLOBALS['egw_info']['user']['apps']['admin'];

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
	$GLOBALS['egw']->redirect_link('/calendar/index.php');
}
if (isset($_POST['charset']))
{
	// we have to set the local, to fix eg. utf-8 imports, as fgetcsv requires it!
	common::setlocale(LC_CTYPE,$_POST['charset']);
}
$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].' - '.lang('Import CSV-File');
$cal = new calendar_boupdate(true);
$GLOBALS['egw']->common->egw_header();

$GLOBALS['egw']->template->set_file(array('import_t' => 'csv_import.tpl'));
$GLOBALS['egw']->template->set_block('import_t','filename','filenamehandle');
$GLOBALS['egw']->template->set_block('import_t','fheader','fheaderhandle');
$GLOBALS['egw']->template->set_block('import_t','fields','fieldshandle');
$GLOBALS['egw']->template->set_block('import_t','ffooter','ffooterhandle');
$GLOBALS['egw']->template->set_block('import_t','imported','importedhandle');
$GLOBALS['egw']->template->set_block('import_t','import','importhandle');

if(($_POST['action'] == 'download' || $_POST['action'] == 'continue') && (!$_POST['fieldsep'] || !$csvfile || !($fp=fopen($csvfile,'rb'))))
{
	$_POST['action'] = '';
}
$GLOBALS['egw']->template->set_var("action_url",$GLOBALS['egw']->link("/calendar/csv_import.php"));

$PSep = '||'; // Pattern-Separator, separats the pattern-replacement-pairs in trans
$ASep = '|>'; // Assignment-Separator, separats pattern and replacesment
$VPre = '|#'; // Value-Prefix, is expanded to \ for ereg_replace
$CPre = '|['; $CPreReg = '\|\['; // |{csv-fieldname} is expanded to the value of the csv-field
$CPos = ']';  $CPosReg = '\]';	// if used together with @ (replacement is eval-ed) value gets autom. quoted

function addr_id( $n_family,$n_given=null,$org_name=null )
{		// find in Addressbook, at least n_family AND (n_given OR org_name) have to match
	static $contacts;
	if (!is_object($contacts))
	{
		$contacts =& CreateObject('phpgwapi.contacts');
	}
	if (!is_null($org_name))	// org_name given?
	{
		$addrs = $contacts->read( 0,0,array('id'),'',"n_family=$n_family,n_given=$n_given,org_name=$org_name" );
		if (!count($addrs))
		{
			$addrs = $contacts->read( 0,0,array('id'),'',"n_family=$n_family,org_name=$org_name",'','n_family,org_name');
		}
	}
	if (!is_null($n_given) && (is_null($org_name) || !count($addrs)))	// first name given and no result so far
	{
		$addrs = $contacts->read( 0,0,array('id'),'',"n_family=$n_family,n_given=$n_given",'','n_family,n_given' );
	}
	if (is_null($n_given) && is_null($org_name))	// just one name given, check against fn (= full name)
	{
		$addrs = $contacts->read( 0,0,array('id'),'',"n_fn=$n_family",'','n_fn' );
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

	foreach(preg_split('/[,;]/',$cats) as $cat)
	{
		if (isset($cat2id[$cat]))
		{
			$ids[$cat] = $cat2id[$cat];	// cat is in cache
		}
		else
		{
			if (is_numeric($cat) && $GLOBALS['egw']->categories->id2name($cat) != '--')
			{
				$cat2id[$cat] = $ids[$cat] = $cat;
			}
			elseif ($id = $GLOBALS['egw']->categories->name2id( addslashes($cat) ))
			{	// cat exists
				$cat2id[$cat] = $ids[$cat] = $id;
			}
			else
			{	// create new cat
				$GLOBALS['egw']->categories->add( array('name' => $cat,'descr' => $cat ));
				$cat2id[$cat] = $ids[$cat] = $GLOBALS['egw']->categories->name2id( addslashes($cat) );
			}
		}
	}
	return implode( ',',$ids );
}

if ($_POST['next']) $_POST['action'] = 'next';
switch ($_POST['action'])
{
case '':	// Start, ask Filename
	$GLOBALS['egw']->template->set_var('lang_csvfile',lang('CSV-Filename'));
	$GLOBALS['egw']->template->set_var('lang_fieldsep',lang('Fieldseparator'));
	$GLOBALS['egw']->template->set_var('lang_charset',lang('Charset of file'));
	$GLOBALS['egw']->template->set_var('lang_help',lang('Please note: You can configure the field assignments AFTER you uploaded the file.'));
	$GLOBALS['egw']->template->set_var('select_charset',
		html::select('charset','',translation::get_installed_charsets(),True));
	$GLOBALS['egw']->template->set_var('fieldsep',$_POST['fieldsep'] ? $_POST['fieldsep'] : ';');
	$GLOBALS['egw']->template->set_var('submit',lang('Import'));
	$GLOBALS['egw']->template->set_var('enctype','ENCTYPE="multipart/form-data"');

	$GLOBALS['egw']->template->parse('rows','filename');
	break;

case 'continue':
case 'download':
	$GLOBALS['egw']->preferences->read_repository();
	$defaults = $GLOBALS['egw_info']['user']['preferences']['calendar']['cvs_import'];
	if (!is_array($defaults))
	{
		$defaults = array();
	}
	$GLOBALS['egw']->template->set_var('lang_csv_fieldname',lang('CSV-Fieldname'));
	$GLOBALS['egw']->template->set_var('lang_info_fieldname',lang('calendar-Fieldname'));
	$GLOBALS['egw']->template->set_var('lang_translation',lang("Translation").' <a href="#help">'.lang('help').'</a>');
	$GLOBALS['egw']->template->set_var('submit',
		html::submit_button('convert','Import') . '&nbsp;'.
		html::submit_button('cancel','Cancel'));
	$GLOBALS['egw']->template->set_var('lang_debug',lang('Test Import (show importable records <u>only</u> in browser)'));
	$GLOBALS['egw']->template->parse('rows','fheader');

	$cal_names = array(
		'title'		=> 'Title varchar(80)',
		'description' => 'Description text',
		'location'	=> 'Location varchar(255)',
		'start'	=> 'Start Date: Timestamp or eg. YYYY-MM-DD hh:mm',
		'end'	=> 'End Date: Timestamp or eg. YYYY-MM-DD hh:mm',
		'participants' => 'Participants: comma separated user-id\'s or -names',
		'category'	=> 'Categories: id\'s or names, comma separated (new ones got created)',
		'priority'  => 'Priority: 1=Low, 2=Normal, 3=High',
		'public'	=> 'Access: 1=public, 0=private',
		'owner'		=> 'Owner: int(11) user-id/-name',
		'modified'	=> 'Modification date',
		'modifier'	=> 'Modification user',
		'non_blocking' => '0=Event blocks time, 1=Event creates no conflicts',
		'uid'       => 'Unique Id, allows multiple import to update identical entries',
//		'recur_type'=> 'Type of recuring event',
		'addr_id'     => 'Link to Addressbook, use nlast,nfirst[,org] or contact_id from addressbook',
		'link_1'      => '1. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_2'      => '2. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_3'      => '3. link: appname:appid the entry should be linked to, eg.: addressbook:123',
	);
	$custom_fields = config::get_customfields('calendar');
	//echo "custom-fields=<pre>".print_r($custom_fields,True)."</pre>";
	foreach ($custom_fields as $name => $data)
	{
		$cal_names['#'.$name] = $data['label'].': Custom field ('.$data['type'].')';
	}

	// the next line is used in the help-text too
	$mktime_lotus = "${PSep}0?([0-9]+)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*).*$ASep@mktime(${VPre}4,${VPre}5,${VPre}6,${VPre}2,${VPre}3,${VPre}1)";

	$cal_name_options = "<option value=\"\">none\n";
	foreach($cal_names as $field => $name)
	{
		$cal_name_options .= "<option value=\"$field\">".$GLOBALS['egw']->strip_html($name)."\n";
	}
	$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
	$csv_fields = translation::convert($csv_fields,$_POST['charset']);
	$csv_fields[] = 'no CSV 1';					// eg. for static assignments
	$csv_fields[] = 'no CSV 2';
	$csv_fields[] = 'no CSV 3';
	foreach($csv_fields as $csv_idx => $csv_field)
	{
		$GLOBALS['egw']->template->set_var('csv_field',$csv_field);
		$GLOBALS['egw']->template->set_var('csv_idx',$csv_idx);
		if ($def = $defaults[$csv_field])
		{
			list( $info,$trans ) = explode($PSep,$def,2);
			$GLOBALS['egw']->template->set_var('trans',$trans);
			$GLOBALS['egw']->template->set_var('cal_fields',str_replace('="'.$info.'">','="'.$info.'" selected>',$cal_name_options));
		}
		else
		{
			$GLOBALS['egw']->template->set_var('trans','');
			$GLOBALS['egw']->template->set_var('cal_fields',$cal_name_options);
		}
		$GLOBALS['egw']->template->parse('rows','fields',True);
	}
	$GLOBALS['egw']->template->set_var('lang_start',lang('Startrecord'));
	$GLOBALS['egw']->template->set_var('start',get_var('start',array('POST'),1));
	$msg = ($safe_mode = ini_get('safe_mode') == 'On') ? lang('to many might exceed your execution-time-limit'):
		lang('empty for all');
	$GLOBALS['egw']->template->set_var('lang_max',lang('Number of records to read (%1)',$msg));
	$GLOBALS['egw']->template->set_var('max',get_var('max',array('POST'),$safe_mode ? 200 : ''));
	$GLOBALS['egw']->template->set_var('debug',get_var('debug',array('POST'),True)?' checked':'');
	$GLOBALS['egw']->template->parse('rows','ffooter',True);
	fclose($fp);

	$hiddenvars = html::input_hidden(array(
		'action'  => 'import',
		'fieldsep'=> $_POST['fieldsep'],
		'charset' => $_POST['charset']
	));
	$help_on_trans = "<a name=\"help\"></a><b>How to use Translation's</b><p>".
		"Translations enable you to change / adapt the content of each CSV field for your needs. <br>".
		"General syntax is: <b>pattern1 ${ASep} replacement1 ${PSep} ... ${PSep} patternN ${ASep} replacementN</b><br>".
		"If the pattern-part of a pair is ommited it will match everything ('^.*$'), which is only ".
		"usefull for the last pair, as they are worked from left to right.<p>".
		"First example: <b>1${ASep}private${PSep}public</b><br>".
		"This will translate a '1' in the CVS field to 'privat' and everything else to 'public'.<p>".
		"Patterns as well as the replacement can be regular expressions (the replacement is done via ereg_replace). ".
		"If, after all replacements, the value starts with an '@' AND you have admin rights the whole value is eval()'ed, so you ".
		"may use php, eGroupWare or your own functions. This is quiet powerfull, but <u>circumvents all ACL</u>.<p>".
		"Example using regular expressions and '@'-eval(): <br><b>$mktime_lotus</b><br>".
		"It will read a date of the form '2001-05-20 08:00:00.00000000000000000' (and many more, see the regular expr.). ".
		"The&nbsp;[&nbsp;.:-]-separated fields are read and assigned in different order to @mktime(). Please note to use ".
		"${VPre} insted of a backslash (I couldn't get backslash through all the involved templates and forms.) ".
		"plus the field-number of the pattern.<p>".
		"In addintion to the fields assign by the pattern of the reg.exp. you can use all other CSV-fields, with the ".
		"syntax <b>${CPre}CVS-FIELDNAME$CPos</b>. Here is an example: <br>".
		"<b>.+$ASep${CPre}Company$CPos: ${CPre}NFamily$CPos, ${CPre}NGiven$CPos$PSep${CPre}NFamily$CPos, ${CPre}NGiven$CPos</b><br>".
		"It is used on the CVS-field 'Company' and constructs a something like <i>Company: FamilyName, GivenName</i> or ".
		"<i>FamilyName, GivenName</i> if 'Company' is empty.<p>".
		"You can use the 'No CVS #'-fields to assign cvs-values to more than on field, the following example uses the ".
		"cvs-field 'Note' (which gots already assingned to the description) and construct a short subject: ".
		"<b>@substr(${CPre}Note$CPos,0,60).' ...'</b><p>".
		"Their is two important user-function for the calendar:<br>".
		"<b>@addr_id(${CPre}NFamily$CPos,${CPre}NGiven$CPos,${CPre}Company$CPos)</b> ".
		"searches the addressbook for an address and returns the id if it founds an exact match of at least ".
		"<i>NFamily</i> AND (<i>NGiven</i> OR <i>Company</i>). This is necessary to link your imported calendar-entrys ".
		"with the addressbook.<br>".
		"<b>@cat_id(Cat1,...,CatN)</b> returns a (','-separated) list with the cat_id's. If a category isn't found, it ".
		"will be automaticaly added. This function is automaticaly called if the category is not numerical!<p>".
		"I hope that helped to understand the features, if not <a href='mailto:egroupware-users@lists.sf.net'>ask</a>.";

	$GLOBALS['egw']->template->set_var('help_on_trans',lang($help_on_trans));	// I don't think anyone will translate this
	break;

case 'next':
	$_POST['cal_fields'] = unserialize(stripslashes($_POST['cal_fields']));
	$_POST['trans']       = unserialize(stripslashes($_POST['trans']));
	// fall-through
case 'import':
	$hiddenvars = html::input_hidden(array(
		'action'  => 'continue',
		'fieldsep'=> $_POST['fieldsep'],
		'charset' => $_POST['charset'],
		'start'   => $_POST['start']+(!$_POST['debug'] ? $_POST['max'] : 0),
		'max'     => $_POST['max'],
		'debug'   => $_POST['debug'],
		'cal_fields' => $_POST['cal_fields'],
		'trans'   => $_POST['trans']
	));
	@set_time_limit(0);
	$fp=fopen($csvfile,'r');
	$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
	$csv_fields = translation::convert($csv_fields,$_POST['charset']);
	$csv_fields[] = 'no CSV 1';						// eg. for static assignments
	$csv_fields[] = 'no CSV 2';
	$csv_fields[] = 'no CSV 3';

	$cal_fields = array_diff($_POST['cal_fields'],array( '' ));	// throw away empty / not assigned entrys

	$defaults = array();
	foreach($cal_fields as $csv_idx => $info)
	{	// convert $trans[$csv_idx] into array of pattern => value
		$defaults[$csv_fields[$csv_idx]] = $info;
		if ($_POST['trans'][$csv_idx])
		{
			$defaults[$csv_fields[$csv_idx]] .= $PSep.addslashes($_POST['trans'][$csv_idx]);
		}
	}

	$GLOBALS['egw']->preferences->read_repository();
	$GLOBALS['egw']->preferences->add('calendar','cvs_import',$defaults);
	$GLOBALS['egw']->preferences->save_repository(True);

	$log = '<table border="1" style="border: 1px dotted black; border-collapse: collapse;">'."\n\t<tr><td>#</td>\n";

	foreach($cal_fields as $csv_idx => $info)
	{	// convert $trans[$csv_idx] into array of pattern => value
		// if (!$debug) echo "<p>$csv_idx: ".$csv_fields[$csv_idx].": $info".($trans[$csv_idx] ? ': '.$trans[$csv_idx] : '')."</p>";
		$pat_reps = explode($PSep,stripslashes($_POST['trans'][$csv_idx]));
		$replaces = ''; $values = '';
		if ($pat_reps[0] != '')
		{
			foreach($pat_reps as $k => $pat_rep)
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
		$log .= "\t\t<td><b>$info</b></td>\n";
	}
	if (!in_array('public',$cal_fields))	// autocreate public access if not set by user
	{
		$log .= "\t\t<td><b>isPublic</b></td>\n";
		$cal_fields[] = 'public';
	}
/*	if (!in_array('recur_type',$cal_fields))	// autocreate single event if not set by user
	{
		$log .= "\t\t<td><b>recureing</b></td>\n";
	}*/
	$log .= "\t\t<td><b>Imported</b></td>\n\t</tr>\n";

	$start = $_POST['start'] < 1 ? 1 : $_POST['start'];

	// ignore empty lines, is_null($fields[0]) is returned on empty lines !!!
	for($i = 1; $i < $start; ++$i)	// overread lines before our start-record
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
		$fields = translation::convert($fields,$_POST['charset']);

		$log .= "\t<tr>\n\t\t<td>".($start+$anz)."</td>\n";

		$values = $orig = array();
		foreach($cal_fields as $csv_idx => $info)
		{
			//echo "<p>$csv: $info".($trans[$csv] ? ': '.$trans[$csv] : '')."</p>";
			$val = $fields[$csv_idx];
			if (isset($trans[$csv_idx]) && is_array($trans[$csv_idx]))
			{
				foreach($trans[$csv_idx] as $pattern => $replace)
				{
					if (ereg((string) $pattern,$val))
					{
						//echo "<p>csv_idx='$csv_idx',info='$info',trans_csv=".print_r($trans_csv).",preg_replace('/$pattern/','$replace','$val') = ";
						$val = preg_replace('/'.(string) $pattern.'/',str_replace($VPre,'\\',$replace),(string) $val);
						//echo "'$val'";

						$reg = $CPreReg.'([a-zA-Z_0-9 ]+)'.$CPosReg;
						while (ereg($reg,$val,$vars))
						{	// expand all CSV fields
							$val = str_replace($CPre.$vars[1].$CPos,$val[0] == '@' ? "'".addslashes($fields[array_search($vars[1],$csv_fields)])."'" : $fields[array_search($vars[1],$csv_fields)],$val);
						}
						//echo "='$val'</p>";
						if ($val[0] == '@' && is_admin)
						{
							// removing the $ to close security hole of showing vars, which contain eg. passwords
							$val = 'return '.substr(str_replace('$','',$val),1).';';
							// echo "<p>eval('$val')=";
							$val = eval($val);
							// echo "'$val'</p>";
						}
						if ($pattern[0] != '@' || $val)
							break;
					}
				}
			}
			$values[$info] = $orig[$info] = $val;
		}
		if (!isset($values['public']))
		{
			$values['public'] = 1;	// public access if not set by user
		}
		if (!isset($values['recur_type']))
		{
			$values['recur_type'] = MCAL_RECUR_NONE;	// single event if not set by user
		}

		if(count($values))	// dont import empty contacts
		{
			//echo "values=<pre>".print_r($values,True)."</pre>\n";
			foreach(array('owner','modifier') as $name)
			{
				if (preg_match('/\[([^\]]+)\]/',$values[$name],$matches)) $values[$name] = $matches[1];
				if (!is_numeric($values[$name])) $values[$name] = $GLOBALS['egw']->accounts->name2id($values[$name],'account_lid','u');
			}
			if (!$values['owner'] || !$GLOBALS['egw']->accounts->exists($values['owner']))
			{
				$values['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			}
			if ($values['priority'] < 1 || $values['priority'] > 3)
			{
				$values['priority'] = 2;
			}
			// convert the category name to an id
			if ($values['category'] && !is_numeric($values['category']))
			{
				$values['category'] = cat_id($values['category']);
			}
			if (empty($values['title']))
			{
				$values['title'] = '('.lang('none').')';
			}

			// convert dates to timestamps
			foreach(array('start','end','modified') as $date)
			{
				if (isset($values[$date]) && !is_numeric($date))
				{
					// convert german DD.MM.YYYY format into ISO YYYY-MM-DD format
					$values[$date] = preg_replace('/([0-9]{1,2}).([0-9]{1,2}).([0-9]{4})/','\3-\2-\1',$values[$date]);
					// remove fractures of seconds if present at the end of the string
					if (ereg('(.*)\.[0-9]+',$values[$date],$parts)) $values[$date] = $parts[1];
					$values[$date] = strtotime($values[$date]);
				}
			}
			// convert participants-names to user-id's
			$parts = $values['participants'] ? preg_split('/[,;]/',$values['participants']) : array();
			$values['participants'] = array();
			foreach($parts as $part_status)
			{
				list($part,$status) = explode('=',$part_status);
				$valid_status = array('U'=>'U','u'=>'U','A'=>'A','a'=>'A','R'=>'R','r'=>'R','T'=>'T','t'=>'T');
				$status = isset($valid_status[$status]) ? $valid_status[$status] : 'U';
				if (!is_numeric($part))
				{
					if (preg_match('/\[([^\]]+)\]/',$part,$matches)) $part = $matches[1];
					$part = $GLOBALS['egw']->accounts->name2id($part);
				}
				if ($part && is_numeric($part))
				{
					$values['participants'][$part] = $status;
				}
			}
			if (!count($values['participants']))		// no valid participants so far --> add the importing user/owner
			{
				$values['participants'][$values['owner']] = 'A';
			}
			if ($values['addr_id'] && !is_numeric($values['addr_id']))
			{
				list($lastname,$firstname,$org_name) = explode(',',$values['addr_id']);
				$values['addr_id'] = addr_id($lastname,$firstname,$org_name);
			}
			if ($values['uid'])
			{
				if (is_numeric($values['uid']))		// to not conflict with our own id's
				{
					$values['uid'] = 'cal-csv-import-'.$values['uid'].'-'.$GLOBALS['egw_info']['server']['install_id'];
				}
				if (($event = $cal->read($values['uid'],null,$is_admin)))	// no ACL check for admins
				{
					//echo "updating existing event"; _debug_array($event);
					$values['id'] = $event['id'];
				}
			}
			$action = $values['id'] ? 'updating' : 'adding';
			//echo $action.'<pre>'.print_r($values,True)."</pre>\n";

			// Keep links, $cal->update will remove them
			$links = array(
				'addr_id'	=>	'addressbook:' . $values['addr_id'],
				'link_1'	=>	$values['link_1'],
				'link_2'	=>	$values['link_2'],
				'link_3'	=>	$values['link_3'],
			);
			if (!$_POST['debug'] &&
				($cal_id = $cal->update($values,true,!$values['modified'],$is_admin)))	// ignoring conflicts and ACL (for admins) on import
			{
				foreach($links as $value) {
					list($app,$app_id) = explode(':',$value);
					if ($app && $app_id)
					{
						//echo "<p>linking calendar:$cal_id with $app:$app_id</p>\n";
						egw_link::link('calendar',$cal_id,$app,$app_id);
					}
				}
			}
			// display read and interpreted results, so the user can check it
			foreach($cal_fields as $name)
			{
				$log .= "\t\t<td>".($orig[$name] != $values[$name] ? htmlspecialchars($orig[$name]).' --> ' : '').
					htmlspecialchars($values[$name])."</td>\n";
			}
			if(!$_POST['debug'] && count($values))	// dont import empty contacts
			{
				$log .= "\t\t".'<td align="center">'.($cal_id ? $action." cal_id=$cal_id" : 'Error '.$action)."</td>\n\t</tr>\n";
			}
			else
			{
				$log .= "\t\t".'<td align="center">only test</td>'."\n\t</tr>\n";
			}
		}
	}
	$log .= "</table>\n";

	$GLOBALS['egw']->template->set_var('anz_imported',($_POST['debug'] ?
		lang('%1 records read (not yet imported, you may go back and uncheck Test Import)',
		$anz,'','') :
		lang('%1 records imported',$anz)). '&nbsp;'.
		(!$_POST['debug'] && $fields ? html::submit_button('next','Import next set') . '&nbsp;':'').
		html::submit_button('continue','Back') . '&nbsp;'.
		html::submit_button('cancel','Cancel'));
	$GLOBALS['egw']->template->set_var('log',$log);
	$GLOBALS['egw']->template->parse('rows','imported');
	break;
}
$GLOBALS['egw']->template->set_var('hiddenvars',str_replace('{','&#x7B;',$hiddenvars));
$GLOBALS['egw']->template->pfp('phpgw_body','import');
$GLOBALS['egw']->common->egw_footer();
