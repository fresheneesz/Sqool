<?php
/*	See http://bt.x10hosting.com/Sqool/ for documentation

	This was created with the LiPG parser generator written by Billy Tetrud.
	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2008, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

include_once(dirname(__FILE__)."/cept.php");	// exceptions with stack traces

/*	Defines:		
		class sqool			connection to a database
		 protected members:
			$debugFlag		This is made availble to sqool sub-classes (ie protected) if you want to use this flag in an extension. It is changed through calls to the method 'debug'
		 public methods:
			new sqool		constructor
			debug			turns on or off debugging messages (for all sqool connections and objects) - debugging is on by default so.... yeah..
			getDB			returns a connection to another database on the same host
			sclass			should return the definition for a sqool class type. This function should be defined for a class extending sqool. 
							Does not create a table in the DB until an object is inserted (lazy table creation).
								Member types: bool, string, tinyint, int, bigint, float, :class:, :type: list
			insert			insets an object into the database
			save			saves variables into the database that have been set for an object
			memberUnset		unsets a member from the object (so that when you save, that member won't be updated) (yea yea, I woulda named it 'unset' but php complained)
			fetch			returns select object members and (if any members are objects or lists) members of those members, etc. See the function for its use.
			sql				executes a single sql command (this is queueable)
			queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'go' method)
								calls that are queued include: insert, fetch, sql, save
			go				performs all the queries in the queue
			addOperation	adds an operation in the form of up to three functions: an SQL generator, a result handler, and an error handler
							Note that the SQL generator for an operator can add to or modify the $op data passed to it, and use that additional or modified data in the result handler
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
			rm				Deletes (removes) an entity in the database server (either a class [table] or a whole database)
			killMagicQuotes	Run this to undo the idiocy of magic quotes (for all sqool connections and objects)
		 protected methods:
			addToCallQueue	Adds an operation at the end of the call queue
			insertOpNext	Inserts an operation into the call queue as the next operation to be executed. May only be run during the SQL generation phase.
				
				
	Tables and naming conventions (for the tables that make up the objects under the covers):
		* CLASSNAME				holds all the objects of a certain type
			* sq_CLASSNAME_id		the ID of the object
			* MEMBER				example field-name of a member named "MEMBER". 
									If this is a primitive, it will hold a value. 
									If this is a list or object, it will hold an ID. Objects are treated as references - they all start with a null id (note this is not the same as ID 0).
		* sq_lists				holds all the lists in the database
			* listID				the ID of the list that owns the object
			* objectID				the ID of the object/primitive that the list owns
			
		* sq_TYPE				holds all of the primitive values of a certain type that are refered to by lists (for example sq_int or sq_float)
			* primID				the ID of the value
			* value					the value
			
	Internal operations - operations used to execute the queueable sqool calls
		* "insert"
		* "save"
		* "fetch"
		* "sql"
 */

/*	To do:
		* write unset
		* what happens when you insert an object that contains an object - what if that object is from another DB? Can I detect that?
		* don't execute a multiquery if theres no queries to execute
		* don't save a particular member if that member hasn't been updated (changedVariables has been created to represent this)
			* write a resave method to save an object even if it hasn't been updated
		* don't execute fetches for data that already exists
			* keep track of what objects have been fetched and sync them (objects that point to the same row in the db should point to the same obejct in the php)
			* write a refetech method to fetch data even if it has already been fetched
		* make sure case is lowered for all internal names
		* Make sure you lower the case of all member names and classnames as they come in
		* have a facility for limiting operations that can be done on an object (allowOnly and disallow should be mutually exclusive). Make sure the mechanism can't affect internal behaviors (for example the insert call using create table or whatever)
		* Make sure any reference parameters of operations are not read from (so that changing what a variable points to after a 'queue' call but berfore a 'go' call won't screw things up)
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
			* deDupPrims	removes duplicates in the primitive lists tables and fixes the sq_lists table acordingly (this should be run in a batch job)
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
			* when someone assigns more than say 50KB of data to a string, output a message that tells the programmer why its better performance-wise to use a filesystem than to use a DB for files. Tell them the ups and downs of a FS and a DB for file storage, including that a FS only uses bandwidth to send to a client, while a DB has to send to the server then the client, while a FS may not be as scalable - unless you're using some "cloud" service that has a scalable filesystem like amazon's S3
		* A book called High performance mysql explained you can use "the ODRER BY SUBSTRING(column, length) trick to convert the values to character strings, which will permit in-memory temporary tables
*/

