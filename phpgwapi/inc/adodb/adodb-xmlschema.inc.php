<?PHP
// Copyright (c) 2003 ars Cognita Inc., all rights reserved
 /*******************************************************************************
    Released under both BSD license and Lesser GPL library license. 
 	Whenever there is any discrepancy between the two licenses, 
 	the BSD license will take precedence. 
*******************************************************************************/
/**
 * xmlschema is a class that allows the user to quickly and easily
 * build a database on any ADOdb-supported platform using a simple
 * XML schema.
 *
 * @author Richard Tango-Lowy
 * @version $Revision$
 * @package xmlschema
 */
 
/**
  * Include the main ADODB library
  */
if (!defined( '_ADODB_LAYER' ) ) {
	require( dirname(__FILE__).'/adodb.inc.php' );
}

/**
 * Maximum length allowed for object prefix
 */
define( 'XMLS_PREFIX_MAXLEN', 10 );


/**
 * Creates a table object in ADOdb's datadict format
 *
 * This class stores information about a database table. As charactaristics
 * of the table are loaded from the external source, methods and properties
 * of this class are used to build up the table description in ADOdb's
*  datadict format.
 *
 * @access private
 */
class dbTable {
	
	/**
	* @var string	Table name
	*/
	var $tableName;
	
	/**
	* @var array	Field specifier: Meta-information about each field
	*/
	var $fieldSpec;
	
	/**
	* @var array	Table options: Table-level options
	*/
	var $tableOpts;
	
	/**
	* @var string	Field index: Keeps track of which field is currently being processed
	*/
	var $currentField;
	
	/**
	* @var string If set (to 'ALTER' or 'REPLACE'), upgrade an existing database
	* @access private
	*/
	var $upgradeMethod;
	
	/**
	* Constructor. Iniitializes a new table object.
	*
	* If the table already exists, there are two methods available to upgrade it.
	* To upgrade an existing table the new schema by ALTERing the table, set the upgradeTable
	* argument to "ALTER."  To force the new table to replace the current table, set the upgradeTable
	* argument to "REPLACE."
	*
	* @param string	$name		Table name
	* @param string	$upgradeTable		Upgrade method (NULL, ALTER, or REPLACE)
	*/
	function dbTable( $name, $upgradeTable = NULL ) {
		$this->tableName = $name;

		// If upgrading, set the upgrade method
		if( isset( $upgradeTable ) ) {
			$upgradeTable = strtoupper( $upgradeTable );
			if( $upgradeTable == 'ALTER' or $upgradeTable == 'REPLACE' ) {
				$this->upgradeMethod = strtoupper( $upgradeTable );
				print "<P>Upgrading table '$name' using {$this->upgradeMethod}</P>";
			} else {
				unset( $this->upgradeMethod );
			}
		} else {
			print "<P>Creating table '$name'</P>";
		}
	}
	
	/**
	* Adds a field to a table object
	*
	* $name is the name of the table to which the field should be added. 
	* $type is an ADODB datadict field type. The following field types
	* are supported as of ADODB 3.40:
	* 	- C:  varchar
	*	- X:  CLOB (character large object) or largest varchar size
	*	   if CLOB is not supported
	*	- C2: Multibyte varchar
	*	- X2: Multibyte CLOB
	*	- B:  BLOB (binary large object)
	*	- D:  Date (some databases do not support this, and we return a datetime type)
	*	- T:  Datetime or Timestamp
	*	- L:  Integer field suitable for storing booleans (0 or 1)
	*	- I:  Integer (mapped to I4)
	*	- I1: 1-byte integer
	*	- I2: 2-byte integer
	*	- I4: 4-byte integer
	*	- I8: 8-byte integer
	*	- F:  Floating point number
	*	- N:  Numeric or decimal number
	*
	* @param string $name	Name of the table to which the field will be added.
	* @param string $type		ADODB datadict field type.
	* @param string $size		Field size
	* @param array $opts		Field options array
	* @return array	Field specifier array
	*/
	function addField( $name, $type, $size = NULL, $opts = NULL ) {
	
		// Set the field index so we know where we are
		$this->currentField = $name;
		
		// Set the field type (required)
		$this->fieldSpec[$name]['TYPE'] = $type;
		
		// Set the field size (optional)
		if( isset( $size ) ) {
			$this->fieldSpec[$name]['SIZE'] = $size;
		}
		
		// Set the field options
		if( isset( $opts ) ) $this->fieldSpec[$name]['OPTS'] = $opts;
		
		// Return array containing field specifier
		return $this->fieldSpec;
	}
	
