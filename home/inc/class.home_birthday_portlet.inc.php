<?php

 /*
  * Egroupware
  * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
  * @package home
  * @subpackage portlet
  * @link http://www.egroupware.org
  * @author Nathan Gray
  * @version $Id$
  */


 /**
  * Show birthdays
  *
  */
 class home_birthday_portlet extends home_portlet {
	 /**
	 * Constructor sets up the portlet according to the user's saved property values
	 * for this particular portlet.  It is possible to have multiple instances of the
	 * same portlet with different properties.
	 *
	 * The implementing class is allowed to modify the context, if needed, but it is
	 * better to use get_properties().
	 *
	 * @param context Array portlet settings such as size, as well as values for properties
	 * @param boolean $need_reload Flag to indicate that the portlet needs to be reloaded (exec will be called)
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$this->context = $context;
	}

	/**
	 * Some descriptive information about the portlet, so that users can decide if
	 * they want it or not, and for inclusion in lists, hover text, etc.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @return Array with keys:
	 * - displayName: Used in lists
	 * - title: Put in the portlet header
	 * - description: A short description of what this portlet does or displays
	 */
	public  function get_description()
	{
		return array(
			'displayName'=> lang('Birthday reminders'),
			'title'=>	lang('Birthday reminders'),
			'description'=>	lang('Birthday reminders')
		);
	}

	/**
	 * Generate a list of birthdays according to properties
	 *
	 * @param id String unique ID, provided to the portlet so it can make sure content is
	 * 	unique, if needed.
	 * @param etemplate etemplate_new Etemplate to generate content
	 */
	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		$content = array();

		$etemplate->read('home.birthdays');
		
		if ($GLOBALS['egw_info']['server']['hide_birthdays'] != 'yes')	// calendar config
		{
			$content = $this->get_birthdays();
		}
		$etemplate->set_dom_id($id);

		$etemplate->exec('home.home_list_portlet.exec',$content);
	}

	/**
	 * Get a list of birthdays
	 */
	protected function get_birthdays()
	{
		$contacts = new addressbook_bo();
		$month_start = date('-m-',$contacts->now_su);
		$days = $this->context['days'];
		$birthdays = array();

		$bdays =& $contacts->search(array('bday' => $month_start),array('id','n_family','n_given','bday'),'n_given,n_family','','%');
		// search accounts too, if not stored in accounts repository
		$extra_accounts_search = $contacts->account_repository == 'ldap' && !is_null($contacts->so_accounts) &&
			!$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'];
		if ($extra_accounts_search && ($bdays2 = $contacts->search(array('bday' => $month_start),array('id','n_family','n_given','bday'),
			'n_given,n_family','','%',false,'AND',false,array('owner' => 0))))
		{
			$bdays = !$bdays ? $bdays2 : array_merge($bdays,$bdays2);
		}
		if (($month_end = date('-m-',$contacts->now_su+$days*24*3600)) != $month_start)
		{
			if (($bdays2 =& $contacts->search(array('bday' => $month_end),array('id','n_family','n_given','bday'),'n_given,n_family','','%')))
			{
				$bdays = !$bdays ? $bdays2 : array_merge($bdays,$bdays2);
			}
			// search accounts too, if not stored in accounts repository
			if ($extra_accounts_search && ($bdays2 = $contacts->search(array('bday' => $month_end),array('id','n_family','n_given','bday'),
				'n_given,n_family','','%',false,'AND',false,array('owner' => 0))))
			{
				$bdays = !$bdays ? $bdays2 : array_merge($bdays,$bdays2);
			}
		}
		unset($bdays2); unset($extra_accounts_search);
		unset($month_start); unset($month_end);
		if ($bdays)
		{
			for($n = 0; $n <= $days; ++$n)
			{
				$day = date('-m-d',$contacts->now_su+$n*24*3600);
				foreach($bdays as $contact)
				{
					if(substr($contact['bday'],-6) == $day)
					{
						if (!$ab_lang_loaded++) $GLOBALS['egw']->translation->add_app('addressbook');
						switch($n)
						{
							case 0:
								$text = lang("Today is %1's birthday!", $contact['n_given'].' '.$contact['n_family']);
								break;
							case 1:
								$text = lang("Tomorrow is %1's birthday.", $contact['n_given'].' '.$contact['n_family']);
								break;
							default:
								list($y,$m,$d) = explode('-',$contact['bday']);
								if ($GLOBALS['egw_info']['server']['hide_birthdays'] == 'dateonly') $y = '';
								$text = lang("In %1 days (%2) is %3's birthday.",$n,
									$GLOBALS['egw']->common->dateformatorder($y,$m,$d,true),
									$contact['n_given'].' '.$contact['n_family']);
								break;
						}

						// Cheat the link widget by providing whatever title we want
						$birthdays[] = array(
							'title' => $text,
							'app' => 'addressbook',
							'id' => $contact['id']
						);
					}
				}
			}
		}
		return $birthdays;
	}

	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @see preferences/inc/class.preferences_settings.inc.php
	 * @return Array of settings.  Each setting should have the following keys:
	 * - name: Internal reference
	 * - type: Widget type for editing
	 * - label: Human name
	 * - help: Description of the setting, and what it does
	 * - default: Default value, for when it's not set yet
	 */
	public function get_properties()
	{
		$properties = parent::get_properties();

		$properties[] = array(
			'name'	=>	'days',
			'type'	=>	'listbox',
			'label'	=>	'',
			'default' => 3,
			'select_options' => array(
				1 => lang('Yes, for today and tomorrow'),
				3 => lang('Yes, for the next three days'),
				7 => lang('Yes, for the next week'),
				14=> lang('Yes, for the next two weeks'),
			),
		);
		return $properties;
	}

	/**
	 * Return a list of allowable actions for the portlet.
	 *
	 * These actions will be merged with the default portlet actions.  Use the
	 * same id / key to override the default action.
	 */
	public function get_actions()
	{
		return array();
	}
 }
