<?php
/**
*  
*
* 
**/

class so_cprofile
{
   var $sqldb;

   function so_cprofile()
   {
	  $this->sqldb = $GLOBALS['egw']->db;
   }

   function getegwlangs()
   {
	  $this->sqldb->select('egw_languages','*','',__LINE__,__FILE__);

	  while($this->sqldb->next_record())
	  {
		 $langs[] = $this->sqldb->row();
	  }

	  return $langs;
   }
}

