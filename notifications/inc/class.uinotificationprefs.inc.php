<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

require_once(EGW_INCLUDE_ROOT. SEP. 'etemplate'. SEP. 'inc'. SEP. 'class.etemplate.inc.php');

class uinotificationprefs {
	
	const _appname = 'notifications';
	
	public $public_functions = array(
		'index' => true,
	);
	
	/**
	 * This are the preferences for notifications 
	 *
	 * @var array
	 */
	private $notification_preferences = array(
		'disable_ajaxpopup' => '',		// bool: true / false
	);
	
	/**
	 * Holds preferences object for current user
	 *
	 * @var object 
	 */
	private $preferences;
	
	public function __construct($_account_id = 0, $_referer = false) {
		$account_id = $_account_id > 0 ? $_account_id : $GLOBALS['egw_info']['user']['account_id'];
		$this->preferences = new preferences($account_id);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Preferences for notification');
	}
	
	public function index($_content = false) {
		$et = new etemplate(self::_appname. '.prefsindex');
		$content = array();
		$sel_options = array();
		$readonlys = array();
		$preserv = array();
		if (is_array($_content)) {
			if (is_array($_content['button'])) {
				$preferences = array_intersect_key($_content, $this->notification_preferences);
				list($button) = each($_content['button']);
				switch ($button) {
					case 'save' : 
						$this->save($preferences);
						$GLOBALS['egw']->redirect_link($_content['referer']);
					case 'apply' :
						$this->save($preferences);
						break;
					case 'cancel' :
					default :
						$GLOBALS['egw']->redirect_link($_content['referer']);
				}
			}
		}
		else {
			$preferences = $this->preferences->read();
			$preferences = $preferences[self::_appname];
			$preserv['referer'] = $GLOBALS['egw']->common->get_referer();
		}
		
		$content = array_merge($content,(array)$preferences);
		return $et->exec(self::_appname. '.uinotificationprefs.index',$content,$sel_options,$readonlys,$preserv);
	}
	
	private function save($_preferences) {
		$this->preferences->read();
		$this->preferences->user[self::_appname] = $_preferences;
		$this->preferences->save_repository();
		return;
	}
}