	/**
	* Adds a field option to the current field specifier
	*
	* This method adds a field option allowed by the ADOdb datadict 
	* and appends it to the given field.
	*
	* @param string $field		Field name
	* @param string $opt		ADOdb field option
	* @param mixed $value	Field option value
	* @return array	Field specifier array
	*/
	function addFieldOpt( $field, $opt, $value = NULL ) {
		
		// Add the option to the field specifier
		if(  $value === NULL ) { // No value, so add only the option
			$this->fieldSpec[$field]['OPTS'][] = $opt;
		} else { // Add the option and value
			$this->fieldSpec[$field]['OPTS'][] = array( "$opt" => "$value" );
		}
	
		// Return array containing field specifier
		return $this->fieldSpec;
	}
	
	/**
	* Adds an option to the table
	*
	*This method takes a comma-separated list of table-level options
	* and appends them to the table object.
	*
	* @param string $opt		Table option
	* @return string	Option list
	*/
	function addTableOpt( $opt ) {
	
		$optlist = &$this->tableOpts;
		$optlist ? $optlist .= ", $opt" : $optlist = $opt;
		
		// Return the options list
		return $optlist;
	}
	
	/**
	* Generates the SQL that will create the table in the database
	*
	* Returns SQL that will create the table represented by the object.
	*
	* @param object $dict		ADOdb data dictionary
	* @return array	Array containing table creation SQL
	*/
	function create( $dict ) {
	
		// Loop through the field specifier array, building the associative array for the field options
		$fldarray = array();
		$i = 0;
		
		foreach( $this->fieldSpec as $field => $finfo ) {
			$i++;
			
			// Set an empty size if it isn't supplied
			if( !isset( $finfo['SIZE'] ) ) $finfo['SIZE'] = '';
			
			// Initialize the field array with the type and size
			$fldarray[$i] = array( $field, $finfo['TYPE'], $finfo['SIZE'] );
			
			// Loop through the options array and add the field options. 
			if( isset( $finfo['OPTS'] ) ) {
				foreach( $finfo['OPTS'] as $opt ) {
					
					if( is_array( $opt ) ) { // Option has an argument.
						$key = key( $opt );
						$value = $opt[key( $opt ) ];
						$fldarray[$i][$key] = $value;
					} else { // Option doesn't have arguments
						array_push( $fldarray[$i], $opt );
					}
				}
			}
		}
		
		// Check for existing table
		$legacyTables = $dict->MetaTables();
		if( is_array( $legacyTables ) and count( $legacyTables > 0 ) ) {
			foreach( $dict->MetaTables() as $table ) {
				$this->legacyTables[ strtoupper( $table ) ] = $table;
			}
			if( in_array( strtoupper( $tableName ), $legacyTables ) ) {
				$existingTableName = $legacyTables[strtoupper( $tableName )];
			}
		} 
		// Build table array
		if( !isset( $this->upgradeMethod ) or !isset( $existingTableName ) ) {
			// Create the new table
			$sqlArray = $dict->CreateTableSQL( $this->tableName, $fldarray, $this->tableOpts );
			print "<P>Generated create table SQL</P>";
		} else {
			// Upgrade an existing table
			switch( $this->upgradeMethod ) {
				case 'ALTER':
					// Use ChangeTableSQL
					print "<P>Generated ALTER table SQL</P>";
					$sqlArray = $dict->ChangeTableSQL( $this->tableName, $fldarray, $this->tableOpts );
					break;
				case 'REPLACE':
					$this->replace( $dict );
					break;
				default:
			}
		}
		
		// Return the array containing the SQL to create the table
		return $sqlArray;
	}
	
