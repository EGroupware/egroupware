<?php
/**************************************************************************\
* eGroupWare API - smtp mailer using PHPMailer                             *
* This file written by RalfBecker@outdoor-training.de                      *
* ------------------------------------------------------------------------ *
* This library is free software; you can redistribute it and/or modify it  *
* under the terms of the GNU Lesser General Public License as published by *
* the Free Software Foundation; either version 2.1 of the License,         *
* or any later version.                                                    *
* This library is distributed in the hope that it will be useful, but      *
* WITHOUT ANY WARRANTY; without even the implied warranty of               *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
* See the GNU Lesser General Public License for more details.              *
* You should have received a copy of the GNU Lesser General Public License *
* along with this library; if not, write to the Free Software Foundation,  *
* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
\**************************************************************************/

/* $Id$ */

require_once(PHPGW_API_INC.'/class.phpmailer.inc.php');

/**
 * New eGW send-class. It implements the old interface (msg-method) on top of PHPMailer.
 *
 * The configuration is read from Admin >> Site configuration and it does NOT depend on one of the email-apps anymore.
 *
 * @author RalfBecker@outdoor-training.de
 */
class send extends PHPMailer 
{
	var $err    = array();
	var $to_res = array();
	
	/**
	 * eGW specific initialisation of the PHPMailer: charset, language, smtp-host, ...
	 *
	 * To be able to call PHPMailer's Send function, we check if a subject, body or address is set and call it in that case,
	 * else we do our constructors work.
	 */
	function send()
	{
		if ($this->Subject || $this->Body || count($this->to))
		{
			return PHPMailer::Send();
		}
		$this->CharSet = $GLOBALS['phpgw']->translation->charset();
		list($lang,$nation) = explode('-',$GLOBALS['phpgw_info']['user']['preferences']['common']['lang']);
		$lang_path = PHPGW_SERVER_ROOT.'/phpgwapi/setup/';
		if ($nation && file_exists($lang_path."phpmailer.lang-$nation.php"))	// atm. only for pt-br => br
		{
			$lang = $nation;
		}
		$this->SetLanguage($lang,$lang_path);
		
		$this->IsSmtp();
		$this->Host = $GLOBALS['phpgw_info']['server']['smtp_server']?$GLOBALS['phpgw_info']['server']['smtp_server']:'localhost';
		$this->Port = $GLOBALS['phpgw_info']['server']['smtp_port']?$GLOBALS['phpgw_info']['server']['smtp_port']:25;
		
		$this->Hostname = $GLOBALS['phpgw_info']['server']['hostname'];
	}
		
	/**
	 * Reset all Settings to send multiple Messages
	 */
	function ClearAll()
	{
		$this->err = array();
	
		$this->Subject = $this->Body = $this->AltBody = '';
		$this->IsHTML(False);
		$this->ClearAllRecipients();
		$this->ClearAttachments();
		$this->ClearCustomHeaders();
			
		$this->FromName = $GLOBALS['phpgw_info']['user']['fullname'];
		$this->From = $GLOBALS['phpgw_info']['user']['email'];
		$this->Sender = '';
		
		$this->AddCustomHeader('X-Mailer:eGroupWare (http://www.eGroupWare.org)');
	}
	
	/**
	 * Emulating the old send::msg interface for compatibility with existing code
	 *
	 * You can either use that code or the PHPMailer variables and methods direct.
	 */
	function msg($service, $to, $subject, $body, $msgtype='', $cc='', $bcc='', $from='', $sender='', $content_type='', $boundary='Message-Boundary')
	{
		//echo "<p>send::msg(,to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender','$content_type','$boundary')<pre>$body</pre>\n";
		$this->ClearAll();	// reset everything to its default, we might be called more then once !!!
	
		if ($service != 'email')
		{
			return False;
		}
		if ($from)
		{
			if (preg_match('/"?(.+)"?<(.+)>/',$from,$matches))
			{
				list(,$this->FromName,$this->From) = $matches;
			}
			else
			{
				$this->From = $from;
				$this->FromName = '';
			}
		}
		if ($sender)
		{
			$this->Sender = $sender;
		}
		foreach(array('to','cc','bcc') as $adr)
		{
			if ($$adr)
			{
				if (preg_match_all('/"?(.+)"?<(.+)>,?/',$$adr,$matches))
				{
					$names = $matches[1];
					$addresses = $matches[2];
				}
				else
				{
					$addresses = split('[, ]',$$adr);
					$names = array();
				}					
				$method = 'Add'.($adr == 'to' ? 'Address' : $adr);
	
				foreach($addresses as $n => $address)
				{
					$this->$method($address,$names[$n]);
				}
			}
		}
		if (!empty($msgtype))
		{
			$this->AddCustomHeader('X-eGW-Type: '.$msgtype);
		}
		if ($content_type)
		{
			$this->ContentType = $content_type;
		}
		$this->Subject = $subject;
		$this->Body = $body;
	
		//echo "PHPMailer = <pre>".print_r($this,True)."</pre>\n";
		if (!$this->Send())
		{
			$this->err = array(
				'code' => 1,	// we dont get a numerical code from PHPMailer
				'msg'  => $this->ErrorInfo,
				'desc' => $this->ErrorInfo,
			);
			return False;
		}
		return True;
	}
	
	/**
	 * encode 8-bit chars in subject-line
	 *
	 * This is not needed any more, as it is done be PHPMailer, but older code depend on it.
	 */
	function encode_subject($subject)
	{
		return $subject;
	}
}
