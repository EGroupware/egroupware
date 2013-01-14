<?php
/**
 * eGroupWare - eMailAdmin hooks
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Klaus Leithoff <leithoff-AT-stylite.de>
 * @copyright (c) 2008-8 by leithoff-At-stylite.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * diverse static emailadmin hooks
 */
class emailadmin_hooks
{
	// hook to plug in into admin (managable) applications list
	static function admin()
	{
		// Only Modify the $file and $title variables.....
		$title = $appname = 'emailadmin';
		$file = Array(
			'Site Configuration'	=> $GLOBALS['egw']->link('/index.php','menuaction=emailadmin.emailadmin_ui.index')
		);

		//Do not modify below this line
		display_section($appname,$title,$file);
	}

    /**
     * Hook called if account emailadim settings has to be modified
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     */
	static function edit_user($data)
	{
		//echo "called hook and function<br>".function_backtrace()."<br>";
		//_debug_array($data);

		if ($data['account_id'] && // can't set it on add
			$GLOBALS['egw_info']['user']['apps']['emailadmin'])
		{
			$GLOBALS['menuData'][] = array(
				'description' => 'eMailAdmin: User assigned Profile',
				'url' => '/index.php',
				'extradata' => 'menuaction=emailadmin.emailadmin_ui.index'
			);
		}
	}

    /**
     * Hook called after group emailadim settings has to be modified
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     */
    static function edit_group($data)
    {
		#echo "called hook and function<br>";
		#_debug_array($data);
		# somehow the $data var seems to be quite sparsely populated, so we dont check any further
        if (#!empty($data['account_id']) && $data['account_id'] < 0 && // can't set it on add
            $GLOBALS['egw_info']['user']['apps']['emailadmin'])
        {
            $GLOBALS['menuData'][] = array(
                'description' => 'eMailAdmin: Group assigned Profile',
                'url' => '/index.php',
                'extradata' => 'menuaction=emailadmin.emailadmin_ui.index'
            );
        }
    }

    /**
     * Hook called before an account get deleted
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     * @param string $data['account_lid'] account-name
     * @param int $data['new_owner'] account-id of new owner, or false if data should get deleted
     */
	static function deleteaccount(array $data)
	{
		if((int)$data['account_id'] > 0 &&
			$GLOBALS['egw_info']['user']['apps']['emailadmin'])
		{
			$boemailadmin = new emailadmin_bo();
			$profileList = $boemailadmin->getProfileList($profileID,$appName,$groupID,(int) $data['account_id']);
			if (is_array($profileList)) {
				foreach ($profileList as $key => $value) {
					$boemailadmin->delete($value['profileID']);
				}
			}
		}

	}

    /**
     * Hook called before a group get deleted
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     * @param string $data['account_name'] account-name
     */
	static function deletegroup(array $data)
	{
		if ((int)$data['account_id'] < 0 &&
			$GLOBALS['egw_info']['user']['apps']['emailadmin'])
		{
			$boemailadmin = new emailadmin_bo();
			$profileList = $boemailadmin->getProfileList($profileID,$appName,(int) $data['account_id'],$accountID);
			if (is_array($profileList)) {
				foreach ($profileList as $key => $value) {
					$boemailadmin->soemailadmin->deleteProfile($value['profileID']);
				}
			}
		}
	}

	/**
	 * Add further imap and smtp server plugins to emailadmin
	 *
	 * @param string|array $data location string or array with key 'location' and other params
	 * @return array
	 */
	public static function server_types($data)
	{
		$location = is_array($data) ? $data['location'] : $data;
		$extended = is_array($data) ? $data['extended'] : false;

		switch($location)
		{
			case 'imap_server_types':
				return array(
					'defaultimap' 	=> $extended ? array(
						'description'	=> 'standard IMAP server',
						'protocol'	=> 'imap',
						'classname'	=> 'defaultimap'
					) : 'standard IMAP server',
					'emailadmin_dovecot' => $extended ? array(
						'description' => 'Dovecot',
						'classname' => 'emailadmin_dovecot',
						'protocol' => 'imap',
					) : 'Dovecot',
					'cyrusimap' 	=> $extended ? array(
						'description'	=> 'Cyrus IMAP Server',
						'protocol'	=> 'imap',
						'classname'	=> 'cyrusimap'
					) : 'Cyrus IMAP Server',
					'dbmailqmailuser' 	=> $extended ? array(
						'description'	=> 'DBMail (qmailUser schema)',
						'protocol'	=> 'imap',
						'classname'	=> 'dbmailqmailuser'
					) : 'DBMail (qmailUser schema)',
					'pleskimap'     => $extended ? array(
						'description'   => 'Plesk IMAP Server (Courier)',
						'protocol'      => 'imap',
						'classname'     => 'pleskimap'
					) : 'Plesk IMAP Server (Courier)',
					'dbmaildbmailuser' 	=> $extended ? array(
						'description'	=> 'DBMail (dbmailUser schema)',
						'protocol'	=> 'imap',
						'classname'	=> 'dbmaildbmailuser'
					) : 'DBMail (dbmailUser schema)',
				);

			case 'smtp_server_types':	// nothing yet
				return array(
					'emailadmin_smtp' 	=> $extended ? array(
						'description'	=> 'standard SMTP-Server',
						'classname'	=> 'emailadmin_smtp'
					) : 'standard SMTP-Server',
					'emailadmin_smtp_sql' => $extended ? array(
						'description' => 'Postfix (SQL)',
						'classname' => 'emailadmin_smtp_sql',
					) : 'Postfix (SQL)',
					'postfixldap' 	=> $extended ? array(
						'description'	=> 'Postfix (qmail Schema)',
						'classname'	=> 'postfixldap'
					) : 'Postfix (qmail Schema)',
					'postfixinetorgperson'     => $extended ? array(
						'description'   => 'Postfix (inetOrgPerson Schema)',
						'classname'     => 'postfixinetorgperson'
					) : 'Postfix (inetOrgPerson Schema)',
					'smtpplesk'     => $extended ? array(
						'description'   => 'Plesk SMTP-Server (Qmail)',
						'classname'     => 'smtpplesk'
					) : 'Plesk SMTP-Server (Qmail)',
					'postfixdbmailuser' 	=> $extended ? array(
						'description'   => 'Postfix (dbmail Schema)',
						'classname'     => 'postfixdbmailuser'
					) : 'Postfix (dbmail Schema)',
				);
				break;
		}
	}
}
