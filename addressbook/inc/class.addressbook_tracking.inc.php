<?php
/**
 * Addressbook - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Addressbook - tracking object
 */
class addressbook_tracking extends bo_tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'addressbook';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = 'creator';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field;
	/**
	 * Translate field-name to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array(
	);
	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = true;
	/**
	 * Instance of the bocontacts class calling us
	 *
	 * @access private
	 * @var bocontacts
	 */
	var $contacts;

	/**
	 * Constructor
	 *
	 * @param bocontacts &$bocontacts
	 * @return tracker_tracking
	 */
	function __construct(&$bocontacts)
	{
		parent::__construct();	// calling the constructor of the extended class

		$this->contacts =& $bocontacts;
	}

	/**
	 * Get a notification-config value
	 *
	 * @param string $what
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'sender' string send email address
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old=null)
	{
		//echo "<p>addressbook_tracking::get_config($name,".print_r($data,true).",...)</p>\n";
		switch($name)
		{
			case 'copy':
				if ($data['is_contactform'])
				{
					$copy = preg_split('/, ?/',$data['email_contactform']);
					if  ($data['email_copytoreceiver']) $copy[] = $data['email'];
					return $copy;
				}
				break;

			case 'sender':
				if ($data['is_contactform'])
				{
					//echo "<p>addressbook_tracking::get_config($name,...) email={$data['email']}, n_given={$data['n_given']}, n_family={$data['n_family']}</p>\n";
					return $data['email'] ? $data['n_given'].' '.$data['n_family'].' <'.$data['email'].'>' : null;
				}
				break;
		}
		return null;
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_message($data,$old,$receiver=null)
	{
		if (!$data['modified'] || !$old)
		{
			return lang('New contact submitted by %1 at %2',
				$GLOBALS['egw']->common->grab_owner_name($data['creator']),
				$this->datetime($data['created']));
		}
		return lang('Contact modified by %1 at %2',
			$GLOBALS['egw']->common->grab_owner_name($data['modifier']),
			$this->datetime($data['modified']));
	}

	/**
	 * Get the subject of the notification
	 *
	 * @param array $data
	 * @param array $old
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undelted
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_subject($data,$old,$deleted=null,$receiver=null)
	{
		if ($data['is_contactform'])
		{
			$prefix = ($data['subject_contactform'] ? $data['subject_contactform'] : lang('Contactform')).': ';
		}
		return $prefix.$this->contacts->link_title($data);
	}

	/**
	 * Get the details of an entry
	 *
	 * @param array|object $data
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data,$receiver=null)
	{
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if (!$data[$name] && $name != 'owner') continue;

			switch($name)
			{
				case 'n_prefix': case 'n_given': case 'n_middle': case 'n_family': case 'n_suffix':	// already in n_fn
				case 'n_fileas': case 'id': case 'tid':
					break;
				case 'created': case 'modified':
					$details[$name] = array(
						'label' => $label,
						'value' => $this->datetime($data[$name]),
					);
					break;
				case 'bday':
					if ($data[$name])
					{
						list($y,$m,$d) = explode('-',$data[$name]);
						$details[$name] = array(
							'label' => $label,
							'value' => $GLOBALS['egw']->common->dateformatorder($y,$m,$d,true),
						);
					}
					break;
				case 'owner': case 'creator': case 'modifier':
					$details[$name] = array(
						'label' => $label,
						'value' => $GLOBALS['egw']->common->grab_owner_name($data[$name]),
					);
					break;
				case 'cat_id':
					if ($data[$name])
					{
						$cats = array();
						foreach(is_array($data[$name]) ? $data[$name] : explode(',',$data[$name]) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->cats->id2name($cat_id);
						}
						$details[$name] = array(
							'label' => $label,
							'value' => explode(', ',$cats),
						);
					}
				case 'note':
					$details[$name] = array(
						'label' => $label,
						'value' => $data[$name],
						'type'  => 'multiline',
					);
					break;
				default:
					$details[$name] = array(
						'label' => $label,
						'value' => $data[$name],
					);
					break;
			}
		}
		// add custom fields for given type
		$details += $this->get_customfields($data, $data['tid']);

		return $details;
	}
}
