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

class phpgw_schema_proc_pgsql
{
	var $m_sStatementTerminator;
	
	function phpgw_schema_proc_pgsql()
	{
		$this->m_sStatementTerminator = ";";
	}
	
	// Return a type suitable for DDL
	function TranslateType($sType, $iPrecision = 0, $iScale = 0, $sTranslated)
	{
		switch($sType)
		{
			case "auto":
				$sTranslated = "int4";
				break;
			case "blob":
				$sTranslated = "text";
				break;
			case "char":
				if ($iPrecision > 0 && $iPrecision < 256)
					$sTranslated =  sprintf("char(%d)", $iPrecision);
				
				if ($iPrecision > 255)
					$sTranslated =  "text";
				
				break;
			case "date":
				$sTranslated =  "date";
				break;
			case "decimal":
				$sTranslated =  sprintf("decimal(%d,%d)", $iPrecision, $iScale);
				break;
			case "float":
				if ($iPrecision == 4 || $iPrecision == 8)
					$sTranslated =  sprintf("float%d", $iPrecision);
				
				break;
			case "int":
				if ($iPrecision == 2 || $iPrecision == 4 || $iPrecision == 8)
					$sTranslated =  sprintf("int%d", $iPrecision);
				
				break;
			case "text":
				$sTranslated = "text";
				break;
			case "timestamp":
				$sTranslated =  "timestamp";
				break;
			case "varchar":
				if ($iPrecision > 0 && $iPrecision < 256)
					$sTranslated =  sprintf("varchar(%d)", $iPrecision);
				
				if ($iPrecision > 255)
					$sTranslated =  "text";
				
				break;
		}
		
		return (strlen($sTranslated) > 0);
	}
	
	function TranslateDefault($sDefault)
	{
		switch ($sDefault)
		{
			case "current_date":
			case "current_timestamp":
				return "now";
		}
		
		return $sDefault;
	}
	
	function GetPKSQL($sFields)
	{
		return "PRIMARY KEY($sFields)";
	}
	
	function GetUCSQL($sFields)
	{
		return "UNIQUE($sFields)";
	}
	
	function _GetColumns($oProc, $sTableName, $sColumns, $sDropColumn = "")
	{
		$sColumns = "";
		$query = "SELECT a.attname FROM pg_attribute a,pg_class b WHERE ";
		$query .= "a.oid=b.attrelid AND a.attnum>0 and b.relname='$sTableName'";
		if ($sDropColumn != "")
			$query .= " AND a.attname != '$sDropColumn'";
		$query .= " ORDER BY a.attnum";
		
		$oProc->m_odb->query($query);
		while ($oProc->m_odb->next_record())
		{
			if ($sColumns != "")
				$sColumns .= ",";
			$sColumns .= $oProc->m_odb->f(0);
		}
		
		return false;
	}
	
	function DropTable($oProc, $sTableName)
	{
		return !!($oProc->m_odb->query("DROP TABLE " . $sTableName));
	}
	
	function DropColumn($oProc, $sTableName, $aNewTableDef, $sColumnName, $bCopyData = true)
	{
		if ($bCopyData)
			$oProc->m_odb->query("ALTER TABLE $sTableName RENAME TO $sTableName" . "_tmp");
		else
			$this->DropTable($oProc, $sTableName);
		
		$oProc->_GetTableSQL($sTableName, $aNewTableDef, $sTableSQL);
		$query = "CREATE TABLE $sTableName ($sTableSQL)";
		if (!$bCopyData)
			return !!($oProc->m_odb->query($query));
		
		$oProc->m_odb->query($query);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns, $sColumnName);
		$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		return !!($oProc->m_odb->query($query));
	}
	
	function RenameTable($oProc, $sOldTableName, $sNewTableName)
	{
		return !!($oProc->m_odb->query("ALTER TABLE $sOldTableName RENAME TO $sNewTableName"));
	}
	
	function RenameColumn($oProc, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
	{
		// This really needs testing - it can affect primary keys, and other table-related objects
		// like sequences and such
		if ($bCopyData)
			$oProc->m_odb->query("ALTER TABLE $sTableName RENAME TO $sTableName" . "_tmp");
		else
			$this->DropTable($oProc, $sTableName);
		
		if (!$bCopyData)
			return $this->CreateTable($oProc, $sTableName, $oProc->m_aTables[$sTableName]);
		
		$this->CreateTable($oProc, $sTableName, $oProc->m_aTables[$sTableName]);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns);
		$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		
		return !!($oProc->m_odb->query($query));
	}
	
	function AlterColumn($oProc, $sTableName, $sColumnName, $aColumnDef, $bCopyData = true)
	{
		if ($bCopyData)
			$oProc->m_odb->query("ALTER TABLE $sTableName RENAME TO $sTableName" . "_tmp");
		else
			$this->DropTable($oProc, $sTableName);
		
		if (!$bCopyData)
			return $this->CreateTable($oProc, $sTableName, $oProc->m_aTables[$sTableName]);
		
		$this->CreateTable($oProc, $sTableName, $oProc->m_aTables[$sTableName]);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns);
		$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		
		return !!($oProc->m_odb->query($query));
	}
	
	function AddColumn($oProc, $sTableName, $sColumnName, $aColumnDef)
	{
		$oProc->_GetFieldSQL($aColumnDef, $sFieldSQL);
		$query = "ALTER TABLE $sTableName ADD COLUMN $sColumnName $sFieldSQL";
		
		return !!($oProc->m_odb->query($query));
	}
	
	function GetSequenceSQL($sTableName, $sFieldName, $sSequenceSQL)
	{
		$sSequenceSQL = sprintf("CREATE SEQUENCE %s_%s_seq", $sTableName, $sFieldName);
		return true;
	}
	
	function CreateTable($oProc, $sTableName, $aTableDef)
	{
		if ($oProc->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL))
		{
			// create sequence first since it will be needed for default
			if ($sSequenceSQL != "")
				$oProc->m_odb->query($sSequenceSQL);
			
			$query = "CREATE TABLE $sTableName ($sTableSQL)";
			return !!($oProc->m_odb->query($query));
		}
		
		return false;
	}	
}
?>