	/**
	* Generates the SQL that will replace an existing table in the database
	*
	* Returns SQL that will replace the table represented by the object.
	*
	* @return array	Array containing table replacement SQL
	*/
	function replace( $dict ) {
		
		$oldTable = $this->tableName;
		$tempTable= "xmls_" . $this->tableName;
		
		// Create the new table
		$sqlArray = $dict->CreateTableSQL( $tempTable, $fldarray, $this->tableOpts );
		
		switch( $dict->dataProvider ) {
			case "posgres7":
				break;
			case "mysql":
				break;
			default:
		}
	}
	
	/**
	* Destructor
	*/
	function destroy() {
		unset( $this );
	}
}

/**
* Creates an index object in ADOdb's datadict format
*
* This class stores information about a database index. As charactaristics
* of the index are loaded from the external source, methods and properties
* of this class are used to build up the index description in ADOdb's
* datadict format.
*
* @access private
*/
class dbIndex {
	
	/**
	* @var string	Index name
	*/
	var $indexName;
	
	/**
	* @var array	Index options: Index-level options
	*/
	var $indexOpts;

	/**
	* @var string	Name of the table this index is attached to
	*/
	var $tableName;
	
	/**
	* @var array	Indexed fields: Table columns included in this index
	*/
	var $fields;
	
	/**
	* Constructor. Initialize the index and table names.
	*
	* @param string $name	Index name
	* @param string $table		Name of indexed table
	*/
	function dbIndex( $name, $table ) {
		$this->indexName = $name;
		$this->tableName = $table;
	}
	
	/**
	* Adds a field to the index
	* 
	* This method adds the specified column to an index.
	*
	* @param string $name		Field name
	* @return string	Field list
	*/
	function addField( $name ) {
		$fieldlist = &$this->fields;
		$fieldlist ? $fieldlist .=" , $name" : $fieldlist = $name;
		
		// Return the field list
		return $fieldlist;
	}
	
	/**
	* Adds an option to the index
	*
	*This method takes a comma-separated list of index-level options
	* and appends them to the index object.
	*
	* @param string $opt		Index option
	* @return string	Option list
	*/
	function addIndexOpt( $opt ) {

		$optlist = &$this->indexOpts;
		$optlist ? $optlist .= ", $opt" : $optlist = $opt;

		// Return the options list
		return $optlist;
	}

	/**
	* Generates the SQL that will create the index in the database
	*
	* Returns SQL that will create the index represented by the object.
	*
	* @param object $dict	ADOdb data dictionary object
	* @return array	Array containing index creation SQL
	*/
	function create( $dict ) {
	
		if (isset($this->indexOpts) ) {
			// CreateIndexSQL requires an array of options.
			$indexOpts_arr = explode(",",$this->indexOpts);
		} else {
			$indexOpts_arr = NULL;
		}

		// Build table array
		$sqlArray = $dict->CreateIndexSQL( $this->indexName, $this->tableName, $this->fields, $indexOpts_arr );
		
		// Return the array containing the SQL to create the table
		return $sqlArray;
	}
	
	/**
	* Destructor
	*/
	function destroy() {
		unset( $this );
	}
}

/**
* Creates the SQL to execute a list of provided SQL queries
*
* This class compiles a list of SQL queries specified in the external file.
*
* @access private
*/
class dbQuerySet {
	
	/**
	* @var array	List of SQL queries
	*/
	var $querySet;
	
	/**
	* @var string	String used to build of a query line by line
	*/
	var $query;
	
	/**
	* Constructor. Initializes the queries array
	*/
	function dbQuerySet() {
		$this->querySet = array();
		$this->query = '';
	}
	
