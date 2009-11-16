<?php
/**
 * InfoLog - CSV import
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info']['flags'] = array(
	'currentapp' => 'infolog',
	'noheader'   => True,
	'enable_contacts_class' => True,
);
include('../header.inc.php');

if (!isset($GLOBALS['egw_info']['user']['apps']['admin']) ||
    !$GLOBALS['egw_info']['user']['apps']['admin'])		// no admin
{
	$GLOBALS['egw']->redirect_link('/home.php');
}
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
	$GLOBALS['egw']->redirect_link('/infolog/index.php');
}
if (isset($_POST['charset']))
{
	// we have to set the local, to fix eg. utf-8 imports, as fgetcsv requires it!
	common::setlocale(LC_CTYPE,$_POST['charset']);
}
$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog - Import CSV-File');
$GLOBALS['egw']->common->egw_header();

$infolog_bo = createobject('infolog.infolog_bo');

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
$GLOBALS['egw']->template->set_var("action_url",$GLOBALS['egw']->link("/infolog/csv_import.php"));

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

function project_id($num_or_title)
{
	static $boprojects;

	if (!$num_or_title) return false;

	if (!is_object($boprojects))
	{
		$boprojects =& CreateObject('projectmanager.boprojectmanager');
	}
	if (($projects = $boprojects->search(array('pm_number' => $num_or_title))) ||
		($projects = $boprojects->search(array('pm_title'  => $num_or_title))))
	{
		return $projects[0]['pm_id'];
	}
	return false;
}

$cat2id = array( );

function cat_id($cats)
{
	if (!$cats)
	{
		return '';
	}

	// no multiple cat's in InfoLog atm.
	foreach(array($cats) /*split('[,;]',$cats)*/ as $cat)
	{
		if (isset($cat2id[$cat]))
		{
			$ids[$cat] = $cat2id[$cat];	// cat is in cache
		}
		else
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = createobject('phpgwapi.categories');
			}
			if (is_numeric($cat) && $GLOBALS['egw']->categories->id2name($cat) != '--')
			{
				$cat2id[$cat] = $ids[$cat] = $cat;
			}
			elseif (($id = $GLOBALS['egw']->categories->name2id( addslashes($cat) )))
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
	$id_str = implode( ',',$ids );

	if (count($ids) > 1)		// multiple cats need to be in ','
	{
		$id_str = ",$id_str,";
	}
	return  $id_str;
}

