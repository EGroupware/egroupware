<?php

	class sofilemanager
	{
		var $db;
//		var $phpgw;
//		var $phpgw_info;

		function sofilemanager()
		{
			$this->db = $GLOBALS['phpgw']->db;
//			$this->phpgw=$GLOBALS[phpgw];
//			$this->phpgw_info=$GLOBALS[phpgw_info];
		}

		/* Any initializations that need to be done */
		function db_init ()
		{
			//global $phpgw;
			//global $phpgw_info;

			$this->db->Auto_Free = 0;
		}

		/* General SQL query */
		function db_query ($query)
		{
		//	global $phpgw;
	//		global $phpgw_info;

			return $this->db->query ($query);
		}

		/* Fetch next array for $query_id */
		function db_fetch_array ($query_id)
		{
//			global $phpgw;
//			global $phpgw_info;

			//	$phpgw->db->Query_ID = $query_id;
			$this->db->next_record ();
			return $this->db->Record;
		}

		/*
		General wrapper for all other db calls
		Calls in here are simply returned, so not all will work
		*/
		function db_call ($function, $query_id)
		{
//			global $phpgw;
//			global $phpgw_info;

			//	$phpgw->db->Query_ID = $query_id;
			return $this->db->$function ();
		}


	}
?>