	/** 
	* Appends a line to a query that is being built line by line
	*
	* $param string $data	Line of SQL data or NULL to initialize a new query
	*/
	function buildQuery( $data = NULL ) {
		isset( $data ) ? $this->query .= " " . trim( $data ) : $this->query = '';
	}
	
	/**
	* Adds a completed query to the query list
	*
	* @return string	SQL of added query
	*/
	function addQuery() {
		
		// Push the query onto the query set array
		$finishedQuery = $this->query;
		array_push( $this->querySet, $finishedQuery );
		
		// Return the query set array
		return $finishedQuery;
	}
	
	/**
	* Creates and returns the current query set
	*
	* @return array Query set
	*/
	function create() {
		return $this->querySet;
	}
	
	/**
	* Destructor
	*/
	function destroy() {
		unset( $this );
	}
}


/**
* Loads and parses an XML file, creating an array of "ready-to-run" SQL statements
* 
* This class is used to load and parse the XML file, to create an array of SQL statements
* that can be used to build a database, and to build the database using the SQL array.
*
* @package xmlschema
*/
class adoSchema {
	
	/**
	* @var array	Array containing SQL queries to generate all objects
	*/
	var $sqlArray;
	
	/**
	* @var object	XML Parser object
	* @access private
	*/
	var $xmlParser;
	
	/**
	* @var object	ADOdb connection object
	* @access private
	*/
	var $dbconn;
	
	/**
	* @var string	Database type (platform)
	* @access private
	*/
	var $dbType;
	
	/**
	* @var object	ADOdb Data Dictionary
	* @access private
	*/
	var $dict;
	
	/**
	* @var object	Temporary dbTable object
	* @access private
	*/
	var $table;
	
	/**
	* @var object	Temporary dbIndex object
	* @access private
	*/
	var $index;
	
	/**
	* @var object	Temporary dbQuerySet object
	* @access private
	*/
	var $querySet;
	
	/**
	* @var string Current XML element
	* @access private
	*/
	var $currentElement;
	
	/**
	* @var string If set (to 'ALTER' or 'REPLACE'), upgrade an existing database
	* @access private
	*/
	var $upgradeMethod;
	
	/**
	* @var mixed Existing tables before upgrade
	* @access private
	*/
	var $legacyTables;
	
	/**
	* @var string Optional object prefix
	* @access private
	*/
	var $objectPrefix;
	
	/**
	* @var long	Original Magic Quotes Runtime value
	* @access private
	*/
	var $mgq;
	
	/**
	* @var long	System debug
	* @access private
	*/
	var $debug;
	
	/**
	* Initializes the xmlschema object.
	*
	* adoSchema provides methods to parse and process the XML schema file, and is called automatically
	* when an xmlschema object is instantiated. 
	* 
	* The dbconn argument is a database connection object created by ADONewConnection. 
	* Set upgradeSchema to TRUE to upgrade an existing database to the provided schema. By default,
	*  adoSchema will attempt to upgrade tables by ALTERing them on the fly. Upgrading has only been tested
	* on the MySQL platform. It is know NOT to work on PostgreSQL. The forceReplace flag is not currently
	* implemented.
	*
	* @param object $dbconn		ADOdb connection object
	* @param object $upgradeSchema	Upgrade the database
	* @param object $forceReplace	If upgrading, REPLACE tables (**NOT IMPLEMENTED**)
	*/
	function adoSchema( &$dbconn, $upgradeSchema = FALSE, $forceReplace = FALSE ) {
		
		// Initialize the environment
		$this->mgq = get_magic_quotes_runtime();
		set_magic_quotes_runtime(0);	
		
		$this->dbconn	=  &$dbconn;
		$this->dbType = $dbconn->databaseType;
		$this->sqlArray = array();
		$this->debug = $this->dbconn->debug;
		
		// Create an ADOdb dictionary object
		$this->dict = NewDataDictionary( $dbconn );

		// If upgradeSchema is set, we will be upgrading an existing database to match
		// the provided schema. If forceReplace is set, objects are marked for replacement
		// rather than alteration.
		if( $upgradeSchema == TRUE ) {
			
			// Get the metadata from existing tables
			$legacyTables = $this->dict->MetaTables();
			if( is_array( $legacyTables ) and count( $legacyTables > 0 ) ) {
				foreach( $this->dict->MetaTables() as $table ) {
					$this->legacyTables[ strtoupper( $table ) ] = $table;
				}
				showDebug( $this->legacyTables, "LEGACY table" );
			} 
			
			$forceReplace == TRUE ? $this->upgradeMethod = 'REPLACE' : $this->upgradeMethod = 'ALTER';
			print "<P>Upgrading database schema using {$this->upgradeMethod}</P>";
		} else {
			print "<P>Creating new database schema</P>";
			unset( $this->upgradeMethod );
		}
	}
	
