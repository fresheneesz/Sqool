<?php
	include_once("cept.php");	// exceptions with stack traces
	
	/*	To do:
			* lazy connection (don't connect if you don't really need to)
			* impliment a "save" function for saving data (so that 'set' data isn't really set until its saved)
			* initList has problems rendering the type - help it out
			* 
			* Error in Create table if there are duplicate names
			* Error in sqoolAddMember if member duplicates a name
			* handle errors in count
			* handle errors in everything else
			*
			* test all array functions
			* Make sure all parsing functions are guarded against injection attacks
	*/
	
	/*	internal funcs that need to be written (because they are currently being used):
				done 	createTable($con, "sqool_info", array("arrayNum", "int NOT NULL"));
			sqool_parseX($members);
			sqool_parseWSList($members);
			sqool_isAnObject($class, $name);
			sqool_insert("U_".$classname, $inits);	// should return the new ID
			
				done 	insertIntoTable($con, "sqool_info", array(array("0")));
			sqoolTypeConvert($newMembers);	// should output array with members of the form (mysqlType, name, code, base type)
			sqool_addMembersToTable($tableName, $members);
			sqool_initMembersOfTable($tableName, $members);
			
			sqool_removeMembersFromString(,);
			sqool_getMemberType("U_".$memberName);
			sqool_copy_object();
			sqool_nextIndex($this->physicalName, $memberName);		// get and incriment the array count
			sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			
			
		internal funcs being removed
			sqool_parseSingle($members, $index);
			sqool_parseComma($members, $index)
			
		funtions need finishing
			rmMember
	*/
	
	/*	Defines:		
			sqoolDebug		turns on or off debugging messages
			sqoolAccess		accesses a local database
			class sqoolCon	connection to a database
				getDB		returns a connection to another database on the same host
				makeClass	creates a new class type. Member types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
				killClass	deletes a class (and all the objects of that class)
				hasClass	checks if a class exists
				addMembers	adds new members to a class (a new column). Modifies all object instances of this class with the new members.
				rmMember	removes members of a class. Also deletes the members from all object instances of this class. Care should be taken - this action is permenant.
				make		creates a main object (returns a sqoolObj)
				hasObj		checks if an object exists
				fetch		returns members of one or more objects (returns as an array of one or more sqoolObj objects). See the function for its use.
			
			class sqoolObj	a sqool object
				fetch			returns members of one or more objects (returns as an array of one or more sqoolObj objects). See the function for its use.
				set				sets a member of an object
				app				appends a value to a list
				setElem			sets an element of a list
				rmElem			removes an element of a list
				count			gets the number of elements in a list
				getMemberNames	gets a list containing the member names of the object
				getMemberTypes	gets a list containing the member types of the object
					
					
		Tables and naming conventions (for the tables that make up the objects under the covers):
			* U_CLASSNAME			holds all the objects of a certain type
				* ID					the ID of the object
				* U_MEMBER				example field-name of a member named "MEMBER". 
										If this is a primitive, it will hold a value. 
										If this is a list or object, it will hold an ID. Objects are treated as references - they all start with a null id (note this is not the same as ID 0).
				* class_MEMBER			example field-name for the classtype of MEMBER (only necessary if MEMBER has a class type)
			* sqool_lists			holds all the lists in the database
				* ID					the ID of the list that owns the object
				* objectID				the ID of the object the list owns
				* class					classname of the object the list owns
	 */
	
	
	
	

	
	// Turns debugging on or off (on by default)
	// $setting is true for 'on', false for 'off'
	function sqoolDebug($setting)
	{	global $sqool_DebugFlag;		// php requires I do this to be able to access global variables..
		$sqool_DebugFlag = $setting;
	}
	
	// performs lazy connection (only connects upon actual use)
	class sqool			// connection to a database
	{	private $con;		// the current selected connection (for accessing multiple databases at once)
		private $username, $password, $host, $database;
		public static $debugFlag=true;
		
		const sqool_list 		= 255;	// ..
		const sqool_obj 		= 254;	// ..
		const sqool_primitive	=   0;	// enums - holds a code representing each of those things (list, ref, ref list, object)
		
		const sqool_connection_failure 			= 0;
		const sqool_database_creation_error 	= 1;
		const sqool_class_already_exists	 	= 2;
		const sqool_nonexistant_object	 		= 3;
		const sqool_append_error		 		= 4;
		const sqool_invalid_variable_name 		= 5;
		
		// Access a local database - attempts to create database if it doesn't exist
		// Can create a database if your host allows you to, otherwise database must already exist
		// reteurns a sqool object
		// the $conIn variable is for internal use
		function _construct($usernameIn, $passwordIn, $databaseIn, $hostIn='localhost', $conIn=false)
		{	require_AZaz09($databaseIn);
			
			$username = $usernameIn;
			$password = $passwordIn;
			$host = $hostIn;
			$database = $databaseIn;
			
			$con = $conIn;
		}
		
		// returns a connection to another database on the same host
		function getDB($databaseName)		
		{	return new sqool($username, $password, $host, $databaseName, $con);
		}
		
		public static debug($setting)
		{	if($setting === true)
			{	$debugFlag = true;
			}else
			{	$debugFlag = false;
			}
		}
		
		// if the object is not connected, it connects
		// returns true if a new connection was made
		private static function connectIfNot()
		{	if($con === 'none')
			{	//connect
				$con = new mysqli($host, $username, $password, $database);
				
				if(mysqli_connect_error())
				{	$errnum = mysqli_connect_errno();
					die('Connect Error (' . $errnum . ') ' . mysqli_connect_error());
					if($errnum == 0)
					{	// do something
					}else if ($errnum == 'unable to connect')
					{	throw new cept("Unable to connect to mysql.", sqool_connection_failure);
					}else if ($errnum == 'database doesn't exist')
					{	// create database
						
					}
				}
				return true;
			}
			return false;
		}
		
		/* 	 Creates a new class
			 $className is a string - the name of the class 
			 $members is a string in the format "type:name type2:name2 type3:name3" etc
			 	Types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
		*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
		//// Objects (a class typed variable) are treated as reference - they start with a null pointer
		function makeClass($className, $members)
		{	require_AZaz09($className);
			if(hasClass($className))
			{	throw new cept("Cannot create class - '".$className."' already exists.", sqool_class_already_exists);
			}
			
			// parse members
			$parsedMembers = sqool_parseX($members);	// should output array with members of the form ("name" => name, "type" = > mysqlType)			
			
			// create table for the class
			createTable($con, "U_".$className, $parsedMembers);
		}
		
		// Deletes a class's table - note that this leaves intact all the fields of objects that referenced objects of this class
		function killClass($className)
		{	require_AZaz09($className);
			sqlquery("DROP TABLE U_".$className, $con);	// delete (drop) the table
		}		
		
		// returns true if the class $className exists
		function hasClass($className)
		{	require_AZaz09($className);
			if(sqlquery('SELECT * FROM "U_'.$classname.'"', $con) != false)
			{	return true;
			}else
			{	return false;
			}
		}
		
		// Adds new members (in the form "type:name etc:etc") to a class (new columns). 
		// Modifies all object instances of this class with the new members.	
		// $newMembers is in the form taken by newClass
		function addMembers($className, $newMembers)
		{	require_AZaz09($className);
			
			// parse members
			$parsedMembers = sqool_parseX($members);	// should output array with members of the form ("name" => name, "type" = > mysqlType)		
			
			// add the members
			$query = 'ALTER TABLE U_'.$className;
			foreach($parsedMembers as $m)
			{	$query .= " ADD ".$m["name"]." ".$m["type"];
			}
			sqlquery($query, $con);
		}
		
		// Removes members of a class. 
		// Also deletes the member from all object instances of this class. Care should be taken - this action is permenant.
		// $members is a string in the form of a comma separated list: eg "name name2 name3 ..." etc
		// returns false on error (member can't be found)
		function rmMembers($className, $rmMembers)
		{	require_AZaz09($className);
			$members = sqool_parseWSList($rmMembers);	// parse newMembers into an array
			
			// remove the members from the class
			$query = "ALTER TABLE U_".$className;
			foreach($members as $m)
			{	$query .= " DROP U_".$m;
				if(sqool_isAnObject($className, $m))
				{	// also removed its class_MEMBER field
					$query .= " DROP class_".$m;
				}
			}
			sqlquery($query, $con);	// drop the columns
		}
		
		// creates a new object
		// returns a sqoolObj
		// inits should be an associative array with members "name" => value
		function make($classname, $inits)
		{	$lowlevelInits = array();
			foreach($inits as $k => $v)
			{	$lowlevelArray["U_".$k] = mysql_real_escape_string($v);
			}
			$newID = sqool_insert("U_".$classname, $lowlevelInits);
			return new sqoolObj($classname, $newID, $con);		// create the object
		}
		
		function hasObj($classname, $ID)
		{	
		}
		
		// loads a list of main database objects
		// returns a sqoolObj
		// throws an error if an invalid member is accessed (if a non-existant member or object is attempted to be accessed)
		/*	
			.fetch(array
			(	"name" => objectSelection,
				"name2" => objectSelection2,
				etc.....
			));
			
			// the 'objectSelection' (objectSelection, objectSelection2, etc) should be represented as follows
			// op is a comparison operator: > < = 
			// for fields that are objects, the 'value' is a sqoolObj instance
			// an empty members array (e.g. "array(membera, memberb, etc)") means return all fields
			// the "sort", "items", "cond", and "ranges" keys are optional (tho one of the following MUST be given: "items", "cond", or "ranges")
			"name" => array
			(	"className" => array(membera, memberb, memberc, etc),
				"sort" => array("field", direction, "field2", direction2, etc, etc),			// the way to sort the returned data (direction should be "-", "+", or a number. "+" means increasing order [smallest first], "-" means decreasing order [largest first], and a number means sort by values closest to the number)
				"items" => array(objectID, object2ID, etc),										// objects selected by one or more IDs
				"cond" => array("field op", value, "andor", "field2 op", value2, "andor", etc), // objects selected by some kind of field conditions
				"ranges" => array(start, end, start2, end2, etc, etc)							// objects to return from the selected list by their position in the list (after being sorted).
			)
			
			// the 'members' (membera, memberb, memberx, membery, etc) should be represented as follows
			// the "sort", "cond", and "ranges" keys are optional (tho one of the following MUST be given: "cond", or "ranges")
			"fieldName"
			OR (for an object or list)
			array
			(	"fieldName" => array(memberx, membery, memberz, etc),
				
				// if "fieldName" is a list, the following keys apply:
				"sort" => array("field", direction, "field2", direction2, etc, etc),			// the way to sort the returned data (direction should be "-", "+", or a number. "+" means increasing order [smallest first], "-" means decreasing order [largest first], and a number means sort by values closest to the number)
				"cond" => array("field op", value, "andor", "field2 op", value2, "andor", etc), // objects selected by some kind of field conditions
				"ranges" => array(start, end, start2, end2, etc, etc)							// objects to return from the selected list by their position in the list (after being sorted).
			)
		*/
		function fetch($arguments)
		{	
		}
	}
	
	class sqoolObj
	{	public $classTable;		// the actual name of the table the object is held in
		public $ID;				// the ID of the object (inside the table $classTable)
		public $con;				// the connection handle	
		
		function _construct($physicalNameIn, $IDin, $conIn)
		{	$physicalName = $physicalNameIn;
			$ID = $IDin;
			$con = $conIn;
		}
		
		// fetches all the data asked for
		// throws an error if an invalid member is accessed
		// uses the format for fetching members in sqoolCon
		function fetch($memberName)
		{	// use sqoolCon's fetch to fetch
		}
		
		function set($memberName, $value)
		{	// get the type of member $memberName
			$mType = sqool_getMemberType("U_".$memberName);
			
			
			
			
			/*$results = sqlquery('SELECT U_'.$memberName.' FROM '.$this->physicalName, $con);
			if($results == false)
			{	sqool_handleError("<br/>Error: Attempted to set a member ".$memberName." which couldn't be found in table ".$this->physicalName.".<br/>");
			}
			$fieldInfo = mysql_fetch_field($results, 0);
			
			
			if($mType == "string")	 // "string" indicates that it is a list, ref, reflist, or object type (which are stored as varchars)
			{	if($fieldInfo->max_length == sqool_list)	// list
				{	
				}
				else if($fieldInfo->max_length == sqool_ref)	// reference
				{	
				}
				else if($fieldInfo->max_length == sqool_refList)	// list of references
				{	
				}
				else if($fieldInfo->max_length == sqool_obj)	// object
				{	
				}
				else
				{	return 0; // error: invalid 
				}
			}
			else	// primitive
			{	
			}
			*/
			
			if($mType == sqool_list)
			{	$this->setList("U_".$memberName, $value, false);
			}
			else if($mType == sqool_ref)
			{	sqlquery('UPDATE '.$physicalName.' SET U_'.$memberName.'="'.$value->physicalName.'"', $con->con);
			}
			else if($mType == sqool_refList)
			{	$this->setList($this->physicalName, "U_".$memberName, $value, true);
			}
			else if($mType == sqool_obj)
			{	sqool_copy_object();	// copy object
			
				/*$memberList = $this->getMemberNames();
				$memberList_len = count($memberList);
				for($n=0;$n<$memberList_len;$n++)		// loop through memebers
				{	$this->set(substr($memberList[$n], 2), $value->$get(substr($memberList[$n], 2)));	// set member
				}*/
			}
			else if($mType == sqool_primitive)
			{	sqlquery('UPDATE '.$this->physicalName.' SET U_'.$memberName.'="'.mysql_real_escape_string($value).'"', $con->con);
			}
			
		}
		
		// append a value to the end of a list
		function app($memberName, $value)	
		{	$index = sqool_nextIndex($this->physicalName, $memberName);		// get and incriment the array count
			//sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=1+LAST_INSERT_ID(SAC_'.$memberName.')');
			//$index = mysql_insert_id();
			
			$results = sqlquery('SELECT * FROM '.$this->physicalName, $con->con);	// ..
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
			//$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
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
			//$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
			//$physicalTableName = mysql_result($results, 0, "U_".$memberName);		// get name of table that holds the list
			
			$results = sqlquery('SELECT "'.$index.'" FROM '.$physicalTableName);	// ..
			return mysql_result($results, 0, "elements");							// get value
		}
		
		// set an element in a list (list or ref list)
		function setElem($memberName, $index, $value)
		{	if($index<0)
			{	throw new cept("Bad index passed in: '".$index."'.");
			}
			// set "SAC_".$membername if it is lower than $index
			sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'<'.$index.','.$index.',SAC_'.$memberName.')');
			
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element already exists, update it with the new value
			{	sqlquery('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
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
			sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'='.$index.','.$index.',SAC_'.$memberName.')');
			
			$physicalTableName = sqool_getListTable($this->physicalName, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element exists, delete it
			{	sqlquery('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
				sqlquery('DELETE FROM '.$physicalTableName.' WHERE indecies='.$index);
			}
		}
		
		// get the number of elements in a list
		function count($memberName)			
		{	$results = sqlquery('SELECT U_'.$memberName.' FROM '.$physicalName);
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
	};
	
	
	
	/********************** BELOW THIS ARE FUNCTIONS MEANT FOR INTERNAL USE ONLY *************************/
	
	function require_AZaz09($variable)
	{	$string = "".$variable;
		$theArray = str_split($string);
		foreach($theArray as $c)
		{	if(false == ('a' <= $c&&$c <= 'z' || 'A' <= $c&&$c <= 'Z' || '0' <= $c&&$c <= '9' || $c == '_'))
			{	throw new cept("Variable name expected - a string containing only alphanumeric characters and the character '_'.", sqool_invalid_variable_name);
			}
		}
	}
	
	// inserts a set of rows into a table
	// $rows must be an array of arrays of values
	function insertIntoTable($con, $tableName, $rows)
	{	$query = 'INSERT INTO '.$tableName.' VALUES ';
		$firstRowDone = false;
		foreach($rows as $row)
		{	if($firstRowDone)
			{	$query .= ", ";
			}else
			{	$firstRowDone = true;
			}
			
			$query .= "(";
			
			$firstValueDone = false;
			foreach($row as $v)
			{	if($firstValueDone)
				{	$query .= ", ";
				}else
				{	$firstValueDone = true;
				}
				$query .= '"' . $v . '"';
			}
			$query .= ")";
		}
		sqlquery($query, $con);
	}
	
		// For internal use only
	function sqool_getList($memberName, $isRefList)
	{	// access the table where the list is stored
		$table = mysql_result($results, $i, "U_".$memberName);
		$results = sqlquery('SELECT * FROM '.$table);
		
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
	{	if(sqlquery('SELECT * FROM '.$physicalTableName.' WHERE '.$columnName.'="'.$contents.'"') != false)
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
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// performs an sql query, and echos error information if sqool_DebugFlag is on
	function sqlquery($query, $con)
	{	global $sqool_DebugFlag;	// this declares that this function uses a global variable
		
		$result = mysql_query($query, $con);
		if($sqool_DebugFlag==TRUE && FALSE == $result)
		{	echo "* The error is: " . mysql_error($con) . "<br/>";
			print_r(debug_backtrace());
			echo "<br/><br/>\n";
		}
		
		return $result;
	}
	
	// creates a mysql table named $name and that has columns named $columns[X]["name"] of type $columns[X]["type"] where X is arbitrary
	// con is mysql connection resource
	function createTable($con, $name, $columns)
	{	$query = 'CREATE TABLE '.$name.' (';
		
		$end = count($columns);
		for($n=0; $n<$end; $n++)
		{	if($n!=0)
			{	$query .= ", ";
			}
			$query .= $columns[$n]["name"] . " " . $columns[$n]["type"];	// name space type
		}
		$query.=")";
		
		return sqlquery($query, $con->con);
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
	
	// tests if a character is in the list of "singles" or in one of the "ranges"
	function sqool_charIsOneOf($theChar, $singles, $ranges)
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
	
	// extracts a string from "theString" (beginning at "index") that is made up of the characters in "singles" or "ranges"
	// puts the result in "result" 
	function sqool_getCertainChars($theString, $index, $singles, $ranges, &$result)
	{	$result = "";
		$n=0;
		while(sqool_charIsOneOf($theString[$index+$n], $singles, $ranges))
		{	$result .= $theString[$index+$n];
			$n+=1;
		}
		return $n;
	}
	
	function sqool_parseSingle($members, &$index)
	{	$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $type);		// get type
		if($numchars==0)	return false; 	// error if type isn't found
		$index += $numchars;
			
		$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $listOrRefOrNone);		// get "list" or "ref" (if it exists)
		$index += $numchars;
		if($listOrRefOrNone=="list")
		{	$isList = true;
		}
		else if($listOrRefOrNone=="ref")
		{	$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $listOrNone);	// get "list" (from a "ref list") if it exists
			$index += $numchars;
			
			if($listOrNone=="list")		// "ref list" 
			{	$isRefList = true;
			}
			else if($numchars==0)	// "ref" 
			{	$isRef = true;
			}
			else		// something other than "ref" or "ref list" is found
			{	echo "Error parsing types: 'list' expected but got'".$listOrNone."'<br/>\n";
				return false;
			}	
		}
		else if($numchars==0)
		{	// isn't a list or a ref (or a ref list)
		}
		else			// something other than "list" or "ref" is found
		{	echo "Error parsing types: 'list' or 'ref' expected but got'".$listOrRefOrNone."'<br/>\n";
			return false;
		}
					
		$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		$numchars = sqool_getCertainChars($members, $index, ":", "", $dumdum);			// get colon
		if($numchars==0)	return false; 	// error colon isn't found
		$index += $numchars;
		
		$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $name);		// get name
		if($numchars==0)	return false; 	// error if name isn't found
		$index += $numchars;
		
		return array("U_".$name, $type, $isList, $isRef, $isRefList);
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
