<?php
/*	See http://bt.x10hosting.com/Sqool/ for documentation

	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2009, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

include_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces



/*	Classes that extend sqool should have a constructor that can be validly called with 0 arguments.

	Defines:		
		class sqool			connection to a database
		 public members:
			id				id is read-only, and holds the id of the object. Holds false if the object isn't in a database yet.
			MEMBER			where MEMBER stands for a member name of the object
		 public static methods:
			connect			connect to the database
			debug			turns on or off debugging messages (for all sqool connections and objects) - debugging is on by default so.... yeah..
			killMagicQuotes	Run this to undo the idiocy of magic quotes (for all sqool connections and objects)
		 public instance methods:
			getDB			returns a connection to another database on the same host
			sclass			should return the definition for a sqool class type. This function should be defined for a class extending sqool. 
							Does not create a table in the DB until an object is inserted (lazy table creation).
								Member types: bool, string, tinyint, int, bigint, float, :class:, :type: list
			insert			inserts an object into the database
			save			saves variables into the database that have been set for an object
			memberUnset		unsets a member from the object (so that when you save, that member won't be updated) (yea yea, I woulda named it 'unset' but php complained)
			fetch			returns select object members and (if any members are objects or lists) members of those members, etc. See the function for its use.
			sql				executes a single sql command (this is queueable)
			queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'go' method)
								calls that are queued include: insert, fetch, sql, save
			go				performs all the queries in the queue
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
			rm				Deletes (removes) an entity in the database server (either a class [table] or a whole database)
		 protected members:
			$debugFlag		This is made availble to sqool sub-classes (ie protected) if you want to use this flag in an extension. It is changed through calls to the method 'debug'
		 protected methods:
			addOperation	adds an operation in the form of up to three functions: an SQL generator, a result handler, and an error handler
								Note that the SQL generator for an operator can add to or modify the $op data passed to it, and use that additional or modified data in the result handler
			addToCallQueue	Adds an operation at the end of the call queue
			// NIXED //insertOpNext	Inserts an operation into the call queue as the next operation to be executed. May only be run during the SQL generation phase.
				
				
	Tables and naming conventions (for the tables that make up the objects under the covers):
		* CLASSNAME				table that holds all the objects of a certain type
			* id					the ID of the object
			* MEMBER				example field-name of a member named "MEMBER". 
									If this is a primitive, it will hold a value. 
									If this is a list or object, it will hold an ID. Objects are treated as references - they all start with a null id (note this is not the same as ID 0). 
		* list					table that holds all the lists in the database (Entry id 0 has list_id 0 and the object_id instead represents the next list_id to use. object_id 0 is incremented for every new list)
			* id					the id of each list-entry (Not strictly used right now)
			* list_id				the ID of the list that owns the object
			* object_id				the ID of the object/primitive that the list owns
			
		* PRIMITIVE_TYPE		table that holds all of the primitive values of a certain type that are refered to by lists (for example int or float)
			* id				the ID of the value
			* value					the value
			
	Internal operations - operations used to execute the queueable sqool calls
		* "insert"
		* "save"
		* "fetch"
		* "sql"
 */

/*	To do:
		* lines 650, 1287, and 1365 assume that a class will never have parameters in its constructor - isn't a safe assumption
		* have id be publically accessible through __get (but error if someone tries to set it)
		* maybe switch the word "fetch" for "load" which is easier to type 
		* make it so rootObject->fetch("className", objectID); returns the object with all its data (also perhaps add a third input that can describe what members to fetch)
		* add types: tinyintU and an intU (unsigned)
		* write unset
		* what happens when you insert an object that contains an object - what if that object is from another DB? Can I detect that? I might have to compare database handles on setting the object or something (so that an object from one db pretends to be an object from another)
		* don't execute a multiquery if theres no queries to execute
		* don't save a particular member if that member hasn't been updated (changedVariables has been created to represent this)
			* write a resave method to save an object even if it hasn't been updated
		* don't execute fetches for data that already exists
			* keep track of what objects have been fetched and sync them (objects that point to the same row in the db should point to the same obejct in the php)
			* write a refetech method to fetch data even if it has already been fetched
 			* I disagree about this now, because sometimes you need consistent data and automatically updating certain parts
				when other parts of the code have them updated could lead to problems.
				Plus its more complicated
		* make sure case is lowered for all internal names
		* Make sure you lower the case of all member names and classnames as they come in
		* have a facility for limiting operations that can be done on an object (allowOnly and disallow should be mutually exclusive). Make sure the mechanism can't affect internal behaviors (for example the insert call using create table or whatever)
		* Make sure any reference parameters of operations are not read from (so that changing what a variable points to after a 'queue' call but berfore a 'go' call won't screw things up)
		* Make sure that if a "does not exist" error comes back and then we try to create it (column, table, database), then we get "already exists", that we ignore that and move on as if nothing happened
		* add: 
			* classStruct	returns the code to create the php class for the specified database table
			* dbStruct		returns the code to create sqool classes for all the tables in the database (returned as an array of definitions)
			* app			appends a value to a list
			* setElem		sets an element of a list
			* rmElem		removes an element of a list
			* count			gets the number of elements in a list
			* addOptimizer	adds a pair of optimizer functions: an operation optimizer and a result detangler
			* getOptimizedCallQueue	returns the call queue for viewing purposes (the internal sqool call queue cannot be modified using the return value of this function)
		* Think about adding:
			* hasObj
			* syncClass
			* syncAll		syncs all defined classes
			* rmMember		deletes a member field from an object's table 
			* killClass		deletes a class (and all the objects of that class)
			* getID			a way to get the ID of an object
			* deDupPrims	removes duplicates in the primitive lists tables and fixes the `list` table acordingly (this should be run in a batch job)
			* Think about including "export" and "import" to get and set associative arrays with the data held in sqool objects
		* Think about adding inheritance
		* Think about indexing - batch updating an index as well
		* add back the non-repeatable calls checker - maybe this isn't necccessary since SQL is going to give an error if the table can't be created
		* Think about adding the ability to specify the length of a string type (which would be good for
		* test sending and receiving text fields longer than 255 characters - might have to caste varchars to Text
		* add the option "noindex" to memberDataControl (so it won't return an object's index)
		* make sure reservedMemberNames doesn't disallow new members named the same things as private members
		* only fully parse the class being searched for (rather than parsing all the classes at once)
		* Think about adding a "file" type that handles files as described in http://www.dreamwerx.net/phpforum/?id=1
			* the interface would work exactly the same way as other files except that it would have the method "stream" like this:
				* object->file->stream(); // this will output the file to the screen
				* on second thought, it really is better to use a filesystem for this - that way you don't have to send it from the sql server to the client server then to the client - it can go directly from the FS to the client
		* Have some helpful messages displayed when in debug mode
			* when someone assigns more than say 50KB of data to a string, output a message that tells the programmer why its better performance-wise to use a 
				filesystem than to use a DB for files. Tell them the ups and downs of a FS and a DB for file storage, including that a FS only uses bandwidth to send 
				to a client, while a DB has to send to the server then the client, while a FS may not be as scalable - unless you're using some "cloud" service that 
				has a scalable filesystem like amazon's S3
		* A book called High performance mysql explained you can use "the ODRER BY SUBSTRING(column, length) trick to convert the values to character strings, which will permit in-memory temporary tables
*/

/*	List of optimizations (that are already done):
		* Lazy database connection - the database is not connected to until a query needs to be processed
		* Lazy database/table/column creation - things are created only after they could not be found (that way there needs be no code that checks to make sure the db/table/column is there before asking for it or writing to it)
		* using queue and go pack all the queries into one network request, thereby minimizing flight time
		* a save is not executed if the object has no set or changed variables
	
*/

// represents a database object (the entire database is also considered an object)
// performs lazy connection (only connects upon actual use)
class sqool			// connection to a database
{	
	// internal static variable
	
	protected static $debugFlag = true;
	private static $debugMessageHasBeenWritten = false;
	private static $initialized = false;
	private static $classes;	// members should be of the form phpClassName=>array("name"=>tableName, "definition"=>definition) 
								// where definition should be an array with keys representing the names of each member and the values being an array of the form array("baseType"=>baseType[, "listType" => listType])
	private static function isADefinedClassName($name, $searchIfNot=true)
	{	$result = self::in_array_caseInsensitive($name, array_keys(self::$classes));	// case insensitive test is done so that variables that only differ by case are not accepted
				
		if($result === false)
		{	if($searchIfNot)
			{	// see if the new class can be found
				self::processSqoolClass($name);
				return self::isADefinedClassName($name, false);
			}else
			{	return false;
			}
		}else
		{	return true;
		}
	}
	private static function requireDefinedClass($className)
	{	if(false === self::isADefinedClassName($className))
		{	throw new cept("'".$className."' is not a defined class name.");
		}
	}
	private static function isADefinedType($typeName)
	{	if( self::in_array_caseInsensitive($typeName, self::primtypes()) )
		{	return true;
		}else
		{	return self::isADefinedClassName($typeName);
		} 
	}
	//private static function getSqoolClassName($phpClassNameORobject)
	private static function getFrontEndClassName($phpClassNameORobject)	// gets the sqool class name used by the programmer - the name of the class that sclass is defined in (in the inheritance hierarchy)
	{	if(false === is_object($phpClassNameORobject) && in_array($phpClassNameORobject, array_keys(self::$classes)))
		{	return $phpClassNameORobject;
		}else
		{	$classNames = self::methodIsDefinedIn($phpClassNameORobject, "sclass");
			if(count($classNames) == 0)
			{	// get the className for $objectOrClassName
				if(is_object($phpClassNameORobject))
				{	$className = get_class($phpClassNameORobject);
				}else
				{	$className = $phpClassNameORobject;
				}
				
				throw new cept("Class '".$className."' doesn't exist or does not have an 'sclass' definition.");
			}else
			{	$lastClassFirst = array_reverse($classNames);
				self::requireDefinedClass($lastClassFirst[0]);
				return $lastClassFirst[0];			// return the last class in the inheritance hierarchy that defines the sclass function
			}
		}
	}
	private static function getBackEndClassName($phpClassNameORobject)
	{	return self::$classes[self::getFrontEndClassName($phpClassNameORobject)]["name"];
	}
	private static function getClassDefinition($phpClassNameORobject)
	{	return self::$classes[self::getFrontEndClassName($phpClassNameORobject)]["definition"];
	}
	
	private static function processSqoolClass($c)
	{	if(false == in_array("sqool", self::getFamilyTree($c)))
		{	throw new cept("Attempting to process a class that doesn't descend from sqool.");
		}
		if(self::isADefinedClassName($c, false))
		{	throw new cept("Attempting to process a class twice.");
		}
		
		$classNames = self::methodIsDefinedIn($c, "sclass");
		if(count($classNames) == 0)
		{	throw new cept("Attempting to process a sqool class that doesn't define an 'sclass' method.");
		}
		
		$members = "";
		$shapeShifter = new sqool();	// doesn't matter what kind of object is created here (since it will be casted)
		foreach($classNames as $className)
		{	$members .= self::classCast_callMethod($shapeShifter, $className, "sclass");
		}
		
		$sqoolFrontendClassName = $classNames[count($classNames)-1];
		self::$classes[$sqoolFrontendClassName] = array();	// placeholder so that the class is seen as existing
		
		// add the class definition to sqool
		$className = $sqoolFrontendClassName;
		sqool::validateVariableName($sqoolFrontendClassName);
		if(self::isReservedName($sqoolFrontendClassName))
		{	$inFunction = false;
			throw new cept("Sqool reserves the class name ".$sqoolFrontendClassName." for its own use. Please choose another class name.");
		}
		
		// add class to list of $classes
		self::$classes[$sqoolFrontendClassName] = array
		(	"name"=>strtolower($sqoolFrontendClassName), 
			"definition"=>self::parseClassDefinition($members, $sqoolFrontendClassName)
		);
	}
	
	private static $startingTypes = array	// this is used to initialize the private member $classes
	(	"bool"      =>array("name"=>"bool",	   "definition"=>array("value"=>array("baseType"=>"bool"))),
		"string"    =>array("name"=>"string",  "definition"=>array("value"=>array("baseType"=>"string"))),
		"tinyint"   =>array("name"=>"tinyint", "definition"=>array("value"=>array("baseType"=>"tinyint"))),
		"int"       =>array("name"=>"int",	   "definition"=>array("value"=>array("baseType"=>"int"))),
		"bigint"    =>array("name"=>"bigint",  "definition"=>array("value"=>array("baseType"=>"bigint"))),
		"float"     =>array("name"=>"float",   "definition"=>array("value"=>array("baseType"=>"float"))),
		"double"    =>array("name"=>"double",  "definition"=>array("value"=>array("baseType"=>"double"))),
		"list"      =>array("name"=>"list",	   "definition"=>array("list_id"=>array("baseType"=>"int"), "object_id"=>array("baseType"=>"int")))
	);	
	
	private static $operations = array();
	private static function primitives()
	{	//	sqool type	/ SQL column type
		return array
		(	'bool'		=>"BOOLEAN", 
			'string'	=>"LONGTEXT", 
			'tinyint'	=>"TINYINT", 
			'int'		=>"INT", 
			'bigint'	=>"BIGINT", 
			'float'		=>"FLOAT", 
			'double'	=>"DOUBLE"
		);
	}
	private static function primtypes(){return array_keys(self::primitives());}
	private static function coretypes(){return array_merge(self::primtypes(),array('list'));}
	
	// make sure reservedMemberNames doesn't disallow new members named the same things as private members
	private static function reservedMemberNames()
	{	$results = array();
		foreach(get_class_methods("sqool") as $r)
		{	$results[] = strtolower($r);
		}
		return $results;
	}
	private static function isReservedTableName($name)
	{	$tableNames = array("list");
		$tableNames = array("olist");
		foreach(self::primtypes() as $pt)
		{	$tableNames[] = $pt;
		}
		return self::in_array_caseInsensitive($name, $tableNames);
	}
	private static function isReservedSqoolClassName($name)
	{	return self::isReservedTableName($name) || self::in_array_caseInsensitive($name, self::coretypes());
	}
	private static function isReservedName($name)
	{	return self::isReservedSqoolClassName($name);
	}		
	