if ($_POST['next']) $_POST['action'] = 'next';
switch ($_POST['action'])
{
case '':	// Start, ask Filename
	$GLOBALS['egw']->template->set_var('lang_csvfile',lang('CSV-Filename'));
	$GLOBALS['egw']->template->set_var('lang_fieldsep',lang('Fieldseparator'));
	$GLOBALS['egw']->template->set_var('lang_charset',lang('Charset of file'));
	$GLOBALS['egw']->template->set_var('select_charset',
		html::select('charset','',
		$GLOBALS['egw']->translation->get_installed_charsets()+
		array('utf-8' => 'utf-8 (Unicode)'),True));
	$GLOBALS['egw']->template->set_var('fieldsep',$_POST['fieldsep'] ? $_POST['fieldsep'] : ';');
	$GLOBALS['egw']->template->set_var('submit',lang('Import'));
	$GLOBALS['egw']->template->set_var('enctype','ENCTYPE="multipart/form-data"');

	$GLOBALS['egw']->template->parse('rows','filename');
	break;

case 'continue':
case 'download':
	$GLOBALS['egw']->preferences->read_repository();
	$defaults = $GLOBALS['egw_info']['user']['preferences']['infolog']['cvs_import'];
	if (!is_array($defaults))
	{
		$defaults = array();
	}
	$GLOBALS['egw']->template->set_var('lang_csv_fieldname',lang('CSV-Fieldname'));
	$GLOBALS['egw']->template->set_var('lang_info_fieldname',lang('InfoLog-Fieldname'));
	$GLOBALS['egw']->template->set_var('lang_translation',lang("Translation").' <a href="#help">'.lang('help').'</a>');
	$GLOBALS['egw']->template->set_var('submit',
		html::submit_button('convert','Import') . '&nbsp;'.
		html::submit_button('cancel','Cancel'));
	$GLOBALS['egw']->template->set_var('lang_debug',lang('Test Import (show importable records <u>only</u> in browser)'));
	$GLOBALS['egw']->template->parse('rows','fheader');

	$info_names = array(
		'type'        => 'Type: char(10) task,phone,note',
		'from'        => 'From: text(255) free text if no Addressbook-entry assigned',
		'addr'        => 'Addr: text(255) phone-nr/email-address',
		'subject'     => 'Subject: text(255)',
		'des'         => 'Description: text long free text',
		'location'    => 'Location: text(255)',
		'responsible' => 'Responsible: int(11) user-id or user-name',
		'owner'       => 'Owner: int(11) user-id/-name of owner, if empty current user',
		'access'      => 'Access: public,private',
		'cat'         => 'Category: int(11) category-id or -name (new ones got created)',
		'startdate'   => 'Start Date: DateTime: Timestamp or eg. YYYY-MM-DD hh:mm',
		'enddate'     => 'End Date: DateTime',
		'datecompleted'=> 'Date completed: DateTime',
		'datemodified'=> 'Date Last Modified: DateTime, if empty = Date Created',
		'modifier'    => 'Modifier: int(11) user-id, if empty current user',
		'priority'    => 'Priority: 3=urgent, 2=high, 1=normal, 0=low',
		'planned_time'=> 'planned Time: int(11) time used in min',
		'used_time'   => 'used Time: int(11) time used in min',
		'status'      => 'Status: char(10) offer,not-started,ongoing,call,will-call,done,billed,cancelled',
		'percent'     => 'Percent completed: int',
//		'confirm'     => 'Confirmation: char(10) not,accept,finish,both when to confirm',
		'project_id'  => 'Link to Projectmanager, use Project-ID, Title or @project_id(id_or_title)',
		'addr_id'     => 'Link to Addressbook, use nlast,nfirst[,org] or contact_id from addressbook',
		'link_1'      => '1. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_2'      => '2. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_3'      => '3. link: appname:appid the entry should be linked to, eg.: addressbook:123',
	);
	// add custom fields
	if ($infolog_bo->customfields)
	{
		foreach($infolog_bo->customfields as $name => $field)
		{
			if ($field['type'] == 'label' || !count($field['values']) && $field['rows'] <= 1 && $field['len'] <= 0) continue;

			$info_names['#'.$name] = lang('custom fields').': '.$field['label'];
		}
	}

	// the next line is used in the help-text too
	$mktime_lotus = "${PSep}0?([0-9]+)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*).*$ASep@mktime(${VPre}4,${VPre}5,${VPre}6,${VPre}2,${VPre}3,${VPre}1)";

	/* this are settings to import from Lotus Organizer
	$defaults += array(	'Land'			=> "addr$PSep.*[(]+([0-9]+)[)]+$ASep+${VPre}1 (${CPre}Ortsvorwahl$CPos) ${CPre}Telefon$CPos$PSep${CPre}Telefon$CPos",
								'Notiz'			=> 'des',
								'Privat'			=> "access${PSep}1${ASep}private${PSep}public",
								'Startdatum'	=>	'startdate'.$mktime_lotus,
								'Enddatum'		=>	'enddate'.$mktime_lotus,
								'Erledigt'		=>	"status${PSep}1${ASep}done${PSep}call",
								'Nachname'		=> "addr_id${PSep}@addr_id(${CPre}Nachname$CPos,${CPre}Vorname$CPos,${CPre}Firma$CPos)",
								'Firma'			=>	"from${PSep}.+$ASep${CPre}Firma$CPos: ${CPre}Nachname$CPos, ${CPre}Vorname$CPos".
																	"${PSep}${CPre}Nachname$CPos, ${CPre}Vorname$CPos",
								'no CSV 1'		=>	"type${PSep}phone",
								'no CSV 2'		=>	"subject${PSep}@substr(${CPre}Notiz$CPos,0,60).' ...'" );
	*/
	$info_name_options = "<option value=\"\">none\n";
	foreach($info_names as $field => $name)
	{
		$info_name_options .= "<option value=\"$field\">".$GLOBALS['egw']->strip_html($name)."\n";
	}
	$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
	$csv_fields = $GLOBALS['egw']->translation->convert($csv_fields,$_POST['charset']);
	$csv_fields[] = 'no CSV 1'; 						// eg. for static assignments
	$csv_fields[] = 'no CSV 2';
	$csv_fields[] = 'no CSV 3';
	foreach($csv_fields as $csv_idx => $csv_field)
	{
		$GLOBALS['egw']->template->set_var('csv_field',$csv_field);
		$GLOBALS['egw']->template->set_var('csv_idx',$csv_idx);

		if (($def = $defaults[$csv_field]))
		{
			list( $info,$trans ) = explode($PSep,$def,2);
			$GLOBALS['egw']->template->set_var('trans',$trans);
			$GLOBALS['egw']->template->set_var('info_fields',str_replace('="'.$info.'">','="'.$info.'" selected>',$info_name_options));
		}
		else
		{
			$GLOBALS['egw']->template->set_var('trans','');
			$GLOBALS['egw']->template->set_var('info_fields',$info_name_options);
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
	$help_on_trans = 	"<a name=\"help\"></a><b>How to use Translation's</b><p>".
		"Translations enable you to change / adapt the content of each CSV field for your needs. <br>".
		"General syntax is: <b>pattern1 ${ASep} replacement1 ${PSep} ... ${PSep} patternN ${ASep} replacementN</b><br>".
		"If the pattern-part of a pair is ommited it will match everything ('^.*$'), which is only ".
		"usefull for the last pair, as they are worked from left to right.<p>".
		"First example: <b>1${ASep}private${PSep}public</b><br>".
		"This will translate a '1' in the CVS field to 'privat' and everything else to 'public'.<p>".
		"Patterns as well as the replacement can be regular expressions (the replacement is done via ereg_replace). ".
		"If, after all replacements, the value starts with an '@' the whole value is eval()'ed, so you ".
		"may use all php, phpgw plus your own functions. This is quiet powerfull, but <u>circumvents all ACL</u>.<p>".
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
		"Their is two important user-function for the InfoLog:<br>".
		"<b>@addr_id(${CPre}NFamily$CPos,${CPre}NGiven$CPos,${CPre}Company$CPos)</b> ".
		"searches the addressbook for an address and returns the id if it founds an exact match of at least ".
		"<i>NFamily</i> AND (<i>NGiven</i> OR <i>Company</i>). This is necessary to link your imported InfoLog-entrys ".
		"with the addressbook.<br>".
		"<b>@cat_id(Cat-name)</b> returns a numerical cat_id. If a category isn't found, it ".
		"will be automaticaly added.<p>".
		"I hope that helped to understand the features, if not <a href='mailto:egroupware-users@lists.sf.net'>ask</a>.";

	$GLOBALS['egw']->template->set_var('help_on_trans',lang($help_on_trans));	// I don't think anyone will translate this
	break;

case 'next':
	$_POST['info_fields'] = unserialize(stripslashes($_POST['info_fields']));
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
		'info_fields' => $_POST['info_fields'],
		'trans'   => $_POST['trans']
	));
	@set_time_limit(0);
	$fp=fopen($csvfile,'r');
	$csv_fields = fgetcsv($fp,8000,$_POST['fieldsep']);
	$csv_fields = $GLOBALS['egw']->translation->convert($csv_fields,$_POST['charset']);
	$csv_fields[] = 'no CSV 1'; 						// eg. for static assignments
	$csv_fields[] = 'no CSV 2';
	$csv_fields[] = 'no CSV 3';

	$info_fields = array_diff($_POST['info_fields'],array( '' ));	// throw away empty / not assigned entrys

	$defaults = array();
	foreach($info_fields as $csv_idx => $info)
	{	// convert $trans[$csv_idx] into array of pattern => value
		$defaults[$csv_fields[$csv_idx]] = $info;
		if ($_POST['trans'][$csv_idx])
		{
			$defaults[$csv_fields[$csv_idx]] .= $PSep.addslashes($_POST['trans'][$csv_idx]);
		}
	}

	$GLOBALS['egw']->preferences->read_repository();
	$GLOBALS['egw']->preferences->add('infolog','cvs_import',$defaults);
	$GLOBALS['egw']->preferences->save_repository(True);

	$log = '<table border="1" style="border: 1px dotted black; border-collapse: collapse;">'."\n\t<tr><td>#</td>\n";

	foreach($info_fields as $csv_idx => $info)
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
		} /*else
			unset( $trans[$csv_idx] );*/

		$log .= "\t\t<td><b>$info</b></td>\n";
	}
	if (!in_array('access',$info_fields))	// autocreate public access if not set by user
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

		$values = $orig = array();
		foreach($info_fields as $csv_idx => $info)
		{
			//echo "<p>$csv: $info".($trans[$csv] ? ': '.$trans[$csv] : '')."</p>";
			$val = $fields[$csv_idx];
			if (isset($trans[$csv_idx]))
			{
				$trans_csv = $trans[$csv_idx];
				while (list($pattern,$replace) = each($trans_csv))
				{
					if (ereg((string) $pattern,$val))
					{
						// echo "<p>csv_idx='$csv_idx',info='$info',trans_csv=".print_r($trans_csv).",preg_replace('/$pattern/','$replace','$val') = ";
						$val = preg_replace('/'.(string) $pattern.'/',str_replace($VPre,'\\',$replace),(string) $val);
						// echo "'$val'</p>";

						$reg = $CPreReg.'([a-zA-Z_0-9]+)'.$CPosReg;
						while (ereg($reg,$val,$vars))
						{	// expand all CSV fields
							$val = str_replace($CPre.$vars[1].$CPos,$val[0] == '@' ? "'".addslashes($fields[array_search($vars[1],$csv_fields)])."'" : $fields[array_search($vars[1],$csv_fields)],$val);
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
							break;
					}
				}
			}
			$values[$info] = $orig[$info] = $val;
		}
		$empty = !count($values);

		// convert the category name to an id
		if ($values['cat'] && !is_numeric($values['cat']))
		{
			$values['cat'] = cat_id($values['cat']);
		}

		// convert dates to timestamps
		foreach(array('startdate','enddate','datemodified','datecompleted') as $date)
		{
			if (isset($values[$date]) && !is_numeric($date))
			{
				if (ereg('(.*)\.[0-9]+',$values[$date],$parts)) $values[$date] = $parts[1];
				$values[$date] = strtotime($values[$date]);
			}
		}
		if (!isset($values['datemodified'])) $values['datemodified'] = $values['startdate'];

		// convert user-names to user-id's
		foreach(array('owner','modifier') as $user)
		{
			if (isset($values[$user]) && !is_numeric($values[$user]))
			{
				if (preg_match('/\[([^\]]+)\]/',$values[$user],$matches)) $values[$user] = $matches[1];
				$values[$user] = $GLOBALS['egw']->accounts->name2id($values[$user],'account_lid',$user=='owner'?null:'u');
			}
		}
		if (isset($values['responsible']))
		{
			$responsible = $values['responsible'];
			$values['responsible'] = array();
			foreach(preg_split('/[,;]/',$responsible) as $user)
			{
				if (preg_match('/\[([^\]]+)\]/',$user,$matches)) $user = $matches[1];
				if ($user && !is_numeric($user)) $user = $GLOBALS['egw']->accounts->name2id($user);
				if ($user) $values['responsible'][] = $user;
			}
		}
		if (!in_array('access',$info_fields))
		{
			$values['access'] = 'public';	// public access if not set by user
			$log .= "\t\t<td>".$values['access']."</td>\n";
		}
		if(!$_POST['debug'] && !$empty)	// dont import empty contacts
		{
			// create new names with info_ prefix
			$to_write = array();
			foreach($values as $name => $value)
			{
				$to_write[substr($name,0,5) != 'info_' && $name{0} != '#' ? 'info_'.$name : $name] = $value;
			}
			if ($values['addr_id'] && !is_numeric($values['addr_id']))
			{
				list($lastname,$firstname,$org_name) = explode(',',$values['addr_id']);
				$values['addr_id'] = addr_id($lastname,$firstname,$org_name);
			}
			if ($values['project_id'] && !is_numeric($values['project_id']))
			{
				$values['project_id'] = project_id($values['project_id']);
			}
			if (($id = $infolog_bo->write($to_write,True,False)))
			{
				$info_link_id = false;
				foreach(array(
					'projectmanager:'.$values['project_id'],
					'addressbook:'.$values['addr_id'],
					$values['link_1'],$values['link_2'],$values['link_3'],
				) as $value)
				{
					list($app,$app_id) = explode(':',$value);
					if ($app && $app_id)
					{
						//echo "<p>linking infolog:$id with $app:$app_id</p>\n";
						$link_id = egw_link::link('infolog',$id,$app,$app_id);
						if ($link_id && !$info_link_id)
						{
							$to_write = array(
								'info_id'      => $id,
								'info_link_id' => $link_id,
							);
							$infolog_bo->write($to_write);
							$info_link_id = true;
						}
					}
				}
			}
		}
		// display read and interpreted results, so the user can check it
		foreach($info_fields as $name)
		{
			$log .= "\t\t<td>".($orig[$name] != $values[$name] ? htmlspecialchars($orig[$name]).' --> ' : '').
				htmlspecialchars($values[$name])."</td>\n";
		}
	}
	$log .= "\t</tr>\n</table>\n";

	$GLOBALS['egw']->template->set_var('anz_imported',($_POST['debug'] ?
		lang('%1 records read (not yet imported, you may go %2back%3 and uncheck Test Import)',
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
