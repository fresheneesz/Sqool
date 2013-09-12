<?php

include_once("cept.php");	// exceptions with stack traces

/*	Defines:		
		class sqool			connection to a database
			new sqool		constructor
			debug			turns on or off debugging messages
			getDB			returns a connection to another database on the same host
			make			defines a sqool class type. This should be used in an class extending sqool. 
							Does not create a table in the DB until an object is inserted (lazy table creation).
								Member types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
			insert			insets an object into the database
			save			saves variables into the database that have been set for an object
			fetch			returns select object members and (if any members are objects or lists) members of those members, etc. See the function for its use.
			sql				executes a single sql command (this is queueable)
			queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'go' method)
								calls that are queued include: insert, fetch, sql, save, fetch
			go				performs all the queries in the queue
			addOperation	adds a pair of functions: an SQL generator and a result handler
							Note that the SQL generator for an operator can add to or modify the $op data passed to it, and use that additional or modified data in the result handler
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
				
				
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
		* have a facility for limiting operations that can be done on an object (allowOnly and disallow should be mutually exclusive). Make sure the mechanism can't affect internal behaviors (for example the insert call using create table or whatever)
		* Make sure you lower the case of all member names and classnames as they come in
		* Make sure the parameters of operations aren't references in most cases (so that changing what a variable points to after a 'queue' call but berfore a 'go' call won't screw things up)
		* add back fetch to the sqool class
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
		* Think about rewriting this in PDO (tho PDO drivers might be a pain in the ass - doctrine uses it tho)
		* Think about adding inheritance
		* Think about indexing - batch updating an index as well
		* add back the non-repeatable calls checker - maybe this isn't necccessary since SQL is going to give an error if the table can't be created
		* Think about adding the ability to specify the length of a string type (which would be good for
		* test sending and receiving text fields longer than 255 characters - might have to caste varchars to Text
		* add the option "noindex" to memberDataControl (so it won't return an object's index)
*/

/*	internal funcs that need to be written (because they are currently being used):


	funtions need finishing
	
*/

// represents a database object (the entire database is also considered an object)
// performs lazy connection (only connects upon actual use)
class sqool			// connection to a database
{	
	// internal static variable
	
	private static $debugFlag = true;
	private static $classes = array();	// members should be of the form phpClassName=>array("name"=>name, "definition"=>definition) 
										//	where definition should be an array with keys representing the names of each member and the values being an array of the form array("baseType"=>baseType[, "listType" => listType])
	private static function getSqoolClassDefinition_fromSqoolClassName($sqoolClassName)
	{	$phpClassName = self::getPHPclass_fromSqoolClassName($sqoolClassName);
		return self::$classes[$phpClassName]["definition"];
	}
	private static function getPHPclass_fromSqoolClassName($sqoolClassName)
	{	foreach(self::$classes as $phpClassName => $sqoolClass)
		{	if($sqoolClass["name"] === $sqoolClassName)
			{	return $phpClassName;
			}
		}
		// else
		throw new cept("The sqool class '".$sqoolClassName."' was not found.");
	}
	private static function getDefinedClassNames()
	{	$alreadyDefinedSqoolClasses = array();
		foreach(self::$classes as $c)
		{	$alreadyDefinedSqoolClasses[] = $c["name"];
		}
		return $alreadyDefinedSqoolClasses;
	}
	private function getSqoolClassName()
	{	return self::$classes[get_class($this)]["name"];
	}
	private function getSqoolClassDefinition_fromPHPclass()
	{	return self::$classes[get_class($this)]["definition"];
	}
	
	private static $operations = array();
	private static function primtypes(){return array('bool', 'string', 'bstring', 'gstring', 'tinyint', 'int', 'float');}
	private static function coretypes(){return array_merge(self::primtypes(),array('list'));}
	
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
	
	const connection_failure 		= 0;
	const database_creation_error 	= 1;
	const class_already_exists	 	= 2;
	const nonexistant_object	 	= 3;
	const append_error		 		= 4;
	const invalid_variable_name 	= 5;
	const general_query_error 		= 6;	// cept::data should hold the error number for the query error
	const table_creation_error 		= 7;
	const column_creation_error 	= 8;
	
	// internal instance variables 
	
	private $databaseRootObject;	// the sqool object that represents the database as a whole (the root sqool object should point to itself here
	private $connectionInfo=array("con"=>false);		// username, password, host, database, con (the connection to the database)
	
