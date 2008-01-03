<?php
   /**
   *  
   *  class.so_oscadminapi.inc.php
   *
   *
   *
   **/

   class so_oscadminapi
   {
	  var $ses_token;
	  var $sessiondata;

	  function so_oscadminapi()
	  {
		 // constructor
		 $this->load();
		 $this->user_id = $GLOBALS['egw_info']['user']['account_id'];

	  }


	  function load()
	  {
		 $this->sessiondata = $GLOBALS['phpgw']->session->appsession('session_data','oscadminapi');
	  }

	  function save_session()
	  {
		 if(count($this->sessiondata) > 0) //this catches the bug in the phpgwapi crypto class..
		 {
			$GLOBALS['phpgw']->session->appsession('session_data','oscadminapi',$this->sessiondata);
		 }
	  }

	  function save_token($token)
	  {
		 $GLOBALS['phpgw']->session->appsession('sestoken','oscadminapi',$token);
	  }

	  function load_token()
	  { 
		 return $GLOBALS['phpgw']->session->appsession('sestoken','oscadminapi');
	  }
	  
	  function getPersonalData($id)
	  {
		 $account =& CreateObject('phpgwapi.accounts',(int)$id,'u');
		 $contact = $GLOBALS['egw']->contacts =& CreateObject('phpgwapi.contacts');
		 $userData = $account->read_repository();
		 $c_arr = $contact->read($userData['person_id']);
		 #_Debug_array($userData);
		 #_Debug_array($c_arr);

		 return array_merge($userData,$c_arr);
	  }

   }
?>
