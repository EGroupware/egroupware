<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id: class.bopreferences.inc.php 23423 2007-02-15 09:44:40Z lkneschke $ */

	require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_signatures.inc.php');
	 
	class felamimail_bosignatures
	{
		function felamimail_bosignatures() {
			$boemailadmin = new emailadmin_bo();
			$this->profileData = $boemailadmin->getUserProfile('felamimail');
		}
		
		function getListOfSignatures() {
			$boemailadmin = new emailadmin_bo();
			$fmSignatures = new felamimail_signatures();
			
			#$profileData = $boemailadmin->getUserProfile('felamimail');

			$systemSignatures = array();
			if(!empty($this->profileData->ea_default_signature)) {
				$systemSignatures[-1] = array(
					'fm_signatureid'	=> -1,
					'fm_description'	=> lang('system signature'),
					'fm_defaultsignature'	=> FALSE,
				);

				if($this->profileData->ea_user_defined_signatures != true) {
					$systemSignatures[-1]['fm_defaultsignature'] = TRUE;
				}
			}
			// return only systemsignature, if no user defined signatures are enabled
			if($this->profileData->ea_user_defined_signatures != true) {
				return $systemSignatures;
			}
			
			$signatures = $fmSignatures->search();
			
			if(count($signatures) == 0 && 
				!isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig_copied']) &&
				!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig'])) {
				
				$GLOBALS['egw']->preferences->read_repository();
				$newSignature = new felamimail_signatures();
				$newSignature->fm_description		= lang('default signature');
				$newSignature->fm_signature		= nl2br($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig']);
				$newSignature->fm_defaultsignature	= true;
				$newSignature->save();
				$GLOBALS['egw']->preferences->add('felamimail', 'email_sig_copied', true);
				$GLOBALS['egw']->preferences->save_repository();
				
				$signatures = $fmSignatures->search();
			}

			// make systemsignature the default, if no other signature is defined as default signature			
			if($fmSignatures->getDefaultSignature() === false) {
				$systemSignatures[-1]['fm_defaultsignature'] = TRUE;
			}
			
			$signatures = array_merge($systemSignatures, $signatures);
			#_debug_array($signatures);
			return $signatures;
		}
		
		function getSignature($_signatureID, $_unparsed = false) 
		{
			if($_signatureID == -1) {
				
				$systemSignatureIsDefaultSignature = $this->getDefaultSignature();

				$signature = new felamimail_signatures();
				$signature->fm_signatureid	= -1;
				$signature->fm_description	= 'eGroupWare '. lang('default signature');
				$signature->fm_signature	= ($_unparsed === true ? $this->profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($this->profileData->ea_default_signature));
				$signature->fm_defaultsignature = $systemSignatureIsDefaultSignature;
				
				return $signature;
				
			} else {
				require_once('class.felamimail_signatures.inc.php');
				$signature = new felamimail_signatures($_signatureID);
				if($_unparsed === false) {
					$signature->fm_signature = ($_unparsed === true ? $this->profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($signature->fm_signature));
				}
				return $signature;
			}
		}
		
		function getDefaultSignature($accountID = NULL) 
		{
			$signature = new felamimail_signatures();
			return $signature->getDefaultSignature();
			#return parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);
		}
		
		function deleteSignatures($_signatureID) 
		{
			if(!is_array($_signatureID)) {
				return false;
			}
			$signature = new felamimail_signatures();
			foreach($_signatureID as $signatureID) {
				#error_log("$signatureID");
				$signature->delete($signatureID);
			}
			#return parent::deleteSignatures($GLOBALS['egw_info']['user']['account_id'], $_signatureID);
		}
		
		function saveSignature($_signatureID, $_description, $_signature, $_isDefaultSignature) 
		{
			if($_signatureID == -1) {
				// the systemwide profile
				// can only be made the default profile
				
				return -1;
			} else {
				if($this->profileData->ea_user_defined_signatures == false) {
					return false;
				}
				
				$signature = new felamimail_signatures();
				
				$signature->fm_description	= $_description;
				$signature->fm_signature	= $_signature;
				$signature->fm_defaultsignature	= (bool)$_isDefaultSignature;
				if((int)$_signatureID > 0) {
					$signature->fm_signatureid = (int)$_signatureID;
				}
				
				$signature->save();
				
				return $signature->fm_signatureid;
			}
		}

	}
?>
