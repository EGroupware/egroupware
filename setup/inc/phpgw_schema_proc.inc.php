<?php
class phpgw_schema_proc
{
	var $m_oTranslator;
	
	function phpgw_schema_proc($dbms)
	{
		include("./inc/phpgw_schema_proc_" . $dbms . ".inc.php");
		eval("\$this->m_oTranslator = new phpgw_schema_proc_$dbms;");
	}
	
	function GenerateScripts($aTables, $bOutputHTML = false)
	{
		if (!is_array($aTables))
			return false;
		
		reset($aTables);
		$sAllTableSQL = "";
		while (list($sTableName, $aTableDef) = each($aTables))
		{
			if ($this->_GetTableSQL($aTableDef, $sTableSQL))
			{
				$sTableSQL = "CREATE TABLE $sTableName (\n$sTableSQL\n)"
					. $this->m_oTranslator->m_sStatementTerminator;
				$sAllTableSQL .= $sTableSQL . "\n\n";
			}
			else
				return false;
		}
		
		if ($bOutputHTML)
			print("<PRE>$sAllTableSQL</PRE><BR><BR>");
		
		return true;
	}
	
	function _GetTableSQL($aTableDef, &$sTableSQL)
	{
		if (!is_array($aTableDef))
			return false;
		
		$sTableSQL = "";
		reset($aTableDef);
		while (list($sFieldName, $aFieldAttr) = each($aTableDef))
		{
			$sFieldSQL = "";
			if ($this->_GetFieldSQL($aFieldAttr, $sFieldSQL))
			{
				if ($sTableSQL != "")
					$sTableSQL .= ",\n";
				
				$sTableSQL .= "$sFieldName $sFieldSQL";
			}
			else
				return false;
		}
		
		return true;
	}
	
	// Get field DDL
	function _GetFieldSQL($aField, &$sFieldSQL)
	{
		if (!is_array($aField))
			return false;
		
		$sType = "";
		$iPrecision = 0;
		$iScale = 0;
		$sDefault = "";
		$bNullable = false;
		
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
		
		return false;
	}	
}
?>
