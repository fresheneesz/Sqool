<?php
	$sqool_DebugFlag = false;
	/*	Defines:	sqoolDebug		turns on or off debugging
					sqoolAccess		accesses a local database
					sqoolSetUp		sets up a new database
					sqoolClass		creates a new class type
					sqoolKillClass	deletes a class
					sqoolCreate		creates a main object
					sqoolLoad		loads a main object
					class sqoolObj	a sqool object
						set				set a member of an object
						get				gets a member of an object
						app				appends a value to a list
						len				gets the length of a list
						rm				removes an item from a list
						getMemberNames	gets a list containing the member names of the object
						getMemberTypes	gets a list containing the member types of the object
					
		Tables and naming conventions:
			* sqool_classes			holds the class definitions
			* sqool_objects			holds the objects that have been created, along with their class type
			* user_NAME				holds a main object called "NAME"
			* sqool_CLASSNAME_NUM	holds a sub-object of class type CLASSNAME and ID number NUM
			* sqool_array_NUM		holds an array
	 */
	
	// turns debugging on or off
	function sqoolDebug($setting)
	{	global $sqool_DebugFlag;
	
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
			(	class varchar(255) NOT NULL PRIMARY KEY,
				name varchar(255) NOT NULL
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
			(	"'.$name.'",
				"'.$members.'",
				"0"
			)'
		);
	}
	
	// deletes a class
	function sqoolKillClass($name)
	{	return sqlquery
		(	'DELETE FROM sqool_classes WHERE name='.$name
		);
	}

	// creates a new database object
	// returns a sqoolObj
	function sqoolCreate($classname, $name)
	{	if($type==1)
		{	return 0;	// can't recreate an already existing object
		}
		
		$results = sqlquery
		(	'SELECT members FROM sqool_classes WHERE name="'.$classname.'"'
		);
		
		$members = mysql_result($results, 0, "members");
		print_r($members);
		$membersArray = sqool_parse($members);
		print_r($membersArray);
		
		$result = createTable($membersArray);
		
		if($result != FALSE)
		{	sqlquery
			(	'INSERT INTO sqool_objects VALUES
				(	"'.$class.'",
					"'.$name.'"
				)'
			);
			
			$result = new sqoolObj();
			$result->$physicalName = "user_".$name;
			return $result;
		}
		else
		{	return 0;
		}
	}
	
	// loads a main database object
	// returns a sqoolObj
	function sqoolLoad($name)
	{	$result = new sqoolObj();
	
		$result->$physicalName = "user_".$name;
		if(sqoolExists($physicalName))
		{	return $result;
		}else
		{	return 0;			// can't load a non existant object
		}
	}
	
	function sqoolExists($objectName)
	{	return sql_exists("user_".$objectName);
	}
	
	class sqoolObj
	{	public $physicalName;	// the actual name of the table representing this object
		
		function set($memberName, $value)
		{	if(is_array($value))
			{	$results = sqlquery('SELECT * FROM user_'.$objectName);//' ORDER BY id ASC');
				$tableName = mysql_result($results, 0, $memberName);
				if($tableName == "0")
				{	// create table
				}else
				{	sqlquery('DELETE * FROM '.$tableName);	// delete contents of table
				}
				
				for($n=0; $n<count($value); $n++)
				{	sqlquery('INSERT INTO '.$tableName.' VALUES("'.$value[$n].'")');
				}
			}else if(gettype($value)=="sqoolObj")
			{	sqlquery('UPDATE '.$physicalName.' SET '.$memberName.'="'.$value->physicalName.'"');
			}else
			{	sqlquery('UPDATE '.$physicalName.' SET '.$memberName.'="'.$value.'"');
			}
		}
		
		function get($memberName)
		{	$results = sqlquery('SELECT '.$member.' FROM user_'.$physicalName);//' ORDER BY id ASC');
			
			
			
			if(mysql_field_type($results)=="string")	
			{	$results = sqlquery('SELECT '.$member.' FROM user_'.$physicalName);
				if(false)	// if memberName is a list type
				{	$num=mysql_numrows($results);
					$endResult = array();
					for($i=0; $i < $num; $i++) 
					{	$endResult[] = mysql_result($results, $i, "element");
					}
					return $endResult;
				}
				else	// if memberName is an object
				{
				}
			}
			else 
			if(getMemberTypes($member))	// if memberName is a value-type
			{	return mysql_result($results, 0, $memberName);
			}
		}
		
		function app($memberName, $value)	// append a value to a list
		{	sqlquery
			(	'INSERT INTO user_'.$objectName.' VALUES
				(	
				)'
			);
		}
		
		function len($memberName)			// get the length of a list
		{	return mysql_numrows($something);
		}
		
		function rm($memberName, $object)			// delete a member of a list
		{	sqlquery
			(	'DELETE FROM contacts WHERE id=7'
			);
		}
		
		function getMemberNames()
		{	$results = sqlquery
			(	'SHOW COLUMNS FROM contacts'
			);
			
			$num=mysql_numrows($results);
			for($i=0; $i < $num; $i++) 
			{	echo $i."is: " . mysql_result($results, $i) . '<br/>';
			}
		}
		
		function getMemberTypes($member)
		{	$results = sqlquery
			(	'SELECT '.$member.' FROM '.$physicalName
			);
			return mysql_field_type($results);
		}
	};
	
	function sqlquery($query)		// if a global variable $sqoolDebug is on, then it prints errors
	{	global $sqool_DebugFlag;
		
		$result = mysql_query($query);
		if($sqool_DebugFlag==TRUE && FALSE == $result)
		{	echo "The error is: " . mysql_error() . "<br/>";
			print_r(debug_backtrace());
		}
		
		return $result;
	}
	
	function createTable($membersArray)
	{	$query = 'CREATE TABLE user_'.$name.'(';
		
		$keys = array_keys($membersArray);
		$end = count($membersArray);
		for($n=0; $n<$end; $n++)
		{	$type = $keys[$n];
		
			switch ($keys[$n]) 
			{case "bool":		$type = "BOOLEAN";		break;
			 case "string":		$type = "TINYTEXT";		break;
			 case "bstring":	$type = "TEXT";			break;	// big string
			 case "gstring":	$type = "LONGTEXT";		break;	// giant string
			 case "tinyint":	$type = "TINYINT";		break;
			 case "int":		$type = "INT";			break;
			 case "bigint":		$type = "BIGINT";		break;
			 case "float":		$type = "FLOAT";		break;
			 case "double":		$type = "DOUBLE";		break;
			 
			 case "bool list":		$type = "VARCHAR(255) NOT NULL";		break;
			 case "string list":	$type = "VARCHAR(255) NOT NULL";		break;
			 case "bstring list":	$type = "VARCHAR(255) NOT NULL";		break;	// big string list
			 case "gstring list":	$type = "VARCHAR(255) NOT NULL";		break;	// giant string list
			 case "tinyint list":	$type = "VARCHAR(255) NOT NULL";		break;
			 case "int list":		$type = "VARCHAR(255) NOT NULL";		break;
			 case "bigint list":	$type = "VARCHAR(255) NOT NULL";		break;
			 case "float list":		$type = "VARCHAR(255) NOT NULL";		break;
			 case "double list":	$type = "VARCHAR(255) NOT NULL";		break;
			 case "binary list":	$type = "VARCHAR(255) NOT NULL";		break;
			 
			 // object
			 default:	// look for key name in list of classes (if its not there its an error)
			 			$type = "VARCHAR(255)";
			}
		
			$query .= $key . " " . $type;
			if($n+1 != $end)
			{	$query .= ", ";
			}
		}
		
		return sqlquery($query);
	}
	
	function sql_exists($objectName)
	{	$results = sqlquery
		(	'SELECT * FROM '.$objectName
		);
		if($results==FALSE)
		{	return FALSE;
		}
		else 
		{	return TRUE;
		}
	}
	
	function sqool_charIsOneOf($theChar, $singles, $ranges)
	{	$singlesLen = strlen($singles);
		for($n=0; $n<$singlesLen; $n+=1)
		{	if($theChar == $singles[$n])
			{	//echo "Win - Char: " . $theChar."  single is: " . $singles[$n]."<br/>\n";
				return true;
			}
			//echo "Lose - Char: " . $theChar."  single is: " . $singles[$n]."<br/>\n";
		}
		
		$numberOfRanges = floor(strlen($ranges)/2);
		//echo "numRnages (".$ranges."): " . strlen($ranges)."<br/>\n";
		for($n=0; $n<$numberOfRanges; $n+=1)
		{	if( $ranges[0+$n*2]<=$theChar && $theChar<= $ranges[1+$n*2] )
			{	//echo "Win - Char: " . $theChar."  range is: " . $ranges[0+$n*2]." to ". $ranges[1+$n*2] ."<br/>\n";
				return true;
			}
			//echo "Lose - Char: " . $theChar."  range is: " . $ranges[0+$n*2]." to ". $ranges[1+$n*2] ."<br/>\n";
		}
		return false;	
	}
	
	
	function sqool_getCertainChars($members, $index, $singles, $ranges, &$result)
	{	$result = "";
		$n=0;
		while(sqool_charIsOneOf($members[$index+$n], $singles, $ranges))
		{	//echo "WTF: " . $n."  Huh: " . $members[$index+$n]."<br/>\n";
			$result .= $members[$index+$n];
			$n+=1;
		}
		return $n;
	}
	
	function sqool_parse($members)
	{	//echo "JNK: " . $members."<br/>\n";
		
		$result = array();
		$index = sqool_getCertainChars($members, 0, " \t\n", "", $dumdum);
		//echo "ind: " . $index." Startarg: '".$dumdum."'<br/>\n";
		while(1)
		{	$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $type);	// get type
			if($numchars==0)	return false; 	// error if type isn't found
			$index += $numchars;
			
			//echo "ind: " . $index." ARG2: '".$type."'<br/>\n";
			
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			//echo "ind: " . $index." ARG2.5: '".$dumdum."'<br/>\n";
			$numchars = sqool_getCertainChars($members, $index, ":", "", $dumdum);		// get colon
			if($numchars==0)	return false; 	// error colon isn't found
			$index += $numchars;
			
			//echo "ind: " . $index." ARG3: '".$dumdum."'<br/>\n";
			
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			//echo "ind: " . $index." ARG3.5: '".$dumdum."'<br/>\n";
			$numchars = sqool_getCertainChars($members, $index, "_", "azAZ09", $name);	// get name
			if($numchars==0)	return false; 	// error if name isn't found
			$index += $numchars;
			
			//echo "ARG4.0 result[".$type."]='".$name."'<br/>\n";
			
			$result[$type] = $name;	// set the result array
			
			$index += sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
			//echo "ARG4.5<br/>\n";
			$numchars = sqool_getCertainChars($members, $index, ",", "", $dumdum);	// get comma
			if($numchars==0)
			{	break;		// done parsing
			}
			$index += $numchars;
			
			//echo "ARG5<br/>\n";
			
			$index += 	sqool_getCertainChars($members, $index, " \t\n", "", $dumdum);
		}
		
		//echo "WoW <br/>\n";
		
		return $result;
	}
	
?>