	private $callQueue = array();	// can be accessed from operations added to sqool
	private $queueFlag = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
	
	private $ID=false;						// the ID of the object (inside the table $classTable)	
	private $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
	
	// meant for use by a class that extends sqool
	protected function addToCallQueue($additionalOperation)
	{	$this->callQueue[] = $additionalOperation;
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
		
		// create operations
		self::addOperation("insert", 		'$this->insertSQLgenerator', 	'$this->insertResultHandler', 	'$this->insertErrorHandler');
		self::addOperation("save", 			'$this->saveSQLgenerator', 		false, 							'$this->saveErrorHandler');
		self::addOperation("fetch", 		'$this->fetchSQLgenerator', 	'$this->fetchResultHandler');		
		self::addOperation("sql", 			'$this->sqlSQLgenerator', 		'$this->sqlResultHandler');
		self::addOperation("createTable", 	'$this->createTableSQLgenerator');
		self::addOperation("addColumns", 	'$this->addColumnsSQLgenerator');
		
		return $returnedObject;
	}
	
	// returns a connection to another database on the same host
	public function getDB($databaseName)		
	{	$this->validateRoot();
		return connect($this->username, $this->password, $this->host, $databaseName);
	}
	
	/* 	 Defines a class
		 $className is a string - the name of the class 
		 $members is a string in the format "type:name type2:name2 type3:name3" etc
		 	Types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
	*/// Modifier 'list' makes a type into a list of those types (eg "bool list, int list")
	//// Objects (a class typed variable) are treated as reference - they start with the value 0 (a null pointer - 0 is reserved to represent a null pointer)
	protected function make($members)
	{	if(false === in_array(get_class($this), array_keys(self::$classes)))	// class can only be made once
		{	$className = strtolower($className);
			sqool::validateVariableName($className);
			if(in_array($className, self::reservedSqoolClassNames()))
			{	throw new cept("Sqool reserves the class name ".$className." for its own use. Please choose another class name.");
			}
			
			self::addClass(get_class($this), $className, $members);	// add class to sqool's list (throws error if a class is redefined) - also returns the parsed class structure definition
		}
	}
	
