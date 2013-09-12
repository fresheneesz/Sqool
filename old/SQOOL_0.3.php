<?php
	include_once("cept.php");	// exceptions with stack traces
	
	/*	To do:
			* finish stuf
			* add: 
				* classStruct	returns the code to create the php class for the specified database table
				* dbStruct		returns the code to create sqool classes for all the tables in the database (returned as an array of definitions)
				* app			appends a value to a list
				* setElem		sets an element of a list
				* rmElem		removes an element of a list
				* count			gets the number of elements in a list
			* Think about adding:
				* hasObj
				* syncClass
				* syncAll		syncs all defined classes
				* rmMember		deletes a member field from an object's table 
				* killClass		deletes a class (and all the objects of that class)
				* deDupPrims	removes duplicates in the primitive lists tables and fixes the sq_lists table acordingly (this should be run in a batch job)
			* Think about rewriting this in PDO (tho PDO drivers might be a pain in the ass - doctrine uses it tho)
	*/
	
	/*	internal funcs that need to be written (because they are currently being used):


		funtions need finishing
		
	*/
	
	/*	Defines:		
			class sqool			connection to a database
				new sqool		constructor
				debug			turns on or off debugging messages
				getDB			returns a connection to another database on the same host
				insert			insets an object into the database
				get				returns a sqoolobj that can be used to modify an object or access its members
				queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'release' method)
				release			performs all the queries in the queue
				sql				executes a multi-query 
				escapeString	escapes a string based on the charset of the current connection (this is not needed for any sqool queries other than 'sql' because sqool escapes data automatically)
			
			class sqoolobj	a sqool object
				make		defines a class type. Does not create a table in the DB until an object is inserted.
								Member types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
				save		saves variables into the database that have been set for an object
				fetch		returns members of one or more objects (returns as an array of one or more sqoolObj objects). See the function for its use.
					
					
		Tables and naming conventions (for the tables that make up the objects under the covers):
			* u_CLASSNAME			holds all the objects of a certain type
				* s_CLASSNAME_id		the ID of the object
				* u_MEMBER				example field-name of a member named "MEMBER". 
										If this is a primitive, it will hold a value. 
										If this is a list or object, it will hold an ID. Objects are treated as references - they all start with a null id (note this is not the same as ID 0).
			* sq_lists				holds all the lists in the database
				* listID				the ID of the list that owns the object
				* objectID				the ID of the object/primitive that the list owns
				
			* sq_TYPE				holds all of the primitive values of a certain type that are refered to by lists (for example sq_int or sq_float)
				* primID				the ID of the value
				* value					the value
	 */
	
	
	// performs lazy connection (only connects upon actual use)
	class sqool			// connection to a database
	{	private $con;		// the current selected connection (for accessing multiple databases at once)
		private $username, $password, $host, $database;
		public static $debugFlag = true;
		private static $classes = array();
		static function addClass($className)			// meant for internal use by the sqoolobj class only
		{	if(in_array($className, sqool::$classes))
			{	throw new cept("Attempting to redefine class '".$className."'. This isn't allowed.");
			}
			sqool::$classes[] = $className;
		}
		
		const connection_failure 		= 0;
		const database_creation_error 	= 1;
		const class_already_exists	 	= 2;
		const nonexistant_object	 	= 3;
		const append_error		 		= 4;
		const invalid_variable_name 	= 5;
		const general_query_error 		= 6;	// cept::data should hold the error number for the query error
		const table_creation_error 		= 7;
		
		static function primtypes()			// meant for internal use by the sqoolobj class only
		{	return array
			(	'bool', 'string', 'bstring', 'gstring', 'tinyint', 'int', 'float', 'class'
			);
		}
		
		// Access a local database - attempts to create database if it doesn't exist
		// Can create a database if your host allows you to, otherwise database must already exist
		// reteurns a sqool object
		// the $conIn variable is for internal use
		public function sqool($usernameIn, $passwordIn, $databaseIn, $hostIn='localhost', $conIn=false)
		{	self::require_AZaz09($databaseIn);
			
			$this->username = $usernameIn;
			$this->password = $passwordIn;
			$this->host = $hostIn;
			$this->database = $databaseIn;
			
			$this->con = $conIn;
		}
		
		// returns a connection to another database on the same host
		public function getDB($databaseName)		
		{	return new sqool($this->username, $this->password, $this->host, $databaseName);
		}
		
		// Turns debugging on or off (on by default)
		// $setting is true for 'on', false for 'off'
		public static function debug($setting)
		{	if($setting === true)
			{	self::$debugFlag = true;
			}else
			{	self::$debugFlag = false;
			}
		}
		
		// copies an object into a new row in the database table for its class
		// returns the inserted object (a reference to the object just inserted into the DB)
		// the object used to insert is unmodified
		public function insert($object)
		{	$className = $object->getClassName();
			$variables = $object->getSetVariables();
			
			// attempt to insert into that table
			$result = $this->insertIntoTable($className, $variables);
			if($result === false)
			{	// create table
				$columns = $object->sqoolTypesToMYSQL();
				$this->createTable($className, $columns);
				
				// retry query
				$result = $this->insertIntoTable($className, $variables);
				if($result === false)
				{	throw new cept("Insert error: Could not create table ".$className, self::$table_creation_error);
				}
			}
			
			$object->clearSetVariables();	// since the database has been set, those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
			
			$newObject = clone $object;		// copy
			$newObject->setID($result[1][0][0]);	// set the ID to the primary key of the object inserted
			$newObject->setSqoolCon($this);
			return $newObject;
		}
		
		// $objectIDORarray can either be an object's ID, or an array of object IDs (in which case an array of sqoolobjs will be returned)
		function get($className, $objectIDORarray)
		{	
		}
		
		
		// performs an sql query, and echos error information if debugFlag is on
		public function sql($query)
		{	$this->connectIfNot();
			
			/* execute multi query */
			$resultSet = array();
			if($this->con->multi_query($query))
			{	do	/* store first result set */
				{	if($result = $this->con->store_result())
					{	$results = array();
						while($row = $result->fetch_row())
						{	$results[] = $row;
						}
						$result->free();
						$resultSet[] = $results;
					}else
					{	$resultSet[] = array();
					}
				}while($this->con->next_result());
			}
			
			if($this->con->errno)
			{	throw new cept("* ERROR(".$this->con->errno.") in query: <br>\n'".$query."' <br>\n".$this->con->error . "<br>\n", self::general_query_error, $this->con->errno);
			}
			
			return $resultSet;
		}
		
		// performs an sql query, and echos error information if debugFlag is on
		public function escapeString($string)
		{	$this->connectIfNot();
			
			return $this->con->real_escape_string($string);
		}
		
		/********************** BELOW THIS ARE MzETH/oDS FOR INTERNAL USE ONLY *************************/
		
		
		
		// creates a mysql table named $name 
		// $columns should be an associtive array where the key is the name of the column, and the value is the type
		// con is mysql connection resource
		private function createTable($tableName, $columns)
		{	$query = 'CREATE TABLE '.$tableName.' (';
			
			$query .= 'sq_'.$tableName.'_id INT NOT NULL PRIMARY KEY AUTO_INCREMENT';	// add an object id field (sq for sqool defined field - as opposed to user defined)
			
			foreach($columns as $col => $type)
			{	$query .= ', '.$col.' '.$type.' NOT NULL';	// name -space- type
			}
			$query.=")";
			
			return $this->sql($query);
		}	
		
		
		// if the object is not connected, it connects
		// returns true if a new connection was made
		private function connectIfNot()
		{	if($this->con === false)
			{	//connect
				@$this->con = new mysqli($this->host, $this->username, $this->password, $this->database);
				
				if($this->con->connect_errno)
				{	if($this->con->connect_errno == 1049)	// database doesn't exist
					{	// create database
						$this->con = new mysqli($this->host, $this->username, $this->password);
						$this->sql('CREATE DATABASE '.$this->database);
						$this->con->select_db($this->database);
						
						return true;
					}else
					{	throw new cept('Connect Error (' . $this->con->connect_errno . ') ' . $this->con->connect_error, sqool::connection_failure, $this->con->connect_errno);
					}
				}
				return true;
			}
			return false;
		}
		
		// tests if a character is in the list of "singles" or in one of the "ranges"
		public static function charIsOneOf($theChar, $singles, $ranges)
		{	$singlesLen = strlen($singles);
			for($n=0; $n<$singlesLen; $n+=1)
			{	if($theChar == $singles[$n])
				{	return true;
				}
			}
			
			$numberOfRanges = floor(strlen($ranges)/2);
			for($n=0; $n<$numberOfRanges; $n+=1)
			{	if( $ranges[0+$n*2]<=$theChar && $theChar<= $ranges[1+$n*2] )
				{	return true;
				}
			}
			return false;	
		}
		
		public static function require_AZaz09($variable)
		{	$string = "".$variable;
			$theArray = str_split($string);
			foreach($theArray as $c)
			{	if(false == self::charIsOneOf($c, '_', 'azAZ09'))
				{	throw new cept("String contains the character '".$c."'. Variable name expected - a string containing only alphanumeric characters and the character '_'.", sqool::invalid_variable_name);
				}
			}
		}
		
		// inserts a set of rows into a table
		// $rows must be an associative array where the keys are the column names, and the values are the values being set
		// returns the resultset if successful (which includes the last insert ID).
		// returns false if table doesn't exist
		private function insertIntoTable($tableName, $rows)
		{	$query = 'INSERT INTO '.$tableName.' VALUES ';
			
			$columns = array();
			$values = array();
			foreach($rows as $col => $val)
			{	$columns[] = '`'.$col.'`';
				$values[] = "'".$this->escapeString($val)."'";
			}
			
			$query = 'INSERT INTO `'.$tableName.'` ('.implode(",", $columns).') '.'VALUES ('.implode(",", $values).');SELECT LAST_INSERT_ID();';
			
			try
			{	$result = $this->sql($query);
			}catch(cept $e)
			{	if($e->data == 1146 || $e->data == 656434540)	// the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
				{	return false;	// table doesn't exist
				}else
				{	throw $e;
				}
			}
			
			return $result;
		}
	}
	
	
	/*********************************************** sqoolobj ******************************************************/
	
	
	
	
	// used to represent a database object
	class sqoolobj
	{	private $con=false;				// the sqool object (connection handler)
		private $ID=false;			// the ID of the object (inside the table $classTable)	
		private $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
		
		function setSqoolCon($conIn)	// meant for internal use by the sqool class only
		{	$this->con = $conIn;
		}
		function setID($theID)			// meant for internal use by the sqool class only
		{	$this->ID = $theID;
		}
		function getSetVariables()		// meant for internal use by the sqool class only
		{	return $this->setVariables;
		}
		function clearSetVariables()	// meant for internal use by the sqool class only
		{	$this->setVariables = array();
		}
		
		private static $className		= false;	// false stands for "not set"
		private static $classDefinition = false;	// false stands for "not set" // when set, should be an array with keys representing the names of each member and the values being an array of the form array(baseType[, listORclassType][, className_forObjectList])
		
		function getClassName()			// meant for internal use by the sqool class only
		{	return self::$className;	
		}
		
		function __set($name, $value)
		{	$backendName = 'u_'.$name;
			if( false == in_array($backendName, array_keys(self::$classDefinition)) )
			{	throw new cept("Object doesn't contain the member '".$name."'.");
			}
			$this->setVariables[$backendName] = $value;
		}
		
		/* 	 Defines a class
			 $className is a string - the name of the class 
			 $members is a string in the format "type:name type2:name2 type3:name3" etc
			 	Types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
		*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
		//// Objects (a class typed variable) are treated as reference - they start with the value 0 (a null pointer - 0 is reserved to represent a null pointer)
		public static function make($className, $members)
		{	if(self::$classDefinition === false)	// class can only be made once
			{	sqool::require_AZaz09($className);
				sqool::addClass($className);		// add class to sqool's list (throws error if a class is redefined)
				
				self::$className = 'u_'.$className;
				self::$classDefinition = self::parseClassDefinition($members);	// should output array like array(name => array(baseType[, listORclassType][, object_list_type]))
			}
		}
		
		public function save()
		{	if(count($this->setVariables) == 0)
			{	throw new cept("Attempted to save an empty dataset to the database");
			}
			if($this->con === false)
			{	throw new cept("Attempted to save an object without a connection to a database");
			}
			if($this->ID === false)
			{	throw new cept("Attempted to save an object that isn't in a database yet. (Use sqool::insert to insert an object into a database).");
			}
			
			if(false == $this->updateObject())		// if a neccessary column doesn't exist
			{	// add columns defined in this class that aren't in the table schema yet
				$showColumnsResult = $this->con->sql("SHOW COLUMNS FROM ".self::$className.";");
				
				$columns = array();
				foreach($showColumnsResult[0] as $DBcol)
				{	$columns[] = $DBcol[0];
				}
				
				$alterQuery = '';
				$oneAlredy = false;
				foreach(self::$classDefinition as $colName => $info)
				{	if( false == in_array($colName, $columns) )
					{	if($oneAlredy)
						{	$alterQuery .= ', ';
						}else
						{	$oneAlredy = true;
						}
						
						$mysqlType = $this->sqoolTypesToMYSQL(array($colName => $info));
						
						$alterQuery .= $colName.' '.$mysqlType[$colName].' NOT NULL';
					}
				}
				
				$this->con->sql('ALTER TABLE '.self::$className.' ADD ('.$alterQuery.');');
				
				if(false == $this->updateObject())
				{	throw new cept("Failed to create the columns neccessary to save the dataset");
				}
			}
		}
		
		// returns false if a neccessary column doesn't exist
		// returns true otherwise
		private function updateObject()
		{	$query = 'UPDATE '.self::$className.' SET';
			
			$onceAlready = false;
			foreach($this->setVariables as $col => $val)
			{	if($onceAlready)
				{	$query .= ',';
				}else
				{	$onceAlready = true;
				}
				
				$query .= " `".$col."`='".$this->con->escapeString($val)."'";
			}
			
			try
			{	$result = $this->con->sql($query.' WHERE sq_'.self::$className.'_id='.$this->ID.';');
			}catch(cept $e)
			{	if($e->data == 1054 || $e->data == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
				{	return false;	// column doesn't exist
				}else
				{	throw $e;
				}
			}
			
			return true;	// success
		}
		
		
		// loads a list of database objects
		// returns a sqoolobj
		// throws an error if an invalid member is accessed (if a non-existant member or object is attempted to be accessed)
		/*	
			object->fetch
			(	"memberName" => memberDataControl,
				"memberName2" => memberDataControl2,
				etc...
			);
			
			// memberDataControl represents the following:
			array
			(	// if the object member being selected by this memberDataControl set is a list, the "members" array controls the returned members for each element of the list
				"members" => array
				(	memberDataConrol
				),
					
				// if "fieldName" is a list, the following keys apply:
				// op is a comparison operator: > < = 
				// for fields that are objects, the 'value' is a sqoolobj instance
				// an empty members array (e.g. "array(membera, memberb, etc)") means return all non-recursive-fields (fields that point back to an already returned piece of data will point to that sqoolobj, instead of returning a new sqoolobj)
				// the "sort", "items", "cond", and "ranges" keys are optional (tho one of the following MUST be given: "items", "cond", or "ranges")
				"sort" => array("field", direction, "field2", direction2, etc, etc),			// the way to sort the elements of a member list - direction should be "-", "+", or a number. "+" means increasing order [smallest first], "-" means decreasing order [largest first], and a number means sort by values closest to the number
				"cond" => array("field op", value, "andor", "field2 op", value2, "andor", etc), // the elements of a member list selected by some kind of conditions on the elements of the list
				"ranges" => array(start, end, start2, end2, etc, etc)							// objects to return from the selected list by their position in the list (after being sorted).
			)
		*/
		function fetch($memberName)
		{	// use sqoolCon's fetch to fetch
		}
		
		
		
		// append a value to the end of a list
		function app($memberName, $value)	
		{	$index = sqool_nextIndex($this->physicalName, $memberName);		// get and incriment the array count
			//sql('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=1+LAST_INSERT_ID(SAC_'.$memberName.')');
			//$index = mysql_insert_id();
			
			$results = sql('SELECT * FROM '.$this->physicalName, $con->con);	// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);		// get name of table that holds the list
			
			if($sqool_DebugFlag)
			{	if(sqool_rowExists($physicalTableName, "indecies", $index))	// if that index already exists, error
				{	throw new cept("<br/>Error: Append's next index for member ".$memberName." in table ".$this->physicalName." already exists. This shouldn't happen.<br/>", sqool_append_error);
				}
			}
			
			
			$mType = sqool_getMemberType("U_".$memberName);		// get the type
			
			if($mType == sqool_list)
			{	throw new cept("<br/>Error: Member ".$memberName." in table ".$this->physicalName." seems to have a list of lists. This shouldn't happen.<br/>", sqool_append_error);
			}
			else if($mType == sqool_ref)
			{	insertIntoTable($con->con, $physicalTableName, array(array($index, $value->physicalName)));
			}
			else if($mType == sqool_refList)
			{	throw new cept("<br/>Error: Member ".$memberName." in table ".$this->physicalName." seems to have a list of lists of refernces. This shouldn't happen.<br/>", sqool_append_error);
			}
			else if($mType == sqool_obj)
			{	sqool_copy_object(); 	// copy object
				/*	$memberList = $this->getMemberNames();
					$memberList_len = count($memberList);
					for($n=0;$n<$memberList_len;$n++)		// loop through memebers
					{	$this->set(substr($memberList[$n], 2), $value->$get(substr($memberList[$n], 2)));	// set member
					}*/
			}
			else if($mType == sqool_primitive)
			{	insertIntoTable($con->con, $physicalTableName, array(array($index, $value)));
			}
		}
		
		// returns true if there exists a value at $index for an array
		function keyExists($memberName, $index)
		{	if($index<0)
			{	return 0;	// error - bad index
			}
				
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);
			//$results = sql('SELECT * FROM '.$this->physicalName);			// ..
			//$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))			// if the element already exists
			{	return true;
			}else
			{	return false;
			}
		}
		
		// get an element from a list (list or ref list)
		function getElem($memberName, $index)
		{	if($index<0)
			{	throw new cept("Bad index passed in: '".$index."'.");
			}
			
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			//$results = sql('SELECT * FROM '.$this->physicalName);			// ..
			//$physicalTableName = mysql_result($results, 0, "U_".$memberName);		// get name of table that holds the list
			
			$results = sql('SELECT "'.$index.'" FROM '.$physicalTableName);	// ..
			return mysql_result($results, 0, "elements");							// get value
		}
		
		// set an element in a list (list or ref list)
		function setElem($memberName, $index, $value)
		{	if($index<0)
			{	throw new cept("Bad index passed in: '".$index."'.");
			}
			// set "SAC_".$membername if it is lower than $index
			sql('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'<'.$index.','.$index.',SAC_'.$memberName.')');
			
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element already exists, update it with the new value
			{	sql('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
			}
			else	// create it
			{	insertIntoTable($con->con, $physicalTableName, array(array($index, $value)));
			}				
		}
		
		function rmElem($memberName, $index)			// delete an element of a list
		{	if($index<0)
			{	throw new cept("Bad index passed in: '".$index."'.");
			}
			// set "SAC_".$membername if it is equal to the $index
			sql('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'='.$index.','.$index.',SAC_'.$memberName.')');
			
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element exists, delete it
			{	sql('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
				sql('DELETE FROM '.$physicalTableName.' WHERE indecies='.$index);
			}
		}
		
		// get the number of elements in a list
		function count($memberName)			
		{	$results = sql('SELECT U_'.$memberName.' FROM '.$physicalName);
			if($results == false)
			{	throw new cept("<br/>Error: Attempted to count a list member ".$memberName." which couldn't be found in table ".$physicalName.".<br/>");
			}
			$listTable = mysql_result($results, 0, "U_".$memberName);
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$listTable);
			return mysql_numrows($results);
		}
		
		function getMemberNames()
		{	$results = sqlquery('SHOW COLUMNS FROM '.$physicalName, $con);
			
			$memberNames = array();
			
			$num=mysql_numrows($results);
			for($i=0; $i < $num; $i++) 
			{	$memberNames[] = mysql_result($results, $i);
			}
			return $memberNames;
		}
		
		function getMemberTypes($member)
		{	$results = sqlquery('SELECT '.$member.' FROM '.$physicalName, $con);
			return mysql_field_type($results);
		}
		
		
		/********************** BELOW THIS ARE MzETH/oDS FOR INTERNAL USE ONLY *************************/
				
		
		// takes some varialbe definitions (may be a subset of what is inside self::$classDefinition)
		// returns an associative array where the keys are the names of the members, and the values are their mysql type
		// returns false if the class is not defined
		public function sqoolTypesToMYSQL($variableDefinitions=false)		// meant for internal use by the sqool class only
		{	if($variableDefinitions === false)
			{	if(self::$classDefinition === false)
				{	return false;
				}else
				{	$variableDefinitions = self::$classDefinition;
				}
			}
			
			$result = array();
			foreach($variableDefinitions as $memberName => $definition)
			{	switch($definition[0])
				{case "bool":		$type = "BOOLEAN";		break;
				 case "string":		$type = "TINYTEXT";		break;
				 case "bstring":	$type = "TEXT";			break;	// big string
				 case "gstring":	$type = "LONGTEXT";		break;	// giant string
				 case "tinyint":	$type = "TINYINT";		break;
				 case "int":		$type = "INT";			break;
				 case "bigint":		$type = "BIGINT";		break;
				 case "float":		$type = "FLOAT";		break;
				 case "double":		$type = "DOUBLE";		break;
				 
				 case "object":		$type = "INT";			break;
				 case "list":		$type = "INT";			break;
				}
				$result[$memberName] = $type;
			}
			return $result;
		}
		
		// keys of array2 will take precedence
		private function assarray_merge($array1, $array2)
		{	foreach($array2 as $k => $v)
			{	$array1[$k] = $v;
			}
			return $array1;
		}
		
		// parses type declarations for a sqool class (in the form "type:name  type:name  etc")
		// returns an array with keys representing the names of each member and the values being an array of the form array(baseType[, listORclassType][, className_forObjectList])
		// see parseSingle for examples of the returned data
		private static function parseClassDefinition($members)
		{	$result = array();
			while(true)
			{	$nextMember = self::parseSingle($members, $index);	// set the result array
				
				if($nextMember === false)
				{	break;		// done parsing (no more members)
				}
				$keys = array_keys($nextMember);
				if(in_array($keys[0], array_keys($result)))
				{	throw new cept("Error: can't redeclare member '".$keys[0]."' in class definition");
				}
				$result = self::assarray_merge($result, $nextMember);
			}
			return $result;
		}
		
		// extracts a string from "theString" (beginning at "index") that is made up of the characters in "singles" or "ranges"
		// puts the result in "result" 
		function getCertainChars($theString, $index, $singles, $ranges, &$result)
		{	$result = "";
			$n=0;
			while(isset($theString[$index+$n]) && sqool::charIsOneOf($theString[$index+$n], $singles, $ranges))
			{	$result .= $theString[$index+$n];
				$n+=1;
			}
			return $n;
		}
		
		// returns an array where the only member has a key (which represents the name of the member) which points to an array of the form array(baseType[, listORclassType][, className_forObjectList])
		// examples of returned values: array("bogus"=>array("int"))  array("bogus2"=>array("list", "int")
		//		  						array("bogus3"=>array("object", "someobjName")  array("bogus4"=>array("list", "object", "yourmomisanobject") 
		private static function parseSingle($members, &$index)
		{	$whitespace = " \t\n\r";
			
			$index += 	self::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
			if($index >= strlen($members))
			{	return false;	// no more members (string is over)
			}
		
			$numchars = self::getCertainChars($members, $index, "_", "azAZ09", $baseType);		// get base type
			if($numchars==0)
			{	throw new cept("Error parsing types: type was expected but not found.<br/>\n"); 	// error if type isn't found
			}
			$index += $numchars;
				
			$index += 	self::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
			$numchars = self::getCertainChars($members, $index, "_", "azAZ09", $listOrRefOrNone);		// get "list" (if it exists)
			$index += $numchars;
			if($listOrRefOrNone=="list")
			{	// 'list' is found
				$listIsFound = true;
			}else if($numchars==0)
			{	$listIsFound = false;
			}else			// something other than "list" or "ref" is found
			{	throw new cept("Error parsing types: 'list' or ':' expected but got'".$listOrRefOrNone."'<br/>\n");
			}
						
			$index += 	self::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
			$numchars = self::getCertainChars($members, $index, ":", "", $dumdum);			// get colon
			if($numchars==0)
			{	throw new cept("Error parsing types: ':' was expected but not found.<br/>\n");		// error colon isn't found
			}
			$index += $numchars;
			
			$index += 	self::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
			$numchars = self::getCertainChars($members, $index, "_", "azAZ09", $name);		// get name
			if($numchars==0)
			{	throw new cept("Error parsing types: className was expected but not found.<br/>\n"); 	// error if name isn't found
			}
			$index += $numchars;
			
			if(in_array($baseType, sqool::primtypes()))	// is a primitive type
			{	$type = array($baseType);
			}else
			{	$type = array('object', $baseType);
			}
			
			if($listIsFound)
			{	return array('u_'.$name => array_merge(array('list'), $type) );
			}else
			{	return array('u_'.$name => $type);
			}
		}
		
	};
	
	
	
	/********************** BELOW THIS ARE FUNCTIONS MEANT FOR INTERNAL USE ONLY *************************/
	
	
		// For internal use only
	function sqool_getList($memberName, $isRefList)
	{	// access the table where the list is stored
		$table = mysql_result($results, $i, "U_".$memberName);
		$results = sql('SELECT * FROM '.$table);
		
		$returnList = array();
		while(1) 
		{	$row = mysql_fetch_row($results);
			if($row == false)
			{	break;
			}
			
			if($isRefList)	// ref list
			{	$returnList[$row[0]] = sqoolLoad(substr($row[1],2) );	// load the object into the list (with the name stripped of its "U_")
			}
			else			// primitive list
			{	$returnList[$row[0]] = $row[1];
			}
		}
		return $returnList;
	}
	
	
	// changes $membersArray, an array with elements of the form (name, type, list, ref, ref list) into mySQL types
	// returns an array with elements of the form (name, mySQLtype) on success, 0 on failure
	function sqoolTypeConvert($membersArray)
	{	if($membersArray == 0)
		{	return 0;	// propogate error
		}
		
		$columnArray = array();
	
		$memberCount = count($membersArray);
		for($n=0; $n < $memberCount; $n++)
		{	$member = $membersArray[$n];
			$type="";
			if($member[2])		// list
			{	$type = "VARCHAR(".sqool_list.") NOT NULL";
			
				// extra column for list count
				$columnArray[] = array("SAC".substr($member[0],1), "INT");
			}
			else if($member[3])	// ref
			{	$type = "VARCHAR(".sqool_ref.") NOT NULL";
			}
			else if($member[4])	// ref list
			{	$type = "VARCHAR(".sqool_refList.") NOT NULL";
			
				// extra column for list count
				$columnArray[] = array("SAC".substr($member[0],1), "INT");
			}
			else
			{	switch ($member[1]) // primitive or object
				{case "bool":		$type = "BOOLEAN";		break;
				 case "string":		$type = "TINYTEXT";		break;
				 case "bstring":	$type = "TEXT";			break;	// big string
				 case "gstring":	$type = "LONGTEXT";		break;	// giant string
				 case "tinyint":	$type = "TINYINT";		break;
				 case "int":		$type = "INT";			break;
				 case "bigint":		$type = "BIGINT";		break;
				 case "float":		$type = "FLOAT";		break;
				 case "double":		$type = "DOUBLE";		break;
				 
				 default:	if(sqoolClassExists($member[1]))	// object
				 			{	$type = "VARCHAR(".sqool_obj.") NOT NULL";
				 				break;	
							}
							else
							{	return 0;	// invalid classtype (type error from sqoolTypeConvert)
							}
				}
			}
			
			$columnArray[] = array($member[0], $type);
		}
		return $columnArray;
	}
	
	// returns true if there exists a row in a table named $physicalTableName with a column named $columnName that contains $contents
	function sqool_rowExists($physicalTableName, $columnName, $contents)
	{	if(sql('SELECT * FROM '.$physicalTableName.' WHERE '.$columnName.'="'.$contents.'"') != false)
		{	return true;
		}
		else
		{	return false;
		}
	}
	
	// returns piece of a query used to initialize (using the "INSERT INTO" command)
	// if $addingName is not null, returns a piece of the query string used to initialize it (using the "UPDATE" command)
	function initList($type, $addingName, $initList=0, $isRefList)
	{	$query = "";
		
		// set list count
		if($addingName!=0)
		{	$query .= "SAC".substr($addingName,1) . "=";
		}
		$query .='"0", ';	// count starts at 0
		
		// find type
		//$elementAndType = sqoolTypeConvert(
		
		// create list table
		sqlquery('UPDATE sqool_info SET arrayNum=1+LAST_INSERT_ID(arrayNum)');
		$result = mysql_insert_id();
		createTable($con, "sqool_array_".$result, array(array("indecies", "TEXT"), array("elements", $type)) );
		
		// initialize list (if applicable)
		if($initList!=0)
		{	setListTable("sqool_array_".$result, $initList, $isRefList);
		}
		
		if($addingName!=0)
		{	$query .= $addingName . "=";
		}
		$query .='"'."sqool_array_".$result.'"';	// write a pointer to the list that was just created
		return $query;
	}
	
	// returns piece of a query used to initialize (using the "INSERT INTO" command)
	// if $addingName is not null, returns a piece of the query string used to initialize it (using the "UPDATE" command)
	function initRef($addingName, $initVal=0)
	{	if($addingName!=0)
		{	return $addingName . '="'.$initVal.'"';	// null reference
		}
		else
			return '"'.$initVal.'"';					// null reference
	}
	
	// returns piece of a query used to initialize (using the "INSERT INTO" command)
	// if $addingName is not null, returns a piece of the query string used to initialize it (using the "UPDATE" command)
	function initPrimitive($addingName, $initVal=0)
	{	if($addingName!=0)
		{	return $addingName . '="'.$initVal.'"';
		}
		else
			return '"'.$initVal.'"';
	}
	
	// returns piece of a query used to initialize (using the "INSERT INTO" command)
	// if $addingName is not null, returns a piece of the query string used to initialize it (using the "UPDATE" command)
	function initObj($classname, $addingName, $initObj=0)
	{	// get next ID for object of this class
		sqlquery('UPDATE sqool_classes SET nextOpenSlot=1+LAST_INSERT_ID(nextOpenSlot) WHERE name="'.$classname.'"');
		$result = mysql_insert_id();
		
		// create object table
		sqool_create_object($classname, "sqool_".$classname."_".$result, $initObj);
		
		if($addingName!=0)
		{	return $addingName . '="'."sqool_".$classname."_".$result.'"';	// write a pointer to the object that was just created
		}
		else
		{	return '"'."sqool_".$classname."_".$result.'"';					// write a pointer to the object that was just created
		}
	}
	
	// returns true if it is a primitive type
	function isPrimType($type)
	{	switch($type)
		{case "bool":
		 case "string":	case "bstring":	case "gstring":	
		 case "tinyint":case "int":		case "bigint":
		 case "float":	case "double":	// if it is a primitive...
			return true;
		 default:	// if it is an object (the object's class was already checked by sqoolTypeConvert)
			return false;
		}
	}
	
	// takes in a member of the form (name, type, list, ref, ref list) and initializes it
	// if $addingName is null, returns a piece of the query string used to initialize it (using the "INSERT INTO" command)
	// if $addingName is not null, returns a piece of the query string used to initialize it (using the "UPDATE" command)
	function initMember($member, $addingName, $initVal=0)
	{	if(	$member[2] && $member[3] || 
			$member[3] && $member[4] || 
			$member[2] && $member[4])
		{	echo "Error: a member can't be multiple types (out of ref, list, and ref list). I know this isn't your fault... its my fault. : (";
		}
		
		if($member[2])			// list
		{	if(isPrimType($member[1]))
			{	return initList($member[1], $addingName, $initVal, false);
			}
			else	// type is an object
			{	return initList("U_".$member[1], $addingName, $initVal, false);
			}
		}
		else if($member[3])		// ref
		{	return initRef($addingName, $initVal);
		}
		else if($member[4])		// ref list
		{	return initList($member[1], $addingName, $initVal, false);
		}
		else									// primitive or object
		{	if(isPrimType($member[1]))
			{	return initPrimitive($addingName, $initVal);
			}
			else
			{	return initObj("U_".$member[1], $addingName, $initVal);
			}
		}
	}
	
	// creates the table that represents an object
	function sqool_create_object($classname, $physicalName, $con, $initObj=0)
	{	if(sqool_exists($physicalName))
		{	return 0;	// can't recreate an already existing object
		}
		
		//	find the class definition
		echo "Wtf: ".$classname."<br>";
		if($classname=="sqool_ltest_0")
		{	echo "* TRACE: <br/>";
			print_r(debug_backtrace());
			echo "<br/><br/>\n";
		}
		$results = sqlquery('SELECT members FROM sqool_classes WHERE name="'.$classname.'"');
		$members = mysql_result($results, 0, "members");
		 
		$membersArray = sqool_parse($members);					// parse the class definition
		$columnArray = sqoolTypeConvert($membersArray);
		if($columnArray==false){return false;/*error*/}// invalid classtype (type error from sqoolTypeConvert)
		
		$result = createTable($con, $physicalName, $columnArray);		// create the object
		
		if($result != FALSE)
		{	// insert table into table of objects (for potential later modification)
			insertIntoTable($con, "sqool_objects", array(array($physicalName, $classname)));
			
			// create space for member variables
			$query = 'INSERT INTO '.$physicalName.' VALUES(';
			$memberCount = count($membersArray);
			$createdTables = array($physicalName);	// keep track of the tables you create here, in case you have to delete them
			for($n=0; $n < $memberCount; $n++)
			{	if($n!=0)
					$query .=', ';
				
				if($initObj!=0)
				{	$query .= initMember($membersArray[$n], 0, $initObj->get(substr($membersArray[$n][0], 2)));
				}
				else
				{	$query .= initMember($membersArray[$n], 0);
				}
			}
			$query .=')';
			sqlquery($query);
			
			$result = new sqoolObj();
			$result->physicalName = $physicalName;
			return $result;
		}
		else
		{	return false;	// error
		}
	}

	
	
	function sqool_handleError($errorMessage)
	{	if($sqool_DebugFlag)
		{	echo $errorMessage;
		}
		throw new InvalidArgumentException($errorMessage);
		echo "<br/>Shouldn't get here<br/><br/>";
	}
		
	// For internal use only
	// sets a list member of an object
	function setList($tableName, $memberName, $value, $isRefList)
	{	$results = sqlquery('SELECT * FROM '.$tableName);	// ..
		$listTable = mysql_result($results, 0, $memberName);	// get the name of the table that holds the list
		
		setListTable($listTable, $value, $isRefList);
	}
	
	// For internal use only
	// sets the table storing a list
	function setListTable($listTable, $value, $isRefList)
	{	sqlquery('DELETE * FROM '.$tableName);			// delete contents of table
		
		$query = 'INSERT INTO '.$tableName.' VALUES ';
		$keys = array_keys($value);
		$numberOfKeys = count($keys);
		for($n=0; $n<$numberOfKeys; $n++)
		{	if($n>0)
			{	$query .= ", ";
			}
			if($isRefList)	// ref list
			{	$query .= '("'.$keys[$n].'", "'.$value[$keys[$n]]->physicalName.'")';
			}
			else			// normal list
			{	$query .= '("'.$keys[$n].'", "'.$value[$keys[$n]].'")';
			}
		}
		sqlquery($query);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// returns true if a table named $physicalName exists
	function sqool_exists($physicalName)
	{	$results = mysql_query('SELECT * FROM '.$physicalName);
		if($results==FALSE)
		{	return FALSE;
		}else 
		{	return TRUE;
		}
	}
	
	
	
	
	
	function sqool_parseComma($members, &$index)
	{	$index += sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		$numchars = sqool_getCertainChars($members, $index, ",", "", $dumdum);			// get comma
		$index += $numchars;			
		$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		if($numchars==0)
		{	return false;		// got comma
		}
		else
		{	return true;
		}
	}
	
	// parses type declarations for a sqool class (in the form "type:name, type:name, etc")
	// returns an array with elements of the form (name, type, list, ref, ref list)
	// returns false on error
	function sqool_parse($members)
	{	$isList = false;
		$isRef = false;
		$isRefList = false;
		
		$result = array();
		$index = sqool_getCertainChars($members, 0, " \t\n", "", $dumdum);
		//echo "ind: " . $index." Startarg: '".$dumdum."'<br/>\n";
		for($n=0;1;$n++)
		{	$result[$n] = sqool_parseSingle($members, $index);	// set the result array
			
			if(!sqool_parseComma($members, $index))					// get comma
			{	break;		// done parsing (no comma)
			}
		}
		
		return $result;
	}
	
?>
