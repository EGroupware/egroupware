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

class phpgw_schema_proc
{
	var $m_oTranslator;
	var $m_odb;
	var $m_aTables;
	
	function phpgw_schema_proc($dbms)
	{
		include("./inc/phpgw_schema_proc_" . $dbms . ".inc.php");
		eval("\$this->m_oTranslator = new phpgw_schema_proc_$dbms;");
		global $phpgw_setup;
		$this->m_odb = $phpgw_setup->db;
		
		$this->m_aTables = array();
	}
	
	function GenerateScripts($aTables, $bOutputHTML = false)
	{
		if (!is_array($aTables))
			return false;
		
		$this->m_aTables = $aTables;
		
		reset($this->m_aTables);
		$sAllTableSQL = "";
		while (list($sTableName, $aTableDef) = each($this->m_aTables))
		{
			$sSequenceSQL = "";
			if ($this->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL))
			{
				$sTableSQL = "CREATE TABLE $sTableName (\n$sTableSQL\n)"
					. $this->m_oTranslator->m_sStatementTerminator;
				if ($sSequenceSQL != "")
					$sAllTableSQL .= $sSequenceSQL . "\n";
				$sAllTableSQL .= $sTableSQL . "\n\n";
			}
			else
			{
				if ($bOutputHTML)
					print("<br>Failed generating script for <b>$sTableName</b><br>");
				return false;
			}
		}
		
		if ($bOutputHTML)
			print("<PRE>$sAllTableSQL</PRE><BR><BR>");
		