	/**
	* Loads and XML document and parses it into a prepared schema
	*
	* This method accepts a path to an xmlschema-compliant XML file,
	* loads it, parses it, and uses it to create the SQL to generate the objects
	* described by the XML file.
	*
	* @param string $file		XML file
	* @return array	Array of SQL queries, ready to execute
	*/
	function ParseSchema( $file ) {
	
		// Create the parser
		$this->xmlParser = &$xmlParser;
		$xmlParser = xml_parser_create();
		xml_set_object( $xmlParser, $this );
		
		// Initialize the XML callback functions
		xml_set_element_handler( $xmlParser, "_xmlcb_startElement", "_xmlcb_endElement" );
		xml_set_character_data_handler( $xmlParser, "_xmlcb_cData" );
		
		// Open the file
		if( !( $fp = fopen( $file, "r" ) ) ) {
			die( "Unable to open file" );
		}
		
		// Process the file
		while( $data = fread( $fp, 4096 ) ) {
			if( !xml_parse( $xmlParser, $data, feof( $fp ) ) ) {
				die( sprintf( "XML error: %s at line %d",
					xml_error_string( xml_get_error_code( $xmlParser ) ),
					xml_get_current_line_number( $xmlParser ) ) );
			}
		}
		
		// Return the array of queries
		return $this->sqlArray;
	}
	
	/**
	* Loads a schema into the database
	*
	* Accepts an array of SQL queries generated by the parser 
	* and executes them.
	*
	* @param array $sqlArray	Array of SQL statements
	* @param boolean $continueOnErr	Don't fail out if an error is encountered
	* @return integer	0 if failed, 1 if errors, 2 if successful
	*/
	function ExecuteSchema( $sqlArray, $continueOnErr =  TRUE ) {
		$err = $this->dict->ExecuteSQLArray( $sqlArray, $continueOnErr );
		
		// Return the success code
		return $err;
	}
	