	// This clears the class that have already been processed
	// This should only be used to test automatic handling of schema additions
	protected static function clearClasses()
	{	if(false === self::$debugFlag)
		{	throw new cept("The clearClasses method can only be used in debug mode");
		}
		
		echo "Executing clearClasses. Note that this function cannot be called when debug mode is off<br>\n";
		self::$classes = self::$startingTypes;
	}
	
	const connection_failure 			= 0;
	const database_creation_error 		= 1;
	const class_already_exists	 		= 2;
	const nonexistant_object	 		= 3;
	const append_error		 			= 4;
	const invalid_variable_name 		= 5;
	const general_query_error 			= 6;	// cept::data should hold the error number for the query error
	const table_creation_error 			= 7;
	const column_creation_error 		= 8;
	
	const a=9;const A=9;const ascend	= 9;	// ...
	const d=10;const D=10;const descend	= 10; 	// .. used for sorting
	
	const sqoolSQLvariableNameBase = "sq_var_";
	
	// internal instance variables 
	
	private $databaseRootObject = false;	// the sqool object that represents the database as a whole (the root sqool object should point to itself here
	private $connectionInfo=array("con"=>false);		// username, password, host, database, con (the connection to the database)
	
	private $callQueue = array();	// can be accessed from operations added to sqool
	private $queueFlag = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
	
	private $id=false;						// the ID of the object (inside the table $classTable)	
	private $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
	private $listsInfo = array();			// info for list variables (like their ids, and their sizes)
	private $changedVariables = array();	// catalogues the variables that have been changed
	
	protected function clearChangedVariables()
	{	$this->changedVariables = array();
	}
	
	// meant for use by a class that extends sqool
	protected function addToCallQueue($additionalOperation)
	{	$this->callQueue[] = $additionalOperation;
	}
	
	// meant for use by a class that extends sqool
	// inserts an operation into the next slot
	// can only be called in SQLgenerator functions for operations (otherwise it'll throw an error)
	/*protected function insertOpNext($op)
	{	if($this->building !== true)
		{	throw new cept("This must only be called in SQL generator functions for an operation");
		}
		$this->callQueue = self::array_insert($this->callQueue, $this->currentOp + 1, $op);
	}
	*/
	
	// Turns debugging on or off (on by default)
	// $setting is true for 'on', false for 'off'
	public static function debug($setting)
	{	if($setting === true)
		{	self::$debugFlag = true;
		}else
		{	self::$debugFlag = false;
		}
	}
	
	// Access a local database - attempts to create database if it doesn't exist
	// Can create a database if your host allows you to, otherwise database must already exist
	// returns a sqool object
	public static function connect($usernameIn, $passwordIn, $databaseIn, $hostIn='localhost')
	{	return self::connect_internal($usernameIn, $passwordIn, $databaseIn, $hostIn, false);
	}
	private static function connect_internal($usernameIn, $passwordIn, $databaseIn, $hostIn='localhost', $conIn=false)
	{	self::validateVariableName($databaseIn);
		
		$returnedObject = new sqool();
		self::setUpSqoolObject($returnedObject, $returnedObject);
		
		$returnedObject->connectionInfo = array
		(	"username" => $usernameIn,
			"password" => $passwordIn,
			"host" => $hostIn,
			"database" => $databaseIn,
			"con" => $conIn
		);
		
		self::initializeSqoolClass();
		
		return $returnedObject;
	}
	
	// returns a connection to another database on the same host
	public function getDB($databaseName)		
	{	$this->validateRoot("Attempting to get a sibling database from an object that isn't a database. I think you can figure out why thats wrong.");
		return connect($this->username, $this->password, $this->host, $databaseName);
	}
	
	// copies an object into a new row in the database table for its class
	// returns the inserted object (a reference to the object just inserted into the DB)
	// if database accesses are being queued, the returned object won't be updated with its ID and connection until after the queue is executed with 'go'
	// the object used to insert is unmodified
	public function insert($object)
	{	$this->validateRoot("Attempting to insert an object into something other than a database. I think you can figure out why thats wrong.");
		
		$newObject = clone $object;		// copy
		self::setUpSqoolObject($newObject, $this);	// give it this sqool object as a connection
		
		foreach($this->createInsertOp($newObject, false) as $operation)
		{	$this->databaseRootObject->addToCallQueue($operation);	// insert the calls into the callQueue
		}
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}	
		
