<?php
	$debug=false
	
	function sqlquery($query)		// if a global variable $debug is on, then it prints errors
	{	global $debug;
		
		$result = mysql_query($query);
		if($debug==TRUE && FALSE == $result)
		{	echo "The error is: " . mysql_error() . "<br/>";
		}
		
		return $result;
	}
	
	// access a local database
	function sqoolAccess($user, $password, $databaseName)
	{	mysql_connect(localhost,$user,$password);
		@mysql_select_db($database) or die("Unable to select database".$databaseName);
	}
	
	// sets up tables to store classes
	function sqoolSetUp()
	{	// sqool_classes holds definitions for defined class types used to instantiate database objects
		// next open slot is for naming new tables that represent member objects inside a main object
		sqlquery
		(	'CREATE TABLE sqool_classes
			(	name varchar NOT NULL PRIMARY KEY,
				members varchar NOT NULL,
				nextOpenSlot varchar NOT NULL	
			)'
		);
		
		// sqool_objects holds the class types for each main database object
		sqlquery	
		(	'CREATE TABLE sqool_objects
			(	class varchar NOT NULL PRIMARY KEY,
				name varchar NOT NULL
			)'
		);
	}
	
	/* 	 $name is a string - the name of the object 
		 $members is an associative array consisting of type => name associations
		 Types: bool, string, tinyint, int, bigint, float, double, binary, object (write objectname)
	*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
	function sqoolClass($name, $members)
	{	return sqlquery
		(	'INSERT INTO sqool_classes VALUES
			(	'.$name.',
				'.$members.'
			)'
		);
	}
	
	// deletes a class
	function sqoolKillClass($name)
	{	return sqlquery
		(	'DELETE FROM sqool_classes WHERE name='.$name
		);
	}
	
	function sqoolExists($objectName)
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
	
	function($membersArray)
	{	$keys = array_keys($membersArray);
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
	
	// creates a new database object
	// returns a sqoolObj
	function sqoolCreate($classname, $name)
	{	if($type==1)
		{	return 0;	// can't recreate an already existing object
		}
		
		$results = sqlquery
		(	'SELECT members FROM sqool_classes WHERE name='.$classname
		);
		
		$members = mysql_result($results, 0, "members");
		$membersArray = parse($members);
		$query = 'CREATE TABLE user_'.$name.'(';
		
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
			 
			 default:	// look for key name in list of classes (if its not there its an error)
			 			$type = "VARCHAR(255)";
			}
		
			$query .= $key . " " . $type;
			if($n+1 != $end)
			{	$query .= ", ";
			}
		}
		
		$result = sqlquery($query);
		
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
				if()	// if memberName is a list type
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
	
?>
