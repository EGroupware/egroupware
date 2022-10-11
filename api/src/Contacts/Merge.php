<?php
/**
 * EGroupware Api: Contacts document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage contacts
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

// explicit import not namespaced classes
use calendar_boupdate;	// we detect if it is not available

/**
 * Contacts document merge
 */
class Merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'	=> true,
		'show_replacements' 	=> true,
		"merge_entries"			=> true
	);

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		// overwrite global export-limit, if an addressbook one is set
		$this->export_limit = self::getExportLimit('addressbook');

		// switch of handling of html formated content, if html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('addressbook');
	}

	/**
	 * Get addressbook replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @param boolean $ignore_acl =false true: no acl check
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null,$ignore_acl=false)
	{
		if (!($replacements = $this->contact_replacements($id,'',$ignore_acl, $content)))
		{
			return false;
		}
		if($content && strpos($content, '$$#') !== false)
		{
			$this->cf_link_to_expand($this->contacts->read($id, $ignore_acl), $content, $replacements,'addressbook');
		}

		// Links
		$replacements += $this->get_all_links('addressbook', $id, '', $content);
		if (!(strpos($content,'$$calendar/') === false))
		{
			$replacements += $this->calendar_replacements($id,!(strpos($content,'$$calendar/-1/') === false));
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar (next events) of a contact
	 *
	 * @param int $id contact-id
	 * @param boolean $last_event_too =false also include information about the last event
	 * @return array
	 */
	protected function calendar_replacements($id,$last_event_too=false)
	{
		if (!class_exists('calendar_boupdate')) return array();

		$calendar = new calendar_boupdate();

		// next events
		$events = $calendar->search(array(
			'start' => $calendar->now_su,
			'users' => 'c'.$id,
			'offset' => 0,
			'num_rows' => 20,
			'order' => 'cal_start',
			'enum_recurring' => true
		));
		if (!$events)
		{
			$events = array();
		}
		if ($last_event_too==true)
		{
			$last = $calendar->search(array(
				'end' => $calendar->now_su,
				'users' => 'c'.$id,
				'offset' => 0,
				'num_rows' => 1,
				'order' => 'cal_start DESC',
				'enum_recurring' => true
			));
			$events['-1'] = $last ? array_shift($last) : array();	// returned events are indexed by cal_id!
		}
		$replacements = array();
		$n = 1;  // Returned events are indexed by cal_id, need to index sequentially
		foreach($events as $key => $event)
		{
			// Use -1 for previous key
			if($key < 0) $n = $key;

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
					$value = $event[$what] ? date($format,$event[$what]) : '';
					if ($format == 'l') $value = lang($value);
					$replacements['$$calendar/'.$n.'/'.$what.$name.'$$'] = $value;
				}
			}
			$duration = ($event['end'] - $event['start'])/60;
			$replacements['$$calendar/'.$n.'/duration$$'] = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

			++$n;
		}

		// Need to set some keys if there is no previous event
		if($last_event_too && count($events['-1']) == 0) {
			$replacements['$$calendar/-1/start$$'] = '';
			$replacements['$$calendar/-1/end$$'] = '';
			$replacements['$$calendar/-1/owner$$'] = '';
			$replacements['$$calendar/-1/updated$$'] = '';
		}
		return $replacements;
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		// Specific order for these ones
		$placeholders = [
			'contact' => [],
			'details' => [
				[
					'value' => $this->prefix($prefix, 'categories', '{'),
					'label' => lang('Category path')
				],
				['value' => $this->prefix($prefix, 'note', '{'),
				 'label' => $this->contacts->contact_fields['note']],
				['value' => $this->prefix($prefix, 'id', '{'),
				 'label' => $this->contacts->contact_fields['id']],
				['value' => $this->prefix($prefix, 'owner', '{'),
				 'label' => $this->contacts->contact_fields['owner']],
				['value' => $this->prefix($prefix, 'private', '{'),
				 'label' => $this->contacts->contact_fields['private']],
				['value' => $this->prefix($prefix, 'cat_id', '{'),
				 'label' => $this->contacts->contact_fields['cat_id']],
			],

		];

		// Iterate through the list & switch groups as we go
		// Hopefully a little better than assigning each field to a group
		$group = 'contact';
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if(in_array($name, array('tid', 'label', 'geo')))
			{
				continue;
			}    // dont show them, as they are not used in the UI atm.

			switch($name)
			{
				case 'adr_one_street':
					$group = 'business';
					break;
				case 'adr_two_street':
					$group = 'private';
					break;
				case 'tel_work':
					$group = 'phone numbers';
					break;
				case 'email':
				case 'email_home':
					$group = 'email';
					break;
				case 'freebusy_uri':
					$group = 'details';
			}
			$marker = $this->prefix($prefix, $name, '{');
			if(!array_filter($placeholders, function ($a) use ($marker)
			{
				count(array_filter($a, function ($b) use ($marker)
					  {
						  return $b['value'] == $marker;
					  })
				) > 0;
			}))
			{
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}

		// Correctly formatted address by country / preference
		$placeholders['business'][] = [
			'value' => $this->prefix($prefix, 'adr_one_formatted', '{'),
			'label' => lang('Formatted business address')
		];
		$placeholders['private'][] = [
			'value' => $this->prefix($prefix, 'adr_two_formatted', '{'),
			'label' => lang('Formatted private address')
		];

		$placeholders['EPL only'][] = [
			'value' => $this->prefix($prefix, 'share', '{'),
			'label' => lang('Public sharing URL')
		];

		$this->add_customfield_placeholders($placeholders, $prefix);

		// Don't add any linked placeholders if we're not at the top level
		// This avoids potential recursion
		if(!$prefix)
		{
			$this->add_calendar_placeholders($placeholders, $prefix);
		}

		return $placeholders;
	}

	protected function add_calendar_placeholders(&$placeholders, $prefix)
	{
		Api\Translation::add_app('calendar');

		// NB: The -1 is actually ‑1, a non-breaking hyphen to avoid UI issues where we split on -
		$group = lang('Calendar fields:') . " # = 1, 2, ..., 20, ‑1";
		foreach(array(
					'title'        => lang('Title'),
					'description'  => lang('Description'),
					'participants' => lang('Participants'),
					'location'     => lang('Location'),
					'start'        => lang('Start') . ': ' . lang('Date') . '+' . lang('Time'),
					'startday'     => lang('Start') . ': ' . lang('Weekday'),
					'startdate'    => lang('Start') . ': ' . lang('Date'),
					'starttime'    => lang('Start') . ': ' . lang('Time'),
					'end'          => lang('End') . ': ' . lang('Date') . '+' . lang('Time'),
					'endday'       => lang('End') . ': ' . lang('Weekday'),
					'enddate'      => lang('End') . ': ' . lang('Date'),
					'endtime'      => lang('End') . ': ' . lang('Time'),
					'duration'     => lang('Duration'),
					'category'     => lang('Category'),
					'priority'     => lang('Priority'),
					'updated'      => lang('Updated'),
					'recur_type'   => lang('Repetition'),
					'access'       => lang('Access') . ': ' . lang('public') . ', ' . lang('private'),
					'owner'        => lang('Owner'),
				) as $name => $label)
		{
			$placeholders[$group][] = array(
				'value' => $this->prefix(($prefix ? $prefix . '/' : '') . 'calendar/#', $name, '{'),
				'label' => $label
			);
		}
	}

	/**
	 * Get insert-in-document action with optional default document on top
	 *
	 * Overridden from parent to change the insert-in-email actions so we can
	 * have a custom action handler.
	 *
	 * @param string $dirs Directory(s comma or space separated) to search
	 * @param int $group see nextmatch_widget::egw_actions
	 * @param string $caption ='Insert in document'
	 * @param string $prefix ='document_'
	 * @param string $default_doc ='' full path to default document to show on top with action == 'document'!
	 * @param int|string $export_limit =null export-limit, default $GLOBALS['egw_info']['server']['export_limit']
	 * @return array see nextmatch_widget::egw_actions
	 */
	public static function document_action($dirs, $group=0, $caption='Insert in document', $prefix='document_', $default_doc='',
		$export_limit=null)
	{
		$actions = parent::document_action($dirs, $group, $caption, $prefix, $default_doc, $export_limit);

		// Change merge into email actions so we can customize them
		static::customise_mail_actions($actions);

		return $actions;
	}

	protected static function customise_mail_actions(&$action)
	{
		if(strpos($action['egw_open'], 'edit-mail') === 0)
		{
			unset($action['confirm_multiple']);
			$action['onExecute'] = 'javaScript:app.addressbook.merge_mail';
		}
		else if ($action['children'])
		{
			foreach($action['children'] as &$child)
			{
				static::customise_mail_actions($child);
			}
		}
	}
}
