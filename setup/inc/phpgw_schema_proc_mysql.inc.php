<?php
class phpgw_schema_proc_mysql
{
	var $m_sStatementTerminator;
	
	function phpgw_schema_proc_mysql()
	{
		$this->m_sStatementTerminator = ";";
	}
	
	// Return a type suitable for DDL
	function TranslateType($sType, $iPrecision = 0, $iScale = 0, $sTranslated)
	{
		$sTranslated = "";
		switch($sType)
		{
			case "auto":
				$sTranslated = "int(11) auto_increment";
				break;
			case "blob":
				$sTranslated = "blob";
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
				switch ($iPrecision)
				{
					case 4:
						$sTranslated = "float";
						break;
					case 8:
						$sTranslated = "double";
						break;
				}
				break;
			case "int":
				switch ($iPrecision)
				{
					case 2:
						$sTranslated = "smallint";
						break;
					case 4:
						$sTranslated = "int";
						break;
					case 8:
						$sTranslated = "bigint";
						break;
				}
				break;
			case "text":
				$sTranslated = "text";
				break;
			case "timestamp":
				$sTranslated = "datetime";
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
		
		$oProc->m_odb->query("describe $sTableName");
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
		return !!($oProc->m_odb->query("ALTER TABLE $sTableName DROP COLUMN $sColumnName"));
	}
	
	function RenameTable($oProc, $sOldTableName, $sNewTableName)
	{
		return !!($oProc->m_odb->query("ALTER TABLE $sOldTableName RENAME TO $sNewTableName"));
	}
	
	function RenameColumn($oProc, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
	{
		// This really needs testing - it can affect primary keys, and other table-related objects
		// like sequences and such
		if ($oProc->_GetFieldSQL($oProc->m_aTables[$sTableName]["fd"][$sNewColumnName], $sNewColumnSQL))
			return !!($oProc->m_odb->query("ALTER TABLE $sTableName CHANGE $sOldColumnName $sNewColumnName " . $sNewColumnSQL));
		
		return false;
	}
	
	function AlterColumn($oProc, $sTableName, $sColumnName, $aColumnDef, $bCopyData = true)
	{
		if ($oProc->_GetFieldSQL($oProc->m_aTables[$sTableName]["fd"][$sColumnName], $sNewColumnSQL))
			return !!($oProc->m_odb->query("ALTER TABLE $sTableName MODIFY $sColumnName " . $sNewColumnSQL));
		
		return false;
	}
	
	function AddColumn($oProc, $sTableName, $sColumnName, $aColumnDef)
	{
		$oProc->_GetFieldSQL($aColumnDef, $sFieldSQL);
		$query = "ALTER TABLE $sTableName ADD COLUMN $sColumnName $sFieldSQL";
		
		return !!($oProc->m_odb->query($query));
	}
	
	function GetSequenceSQL($sTableName, $sFieldName, $sSequenceSQL)
	{
		$sSequenceSQL = "";
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
