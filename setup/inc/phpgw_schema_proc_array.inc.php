<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

class phpgw_schema_proc_array
{
	var $m_sStatementTerminator;
	
	function phpgw_schema_proc_array()
	{
		$this->m_sStatementTerminator = ";";
	}
	
	// Return a type suitable for DDL abstracted array
	function TranslateType($sType, $iPrecision = 0, $iScale = 0, $sTranslated)
	{
		$sTranslated = $sType;
		return (strlen($sTranslated) > 0);
	}
	
	function TranslateDefault($sDefault)
	{
		return $sDefault;
	}
	
	function GetPKSQL($sFields)
	{
		return "";
	}
	
	function GetUCSQL($sFields)
	{
		return "";
	}
	
	function _GetColumns($oProc, $sTableName, $sColumns, $sDropColumn = "")
	{
		$sColumns = "";
		while (list($sName, $aJunk) = each($oProc->m_aTables[$sTableName]["fd"]))
		{
			if ($sColumns != "")
				$sColumns .= ",";
			$sColumns .= $sName;
		}
		
		return true;
	}
	
	function DropTable($oProc, $sTableName)
	{
		if (IsSet($oProc->m_aTables[$sTableName]))
			UnSet($oProc->m_aTables[$sTableName]);
		
		return true;
	}
	
	function DropColumn($oProc, $sTableName, $aNewTableDef, $sColumnName, $bCopyData = true)
	{
		if (IsSet($oProc->m_aTables[$sTableName]))
		{
			if (IsSet($oProc->m_aTables[$sTableName]["fd"][$sColumnName]))
				UnSet($oProc->m_aTables[$sTableName]["fd"][$sColumnName]);
		}
		
		return true;
	}
	
	function RenameTable($oProc, $sOldTableName, $sNewTableName)
	{
		$aNewTables = array();
		while (list($sTableName, $aTableDef) = each($oProc->m_aTables))
		{
			if ($sTableName == $sOldTableName)
				$aNewTables[$sNewTableName] = $aTableDef;
			else
				$aNewTables[$sTableName] = $aTableDef;
		}
		
		$oProc->m_aTables = $aNewTables;
		
		return true;
	}
	
	function RenameColumn($oProc, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
	{
		if (IsSet($oProc->m_aTables[$sTableName]))
		{
			$aNewTableDef = array();
			reset($oProc->m_aTables[$sTableName]["fd"]);
			while (list($sColumnName, $aColumnDef) = each($oProc->m_aTables[$sTableName]["fd"]))
			{
				if ($sColumnName == $sOldColumnName)
					$aNewTableDef[$sNewColumnName] = $aColumnDef;
				else
					$aNewTableDef[$sColumnName] = $aColumnDef;
			}
			
			$oProc->m_aTables[$sTableName]["fd"] = $aNewTableDef;
			
			reset($oProc->m_aTables[$sTableName]["pk"]);
			while (list($key, $sColumnName) = each($oProc->m_aTables[$sTableName]["pk"]))
			{
				if ($sColumnName == $sOldColumnName)
					$oProc->m_aTables[$sTableName]["pk"][$key] = $sNewColumnName;
			}
			
			reset($oProc->m_aTables[$sTableName]["uc"]);
			while (list($key, $sColumnName) = each($oProc->m_aTables[$sTableName]["uc"]))
			{
				if ($sColumnName == $sOldColumnName)
					$oProc->m_aTables[$sTableName]["uc"][$key] = $sNewColumnName;
			}
		}
		
		return true;
	}
	
	function AlterColumn($oProc, $sTableName, $sColumnName, $aColumnDef, $bCopyData = true)
	{
		if (IsSet($oProc->m_aTables[$sTableName]))
		{
			if (IsSet($oProc->m_aTables[$sTableName]["fd"][$sColumnName]))
				$oProc->m_aTables[$sTableName]["fd"][$sColumnName] = $aColumnDef;
		}
		
		return true;
	}
	
	function AddColumn($oProc, $sTableName, $sColumnName, $aColumnDef)
	{
		if (IsSet($oProc->m_aTables[$sTableName]))
		{
			if (!IsSet($oProc->m_aTables[$sTableName]["fd"][$sColumnName]))
				$oProc->m_aTables[$sTableName]["fd"][$sColumnName] = $aColumnDef;
		}
		
		return true;
	}
	
	function CreateTable($oProc, $sTableName, $aTableDef)
	{
		if (!IsSet($oProc->m_aTables[$sTableName]))
			$oProc->m_aTables[$sTableName] = $aTableDef;
		
		return true;
	}	
}
?>
