<?php
class phpgw_schema_proc_mysql
{
	var $m_sStatementTerminator;
	
	function phpgw_schema_proc_mysql()
	{
		$this->m_sStatementTerminator = ";";
	}
	
	// Return a type suitable for DDL
	function TranslateType($sType, $iPrecision = 0, $iScale = 0, &$sTranslated)
	{
		$sTranslated = "";
		switch($sType)
		{
			case "autoincrement":
				$sTranslated = "auto_increment";
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
			case "timestamp":
				$sTranslated =  "datetime";
			case "varchar":
				if ($iPrecision > 0 && $iPrecision < 256)
					$sTranslated =  sprintf("varchar(%d)", $iPrecision);
				
				if ($iPrecision > 255)
					$sTranslated =  "text";
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
