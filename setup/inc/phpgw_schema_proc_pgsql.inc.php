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
	function TranslateType($sType, $iPrecision = 0, $iScale = 0, &$sTranslated)
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
				$sTranslated = "timestamp";
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
	
	function _GetColumns($oProc, $sTableName, &$sColumns, $sDropColumn = '', $sAlteredColumn = '', $sAlteredColumnType = '')
	{
		$sColumns = '';
		$query = "SELECT a.attname FROM pg_attribute a,pg_class b WHERE ";
		$query .= "b.oid=a.attrelid AND a.attnum>0 and b.relname='$sTableName'";
		if ($sDropColumn != "")
			$query .= " AND a.attname != '$sDropColumn'";
		$query .= " ORDER BY a.attnum";
		
		$oProc->m_odb->query($query);
		while ($oProc->m_odb->next_record())
		{
			if ($sColumns != "")
				$sColumns .= ",";
			
			$sFieldName = $oProc->m_odb->f(0);
			$sColumns .= $sFieldName;
			if ($sAlteredColumn == $sFieldName && $sAlteredColumnType != '')
				$sColumns .= '::' . $sAlteredColumnType;
		}
		
		return false;
	}
	
	function _CopyAlteredTable($oProc, &$aTables, $sSource, $sDest)
	{
		$oDB = $oProc->m_odb;
		$oProc->m_odb->query("select * from $sSource");
		while ($oProc->m_odb->next_record())
		{
			$sSQL = "insert into $sDest (";
			for ($i = 0; $i < count($aTables[$sDest]); $i++)
			{
				if ($i > 0)
					$sSQL .= ',';
				
				$sSQL .= $aTables[$sDest]['fd'][$i];
			}
			
			$sSQL .= ') values (';
			for ($i = 0; $i < $oProc->m_odb->num_fields(); $i++)
			{
				if ($i > 0)
					$sSQL .= ',';
				
				if ($oProc->m_odb->f($i) != null)
				{
					switch ($aTables[$sDest]['fd'][$i])
					{
						case "blob":
						case "char":
						case "date":
						case "text":
						case "timestamp":
						case "varchar":
							$sSQL .= "'" . $oProc->m_odb->f($i) . "'";
							break;
						default:
							$sSQL .= $oProc->m_odb->f($i);
					}
				}
				else
					$sSQL .= 'null';
			}
			$sSQL .= ')';
			
			$oDB->query($sSQL);
		}
		
		return true;
	}
	
	function DropTable($oProc, &$aTables, $sTableName)
	{
		return !!($oProc->m_odb->query("DROP TABLE " . $sTableName));
	}
	
	function DropColumn($oProc, &$aTables, $sTableName, $aNewTableDef, $sColumnName, $bCopyData = true)
	{
		if ($bCopyData)
			$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
		
		$this->DropTable($oProc, $aTables, $sTableName);
		
		$oProc->_GetTableSQL($sTableName, $aNewTableDef, $sTableSQL);
		$query = "CREATE TABLE $sTableName ($sTableSQL)";
		if (!$bCopyData)
			return !!($oProc->m_odb->query($query));
		
		$oProc->m_odb->query($query);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns, $sColumnName);
		$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		$bRet = !!($oProc->m_odb->query($query));
		return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . "_tmp"));
	}
	
	function RenameTable($oProc, &$aTables, $sOldTableName, $sNewTableName)
	{
		return !!($oProc->m_odb->query("ALTER TABLE $sOldTableName RENAME TO $sNewTableName"));
	}
	
	function RenameColumn($oProc, &$aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
	{
		// This really needs testing - it can affect primary keys, and other table-related objects
		// like sequences and such
		if ($bCopyData)
			$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
		
		$this->DropTable($oProc, $aTables, $sTableName);
		
		if (!$bCopyData)
			return $this->CreateTable($oProc, $aTables, $sTableName, $oProc->m_aTables[$sTableName], false);
		
		$this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], false);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns);
		$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		
		$bRet = !!($oProc->m_odb->query($query));
		return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . "_tmp"));
	}
	
	function AlterColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef, $bCopyData = true)
	{
		if ($bCopyData)
			$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
		
		$this->DropTable($oProc, $aTables, $sTableName);
		
		if (!$bCopyData)
			return $this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], false);
		
		$this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], false);
		$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns, '', $sColumnName, $aColumnDef['type'] == 'auto' ? 'int4' : $aColumnDef['type']);
		
		// TODO: analyze the type of change and determine if this is used or _CopyAlteredTable
		//$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
		//$bRet = !!($oProc->m_odb->query($query));
		
		$bRet = $this->_CopyAlteredTable($oProc, $aTables, $sTableName . '_tmp', $sTableName);
		
		return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . "_tmp"));
	}
	
	function AddColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef)
	{
		$oProc->_GetFieldSQL($aColumnDef, $sFieldSQL);
		$query = "ALTER TABLE $sTableName ADD COLUMN $sColumnName $sFieldSQL";
		
		return !!($oProc->m_odb->query($query));
	}
	
	function GetSequenceSQL($sTableName, $sFieldName, &$sSequenceSQL)
	{
		$sSequenceSQL = sprintf("CREATE SEQUENCE %s_%s_seq", $sTableName, $sFieldName);
		return true;
	}
	
	function CreateTable($oProc, $aTables, $sTableName, $aTableDef, $bCreateSequence = true)
	{
		if ($oProc->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL))
		{
			// create sequence first since it will be needed for default
			if ($bCreateSequence && $sSequenceSQL != "")
				$oProc->m_odb->query($sSequenceSQL);
			
			$query = "CREATE TABLE $sTableName ($sTableSQL)";
			
			return !!($oProc->m_odb->query($query));
		}
		
		return false;
	}	
}
?>
