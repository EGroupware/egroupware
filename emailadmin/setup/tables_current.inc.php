<?php
/**
 * eGroupware EMailAdmin - DB schema
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @package emailadmin
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_emailadmin' => array(
		'fd' => array(
			'ea_profile_id' => array('type' => 'auto','nullable' => False,'comment' => 'the id of the profile; in programm its used as its negative counterpart'),
			'ea_smtp_server' => array('type' => 'varchar','precision' => '80','comment' => 'smtp server name or ip-address'),
			'ea_smtp_type' => array('type' => 'varchar','precision' => '56','comment' => 'smtp server type; designed to specify the corresponding php class to be used/loaded'),
			'ea_smtp_port' => array('type' => 'int','precision' => '4','comment' => 'port to be used'),
			'ea_smtp_auth' => array('type' => 'varchar','precision' => '3','comment' => 'multistate flag to indicate authentication required'),
			'ea_editforwardingaddress' => array('type' => 'varchar','precision' => '3','comment' => 'yes/no flag to indicate if a user is allowed to edit its own forwardingaddresses; server side restrictions must be met, and a suitable smtp server type selected'),
			'ea_smtp_ldap_server' => array('type' => 'varchar','precision' => '80','comment' => 'unused'),
			'ea_smtp_ldap_basedn' => array('type' => 'varchar','precision' => '200','comment' => 'unused'),
			'ea_smtp_ldap_admindn' => array('type' => 'varchar','precision' => '200','comment' => 'unused'),
			'ea_smtp_ldap_adminpw' => array('type' => 'varchar','precision' => '30','comment' => 'unused'),
			'ea_smtp_ldap_use_default' => array('type' => 'varchar','precision' => '3','comment' => 'unused'),
			'ea_imap_server' => array('type' => 'varchar','precision' => '80','comment' => 'imap server name or ip address'),
			'ea_imap_type' => array('type' => 'varchar','precision' => '56','comment' => 'imap server type, designed to specify the corresponding mail class to be loaded/used'),
			'ea_imap_port' => array('type' => 'int','precision' => '4','comment' => 'imap server port'),
			'ea_imap_login_type' => array('type' => 'varchar','precision' => '20','comment' => 'logintype to be used for authentication vs. the imap server, for this profile'),
			'ea_imap_tsl_auth' => array('type' => 'varchar','precision' => '3','comment' => 'flag to indicate wether to use certificate validation; only affects secure connections'),
			'ea_imap_tsl_encryption' => array('type' => 'varchar','precision' => '3','comment' => 'wether to use encryption 0=none, 1=STARTTLS, 2=TLS, 3=SSL'),
			'ea_imap_enable_cyrus' => array('type' => 'varchar','precision' => '3','comment' => 'flag to indicate if we have some server/system integration for account/email management'),
			'ea_imap_admin_user' => array('type' => 'varchar','precision' => '40','comment' => 'use this username for authentication on administrative purposes; or timed actions (sieve) for a user'),
			'ea_imap_admin_pw' => array('type' => 'varchar','precision' => '40','comment' => 'use this password for authentication on administrative purposes; or timed actions (sieve) for a user'),
			'ea_imap_enable_sieve' => array('type' => 'varchar','precision' => '3','comment' => 'flag to indicate that sieve support is assumed, and may be allowed to be utilized by the users affected by this profile'),
			'ea_imap_sieve_server' => array('type' => 'varchar','precision' => '80','comment' => 'sieve server name or ip-address'),
			'ea_imap_sieve_port' => array('type' => 'int','precision' => '4','comment' => 'sieve server port'),
			'ea_description' => array('type' => 'varchar','precision' => '200','comment' => 'textual descriptor used for readable distinction of profiles'),
			'ea_default_domain' => array('type' => 'varchar','precision' => '100','comment' => 'default domain string, used when vmailmanager is used as auth type for imap (also for smtp if auth is required)'),
			'ea_organisation_name' => array('type' => 'varchar','precision' => '100','comment' => 'textual organization string, may be used in mail header'),
			'ea_user_defined_identities' => array('type' => 'varchar','precision' => '3','comment' => 'yes/no flag to indicate if this profile is allowing the utiliszation of user defined identities'),
			'ea_user_defined_accounts' => array('type' => 'varchar','precision' => '3','comment' => 'yes/no flag to indicate if this profile is allowing the utilization of user defined mail accounts'),
			'ea_order' => array('type' => 'int','precision' => '4','comment' => 'helper to define the order of the profiles'),
			'ea_appname' => array('type' => 'varchar','precision' => '80','comment' => 'appname the profile is to be used for; of no practical use, as of my knowledge, was designed to allow notification to use a specific profile'),
			'ea_group' => array('type' => 'varchar','precision' => '80','comment' => 'the usergroup (primary) the given profile should be applied to','meta' => 'group'),
			'ea_user' => array('type' => 'varchar','precision' => '80','comment' => 'the user the given profile should be applied to','meta' => 'user'),
			'ea_active' => array('type' => 'int','precision' => '4','comment' => 'flag to indicate that a profile is active'),
			'ea_smtp_auth_username' => array('type' => 'varchar','precision' => '128','comment' => 'depending on smtp auth type, use this username for authentication; may hold a semicolon separated emailaddress, to specify the emailaddress to be used on sending e.g.:username;email@address.nfo'),
			'ea_smtp_auth_password' => array('type' => 'varchar','precision' => '80','comment' => 'depending on smtp auth type, the password to be used for authentication'),
			'ea_user_defined_signatures' => array('type' => 'varchar','precision' => '3','comment' => 'flag to indicate, that this profile allows its users to edit and use own signatures (rights to preferences-app needed)'),
			'ea_default_signature' => array('type' => 'text','comment' => 'the default signature (text or html)'),
			'ea_imap_auth_username' => array('type' => 'varchar','precision' => '80','comment' => 'depending on the imap auth type use this username for authentication purposes'),
			'ea_imap_auth_password' => array('type' => 'varchar','precision' => '80','comment' => 'depending on the imap auth type use this password for authentication purposes'),
			'ea_stationery_active_templates' => array('type' => 'text','comment' => 'stationery templates available for the profile')
		),
		'pk' => array('ea_profile_id'),
		'fk' => array(),
		'ix' => array('ea_appname','ea_group'),
		'uc' => array()
	),
	'egw_mailaccounts' => array(
		'fd' => array(
			'mail_id' => array('type' => 'auto','nullable' => False,'comment' => 'the id'),
			'account_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'account id of the owner, can be user AND group','meta' => 'account'),
			'mail_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '0=active, 1=alias, 2=forward, 3=forwardOnly, 4=quota'),
			'mail_value' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'the value (that should be) corresponding to the mail_type')
		),
		'pk' => array('mail_id'),
		'fk' => array(),
		'ix' => array('mail_value',array('account_id','mail_type')),
		'uc' => array()
	),
	'egw_ea_accounts' => array(
		'fd' => array(
			'acc_id' => array('type' => 'auto','nullable' => False),
			'acc_name' => array('type' => 'varchar','precision' => '80','comment' => 'description'),
			'ident_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'standard identity'),
			'acc_imap_host' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'imap hostname'),
			'acc_imap_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_imap_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '143','comment' => 'imap port'),
			'acc_sieve_enabled' => array('type' => 'bool','default' => '0','comment' => 'sieve enabled'),
			'acc_sieve_host' => array('type' => 'varchar','precision' => '128','comment' => 'sieve host, default imap_host'),
			'acc_sieve_port' => array('type' => 'int','precision' => '4','default' => '4190'),
			'acc_folder_sent' => array('type' => 'varchar','precision' => '128','comment' => 'sent folder'),
			'acc_folder_trash' => array('type' => 'varchar','precision' => '128','comment' => 'trash folder'),
			'acc_folder_draft' => array('type' => 'varchar','precision' => '128','comment' => 'draft folder'),
			'acc_folder_template' => array('type' => 'varchar','precision' => '128','comment' => 'template folder'),
			'acc_smtp_host' => array('type' => 'varchar','precision' => '128','comment' => 'smtp hostname'),
			'acc_smtp_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_smtp_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '25','comment' => 'smtp port'),
			'acc_smtp_type' => array('type' => 'varchar','precision' => '32','default' => 'emailadmin_smtp','comment' => 'smtp class to use'),
			'acc_imap_type' => array('type' => 'varchar','precision' => '32','default' => 'emailadmin_imap','comment' => 'imap class to use'),
			'acc_imap_logintype' => array('type' => 'varchar','precision' => '20','comment' => 'standard, vmailmgr, admin, uidNumber'),
			'acc_domain' => array('type' => 'varchar','precision' => '100','comment' => 'domain name'),
			'acc_further_identities' => array('type' => 'bool','nullable' => False,'default' => '1','comment' => '0=no, 1=yes'),
			'acc_user_editable' => array('type' => 'bool','nullable' => False,'default' => '1','comment' => '0=no, 1=yes'),
			'acc_sieve_ssl' => array('type' => 'int','precision' => '1','default' => '1','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'acc_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'acc_smtp_auth_session' => array('type' => 'bool','comment' => '0=no, 1=yes, use username/pw from current user'),
			'acc_folder_junk' => array('type' => 'varchar','precision' => '128','comment' => 'junk folder'),
			'acc_imap_default_quota' => array('type' => 'int','precision' => '4','comment' => 'default quota, if no user specific one set'),
			'acc_imap_timeout' => array('type' => 'int','precision' => '2','comment' => 'timeout for imap connection')
		),
		'pk' => array('acc_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_ea_credentials' => array(
		'fd' => array(
			'cred_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'into egw_ea_accounts'),
			'cred_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '&1=imap, &2=smtp, &4=admin'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account_id or 0=all'),
			'cred_username' => array('type' => 'varchar','precision' => '80','nullable' => False,'comment' => 'username'),
			'cred_password' => array('type' => 'varchar','precision' => '80','comment' => 'password encrypted'),
			'cred_pw_enc' => array('type' => 'int','precision' => '1','default' => '0','comment' => '0=not, 1=user pw, 2=system')
		),
		'pk' => array('cred_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('acc_id','account_id','cred_type'))
	),
	'egw_ea_identities' => array(
		'fd' => array(
			'ident_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'for which account'),
			'ident_realname' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'real name'),
			'ident_email' => array('type' => 'varchar','precision' => '128','comment' => 'email address'),
			'ident_org' => array('type' => 'varchar','precision' => '128','comment' => 'organisation'),
			'ident_signature' => array('type' => 'text','comment' => 'signature text'),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'default' => '0','comment' => '0=all users of give mail account'),
			'ident_name' => array('type' => 'varchar','precision' => '128','comment' => 'name of identity to display')
		),
		'pk' => array('ident_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_ea_valid' => array(
		'fd' => array(
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False)
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array(array('acc_id','account_id'),array('account_id','acc_id')),
		'uc' => array()
	),
	'egw_ea_notifications' => array(
		'fd' => array(
			'notif_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'mail account'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'user account'),
			'notif_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'folder name')
		),
		'pk' => array('notif_id'),
		'fk' => array(),
		'ix' => array(array('account_id','acc_id')),
		'uc' => array()
	)
);