	/**
	* XML Callback to process start elements
	*
	* @access private
	*/
	function _xmlcb_startElement( $parser, $name, $attrs ) {
		
		$dbType = $this->dbType;
		if( isset( $this->table ) ) $table = &$this->table;
		if( isset( $this->index ) ) $index = &$this->index;
		if( isset( $this->querySet ) ) $querySet = &$this->querySet;
		$this->currentElement = $name;
		
		// Process the element. Ignore unimportant elements.
		if( in_array( trim( $name ),  array( "SCHEMA", "DESCR", "COL", "CONSTRAINT" ) ) ) {
			return FALSE;
		}
		
		switch( $name ) {
				
			case "CLUSTERED":	// IndexOpt
			case "BITMAP":		// IndexOpt
			case "UNIQUE":		// IndexOpt
			case "FULLTEXT":	// IndexOpt
			case "HASH":		// IndexOpt
				if( isset( $this->index ) ) $this->index->addIndexOpt( $name );
				break;

			case "TABLE":	// Table element
				if( !isset( $attrs['PLATFORM'] ) or $this->supportedPlatform( $attrs['PLATFORM'] ) ) {
					isset( $this->objectPrefix ) ? $tableName = $this->objectPrefix . $attrs['NAME'] : $tableName =  $attrs['NAME'];
					$this->table = new dbTable( $tableName, $this->upgradeMethod );
				} else {
					unset( $this->table );
				}
				break;
				
			case "FIELD":	// Table field
				if( isset( $this->table ) ) {
					$fieldName = $attrs['NAME'];
					$fieldType = $attrs['TYPE'];
					isset( $attrs['SIZE'] ) ? $fieldSize = $attrs['SIZE'] : $fieldSize = NULL;
					isset( $attrs['OPTS'] ) ? $fieldOpts = $attrs['OPTS'] : $fieldOpts = NULL;
					$this->table->addField( $fieldName, $fieldType, $fieldSize, $fieldOpts );
				}
				break;
				
			case "KEY":	// Table field option
				if( isset( $this->table ) ) {
					$this->table->addFieldOpt( $this->table->currentField, 'KEY' );
				}
				break;
				
			case "NOTNULL":	// Table field option
				if( isset( $this->table ) ) {
					$this->table->addFieldOpt( $this->table->currentField, 'NOTNULL' );
				}
				break;
				
			case "AUTOINCREMENT":	// Table field option
				if( isset( $this->table ) ) {
					$this->table->addFieldOpt( $this->table->currentField, 'AUTOINCREMENT' );
				}
				break;
				
			case "DEFAULT":	// Table field option
				if( isset( $this->table ) ) {
					$this->table->addFieldOpt( $this->table->currentField, 'DEFAULT', $attrs['VALUE'] );
				}
				break;
				
			case "INDEX":	// Table index
				if( !isset( $attrs['PLATFORM'] ) or $this->supportedPlatform( $attrs['PLATFORM'] ) ) {
					isset( $this->objectPrefix ) ? $tableName = $this->objectPrefix . $attrs['TABLE'] : $tableName =  $attrs['TABLE'];
					$this->index = new dbIndex( $attrs['NAME'], $tableName );
				} else {
					if( isset( $this->index ) ) unset( $this->index );
				}
				break;
				
			case "SQL":	// Freeform SQL queryset
				if( !isset( $attrs['PLATFORM'] ) or $this->supportedPlatform( $attrs['PLATFORM'] ) ) {
					$this->querySet = new dbQuerySet( $attrs );
				} else {
					if( isset( $this->querySet ) ) unset( $this->querySet );
				}
				break;
				
			case "QUERY":	// Queryset SQL query
				if( isset( $this->querySet ) ) {
					// Ignore this query set if a platform is specified and it's different than the 
					// current connection platform.
					if( !isset( $attrs['PLATFORM'] ) or $this->supportedPlatform( $attrs['PLATFORM'] ) ) {
						$this->querySet->buildQuery();
					} else {
						if( isset( $this->querySet->query ) ) unset( $this->querySet->query );
					}
				}
				break;
				
			default:
				if( $this->debug ) print "OPENING ELEMENT '$name'<BR/>\n";
		}	
	}

	/**
	* XML Callback to process cDATA elements
	*
	* @access private
	*/
	function _xmlcb_cData( $parser, $data ) {
		
		$element = &$this->currentElement;
		
		if( trim( $data ) == "" ) return;
		
		// Process the data depending on the element
		switch( $element ) {
		
			case "COL":	// Index column
				if( isset( $this->index ) ) $this->index->addField( $data );
				break;
				
			case "DESCR":	// Description element
				// Display the description information
				if( isset( $this->table ) ) {
					$name = "({$this->table->tableName}):  ";
				} elseif( isset( $this->index ) ) {
					$name = "({$this->index->indexName}):  ";
				} else {
					$name = "";
				}
				if( $this->debug ) print "<LI> $name $data\n";
				break;
			
			case "QUERY":	// Query SQL data
				if( isset( $this->querySet ) and isset( $this->querySet->query ) ) $this->querySet->buildQuery( $data );
				break;
			
			case "CONSTRAINT":	// Table constraint
				if( isset( $this->table ) ) $this->table->addTableOpt( $data );
				break;
				
			default:
				if( $this->debug ) print "<UL><LI>CDATA ($element) $data</UL>\n";
		}
	}

