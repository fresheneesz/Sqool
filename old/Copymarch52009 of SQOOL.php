<?php
	/*	To do:
			* Finish the error handling for sqool_parse 
			* Error in Create table if there are duplicate names
			* Figure out how to handle column names for primitives, primitive lists, objects, object lists, object references, and object reference lists
				* current plan: Use column names to denote the different types. Poll the object's member names until you get the right one
				* Another option: Have a separate table for object information, from which you get the type of each field
				* option 3: store the type of each field in the max_size attribute, find this using fetch_field.
	
	*/
	$sqool_DebugFlag = false;
	/*	Defines:	sqoolDebug		turns on or off debugging
					sqoolAccess		accesses a local database
					sqoolSetUp		sets up a new database
					sqoolClass		creates a new class type
					sqoolKillClass	deletes a class
					sqoolAddMember	adds a new member to a class (a new column). Modifies all object instances of this class with the new column.
					sqoolRmMember	removes a member of a class. Also deletes the member from all object instances of this class. Care should be taken - this action is permenant.
					sqoolCreate		creates a main object
					sqoolLoad		loads a main object
					class sqoolObj	a sqool object
						set				sets a member of an object
						get				gets a member of an object
						app				appends a value to a list
						len				gets the length of a list
						rm				removes an item from a list
						getMemberNames	gets a list containing the member names of the object
						getMemberTypes	gets a list containing the member types of the object
					
		Tables and naming conventions:
			* sqool_info			holds information about the state of sqool
			* sqool_classes			holds the class definitions
				* name					name of the class
				* members				string representing the class structure (the members of the class)
				* nextOpenSlot			the next id for a object of this class
			* sqool_objects			holds the objects that have been created, along with their class type
				* name
				* class
			* U_NAME				holds a main object called "NAME" (U for user)
			* sqool_CLASSNAME_NUM	holds a sub-object of class type CLASSNAME and ID number NUM
			* sqool_array_NUM		holds an array
				* elements				the elements of the array
	 */
	
	$sqool_list 	= 255;
	$sqool_ref 		= 254;
	$sqool_refList 	= 253;
	$sqool_obj 		= 252;
	
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
		}
		else if(!mysql_select_db($databaseName))
		{	echo "Error accessing ".$databaseName>": " . mysql_error();
		}
	}
	
	// creates and sets up a table to store classes - only needs to be done once for a database
	// can create a database if your host allows you to, otherwise must be done on an existing table (does not delete existing content)
	function sqoolSetUp($databaseName, $user, $password)
	{	$con=mysql_connect(localhost,$user,$password);
		if(!$con)
		{	die("Unable to select database ".$databaseName);
		}
		else if(!mysql_query("CREATE DATABASE ".$databaseName,$con))
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
		
		mysql_close($con);
	}
	
	/* 	 $name is a string - the name of the object 
		 $members is a string in the format "type:name, type2:name2, ..." etc
		 Types: bool, string, tinyint, int, bigint, float, double, binary, object (write objectname)
	*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
	function sqoolClass($name, $members)
	{	return sqlquery
		(	'INSERT INTO sqool_classes VALUES
			(	"U_'.$name.'",	
				"'.$members.'",
				"0"
			)'
		);
	}
	
	// deletes a class
	function sqoolKillClass($name)
	{	return sqlquery
		(	'DELETE FROM sqool_classes WHERE name=U_'.$name
		);
	}
	
	// changes an array of the form (name, type, list, ref, ref list) into mySQL types
	// returns an array in the form (name, mySQLtype) on success, 0 on failure
	function sqoolTypeConvert($member)
	{	$type="";
		if($member[2])		// list
		{	$type = "VARCHAR(".$sqool_list.") NOT NULL";
		}
		else if($member[3])	// ref
		{	$type = "VARCHAR(".$sqool_ref.") NOT NULL";
		}
		else if($member[4])	// ref list
		{	$type = "VARCHAR(".$sqool_refList.") NOT NULL";
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
						{	return 0;
						}
			}
		}
		return array($member[0], $type);
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
	
	// creates the table that represents an object
	function sqool_create_object($classname, $physicalName)
	{	if(sql_exists($physicalName))
		{	return 0;	// can't recreate an already existing object
		}
		
		//	find the class definition
		$results = sqlquery
		(	'SELECT members FROM sqool_classes WHERE name="'.$classname.'"'
		);
		 
		$membersArray = sqool_parse($members);					// parse the class definition
		if($membersArray==false){return false;/*error*/}
		$columnArray = array();
		$memberCount = count($membersArray);
		
		for($n=0; $n < $memberCount; $n++)
		{	$nextType = sqoolTypeConvert($membersArray[$n]);
			if($nextType != 0)
			{	$columnArray[] = $nextType;
			}
			else
			{	return 0;	// invalid classtype (type error from sqoolTypeConvert)
			}
		}

		$result = createTable($physicalName, $columnArray);		// create the object
		
		if($result != FALSE)
		{	// create space for member variables
			$query = 'INSERT INTO '.$physicalName.' VALUES(';
			
			$createdTables = array($physicalName);	// keep track of the tables you create here, in case you have to delete them
			for($n=0; $n < $memberCount; $n++)
			{	if($n!=0)
					$query .=', ';
					
				if(	$membersArray[$n][2] && $membersArray[$n][3] || 
					$membersArray[$n][3] && $membersArray[$n][4] || 
					$membersArray[$n][2] && $membersArray[$n][4])
				{	echo "Error: a member can't be multiple types (out of ref, list, and ref list). I know this isn't your fault... its my fault. : (";
				}
				
				if($membersArray[$n][2] || $membersArray[$n][4])			// list or ref list
				{	// create list table
					sqlquery('UPDATE sqool_info SET arrayNum=LAST_INSERT_ID(arrayNum+1)');
					$result = mysql_insert_id()-1;
					createTable("sqool_array_".$result, array(array("indecies", "TEXT"), array("elements", $columnArray[1])) );
					
					$query .='"'."sqool_array_".$result.'"';	// write a pointer to the list that was just created
				}
				else if($membersArray[$n][3])	// ref
				{	$query .='"0"';	// null reference
				}
				else									// primitive or object
				{	switch($membersArray[$n][1])
					{case "bool":
					 case "string":	case "bstring":	case "gstring":	
					 case "tinyint":case "int":		case "bigint":
					 case "float":	case "double":	// if it is a primitive...
						$query .='"0"';
						break;
					 default:	// if it is an object (the object's class was already checked by sqoolTypeConvert)
						
						// get next ID for object of this class
						sqlquery('UPDATE sqool_info SET nextOpenSlot=LAST_INSERT_ID(nextOpenSlot+1) WHERE name="'.$classname.'"');
						$result = mysql_insert_id()-1;
						
						// create object table
						sqool_create_object("sqool_".$columnArray[1]."_".$result, $columnArray[1]);
						$query .='"'."sqool_".$columnArray[1]."_".$result.'"';	// write a pointer to the object that was just created
					}
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
	{	$result = sqool_create_object("U_".$classname, "U_".$name);	// create the object
		
		if($result!=false)	// insert object into sqool_objects table
		{	sqlquery
			(	'INSERT INTO sqool_objects VALUES
				(	"U_'.$classname.'",
					"U_'.$name.'"
				)'
			);
		}		
		return $result;
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
	
	class sqoolObj
	{	public $physicalName;	// the actual name of the table representing this object
		
		// For internal use only
		function setList($memberName, $value, $isRefList)
		{	$results = sqlquery('SELECT * FROM '.$physicalName);	// ..
			$tableName = mysql_result($results, 0, $memberName);	// get the name of the table that holds the list
			sqlquery('DELETE * FROM '.$tableName);			// delete contents of table
			
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
			
		function set($memberName, $value)
		{	// get the type of member $memberName
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$physicalName);
			if($results == false)
			{	if($sqool_DebugFlag)
				{	echo "<br/>Error: Attempted to set a member ".$memberName." which couldn't be found in table ".$physicalName.".<br/>";
				}
				throw new InvalidArgumentException("Attempted to access ".$memberName." which couldn't be found in table ".$physicalName.".<br/>");
				echo "<br/>Shouldn't get here<br/><br/>";
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
				{	$this->setList("U_".$memberName, $value, true);
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
			$results = sqlquery('SELECT U_'.$memberName.' FROM '.$physicalName);
			if($results == false)
			{	if($sqool_DebugFlag)
				{	echo "<br/>Error: Attempted to get a member ".$memberName." which couldn't be found in table ".$physicalName.".<br/>";
				}
				throw new InvalidArgumentException("Attempted to access ".$memberName." which couldn't be found in table ".$physicalName.".<br/>");
				echo "<br/>Shouldn't get here<br/><br/>";
			}
			$fieldInfo = mysql_fetch_field ($results, 0);
			
			if($fieldInfo->type == "string")	 // "string" indicates that it is a list, ref, reflist, or object type (which are stored as varchars)
			{	if($fieldInfo->max_length == $sqool_list)	// list
				{	return getList($memberName, false);
				}
				else if($fieldInfo->max_length == $sqool_ref)	// reference
				{	sqoolLoad( substr(mysql_result($results, 0, "U_".$memberName),2) );
				}
				else if($fieldInfo->max_length == $sqool_refList)	// list of references
				{	return getList($memberName, true);
				}
				else if($fieldInfo->max_length == $sqool_obj)	// object
				{	sqoolLoad( substr(mysql_result($results, 0, "U_".$memberName),2) );
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
		{	sqlquery
			(	'INSERT INTO U_'.$physicalName.' VALUES
				(	
				)'
			);
		}
		
		// get an element from a list (list or ref list)
		function getElem($memberName, $index)
		{	
		}
		
		// set an element in a list (list or ref list)
		function setElem($memberName, $index, $value)
		{	
		}
		
		function rmElem($memberName, $index)			// delete an element of a list
		{	sqlquery
			(	'DELETE FROM contacts WHERE id=7'
			);
		}
		
		// get the number of elements in a list
		function count($memberName)			
		{	return mysql_numrows($something);
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
			echo "<br/>\n";
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
	{	$results = sqlquery
		(	'SELECT * FROM '.$physicalName
		);
		if($results==FALSE)
		{	global $sqool_DebugFlag;
			if($sqool_DebugFlag)	// let the user know that the error that printed is normal
			{	echo "<br/>THE ERROR ABOVE IS EXPECTED - IT IS NOT A PROBLEM. (Turn off debugging to get rid of these error messages).<br/>";
			}
			return FALSE;
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
	
	// parses type declarations for a sqool class
	function sqool_parse($members)
	{	$isList = false;
		$isRef = false;
		$isRefList = false;
		
		$result = array();
		$index = sqool_getCertainChars($members, 0, " \t\n", "", $dumdum);
		//echo "ind: " . $index." Startarg: '".$dumdum."'<br/>\n";
		for($n=0;1;$n++)
		{	$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $type);		// get type
			if($numchars==0)	return false; 	// error if type isn't found
			$index += $numchars;
				
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $dumdum);		// get "list" or "ref" (if it exists)
			if($dumdum=="list")
			{	$isList = true;
			}
			else if($dumdum=="ref")
			{	$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
				$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $dumdum);	// get "list" (from a "ref list") if it exists
				
				if($dumdum=="list")		// "ref list" 
				{	$isRefList = true;
				}
				else if($numchars==0)	// "ref" 
				{	$isRef = true;
				}
				else		// something other than "ref" or "ref list" is found
				{	echo "Error parsing types: 'list' expected but got'".$dumdum."'<br/>\n";
					return false;
				}	
				$index += $numchars;
			}
			else if($numchars==0)
			{	// isn't a list or a ref (or a ref list)
			}
			else			// something other than "list" or "ref" is found
			{	echo "Error parsing types: 'list' or 'ref' expected but got'".$dumdum."'<br/>\n";
				return false;
			}			
			$index += $numchars;
						
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			$numchars = sqool_getCertainChars($members, $index, ":", "", $dumdum);			// get colon
			if($numchars==0)	return false; 	// error colon isn't found
			$index += $numchars;
			
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $name);		// get name
			if($numchars==0)	return false; 	// error if name isn't found
			$index += $numchars;
			
			$result[$n] = array("U_".$name, $type, $isList, $isRef, $isRefList);	// set the result array
			
			$index += sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			//echo "ARG4.5<br/>\n";
			$numchars = sqool_getCertainChars($members, $index, ",", "", $dumdum);			// get comma
			if($numchars==0)
			{	break;		// done parsing
			}
			$index += $numchars;
			
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		}
		
		return $result;
	}
	
?>
