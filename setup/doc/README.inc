phpGroupWare setup		March 2002 revised (5-2001)

Note: All setup classes are now located in the phpgwapi module.  Since setup
      cannot work without the api anyway, these classes were moved.

Class logical (?) organization map:

	class.setup.inc.php					Global setup functions app/hook/login
		|
		class.detection.inc.php	Detection of header, file and db versions
		|
		class.translation.inc.php		Multi-lang functions for display
		|
		class.html.inc.php		HTML/template output functions
		|
		class.process.inc.php		db processing functions/upgrade/install
			|
			class.schema_proc.inc.php				DB array <--> SQL and abstraction class
				|
				class.schema_proc_array.inc.php		Array input parser
				|
				class.schema_proc_mysql.inc.php		SQL functions for MySQL
				|
				class.schema_proc_pgsql.inc.php		SQL functions for Postgresql
				|
				class.schema_proc_mssql.inc.php		SQL functions for MS SQL
				|
				...									other db support...

