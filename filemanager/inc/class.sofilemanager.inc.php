<?php

	class sofilemanager
	{

		/* Any initializations that need to be done */
		function sofilemanager()
		{
			$GLOBALS['phpgw']->db->Auto_Free = 0;
		}

		/* General SQL query */
		function query($query)
		{
			return $GLOBALS['phpgw']->db->query($query);
		}

		/* Fetch next array for $query_id */
		function fetch_array($query_id)
		{
			$GLOBALS['phpgw']->db->next_record ();
			return $GLOBALS['phpgw']->db->Record;
		}

		/*
		   General wrapper for all other db calls
		   Calls in here are simply returned, so not all will work
		*/
		function call($function, $query_id)
		{
			return $GLOBALS['phpgw']->db->$function();
		}
	}
?>
