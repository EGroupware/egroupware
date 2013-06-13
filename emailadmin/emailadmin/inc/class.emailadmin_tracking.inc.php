<?php
/**
 * EMailAdmin - history
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.emailadmin_tracking.inc.php 29941 2010-04-22 15:39:32Z nathangray $
 */

/**
 * EMailAdmin - tracking object
 */
class emailadmin_tracking extends bo_tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'emailadmin';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'ea_profile_id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	//var $creator_field = '';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	//var $assigned_field = '';
	/**
	 * Translate field-name to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array(
		'ea_smtp_server'	=> 'ea_smtp_server',
		'ea_smtp_type'	=> 'ea_smtp_type',
		'ea_smtp_port'	=> 'ea_smtp_port',
		'ea_smtp_auth'	=> 'ea_smtp_auth',
		'ea_editforwardingaddress'	=> 'ea_editforwardingaddress',
		'ea_smtp_ldap_server'	=> 'ea_smtp_ldap_server', 
		'ea_smtp_ldap_basedn'	=> 'ea_smtp_ldap_basedn',
		'ea_smtp_ldap_admindn'	=> 'ea_smtp_ldap_admindn',
		'ea_smtp_ldap_adminpw'	=> 'ea_smtp_ldap_adminpw',
		'ea_smtp_ldap_use_default'	=> 'ea_smtp_ldap_use_default',
		'ea_imap_server'	=> 'ea_imap_server',
		'ea_imap_type'	=> 'ea_imap_type',
		'ea_imap_port'	=> 'ea_imap_port',
		'ea_imap_login_type'	=> 'ea_imap_login_type',
		'ea_imap_tsl_auth'	=> 'ea_imap_tsl_auth',
		'ea_imap_tsl_encryption'	=> 'ea_imap_tsl_encryption',
		'ea_imap_enable_cyrus'	=> 'ea_imap_enable_cyrus',
		'ea_imap_admin_user'	=> 'ea_imap_admin_user',
		'ea_imap_admin_pw'	=> 'ea_imap_admin_pw',
		'ea_imap_enable_sieve'	=> 'ea_imap_enable_sieve',
		'ea_imap_sieve_server'	=> 'ea_imap_sieve_server',
		'ea_imap_sieve_port'	=> 'ea_imap_sieve_port',
		'ea_description'	=> 'ea_description',
		'ea_default_domain'	=> 'ea_default_domain',
		'ea_organisation_name'	=> 'ea_organisation_name',
		'ea_user_defined_identities'	=> 'ea_user_defined_identities',
		'ea_user_defined_accounts'	=> 'ea_user_defined_accounts',
		'ea_order'	=> 'ea_order',
		'ea_appname'	=> 'ea_appname',
		'ea_group'	=> 'ea_group',
		'ea_user'	=> 'ea_user',
		'ea_active'	=> 'ea_active',
		'ea_smtp_auth_username'	=> 'ea_smtp_auth_username',
		'ea_smtp_auth_password'	=> 'ea_smtp_auth_password',
		'ea_user_defined_signatures'	=> 'ea_user_defined_signatures',
		'ea_default_signature'	=> 'ea_default_signature',
		'ea_imap_auth_username'	=> 'ea_imap_auth_username',
		'ea_imap_auth_password'	=> 'ea_imap_auth_password',
		'ea_stationery_active_templates'	=> 'ea_stationery_active_templates'
	);

	/**
     * Translate field name to label
     */
    public $field2label = array(
		'ea_smtp_server'	=> 'SMTP server',
		'ea_smtp_type'	=> 'SMTP type',
		'ea_smtp_port'	=> 'SMTP port',
		'ea_smtp_auth'	=> 'SMTP authentification',
		'ea_editforwardingaddress'	=> 'edit forwarding address',
		'ea_smtp_ldap_server'	=> 'SMTP Ldap Server', 
		'ea_smtp_ldap_basedn'	=> '',
		'ea_smtp_ldap_admindn'	=> '',
		'ea_smtp_ldap_adminpw'	=> 'SMTP Ldap admin password',
		'ea_smtp_ldap_use_default'	=> 'SMTP Ldap use default',
		'ea_imap_server'	=> 'IMAP server',
		'ea_imap_type'	=> 'IMAP type',
		'ea_imap_port'	=> 'IMAP port',
		'ea_imap_login_type'	=> 'IMAP login type',
		'ea_imap_tsl_auth'	=> 'IMAP Tsl authentification',
		'ea_imap_tsl_encryption'	=> 'IMAP Tsl encryption',
		'ea_imap_enable_cyrus'	=> 'IMAP enable Cyrus',
		'ea_imap_admin_user'	=> 'IMAP admin user',
		'ea_imap_admin_pw'	=> 'IMAP admin password',
		'ea_imap_enable_sieve'	=> 'IMAP enable Sieve',
		'ea_imap_sieve_server'	=> 'IMAP Sieve server',
		'ea_imap_sieve_port'	=> 'IMAP Sieve port',
		'ea_description'	=> 'Description',
		'ea_default_domain'	=> 'Default domain',
		'ea_organisation_name'	=> 'Organisation',
		'ea_user_defined_identities'	=> 'User defined identities',
		'ea_user_defined_accounts'	=> 'User defined accounts',
		'ea_order'	=> 'Order',
		'ea_appname'	=> 'Application',
		'ea_group'	=> 'Group',
		'ea_user'	=> 'User',
		'ea_active'	=> 'Active',
		'ea_smtp_auth_username'	=> 'SMTP authentification user',
		'ea_smtp_auth_password'	=> 'SMTP authentification password',
		'ea_user_defined_signatures'	=> 'User defined signatures',
		'ea_default_signature'	=> 'Default signature',
		'ea_imap_auth_username'	=> 'IMAP authentification user',
		'ea_imap_auth_password'	=> 'IMAP authentification password',
		'ea_stationery_active_templates'	=> ''
	);

	/**
	 * Fields that contain passwords
	 */
	static $passwordFields = array(
		'ea_smtp_auth_password',
		'ea_imap_auth_password',
		'ea_smtp_ldap_adminpw',
		'ea_imap_admin_pw',
		);

	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = false;
	/**
	 * Instance of the emailadmin_bo class calling us
	 *
	 * @access private
	 * @var emailadmin_bo
	 */
	var $emailadmin_bo;

	/**
	 * Constructor
	 *
	 * @param emailadmin_bo &$emailadmin_bo
	 * @return tracker_tracking
	 */
	function __construct(&$emailadmin_bo)
	{
		parent::__construct();	// calling the constructor of the extended class

		$this->emailadmin_bo =& $emailadmin_bo;
	}

	/**
	 * Tracks the changes in one entry $data, by comparing it with the last version in $old
	 *
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param int $user=null user who made the changes, default to current user
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undeleted
	 * @param array $changed_fields=null changed fields from ealier call to $this->changed_fields($data,$old), to not compute it again
	 * @param boolean $skip_notification=false do NOT send any notification
	 * @return int|boolean false on error, integer number of changes logged or true for new entries ($old == null)
	 */
	public function track(array $data,array $old=null,$user=null,$deleted=null,array $changed_fields=null,$skip_notification=false)
	{
		foreach (self::$passwordFields as $k => $v)
		{
			if (is_array($data))
			{
				foreach($data as $key => &$dd)
				{
					if ($key == $v && !empty($dd))
					{
						$dd = $this->maskstring($dd);
					} 
				}
			}
			if (is_array($old))
			{
				foreach($old as $ko => &$do)
				{
					//error_log(__METHOD__.__LINE__.$ko);
					if ($ko == $v && !empty($do))
					{
						$do = $this->maskstring($do);
					}
				}
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($data));
		//error_log(__METHOD__.__LINE__.array2string($old));

		return parent::track($data,$old,$user,$deleted,$changed_fields,$skip_notifications);
	}

	private function maskstring($data)
	{
		$length =strlen($data);
		$first = substr($data,0,1);
		$last = substr($data,-1);
		if ($length<3)
		{
			$data = str_repeat('*',$length);
		}
		else
		{
			$data = $first.str_repeat('*',($length-2>0?$length-2:1)).$last;
		}
		return $data;
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
		return null;
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_message($data,$old)
	{
		return null;
	}

	/**
	 * Get the subject of the notification
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_subject($data,$old)
	{
		return null;
	}

	/**
	 * Get the details of an entry
	 *
	 * @param array $data
	 *
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data)
	{
		return null;
	}
}