		return $newObject;	// return the new object that has (or will have) a new ID and a database connection
	}
	// Returns an array of operations (all the operations neccessary to do the requested insert
	// $fieldToUpdate should either be false (for no update) or should be an array of the form array("class"=>class, "member"=>member, "variable"=>number)
		// where 'class' and 'member' is the member of a certain class to update after the insert
		// and 'number' is the the number used to create the variable name where the operation should look for the ID of the object containing the field to update
			// the variable name will be made from class and number concatenated
	private function createInsertOp($newObject, $fieldToUpdate)
	{	$className = self::getFrontEndClassName($newObject);	
		$memberDefinition = self::getClassDefinition($className);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		return $this->createInsertOp_explicitDefinition($newObject, $fieldToUpdate, $className, $memberDefinition);
	}
	private function createInsertOp_explicitDefinition($newObject, $fieldToUpdate, $className, $memberDefinition)
	{	$variables = $newObject->setVariables;
		$newObject->clearChangedVariables();
		
		if($fieldToUpdate === false)
		{	$numberForVariable = 0;
		}else
		{	$numberForVariable = $fieldToUpdate["variable"]+1;
		}
		
		$columns = array();
		$values = array();
		$extraOps = array();
		foreach($variables as $member => $val)
		{	if(is_object($newObject->$member) && ($newObject->$member->id === false || $newObject->$member->databaseRootObject !== $this->databaseRootObject))
			{	$valToUse = clone $val;		// copy
				self::setUpSqoolObject($val, $this);	// give it this sqool object as a connection
			}else
			{	$valToUse = $val;
			}
			
			$columns[] = self::makeSQLnames(strtolower($member));
			$result = $this->PHPvalToSqoolVal
			(	$memberDefinition[$member], $valToUse, 
				array("class"=>$className, "member"=>$member, "variable"=>$numberForVariable, "objectReference"=>$newObject),
				"new"	// for lists
			);
			
			$values[] = $result["value"];
			$extraOps = array_merge($extraOps, $result["ops"]);
		}
		
		return array_merge
		(	array(array
			(	"opName" => "insert", 
				"class" => $className, 
				"vars" => array("columns"=>$columns, "values"=>$values),
				"returnedObjectReference" => $newObject, "fieldToUpdate"=>$fieldToUpdate, "numberForVariable"=>$numberForVariable
			)),
			$extraOps
		);
	}
	
	private function createInsertListOp($baseType, $newList, $fieldToUpdate)
	{	if(count($newList) == 0)
		{	return array();	// no operations to do
		}
		
		$className = "list";

		if($fieldToUpdate === false)	// i'm pretty sure $fieldToUpdate will never be false in this
		{	$listID_variableNumber = 0;	
		}else
		{	$listID_variableNumber = $fieldToUpdate["variable"]+1;
		}
		
		$numberForVariable = $listID_variableNumber+1;	// sorry about the confusing names
		
		$operations = array(array
		(	"opName" => "getNewListID", "numberForVariable"=>$listID_variableNumber,	// get new list id
			"objectReference"=> array("object"=>$fieldToUpdate["objectReference"], "member"=>$fieldToUpdate["member"])
		));	
		foreach($newList as $item)
		{	$operations[] = array
			(	"opName" => "insertListItem", 
				"listID" => array("type"=>'variableNumber', "value"=>$listID_variableNumber),
				"objectID" => "0",	// to be filled in later
				"fieldToUpdate"=>false, "numberForVariable"=>$numberForVariable
			);
			
			// insert object operations
			$result = $this->PHPvalToSqoolVal($baseType, $item, $fieldToUpdate, "listItem");
			
			$operations = array_merge($operations, $result["ops"]);
		}
		
		return $operations;
	}
	
	// to use the '__set' magic function in child classes, use ___set (with three underscores instead of two)
	function __set($name, $value)
	{	$this->validateNOTRoot("You can't set member variables of a Sqool object that represents the database");

        // validate the type
        $thisClassName = self::getFrontEndClassName($this);
		$memberDefinition = self::getClassDefinition($thisClassName);
        if(array_key_exists("listType", $memberDefinition[$name]))
        {   if(gettype($value) !== "array")
            {   throw new cept("Attempting to set array field (".$name.") as a".gettype($value));
            }
        }else if(in_array($memberDefinition[$name]["baseType"], array_keys(self::primitives())) )
        {   if(in_array(gettype($value), array("object", "array")) || $value === null)
            {   throw new cept("Attempting to set ".$memberDefinition[$name]["baseType"]." field (".$name.") as a ".gettype($value));
            }
        } else
        {   if($value !== null && gettype($value) !== "object")
            {   throw new cept("Attempting to set object field (".$name.") as a ".gettype($value));
            }
        }


		if($name == 'id')
		{	throw new cept("You can't manually set the object's id. Sorry.");
		}else if( false == $this->containsMember($name) )
		{	if(method_exists($this, "___set"))
			{	$this->___set($name, $value);
			}else
			{	throw new cept("Object doesn't contain the member '".$name."'.");
			}
		}
		
		$this->setVariables[$name] = $value;
		$this->changedVariables[$name] = true;
	}
	
	function __get($name)
	{	if($name == 'id')
		{	return $this->id;
		}else if(array_key_exists($name, $this->setVariables))
		{	return $this->setVariables[$name];
		}else if( $this->containsMember($name) )	// if sqool class has the member $name (but it isn't set)
		{	throw new cept("Attempted to get the member '".$name."', but it has not been fetched yet.");
		}else
		{	if(function_exists("___get"))
			{	return $this->___get($name);
			}else
			{	throw new cept("Object doesn't contain the member '".$name."'.");
			}
		}
	}
	
	public function save()
	{	$this->validateNOTRoot("You can't save member variables of a Sqool object that represents the database");	// MAYBE ALLOW THIS IN THE FUTURE
		$this->requireID("save");
		
		if(count($this->setVariables) == 0)
		{	return;	// nothing to do
			//throw new cept("Attempted to save an empty dataset to the database");
		}
		
		$this->databaseRootObject->saveSqoolObject($this);
	}	
	
	public function memberUnset()
	{	throw new cept("Unset isn't written yet");
	}
	
	// fetches objects from the database
	// returns a sqool object
	// If the sqool object has a connection but does not have a class, it represents the entire database where each table is a list member
	// Note: if a member is a class type object, it will be NULL if it doesn't point to any object
	// throws an error if an invalid member is accessed (if a non-existant member is attempted to be accessed)
	/*	
		object->fetch()		// fetches all the members of the object (but does not fetch members of object-members). If its the root object, fetches the whole database.
		// OR
		object->fetch("<memberList>");
	    // OR
	    object->fetch("<className>", <id>); // returns an object with a connection and an ID (makes no call to the database server) 	
		
		// <memberList> represents a list of members that may or may not have associated dataControls. Eg:
			memberName memberName2 etc
			// or
			memberName[<dataControl>] memberName2[<dataControl2>] etc[<etcControl>]
			// or a mix:
			memberName membername2 memberName3[<dataControl>] etc[<etcControl>]
			
					
		// <dataControl> represents the following:
			members: <memberList>	
			cond: <expression>
			sort <direction>: member1 member2 member3  <direction>: memberetc
			range: 0:10 34:234
			
			// members: the list of members to fetch for this object (and their dataControls)
				// members without data controls are fetched with all their members (but all those member don't have their members fetched) (in the case of the root object, it fetches an array of every object of that type)
				// if the object member being selected by this memberDataControl set is a list, the "members" array controls the returned members for each element of the list
			// -- The following three only apply if the member is a list: --
			// cond: conditions on selecting items in a list
			// sort: used only for a list - how to sort the list
				// <direction> can either be 'asc' or 'desc' - whenever a direction is written, it changes the direction subsequent fields are sorted
					// e.g. in "sort desc: fieldA fieldB  asc: fieldC", fieldA and fieldB are sorted descending, and fieldC is sorted ascending		
			// ranges: used for selecting a slice of the list to fetch (after being sorted)
			
		
		
		// <expression> represents a boolean or mathematical expression (ie a where clause)
		// The sql condition set:
			//	`x` > '5' AND `y` < '3' AND (`x` = `y` OR `y`*'5' >= `x`*`z`)
			//	would be written in sqool as:
			//	x > ',5,'&& y <',3,'&& (x=y || y*',5,'>= x * z )
	*/
	public function fetch( /*$fetchOptions ) OR fetch($className) OR fetch($className, $id*/ )
	{	$args = func_get_args();
        $hasFetchOptions = false;
    
		if(count($args) == 0)
		{	if($this->isRoot())
			{	throw new cept("Sqool doesn't support fetching the entire database by calling 'fetch' without arguments yet.");
			}else
			{	$mode = "members";
				$ID = $this->id;
				$className = self::getFrontEndClassName($this);
				$objectRef = array($this);
			}
		}
		else
		{	$stringFetchOptions = self::mergeStringFetchOptions($args);

            if($this->isRoot())
			{	$mode = "tables";
			}else
			{	$this->requireID("fetch from");

				$mode = "members";
				$hasFetchOptions = true;
				$ID = $this->id;
				$className = self::getFrontEndClassName($this);
				$objectRef = array($this);
			}

            $fetchOptions = self::stringFetchOptionsToArrays($stringFetchOptions);
		}

		if($this->databaseRootObject->queueFlag === false)
		{	$this->databaseRootObject->queue();
			$goImmediately = true;
		}else
		{	$goImmediately = false;
		}

		//$fetchOptions = $this->membersToKeyValue($firstArg);

		if($mode == "tables")	// if this is the root
		{	foreach($fetchOptions as $k=>$v)
			{	foreach($this->createFetchOperation($k, $v, array($this, $k), "tables") as $o)
				{	$this->databaseRootObject->addToCallQueue($o);	// insert the calls into the callQueue
				}
			}
		}else
		{	// translate into the uniform fetch form

			$mainOptions = array
			(	"cond"=>array(self::getClassPrimaryKey($className)."=",$ID),
				"ranges"=>array(0,0)	// only return the first item found (since there can only be one)
			);
			if($hasFetchOptions)
			{	$mainOptions["members"] = $fetchOptions;
			}

			foreach($this->databaseRootObject->createFetchOperation($className, $mainOptions, $objectRef, "members") as $o)
			{	$this->databaseRootObject->addToCallQueue($o);	// insert the calls into the callQueue
			}
		}

		if($goImmediately)
		{	$this->databaseRootObject->go();
		}

        if(isset($fetchOptions) && count($fetchOptions) === 1)
        {   $fetchOptionsArraykeys = array_keys($fetchOptions);
            $objectBeingFetched = $fetchOptionsArraykeys[0];
            return $this->setVariables[$objectBeingFetched];
        }
	}

    // concatenates toegether the fetch arguments, transforming every other argument into safe-input
    private function mergeStringFetchOptions($fetchArguments)
    {   $result = "";

        $even = true;
        foreach($fetchArguments as $arg)
        {   if($even)
            {   $result .= $arg;
            }
            else
            {   $argType = gettype($arg);
                if($argType === "string")
                {   $result .= "'".self::escapeString($arg)."'";
                } else if($argType === "object" && $arg instanceof sqool)
                {   $result .= $arg->id;    // a sqool object is represented by it's id
                } else if($arg === null)
                {   $result .= "null";
                } else
                {   $result .= $arg;
                }
            }

            $even = !$even;
        }

        return $result;
    }



    /*	returns a memberSelection array
		// membersSelection represents the following:
		array
		(	"memberNameA"=>memberDataConrolA,	// the key is the member name, the value is the data control
			"memberNameB"=>memberDataControlB,
			//etc...
			//OR
			"memberNameC",	// numeric indecies mean that the value is the member name (and no data control)
			"memberNameD"
			//etc...
		),

		// memberDataControl represents the following:
		array
		(	// if the object member being selected by this memberDataControl set is a list, the "members" array controls the returned members for each element of the list
			"members" => membersSelection,

			// if a key in "members" is a list, the following keys apply to the array that key points to:
			// for fields that are objects, the 'value' is a sqool object instance
			// the "sort", "cond", and "ranges" keys are optional
			"sort" => array(direction, fieldArrayOrDirection, fieldArrayOrDirection, etc),	// the way to sort the elements of a member list
				// direction should be either sqool::a or sqool::ascend for ascending [smallest first], and sqool::d or sqool::descend for descending [largest first]
					// whenever a direction is written, it changes the direction subsequent fields are sorted
						// e.g. in "sort" => array(sqool::d, "fieldA", "fieldB", sqool::a, "fieldC")  fieldA and fieldB are sorted descending, and fieldC is sorted ascending
				// a field should just be a string holding the field name
				// a string inside an array is treated as a raw SQL string to insert into the sort conditions

			"cond" => expression, 									// the elements of a member list selected by some kind of conditions on the elements of the list
			"ranges" => array(start, end, start2, end2, etc, etc)	// objects to return from the selected list by their position in the list (after being sorted).
		)

		// expression represents a boolean or mathematical expression
		// op is any non-alphanumeric string (sqool does not support alphanumeric operators like "LIKE" or "XOR" - use "&&" and "||" instead of "AND" and "OR")
		//		examples of an 'op': "&& ", "||", ">", "<", "=", etc
		// expressions consist of pairs of parameters representing a field with connecting operators and a value to be tested on
		// surrounding a value with an array (e.g. array('y') instead of just 'y') makes sqool treat the first word or token in the string as an object member (column/field name in database terms)
			// another example of this is array('y*5') will be translated (for mySQL) into `y`*5
		// expressions can be treated like parentheses, for example the sql condition set:
			//	`x` > '5' AND `y` < '3' AND (`x` = `y` OR `y`*'5' >= `x`*`z`)
			//	would be written in sqool as:
			//	array("x >", 5, '&& y<', 3, '&&', array('x =', array('y'), "|| y *", 5, ">= x *", array('z')) )
		// as in the above example, a 'value' parameter slot (array members with an odd index in an expression) can be replaced with an expression, allowing it to represent a sqool object member
		// the following is the syntax of an expression:
		array
		(	"field op", value1, "",
			"op field2 op", value2,
			"op field3 op", value3, // etc
			// OR
			expressionX,
			expressionY,
			expressionZ // etc
			// OR
			array("raw"=>sqlExpression)
			// OR a mix
		)
	*/
    private static function stringFetchOptionsToArrays($stringFetchOptions)
    {   $position = 0;
        $result = self::parseFetchMemberList($stringFetchOptions, $position);
        if($position != strlen($stringFetchOptions)) 
        {   throw new cept("Parse failure in fetch options: ".$stringFetchOptions);
        }
        return $result;
    }




    // returns the result, and sets $endPosition
    private static function parseFetchMemberList($stringFetchOptions, &$curPosition)
    {   $result = array();
        $currentPosition = $curPosition;
        while(true) // loop through members in memberList
        {   self::parseWhiteSpace($stringFetchOptions, $currentPosition); // ignore preceding whitespace
            $currentResult = self::parseFetchMember($stringFetchOptions, $currentPosition, $currentPosition);
            if($currentResult === null)
            {   break;
            } else
            {   $result = array_merge($result, $currentResult);
            }
        }
        $curPosition = $currentPosition;
        return $result;
    }

    // translates this:
    /* 		memberName
			// or
			memberName[<dataControl>]
     */
    // into this:
    /*  array
		(	"memberName"=>array()
            // or
			"memberName"=><dataControl>
		)
     */
    // returns null if the endPosition doesn't increment
    private static function parseFetchMember($stringFetchOptions, $startPosition, &$endPosition)
    {   $currentPosition = null;  // will be overwritten in parseWhiteSpace
        $memberName = self::parseVariableName($stringFetchOptions, $startPosition, $currentPosition);
        if($memberName === null) {return null;}

        if(self::parseConstantString('[', $stringFetchOptions, $currentPosition, $currentPosition) === null)
        {   $endPosition = $currentPosition;
            return array($memberName=>array());
        }

        $dataControl = self::parseDataControl($stringFetchOptions, $currentPosition, $currentPosition);
        if($dataControl === null) {return null;}

        if(self::parseConstantString(']', $stringFetchOptions, $currentPosition, $currentPosition) === null)
        {   return null;
        } else
        {   $endPosition = $currentPosition;
            return array($memberName=>$dataControl);
        }
    }

    // translates this:
    /* 		members: <memberList>
			cond: <expression>
			sort:  member1 member2 <direction>: member3  <direction>: memberetc
			range: 0:10 34:234
     */
    // into this:
    /* array
		(	// if the object member being selected by this memberDataControl set is a list, the "members" array controls the returned members for each element of the list
			"members" => membersSelection,

			// if a key in "members" is a list, the following keys apply to the array that key points to:
			// for fields that are objects, the 'value' is a sqool object instance
			// the "sort", "cond", and "ranges" keys are optional
			"sort" => array(direction, fieldArrayOrDirection, fieldArrayOrDirection, etc),	// the way to sort the elements of a member list
				// direction should be either sqool::a or sqool::ascend for ascending [smallest first], and sqool::d or sqool::descend for descending [largest first]
					// defaults to ascending
                    // whenever a direction is written, it changes the direction subsequent fields are sorted
						// e.g. in "sort" => array(sqool::d, "fieldA", "fieldB", sqool::a, "fieldC")  fieldA and fieldB are sorted descending, and fieldC is sorted ascending
				// a field should just be a string holding the field name
				// a string inside an array is treated as a raw SQL string to insert into the sort conditions

			"cond" => expression, 									// the elements of a member list selected by some kind of conditions on the elements of the list
			"ranges" => array(start, end, start2, end2, etc, etc)	// objects to return from the selected list by their position in the list (after being sorted).
		)

     */
    private static function parseDataControl($stringFetchOptions, &$curPosition)
    {   $currentPosition = $curPosition;
        $result = array();

        $notDoneYet = array('members'=>0, 'cond'=>0, 'sort'=>0, 'range'=>0);
        while(true)
        {   $word = self::getDataControlType($stringFetchOptions, $currentPosition);

            if($word === null)
            {   break;  // quit if there's no more valid words happening
            }
            if( ! in_array($word, array_keys($notDoneYet)))
            {   return null;
            }

            if($word === 'members')
            {   $members = self::parseFetchMemberList($stringFetchOptions, $currentPosition);
                $result['members'] = $members;
            } else if($word === 'cond')
            {   $conditions = self::parseConditional($stringFetchOptions, $currentPosition);
                $result['cond'] = array('raw'=>$conditions);
            } else if($word === 'sort')
            {   $sortList = self::parseSortList($stringFetchOptions, $currentPosition, $currentPosition);
                $result['sort'] = $sortList;
            } else if($word === 'range')
            {   $range = self::parseRange($stringFetchOptions, $currentPosition);
                $result['ranges'] = $range;
            }

            unset($notDoneYet[$word]);
        }

        $curPosition = $currentPosition;
        return $result;
    }
        // returns the data control type from a DataControl string
        private static function getDataControlType($stringFetchOptions, &$curPosition)
        {   $currentPosition = $curPosition;
            $word = self::parseVariableName($stringFetchOptions, $currentPosition, $currentPosition);

            if($word === null)
            {   return null;
            }

            // accept 'order by' in place of 'sort'
            if($word === 'order')
            {   $nextWord = self::parseVariableName($stringFetchOptions, $currentPosition, $currentPosition);
                if($nextWord !== 'by')
                {   return null;
                } else
                {   $word = 'sort';
                }
            }

            if($word === 'where')
            {   $word = 'cond';

            }

            if($word === 'sort')
            {   $positionAfterSort = $currentPosition;
                $nextWord = self::parseVariableName($stringFetchOptions, $positionAfterSort, $positionAfterSort);
                if($nextWord === 'asc' || $nextWord === 'desc')
                {   self::parseWhiteSpace($stringFetchOptions, $positionAfterSort); // ignore preceding whitespace
                    if(self::parseConstantString(':', $stringFetchOptions, $positionAfterSort, $positionAfterSort) === null)
                    {   return null;    // expected a :, got something else
                    } else
                    {   $curPosition = $currentPosition;
                        return $word;
                    }
                }
            }

            // else
            self::parseWhiteSpace($stringFetchOptions, $currentPosition); // ignore preceding whitespace
            if(self::parseConstantString(':', $stringFetchOptions, $currentPosition, $currentPosition) === null)
            {   return null;    // expected a :, got something else
            }

            if(in_array($word, array('members', 'cond', 'sort', 'range')))
            {   $curPosition = $currentPosition;
                return $word;
            } else
            {   return null;
            }
        }





    // <expression> represents a boolean or mathematical expression (ie a where clause)
    // The sql condition set:
        //	`x` > 5 AND `y` < 3 AND (`x` = `y` OR `y`*5 >= `x`*`z`)
        //	would be written in sqool as:
        //	x > ',5,'&& y <',3,'&& (x=y || y*',5,'>= x * z )
    private static function parseConditional($stringConditional, &$curPositionOut)
    {   $currentPosition = $curPositionOut;
        $finalResult = "";

        // need to parse
            // function calls
            // strings (single and double quoted)
            // word: (end condition)
            // members
            // end bracket (end condition)
            // operators (ie any non alphanumeric characters)

        while(true)
        {   $result = self::parseFunctionLikeCall($stringConditional, $currentPosition);
            if($result !== null) { $finalResult .= $result; continue; }

            $result = self::parseString($stringConditional, $currentPosition);
            if($result !== null) { $finalResult .= $result; continue; }

            $dummy = $currentPosition; // make sure $currentPosition isn't changed
            if(self::getDataControlType($stringConditional, $dummy) !== null)
            {   break; // end condition
            }

            $result = self::parseSpecialConditionalSyntax($stringConditional, $currentPosition, $currentPosition);
            if($result !== null) { $finalResult .= $result; continue; }

            $result = self::parseVariableName($stringConditional, $currentPosition, $currentPosition);
            if($result !== null) { $finalResult .= self::makeSQLnames($result); continue; }

            if(self::parseConstantString("]", $stringConditional, $currentPosition, $currentPosition) !== null)
            {   $currentPosition -= 1; // don't count the bracket
                break;  // end condition
            }

            $result = self::getOtherChars($stringConditional, "]:_'\"=!", "azAZ", $currentPosition);
            if($result !== null && $result !== '') { $finalResult .= $result; continue; }

            //else
            return null;    // didn't parse correctly, needs an end condition

        }

        $curPositionOut = $currentPosition;
        return $finalResult;
    }

    // for right now, just changes "== null" and "!= null" to "is null" and "is not null"
    private static function parseSpecialConditionalSyntax($theString, &$currentPositionOut)
    {   $curPosition = $currentPositionOut;

        self::parseWhiteSpace($theString, $curPosition); // ignore preceding whitespace
        $result = self::getConstantString($theString, array("!=", "=="), $curPosition);
        if($result !== null)
        {   self::parseWhiteSpace($theString, $curPosition); // ignore preceding whitespace
            $result = self::getConstantString($theString, array("null"), $curPosition);
            if($result !== null)
            {   $currentPositionOut = $curPosition;
                return " is not null";
            }
        }

        // otherwise just make sure to parse stray ! and = characters
        $curPosition = $currentPositionOut;
        $result = self::getConstantString($theString, array("!", "="), $curPosition);
        $currentPositionOut = $curPosition;
        return $result;
    }

    private static function parseFunctionLikeCall($theString, &$currentPositionOut)
    {   $currentPosition = $currentPositionOut;
        $result = "";

        $variableName = self::parseVariableName($theString, $currentPosition, $currentPosition);
        if($variableName === null)
        {   return null;
        }

        self::parseWhiteSpace($theString, $currentPosition); // ignore preceding whitespace
        if(self::parseConstantString("(", $theString, $currentPosition, $currentPosition) === null)
        {   return null;
        }

        while(true)
        {   $result .= self::parseConditional($theString, $currentPosition, $currentPosition);
        }

        self::parseWhiteSpace($theString, $currentPosition); // ignore preceding whitespace
        if(self::parseConstantString(")", $theString, $currentPosition, $currentPosition) === null)
        {   return null;
        }

        $currentPositionOut = $currentPosition;

        return $result;
    }

    private static function parseString($theString, &$curPosition)
    {   $currentPosition = $curPosition;
        $result = "";
        $length = count($theString);

        $quoteType = self::parseConstantString(array('"', "'"), $theString, $currentPosition, $currentPosition);
        if($quoteType === null)
        {   return null; // failure
        }

        while(true)
        {   // get normal characters
            $result += self::getOtherChars($theString, $quoteType+'\\', "", $currentPosition);

            // get special characters
            if($currentPosition===$length)
            {   return null; // fail
            } else if($theString[$currentPosition] === $quoteType)
            {   break; // quote's done
            } else if($theString[$currentPosition] === '\\')
            {   $currentPosition += 1;
                if($currentPosition===$length)
                {   return null; // fail
                } else if($quoteType === "'")
                {   $c = $theString[$currentPosition];
                    if($c === '\\')
                    {   $result += '\\';
                    } else if($c === "'")
                    {   $result += "'";
                    } else
                    {   $result += '\\'.$c; // literal
                    }
                } else /*$quoteType === '"'  */
                {   $c = $theString[$currentPosition];
                    if($c === '\\')
                    {   $result += '\\';
                    } else if($c === '"')
                    {   $result += '"';
                    } else if($c === 'n')
                    {   $result += "\n";
                    } else if($c === 'r')
                    {   $result += "\r";
                    } else if($c === 't')
                    {   $result += "\t";
                    } else if($c === 'v')
                    {   $result += "\v";
                    } else if($c === 'f')
                    {   $result += "\f";
                    } else if($c === '$')
                    {   $result += "\$";
                    } else
                    {   $result += '\\'.$c; // literal
                    }
                }
            }
        }

        $curPosition = $currentPosition;    // set the output variable on successful completion
        return $result;
    }

    // translates something like this:  member1 member2 <direction>: member3  <direction>: memberetc
    // into: array(<defaultDirection>, member1, member2, <direction>, member3, <direction> memberetc)
    private static function parseSortList($stringFetchOptions, $startPosition, &$endPosition)
    {   $dummy = null; // will be overwritten
        $currentPosition = $startPosition;
        $result = array();

        while(true)
        {   $directionChange = self::parseSortDirection($stringFetchOptions, $currentPosition, $currentPosition);
            if($directionChange !== null)
            {   $result[] = $directionChange;
            } else if(count($result) === 0)
            {   $result[] = self::ascend;
            }

            $memberName = self::parseVariableName($stringFetchOptions, $currentPosition, $currentPosition);

            if($memberName === null)
            {   return null; // this means there was a syntax error
            } else
            {   $result[] = $memberName;
            }

            $dummy = $currentPosition; // make sure $currentPosition isn't changed
            $word = self::getDataControlType($stringFetchOptions, $dummy);
            if($word != null)
            {   break;  // done
            }
            $endBracket = self::parseConstantString("]", $stringFetchOptions, $currentPosition, $dummy);
            if($endBracket !== null)
            {   break;  // done here too
            }
        }

        $endPosition = $currentPosition;
        return $result;
    }
        private static function parseSortDirection($stringFetchOptions, &$curPositionOut)
        {   $currentPosition = $curPositionOut;

            $result = self::parseConstantString(array('asc', 'desc'), $stringFetchOptions, $currentPosition, $currentPosition);
            if($result !== null)
            {   $colonResult = self::parseConstantString(':', $stringFetchOptions, $currentPosition, $currentPosition);
                if($colonResult !== null)
                {   $curPositionOut = $currentPosition;
                    if($result=='asc')
                    {   return self::ascend;
                    } else
                    {   return self::descend;
                    }
                }
            } else
            {   return null;
            }
        }

    // translate something like: 0:10
    // into: array(0, 10)
    private static function parseRange($stringFetchOptions, &$curPosition) {
        $currentPosition = $curPosition;

        self::parseWhiteSpace($stringFetchOptions, $currentPosition); // ignore preceding whitespace
        $startIndex = self::parseNumber($stringFetchOptions, $currentPosition, $currentPosition);
        if($startIndex === null) { return null; }

        self::parseWhiteSpace($stringFetchOptions, $currentPosition); // ignore preceding whitespace
        $endIndex = self::parseNumber($stringFetchOptions, $currentPosition, $currentPosition);
        if($endIndex === null) { return null; }

        $curPosition = $currentPosition;
        return array($startIndex+0, $endIndex+0);   // the +0 is for converting to a number (even if its bigger than an integer can store - don't want to truncate)
    }

    // parses either a single constant string, or one of a list of constant strings
    private static function parseConstantString($stringsToGet, $theString, $startPosition, &$endPosition)
    {   $result=null; // will be overwritten

        if(gettype($stringsToGet) !== "array")
        {   $stringsToGet = array($stringsToGet);
        }
        $numberOfCharactersGotten = self::getConstantStringToken($theString, $startPosition, $stringsToGet, $result);

        if(in_array($numberOfCharactersGotten, array(0,-1)))
        {   return null;
        } else
        {   $endPosition = $startPosition+$numberOfCharactersGotten;
            return $result;
        }
    }

    // gets an integer number
    private static function parseNumber($theString, &$curPosition)
    {   $result = null; // will be overwritten

        $numberOfCharactersGotten = self::getCertainChars($theString, $curPosition, "", "09", $result);
        if($numberOfCharactersGotten == 0)
        {   return null;
        } else
        {   $curPosition += $numberOfCharactersGotten;
            return $result;
        }
    }

    // returns the variable name, or null if no match
    private static function parseVariableName($theString, $startPosition, &$endPosition)
    {   $wordStartPosition = $startPosition;
        self::parseWhiteSpace($theString, $wordStartPosition); // ignore whitespace

        if($wordStartPosition >= strlen($theString) || '0' <= $theString[$wordStartPosition] && $theString[$wordStartPosition] <= '9')
        {   return null; // variables can't start with a number
        }

        $result = null; // will be overwritten
        $numberOfCharactersGotten = self::getCertainChars($theString, $wordStartPosition, "_", "09azAZ", $result);
        if($numberOfCharactersGotten == 0)
        {   return null;
        }
        else
        {   $endPosition = $wordStartPosition+$numberOfCharactersGotten;
            return substr($theString, $wordStartPosition, $endPosition-$wordStartPosition);
        }
    }

    private static function parseWhiteSpace($theString, &$curPosition)
    {   $result = null; // will be overwritten
        $numberOfCharactersGotten = self::getCertainChars($theString, $curPosition, " \t\n\r", "", $result);
        $curPosition += $numberOfCharactersGotten;
    }
	
	// transforms a members array into key value form (potentially from a mix of key=>value and implicit integer keying 
	private function membersToKeyValue($membersArray)
	{	if(false == is_array($membersArray))
		{	$membersArray = array($membersArray);
		}
		
		$resultArray = array();
		foreach($membersArray as $k=>$v)
		{	if(is_int($k))
			{	$resultArray[$v] = array();
			}else
			{	$resultArray[$k] = $v;
			}
		}
		return $resultArray;
	}
	
	// creates a fetch operation, and any operations that need to be done to fulfil that fetch
	// returns a list of operations
	// $relMember represents the object to modify
	//	* should be an array of names representing an inner member relative to an object (eg array($object, 'a','b','c') would represent $object->a->b->c)
	private function createFetchOperation($className, $options, $relMember, $mode, $numberForVariable=0)
	{	$this->validateRoot("Attempting to use the backend createFetchOperation function with an object that does not represent a database.");
		
		if(isset($options["members"]))
		{	$membersOptions = self::membersToKeyValue($options["members"]);
		}else
		{	$membersOptions = array();
		}
		
		self::validateFetchOptions($options);
		$classDefinition = self::getClassDefinition($className);		// members of the form "memberName" =>array("baseType"=>baseType[, "listType" => listType])
			
		if(isset($options["members"]))
		{	$membersOptions = self::membersToKeyValue($options["members"]);

			$containsResult = self::containsMembers($className, array_keys($membersOptions));	// returns array(bool_containsAll[, invalidMember])
			if($containsResult[0] === false)
			{	throw new cept("Attempting to fetch invalid member '".$containsResult[1]."' from an object of the class '".$className."'");
			}
		}else
		{	$membersOptions = $classDefinition;
		}
			
		$selectMembersDefinitions = array();
		foreach($membersOptions as $m => $d)
		{	$selectMembersDefinitions[strtolower($m)] = $classDefinition[$m];
			$selectMembersDefinitions[strtolower($m)]["memberName_originalCase"] = $m;
		}
		
		$operations = array();
		
		$classDefinition = self::getClassDefinition($className);		// members of the form "memberName" =>array("baseType"=>baseType[, "listType" => listType])
		foreach($membersOptions as $m => $d)
		{	$member_className = $classDefinition[$m]["baseType"];
			if(false === in_array($member_className, self::primtypes()))
			{	if(isset($options["members"]))	// don't fetch members of subobjects unless explicitly asked for
				{	$member_options = $d;
					$member_condition = self::getClassPrimaryKey($member_className)." IN ".
						"(SELECT ".self::makeSQLnames($m)." FROM ".self::getTemporaryTableNameForFetch($className, $numberForVariable).")";
					if(isset($member_options["cond"]))
					{	$member_options["cond"] = array($member_options["cond"], "&&", array("raw"=>$member_condition));
					}else
					{	$member_options["cond"] = array("raw"=>$member_condition);
					}
					$operations = array_merge($operations, $this->createFetchOperation($member_className, $member_options, array_merge($relMember, array($m)), "members", $numberForVariable+1));
				}
			}
		}
		
		return array_merge
		(	array(array
			(	"opName" => "fetch", 
				"className"=>$className, "options" => $options, "selectMembersDefinitions"=>$selectMembersDefinitions, 
				"relMember"=>$relMember, "mode"=>$mode, "numberForVariable"=>$numberForVariable
			)),
			$operations,
			array
			(	self::createRMtableOperation(self::getTemporaryTableNameForFetch($className, $numberForVariable))
			)
		);
	}	
	
	// sets up queueing all database accesses (use 'go' to process the queue - which does all the calls in order)
	public function queue()
	{	$this->validateRoot("Attempting to begin queueing calls on something other than a database.");
		$this->databaseRootObject->queueFlag = true;
	}
	
	private $building=false;	// is set to true while the function go() is building SQL (used for the insertOpNext function)
	private $currentOp;		// when $going is true, this stores what 
	
	// processes the queued calls, performing their functions in order
	public function go()
	{	$this->validateRoot("Attempting to make something other than a database execute queued calls.");
	
		$this->databaseRootObject->queueFlag = false;	// reset queueFlag back to false (off)
		//$nonRepeatableCalls = array();	// record which calls should generate errors if they are tried multiple times
		
		$buildResult = $this->buildSQL($this->callQueue);
		
		$this->executeQueriesAndHandleResult($buildResult["multiqueries"], $buildResult["numberOfCommands_inEachMultiquery"]);
	}
	
	private function buildSQL()
	{	$this->building=true;
		
		// build the sql multiquery
		$multiqueries = array();
		$numberOfCommands_inEachMultiquery = array();
		
		for($n=0; $n<count($this->callQueue); $n++)	// not done as a foreach beacuse of the possibility of inserting another call into the next callQueue index (insertOpNext)
		{	$op = &$this->callQueue[$n];
			$this->currentOp = $n;
			
			if(false == in_array($op["opName"], array_keys(self::$operations)))
			{	throw new cept("Invalid operation: '".$op["opName"]."'");
			}
			
			$generatorResult = $this->call_function_ref(self::$operations[$op["opName"]]["generator"], array(&$op));
			$numberOfCommands_inEachMultiquery[$n] = count($generatorResult);
			$multiqueries[] = implode(";",$generatorResult).";";
		}
		
		$this->building=false;
		
		return array
		(	"numberOfCommands_inEachMultiquery" => $numberOfCommands_inEachMultiquery,
			"multiqueries" => $multiqueries
		);
	}
	
	private function executeQueriesAndHandleResult($multiqueries, $numberOfCommands_inEachMultiquery)
	{	if(count($multiqueries) === 0)
        {   throw new cept("Strange... there are no querys to run");  
        }

        // run the multiquery
		$results = $this->rawSQLquery(implode("", $multiqueries));
		
		// handle the results
		$resultsIndex = 0;	// holds the current results index
		$lastResultsIndex = count($results["resultSet"])-1;	// In the case of an error, the results that were received are processed first, then the error is processed
		foreach($this->callQueue as $n => &$op)
		{	$numApplicableResults = $numberOfCommands_inEachMultiquery[$n];
			$applicableResults = array_slice($results["resultSet"], $resultsIndex, $numApplicableResults);
			$errorNumber = $results["errorNumber"];
			
			if
			(	$errorNumber != 0 && 
				$lastResultsIndex < $resultsIndex + $numApplicableResults - 1 && 	// tests that the current operation was responsible for an error (even if the first result of the operation didn't cause an error)
				$numApplicableResults != 0		// this makes it so results-less operations don't "steal" an error from an operation that does have results (and thus possible errors) (not actually sure if this is neccessary, but it doesn't hurt)
			)
			{	$cutInLine = $this->call_function_ref(self::$operations[$op["opName"]]["errorHandler"], array($op, $errorNumber, $applicableResults, $results["errorMsg"]));
				if($cutInLine === false)
				{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$multiqueries[$n]."' <br>\n".$results["errorMsg"], self::general_query_error, $errorNumber);
				}
				
				$sliceIndex = $n+1;
				
				// build the new calls
				$unfinishedOps = array_slice($this->callQueue, $sliceIndex);
				$this->callQueue = $cutInLine;
				$buildResult = $this->buildSQL();
				
				$this->callQueue = array_merge			// put the new calls in the queue (and remove finished operations from queue)
				(	$cutInLine,
					$unfinishedOps
				);
				$newMultiQueriesSet = array_merge
				(	$buildResult["multiqueries"],
					array_slice($multiqueries, $sliceIndex)
				);
				$new_numberOfCommands_inEachMultiquery = array_merge
				(	$buildResult["numberOfCommands_inEachMultiquery"],
					array_slice($numberOfCommands_inEachMultiquery, $sliceIndex)
				);
				
				// execute
				$this->executeQueriesAndHandleResult($newMultiQueriesSet, $new_numberOfCommands_inEachMultiquery);
				return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
			}
			//else...
					
			// run the resultHandler with the operation call and relevant query results as parameters
			$this->call_function_ref(self::$operations[$op["opName"]]["resultHandler"], array($op, $applicableResults));
			$resultsIndex += $numApplicableResults;
		}

        if(count($results["resultSet"]) === 0 && $results["errorNumber"] === 1065) // result was empty
        {   throw new cept("There is no result set... : ( "+$results["errorMsg"]);  
        }
		
		if(count($results["resultSet"]) > $resultsIndex)
		{	throw new cept("There are too many results (".count($results["resultSet"]).") for the query/queries being processed. Make sure your 'sql' calls only contain one query each and do NOT end in a semi-colon.");
		}else if(count($results["resultSet"]) < $resultsIndex)
		{	throw new cept("There are too few results (".count($results["resultSet"]).") for the queries being processed.");	// this error should never be able to happen
		}
		
		$this->callQueue = array();	// reset callQueue
	}
	
	// performs an sql query
	// queries should NOT end in a semi-colon and there should only be ONE query
	// returns a sqool object that has the memeber 'result' that holds the result of the query
	public function sql($query)
	{	$this->validateRoot("Attempting to run raw SQL on an object that does not represent a database.");	// this may be kinda arbitrary (theres not *real* reason to disallow calling this on non-root objects, other than consistency)
		
		if(substr($query, -1) == ';')
		{	throw new cept("Don't end your sql queries with a semi-colon. Make sure your 'sql' calls only contain one query each and do NOT end in a semi-colon.");
		}
		
		$returnedObject = new sqool();
		$this->databaseRootObject->addToCallQueue(array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>$returnedObject));	// insert the call into the callQueue
		
		if($this->databaseRootObject->queueFlag === false)
		{	$this->go();
		}
		
		return $returnedObject; 
	}
	
	// adds an operation to sqool's backend
	// $SQLgenerator generates SQL that is executed in a multiquery with other SQL statements
	// $resultHandler handles the result returned by the database server
	//		$SQLgenerator must return an array of the form array("numberOfCommands"=>$numberOf_SQL_Commands, "queries"=>"multiqueryString")
	// $errorHandler handles any error that a given operation expects may happen (it should not handle errors that aren't part of normal operation)
	//		$errorHandler returns either false to indicate that the error is not being handled (and should throw an error), or an array of operations to insert before the operation that errored (operations that will hopefully help avoid the error the next time around)
	protected static function addOperation($opName, $SQLgenerator, $resultHandler_in=false, $errorHandler_in=false)
	{	if(in_array($opName, array_keys(self::$operations)))
		{	throw new cept("Attempting to redeclare sqool operation '".$opName."'.");
		}
		
		if($resultHandler_in === false)
		{	$resultHandler = "self::noOp";
		}else
		{	$resultHandler = $resultHandler_in;
		}
		if($errorHandler_in === false)
		{	$errorHandler = "self::noOp";
		}else
		{	$errorHandler = $errorHandler_in;
		}
		self::$operations[$opName] = array("generator"=>$SQLgenerator, "resultHandler"=>$resultHandler, "errorHandler"=>$errorHandler);
	}
	
	public function rm()
	{	if($this->isRoot())
		{	$this->databaseRootObject->addToCallQueue(self::createRMdatabaseOperation($this->connectionInfo["database"]));	// insert the call into the callQueue
		}else
		{	$this->databaseRootObject->addToCallQueue(self::createRMobjectOperation(self::getFrontEndClassName($this), $this->id));	// insert the call into the callQueue
		}

        if($this->databaseRootObject->queueFlag === false)
		{	$this->go();
		}
	}
	
	private static function createRMdatabaseOperation($dbName)
	{	return array("opName"=>"rmDatabase", "DBname"=>$dbName);
	}
	private static function createRMobjectOperation($className, $objectID)
	{	return array("opName"=>"rmObject", "class"=>$className, "objectID"=>$objectID);
	}
	private static function createRMtableOperation($tableName)
	{	return array("opName"=>"rmTable", "tableName"=>$tableName);
	}
	
	// Running this function will counteract the extreme stupidity of magic quotes - NOTE THAT THIS WILL ONLY AFFECT SQOOL
	// I hope the guy who invtented magic quotes has been repeatedly punched in the face
	public static function killMagicQuotes()
	{	self::$killMagicQuotes = true;
	}
	private static $killMagicQuotes=false;	// assumes magic quotes are off
	
	/****************************** FUNCTIONS FOR OPERATION HANDLING ******************************/
	
	private static function initializeSqoolClass()
	{	// create operations
		if(self::$initialized === false)
		{	self::addOperation("insert", 		'$this->insertSQLgenerator', 	'$this->insertResultHandler', 	'$this->insertErrorHandler');
			self::addOperation("save", 			'$this->saveSQLgenerator', 		false, 							'$this->saveErrorHandler');
			self::addOperation("fetch", 		'$this->fetchSQLgenerator', 	'$this->fetchResultHandler',	'$this->fetchErrorHandler');		
			self::addOperation("sql", 			'$this->sqlSQLgenerator', 		'$this->sqlResultHandler');
			self::addOperation("createDatabase",'$this->createDatabaseSQLgenerator');
			self::addOperation("selectDatabase",'$this->selectDatabaseSQLgenerator', false,						'$this->selectDatabaseErrorHandler');
			self::addOperation("createTable", 	'$this->createTableSQLgenerator');
			self::addOperation("addColumns", 	'$this->addColumnsSQLgenerator', false, 						'$this->addColumnsErrorHandler');
			self::addOperation("rmDatabase", 	'$this->rmDatabaseSQLgenerator', false, 						'$this->rmDatabaseErrorHandler');
			self::addOperation("rmTable", 		'$this->rmTableSQLgenerator');
			self::addOperation("getNewListID", 	'$this->getNewListIDSQLgenerator', '$this->getNewListIDResultHandler', '$this->getNewListIDItemErrorHandler');
			self::addOperation("insertListItem", 	'$this->insertListItemSQLgenerator', false, 					'$this->insertListItemErrorHandler');
					
			self::$classes = self::$startingTypes;
			self::$initialized = true;
		}
	}
	
	// handles creating a database if it doesn't exist, creating a table if it doesn't exist, and adding columns if they don't exist
	// $errorsToHandle should be an array of the possible values "database", "table", or "column"
	private function genericErrorHandler($op, $errorNumber, $errorsToHandle, $className=false, $classDefinition=false)
	{	if($errorNumber == 1049 && in_array("database", $errorsToHandle))	// database doesn't exist
		{	$createDBop = array("opName"=>"createDatabase", "databaseName"=>$this->connectionInfo["database"]);
			$selectDBop = array("opName"=>"selectDatabase", "databaseName"=>$this->connectionInfo["database"]);
			return array($createDBop, $selectDBop, $op);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)
		}
		else if($errorNumber == 1046 && in_array("noSelectedDB", $errorsToHandle))	// theres no selected database
		{	$callToQueue = array("opName"=>"selectDatabase", "databaseName"=>$this->connectionInfo["database"]);
			return array($callToQueue, $op);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)
		}
		else if(($errorNumber == 1146 || $errorNumber == 656434540) && in_array("table", $errorsToHandle))	// table doesn't exist - the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
		{	// queue creating a table, a retry of the insert, and the following queries that weren't executed
			$callToQueue = array("opName"=>"createTable", "class"=>$className, "classDefinition"=>$classDefinition);
			
			//if(inOperationsList($callToQueue, $nonRepeatableCalls))
			//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
			//}
			
			$resultOps = array($callToQueue);
			if($className == "list")
			{	$resultOps[] = array
				(	"opName"=>"insert", "class"=>'list', "vars"=>array("columns"=>array('id','list_id','object_id'), "values"=>array(0,0,1)),
					"returnedObjectReference"=>null, "fieldToUpdate"=>false, "numberForVariable"=>0	// numberForVariable shouldn't matter since it won't be used for anything and its at the front of the list of sql statements
				);
			}
			$resultOps[] = $op;
			
			return $resultOps;	// insert the createTable op at the front of the queue, along with the errored op (try it again)
		}
		else if(($errorNumber == 1054 || $errorNumber == 1853321070) && in_array("column", $errorsToHandle))	// column doesn't exist - the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	return array($this->getAddColumnsOp($className, $classDefinition), $op);
		}else
		{	return false;	// don't handle the error (let the system throw an error)
		}
	}
	
					/*************** insert ***************/
					//$op holds: array
					//			(	"opName" => "insert", 
					//				"class" => $className, 
					//				"vars" => array("columns"=>$columns, "values"=>$values), 
					//				"returnedObjectReference" => $newObject, "fieldToUpdate"=>$fieldToUpdate), "numberForVariable"=>$numberForVariable
					//			);	

	
	// inserts a row into a table
	// the resultset of the sql includes the last_insert_ID
	private function insertSQLgenerator($op)
	{	$queries = array
		(	'INSERT INTO '.self::makeSQLnames(self::getBackEndClassName($op["class"])).
				' ('.implode(",", $op["vars"]["columns"]).') '.
				'VALUES ('.implode(",", $op["vars"]["values"]).')',
			'SELECT @'.sqool::sqoolSQLvariableNameBase.$op["numberForVariable"].':= LAST_INSERT_ID()'
		);
		
		if($op["fieldToUpdate"] !== false)
		{	$queries[] = 'UPDATE '.self::makeSQLnames($op["fieldToUpdate"]["class"]).
						' SET '.self::makeSQLnames($op["fieldToUpdate"]["member"])."=".'@'.sqool::sqoolSQLvariableNameBase.$op["numberForVariable"].
						" WHERE ".self::getClassPrimaryKey($op["fieldToUpdate"]["class"])."= @".sqool::sqoolSQLvariableNameBase.$op["fieldToUpdate"]["variable"].
						" LIMIT 1";
		}	
		
		return $queries;
	}
	
	private function insertResultHandler($op, $results)
	{	if($op["returnedObjectReference"] !== null)	// this is null for inserting the first row into the list table
		{	$op["returnedObjectReference"]->id = intval($results[1][0][0]);	// set the ID to the primary key of the object inserted
		}
	}
	
	private function insertErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler
		(	$op, $errorNumber, array("database", "table", "column", "noSelectedDB"), 
			self::getFrontEndClassName($op["returnedObjectReference"]), 
			$this->getClassDefinition($op["returnedObjectReference"])
		);
	}
	
						/*************** insertListItem ***************/
					//$op holds: array
					//			(	"opName" => "insertListItem", 
					//				"listID" => $listID, 		// $listID is an array of the form array("type"=>$type, "value"=>$value) 
					//											//	* where $type can be either 'id' or 'variableNumber'
					//											//  * 'variableNumber means the id will be held in a variable with that number
					//				"objectID" => $objectID, 	// should be null if the object in the list is null, or if it is expected to be filled by a later command
					//				"fieldToUpdate"=>$fieldToUpdate, "numberForVariable"=>$numberForVariable
					//			);	

	
	// inserts a list item into the list table
	// the resultset of the sql includes the last_insert_ID
	private function insertListItemSQLgenerator($op)
	{	if($op["listID"]["type"] == 'id')
		{	$list_id = '@'.sqool::sqoolSQLvariableNameBase.$op["listID"]["value"];
		}else if($op["listID"]["type"] == 'variableNumber')
		{	$list_id = $op["listID"]["value"];
		}else
		{	throw new cept("Bad listID type");
		}
		
		$queries = array
		(	'INSERT INTO list'.
				' (list_id,object_id)'.
				' VALUES ('.$list_id.','.$op["objectID"].')',
			'SELECT @'.sqool::sqoolSQLvariableNameBase.$op["numberForVariable"].':= LAST_INSERT_ID()'
		);
		
		if($op["fieldToUpdate"] !== false)
		{	$queries[] = 'UPDATE '.self::makeSQLnames($op["fieldToUpdate"]["class"]).
						' SET '.self::makeSQLnames($op["fieldToUpdate"]["member"])."=".'@'.sqool::sqoolSQLvariableNameBase.$op["numberForVariable"].
						" WHERE ".self::getClassPrimaryKey($op["fieldToUpdate"]["class"])."= @".sqool::sqoolSQLvariableNameBase.$op["fieldToUpdate"]["variable"].
						" LIMIT 1";
		}	
		
		return $queries;
	}
	
	private function insertListItemErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler
		(	$op, $errorNumber, array("database", "table", "noSelectedDB"), 
			'list', 
			self::$classes['list']
		);
	}
	
						/*************** getNewListID ***************/
					//$op holds: array
					//			(	"opName" => "getNewListID", "numberForVariable"=>$numberForVariable,
					//				"objectReference" => array("object"=>$newObject, "member"=>$memberName)
					//			);	

	
	// gets a new list id and puts it in the variable numbered with the number in $op["numberForVariable"]
	private function getNewListIDSQLgenerator($op)
	{	return array
		(	'UPDATE list SET object_id=LAST_INSERT_ID(object_id+1) WHERE id=0',	// stores object_id+1 in LAST_INSRT_ID()
			'SELECT @'.sqool::sqoolSQLvariableNameBase.$op["numberForVariable"].':= LAST_INSERT_ID()'	// puts object_id+1 into the variable
		);
	}
	
	private function getNewListIDResultHandler($op, $results)
	{	$op["objectReference"]['object']->listsInfo[$op["objectReference"]["member"]]["id"] = intval($results[1][0][0]);	// set the ID to the primary key of the object inserted
	}
	
	private function getNewListIDItemErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler
		(	$op, $errorNumber, array("database", "table", "noSelectedDB"), 
			'list', 
			self::$classes['list']['definition']
		);
	}
	
					/*************** save ***************/
					// $op holds: 	array
					//				(	"opName"=>"save", "class"=>$sqoolObject->getClassName(), 
					//					"id"=>$id, "vars"=>$sqoolObject->setVariables, , "changedVars"=> $sqoolObject->changedVariables, 
					//					"queryColUpdates"=>$queryColUpdates,
					//					"classDefinition"=>$sqoolObject->getClassDefinition()
					//				);	
										
	// renders the SQL for saving $setVariables onto a database object referenced by $sqoolObject
	private function saveSQLgenerator($op)
	{	return array
		(	'UPDATE '.self::getBackEndClassName($op["class"]).' SET '.implode(",", $op["queryColUpdates"])." WHERE id=".$op["id"]
		);
	}
	
	private function saveErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler($op, $errorNumber, array("column", "noSelectedDB"), $op["class"], $op["classDefinition"]);
	}		
	
					/*************** fetch ***************/
					// $op holds: 	array("opName" => "fetch", "className"=>$classTable, "options" => $options, "relMember"=>$relMember, "selectMembersDefinitions"=$selectMembersDefinitions, "mode"=>$mode[, "memberName"=>$memberName])
					// 	$op['selectMembersDefinitions'] contains members of the form:
					// 		array("memberName"=>m, submembers=>array("memberName", "baseType"[, "listtype"]))
	
	private function getTemporaryTableNameForFetch($className, $number)
	{	return "sq_temporary_".self::getBackEndClassName($className).$number;
	}
	
	private function fetchSQLgenerator($op)
	{	
		/*if(isset($options["members"]))
		{	foreach($membersOptions as $m => $d)
			{	if(false === in_array($classDefinition[$m]["baseType"], self::primtypes()))
				{	$callObject = array("opName" => "fetch", "className"=>$className, "options" => $options, "object"=>$object, "mode"=>$mode);
					if($memberName !== false)
					{	$callObject["memberName"] = $memberName;
					}
					
					//$this->insertOpNext($callObject)
					
					//$this->traverseMemberSelection($classDefinition[$m]["baseType"], $m, $d, $callback, $callbackResult);
				}
			}
		}*/
		
		$options = $op["options"];
	
		/* situations:
		* database->memberName = array(object, obj...)
			* result = 
				rowlist (array)
					colList (object)
						$col (object member)
		* object->memberName = prim
			* result = 
				rowlist
					colList (object)
						$col (prim)
		* object->memberName = object
			* ignored - this should be preset with an object
				* only set object to null if ID is 0
		* object->memberName = array(prim, prim ...)
			* ignored - this should already be
		* object->memberName = array(object, obj...)
		* array[] = prim
		* array[] = object
			?	
		*/
		
		$selectMembersDefinitions = $op["selectMembersDefinitions"];
		$memberNames_toSelect = array_merge(array(self::getClassPrimaryKey($op["className"])), array_keys($selectMembersDefinitions));
		$columnNames = implode(",", self::makeSQLnames($memberNames_toSelect));
		
		$temporaryTableName = self::getTemporaryTableNameForFetch($op["className"], $op["numberForVariable"]);
		
		$memberQueryPart = 
			"CREATE TEMPORARY TABLE ".$temporaryTableName." SELECT ".
			$columnNames." FROM ".self::makeSQLnames(self::getBackEndClassName($op["className"]));
	
		if(isset($options["cond"]))
		{	$whereClause = " WHERE ".$this->parseExpression($options["cond"]);
		}else
		{	$whereClause = "";
		}
		
		if(isset($options["sort"]))
		{	if(false === is_int($options["sort"][0]))
			{	throw new cept("The first element of the sort options array must be a 'direction' to sort (sqool::a/A/ascend or sqool::d/D/descend)");
			}
			$currentDirection = 0;
			$sortStatements = array();
			foreach($options["sort"] as $x)
			{	if(is_int($x))
				{	if($x == sqool::ascend)
					{	$currentDirection = "ASC";
					}else if($x == sqool::descend)
					{	$currentDirection = "DESC";
					}else
					{	throw new cept("Invalid sort direction: ".$x);
					}
				}else if(is_array($x))
				{	if(count($x) != 1)
					{	throw new cept("Using an array in a sort clause must have one and only one member");
					}
					
					$sortStatements[] = $x[0];
				}else if(is_string($x))
				{	$sortStatements[] = self::makeSQLnames($x)." ".$currentDirection;
				}
			}
			
			$sortClause = " ORDER BY ".implode(",", $sortStatements);
		}else
		{	$sortClause = "";
		}
		
		// ranges is limited to a start position and an end position - but multiple pieces of a sorted list should be supported later
		if(isset($options["ranges"]))
		{	//$countRanges = count($options["ranges"]);
			//for($n=0;$n<count($options["ranges"]
			if(count($options["ranges"])>2)
			{	throw new cept("ranges does not support more than one range yet");
			}	
			
			$limitClause = " LIMIT ".$options["ranges"][0].",".($options["ranges"][1] - $options["ranges"][0] + 1);
		}else
		{	$limitClause = "";
		}
		
		
		//return $outgoingExtraInfo;
		
				
		return array
		(	$memberQueryPart.$whereClause.$sortClause.$limitClause,
			"SELECT * FROM ".$temporaryTableName
		); 
	}
	
	private function fetchResultHandler($op, $results)
	{	$selectMembersDefinitions = $op['selectMembersDefinitions'];
		$memberNames_toSelect = array_merge(array(self::getClassPrimaryKey($op["className"])), array_keys($selectMembersDefinitions));
		
		$resultSet = self::indexListByValue($results[1], 0);
		
		if($op["mode"] === "tables")
		{	if(count($op["relMember"]) != 2)
			{	throw new cept("Unexpected count (".count($op["relMember"]).") of relMember for a table-type fetch");
			}
			
			$rel1 = $op["relMember"][1];
			$op["relMember"][0]->setVariables[$rel1] = array();
			
			foreach(array_keys($resultSet) as $k)
			{	$newObj = new $op["className"]();	// 
				self::setUpSqoolObject($newObj, $this);
				$newObj->id = $k;
				$op["relMember"][0]->setVariables[$rel1][count($op["relMember"][0]->$rel1)] = $newObj;
			}
		}else if($op["mode"] === "members")
		{	// do nothing
		}else
		{	throw new cept("Invalid mode: '".$op["mode"]."'");
		}
		
		self::loopListReferences($op["relMember"], '$this->setFetchResults', array($op, $resultSet, $selectMembersDefinitions, $memberNames_toSelect));
		
		if($op["numberForVariable"] == 0 && $op["mode"] === "members" && count($resultSet) == 0)
		{	throw new cept("Attempted to fetch an object that doesn't exist.");
		}
		
		/*if($op["mode"] === "tables")
		{	$op["object"]->setVariables[$op["className"]] = $resultingMembersList;
		}
		*/
	}
	
	// $relMember must have at least two elements
	private function loopListReferences($relMember, $callback, $args)
	{	if(is_array($relMember[0]))
		{	foreach($relMember[0] as $i)
			{	$this->loopListReferences_inner(array_merge(array($i), array_slice($relMember, 1)), $callback, $args);
			}
		}else
		{	$this->loopListReferences_inner($relMember, $callback, $args);
		}
	}
	private function loopListReferences_inner($relMember, $callback, $args)		// used only in loopListReferences
	{	if(isset($relMember[1]))
		{	self::loopListReferences(array_merge(array($relMember[0]->$relMember[1]), array_slice($relMember, 2)), $callback, $args);
		}else
		{	$this->call_function_ref($callback, array_merge(array($relMember[0]), $args));
		}
	}
	
	private function setFetchResults($member, $op, $resultSet, $selectMembersDefinitions, $memberNames_toSelect)
	{	if(is_array($member))
		{	foreach($member as $item)
			{	$this->setFetchResults_inner($item, $op, $resultSet, $selectMembersDefinitions, $memberNames_toSelect);
			}
		}else
		{	if($member !== null)	// if member is null, theres nothing to set
			{	$this->setFetchResults_inner($member, $op, $resultSet, $selectMembersDefinitions, $memberNames_toSelect);
			}
		}
	}
	private function setFetchResults_inner($object, $op, $resultSet, $selectMembersDefinitions, $memberNames_toSelect) // god i wish php put it anonymous functions alot sooner
	{	//echo "Doing setFetchResults_inner for ID ".$object->ID."<br>";
		
		$rowVals = $resultSet[$object->id];
		
		foreach($rowVals as $colNum => $val)
		{	if($colNum == 0)
			{	//$newObj->ID = $this->SQLvalToPrimVal($val, "int");	// the object's ID
			}else
			{	$colName = $memberNames_toSelect[$colNum];
				$definition = $selectMembersDefinitions[$colName];
				
				//prettyTree($definition);
				
				if(isset($definition["listType"]))
				{	throw new cept("Lists aren't supported yet");
				
					if($definition["listType"] === "list")
					{	
					}else
					{	throw new cept("Unknown list type");
					}
				}else if(in_array($definition["baseType"], self::primtypes()))	// only set primitive types here
				{	$object->setVariables[$definition["memberName_originalCase"]] = self::SQLvalToPrimVal($val, $definition["baseType"]);
				}else if(self::isADefinedClassName($definition["baseType"]))
				{	if($val !== null)
					{	$object->setVariables[$definition["memberName_originalCase"]] = new $definition["baseType"]();
						//echo "Before ID set: ";print_r($object->setVariables[$definition["memberName_originalCase"]]);echo "<br>";
						
						$object->setVariables[$definition["memberName_originalCase"]]->id = $val;	// set the ID of the member object
						self::setUpSqoolObject($object->setVariables[$definition["memberName_originalCase"]], $this->databaseRootObject);
						
						//echo "After ID set: ".$object->setVariables[$definition["memberName_originalCase"]]->ID."<br>";
						
					}else
					{	$object->setVariables[$definition["memberName_originalCase"]] = null;
					}	
				}else
				{	throw new cept("Fetching something that isn't a primitive or an object (lists aren't supported yet)");
				}
			}
		}
	}
	
	
	private function fetchErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler
		(	$op, $errorNumber, array("database", "table", "column", "noSelectedDB"), 
			$op["className"], 
			$this->getClassDefinition($op["className"])
		);
	}	
	

	// validates that an array only contains keys: "members", "cond", "sort", and "ranges"
	private static function validateFetchOptions($array)
	{	$keys = array_keys($array);
		foreach($keys as $k)
		{	if(false == in_array($k, array("members", "cond", "sort", "ranges")))
			{	throw new cept("Invalid fetch option: '".$k."'");
			}
		}
	}
	
	private static function containsMembers($className, $members)
	{	$classDefinition = self::getClassDefinition($className);		// members of the form "memberName" =>array("baseType"=>baseType[, "listType" => listType])
		$keys = array_keys($classDefinition);
		foreach($members as $m)
		{	if(false === in_array($m, $keys))
			{	return array(false, $m);
			}
		}
		return array(true);
	}
	private function parseExpression($expression)
	{	$whereClause = "";
		if(isset($expression["raw"]))
		{	$whereClause = $expression["raw"];
		}else
		{	// todo: probably remove this - I don't think we need it anymore (in fact, this function isn't needed if thats the case)
            for($n=0; $n<count($expression); $n+=2)
			{	$leftPart = $this->parseExpressionLeft($expression[$n]);
				if($n == 0 && $leftPart["operators"][0] != "")
				{	throw new cept("Sqool syntax error: You can't begin a 'cond' expression with an operator. Error here: '".print_r($expression, true)."'");
				}
							
				if($n+1 < count($expression))
				{	$rightPart = $this->parseExpressionRight($expression[$n+1]);
				}
				$whereClause .= $leftPart["operators"][0].$leftPart["member"].$leftPart["operators"][1].$rightPart;
			}
		}
		return $whereClause;
	}
	
	private function parseExpressionLeft($condLeft)
	{	if(is_array($condLeft))
		{	return $this->parseExpression($condLeft);
		}else
		{	$operators = array();
			
			$position = 0;
            $operator1 = null;  $operator2 = null; $member = null; // will be overwritten
			$position += self::getCharsExcept($condLeft, $position, "_", "09azAZ", $operator1);	// get first operator
			$operators[0] = trim($operator1);	// trim off whitspace
			
			$result = self::getVariableKeyWord($condLeft, $position, $member);	// $member is written into
			if($result < 0)
			{	throw new cept("Couldn't parse 'cond' parameter: '".$member."'");
			}else
			{	$position += $result;
			}
			
			$position += self::getCharsExcept($condLeft, $position, "_", "09azAZ", $operator2);	// get second operator
			$operators[1] = trim($operator2);	// trim off whitspace
			
			return array("operators"=>$operators, "member"=>self::makeSQLnames($member));
		}
	}
	
	private function parseExpressionRight($condRight)
	{	if(is_array($condRight))
		{	return $this->parseExpression($condRight);
		}else
		{	return "'".$this->escapeString($condRight)."'";
		}
	}
	
	
					/*************** sql ***************/
					// $op holds: array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>&$resultVariable);
					
	private function sqlSQLgenerator($op)
	{	return array
		(	$op["query"]
		);
	}
	
	private function sqlResultHandler($op, $results)
	{	$op["resultVariableReference"]->setVariables["result"] = $results;	// set the variable returned by the method 'sql'
		//echo "HERE<BR>";var_dump($op["resultVariableReference"]);
	}
	
					/*************** createDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"createDatabase", "databaseName"=>databaseName);
					
	// creates a database a user has attempted to connect to - and connects to it
	private function createDatabaseSQLgenerator($op)
	{	return array
		(	'CREATE DATABASE '.$op["databaseName"]
		);
	}
	
					/*************** selectDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"selectDatabase", "databaseName"=>databaseName);
					
	// creates a database a user has attempted to connect to - and connects to it
	private function selectDatabaseSQLgenerator($op)
	{	return array
		(	'USE '.$op["databaseName"]
		);
	}
	
	private function selectDatabaseErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler($op, $errorNumber, array("database"));
	}
	
					/*************** createTable ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"createTable", "class"=>$className, "classDefinition"=>$classDefinition);
					
	// returns the SQL to create a mysql table named $tableName 
	// $op["sqlColumnDefinitions"] should be an associtive array where the key is the name of the column, and the value is the type
	private function createTableSQLgenerator($op)
	{	//if(inOperationsList($op, $nonRepeatableCalls))
		//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
		//}
		//$nonRepeatableCalls[] = $op;
		
		$query = 'CREATE TABLE '.self::makeSQLnames($op["class"]).' (';
		$query .= self::getClassPrimaryKey($op["class"]).' '.self::getStandardPrimaryKeyDefinition();	// add an object id field (sq for sqool defined field - as opposed to user defined)
		
		$columns = self::sqoolTypesToMYSQL($op["classDefinition"]);
		
		foreach($columns as $col => $type)
		{	$query .= ', '.self::makeSQLnames($col).' '.$type;	// name -space- type
		}
		$query.=")";
	
		return array
		(	$query
		);
	}
	
	private function getStandardPrimaryKeyDefinition() 
	{	return 'INT NOT NULL PRIMARY KEY AUTO_INCREMENT';
	}
	
					/*************** addColumns ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"addColumns", "class"=>$className, "sqlColumnDefinitions"=>$newColumns);	
					// $newColumns is an array with members of the form $memberName => $type
	
	private function addColumnsSQLgenerator($op)
	{	//if(inOperationsList($op, $nonRepeatableCalls))
		//{	throw new cept("In the table '".$op["class"]."', could not create columns '".implode("', '", array_keys($op["sqlColumnDefinitions"]))."'", self::$column_creation_error);
		//}
		//$nonRepeatableCalls[] = $op;
		
		//var_dump($op);
		
		$alterations = array();
		foreach($op["sqlColumnDefinitions"] as $memberName => $SQLtype)
		{	$alterations[] = self::makeSQLnames($memberName).' '.$SQLtype;
		}
		
		return array
		(	'ALTER TABLE '.$op["class"].' ADD ('.implode(",", $alterations).')'
		);
	}
	
	private function addColumnsErrorHandler($op, $errorNumber, $results, $errorMessage)
	{	if($errorNumber == 1075)	// Incorrect table definition; there can be only one auto column and it must be defined as a key
		{	throw new cept
			(	"SQooL attempted to create a second auto-incriment key."
				." This means you have to indicate (or change) the primary key's name for the SQooL class '".$op["class"]."' in its definition."
				." Alternatively, you can rename the offending column in the database."
				." Here is the SQL error (number 1075): "
				.$errorMessage
			);
		}else
		{	return false;	// don't handle the error (let the system throw an error)
		}
	}
	
					/*************** rmDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"rmDatabase", "DBname"=>$connectionInfo["database"])
	
	private function rmDatabaseSQLgenerator($op)
	{	return array
		(	'DROP DATABASE '.self::makeSQLnames(strtolower($op["DBname"]))
		);
	}
	
	private function rmDatabaseErrorHandler($op, $errorNumber, $results)
	{	if($errorNumber == 1049 || $errorNumber == 1008)	// database doesn't exist
		{	return array();
		}else
		{	return false;
		}
	}	
	
					/*************** rmTable ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"rmTable", "tableName"=>$tableName)
	
	private function rmTableSQLgenerator($op)
	{	return array
		(	'DROP TABLE '.self::makeSQLnames($op["tableName"])
		);
	}
	
	/********************** NON-STANDARD METHODS USED INTERNALY, but also ENCOURAGED FOR EXTERNAL USE *************/
	
	public function escapeString($string)
	{	$this->connectIfNot();
		if(self::$killMagicQuotes)
		{	$string = stripslashes($string);
		}
		return $this->connectionInfo["con"]->real_escape_string($string);
	}
	
	// keys of array2 will take precedence
	public static function assarray_merge($array1, $array2)
	{	foreach($array2 as $k => $v)
		{	$array1[$k] = $v;
		}
		return $array1;
	}
	
	// extracts a string from "theString" (beginning at "index") that is made up of the characters in "singles" or "ranges"
	// puts the result in "result" 
	public static function getCertainChars($theString, $index, $singles, $ranges, &$result)
	{	$result = "";
		$n=0;
		while(isset($theString[$index+$n]) && self::charIsOneOf($theString[$index+$n], $singles, $ranges))
		{	$result .= $theString[$index+$n];
			$n+=1;
		}
		return $n;
	}

    // extracts a string from "theString" (beginning at "index") that is made up of the characters that are NOT in "singles" or "ranges"
	// puts the result in "result"
	public static function getOtherChars($theString, $singles, $ranges, &$curPositionOut)
	{	$curPosition = $curPositionOut;
        $result = "";

		while(isset($theString[$curPosition]) && ! self::charIsOneOf($theString[$curPosition], $singles, $ranges))
		{	$result .= $theString[$curPosition];
			$curPosition += 1;
		}

        $curPositionOut = $curPosition;
		return $result;
	}
	
	// extracts a string from "theString" (beginning at "index") that does NOT contain the characters in "singles" or "ranges"
	// puts the result in "result" 
	public static function getCharsExcept($theString, $index, $singles, $ranges, &$result)
	{	$result = "";
		$n=0;
		while(isset($theString[$index+$n]) && !self::charIsOneOf($theString[$index+$n], $singles, $ranges))
		{	$result .= $theString[$index+$n];
			$n+=1;
		}
		return $n;
	}
	
	// tests if a character is in the list of "singles" or in one of the "ranges"
	public static function charIsOneOf($theChar, $singles, $ranges)
	{	if($theChar === '')
		{	return false;
		}
		
		$singlesLen = strlen($singles);
		for($n=0; $n<$singlesLen; $n+=1)
		{	if($theChar === $singles[$n])
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
	
	// just like php and C and other programming languages, variables must consist of alphanumeric characters and cannot start with a numerical digit
	public static function validateVariableName($variable)
	{	$string = "".$variable;
		$theArray = str_split($string);
		
		if(self::charIsOneOf($theArray[0], '', '09'))
		{	throw new cept("Attempted to define a member named '".$variable."'. Member variables cannot start with a numerical digit", sqool::invalid_variable_name);
		}
		
		foreach($theArray as $c)
		{	if(false == self::charIsOneOf($c, '_', 'azAZ09'))
			{	throw new cept("String contains the character '".$c."'. Variable name expected - a string containing only alphanumeric characters and the character '_'.", sqool::invalid_variable_name);
			}
		}
	}
	
	public static function ignoreLeadingWhitespace($string, $index)
	{	$dumdum = null; // will be overwritten
        $whitespace = " \t\n\r";
		$whitespaceChars = self::getCertainChars($string, $index, $whitespace, '', $dumdum);	// ignore whitespace
		return $whitespaceChars;
	}
	
	// gets a variable starting with a-z or A-Z and containing only the characters a-z A-Z 0-9 or _
	// puts the variable in $result
	// discards leading whitespace
	// returns -1 if string is done
	// returns -2 if string is an invalid variable
	public static function getVariableKeyWord($string, $index, &$result)
	{	$whitespaceChars = self::ignoreLeadingWhitespace($string, $index);
		$index += $whitespaceChars;
		if($index >= strlen($string))
		{	return -1;	// no variable
		}
		
		if(self::charIsOneOf($string[$index], '', "09"))
		{	return -2;	// not a valid variable
		}
		
		$numchars = sqool::getCertainChars($string, $index, "_", "azAZ09", $result);	// get keyword (variable name)
		if($numchars==0)
		{	return -2;	// not a valid variable
		}else
		{	return $numchars+$whitespaceChars;
		}
	}
	
	// gets one of an array of constant strings from an input string
	// discards leading whitespace
	// returns -1 if $stringToRead is done
	// returns 0 if the $stringToGet isn't found
	// returns number of characters gotten (strlen($stringToGet)) on success
	public static function getConstantStringToken($stringToRead, $startIndex, $stringsToGet, &$result)
	{	$whitespaceChars = self::ignoreLeadingWhitespace($stringToRead, $startIndex);
		$startIndex += $whitespaceChars;
		if($startIndex >= strlen($stringToRead))
		{	return -1;	// $stringToRead is done
		}
		
		foreach($stringsToGet as $s)
		{	if($s == substr($stringToRead, $startIndex, strlen($s)))
			{	$result = $s;
                return $whitespaceChars+strlen($s);	// got it
			}
		}
		
		return 0;	// didn't get any of them
	}

    // returns string gotten
    // $curPosition will be incrimented by the length of the string gotten
    public static function getConstantString($stringToRead, $stringsToGet, &$curPositionOut)
    {   foreach($stringsToGet as $s)
		{	if($s === substr($stringToRead, $curPositionOut, strlen($s)))
			{	$curPositionOut += strlen($s);
                return $s;	// got it
			}
		}
    }
	
	// calls function references, even if they start with 'self::' or '$this->'
	// $params should be an array of parameters to pass into $function
	private function call_function_ref($function, $params)
	{	if('$this->' == substr($function, 0, 7))
		{	return call_user_func_array(array($this, substr($function, 7)), $params);
		}else if('self::' == substr($function, 0, 6))
		{	return call_user_func_array(get_class()."::".substr($function, 6), $params);
		}else
		{	return call_user_func_array($function, $params);
		}
	}
	
	// returns all the classes an object or class inherits from (the list of class parents basically)
	// returns the root class first, the object's class last
	public static function getFamilyTree($objectOrClassName)
	{	// get the className for $objectOrClassName
		if(is_object($objectOrClassName))
		{	$className = get_class($objectOrClassName);
		}else
		{	$className = $objectOrClassName;
		}
		
		$classList = array($className);
		while(true)
		{	// get the next parent class up the inheritance hierarchy
			$lastClassFirst = array_reverse($classList);
			$nextClass = get_parent_class($lastClassFirst[0]);
			if($nextClass === false)
			{	break;	// no more classes (it must be defined in the root-parent
			}
			
			$classList[] = $nextClass;
		}
		
		return array_reverse($classList);
	}
	
	// returns a list of parents class name (in an inheritance hierarchy) that the method was originally defined/overridden in
	// $objectOrClassName is the object or class to start looking for the method
	public static function methodIsDefinedIn($objectOrClassName, $methodName)
	{	$family = self::getFamilyTree($objectOrClassName);
		
		$resultClasses = array();
		foreach($family as $c)
		{	if( in_array($methodName, self::get_defined_class_methods($c)) )
			{	$resultClasses[] = $c;
			}
		}
		return $resultClasses;
	}
		
	// gets the visible methods actually defined in a given class
	public static function get_defined_class_methods($className)
	{	if(false == class_exists($className))
		{	throw new cept("There is no defined class named '".$className."'");
		}
		
		$reflect = new ReflectionClass($className);
		$methods = $reflect->getMethods();
		$classOnlyMethods = array();
		foreach($methods as $m)
		{	if ($m->getDeclaringClass()->name == $className) 
			{	$classOnlyMethods[] = $m->name;
			}
		}
		return $classOnlyMethods;
	}

	// changes an object to a given class type (used in classCast_callMethod)
	public static function changeClass(&$obj, $newClass)
	{	if(false == class_exists($newClass))
		{	throw new Exception("non-existant class '".$newClass."'");
		}
		//if( false == in_array($newClass, self::getFamilyTree($obj)) )
		//{	throw new Exception("Attempting to cast an object to a non-inherited type");
		//}
		$obj = unserialize(preg_replace		// change object into type $new_class
		(	"/^O:[0-9]+:\"[^\"]+\":/i", 
			"O:".strlen($newClass).":\"".$newClass."\":", 
			serialize($obj)
		));
	}
	
	// calls a method on a class casted object
	public static function classCast_callMethod(&$obj, $newClass, $methodName, $methodArgs=array())
	{	$oldClass = get_class($obj);
		
		self::changeClass($obj, $newClass);
		$result = call_user_func_array(array($obj, $methodName), $methodArgs);	// get result of method call
		self::changeClass($obj, $oldClass);	// change back
		
		return $result;
	}
	
	public static function in_array_caseInsensitive($thing, $array)
	{	$loweredList = array();
		foreach($array as $a)
		{	$loweredList[] = strtolower($a);		
		}
		return in_array(strtolower($thing), $loweredList);
	}
	
	public static function array_insert($array, $pos, $value)
	{	$array2 = array_splice($array,$pos);
	    $array[] = $value;
	    return array_merge($array,$array2);
	}
	
	/********************** BELOW THIS ARE MzETH/oDS FOR INTERNAL USE ONLY *************************/
	
	// does nothing - used for default function callbacks
	private static function noOp()
	{	return false;
	}
	
	// executes a multiquery
	private function rawSQLquery($query)
	{	$connectResult = $this->connectIfNot();
		if(is_array($connectResult))
		{	return self::assarray_merge( $connectResult, array("resultSet"=>array()) );	// error information
		}
				
		if(self::$debugFlag)
		{	echo "\n<br>\nExecuting: ".$query."\n<br>\n";
		}
		
		$connection = $this->connectionInfo["con"];
		
		/* execute multi query */
		$resultSet = array();
		if($connection->multi_query($query))
		{	do	/* store first result set */
			{	if($result = $connection->store_result())
				{	$results = array();
					while($row = $result->fetch_row())
					{	$results[] = $row;
					}
					$result->free();
					$resultSet[] = $results;
				}else
				{	$resultSet[] = array();
				}
				
				if($connection->more_results())
				{	$connection->next_result();
				} else
				{	break;
				}
			}while(true);
		}
		
		$returnResult = array("resultSet"=>$resultSet, "errorNumber"=>$connection->errno, "errorMsg"=>$connection->error);	// returns the results and the last error number (the only one that may be non-zero)
		
		if(self::$debugFlag)
		{	echo "Results: ";
			//prettyTree($results);
			print_r($returnResult);
			echo "<br>\n";
		}
		
		return $returnResult;
	}	
	
	// if the object is not connected, it connects
	// returns true if a new connection was made
	// returns -1 if requested database doesn't exist
	private function connectIfNot()
	{	if($this->connectionInfo["con"] === false)
		{	//connect
			
			if(self::$debugFlag)
			{	if(self::$debugMessageHasBeenWritten === false)
				{	self::$debugMessageHasBeenWritten = true;
					echo "\n\n***** To turn off debug messages, add \"sqool::debug(false);\" to your code *****\n\n";
				}
				
				echo "\n<br><br>\nAttempting to connect to the database ".$this->connectionInfo["database"]." on ".$this->connectionInfo["host"]." with the username ".$this->connectionInfo["username"].".\n<br><br>\n";
			}
			
			@$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"], $this->connectionInfo["database"]);

			
			if($this->connectionInfo["con"]->connect_errno)
			{	if($this->connectionInfo["con"]->connect_errno == 1049)	// database doesn't exist
				{	$errNo = 1049;
					$errMsg = $this->connectionInfo["con"]->connect_error;
					// connect to the database server (but not the actual database since it doesn't exist yet)
					$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"]);
					return array("errorNumber"=>$errNo, "errorMsg"=>$errMsg);
				}else
				{	throw new cept('Connect Error (' . $this->connectionInfo["con"]->connect_errno . ') ' . $this->connectionInfo["con"]->connect_error, sqool::connection_failure, $this->connectionInfo["con"]->connect_errno);
				}
			}
			return true;
		}
		return false;
	}
	
	// adds a save operation into the queue for a certain object
	private function saveSqoolObject($sqoolObject)
	{	if(count($sqoolObject->changedVariables) == 0)
		{	return; // do nothing if theres nothing to do (no members have been changed)
		}
		
		foreach($this->createSaveOp($sqoolObject) as $operation)
		{	$this->databaseRootObject->addToCallQueue($operation);	// insert the call into the callQueue
		}
		
		$sqoolObject->clearChangedVariables();
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}
	}
	
	private function createSaveOp($sqoolObject)
	{	$memberDefinition = self::getClassDefinition(self::getFrontEndClassName($sqoolObject));		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		$extraOps = array();
		$queryColUpdates = array();
		foreach($sqoolObject->setVariables as $member => $val)
		{	$result = $this->PHPvalToSqoolVal
			(	$memberDefinition[$member], $val, 
				array("class"=>self::getFrontEndClassName($sqoolObject), "member"=>$member, "variable"=>0, "objectReference"=>$sqoolObject)
			);
			
			$valueToSave = $result["value"];
			$extraOps = array_merge($extraOps, $result["ops"]);
			$queryColUpdates[] = self::makeSQLnames($member)."=".$valueToSave;
		}
		
		return array_merge
		(	array(array					
			(	"opName"=>"save", "class"=>self::getFrontEndClassName($sqoolObject),
				"id"=>$sqoolObject->id, "vars"=>$sqoolObject->setVariables, "changedVars"=> $sqoolObject->changedVariables, 
				"queryColUpdates"=>$queryColUpdates,
				"classDefinition"=>$this->getClassDefinition($sqoolObject)
			)),
			$extraOps
		);
	}
	
	// returns an addColumns operation that can be put into sqool's call queue
	private function getAddColumnsOp($className, $classDefinition /*, $nonRepeatableCalls*/)
	{	// add columns defined in this class that aren't in the table schema yet
		$showColumnsResult = $this->rawSQLquery("SHOW COLUMNS FROM ".$className.";");	// this can probably be done in-line with the other multiquery items - but we'll have to do the following loop in a mySQL procedure (later)
	
		if($showColumnsResult["errorNumber"] != 0)
		{	throw new cept("Some error happened in getAddColumnsOp: '".$showColumnsResult["errorMsg"]."'");
		}
		
		//echo "AHHHGA ";var_dump($showColumnsResult["resultSet"][0]);echo "<br>";
		
		// assemble a list of columns that already exist
		$columns = array();
		foreach($showColumnsResult["resultSet"][0] as $DBcol)
		{	$columns[] = $DBcol[0];
		}
		
		// assemble a list of columns that are defined in the sclass but aren't part of the DB schema yet
		$newColumns = array();
		foreach($classDefinition as $colName => $info)
		{	//echo $colName." is apparently in ".print_r($columns, true)."<br>";
			//var_dump(in_array($colName, $columns));echo "<br>";
			
			if( false === in_array($colName, $columns) )
			{	$newColumns = self::assarray_merge( $newColumns, self::sqoolTypesToMYSQL(array($colName => $info)) );
			}
		}
		if( false === in_array("id", $columns) )
		{	$newColumns = self::assarray_merge($newColumns, array
			(	"id"=>self::getStandardPrimaryKeyDefinition()
			));	
		}
		
		return array("opName"=>"addColumns", "class"=>$className, "sqlColumnDefinitions"=>$newColumns);
		
		//if(inOperationsList($callToQueue, $nonRepeatableCalls))
		//{	throw new cept("Could not create table '".$className."'", self::$column_creation_error);
		//}
	}
	
	// takes some varialbe definitions
	// returns an associative array where the keys are the names of the members, and the values are their mysql type
	private static function sqoolTypesToMYSQL($variableDefinitions)
	{	$result = array();
		foreach($variableDefinitions as $memberName => $definition)
		{	if(isset($definition["listType"]) && $definition["listType"] === "list")
			{	$type = "INT DEFAULT NULL";
			}else if(in_array($definition["baseType"], self::primtypes()))
			{	$thePrimitives = self::primitives();
				$type = $thePrimitives[$definition["baseType"]]." NOT NULL";
			}else if(in_array("sqool", self::getFamilyTree($definition["baseType"])))	// if it actually is a sqool object
			{	$type = "INT DEFAULT NULL";
			}else		
			{	throw new cept("Invalid baseType '".$definition["baseType"]."'");
			}
			$result[$memberName] = $type;
		}
		return $result;
	}
	
	// checks to see if $op1 is in $oplist
	// will return true if they have the same value
	private function inOperationsList($op1, $oplist)
	{	return in_array($op1, $oplist);
	}
	
	private static function getClassPrimaryKey($sqoolClassName)
	{	return 'id';
	}
	
	// parses type declarations for a sqool class (in the form "type:name  type:name  etc")
	// returns an array of the form "memberName" => array("baseType"=>baseType[, "listType" => listType])
	// see parseMemberDefinition for examples of the returned data
	private static function parseClassDefinition($members, $className)
	{	$result = array();
		$index = 0;
		while(true)
		{	$nextMember = self::parseMemberDefinition($members, $index);	// set the result array
			
			if($nextMember === false)
			{	break;		// done parsing (no more members)
			}
			if(false == self::isADefinedType($nextMember[1]["baseType"]))	// is a defined type
			{	throw new cept("The sqool class '".$nextMember[1]["baseType"]."' has not been defined and it is not the name of a primitive type either");
			}
			
			$name = $nextMember[0];
			if(self::in_array_caseInsensitive($name, array_keys($result)))
			{	throw new cept("Error: can't redeclare member '".$name."' in class definition (note: member names are NOT case-sensitive)");
			}
			if($name == self::getClassPrimaryKey($className))
			{	throw new cept("Error: sqool reserves the member name '".$name."' (note: member names are NOT case-sensitive)");
			}
			if(self::in_array_caseInsensitive($name, self::reservedMemberNames()))
			{	throw new cept("Error: sqool already has functions named '".$name."' (note: member names are NOT case-sensitive)");
			}
			$result = self::assarray_merge($result, array($nextMember[0] => $nextMember[1]));
		}
		return $result;
	}
	
	// returns an array where the only member has a key (which represents the name of the member) which points to an array of the form array("baseType"=>baseType[, "listType" => listType])
	// examples of returned values: array("bogus"=>array("baseType"=>"int"))  array("bogus2"=>array("baseType"=>"int", "listType"=>"list")
	//		  						array("bogus3"=>array("baseType"=>"someobjName")  array("bogus4"=>array("baseType"=>"yourmomisanobject", "listType"=>"list") 
	private static function parseMemberDefinition($members, &$index)
	{	$baseType = null; $listOrRefOrNone = null; $name = null; $dumdum = null; // will be overwritten
        $result = self::getVariableKeyWord($members, $index, $baseType);
		if($result == -1)
		{	return false;	// no more members (string is over)
		}else if($result == -2)
		{	throw new cept("Error parsing types: type was expected but not found.\n");
		}else
		{	$index += $result;
		}


		$result = self::getVariableKeyWord($members, $index, $listOrRefOrNone);
		if($result > 0 && $listOrRefOrNone=="list")
		{	$index += $result;
			$listIsFound = true;
		}else if($result<=0)
		{	$listIsFound = false;
		}else			// something other than "list" is found
		{	throw new cept("Error parsing types: 'list' or ':' expected but got'".$listOrRefOrNone."'\n");
		}
		
		$result = self::getConstantStringToken($members, $index, array(":"), $dumdum);
		if($result <= 0)
		{	throw new cept
			(	"Error parsing types: ':' was expected but not found starting from character ".$index." in member definition that begins with '".substr($members, $index, 20)."'.\n"
			);		// error colon isn't found
		}else
		{	$index += $result;
		}
		
		$result = self::getVariableKeyWord($members, $index, $name);
		if($result <= 0)
		{	throw new cept("Error parsing types: className was expected but not found.\n"); 	// error if name isn't found
		}else
		{	$index += $result;
		}
		
		if($listIsFound)
		{	return array($name, array("baseType"=>$baseType, "listType"=>'list') );
		}else
		{	return array($name, array("baseType"=>$baseType) );
		}
	}
	
	// throws error if this object doesn't have an ID (or doesn't have an ID waiting for it in the queue)
	private function requireID($actionText)
	{	if($this->id === false)
		{	if($this->databaseRootObject !== false && count($this->databaseRootObject->callQueue) != 0)
			{	$this->databaseRootObject->go();			// TODO: handle this better later (write code so that an extra multi-query isn't needed)
				$this->databaseRootObject->queue();			// turn queuing back on (since it was obviously on before)
			}

			if($this->id === false)	//if the ID is still false
			{	throw new cept("Attempted to ".$actionText." an object that isn't in a database yet. (Use sqool::insert to insert an object into a database).");
			}
		}
	}
	private function isRoot()
	{	if($this->databaseRootObject === $this)
		{	return true;
		}else
		{	return false;
		}
	}
	// make sure the calling function is the root
	private function validateRoot($error)
	{	if(false === $this->isRoot())
		{	throw new cept($error);
		}
	}
	// make sure the calling function is NOT the root
	private function validateNOTRoot($error)
	{	if($this->isRoot())
		{	throw new cept($error);
		}
	}
	
	private static function setUpSqoolObject($sqoolObject, $root)
	{	$sqoolObject->databaseRootObject = $root;
	}
	
	private static function primValToSQLVal($val, $type)
	{	if($type == "bool")
		{	if($val)
			{	return 1;
			}else
			{	return 0;
			}
		}else
		{	return $val;
		}
	}
	private static function SQLvalToPrimVal($val, $type)
	{	if($type == "bool")
		{	if($val == 1)
			{	return true;
			}else
			{	return false;
			}	
		}else if($type == "tinyint" || $type == "int" || $type == "bigint" )
		{	return intval($val);
		}else if($type == "float" || $type == "double")
		{	return floatval($val);
		}else
		{	return $val;
		}
	}
	
	// returns the value, and any operations needed to create that value
	// $fieldToUpdateAfterInsert is only used if the $sqoolVal is a sqool object that is not in the database yet (and thus needs to be inserted)
		// has the form array("class"=>$className, "member"=>$member, "variable"=>$numberForVariable, "objectReference"=>$newObject),
		// "objectReference" is only needed for inserting lists
	// $listInfo can either be "new", an integer listID, or false if it isn't new but not ID is immediately available
	private function PHPvalToSqoolVal($sqoolType, $sqoolVal, $fieldToUpdateAfterInsert, $listInfo=false)
	{	$ops = array();

        $isPrimitiveType = in_array($sqoolType["baseType"], self::primtypes());
        if(!$isPrimitiveType && ! in_array("sqool", self::getFamilyTree($sqoolType["baseType"])))
        {   throw new cept("Invalid type: '".$sqoolType["baseType"]."'");
        }


        if(isset($sqoolType["listType"]))
        {	if($sqoolType["listType"] == "list")
            {	if($listInfo === "new")
                {	$sqlVal = null;		// to be filled with the ID of the list after it's inserted
                    $ops = $this->createInsertListOp(array("baseType"=>$sqoolType["baseType"]), $sqoolVal, $fieldToUpdateAfterInsert);
                }else if($listInfo === false)
                {	throw new cept("We're not doing list IDs yet1.");
                }else if(is_int($listInfo))
                {	throw new cept("We're not doing list IDs yet2.");
                }else
                {	throw new cept("Unknown listInfo format in PHPvalToSqoolVal. listInfo: ".$listInfo);
                }
            } else if($sqoolType["listType"] == "listItem")
            {	$sqlVal = null;		// to be filled with the ID of the list after it's inserted
                $ops = $this->createInsertOp_explicitDefinition($sqoolVal, $fieldToUpdateAfterInsert, $sqoolType["baseType"], self::$classes["definition"]);
            }else
            {	throw new cept("Invalid listType ".$sqoolType["listType"]);
            }
        }else
        {	if($isPrimitiveType)
            {   $sqlVal = sqool::primValToSQLVal($sqoolVal, $sqoolType["baseType"]);

            } else
            {   if($sqoolVal === null)
                {	$sqlVal = null;
                }else if($sqoolVal->id === false)
                {	$sqlVal = null;		// to be filled with the ID of the object after it's inserted
                    $ops = $this->createInsertOp($sqoolVal, $fieldToUpdateAfterInsert);
                }else
                {	$sqlVal = $sqoolVal->id;
                    // queue saving the object - if the object has stuff to save
                    if(count($sqoolVal->changedVariables) > 0)
                    {	$ops = $this->createSaveOp($sqoolVal);
                    }
                }
            }
        }
		
		if($sqlVal === null)
		{	return array("value"=>"null", "ops"=>$ops);
		}else
		{	return array("value"=>"'".$this->escapeString($sqlVal)."'", "ops"=>$ops);
		}
	}
	
	private function containsMember($memberName)
	{	if(get_class($this) === "sqool")
		{	return self::isADefinedClassName($memberName);
		}
		else
		{	if(self::isADefinedClassName(get_class($this)))
			{	return self::in_array_caseInsensitive($memberName, array_keys($this->getClassDefinition($this)));
			}else
			{	throw new cept("containsMember called for a class that isn't defined (this should never happen)");
			}
		}
	}
	
	// eventually, this might recognize which need the weird quote and which don't
	// $variableNames should either be  or a single string
		// if $variableNames is an array of strings, the function will return an array of quoted names
		// if $variableNames is a single string, the function will return the quoted name as a single string 
	private static function makeSQLnames($variableNames)
	{	if(is_array($variableNames))
		{	$result = array();
			foreach($variableNames as $v)
			{	$result[] = "`".$v."`";
			}
		
			return $result;
		}else
		{	return "`".$variableNames."`";
		}
	}
	
	// takes in an array of arrays, and returns an associative array where each inner array is keyed based on the value of a given element of each array
	private static function indexListByValue($list, $valueElementName)
	{	$result = array();
		
		foreach($list as $i)
		{	$result[$i[$valueElementName]] = $i;
		}
		return $result;
	}
}
?>
