<?php

error_reporting (8);

/* Any initializations that need to be done */
function db_init ()
{
	global $phpgw;
	global $phpgw_info;

	$phpgw->db->Auto_Free = 0;
}

/* General SQL query */
function db_query ($query)
{
	global $phpgw;
	global $phpgw_info;

	return $phpgw->db->query ($query);
}

/* Fetch next array for $query_id */
function db_fetch_array ($query_id)
{
	global $phpgw;
	global $phpgw_info;

//	$phpgw->db->Query_ID = $query_id;
	$phpgw->db->next_record ();
	return $phpgw->db->Record;
}

/*
   General wrapper for all other db calls
   Calls in here are simply returned, so not all will work
*/
function db_call ($function, $query_id)
{
	global $phpgw;
	global $phpgw_info;

//	$phpgw->db->Query_ID = $query_id;
	return $phpgw->db->$function ();
}

?>