/*	List of optimizations:
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
	private static $initialized = false;
	private static $classes = array();	// members should be of the form phpClassName=>array("name"=>tableName, "definition"=>definition) 
										//	where definition should be an array with keys representing the names of each member and the values being an array of the form array("baseType"=>baseType[, "listType" => listType])
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
		if(self::in_array_caseInsensitive($sqoolFrontendClassName, self::reservedSqoolClassNames()))
		{	$inFunction = false;
			throw new cept("Sqool reserves the class name ".$sqoolFrontendClassName." for its own use. Please choose another class name.");
		}
		
		// add class to list of $classes
		self::$classes[$sqoolFrontendClassName] = array
		(	"name"=>strtolower($sqoolFrontendClassName), 
			"definition"=>self::parseClassDefinition($members, $sqoolFrontendClassName)
		);
	}
	
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
	private static function reservedTableNames()
	{	$tableNames = array("sq_lists");
		$tableNames = array("sq_olists");
		foreach(self::primtypes() as $pt)
		{	$tableNames[] = "sq_".$pt;
		}
		return $tableNames;
	}
	private static function reservedSqoolClassNames()
	{	return array_merge(self::reservedTableNames(), self::coretypes());
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
	
	// internal instance variables 
	
	private $databaseRootObject;	// the sqool object that represents the database as a whole (the root sqool object should point to itself here
	private $connectionInfo=array("con"=>false);		// username, password, host, database, con (the connection to the database)
	
	private $callQueue = array();	// can be accessed from operations added to sqool
	private $queueFlag = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
	
	private $ID=false;						// the ID of the object (inside the table $classTable)	
	private $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
	private $changedVariables = array();	// catalogues the variables that have been changed
	
	protected function clearSetVariables()
	{	$this->setVariables = array();
		$this->changedVariables = array();
	}
	
	// meant for use by a class that extends sqool
	protected function addToCallQueue($additionalOperation)
	{	$this->callQueue[] = $additionalOperation;
	}
	
	// meant for use by a class that extends sqool
	// inserts an operation into the next slot
	// can only be called in SQLgenerator functions for operations (otherwise it'll throw an error)
	protected function insertOpNext($op)
	{	if($this->building !== true)
		{	throw new cept("This must only be called in SQL generator functions for an operation");
		}
		$this->callQueue = self::array_insert($this->callQueue, $this->currentOp + 1, $op);
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
	
	// Access a local database - attempts to create database if it doesn't exist
	// Can create a database if your host allows you to, otherwise database must already exist
	// reteurns a sqool object
	// the $conIn variable is for internal use
	public static function connect($usernameIn, $passwordIn, $databaseIn, $hostIn='localhost', $conIn=false)
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
		
		$this->databaseRootObject->addToCallQueue($this->createInsertOp($newObject, false));	// insert the call into the callQueue
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}	
		
		return $newObject;	// return the new object that has (or will have) a new ID and a database connection
	}
	// $fieldToUpdate should either be false (for no update) or should be an array of the form array("class"=>class, "member"=>member, "variable"=>number)
		// where 'class' and 'member' is the member of a certain class to update after the insert
		// and 'number' is the the number used to create the variable name where the operation should look for the ID of the object containing the field to update
			// the variable name will be made from class and number concatenated
	private function createInsertOp($newObject, $fieldToUpdate)
	{	$className = self::getFrontEndClassName($newObject);
		$variables = $newObject->setVariables;
		$changedVars = $newObject->changedVariables;
		$newObject->clearSetVariables();	// since the database has been set (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		
		return array
		(	"opName" => "insert", 
			"class" => $className, 
			"vars" => $variables,
			"returnedObjectReference" => $newObject, "fieldToUpdate"=>$fieldToUpdate
		);
	}
	
	// to use the '__set' magic function in child classes, use ___set (with three underscores instead of two)
	function __set($name, $value)
	{	$this->validateNOTRoot("You can't set member variables of a Sqool object that represents the database");
		
		if( false == $this->containsMember($name) )
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
	{	if( $this->containsMember($name) )	// if sqool class has the member $name
		{	if(isset($this->setVariables[$name]))
			{	return $this->setVariables[$name];
			}else
			{	throw new cept("Attempted to get the member '".$name."', but it has not been fetched yet.");
			}
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
		
		if(count($this->setVariables) == 0)
		{	throw new cept("Attempted to save an empty dataset to the database");
		}
		$this->requireID("save");
		
		$this->databaseRootObject->saveSqoolObject($this);
	}	
	
	public function memberUnset()
	{	throw new cept("Unset isn't written yet");
	}
	
	// fetches objects from the database
	// returns a sqool object
	// If the sqool object has a connection but does not have a class, it represents the entire database where each table is a list member
	// Note: if a member is a class type object, it will be NULL if it doesn't point to any object
	// throws an error if an invalid member is accessed (if a non-existant member or object is attempted to be accessed)
	/*	
		object->fetch(membersSelection);
		// OR
		object->fetch("memberName");	// fetch a single member without any options (in the case of the root object, it fetches an array of every object of that type)
		// OR
		rootObject->fetch("className", objectID);	// fetches a single object of a given class (the calling object must be the root object) - this returns the object fetched (but does not return any data from the database - a call to fetch must be done on the returned object to get its data)
		// OR
		nonRootObject->fetch()		// fetches all the members of the object (but does not fetch members of object-members)
		
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
			// OR a mix of the two
		)	
	*/
	public function fetch( /*$fetchOptions ) OR fetch($className) OR fetch($className, $id*/ )
	{	$args = func_get_args();
		if(count($args) == 0)
		{	if($this->isRoot())
			{	throw new cept("Sqool doesn't support fetching the entire database by calling 'fetch' without arguments yet.");
			}else
			{	$mode = "members";
				$firstArg = array();
				$hasFetchOptions = false;
				$ID = $this->ID;
				$className = self::getFrontEndClassName($this);
				$objectRef = $this;
			}
		}
		else if(count($args) == 1)
		{	if($this->isRoot())
			{	$mode = "tables";
				$firstArg = $args[0];
			}else
			{	$this->requireID("fetch from");
			
				$mode = "members";
				$hasFetchOptions = true;
				$firstArg = $args[0];
				$ID = $this->ID;
				$className = self::getFrontEndClassName($this);
				$objectRef = $this;
			}
		}
		else if(count($args) == 2)	// return an object with a connection and an ID (makes no call to the database server)
		{	$this->validateRoot("A call to 'fetch' with 2 arguments must be called on a database root object (an object returned by sqool::connect)");

			$className = $args[0];
			$objectRef = new $className();
			$objectRef->ID = $args[1];
			self::setUpSqoolObject($objectRef, $this->databaseRootObject);
			return $objectRef;
		}
		else
		{	throw new cept("fetch called with too many arguments");
		}
		
		if($this->databaseRootObject->queueFlag === false)
		{	$this->databaseRootObject->queue();
			$goImmedaitely = true;
		}else
		{	$goImmedaitely = false;
		}
		
		$fetchOptions = $this->membersToKeyValue($firstArg);
		
		if($mode == "tables")	// if this is the root
		{	foreach($fetchOptions as $k=>$v)
			{	$this->fetchBack($k, $v, $this, "tables");
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
			
			$this->databaseRootObject->fetchBack
			(	$className, $mainOptions,
				$objectRef, "members", $className
			);
		}	
		
		if($goImmedaitely)
		{	$this->databaseRootObject->go();
		}
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
	
	// queues the fetch
	private function fetchBack($className, $options, $object, $mode)
	{	$this->validateRoot("Attempting to use the backend fetchback function with an object that does not represent a database.");

		$this->addToCallQueue(array("opName" => "fetch", "className"=>$className, "options" => $options, "object"=>$object, "mode"=>$mode));	// insert the call into the callQueue
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
			{	throw new cept("Invalid call: '".$op["opName"]."'");
			}
			
			$generatorResult = $this->call_function_ref(self::$operations[$op["opName"]]["generator"], array(&$op));
			$numberOfCommands_inEachMultiquery[$n] = $generatorResult["numberOfCommands"];
			$multiqueries[] = $generatorResult["queries"];
		}
		
		$this->building=false;
		
		return array
		(	"numberOfCommands_inEachMultiquery" => $numberOfCommands_inEachMultiquery,
			"multiqueries" => $multiqueries
		);
	}
	
	private function executeQueriesAndHandleResult($multiqueries, $numberOfCommands_inEachMultiquery)
	{	// run the multiquery
		$results = $this->rawSQLquery(implode("", $multiqueries));
		
		if(self::$debugFlag)
		{	print_r($results);
			echo "<br>\n";
		}
		
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
			{	$cutInLine = $this->call_function_ref(self::$operations[$op["opName"]]["errorHandler"], array($op, $errorNumber, $applicableResults));
				if($cutInLine === false)
				{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$multiqueries[$n]."' <br>\n".$results["errorMsg"], self::general_query_error, $errorNumber);
				}
				
				$sliceIndex = $n;
				
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
		
		$returnedObject = new sqool();
		$this->databaseRootObject->addToCallQueue(array("opName"=>"sql", "query"=>$query, "resultVariableReference">$returnedObject));	// insert the call into the callQueue
		
		if($this->databaseRootObject->queueFlag == false)
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
	public static function addOperation($opName, $SQLgenerator, $resultHandler_in=false, $errorHandler_in=false)
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
		{	$this->databaseRootObject->addToCallQueue(array("opName"=>"rmDatabase", "DBname"=>$this->connectionInfo["database"]));	// insert the call into the callQueue
		}else
		{	$this->databaseRootObject->addToCallQueue(array("opName"=>"rmObject", "class"=>self::getFrontEndClassName($this), "objectID"=>$this->ID));	// insert the call into the callQueue
		}
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
			self::addOperation("addColumns", 	'$this->addColumnsSQLgenerator');
			self::addOperation("rmDatabase", 	'$this->rmDatabaseSQLgenerator', false, 						'$this->rmDatabaseErrorHandler');
			
			self::$initialized = true;
		}
	}
	
	// handles creating a database if it doesn't exist, creating a table if it doesn't exist, and adding columns if they don't exist
	// $errorsToHandle should be an array of the possible values "database", "table", or "column"
	private function genericErrorHandler($op, $errorNumber, $errorsToHandle, $className=false, $classDefinition=false)
	{	if($errorNumber == 1049 && in_array("database", $errorsToHandle))	// database doesn't exist
		{	$createDBop = array("opName"=>"createDatabase", "databaseName"=>$this->connectionInfo["database"]);
			$selectDBop = array("opName"=>"selectDatabase", "databaseName"=>$this->connectionInfo["database"]);
			return array($createDBop, $selectDBop);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)
		}
		else if($errorNumber == 1046 && in_array("noSelectedDB", $errorsToHandle))	// theres no selected database
		{	$callToQueue = array("opName"=>"selectDatabase", "databaseName"=>$this->connectionInfo["database"]);
			return array($callToQueue);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)
		}
		else if(($errorNumber == 1146 || $errorNumber == 656434540) && in_array("table", $errorsToHandle))	// table doesn't exist - the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
		{	// queue creating a table, a retry of the insert, and the following queries that weren't executed
			$columns = self::sqoolTypesToMYSQL($classDefinition);
			$callToQueue = array("opName"=>"createTable", "class"=>$className, "sqlColumnDefinitions"=>$columns);
			
			//if(inOperationsList($callToQueue, $nonRepeatableCalls))
			//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
			//}
			
			return array($callToQueue);	// insert the createTable op at the front of the queue, along with the errored op (try it again)
		}
		else if(($errorNumber == 1054 || $errorNumber == 1853321070) && in_array("column", $errorsToHandle))	// column doesn't exist - the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	return array($this->getAddColumnsOp($className, $classDefinition));
		}else
		{	return false;	// don't handle the error (let the system throw an error)
		}
	}
	
					/*************** insert ***************/
					//$op holds: array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject, "fieldToUpdate"=>$fieldToUpdate);	
	
	// inserts a set of rows into a table
	// $rows must be an associative array where the keys are the column names, and the values are the values being set
	// the resultset of the sql includes the last_insert_ID
	private function insertSQLgenerator($op)
	{	$memberDefinition = self::getClassDefinition($op["class"]);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		if($op["fieldToUpdate"] === false)
		{	$numberForVariable = 0;
		}else
		{	$numberForVariable = $op["fieldToUpdate"]["variable"]+1;
		}
		
		$columns = array();
		$values = array();
		foreach($op["vars"] as $member => $val)
		{	$columns[] = self::makeSQLnames(strtolower($member));
			$values[] = $this->PHPvalToSqoolVal
			(	$memberDefinition[$member]["baseType"], $val, 
				array("class"=>$op["class"], "member"=>$member, "variable"=>$numberForVariable)
			);
		}
		
		$numberOfCommands = 2;	// insert normally uses two sql statements
		$queries =  'INSERT INTO '.self::makeSQLnames(self::getBackEndClassName($op["class"])).
					' ('.implode(",", $columns).') '.
					'VALUES ('.implode(",", $values).');'.
					'SELECT @'.self::getBackEndClassName($op["class"]).$numberForVariable.':= LAST_INSERT_ID();';
		
		if($op["fieldToUpdate"] !== false)
		{	$numberOfCommands += 1;
			$queries .= 'UPDATE '.self::makeSQLnames($op["fieldToUpdate"]["class"]).
						' SET '.self::makeSQLnames($op["fieldToUpdate"]["member"])."=".'@'.self::getBackEndClassName($op["class"]).$numberForVariable.
						" WHERE ".self::getClassPrimaryKey($op["fieldToUpdate"]["class"])."= @".$op["fieldToUpdate"]["class"].$op["fieldToUpdate"]["variable"].";";
		}	
		
		return array("numberOfCommands" => $numberOfCommands, "queries" => $queries);
	}
	
	private function insertResultHandler($op, $results)
	{	$op["returnedObjectReference"]->ID = intval($results[1][0][0]);	// set the ID to the primary key of the object inserted
	}
	
	private function insertErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler
		(	$op, $errorNumber, array("database", "table", "column", "noSelectedDB"), 
			self::getFrontEndClassName($op["returnedObjectReference"]), 
			$this->getClassDefinition($op["returnedObjectReference"])
		);
	}
	
					/*************** save ***************/
					// $op holds: 	array
					//				(	"opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->setVariables, 
					//					"classDefinition"=>$sqoolObject->getClassDefinition()
					//				);	
					
	// renders the SQL for saving $setVariables onto a database object referenced by $sqoolObject
	private function saveSQLgenerator($op)
	{	$memberDefinition = self::getClassDefinition($op["class"]);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		$queryColUpdates = array();
		foreach($op["vars"] as $member => $val)
		{	$valueToSave = $this->PHPvalToSqoolVal
			(	$memberDefinition[$member]["baseType"], $val, 
				array("class"=>$op["class"], "member"=>$member, "variable"=>0)
			);
			$queryColUpdates[] = self::makeSQLnames($member)."=".$valueToSave;
		}
		
		return array
		(	"numberOfCommands" => 1,
			"queries" => 'UPDATE '.self::getBackEndClassName($op["class"]).' SET '.implode(",", $queryColUpdates).";"
		);
	}
	
	private function saveErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler($op, $errorNumber, array("column", "noSelectedDB"), $op["class"], $op["classDefinition"]);
	}		
	
					/*************** fetch ***************/
					// $op holds: 	array("opName" => "fetch", "className"=>$classTable, "options" => $options, "object"=>$object, "mode"=>$mode)
					// 	$op['resultsInfo'] is added in later, which contains members of the form:
					// 		array("memberName"=>m, submembers=>array("memberName", "baseType"[, "listtype"]))
	
	// 	$op['resultsInfo'] is added in later, which contains members of the form:
					// 		array("memberName"=>m, submembers=>array("memberName", "baseType"[, "listtype"]))
	private function fetchSQLgenerator($op)
	{	$queries = "";
		$numQueries = 0;
		$this->traverseMemberSelection
		(	$op["className"], false, $op["options"], '$this->fetchCallback', array
			(	"callbackType"=> "SQL",
				"queries"=>&$queries, 
				"numQueries"=>&$numQueries
			)
		);
		
		return array
		(	"numberOfCommands" => $numQueries,
			"queries" => $queries
		); 
	}
	
	private function fetchErrorHandler($op, $errorNumber, $results)
	{	//if($op["mode"] === "tables")	// root
		{	$className = "";
			$resultsCount = 0;
			$this->traverseMemberSelection
			(	$op["className"], false, $op["options"], '$this->fetchCallback', array
				(	"callbackType"=>"error",
					"numResults"=>count($results), 	// total results
					"resultsCount"=>&$resultsCount, // variables used to count results traversed through each time the callback is called
					"className"=>&$className		// the className that caused the error
				)
			);
			return $this->genericErrorHandler
			(	$op, $errorNumber, array("database", "table", "column", "noSelectedDB"), 
				$className, 
				$this->getClassDefinition($className)
			);
		}
	}	
	
	private function fetchResultHandler($op, $results)
	{	$resultsIndexCount = 0;
		
		$this->traverseMemberSelection
		(	$op["className"], false, $op["options"], '$this->fetchCallback', array
			(	"callbackType"=>"results",
				"mode"=>$op["mode"],
				"results"=>$results,
				"resultsIndexCount"=> &$resultsIndexCount,
				"object"=>$op["object"]
			)
		);
	}
	
	
	// receives write-arguments via $extraInfo (to which it can write out results)
	// $selectOptions are the "cond", "sort", and "range" options
	// $selectMembersDefinitions is an array with members of the form: strtolower("memberName") =>array("baseType"=>baseType[, "listType" => listType], "memberName_originalCase"=>memberName)
	// $extraInfo["callbackType'] can be "SQL", "error", or "results"
	//		* $extraInfo for "SQL" contains array("callbackType"=>"SQL", "queries"=>&$queries, "numQueries"=>&$numQueries)
	//		* $extraInfo for "error" contains array("callbackType"=>"error", "numResults"=>count($results), "resultsCount"=>&$resultsCount, "className"=>&$className)
	//		* $extraInfo for "results" contains array("callbackType"=>"results", "mode"=>$mode, "results"=>$results, "resultsIndexCount"=> &$resultsIndexCount, "object"=>$op["object"])
	// 
	private function fetchCallback($className, $memberName, $selectMembersDefinitions, $selectOptions, $extraInfo)
	{	$error= $SQL= $results= false;	//initialize (false until told otherwise)
		if	($extraInfo["callbackType"] == "error"){		$error= true;}
		else if($extraInfo["callbackType"] == "SQL"){		$SQL= true;}
		else if($extraInfo["callbackType"] == "results"){	$results= true;}
		else{	throw new cept("Invalid callbackType");}
		
		$outgoingExtraInfo = array();
		
		if($error)
		{	if($extraInfo["numResults"] == $extraInfo["resultsCount"])
			{	$extraInfo["className"] = $className;
			}
			$extraInfo["resultsCount"] += 1;
		}
		else if($SQL || $results)
		{	/* situations:
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
			
			$memberNames_toSelect = array_merge(array(self::getClassPrimaryKey($className)), array_keys($selectMembersDefinitions));
			
			if($SQL)
			{	$columnNames = implode(",", self::makeSQLnames($memberNames_toSelect));
				
				$memberQueryPart = "SELECT ".$columnNames." FROM ".self::makeSQLnames(self::getBackEndClassName($className));
				$numberOfCommands = 1;
				
				$outgoingExtraInfo = $extraInfo;
			}else if($results)
			{	if($extraInfo["mode"] === "tables")
				{	$resultingMembersList = array();
				}else if($extraInfo["mode"] !== "members")
				{	throw new cept("Invalid mode: '".$extraInfo["mode"]."'");
				}
				
				//echo "HR: "; var_dump($extraInfo["results"]); echo "<br><br>";
				
				for($n=$extraInfo["resultsIndexCount"]; $n < $extraInfo["resultsIndexCount"]+1; $n++)
				{	$resultSet = $extraInfo["results"][$n];
					foreach($resultSet as $row => $rowVals)
					{	if($extraInfo["mode"] === "tables")
						{	$newObj = new $className();
							self::setUpSqoolObject($newObj, $this);
						}else if($extraInfo["mode"] === "members")
						{	if(count($resultSet) > 1)
							{	throw new cept("Expected just one result row - got: ".count($resultSet));
							}
							$newObj = $extraInfo["object"];
						}
						
						foreach($rowVals as $colNum => $val)
						{	if($colNum == 0)
							{	$newObj->ID = $this->SQLvalToPrimVal($val, "int");	// the object's ID
							}else
							{	$colName = $memberNames_toSelect[$colNum];
								$definition = $selectMembersDefinitions[$colName];
								
								if(isset($definition["listType"]))
								{	throw new cept("Lists aren't supported yet");
								
									if($definition["listType"] == "list")
									{	
									}else
									{	throw new cept("Unknown list type");
									}
								}else if(in_array($definition["baseType"], self::primtypes()))	// only set primitive types here
								{	$newObj->setVariables[$definition["memberName_originalCase"]] = $this->SQLvalToPrimVal($val, $definition["baseType"]);
								}else if(self::isADefinedClassName($definition["baseType"]))
								{	if($val !== null)
									{	$newObj->setVariables[$definition["memberName_originalCase"]] = new $definition["baseType"]();
										$newObj->setVariables[$definition["memberName_originalCase"]]->ID = $val;	// set the ID of the member object
										$this->setUpSqoolObject($newObj->setVariables[$definition["memberName_originalCase"]], $this->databaseRootObject);
									}else
									{	$newObj->setVariables[$definition["memberName_originalCase"]] = null;
									}	
								}else
								{	throw new cept("Fetching something that isn't a primitive or an object (lists aren't supported yet)");
								}
							}
						}
						
						if($extraInfo["mode"] === "tables")
						{	$resultingMembersList[] = $newObj;
						}
					}
				}
				
				if($extraInfo["mode"] === "tables")
				{	$extraInfo["object"]->setVariables[$className] = $resultingMembersList;
				}
				
				$extraInfo["resultsIndexCount"] += 1;
				
				return false;	// this return value shouldn't be used
			}
			
			if($SQL)
			{	if(isset($selectOptions["cond"]))
				{	$whereClause = " WHERE ".$this->parseExpression($selectOptions["cond"]);
				}else
				{	$whereClause = "";
				}
				
				// add sorting here
				if(isset($selectOptions["sort"]))
				{	if(false === is_int($selectOptions["sort"][0]))
					{	throw new cept("The first element of the sort options array must be a 'direction' to sort (sqool::a/A/ascend or sqool::d/D/descend)");
					}
					$currentDirection = 0;
					$sortStatements = array();
					foreach($selectOptions["sort"] as $x)
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
				if(isset($selectOptions["ranges"]))
				{	//$countRanges = count($options["ranges"]);
					//for($n=0;$n<count($options["ranges"]
					if(count($selectOptions["ranges"])>2)
					{	throw new cept("ranges does not support more than one range yet");
					}	
					
					$limitClause = " LIMIT ".$selectOptions["ranges"][0].",".($selectOptions["ranges"][1] - $selectOptions["ranges"][0] + 1);
				}else
				{	$limitClause = "";
				}
				
				$querySets = $memberQueryPart.$whereClause.$sortClause.$limitClause.";";
				
				$extraInfo["queries"] .= $querySets;
				$extraInfo["numQueries"] += 1;
			}
		}
		
		return $outgoingExtraInfo;
	}
	
	// basically an itterator for member selection fetch options
	// $memberName is passed into the callback
	private function traverseMemberSelection($className, $memberName, $options, $callback, $extraInfo=false)
	{	self::validateFetchOptions($options);
		$resultsInfo = array();
		
		$classDefinition = self::getClassDefinition($className);		// members of the form "memberName" =>array("baseType"=>baseType[, "listType" => listType])
			
		if(isset($options["members"]))
		{	$membersOptions = self::membersToKeyValue($options["members"]);

			$containsResult = self::containsMembers($className, array_keys($membersOptions));
			if($containsResult[0] === false)
			{	throw new cept("Attempting to fetch invalid member '".$containsResult[0]."' from an object of the class '".$className."'");
			}
			
			$selectMembersDefinitions = array();
			foreach($membersOptions as $m => $d)
			{	$selectMembersDefinitions[strtolower($m)] = $classDefinition[$m];
				$selectMembersDefinitions[strtolower($m)]["memberName_originalCase"] = $m;
			}
		}else
		{	$selectMembersDefinitions = array();
			foreach($classDefinition as $m => $d)
			{	$selectMembersDefinitions[strtolower($m)] = $d;
				$selectMembersDefinitions[strtolower($m)]["memberName_originalCase"] = $m;
			}
		}
		
		$selectOptions = array();
		if(isset($options["cond"]))
		{	$selectOptions["cond"] = $options["cond"];
		}
		if(isset($options["ranges"]))
		{	$selectOptions["ranges"] = $options["ranges"];
		}
		if(isset($options["sort"]))
		{	$selectOptions["sort"] = $options["sort"];
		}
		
		$callbackResult = self::call_function_ref($callback, array($className, $memberName, $selectMembersDefinitions, $selectOptions, $extraInfo));
		
		if(isset($options["members"]))
		{	foreach($membersOptions as $m => $d)
			{	if(false === in_array($classDefinition[$m]["baseType"], self::primtypes()))
				{	$this->traverseMemberSelection($classDefinition[$m]["baseType"], $m, $d, $callback, $callbackResult);
				}
			}
		}
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
		return "(".$whereClause.")";
	}
	
	private function parseExpressionLeft($condLeft)
	{	if(is_array($condLeft))
		{	return $this->parseExpression($condLeft);
		}else
		{	$operators = array();
			
			$position = 0;
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
		(	"numberOfCommands" => 1,
			"queries" => $op["query"].";"
		);
	}
	
	private function sqlResultHandler($op, $results)
	{	$op["resultVariableReference"]->result = $results;	// set the variable returned by the method 'sql'
	}
	
					/*************** createDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"createDatabase", "databaseName"=>databaseName);
					
	// creates a database a user has attempted to connect to - and connects to it
	private function createDatabaseSQLgenerator($op)
	{	return array
		(	"numberOfCommands" => 1,
			"queries" => 	'CREATE DATABASE '.$op["databaseName"].';'
		);
	}
	
					/*************** selectDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"selectDatabase", "databaseName"=>databaseName);
					
	// creates a database a user has attempted to connect to - and connects to it
	private function selectDatabaseSQLgenerator($op)
	{	return array
		(	"numberOfCommands" => 1,
			"queries" => 	'USE '.$op["databaseName"].';'
		);
	}
	
	private function selectDatabaseErrorHandler($op, $errorNumber, $results)
	{	return $this->genericErrorHandler($op, $errorNumber, array("database"));
	}
	
					/*************** createTable ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"createTable", "class"=>$className, "sqlColumnDefinitions"=>$newColumns);	
					// 		$newColumns is an array with members of the form $memberName => $type
					
	// returns the SQL to create a mysql table named $tableName 
	// $op["sqlColumnDefinitions"] should be an associtive array where the key is the name of the column, and the value is the type
	private function createTableSQLgenerator($op)
	{	//if(inOperationsList($op, $nonRepeatableCalls))
		//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
		//}
		//$nonRepeatableCalls[] = $op;
		
		$query = 'CREATE TABLE '.self::makeSQLnames($op["class"]).' (';
		
		$query .= self::getClassPrimaryKey($op["class"]).' INT NOT NULL PRIMARY KEY AUTO_INCREMENT';	// add an object id field (sq for sqool defined field - as opposed to user defined)
		
		foreach($op["sqlColumnDefinitions"] as $col => $type)
		{	$query .= ', '.self::makeSQLnames($col).' '.$type;	// name -space- type
		}
		$query.=");";
	
		return array
		(	"numberOfCommands" => 1,
			"queries" => $query
		);
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
		
		$alterQuery = '';
		$oneAlredy = false;
		foreach($op["sqlColumnDefinitions"] as $memberName => $SQLtype)
		{	if($oneAlredy)
			{	$alterQuery .= ',';
			}else
			{	$oneAlredy = true;
			}
			
			$alterQuery .= self::makeSQLnames($memberName).' '.$SQLtype;
		}
		
		return array
		(	"numberOfCommands" => 1,
			"queries" => 'ALTER TABLE '.$op["class"].' ADD ('.$alterQuery.');'
		);
	}
	
					/*************** rmDatabase ***************/
					// 			internal operation 
					// $op holds: array("opName"=>"rmDatabase", "DBname"=>$connectionInfo["database"])
	
	private function rmDatabaseSQLgenerator($op)
	{	return array
		(	"numberOfCommands" => 1,	
			"queries" => 'DROP DATABASE '.self::makeSQLnames(strtolower($op["DBname"])).';'
		);
	}
	
	private function rmDatabaseErrorHandler($op, $errorNumber, $results)
	{	if($errorNumber == 1049)	// database doesn't exist
		{		return array();
		}else
		{	return false;
		}
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
	
	// gets a variable starting with a-z or A-Z and containing only the characters a-z A-Z 0-9 or _
	// puts the variable in $result
	// discards leading whitespace
	// returns -1 if string is done
	// returns -2 if string is an invalid variable
	public static function getVariableKeyWord($string, $index, &$result)
	{	$whitespace = " \t\n\r";
		
		$whitespaceChars = self::getCertainChars($string, $index, $whitespace, '', $dumdum);	// ignore whitespace
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
	// returns number of characters gotten (count($stringToGet)) on success
	public static function getConstantStringToken($stringToRead, $index, $stringsToGet)
	{	$whitespace = " \t\n\r";
		
		$index += self::getCertainChars($stringToRead, $index, $whitespace, '', $dumdum);	// ignore whitespace
		if($index >= strlen($stringToRead))
		{	return -1;	// $stringToRead is done
		}
		
		foreach($stringsToGet as $s)
		{	if($s == substr($stringToRead, $index, count($s)))
			{	return count($s);	// got it
			}
		}
		
		return 0;	// didn't get any of them
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
			}while($connection->next_result());
		}
		
		return array("resultSet"=>$resultSet, "errorNumber"=>$connection->errno, "errorMsg"=>$connection->error);	// returns the results and the last error number (the only one that may be non-zero)
	}	
	
	// if the object is not connected, it connects
	// returns true if a new connection was made
	// returns -1 if requested database doesn't exist
	private function connectIfNot()
	{	if($this->connectionInfo["con"] === false)
		{	//connect
			
			if(self::$debugFlag)
			{	echo "\n<br><br>\nAttempting to connect to the database ".$this->connectionInfo["database"]." on ".$this->connectionInfo["host"]." with the username ".$this->connectionInfo["username"].".\n<br><br>\n";
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
		
		$this->databaseRootObject->addToCallQueue($this->createSaveOp($sqoolObject));	// insert the call into the callQueue
		
		$sqoolObject->clearSetVariables();	// since the database has been changed (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}
	}
	
	private function createSaveOp($sqoolObject)
	{	return array					
		(	"opName"=>"save", "class"=>self::getFrontEndClassName($sqoolObject), 
			"vars"=>$sqoolObject->setVariables, "changedVars"=> $sqoolObject->changedVariables, 
			"classDefinition"=>$this->getClassDefinition($sqoolObject)
		);
	}
	
	// returns an addColumns operation that can be put into sqool's call queue
	private function getAddColumnsOp($className, $classDefinition /*, $nonRepeatableCalls*/)
	{	// add columns defined in this class that aren't in the table schema yet
		$showColumnsResult = $this->rawSQLquery("SHOW COLUMNS FROM ".$className.";");	// this can probably be done in-line with the other multiquery items - but we'll have to do the following loop in a mySQL procedure (later)
		
		if($showColumnsResult["errorNumber"] != 0)
		{	throw new cept("Some error happened in getAddColumnsOp: '".$showColumnsResult["errorMsg"]."'");
		}
		
		$columns = array();
		foreach($showColumnsResult[0][0] as $DBcol)
		{	$columns[] = $DBcol[0];
		}
		
		$newColumns = array();
		foreach($classDefinition as $colName => $info)
		{	if( false == in_array($colName, $columns) )
			{	$newColumns = self::assarray_merge
				(	$newColumns, 
					self::sqoolTypesToMYSQL(array($colName => $info)) 
				);
			}
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
		{	if(in_array($definition["baseType"], self::primtypes()))
			{	$thePrimitives = self::primitives();
				$type = $thePrimitives[$definition["baseType"]]." NOT NULL";
			}else if($definition["baseType"] === "object" || $definition["baseType"] === "list")
			{	throw new cept("I didn't think this was still being used");//$type = "INT NOT NULL";
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
	{	return 'sq_'.strtolower($sqoolClassName).'_id';
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
	{	$whitespace = " \t\n\r";
		
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
		
		$result = self::getConstantStringToken($members, $index, array(":"));
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
	{	if($this->ID === false)
		{	if(count($this->databaseRootObject->callQueue) != 0)
			{	$this->databaseRootObject->go();			// TODO: handle this better later (write code so that an extra multi-query isn't needed)
				$this->databaseRootObject->queue();			// turn queuing back on (since it was obviously on before)
			}
			
			if($this->ID === false)	//if the ID is still false
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
	
	// $fieldToUpdateAfterInsert is only used if the $sqoolVal is a sqool object that is not in the database yet (and thus needs to be inserted)
	private function PHPvalToSqoolVal($sqoolType, $sqoolVal, $fieldToUpdateAfterInsert)
	{	if(in_array($sqoolType, self::primtypes()))
		{	$sqlVal = sqool::primValToSQLVal($sqoolVal, $sqoolType);
		}else if(in_array("sqool", self::getFamilyTree($sqoolType)))
		{	if($sqoolVal === null)
			{	$sqlVal = null;
			}else if($sqoolVal->ID === false)
			{	$sqlVal = null;		// to be filled with the ID of the object after it's inserted
				$this->insertOpNext($this->createInsertOp($sqoolVal, $fieldToUpdateAfterInsert));		// queue inserting the object as the next operation in the callQueue
			}else
			{	$sqlVal = $sqoolVal->ID;
				// queue saving the object - if the object has stuff to save
				if(count($sqoolVal->changedVariables) > 0)
				{	$this->insertOpNext($this->createSaveOp($sqoolVal));		// queue inserting the object as the next operation in the callQueue
				}
			}			
		}else
		{	throw new cept("Invalid type: '".$sqoolType."'");
		}
		
		if($sqlVal === null)
		{	return "null";
		}else
		{	return "'".$this->escapeString($sqlVal)."'";
		}
	}
	
	private function containsMember($memberName)
	{	if(get_class($this) === "sqool")
		{	return self::isADefinedClassName($memberName);
		}
		else
		{	return self::in_array_caseInsensitive($memberName, array_keys($this->getClassDefinition($this)));
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
}
?>
