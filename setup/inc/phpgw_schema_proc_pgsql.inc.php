<?php
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
			case "autoincrement":
				$sTranslated = "serial";
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
}
?>