	// copies an object into a new row in the database table for its class
	// returns the inserted object (a reference to the object just inserted into the DB)
	// if database accesses are being queued, the returned object won't be updated with its ID and connection until after the queue is executed with 'go'
	// the object used to insert is unmodified
	public function insert($object)
	{	$this->validateRoot();
		
		$className = $object->getSqoolClassName();
		$variables = $object->setVariables;
		
		$object->setVariables = array();	// since the database has been set (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		$newObject = clone $object;		// copy
		self::setUpSqoolObject($newObject, $this);	// give it this sqool object as a connection
		
		$this->databaseRootObject->addToCallQueue(array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject));	// insert the call into the callQueue
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}	
		
		return $newObject;	// return the new object that has (or will have) a new ID and a database connection
	}
	
	// to use the '__set' magic function in child classes, use ___set (with three underscores instead of two)
	function __set($name, $value)
	{	$this->validateNOTRoot();
		
		$backendName = strtolower($name);
		if( false == $this->containsMember($backendName) )
		{	if(method_exists($this, "___set"))
			{	$this->___set($name, $value);
			}else
			{	throw new cept("Object doesn't contain the member '".$name."'.");
			}
			
		}
		$this->setVariables[$backendName] = $value;
	}
	
	function __get($name)
	{	$backendName = strtolower($name);
		if( $this->containsMember($backendName) )	// if sqool class has the member $name
		{	if(isset($this->setVariables[$backendName]))
			{	return $this->setVariables[$backendName];
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
	{	$this->validateNOTRoot();
		
		if(count($this->setVariables) == 0)
		{	throw new cept("Attempted to save an empty dataset to the database");
		}
		$this->requireID("save");
		
		$this->databaseRootObject->saveSqoolObject($this);
	}	
	
	// fetches objects from the database
	// returns a sqool object
	// If the sqool object has a connection but does not have a class, it represents the entire database where each table is a list member
	// throws an error if an invalid member is accessed (if a non-existant member or object is attempted to be accessed)
	/*	
		object->fetch(membersSelection);
		
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
				
			// if "fieldName" is a list, the following keys apply:
			// for fields that are objects, the 'value' is a sqool object instance
			// an empty members array (e.g. "array(membera, memberb, etc)") means return all non-recursive-fields (fields that point back to an already returned piece of data will point to that sqool object, instead of returning a new sqool object)
			// the "sort", "items", "cond", and "ranges" keys are optional (tho one of the following MUST be given: "items", "cond", or "ranges")
			"sort" => array("field", direction, "field2", direction2, etc, etc),	// the way to sort the elements of a member list - direction should be "-", "+", or a number. "+" means increasing order [smallest first], "-" means decreasing order [largest first], and a number means sort by values closest to the number
			"cond" => expression, 												// the elements of a member list selected by some kind of conditions on the elements of the list
			"ranges" => array(start, end, start2, end2, etc, etc)					// objects to return from the selected list by their position in the list (after being sorted).
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
	function fetch($fetchOptions)
	{	if($this->databaseRootObject === $this)	// if this is the root
		{	$this->fetchBack($fetchOptions, $this, "tables");
		}else
		{	$this->requireID("fetch from");
			
			// translate into the uniform fetch form
			$this->databaseRootObject->fetchBack(array
			(	$this->getSqoolClassName() => array
				(	"cond"=>array(self::getClassPrimaryKey($this->getSqoolClassName())."=",$this->ID), 
					"ranges"=>array(0,0),	// only return the first item found (since there can only be one)
					"members"=>$fetchOptions
				)
			),$this, "members", $this->getSqoolClassName());
		}	
	}
	
	// queues the fetch
	private function fetchBack($fetchOptions, $object, $mode, $className=false)
	{	$this->validateRoot();

		$this->addToCallQueue(array("opName" => "fetch", "fetchOptions" => $fetchOptions, "object"=>$object, "mode"=>$mode, "className"=>$className));	// insert the call into the callQueue
		if($this->queueFlag == false)
		{	$this->go();
		}
	}	
	
	// sets up queueing all database accesses (use 'go' to process the queue - which does all the calls in order)
	public function queue()
	{	$this->validateRoot();
		$this->databaseRootObject->queueFlag = true;
	}
	
	// processes the queued calls, performing their functions in order
	public function go()
	{	$this->validateRoot();
	
		$this->databaseRootObject->queueFlag = false;	// reset queueFlag back to false (off)
		//$nonRepeatableCalls = array();	// record which calls should generate errors if they are tried multiple times
		
		// build the sql multiquery
		$multiqueries = array();
		$numberOfCommands_inEachMultiquery = array();
		foreach($this->callQueue as $n => &$op)
		{	if(false == in_array($op["opName"], array_keys(self::$operations)))
			{	throw new cept("Invalid call: '".$op["opName"]."'");
			}
			
			$generatorResult = $this->call_function_ref(self::$operations[$op["opName"]]["generator"], array(&$op));
			$numberOfCommands_inEachMultiquery[$n] = $generatorResult["numberOfCommands"];
			$multiqueries[] = $generatorResult["queries"];
		}
		
		// run the multiquery
		$results = $this->rawSQLquery(implode("", $multiqueries));
		
		// handle the results
		$resultsIndex = 0;	// holds the current results index
		foreach($this->callQueue as $n => &$op)
		{	$errorNumber = $results["errorNumber"];
			
			if($errorNumber != 0)
			{	$cutInLine = $this->call_function_ref(self::$operations[$op["opName"]]["errorHandler"], array($op, $errorNumber));
				if($cutInLine === false)
				{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$multiqueries[$n]."' <br>\n".$results["errorMsg"], self::general_query_error, $errorNumber);
				}
			
				$this->callQueue = array_merge
				(	$cutInLine,
					array_slice($this->callQueue, $n+1)
				);
				
				// execute the newly queued calls
				$this->go();
				return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
			}
			//else...
			
			$numApplicableResults = $numberOfCommands_inEachMultiquery[$n];
			
			// run the resultHandler with the operation call and relevant query results as parameters
			$this->call_function_ref(self::$operations[$op["opName"]]["resultHandler"], array($op, array_slice($results["resultSet"], $resultsIndex, $numApplicableResults)));
			$resultsIndex += $numApplicableResults;
		}
		
		if(count($results["resultSet"]) < $resultsIndex)
		{	throw new cept("There are too many results for the query/queries being processed. Make sure your 'sql' calls only contain one query each and do NOT end in a semi-colon.");
		}else if(count($results["resultSet"]) > $resultsIndex)
		{	throw new cept("There are too few results for the queries being processed.");	// this error should never be able to happen
		}
		
		$this->callQueue = array();	// reset callQueue
	}
	
	// performs an sql query
	// queries should NOT end in a semi-colon and there should only be ONE query
	// returns a sqool object that has the memeber 'result' that holds the result of the query
	public function sql($query)
	{	$this->validateRoot();	// this may be kinda arbitrary (theres not *real* reason to disallow calling this on non-root objects, other than consistency)
		
		$returnedObject = new sqool();
		$this->databaseRootObject->addToCallQueue(array("opName"=>"sql", "query"=>$query, "resultVariableReference">$returnedObject));	// insert the call into the callQueue
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}
		
		return $returnedObject; 
	}
	
	// adds an operation to sqool's backend
	// SQLgenerator generates SQL that is executed in a multiquery with other SQL statements
	// resultHandler handles the result returned by the database server
	//		$SQLgenerator must return an array of the form array("numberOfCommands"=>$numberOf_SQL_Commands, "queries"=>"multiqueryString")
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
	
	/****************************** FUNCTIONS FOR OPERATION HANDLING ******************************/
	
					/*************** insert ***************/
					//$op holds: array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject);	
	
	// inserts a set of rows into a table
	// $rows must be an associative array where the keys are the column names, and the values are the values being set
	// the resultset of the sql includes the last_insert_ID
	private function insertSQLgenerator($op)
	{	$memberDefinition = self::getSqoolClassDefinition_fromSqoolClassName($op["class"]);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		$columns = array();
		$values = array();
		foreach($op["vars"] as $col => $val)
		{	$columns[] = '`'.$col.'`';
			$values[] = "'".$this->escapeString(sqool::primValToSQLVal($val, $memberDefinition[$col]["baseType"]))."'";
		}
		
		return array
		(	"numberOfCommands" => 2,	// insert uses two sql statements
			"queries" => 'INSERT INTO `'.$op["class"].'` ('.implode(",", $columns).') '.'VALUES ('.implode(",", $values).');SELECT LAST_INSERT_ID();'
		);
	}
	
	private function insertResultHandler($op, $results)
	{	$op["returnedObjectReference"]->ID = $results[1][0][0];	// set the ID to the primary key of the object inserted
	}
	
	private function insertErrorHandler($op, $errorNumber)
	{	if($errorNumber == 1146 || $errorNumber == 656434540)	// the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
		{	// queue creating a table, a retry of the insert, and the following queries that weren't executed
			$columns = self::sqoolTypesToMYSQL($op["returnedObjectReference"]->getSqoolClassDefinition_fromPHPclass());
			$callToQueue = array("opName"=>"createTable", "class"=>$op["class"], "sqlColumnDefinitions"=>$columns);
			
			//if(inOperationsList($callToQueue, $nonRepeatableCalls))
			//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
			//}
			
			return array($callToQueue, $op);	// insert the createTable op at the front of the queue, along with the errored op (try it again)
		}
		else if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	// column doesn't exist
			return array(getAddColumnsOp($op["class"], $op["returnedObjectReference"]->getSqoolClassDefinition_fromPHPclass()), $op);
		}else
		{	return false;
		}
	}
	
					/*************** save ***************/
					// $op holds: 	array
					//				(	"opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->setVariables, 
					//					"classDefinition"=>$sqoolObject->getClassDefinition()
					//				);	
					
	// renders the SQL for saving $setVariables onto a database object referenced by $sqoolObject
	private function saveSQLgenerator($op)
	{	$memberDefinition = self::getSqoolClassDefinition_fromSqoolClassName($op["class"]);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
		$queryColUpdates = "";
		$onceAlready = false;
		foreach($op["vars"] as $col => $val)
		{	if($onceAlready)
			{	$queryColUpdates .= ',';
			}else
			{	$onceAlready = true;
			}
			
			$queryColUpdates .= " `".$col."`='".$this->escapeString(sqool::primValToSQLVal($val, $memberDefinition[$col]["baseType"]))."'";
		}
		
		return array
		(	"numberOfCommands" => 1,
			"queries" => 'UPDATE '.$op["class"].' SET'.$queryColUpdates.";"
		);
	}
	
	private function saveErrorHandler($op, $errorNumber)
	{	if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	// column doesn't exist
			return array($this->getAddColumnsOp($op["class"], $op["classDefinition"]), $op);
		}else
		{	return false;
		}
	}		
	
					/*************** fetch ***************/
					// $op holds: 	array("opName" => "fetch", "fetchOptions" => $fetchOptions, "object"=>$object, "mode"=>$mode, "className"=>$className)
	
	private function fetchResultHandler($op, $results)
	{	//print_r($op);
		//print_r($results);
		//exit;
		
		if($op["mode"] === "tables")	// root
		{	$members = array_keys($op["memberHierarchy"]);
			foreach($results as $n => $result)
			{	$info = $op["memberHierarchy"][$members[$n]];
				$op["object"]->setVariables[$members[$n]] = array();
				$phpClassName = self::getPHPclass_fromSqoolClassName($info["baseType"]);
				
				foreach($result as $n2 => $subresult)
				{	$newObj = new $phpClassName();
					$submembers = array_keys($info["submembers"]);
					foreach($subresult as $n3 => $sub2result)
					{	if($n3 == 0)
						{	$newObj->ID = $this->SQLvalToPrimVal($sub2result, "int");
						}else
						{	$subinfo = $info["submembers"][$submembers[$n3-1]];
							$newObj->setVariables[$submembers[$n3-1]] = $this->SQLvalToPrimVal($sub2result, $subinfo["baseType"]);
						}
					}
					$op["object"]->setVariables[$members[$n]][] = $newObj;
				}	
			}
		}
		
		/*if($op["mode"] === "tables")	// root
		{	$index = 0;
			foreach($op["memberHierarchy"] as $table => $children)
			{	$op["object"]->setVariables[$table] = $this->parseMemberFetchResults($results[$index], $table, $children);
			}
		}
		else if($op["mode"] === "members")
		{	$index = 0;
			foreach($op["memberHierarchy"][$op["object"]->getSqoolClassName()] as $member => $children)
			{	$op["object"]->setVariables[$member] = $this->parseMemberFetchResults($results[0][$index], $member, $children);
				//finish this later ///
			}
		}else
		{	throw new cept("Invalid mode");
		}
		*/
	}
	
	private function parseMemberFetchResults($memberResults, $memberType, $children)
	{	if(in_array($memberType, self::primtypes()))
		{	return SQLvalToPrimVal($memberResults, $memberType["baseType"]);
		}else
		{	$memberDefinition = self::getSqoolClassDefinition_fromSqoolClassName($memberType["baseType"]);		// members of the form array("baseType"=>baseType[, "listType" => listType])
		
			$index = 0;
			$objectMembers = array();
			foreach($children as $member => $grandChildren)
			{	$objectMember = new sqool();
				$objectMember->setVariables[$member] = parseMemberFetchResults($results[0][$index], $member, $children);
				//finish this later ///
			}
		}
	}
	
	private function fetchSQLgenerator($op)
	{	$result = $this->parseMemberSelection($op["fetchOptions"], "tables");
		$op["memberHierarchy"] = $result["memberHierarchy"];	// load some auxilary info into the operation array
		
		return array
		(	"numberOfCommands" => $result['numberOfCommands'],
			"queries" => $result['queries']
		); 
	}
	
	// returns:  (this needs to have more thought put into it)
	//	* SQL for a given set of fetch options
	//	* an array of members being selected by the top level in the fetch options
	//		* the memebersHierarchy returned should be an array where each element is of the form "member" => array("baseType"=>baseType[,"listtype"=>listType],"mode"=>tablesOrMembers[, "submembers"=>submembers])
	// $type can be "tables" or "members"
	//	* "table" means that the top-level fetch options refer to tables in the database
	//	* "member" means that the top-level fetch options refer to fields in a certain table
	private function parseMemberSelection($fetchOptions, $mode, $className=false)
	{	// get the SQL for the top level object in question
		$querySets = "";
		$numberOfCommands = 0;
		$memberHierarchy = array();
		foreach($fetchOptions as $k => $v)//$t => $options)
		{	if(is_int($k))
			{	$result = $this->handleMembersOptions($mode, $v, $v);
				$memberHierarchy[$v] = $result["memberHierarchy"];
				$numberOfCommands += $result["numberOfCommands"];
				$querySets .= $result["memberQueryPart"].";";
			}
			else
			{	if($mode == "tables")
				{	if(isset($v["members"]))
					{	/*$result = handleMembersOptions($mode, $k, $k, $v["members"]);
					
						$memberHierarchy[$k] = $result["memberHierarchy"];
						$numberOfCommands += $result["numberOfCommands"];
						$memberQueryPart = $result["memberQueryPart"];
						*/
					}else
					{	$result = $this->handleMembersOptions($mode, $k, $k);
					
						$memberHierarchy[$k] = $result["memberHierarchy"];
						$numberOfCommands += $result["numberOfCommands"];
						$memberQueryPart = $result["memberQueryPart"];
					}
				}else if($mode == "members")
				{	
				}else
				{	throw new cept("Invalid mode");
				}
				
				
				if(isset($v["cond"]))
				{	$whereClause = $this->parseExpression($v["cond"]);
				}else
				{	$whereClause = "";
				}
				
				// ranges is limited to a start position and an end position - but multiple pieces of a sorted list should be supported later
				if(isset($v["ranges"]))
				{	//$countRanges = count($options["ranges"]);
					//for($n=0;$n<count($options["ranges"]
					if(count($v["ranges"])>2)
					{	throw new cept("ranges does not support more than one range yet");
					}	
					
					$limitClause = "LIMIT ".$v["ranges"][0].",".$v["ranges"][1];
				}else
				{	$limitClause = "";
				}
				
				// add sorting here
				if(isset($v["sort"]))
				{	throw new cept("Sort not written yet");
				}else
				{	$sortClause = "";
				}
				
				$querySets .= $memberQueryPart.$whereClause.$sortClause.$limitClause.";";
				$numberOfCommands += 1;
			}
		}
		
		// get the SQL for the objects in the next level
		
		
		return array
		(	"numberOfCommands" => $numberOfCommands,
			"queries" => $querySets,
			"memberHierarchy" => $memberHierarchy
		);
	}	
	
	// handles the member options 
	// returns the beggining of the applicable query and potentially any subqueries (from nested member options)
	private function handleMembersOptions($mode, $classType, $member, $memberOptions=false)
	{	$subqueries="";	// default to empty
		
		if($memberOptions === false)
		{	$classDefinition = self::getSqoolClassDefinition_fromSqoolClassName($classType);		// members of the form array("baseType"=>baseType[, "listType" => listType])
			
			$memberHierarchy = array("baseType"=>$classType, "listType"=>"list", "mode"=>$mode, "submembers"=>$classDefinition);
			$memberQueryPart = "SELECT * FROM `".$classType."`";
			$numberOfCommands = 1;
		}else
		{	if($mode == "tables")
			{	
			}else if($mode == "members")
			{	/*$result = $this->parseMemberSelection($memberOptions, "members", $classType);
					
				$memberHierarchy = array("basetype"=>$classType, "listType"=>"list", "mode"=>"members", "submembers"=>$result["memberHierarchy"]);
				$memberQueryPart = "SELECT `".implode("`,`",array_keys($result["memberHierarchy"]))."` FROM `".$classType."`";
				$numberOfCommands = 1+$result["numberOfCommands"];
				$subqueries = $result["queries"];
				*/
			}else
			{	throw new cept("Invalid mode");
			}
		}
			
		return array
		(	"numberOfCommands" => $numberOfCommands,
			"memberQueryPart" => $memberQueryPart,
			"memberHierarchy" => $memberHierarchy,
			"subqueries" => $subqueries
		);
	}
	
	private function parseExpression($expression)
	{	$whereClause = "";
		for($n=0; $n<count($options["cond"]); $n+=2)
		{	if($n>0)
			{	if($options["cond"][$n] == "and")
				{	$whereClause .= " AND ";
				}else if($options["cond"][$n] == "or")
				{	$whereClause .= " OR ";
				}else
				{	throw new cept("Parse error in 'cond' clause, expected 'and' or 'or' but got '".$options["cond"][$n]."' instead");
				}
				$n += 1;
			}
			
			$leftPart = $this->parseExpressionLeft($options["cond"][$n]);			
			$rightPart = $this->parseExpressionRight($options["cond"][$n+1]);
			$whereClause .= "(".$leftPart.$rightPart.")";
		}
		return $whereClause;
	}
	
	private function parseExpressionLeft($condLeft)
	{	if(is_array($condLeft))
		{	return $this->parseExpression($condLeft);
		}else
		{	$position = 0;
			$position += self::getCharsExcept($condLeft, $result, "_", "09azAZ", $operator1);	// get first operator
			$operator1 = trim($operator1);	// trim off whitspace
			
			$result = self::getVariableKeyWord($condLeft, $position, $member);	// $member is written into
			if($result < 0)
			{	throw new cept("Couldn't parse 'cond' parameter: '".$member."'");
			}else
			{	$position += $result;
			}
			
			$position += self::getCharsExcept($condLeft, $result, "_", "09azAZ", $operator2);	// get second operator
			$operator2 = trim($operator2);	// trim off whitspace
			
			return $operator1."`".$member."`".$operator2;
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
	
					/*************** createTable ***************/
					// 			internal function 
					// $op holds: array("opName"=>"addColumns", "class"=>$className, "sqlColumnDefinitions"=>$newColumns);	
					// 		$newColumns is an array with members of the form $memberName => $type
					
	// returns the SQL to create a mysql table named $tableName 
	// $op["sqlColumnDefinitions"] should be an associtive array where the key is the name of the column, and the value is the type
	private function createTableSQLgenerator($op)
	{	//if(inOperationsList($op, $nonRepeatableCalls))
		//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
		//}
		//$nonRepeatableCalls[] = $op;
		
		var_dump($op["class"]);
		
		$query = 'CREATE TABLE '.$op["class"].' (';
		
		$query .= self::getClassPrimaryKey($op["class"]).' INT NOT NULL PRIMARY KEY AUTO_INCREMENT';	// add an object id field (sq for sqool defined field - as opposed to user defined)
		
		foreach($op["sqlColumnDefinitions"] as $col => $type)
		{	$query .= ', '.$col.' '.$type.' NOT NULL';	// name -space- type
		}
		$query.=");";
	
		return array
		(	"numberOfCommands" => 1,
			"queries" => $query
		);
	}
	
					/*************** addColumns ***************/
					// 			internal function 
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
			
			$alterQuery .= $memberName.' '.$SQLtype.' NOT NULL';
		}
		
		array
		(	"numberOfCommands" => 1,
			"queries" => 'ALTER TABLE '.$op["class"].' ADD ('.$alterQuery.');'
		);
	}
	
	
	
	/********************** NON-STANDARD METHODS USED INTERNALY, but also ENCOURAGED FOR EXTERNAL USE *************/
	
	// performs an sql query, and echos error information if debugFlag is on
	public function escapeString($string)
	{	$this->connectIfNot();
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
		
		if(self::charIsOneOf($theArray[0], '_', '09'))
		{	throw new cept("Attempted to define a member named '".$theArray[0]."'. Member variables cannot start with a numerical digit", sqool::invalid_variable_name);
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
	
	// returns the parent class name (in an inheritance hierarchy) the method was originally defined in
	// $objectOrClassName is the object or class to start looking for the method
	// returns false if the method doesn't exist at all
	public function methodIsDefinedIn($objectOrClassName, $methodName)
	{	// get the className for $objectOrClassName
		if(is_object($objectOrClassName))
		{	$className = get_class($objectOrClassName);
		}else
		{	$className = $objectOrClassName;
		}
		
		// check if the class exists
		$methodList = get_class_methods($className);
		if(false == in_array($methodName, $methodList))
		{	return false;
		}else
		{	$lastClass = $className;
		}
		
		while(true)
		{	// get the next parent class up the inheritance hierarchy
			$nextClass = get_parent_class($lastClass);
			if($nextClass === false)
			{	break;	// no more classes (it must be defined in the root-parent
			}
			
			// check if the
			$methodList = get_class_methods($nextClass);
			if(false == in_array($methodName, $methodList))
			{	break;
			}else
			{	$lastClass = $nextClass;
			}
			
			
		}
		
		return $lastClass;
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
	
	/********************** BELOW THIS ARE MzETH/oDS FOR INTERNAL USE ONLY *************************/
	
	// does nothing - used for default function callbacks
	private static function noOp()
	{	return true;
	}
	
	// executes a multiquery
	private function rawSQLquery($query)
	{	$this->connectIfNot();
		
		if(self::$debugFlag)
		{	echo "\n<br><br>\nExecuting: ".$query."\n<br><br>\n";
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
	private function connectIfNot()
	{	if($this->connectionInfo["con"] === false)
		{	//connect
			
			if(self::$debugFlag)
			{	echo "\n<br><br>\nAttempting to connect to the database ".$this->connectionInfo["database"]." on ".$this->connectionInfo["host"]." with the username ".$this->connectionInfo["username"].".\n<br><br>\n";
			}
			
			@$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"], $this->connectionInfo["database"]);
			
			if($this->connectionInfo["con"]->connect_errno)
			{	if($this->connectionInfo["con"]->connect_errno == 1049)	// database doesn't exist
				{	// create database
					$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"]);
					$this->rawSQLquery('CREATE DATABASE '.$this->connectionInfo["database"].';');
					$this->connectionInfo["con"]->select_db($this->connectionInfo["database"]);
					
					return true;
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
	{	$this->databaseRootObject->addToCallQueue(array					// insert the call into the callQueue
		(	"opName"=>"save", "class"=>$sqoolObject->getSqoolClassName(), "vars"=>$sqoolObject->setVariables, "classDefinition"=>$sqoolObject->getSqoolClassDefinition_fromPHPclass()
		));	
		
		$sqoolObject->setVariables = array();	// since the database has been changed (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		
		if($this->databaseRootObject->queueFlag == false)
		{	$this->go();
		}
	}
	
	// returns an addColumns operation that can be put into sqool's call queue
	private function getAddColumnsOp($className, $classDefinition /*, $nonRepeatableCalls*/)
	{	// add columns defined in this class that aren't in the table schema yet
		$showColumnsResult = $this->rawSQLquery("SHOW COLUMNS FROM ".$className.";");	// this can probably be done in-line with the other multiquery items - but we'll have to do the following loop in a mySQL procedure (later)
		
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
		{	switch($definition["baseType"])
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
	
	// checks to see if $op1 is in $oplist
	// will return true if they have the same value
	private function inOperationsList($op1, $oplist)
	{	return in_array($op1, $oplist);
	}
	
	private static function getClassPrimaryKey($sqoolClassName)
	{	return 'sq_'.$sqoolClassName.'_id';
	}
	
	// parses type declarations for a sqool class (in the form "type:name  type:name  etc")
	// returns an array with keys representing the names of each member and the values being an array of the form array("baseType"=>baseType[, "listType" => listType])
	// see parseMemberDefinition for examples of the returned data
	private static function parseClassDefinition($members, $className)
	{	$result = array();
		$index = 0;
		while(true)
		{	$nextMember = self::parseMemberDefinition($members, $index);	// set the result array
			
			if($nextMember === false)
			{	break;		// done parsing (no more members)
			}
			$keys = array_keys($nextMember);
			if(in_array($keys[0], array_keys($result)))
			{	throw new cept("Error: can't redeclare member '".$keys[0]."' in class definition (note: member names are NOT case-sensitive)");
			}
			if($keys[0] == self::getClassPrimaryKey($className))
			{	throw new cept("Error: sqool reserves the member name '".$keys[0]."' (note: member names are NOT case-sensitive)");
			}
			if(in_array($keys[0], self::reservedMemberNames()))
			{	throw new cept("Error: sqool already has functions named '".$keys[0]."' (note: member names are NOT case-sensitive)");
			}
			$result = self::assarray_merge($result, $nextMember);
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
		
		if( false == in_array($baseType, array_merge(sqool::primtypes(), self::getDefinedClassNames())) )	// is a defined type
		{	throw new cept("The sqool class '".$baseType."' has not been defined and it is not the name of a primitive type either");
		}
		
		if($listIsFound)
		{	return array(strtolower($name) => array("baseType"=>strtolower($baseType), "listType"=>'list') );
		}else
		{	return array(strtolower($name) => array("baseType"=>strtolower($baseType)) );
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
	// make sure the calling function is the root
	private function validateRoot()
	{	if($this->databaseRootObject !== $this)
		{	throw new cept("Attempting to insert an object into something other than a database. I think you can figure out why thats wrong.");
		}
	}
	// make sure the calling function is NOT the root
	private function validateNOTRoot()
	{	if($this->databaseRootObject === $this)
		{	throw new cept("Attempting to insert an object into something other than a database. I think you can figure out why thats wrong.");
		}
	}
	
	private static function addClass($phpClassName, $sqoolClassName, $members)	
	{	if(in_array($sqoolClassName, self::getDefinedClassNames()))
		{	throw new cept("Attempting to redefine class '".$sqoolClassName."'. This isn't allowed.");
		}
		
		self::$classes[$phpClassName] = array("name"=>$sqoolClassName, "definition"=>self::parseClassDefinition($members, $sqoolClassName));
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
		}else if($type == "tinyint" || $type == "int" )
		{	return intval($val);
		}else if($type == "float")
		{	return floatval($val);
		}else
		{	return $val;
		}
	}
	
	private function containsMember($memberName)
	{	if(get_class($this) === "sqool")
		{	return in_array($memberName, self::getDefinedClassNames());
		}
		else
		{	return in_array($memberName, array_keys($this->getSqoolClassDefinition_fromPHPclass()));
		}
	}
}
?>
