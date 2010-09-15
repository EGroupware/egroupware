<?php
/**
 * eGroupWare API: Sending mail via PHPMailer
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once(EGW_API_INC.'/class.phpmailer.inc.php');

/**
 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
 * or regular error_log for true (can be set either in DB or header.inc.php).
 * 
 * This class does NOT use anything EGroupware specific, it acts like PHPMail, but logs.
 */
class egw_mailer extends PHPMailer
{
	/**
	 * Constructor: always throw exceptions instead of echoing errors and EGw pathes
	 */
	function __construct()
	{
		parent::__construct(true);	// throw exceptions instead of echoing errors
		
		// setting EGroupware specific path for PHPMailer lang files
		list($lang,$nation) = explode('-',$GLOBALS['egw_info']['user']['preferences']['common']['lang']);
		$lang_path = EGW_SERVER_ROOT.'/phpgwapi/lang/';
		if ($nation && file_exists($lang_path."phpmailer.lang-$nation.php"))	// atm. only for pt-br => br
		{
			$lang = $nation;
		}
		if (!$this->SetLanguage($lang,$lang_path))
		{
			$this->SetLanguage('en',$lang_path);	// use English default
		}
	}

	/**
	 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
	 * or regular error_log for true (can be set either in DB or header.inc.php).
	 * 
	 * We can NOT supply this method as callback to phpMailer, as phpMailer only accepts
	 * functions (not methods) and from a function we can NOT access $this->ErrorInfo.
	 * 
	 * @param boolean $isSent
	 * @param string $to
	 * @param string $cc
	 * @param string $bcc
	 * @param string $subject
	 * @param string $body
	 */
  	protected function doCallback($isSent,$to,$cc,$bcc,$subject,$body)
	{
		if ($GLOBALS['egw_info']['server']['log_mail'])
		{
			$msg = $GLOBALS['egw_info']['server']['log_mail'] !== true ? date('Y-m-d H:i:s')."\n" : '';
			$msg .= ($isSent ? 'Mail send' : 'Mail NOT send').
				' to '.$to.' with subject: "'.trim($subject).'"';

			if ($GLOBALS['egw_info']['user']['account_id'] && class_exists('common',false))
			{
				$msg .= ' from user #'.$GLOBALS['egw_info']['user']['account_id'].' ('.
					common::grab_owner_name($GLOBALS['egw_info']['user']['account_id']).')';
			}
			if (!$isSent)
			{
				$this->SetError('');	// queries error from (private) smtp and stores it in $this->ErrorInfo
				$msg .= $GLOBALS['egw_info']['server']['log_mail'] !== true ? "\n" : ': ';
				$msg .= 'ERROR '.str_replace(array('Language string failed to load: smtp_error',"\n","\r"),'',
					strip_tags($this->ErrorInfo));
			}
			if ($GLOBALS['egw_info']['server']['log_mail'] !== true) $msg .= "\n\n";

			error_log($msg,$GLOBALS['egw_info']['server']['log_mail'] === true ? 0 : 3,
				$GLOBALS['egw_info']['server']['log_mail']);
		}
		// calling the orginal callback of phpMailer
		parent::doCallback($isSent,$to,$cc,$bcc,$subject,$body);
	}
}
