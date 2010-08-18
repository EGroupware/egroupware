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
					$boemailadmin->deleteProfile($value['profileID']);
				}
			}
		}
	}

}
