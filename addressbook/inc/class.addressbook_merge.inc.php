<?php
/**
 * Addressbook - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Addressbook - document merge object
 */
class addressbook_merge	// extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('show_replacements' => true);
	/**
	 * Instance of the addressbook_bo class
	 *
	 * @var addressbook_bo
	 */
	var $contacts;

	/**
	 * Constructor
	 *
	 * @return addressbook_merge
	 */
	function __construct()
	{
		$this->contacts = new addressbook_bo();
	}

	/**
	 * Return if merge-print is implemented for given mime-type (and/or extension)
	 *
	 * @param string $mimetype eg. text/plain
	 * @param string $extension only checked for applications/msword and .rtf
	 */
	static function is_implemented($mimetype,$extension=null)
	{
		switch ($mimetype)
		{
			case 'application/msword':
				if (strtolower($extension) != '.rtf') break;
			case 'application/rtf':
				return true;	// rtf files
			case 'application/vnd.oasis.opendocument.text':
				if (!check_load_extension('zip')) break;
				return true;	// open office write xml files
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				if (!check_load_extension('zip')) break;
				return true;	// ms word xml format
			default:
				if (substr($mimetype,0,5) == 'text/')
				{
					return true;	// text files
				}
				break;
		}
		return false;

		// As browsers not always return correct mime types, one could use a negative list instead
		//return !($mimetype == egw_vfs::DIR_MIME_TYPE || substr($mimetype,0,6) == 'image/');
	}

	/**
	 * Return replacements for a contact
	 *
	 * @param int/string/array $contact contact-array or id
	 * @param string $prefix='' prefix like eg. 'user'
	 * @return array
	 */
	function contact_replacements($contact,$prefix='')
	{
		if (!is_array($contact))
		{
			$contact = $this->contacts->read($contact);
		}
		if (!is_array($contact)) return array();

		$replacements = array();
		foreach(array_keys($this->contacts->contact_fields) as $name)
		{
			$value = $contact[$name];
			switch($name)
			{
				case 'created': case 'modified':
					$value = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].' '.
						($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i a':'H:i'),$value);
					break;
				case 'bday':
					if ($value)
					{
						list($y,$m,$d) = explode('-',$value);
						$value = $GLOBALS['egw']->common->dateformatorder($y,$m,$d,true);
					}
					break;
				case 'owner': case 'creator': case 'modifier':
					$value = $GLOBALS['egw']->common->grab_owner_name($value);
					break;
				case 'cat_id':
					if ($value)
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$use = $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree' ? 'path' : 'name';
						$cats = array();
						foreach(is_array($value) ? $value : explode(',',$value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id,$use);
						}
						$value = implode(', ',$cats);
					}
					break;
				case 'jpegphoto':	// returning a link might make more sense then the binary photo
					if ($contact['photo'])
					{
						$value = ($GLOBALS['egw_info']['server']['webserver_url']{0} == '/' ?
							($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'] : '').
							$GLOBALS['egw']->link('/index.php',$contact['photo']);
					}
					break;
				case 'tel_prefer':
					if ($value && $contact[$value])
					{
						$value = $contact[$value];
					}
					break;
				case 'account_id':
					if ($value)
					{
						$replacements['$$'.($prefix ? $prefix.'/':'').'account_lid$$'] = $GLOBALS['egw']->accounts->id2name($value);
					}
					break;
			}
			if ($name != 'photo') $replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		// set custom fields
		foreach($this->contacts->customfields as $name => $field)
		{
			$name = '#'.$name;
			$value = (string)$contact[$name];
			switch($field['type'])
			{
				case 'select-account':
					if ($value) $value = common::grab_owner_name($value);
					break;

				case 'select':
					if (count($field['values']) == 1 && isset($field['values']['@']))
					{
						$field['values'] = customfields_widget::_get_options_from_file($field['values']['@']);
					}
					$values = array();
					foreach($field['rows'] > 1 ? explode(',',$value) : (array) $value as $value)
					{
						$values[] = $field['values'][$value];
					}
					$value = implode(', ',$values);
					break;

				case 'date':
				case 'date-time':
					if ($value)
					{
						$format = $field['len'] ? $field['len'] : ($field['type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
						$date = array_combine(preg_split('/[\\/. :-]/',$format),preg_split('/[\\/. :-]/',$value));
						$value = common::dateformatorder($date['Y'],$date['m'],$date['d'],true);
						if (isset($date['H'])) $value .= ' '.common::formattime($date['H'],$date['i']);
					}
					break;
			}
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar (next events) of a contact
	 *
	 * @param int $contact contact-id
	 * @param boolean $last_event_too=false also include information about the last event
	 * @return array
	 */
	function calendar_replacements($id,$last_event_too=false)
	{
		require_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_boupdate.inc.php');
		$calendar = new calendar_boupdate();

		// next events
		$events = $calendar->search(array(
			'start' => $calendar->now_su,
			'users' => 'c'.$id,
			'offset' => 0,
			'num_rows' => 20,
			'order' => 'cal_start',
		));
		if ($events)
		{
			array_unshift($events,false); unset($events[0]);	// renumber the array to start with key 1, instead of 0
		}
		else
		{
			$events = array();
		}
		if ($last_event_too=true)
		{
			$last = $calendar->search(array(
				'end' => $calendar->now_su,
				'users' => 'c'.$id,
				'offset' => 0,
				'num_rows' => 1,
				'order' => 'cal_start DESC',
			));
			if ($last) $events['-1'] = array_shift($last);	// returned events are indexed by cal_id!
		}
		$replacements = array();
		foreach($events as $n => $event)
		{
			foreach($calendar->event2array($event) as $name => $data)
			{
				if (substr($name,-4) == 'date') $name = substr($name,0,-4);
				$replacements['$$calendar/'.$n.'/'.$name.'$$'] = is_array($data['data']) ? implode(', ',$data['data']) : $data['data'];
			}
			foreach(array('start','end') as $what)
			{
				foreach(array(
					'date' => $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],
					'day'  => 'l',
					'time' => $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i',
				) as $name => $format)
				{
					$value = date($format,$event[$what]);
					if ($format == 'l') $value = lang($value);
					$replacements['$$calendar/'.$n.'/'.$what.$name.'$$'] = $value;
				}
			}
			$duration = ($event['end'] - $event['start'])/60;
			$replacements['$$calendar/'.$n.'/duration$$'] = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

			++$n;
		}
		return $replacements;
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $document path/url of document
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @return string/boolean merged document or false on error
	 */
	function &merge($document,$ids,&$err,$mimetype)
	{
		if (!($content = file_get_contents($document)))
		{
			$err = lang("Document '%1' does not exist or is not readable for you!",$document);
			return false;
		}
		list($contentstart,$contentrepeat,$contentend) = preg_split('/\$\$pagerepeat\$\$/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
		list($Labelstart,$Labelrepeat,$Labeltend) = preg_split('/\$\$label\$\$/',$contentrepeat,-1, PREG_SPLIT_NO_EMPTY);  //get the Lable content
		preg_match_all('/\$\$labelplacement\$\$/',$contentrepeat,$countlables, PREG_SPLIT_NO_EMPTY);
		$countlables = count($countlables[0])+1;
		preg_replace('/\$\$labelplacement\$\$/','',$Labelrepeat,1);
		if ($countlables > 1) $lableprint = true;
		if (count($ids) > 1 && !$contentrepeat)
		{
			$err = lang('for more then one contact in a document use the tag pagerepeat!');
			return false;
		}
		foreach ($ids as $id)
		{
			if ($contentrepeat)   $content = $contentrepeat;   //content to repeat
			// generate replacements
			if ($lableprint) $content = $Labelrepeat;
			if (!($replacements = $this->contact_replacements($id)))
			{
				$err = lang('Contact not found!');
				return false;
			}
			if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
			{
				$replacements += $this->contact_replacements($user,'user');
			}
			if (strpos($content,'$$calendar/') !== null)
			{
				$replacements += $this->calendar_replacements($id,strpos($content,'$$calendar/-1/') !== null);
			}
			$replacements['$$date$$'] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],time()+$this->contacts->tz_offset_s);

			if ($this->contacts->prefs['csv_charset'])	// if we have an export-charset defined, use it here to
			{
				$replacements = $GLOBALS['egw']->translation->convert($replacements,$GLOBALS['egw']->translation->charset(),$this->contacts->prefs['csv_charset']);
			}
			$content = str_replace(array_keys($replacements),array_values($replacements),$content);

			if (strpos($content,'$$calendar/') !== null)	// remove not existing event-replacements
			{
				$content = preg_replace('/\$\$calendar\/[0-9]+\/[a-z_]+\$\$/','',$content);
			}
			$this->replacements = $replacements;
			if (strpos($content,'$$IF'))
			{	//Example use to use: $$IF n_prefix~Herr~Sehr geehrter~Sehr geehrte$$
				$content = preg_replace_callback('/\$\$IF ([0-9a-z_-]+)~(.*)~(.*)~(.*)\$\$/imU',Array($this,'replace_callback'),$content);
			}
			if ($contentrepeat) $contentrep[$id] = $content;
		}
		if ($Labelrepeat)
		{
			$countpage=0;
			$count=0;
			$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;

			foreach ($contentrep as $Label)
			{
				$count=$count+1;
				if ($count % $countlables == 0)
				{
					$countpage=$countpage+1;
					$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;
				}
				$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/',$Label,$contentrepeatpages[$countpage],1);
			}
			$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/','',$contentrepeatpages[$countpage],-1);  //clean empty fields

			switch($mimetype)
			{
				case 'application/msword':
					if (strtolower(substr($document,-4)) != '.rtf') break;	// no binary word documents
				case 'application/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrepeatpages).$contentend;
				case 'application/vnd.oasis.opendocument.text':
					// todo OO writer files
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
					// todo ms word xml files
					break;
			}
			$err = lang('%1 not implemented for %2!','$$labelplacement$$',$mimetype);
			return false;
		}

		if ($contentrepeat)
		{
			switch($mimetype)
			{
				case 'application/msword':
					if (strtolower(substr($document,-4)) != '.rtf') break;	// no binary word documents
				case 'application/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrep).$contentend;
				case 'application/vnd.oasis.opendocument.text':
					// todo OO writer files
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
					// todo ms word xml files
					break;
			}
			$err = lang('%1 not implemented for %2!','$$pagerepeat$$',$mimetype);
			return false;
		}
		return $content;
	}

	/**
	 * Callback for preg_replace to process $$IF
	 *
	 * @param array $param
	 * @return string
	 */
	function replace_callback($param)
	{
		if (array_key_exists('$$'.$param[4].'$$',$this->replacements)) $param[4] = $this->replacements['$$'.$param[4].'$$'];
		if (array_key_exists('$$'.$param[3].'$$',$this->replacements)) $param[3] = $this->replacements['$$'.$param[3].'$$'];
		$replace = preg_match('/'.$param[2].'/',$this->replacements['$$'.$param[1].'$$']) ? $param[3] : $param[4];
		return $replace;
	}

	/**
	 * Download document merged with contact(s)
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	function download($document,$ids)
	{
		$content_url = egw_vfs::PREFIX.$document;
		switch (($mime_type = egw_vfs::mime_content_type($document)))
		{
			case 'application/vnd.oasis.opendocument.text':
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.odt').'-').'.odt';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'content.xml');
				break;
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.dotx').'-').'.dotx';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'word/document.xml');
				break;
		}
		if (!($merged =& $this->merge($content_url,$ids,$err,$mime_type)))
		{
			return $err;
		}
		if (isset($archive))
		{
			$zip = new ZipArchive;
			if ($zip->open($archive,ZIPARCHIVE::CHECKCONS) !== true) throw new Exception("!ZipArchive::open('$archive',ZIPARCHIVE::OVERWRITE)");
			if ($zip->addFromString($content_file,$merged) !== true) throw new Exception("!ZipArchive::addFromString('$content_file',\$merged)");
			if ($zip->close() !== true) throw new Exception("!ZipArchive::close()");
			unset($zip);
			unset($merged);
			if (file_exists('/usr/bin/zip'))	// fix broken zip archives generated by current php
			{
				exec('/usr/bin/zip -F '.escapeshellarg($archive));
			}
			ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
			readfile($archive,'r');
		}
		else
		{
			ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
			echo $merged;
		}
		$GLOBALS['egw']->common->egw_exit();
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Replacements for inserting contacts into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		$GLOBALS['egw']->common->egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Contact fields:')."</h3></td></tr>";

		$n = 0;
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if (in_array($name,array('tid','label','geo'))) continue;	// dont show them, as they are not used in the UI atm.

			if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->contacts->customfields as $name => $field)
		{
			echo '<tr><td>$$#'.$name.'$$</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example $$IF n_prefix~Mr~Hello Mr.~Hello Ms.$$ - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
		}

		$GLOBALS['egw']->translation->add_app('calendar');
		echo '<tr><td colspan="4"><h3>'.lang('Calendar fields:')." # = 1, 2, ..., 20, -1</h3></td></tr>";
		foreach(array(
			'title' => lang('Title'),
			'description' => lang('Description'),
			'participants' => lang('Participants'),
			'location' => lang('Location'),
			'start'    => lang('Start').': '.lang('Date').'+'.lang('Time'),
			'startday' => lang('Start').': '.lang('Weekday'),
			'startdate'=> lang('Start').': '.lang('Date'),
			'starttime'=> lang('Start').': '.lang('Time'),
			'end'      => lang('End').': '.lang('Date').'+'.lang('Time'),
			'endday'   => lang('End').': '.lang('Weekday'),
			'enddate'  => lang('End').': '.lang('Date'),
			'endtime'  => lang('End').': '.lang('Time'),
			'duration' => lang('Duration'),
			'category' => lang('Category'),
			'priority' => lang('Priority'),
			'updated'  => lang('Updated'),
			'recur_type' => lang('Repetition'),
			'access'   => lang('Access').': '.lang('public').', '.lang('private'),
			'owner'    => lang('Owner'),
		) as $name => $label)
		{
			if (in_array($name,array('start','end')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>$$calendar/#/'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		echo "</table>\n";

		$GLOBALS['egw']->common->egw_footer();
	}
}
