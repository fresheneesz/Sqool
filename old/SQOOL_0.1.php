<?php
	/*	To do:
			* initList has problems rendering the type - help it out
			* 
			* Error in Create table if there are duplicate names
			* Error in sqoolAddMember if member duplicates a name
			* handle errors in count
			* handle errors in everything else
			*
			* test all array functions
	*/
	
	$sqool_DebugFlag = true;
	/*	Defines:	sqoolDebug		turns on or off debugging
					sqoolAccess		accesses a local database
					sqoolSetUp		sets up a new database
					
					sqoolClass		creates a new class type
						member types: bool, string, bstring, gstring, tinyint, int, float, :obj:, :obj: ref, :obj: ref list, :type: list
					sqoolKillClass	deletes a class
					sqoolAddMember	adds a new member to a class (a new column). Modifies all object instances of this class with the new column.
					sqoolRmMember	removes a member of a class. Also deletes the member from all object instances of this class. Care should be taken - this action is permenant.
					
					sqoolCreate		creates a main object (returns a sqoolObj)
					sqoolLoad		loads a main object (returns a sqoolObj)
					class sqoolObj	a sqool object
						set				sets a member of an object
						get				gets a member of an object
						app				appends a value to a list
						keyExists		returns true if an element for a certain array exists at a certain index
						getElem			gets an element of a list
						setElem			sets an element of a list
						rmElem			removes an element of a list
						count			gets the number of elements in a list
						getMemberNames	gets a list containing the member names of the object
						getMemberTypes	gets a list containing the member types of the object
					
		Tables and naming conventions:
			* sqool_info			holds information about the state of sqool
				* arrayNum				the next id for a list
			* sqool_classes			holds the class definitions
				* name					name of the class
				* members				string representing the class structure (the members of the class)
				* nextOpenSlot			the next id for an object of this class
			* sqool_objects			holds the objects that have been created, along with their class type
				* name
				* class
			* U_NAME				holds a main object called "NAME" (U for user)
				* U_MEMBER				example name of a member named "MEMBER"
				* SAC_MEMBER			array count for array member named "MEMBER" (SAC stands for sqool array count)
			* sqool_CLASSNAME_NUM	holds a sub-object of class type CLASSNAME and ID number NUM
				* U_MEMBER				example name of a member named "MEMBER"
			* sqool_array_NUM		holds an array
				* indecies				the indecies of the array (string type)
				* elements				the elements of the array
	 */
	
	$sqool_list 	= 255;	// ..
	$sqool_ref 		= 254;	// ..
	$sqool_refList 	= 253;	// ..
	$sqool_obj 		= 252;	// enums - holds a code representing each of those things (list, ref, ref list, object)
	
	// turns debugging on or off
	function sqoolDebug($setting)
	{	global $sqool_DebugFlag;		// php requires I do this to be able to access global variables..
		$sqool_DebugFlag = $setting;
	}
	
	// access a local database
	function sqoolAccess($databaseName, $user, $password)
	{	$con=mysql_connect(localhost,$user,$password);
		if(!$con)
		{	die("Unable to connect to mysql.");
			return 0;
		}
		else if(!mysql_select_db($databaseName))
		{	echo "Error accessing ".$databaseName>": " . mysql_error();
			return 0;
		}
		return $con;
	}
	
	// creates and sets up a table to store classes - only needs to be done once for a database
	// can create a database if your host allows you to, otherwise must be done on an existing table (does not delete existing content)
	function sqoolSetUp($databaseName, $user, $password)
	{	if(!mysql_query("CREATE DATABASE ".$databaseName))
		{	if(!mysql_select_db($databaseName))
			{	echo "Error creating database: " . mysql_error();
			}
		}		
		
		// sqool_info holds information about the state of sqool (right now this is just the next number for sqool_array tables)
		// make sure "NOT NULL" still allows an int to hold 0
		sqlquery
		(	'CREATE TABLE sqool_info
			(	arrayNum int NOT NULL
			)'
		);
		sqlquery
		(	'INSERT INTO sqool_info VALUES
			(	"0"
			)'
		);
		
		// sqool_classes holds definitions for defined class types used to instantiate database objects
		// next open slot is for naming new tables that represent member objects inside a main object
		sqlquery
		(	'CREATE TABLE sqool_classes
			(	name varchar(255) NOT NULL PRIMARY KEY,
				members varchar(255) NOT NULL,
				nextOpenSlot varchar(255) NOT NULL	
			)'
		);
		
		// sqool_objects holds the class types for each main database object
		sqlquery	
		(	'CREATE TABLE sqool_objects
			(	name varchar(255) NOT NULL PRIMARY KEY,
				class varchar(255) NOT NULL
			)'
		);
	}
	
	/* 	 $name is a string - the name of the object 
		 $members is a string in the format "type:name, type2:name2, ..." etc
		 Types: bool, string, tinyint, int, bigint, float, double, binary, object (write objectname)
	*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
	function sqoolClass($className, $members)
	{	return sqlquery
		(	'INSERT INTO sqool_classes VALUES
			(	"U_'.$className.'",	
				"'.$members.'",
				"0"
			)'
		);
	}
	
	// deletes a class
	function sqoolKillClass($className)
	{	return sqlquery
		(	'DELETE FROM sqool_classes WHERE name=U_'.$className
		);
	}
	
	// adds a single new member (in the form "type:name") to a class (a new column). 
	// Modifies all object instances of this class with the new member.					
	function sqoolAddMember($className, $memberTypeAndName)
	{	// add member (and type) to the end of the members string for the given class
		sqlquery('UPDATE sqool_classes SET members=CONCAT(members,'.$memberTypeAndName.') WHERE name=U_'.$className);

		$members = sqool_parse($memberTypeAndName);
		$columnArray = sqoolTypeConvert($members);	// convert type
		$results = sqlquery('SELECT * FROM sqool_objects WHERE class="U_'.$className.'"');		
		$num=mysql_numrows($results);
		for($n=0;$n<$num;$n++)	// loop through the sqool_objects table for rows that have "class" of $className
		{	// get table name
			$tableName = mysql_result($results, $n, "name");
			// add the member to the next object
			sqlquery('ALTER TABLE '.$tableName.' ADD '.$columnArray[$n][0].' '.$columnArray[$n][1]);
			
			// initialize that member
			$queryPiece = initMember($members[$n], $columnArray[$n][0]);
			sqlquery('UPDATE '.$tableName.' SET '.$queryPiece.' WHERE name=U_'.$className);
		}
	}
	
	// removes a member of a class. 
	// Also deletes the member from all object instances of this class. 
	// Care should be taken - this action is permenant.
	// returns false on error (member can't be found)
	function sqoolRmMember($className, $memberName)
	{	// add member (and type) to the end of the members string for the given class
		$results = sqlquery('SELECT members FROM sqool_classes WHERE name=U_'.$className);
		$classMembers = mysql_result($results, 0, $memberName);
		
		$index=0;
		for($n=0;1;$n++)		// loop through members till you find the right one
		{	$indexBefore = $index;
			$result = sqool_parseSingle($members, $index);
			if($result[0] == "U_".$memberName)	// if got member
			{	// remove it
				sqlquery('UPDATE sqool_classes SET members=REPLACE(members, '.substr($members, $indexBefore, $index-$indexBefore).', "") WHERE name=U_'.$className);
				break;
			}
			
			if(!sqool_parseComma($members, $index))					// get comma
			{	return 0;		// done parsing (no comma - and no member found)
			}
		}
		
		// remove member from objects
		$results = sqlquery('SELECT * FROM sqool_objects WHERE class="U_'.$className.'"');		
		$num=mysql_numrows($results);
		for($n=0;$n<$num;$n++)	// loop through the sqool_objects table for rows that have "class" of $className
		{	// get table name
			$tableName = mysql_result($results, $n, "name");
			// remove the member from the next object
			sqlquery('ALTER TABLE '.$tableName.' DROP '.$columnArray[$n][0]);
		}		
		
		return true;
	}
	
	// changes $membersArray, an array with elements of the form (name, type, list, ref, ref list) into mySQL types
	// returns an array with elements of the form (name, mySQLtype) on success, 0 on failure
	function sqoolTypeConvert($membersArray)
	{	global $sqool_list;
		global $sqool_ref;
		global $sqool_refList;
		global $sqool_obj;
		
		if($membersArray == 0)
		{	return 0;	// propogate error
		}
		
		$columnArray = array();
	
		$memberCount = count($membersArray);
		for($n=0; $n < $memberCount; $n++)
		{	$member = $membersArray[$n];
			$type="";
			if($member[2])		// list
			{	$type = "VARCHAR(".$sqool_list.") NOT NULL";
			
				// extra column for list count
				$columnArray[] = array("SAC".substr($member[0],1), "INT");
			}
			else if($member[3])	// ref
			{	$type = "VARCHAR(".$sqool_ref.") NOT NULL";
			}
			else if($member[4])	// ref list
			{	$type = "VARCHAR(".$sqool_refList.") NOT NULL";
			
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
				 			{	$type = "VARCHAR(".$sqool_obj.") NOT NULL";
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
	
	// returns true if the class $className exists
	function sqoolClassExists($className)
	{	if(sqlquery('SELECT members FROM sqool_classes WHERE name="U_'.$classname.'"') != false)
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
		createTable("sqool_array_".$result, array(array("indecies", "TEXT"), array("elements", $type)) );
		
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
	function sqool_create_object($classname, $physicalName, $initObj=0)
	{	if(sql_exists($physicalName))
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
		
		$result = createTable($physicalName, $columnArray);		// create the object
		
		if($result != FALSE)
		{	sqlquery		// insert table into table of objects (for potential later modification)
			(	'INSERT INTO sqool_objects VALUES
				(	"'.$physicalName.'",
					"'.$classname.'"
				)'
			);
			
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

	// creates a new database object
	// returns a sqoolObj
	function sqoolCreate($classname, $name)
	{	return sqool_create_object("U_".$classname, "U_".$name);	// create the object
	}
	
	// loads a main database object
	// returns a sqoolObj
	function sqoolLoad($name)
	{	if(sqoolExists($name))
		{	$result = new sqoolObj();
			$result->physicalName = "U_".$name;
			return $result;
		}else
		{	return 0;			// can't load a non existant object
		}
	}
	
	function sqoolExists($objectName)
	{	return sql_exists("U_".$objectName);
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
	
	class sqoolObj
	{	public $physicalName;	// the actual name of the table representing this object
			
		function set($memberName, $value)
		{	// get the type of member $memberName
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$this->physicalName);
			if($results == false)
			{	sqool_handleError("<br/>Error: Attempted to set a member ".$memberName." which couldn't be found in table ".$this->physicalName.".<br/>");
			}
			$fieldInfo = mysql_fetch_field ($results, 0);
			
			if($fieldInfo->type == "string")	 // "string" indicates that it is a list, ref, reflist, or object type (which are stored as varchars)
			{	if($fieldInfo->max_length == $sqool_list)	// list
				{	$this->setList("U_".$memberName, $value, false);
				}
				else if($fieldInfo->max_length == $sqool_ref)	// reference
				{	sqlquery('UPDATE '.$physicalName.' SET U_'.$memberName.'="'.$value->physicalName.'"');
				}
				else if($fieldInfo->max_length == $sqool_refList)	// list of references
				{	$this->setList($this->physicalName, "U_".$memberName, $value, true);
				}
				else if($fieldInfo->max_length == $sqool_obj)	// object
				{	// copy object
					$memberList = $this->getMemberNames();
					$memberList_len = count($memberList);
					for($n=0;$n<$memberList_len;$n++)		// loop through memebers
					{	$this->set(substr($memberList[$n], 2), $value->$get(substr($memberList[$n], 2)));	// set member
					}
				}
				else
				{	return 0; // error: invalid 
				}
			}
			else	// primitive
			{	sqlquery('UPDATE '.$this->physicalName.' SET U_'.$memberName.'="'.$value.'"');
			}
		}
		
		// For internal use only
		function getList($memberName, $isRefList)
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
		
		// throws an error if an invalid member is accessed
		function get($memberName)
		{	// get the type of member $memberName
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$this->physicalName);
			if($results == false)
			{	sqool_handleError("<br/>Error: Attempted to get a member ".$memberName." which couldn't be found in table ".$physicalName.".<br/>");
			}
			$fieldInfo = mysql_fetch_field($results, 0);
			
			if($fieldInfo->type == "string")	 // "string" indicates that it is a list, ref, reflist, or object type (which are stored as varchars)
			{	if($fieldInfo->max_length == $sqool_list)	// list
				{	return getList($memberName, false);
				}
				else if($fieldInfo->max_length == $sqool_ref)	// reference
				{	return sqoolLoad( substr(mysql_result($results, 0, "U_".$memberName),2) );
				}
				else if($fieldInfo->max_length == $sqool_refList)	// list of references
				{	return getList($memberName, true);
				}
				else if($fieldInfo->max_length == $sqool_obj)	// object
				{	return sqoolLoad( substr(mysql_result($results, 0, "U_".$memberName),2) );
				}
				else
				{	return 0; // error: invalid 
				}
			}
			else	// primitive
			{	return mysql_result($results, 0, "U_".$memberName);
			}
		}
		
		// append a value to the end of a list
		function app($memberName, $value)	
		{	// get and incriment the array count
			sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=1+LAST_INSERT_ID(SAC_'.$memberName.')');
			$index = mysql_insert_id();
			
			$results = sqlquery('SELECT * FROM '.$this->physicalName);				// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			if($sqool_DebugFlag)
			{	if(sqool_rowExists($physicalTableName, "indecies", $index))	// if that index already exists, error
				{	sqool_handleError("<br/>Error: Append's next index for member ".$memberName." in table ".$this->physicalName." already exists. This shouldn't happen.<br/>");
				}
			}
			
			// get the type
			$results = sqlquery('SELECT elements FROM '.$physicalTableName);
			if($results == false)
			{	sqool_handleError("<br/>Error: List ".$physicalName." being used as an array, but it doesn't have an 'elements' column.<br/>");
			}
			$fieldInfo = mysql_fetch_field ($results, 0);
			
			if($fieldInfo->type == "string")	 // "string" indicates that it is a list, ref, reflist, or object type (which are stored as varchars)
			{	if($fieldInfo->max_length == $sqool_list)	// list
				{	// should't happen - error (list of lists arent supported)
					sqool_handleError("<br/>Error: Member ".$memberName." in table ".$this->physicalName." seems to have a list of lists. This shouldn't happen.<br/>");
				}
				else if($fieldInfo->max_length == $sqool_ref)	// reference
				{	sqlquery
					(	'INSERT INTO '.$physicalTableName.' VALUES("'.$index.'", "'.$value->physicalName.'")'
					);
				}
				else if($fieldInfo->max_length == $sqool_refList)	// list of references
				{	// should't happen - error
					sqool_handleError("<br/>Error: Member ".$memberName." in table ".$this->physicalName." seems to have a list of lists of refernces. This shouldn't happen.<br/>");
				}
				else if($fieldInfo->max_length == $sqool_obj)	// object
				{	// copy object
					$memberList = $this->getMemberNames();
					$memberList_len = count($memberList);
					for($n=0;$n<$memberList_len;$n++)		// loop through memebers
					{	$this->set(substr($memberList[$n], 2), $value->$get(substr($memberList[$n], 2)));	// set member
					}
				}
				else
				{	sqool_handleError("<br/>Error: Member ".$memberName." in table ".$this->physicalName." has the invalid maxlength indicator ".$fieldInfo->max_length.". This shouldn't happen. (The maxlength indicator indicates whether a value represents an object, reference, list, or reference list and can only hold values 252 to 255<br/>");
				}
			}
			else	// primitive
			{	sqlquery
				(	'INSERT INTO '.$physicalTableName.' VALUES("'.$index.'", "'.$value.'")'
				);
			}
		}
		
		// returns true if there exists a value at $index for an array
		function keyExists($memberName, $index)
		{	if($index<0)
			{	return 0;	// error - bad index
			}

			$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))			// if the element already exists
			{	return true;
			}
			else
			{	return false;
			}
		}
		
		// get an element from a list (list or ref list)
		function getElem($memberName, $index)
		{	if($index<0)
			{	return 0;	// error - bad index
			}
			
			$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			$results = sqlquery('SELECT "'.$index.'" FROM '.$physicalTableName);	// ..
			return mysql_result($results, 0, "elements");							// get value
		}
		
		// set an element in a list (list or ref list)
		function setElem($memberName, $index, $value)
		{	if($index<0)
			{	return 0;	// error - bad index
			}
			// set "SAC_".$membername if it is lower than $index
			sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'<'.$index.','.$index.',SAC_'.$memberName.')');
			
			$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element already exists, update it with the new value
			{	sqlquery('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
			}
			else	// create it
			{	sqlquery('INSERT INTO '.$physicalTableName.' VALUES("'.$index.'", "'.$value.'")');
			}				
		}
		
		function rmElem($memberName, $index)			// delete an element of a list
		{	if($index<0)
			{	return 0;	// error - bad index
			}
			// set "SAC_".$membername if it is equal to the $index
			sqlquery('UPDATE '.$this->physicalName.' SET SAC_'.$memberName.'=if(SAC_'.$memberName.'='.$index.','.$index.',SAC_'.$memberName.')');
			
			$results = sqlquery('SELECT * FROM '.$this->physicalName);			// ..
			$physicalTableName = mysql_result($results, 0, "U_".$memberName);	// get name of table that holds the list
			
			if(sqool_rowExists($physicalTableName, "indecies", $index))	// if the element exists, delete it
			{	sqlquery('UPDATE '.$physicalTableName.' SET U_'.$memberName.'='.$value);
				sqlquery('DELETE FROM '.$physicalTableName.' WHERE indecies='.$index);
			}
		}
		
		// get the number of elements in a list
		function count($memberName)			
		{	$results = sqlquery('SELECT U_'.$memberName.' FROM '.$physicalName);
			if($results == false)
			{	sqool_handleError("<br/>Error: Attempted to count a list member ".$memberName." which couldn't be found in table ".$physicalName.".<br/>");
			}
			$listTable = mysql_result($results, 0, "U_".$memberName);
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$listTable);
			return mysql_numrows($results);
		}
		
		function getMemberNames()
		{	$results = sqlquery
			(	'SHOW COLUMNS FROM '.$physicalName
			);
			
			$memberNames = array();
			
			$num=mysql_numrows($results);
			for($i=0; $i < $num; $i++) 
			{	$memberNames[] = mysql_result($results, $i);
			}
			return $memberNames;
		}
		
		function getMemberTypes($member)
		{	$results = sqlquery
			(	'SELECT '.$member.' FROM '.$physicalName
			);
			return mysql_field_type($results);
		}
	};
	
	
	
	/********************** BELOW THIS ARE FUNCTIONS MEANT FOR INTERNAL USE ONLY *************************/
	
	// performs an sql query, and echos error information if sqool_DebugFlag is on
	function sqlquery($query)
	{	global $sqool_DebugFlag;	// this declares that this function uses a global variable
		
		$result = mysql_query($query);
		if($sqool_DebugFlag==TRUE && FALSE == $result)
		{	echo "* The error is: " . mysql_error() . "<br/>";
			print_r(debug_backtrace());
			echo "<br/><br/>\n";
		}
		
		return $result;
	}
	
	// creates a mysql table named $name and that has columns named $columns[X][0] of type $columns[X][1] where X is arbitrary
	function createTable($name, $columns)
	{	$query = 'CREATE TABLE '.$name.' (';
		
		$end = count($columns);
		for($n=0; $n<$end; $n++)
		{	if($n!=0)
			{	$query .= ", ";
			}
			$query .= $columns[$n][0] . " " . $columns[$n][1];	// name space type
		}
		$query.=")";
		
		return sqlquery($query);
	}
	
	// returns true if a table named $physicalName exists
	function sql_exists($physicalName)
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
