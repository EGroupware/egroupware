<?php
/**
 * Addressbook - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @return string/boolean merged document or false on error
	 */
	function merge($document,$ids,&$err)
	{
		if (!($content = file_get_contents(egw_vfs::PREFIX.$document)))
		{
			$err = lang("Document '%1' does not exist or is not readable for you!",$document);
			return false;
		}
		list($contentstart,$contentrepeat,$contentend) = preg_split('/\$\$pagerepeat\$\$/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
		if (count($ids) > 1 && !$contentrepeat)
		{
			$err = lang('for more then one contact in a document use the tag pagerepeat!');
			return false;
		}
		foreach ($ids as $id)
		{
			if ($contentrepeat)   $content = $contentrepeat;   //content to repeat
			// generate replacements
			if (!($replacements = $this->contact_replacements($id)))
			{
				$err = lang('Contact not found!');
				return false;
			}
			if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
			{
				$replacements += $this->contact_replacements($user,'user');
			}
			if (!(strpos($content,'$$calendar/') === false))
			{
				$replacements += $this->calendar_replacements($id,!(strpos($content,'$$calendar/-1/') === false));
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
		if ($contentrepeat)
		{
			return $contentstart.implode('\\par \\page\\pard\\plain',$contentrep).$contentend;
		}
		return $content;
	}


	function replace_callback($param)
	{
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
		if (!($merged = $this->merge($document,$ids,$err)))
		{
			return $err;
		}
		$mime_type = egw_vfs::mime_content_type($document);
		ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
		echo $merged;

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

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
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