		return true;
	}
	
	function ExecuteScripts($aTables, $bOutputHTML = false)
	{
		if (!is_array($aTables) || !IsSet($this->m_odb))
			return false;
		
		$this->m_aTables = $aTables;
		
		reset($this->m_aTables);
		while (list($sTableName, $aTableDef) = each($this->m_aTables))
		{
			if ($this->CreateTable($sTableName, $aTableDef))
			{
				if ($bOutputHTML)
					echo "<br>Create Table <b>$sTableSQL</b>";
			}
			else
				return false;
		}
		
		return true;
	}
	
	function DropAllTables($aTables, $bOutputHTML = false)
	{
		if (!is_array($aTables) || !IsSet($this->m_odb))
			return false;
		
		$this->m_aTables = $aTables;
		
		reset($this->m_aTables);
		while (list($sTableName, $aTableDef) = each($this->m_aTables))
		{
			if ($this->DropTable($sTableName))
			{
				if ($bOutputHTML)
					echo "<br>Drop Table <b>$sTableSQL</b>";
			}
			else
				return false;
		}
		
		return true;
	}
	
	function DropTable($sTableName)
	{
		return $this->m_oTranslator->DropTable($this, $sTableName);
	}
	
	function DropColumn($sTableName, $aTableDef, $sColumnName, $bCopyData = true)
	{
		return $this->m_oTranslator->DropColumn($this, $sTableName, $aTableDef, $sColumnName, $bCopyData);
	}
	
	function RenameTable($sOldTableName, $sNewTableName)
	{
		return $this->m_oTranslator->RenameTable($this, $sOldTableName, $sNewTableName);
	}
	
	function RenameColumn($sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
	{
		return $this->m_oTranslator->RenameColumn($this, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData);
	}
	
	function AlterColumn($sTableName, $sColumnName, $aColumnDef, $bCopyData = true)
	{
		return $this->m_oTranslator->AlterColumn($this, $sTableName, $sColumnName, $aColumnDef, $bCopyData);
	}
	
	function AddColumn($sTableName, $sColumnName, $aColumnDef)
	{
		return $this->m_oTranslator->AddColumn($this, $sTableName, $sColumnName, $aColumnDef);
	}
	
	function CreateTable($sTableName, $aTableDef)
	{
		return $this->m_oTranslator->CreateTable($this, $sTableName, $aTableDef);
	}
	
	function _GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL)
	{
		if (!is_array($aTableDef))
			return false;
		
		$sTableSQL = "";
		reset($aTableDef["fd"]);
		while (list($sFieldName, $aFieldAttr) = each($aTableDef["fd"]))
		{
			$sFieldSQL = "";
			if ($this->_GetFieldSQL($aFieldAttr, $sFieldSQL))
			{
				if ($sTableSQL != "")
					$sTableSQL .= ",\n";
				
				$sTableSQL .= "$sFieldName $sFieldSQL";
				
				if ($aFieldAttr["type"] == "auto")
				{
					$this->m_oTranslator->GetSequenceSQL($sTableName, $sFieldName, $sSequenceSQL);
					if ($sSequenceSQL != "")
					{
						$sTableSQL .= sprintf(" DEFAULT nextval('%s_%s_seq')", $sTableName, $sFieldName);
					}
				}
			}
			else
				return false;
		}
		
		$sUCSQL = "";
		$sPKSQL = "";
		
		if (count($aTableDef["pk"]) > 0)
		{
			if (!$this->_GetPK($aTableDef["pk"], $sPKSQL))
			{
				if ($bOutputHTML)
					print("<br>Failed getting primary key<br>");
				return false;
			}
		}
		
		if (count($aTableDef["uc"]) > 0)
		{
			if (!$this->_GetUC($aTableDef["uc"], $sUCSQL))
			{
				if ($bOutputHTML)
					print("<br>Failed getting unique constraint<br>");
				return false;
			}
		}
		
		if ($sPKSQL != "")
			$sTableSQL .= ",\n" . $sPKSQL;
		
		if ($sUCSQL != "")
			$sTableSQL .= ",\n" . $sUCSQL;
		
		return true;
	}
	
	// Get field DDL
	function _GetFieldSQL($aField, $sFieldSQL)
	{
		if (!is_array($aField))
			return false;
		
		$sType = "";
		$iPrecision = 0;
		$iScale = 0;
		$sDefault = "";
		$bNullable = true;
		
		reset($aField);
		while (list($sAttr, $vAttrVal) = each($aField))
		{
			switch ($sAttr)
			{
				case "type":
					$sType = $vAttrVal;
					break;
				case "precision":
					$iPrecision = (int)$vAttrVal;
					break;
				case "scale":
					$iScale = (int)$vAttrVal;
					break;
				case "default":
					$sDefault = $vAttrVal;
					break;
				case "nullable":
					$bNullable = $vAttrVal;
					break;
			}
		}
		
		// Translate the type for the DBMS
		if ($this->m_oTranslator->TranslateType($sType, $iPrecision, $iScale, $sFieldSQL))
		{
			if ($bNullable == false)
				$sFieldSQL .= " NOT NULL";
			
			if ($sDefault != "")
			{
				// Get default DDL - useful for differences in date defaults (eg, now() vs. getdate())
				$sTranslatedDefault = $this->m_oTranslator->TranslateDefault($sDefault);
				$sFieldSQL .= " DEFAULT '$sTranslatedDefault'";
			}
			
			return true;
		}
		
		print("<br>Failed to translate field: type[$sType] precision[$iPrecision] scale[$iScale]<br>");
		
		return false;
	}
	
	function _GetPK($aFields, $sPKSQL)
	{
		$sPKSQL = "";
		if (count($aFields) < 1)
			return true;
		
		$sFields = "";
		reset($aFields);
		while (list($key, $sField) = each($aFields))
		{
			if ($sFields != "")
				$sFields .= ",";
			$sFields .= $sField;
		}
		
		$sPKSQL = $this->m_oTranslator->GetPKSQL($sFields);
		
		return true;
	}
	
	function _GetUC($aFields, $sUCSQL)
	{
		$sUCSQL = "";
		if (count($aFields) < 1)
			return true;
		
		$sFields = "";
		reset($aFields);
		while (list($key, $sField) = each($aFields))
		{
			if ($sFields != "")
				$sFields .= ",";
			$sFields .= $sField;
		}
		
		$sUCSQL = $this->m_oTranslator->GetUCSQL($sFields);
		
		return true;
	}
}
?>