	/**
	* XML Callback to process end elements
	*
	* @access private
	*/
	function _xmlcb_endElement( $parser, $name ) {
		
		// Process the element. Ignore unimportant elements.
		if( in_array( trim( $name ), 
			array( 	"SCHEMA", "DESCR", "KEY", "AUTOINCREMENT", "FIELD",
						"DEFAULT", "NOTNULL", "CONSTRAINT", "COL" ) ) ) {
			return FALSE;
		}
		
		switch( trim( $name ) ) {
			
			case "TABLE":	// Table element
				if( isset( $this->table ) ) {
					$tableSQL = $this->table->create( $this->dict );
					
					// Handle case changes in MySQL
					// Get the metadata from the database, convert old and new table names to the
					// same case and compare. If they're the same, pop a RENAME onto the query stack.
 					$tableName = $this->table->tableName;
					if( $this->dict->upperName == 'MYSQL' and $oldTableName = $this->legacyTables[ strtoupper( $tableName ) ] ) {
						if( $oldTableName != $tableName ) {
    						print "RENAMING table $oldTableName to $tableName\n";
    						array_push( $this->sqlArray, "RENAME TABLE $oldTableName TO $tableName" );
						}
					}
					foreach( $tableSQL as $query ) {
						array_push( $this->sqlArray, $query );
					}
					$this->table->destroy();
				}
				break;
				
			case "INDEX":	// Index element
				if( isset( $this->index ) ) {
					$indexSQL = $this->index->create( $this->dict );
					array_push( $this->sqlArray, $indexSQL[0] );
					$this->index->destroy();
				}
				break;
			
			case "QUERY":	// Queryset element
				if( isset( $this->querySet ) and isset( $this->querySet->query ) ) $this->querySet->addQuery();
				break;
			
			case "SQL":	// Query SQL element
				if( isset( $this->querySet ) ) {
					$querySQL = $this->querySet->create();
					$this->sqlArray = array_merge( $this->sqlArray, $querySQL );;
					$this->querySet->destroy();
				}
				break;
				
			default:
				if( $this->debug ) print "<LI>CLOSING $name</UL>\n";
		}
	}
	
	/**
        * Sets default table prefix
        *
        * Sets a standard prefix that will be prepended to all database tables during
        * database creation. The prefix will automatically apply to tables referenced in indices as well.
		* Calling setPrefix with no arguments clears the prefix.
        *
        * @param string $prefix Prefix
        * @return boolean       TRUE if successful, else FALSE
        */
        function setPrefix( $prefix = '' ) {
                if( !preg_match( '/[^\w]/', $prefix ) and strlen( $prefix < XMLS_PREFIX_MAXLEN ) ) {
                        $this->objectPrefix = $prefix;
                        return TRUE;
                } else {
                        return FALSE;
                }
        }
	
	
	/**
	* Checks if element references a specific platform
	*
	* Returns TRUE is no platform is specified or if we are currently
	* using the specified platform.
	*
	* @param string	$platform	Requested platform
	* @return boolean	TRUE if platform check succeeds
	*
	* @access private
	*/
	function supportedPlatform( $platform = NULL ) {

		$dbType = $this->dbType;
		$regex = "/^(\w*\|)*" . $dbType . "(\|\w*)*$/";
		
		if( !isset( $platform ) or 
			preg_match( $regex, $platform ) ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	/**
	* Destroys the current object, freeing all bound resources
	*
	* It is recommended that you explicitly destroy all adoSchema objects when
	* you are finished using them.
	*/
	function Destroy() {
		xml_parser_free( $this->xmlParser );
		set_magic_quotes_runtime( $this->mgq );
		unset( $this );
	}
}
?>