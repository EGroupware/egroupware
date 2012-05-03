<?php
/**
 * eGroupWare - resources
 * Book a single resource through sitemgr module, not the normal method
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class resources_reserve {

	public function __construct() {
		$this->tmpl = new etemplate();
		$this->bo = new resources_bo();
	}

	/**
	 * Book a resource
	 */
	public function book($content = array(), $arguments = array())
	{
		$data = array();
		$readonlys = array();
		$display_days = $_GET['planner_days'] ? $_GET['planner_days'] : 3;
		$planner_date = $_GET['date'] ? $_GET['date'] : strtotime('yesterday',$content['date'] ? $content['date'] : time());

		if($_GET['confirm']) {
			$register_code = ($_GET['confirm'] && preg_match('/^[0-9a-f]{32}$/',$_GET['confirm'])) ? $_GET['confirm'] : false;
			if($register_code && $registration = registration_bo::confirm($register_code)) {
				// Get calendar through link
				$links = egw_link::get_links('registration', $registration['reg_id'],'calendar');
				$bo = new calendar_boupdate();
				$data = $bo->read(current($links));

				$addressbook = new addressbook_bo();
				$data += $addressbook->read(key($data['participant_types']['c']));

				// Show date of reservation, so they can see it blocked off
				$display_days = 1;
				$planner_date = mktime(0,0,0,date('m',$data['start']),date('d',$data['start']),date('Y',$data['start']));
				$readonlys['__ALL__'] = true;
				$content = array(
					'resource' => key($data['participant_types']['r']),
					'date' => $data['start'],
					'time' => $data['start'] - mktime(0,0,0,date('m',$data['start']),date('d',$data['start']),date('Y',$data['start'])),
					'quantity' => 0
				);
				calendar_so::split_status($data['participant_types']['r'][$content['resource']], $content['quantity'],$role);
				$data['msg']= '<div class="confirm">'.lang('Registration confirmed %1', egw_time::to($data['start'])) .'</div>';
			} else {
				$data['msg']= '<div class="confirm">'.lang('Unable to process confirmation.').'</div>';
			}
                }

		$this->tmpl->read('resources.sitemgr_book');

		if(!$arguments['resource'] && !($arguments['resource'] = $content['resource'])) return false;
		$data += $this->bo->read($arguments['resource']);

		$data['quantity'] = $content['quantity'] ? $content['quantity'] : 2;

		$data['date'] = $arguments['date'] ? $arguments['date'] : $content['date'];
		$data['time'] = $content['time'];

		$preserve = array(
			'resource' => $data['res_id'],
		);
		$preserve['sitemgr_version'] = $arguments['sitemgr_version'] ? $arguments['sitemgr_version'] : $content['sitemgr_version'];
		$preserve['confirmation'] = $arguments['confirmation'] || $content['confirmation'];
		$preserve['contact_form'] = $arguments['contact_form'] ? $arguments['contact_form'] : $content['contact_form'];
		$preserve['link'] = $arguments['link'] ? $arguments['link'] : $content['link'];
		$preserve['email_message'] = $arguments['email_message'] ? $arguments['email_message'] : $content['email_message'];
		$preserve['include_group'] = $arguments['include_group'] ? $arguments['include_group'] : $content['include_group'];

		// Check for valid time
		if($data['date'] && $content['book'])
		{
			$start_time = $data['date'] + (is_array($data['time']) ? $data['time']['hour']*3600 + $data['time']['minute']*60 : $data['time']);
			// Use sitemgr's default appointment length
			if($GLOBALS['egw_info']['user']['account_lid'] != $GLOBALS['sitemgr_info']['anonymous_user'])
			{
				$preferences = new preferences($GLOBALS['egw']->accounts->name2id($GLOBALS['sitemgr_info']['anonymous_user']));
				$preferences = $preferences->read_repository();
				$duration = $preferences['calendar']['defaultlength'] * 60;
			}
			else
			{
				$duration = ($GLOBALS['egw_info']['user']['preferences']['calendar']['defaultlength'] * 60);
			}
			$end_time = $start_time + $duration;

			$config = config::read('registration');

			// Not a user, need contact
			if($GLOBALS['egw_info']['user']['account_lid'] == $GLOBALS['sitemgr_info']['anonymous_user'] && !$content['contact_id'])
			{
				if ($config['pending_addressbook'])   // save the contact in the addressbook
				{
					$content['owner'] = $config['pending_addressbook'];
					$content['private'] = 0;        // in case default_private is set
				}

				$addressbook = new addressbook_bo();
				$contact_fields = $addressbook->contact_fields;
				unset($contact_fields['email']); // Always present
				unset($contact_fields['id']); // Address already there
				if(array_intersect_key($contact_fields,$content)) {
					$result = $addressbook->save($content);
					if(!$result) {
						throw new egw_exception_no_permission($addressbook->error);
						return False;
					}


					$contact_id = $result;
					$contact = 'c'.$contact_id;
				}
			}
			else
			{
				$contact = $GLOBALS['egw_info']['user']['account_id'];
				$contact_id = $GLOBALS['egw_info']['user']['person_id'];
			}

			if($contact) {
				// Make event
				$event = array(
					'title' =>	egw_link::title('addressbook', $contact_id),
					'start' =>	$start_time,
					'end' =>	$end_time,
					'participants' => array(
						'r'.$data['res_id'] => calendar_so::combine_status('U', $data['quantity'], 'REQ-PARTICIPANT'),
						$contact => calendar_so::combine_status('U', $data['quantity'], 'REQ-PARTICIPANT')
					)
				);
				if($preserve['include_group'])
				{
					$event['participants'][$preserve['include_group']] = calendar_so::combine_status('U', 1, 'REQ-PARTICIPANT');
				}
				$cal_bo = new calendar_boupdate();
				$result = $cal_bo->update($event);

				// Show date of reservation, so they can see it blocked off
				$display_days = 1;
				$planner_date = mktime(0,0,0,date('m',$event['start']),date('d',$event['start']),date('Y',$event['start']));

				// Free time search
				if(!is_numeric($result))
				{
					$form = new calendar_uiforms();
					$freetime = $form->freetime('r'.$data['res_id'],
						strtotime($GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts'] . ':00',$start_time),
						strtotime($GLOBALS['egw_info']['user']['preferences']['calendar']['workdayends'] . ':00 ',$end_time),
						$duration
					);
					$data += $content;
					$data['msg'] = lang('Please choose a different time:')."\n";
					foreach($freetime as $slot) {
						$data['msg'] .= egw_time::to($slot['start']) . ' - ' . egw_time::to($slot['end'])."<br />\n";
					}
				}
				elseif ($preserve['confirmation'] && $contact_id && $result)
				{
					// Confirmation required - send registration email
					$reg_id = registration_bo::save(array(
						'contact_id' => $contact_id,
						'timestamp' => time() + ($config['expiry'] * 3600),
						'post_confirm_hook' => 'resources.resources_reserve.confirm',
						'sitemgr_version' => $preserve['sitemgr_version']
					), false);
					if(is_numeric($reg_id))
					{
						// Link to event
						egw_link::link('registration', $reg_id, 'calendar', $result);
						$reg = registration_bo::read($reg_id);

						// Send email
						$email_info = $config + array(
							'title' => $data['name'],
							'subject' => $data['name'] . ' ' . egw_time::to($start_time),
							'link' => $preserve['link'],
						);
						if($preserve['email_message'])
						{
							$email_info['message'] = lang($preserve['email_message'],
								egw_time::to($start_time),
								$preserve['link'].'&confirm='.$reg['register_code'],
								egw_time::to($reg['timestamp'])
							);
						}
						$data['msg'] .= registration_bo::send_confirmation($email_info, $reg);
					}
				}
			}
		}

		$data['picture'] = $this->bo->get_picture($data);

		// Not a user, need contact
		if($GLOBALS['egw_info']['user']['account_lid'] == $GLOBALS['sitemgr_info']['anonymous_user'] && !$content['contact_id'])
		{
			$data['contact_form'] = $preserve['contact_form'];
			$data['show_contact'] = !$preserve['contact_form'];
		}

		$cal_user = array('r'.$arguments['resource']);
		foreach($this->bo->get_acc_list($data['res_id']) as $acc_id => $name)
		{
			$cal_user[] = 'r'.$acc_id;
		}

		if(!$data['date']) $data['date'] = strtotime('tomorrow');
		if(!$data['time']) $data['time']['hour'] = $GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts'];

		// Use sitemgr's default appointment length
		if($GLOBALS['egw_info']['user']['account_lid'] != $GLOBALS['sitemgr_info']['anonymous_user'])
		{
			$preferences = new preferences($GLOBALS['egw']->accounts->name2id($GLOBALS['sitemgr_info']['anonymous_user']));
			$preferences = $preferences->read_repository();
			$data['duration'] = $preferences['calendar']['defaultlength'];
		}
		else
		{
			$data['duration'] = $GLOBALS['egw_info']['user']['preferences']['calendar']['defaultlength'];
		}
		// Leading 0
		$data['duration'] = '0'.$data['duration'];

		if(!$GLOBALS['egw_info']['user']['apps']['resources'] || !$this->bo->get_calendar_info($data['res_id']) || // Needed for booking
			!$GLOBALS['egw_info']['user']['apps']['calendar'] // Needed for updating schedule
		)
		{
			$data['planner'] = 'Permission error - site not configured properly.  Need ' . lang('resources') . ' & ' . lang('calendar');
		}
		else
		{
			$data['planner'] = $this->get_planner(
				$arguments['resource'],
				$planner_date ? $planner_date : strtotime('yesterday',$date), // Show day before, too
				$display_days,
				$preserve['link']
			);
		}

		// Restrict times to working hours
		for($i = $GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts']; $i < $GLOBALS['egw_info']['user']['preferences']['calendar']['workdayends']; $i++)
		{
			if($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '12')
			{
				$sel_options['hour'][$i] = ($i < '12' ? $i .' am' : ($i > 12 ? $i -12 : $i).' pm');
			}
			else
			{
				$sel_options['hour'][$i] = ($i < '10' ? '0'.$i : $i);
			}
		}

		return $this->tmpl->exec('resources.resources_reserve.book',$data,$sel_options,$readonlys,$preserve);
	}

	public function ajax_update_planner($resource_id, $date) {
		$response = egw_json_response::get();
		$response->assign('exec[planner_div]','innerHTML',$this->get_planner(
			$resource_id,
			strtotime('yesterday',$date),
			3,
			$_SERVER['HTTP_REFERER']
		));
	}

	/**
	 * Get HTML for the planner to show when its busy
	 *
	 * @param resource_id
	 *
	 * @return String - HTML
	 */
	protected function get_planner($resource_id, $date, $days = 3, $link)
	{
//echo "get_planner($resource_id, $date, $days, $link)";
		$calendar = new calendar_uiviews(array(
			'owner'		=> 'r'.$resource_id,
			//'owner'		=> implode(',',$cal_user),
			'planner_days'	=> $days,
			'view'		=> 'date_advance',
			'date'		=> $date
		));
		// Need to add this in explicitly, or it may be lost if use clicks a day
		$calendar->search_params['users'][] = 'r'.$resource_id;
		$calendar->search_params['users'][] = $GLOBALS['egw_info']['user']['account_id'];
		$calendar->allowEdit = false;    // switches off all edit popups

		$planner = '<style type="text/css">'."\n".
			'@import url('.$GLOBALS['egw_info']['server']['webserver_url'].'/calendar/templates/default/app.css'.");\n".
			'</style>'."\n";

		// replacing egw-urls with sitemgr ones, allows to use navigation links
		$planner .= str_replace($GLOBALS['egw_info']['server']['webserver_url'].'/index.php?',
			$link.'&',
			$calendar->planner(true));

		return $planner;
	}

	/**
	 * Confirm a registration done through sitemgr
	 *
	 * @param registration - registration information
	 */
	public function confirm($registration)
	{
		// Get calendar through link
		$links = egw_link::get_links('registration', $registration['reg_id'],'calendar');
		$bo = new calendar_boupdate();
		$event = $bo->read(current($links));
		if($registration['status'] == registration_bo::CONFIRMED)
		{
			$bo->set_status(current($links), 'c'.$registration['contact_id'], 'A');
		}
		else
		{
			$bo->delete(current($links));
			// Remove contact ID, or registration will try to purge the account
			if($registration['account_id'])
			{
				unset($registration['contact_id']);
			}
		}
		return $registration;
	}
}

