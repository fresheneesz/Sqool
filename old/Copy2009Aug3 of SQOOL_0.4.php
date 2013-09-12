<?php

include_once("cept.php");	// exceptions with stack traces

/*	Defines:		
		class sqool			connection to a database
			new sqool		constructor
			debug			turns on or off debugging messages
			getDB			returns a connection to another database on the same host
			insert			insets an object into the database
			fetch			fetches information for any number of objects (is a superset of the functionality of sqoolobj::fetch)
			sql				executes a single sql command (this is queueable)
			queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'go' method)
								calls that are queued include: sqool::insert, sqool::fetch, sqool::sql, sqoolobj::save, sqoolobj::fetch
			go				performs all the queries in the queue
			addOperation	adds a pair of functions: an SQL generator and a result handler
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
		
		class sqoolobj	a sqool object
			make		defines a class type. Does not create a table in the DB until an object is inserted.
							Member types: bool, string, bstring, gstring, tinyint, int, float, :class:, :type: list
			save		saves variables into the database that have been set for an object
			fetch		returns select object members and (if any members are objects or lists) members of those members, etc. See the function for its use.
				
				
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
			
	Internal operations - operations used to execute the queueable sqool and sqoolobj calls
		* "insert"
		* "save"
		* "fetch"
		* "sql"
 */

/*	To do:
		* have a facility for limiting operations that can be done on an object (allowOnly and disallow should be mutually exclusive). Make sure the mechanism can't affect internal behaviors (for example the insert call using create table or whatever)
		* Make sure you lower the case of all member names and classnames as they come in
		* Make sure the parameters of operations aren't references in most cases (so that changing what a variable points to after a 'queue' call but berfore a 'go' call won't screw things up)
		* add back the non-repeatable calls checker
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
*/

/*	internal funcs that need to be written (because they are currently being used):


	funtions need finishing
	
*/

// performs lazy connection (only connects upon actual use)
class sqool			// connection to a database
{	private static $debugFlag = true;
	private static $classes = array();
			static function addClass($className)			// meant for internal use by the sqoolobj class only
	{	if(in_array($className, sqool::$classes))
		{	throw new cept("Attempting to redefine class '".$className."'. This isn't allowed.");
		}
		sqool::$classes[] = $className;
	}
	
	private static $operations = array();
	
			static function primtypes()			// meant for internal use by the sqoolobj class only
	{	return array
		(	'bool', 'string', 'bstring', 'gstring', 'tinyint', 'int', 'float', 'class'
		);
	}
			static function reservedTableNames()	// meant for internal use by the sqoolobj class only
	{	$tableNames = array("sq_lists");
		foreach(self::primtypes() as $pt)
		{	$tableNames[] = "sq_".$pt;
		} 
		return $tableNames;
	}
	
	private $con;		// the current selected connection (for accessing multiple databases at once)
	private $username, $password, $host, $database;
	
	const connection_failure 		= 0;
	const database_creation_error 	= 1;
	const class_already_exists	 	= 2;
	const nonexistant_object	 	= 3;
	const append_error		 		= 4;
	const invalid_variable_name 	= 5;
	const general_query_error 		= 6;	// cept::data should hold the error number for the query error
	const table_creation_error 		= 7;
	const column_creation_error 	= 8;
	
	protected $callQueue = array();	// can be accessed from operations added to sqool
	private $queueFlag = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
	function getQueueFlag()		// meant for internal use by the sqoolobj class only
	{	return $this->queueFlag;
	}
	function countCallQueue()
	{	return count($this->callQueue);
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
		
		// create operations
		self::addOperation("insert", '$this->insertSQLgenerator', '$this->insertResultHandler', '$this->insertErrorHandler');
		self::addOperation("save", '$this->saveSQLgenerator', false, '$this->saveErrorHandler');
		self::addOperation("sql", '$this->sqlSQLgenerator', '$this->sqlResultHandler');
		self::addOperation("createTable", '$this->createTableSQLgenerator');
		self::addOperation("addColumns", '$this->addColumnsSQLgenerator');
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
	// if database accesses are being queued, the returned object won't be updated with its ID and connection until after the queue is executed with 'go'
	// the object used to insert is unmodified
	public function insert($object)
	{	$className = $object->getClassName();
		$variables = $object->getSetVariables();
		
		$object->clearSetVariables();	// since the database has been set (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		$newObject = clone $object;		// copy
		
		$this->callQueue[] = array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject);	// insert the call into the callQueue
		
		if($this->getQueueFlag() == false)
		{	$this->go();
		}
		
		$newObject->setSqoolCon($this);		// give it this sqool object as a connection
		
		return $newObject;	// return the new object that has (or will have) a new ID and a database connection
	}
	
	// sets up queueing all database accesses (use 'go' to process the queue - which does all the calls in order)
	public function queue()
	{	$this->queueFlag = true;
	}
	
	// processes the queued calls, performing their functions in order
	public function go()
	{	$this->queueFlag = false;	// reset queueFlag back to false (off)
		//$nonRepeatableCalls = array();	// record which calls should generate errors if they are tried multiple times
		
		// build the sql multiquery
		$multiqueries = array();
		$numberOfCommands_inEachMultiquery = array();
		foreach($this->callQueue as $n=>$op)
		{	if(false == in_array($op["opName"], array_keys(self::$operations)))
			{	throw new cept("Invalid call: '".$op["opName"]."'");
			}
			
			
			$generatorResult = $this->call_function_ref(self::$operations[$op["opName"]]["generator"], array($op));
			$numberOfCommands_inEachMultiquery[$n] = $generatorResult["numberOfCommands"];
			$multiqueries[] = $generatorResult["queries"];
			
			/*if($op["opName"] == "insert")		// $op holds: array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject);	
			{	$queries[] = $this->renderInsertIntoTableSQL($op["class"], $op["vars"]);
			}
			else if($op["opName"] == "save")	// $op holds: array("opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->getSetVariables(), "classDefinition"=>$sqoolObject->getClassDefinition());	
			{	$queries[] = $this->renderSaveSQL($op["class"], $op["vars"]);
			}
			else if($op["opName"] == "fetch")
			{
			}
			else if($op["opName"] == "sql")		// $op holds: array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>&$resultVariable);
			{	$queries[] = $op["query"].";";
			}
			else if($op["opName"] == "createTable")	// internal function // $op holds: array("opName"=>"createTable", "class"=>$op["class"], "sqlColumnDefinitions"=>$columns);
			{	if(inOperationsList($op, $nonRepeatableCalls))
				{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
				}
				$nonRepeatableCalls[] = $op;
				
				$queries[] = $this->createTableSQL($op["class"], $op["sqlColumnDefinitions"]);	
			}
			else if($op["opName"] == "addColumns")	// internal function // $op holds: array("opName"=>"addColumns", "class"=>$className, "sqlColumnDefinitions"=>$newColumns);	// $newColumns is an array with members of the form $memberName => $type
			{	if(inOperationsList($op, $nonRepeatableCalls))
				{	throw new cept("In the table '".$op["class"]."', could not create columns '".implode("', '", array_keys($op["sqlColumnDefinitions"]))."'", self::$column_creation_error);
				}
				$nonRepeatableCalls[] = $op;
				
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
				
				$queries[] = 'ALTER TABLE '.$op["class"].' ADD ('.$alterQuery.');';
			}		
			*/	
		}
		
		// run the multiquery
		$results = $this->rawSQLquery(implode("", $multiqueries));
		
		// handle the results
		$resultsIndex = 0;	// holds the current results index
		foreach($this->callQueue as $n => $op)
		{	$errorNumber = $results["errorNumber"];
			
			if($errorNumber != 0)
			{	$cutInLine = $this->call_function_ref(self::$operations[$op["opName"]]["errorHandler"], array($op, $errorNumber));
				if($cutInLine === false)
				{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$queries[$n]."' <br>\n".$results["errorMsg"]."<br>\n", self::general_query_error, $errorNumber);
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
			
			/*
			if($op["opName"] == "insert")	//$op holds: array("opName" => "insert", "class" => $className, "vars" => $variables, "returnedObjectReference" => $newObject);	
			{	if($errorNumber == 1146 || $errorNumber == 656434540)	// the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
				{	// queue creating a table, a retry of the insert, and the following queries that weren't executed
					$columns = self::sqoolTypesToMYSQL($op["returnedObjectReference"]->getClassDefinition());
					$callToQueue = array("opName"=>"createTable", "class"=>$op["class"], "sqlColumnDefinitions"=>$columns);
					
					if(inOperationsList($callToQueue, $nonRepeatableCalls))
					{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
					}
					
					$this->callQueue = array_merge
					(	array($callToQueue),
						array_slice($this->callQueue, $n)
					);
					
					// execute the newly queued calls
					$this->go();
					return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
				}
				else if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
				{	// column doesn't exist
					$this->queueAddColumnsOp($op["class"],$op["returnedObjectReference"]->getClassDefinition(), array_slice($this->callQueue, $n), $nonRepeatableCalls);
					
					// execute the newly queued calls
					$this->go();
					return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
				}
				
				$op["returnedObjectReference"]->setID($results[0][$resultsIndex+1][0][0]);	// set the ID to the primary key of the object inserted
				
				$resultsIndex += 2;	// insert uses two sql statements
			}
			else if($op["opName"] == "save")	// $op holds: array("opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->getSetVariables(), "classDefinition"=>$sqoolObject->getClassDefinition());	
			{	if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
				{	// column doesn't exist
					$this->queueAddColumnsOp($op["class"],$op["classDefinition"], array_slice($this->callQueue, $n), $nonRepeatableCalls);
					
					// execute the newly queued calls
					$this->go();
					return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
				}
				
				$resultsIndex += 1;
			}
			else if($op["opName"] == "fetch")
			{
			}
			else if($op["opName"] == "sql")	//	$op holds: array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>&$resultVariable);
			{	$op["resultVariableReference"] = $results[0][$resultsIndex];	// set the variable returned by the method 'sql'
				$resultsIndex += 1;
			}
			else if($op["opName"] == "createTable")	// internal function
			{	// don't have to do squat for this one
				$resultsIndex += 1;
			}
			else if($op["opName"] == "addColumns")	// internal function
			{	// don't have to do squat for this one
				$resultsIndex += 1;
			}
			
			if($errorNumber != 0)
			{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$queries[$n]."' <br>\n".$results[2]."<br>\n", self::general_query_error, $errorNumber);
			}
			*/
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
	public function sql(&$resultReference, $query)
	{	$this->callQueue[] = array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>&$resultReference);	// insert the call into the callQueue
		
		if($this->getQueueFlag() == false)
		{	$this->go();
		}
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
	{	$columns = array();
		$values = array();
		foreach($op["vars"] as $col => $val)
		{	$columns[] = '`'.$col.'`';
			$values[] = "'".$this->escapeString($val)."'";
		}
		
		return array
		(	"numberOfCommands" => 2,	// insert uses two sql statements
			"queries" => 'INSERT INTO `'.$op["class"].'` ('.implode(",", $columns).') '.'VALUES ('.implode(",", $values).');SELECT LAST_INSERT_ID();'
		);
	}
	
	private function insertResultHandler($op, $results)
	{	$op["returnedObjectReference"]->setID($results[1][0][0]);	// set the ID to the primary key of the object inserted
	}
	
	private function insertErrorHandler($op, $errorNumber)
	{	if($errorNumber == 1146 || $errorNumber == 656434540)	// the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
		{	// queue creating a table, a retry of the insert, and the following queries that weren't executed
			$columns = self::sqoolTypesToMYSQL($op["returnedObjectReference"]->getClassDefinition());
			$callToQueue = array("opName"=>"createTable", "class"=>$op["class"], "sqlColumnDefinitions"=>$columns);
			
			//if(inOperationsList($callToQueue, $nonRepeatableCalls))
			//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
			//}
			
			return array($callToQueue, $op);	// insert the createTable op at the front of the queue, along with the errored op (try it again)
		}
		else if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	// column doesn't exist
			return array(getAddColumnsOp($op["class"], $op["returnedObjectReference"]->getClassDefinition()), $op);
		}else
		{	return false;
		}
	}
	
					/*************** save ***************/
					// $op holds: 	array
					//				(	"opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->getSetVariables(), 
					//					"classDefinition"=>$sqoolObject->getClassDefinition()
					//				);	
					
	// renders the SQL for saving $setVariables onto a database object referenced by $sqoolObject
	private function saveSQLgenerator($op)
	{	$queryColUpdates = "";
		$onceAlready = false;
		foreach($op["vars"] as $col => $val)
		{	if($onceAlready)
			{	$queryColUpdates .= ',';
			}else
			{	$onceAlready = true;
			}
			
			$queryColUpdates .= " `".$col."`='".$this->escapeString($val)."'";
		}
		
		return array
		(	"numberOfCommands" => 1,
			"queries" => 'UPDATE '.$op["class"].' SET'.$queryColUpdates.";"
		);
	}
	
	private function saveErrorHandler($op, $errorNumber)
	{	if($errorNumber == 1054 || $errorNumber == 1853321070)	// the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
		{	// column doesn't exist
			return array(getAddColumnsOp($op["class"], $op["classDefinition"]), $op);
		}else
		{	return false;
		}
	}		
	
					/*************** fetch ***************/
	
	
	
					/*************** sql ***************/
					// $op holds: array("opName"=>"sql", "query"=>$query, "resultVariableReference"=>&$resultVariable);
					
	private function sqlSQLgenerator($op)
	{	return array
		(	"numberOfCommands" => 1,
			"queries" => $op["query"].";"
		);
	}
	
	private function sqlResultHandler($op, $results)
	{	$op["resultVariableReference"] = $results;	// set the variable returned by the method 'sql'
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
		
		$query = 'CREATE TABLE '.$op["class"].' (';
		
		$query .= 'sq_'.$op["class"].'_id INT NOT NULL PRIMARY KEY AUTO_INCREMENT';	// add an object id field (sq for sqool defined field - as opposed to user defined)
		
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
	
	
	
	/********************** NON-STANDARD METHODS USED INTERNALY, BUT ALSO ENCOURAGED FOR EXTERNAL USE *************/
	
	// performs an sql query, and echos error information if debugFlag is on
	public function escapeString($string)
	{	$this->connectIfNot();
		return $this->con->real_escape_string($string);
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
	{	return false;
	}
	
	// executes a multiquery
	private function rawSQLquery($query)
	{	$this->connectIfNot();
		
		if(self::$debugFlag)
		{	echo "\n<br><br>\nExecuting: ".$query."\n<br><br>\n";
		}
		
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
		
		return array("resultSet"=>$resultSet, "errorNumber"=>$this->con->errno, "errorMsg"=>$this->con->error);	// returns the results and the last error number (the only one that may be non-zero)
	}	
	
	// if the object is not connected, it connects
	// returns true if a new connection was made
	private function connectIfNot()
	{	if($this->con === false)
		{	//connect
			
			if(self::$debugFlag)
			{	echo "\n<br><br>\nAttempting to connect to the database ".$this->database.".\n<br><br>\n";
			}
			
			@$this->con = new mysqli($this->host, $this->username, $this->password, $this->database);
			
			if($this->con->connect_errno)
			{	if($this->con->connect_errno == 1049)	// database doesn't exist
				{	// create database
					$this->con = new mysqli($this->host, $this->username, $this->password);
					$this->rawSQLquery('CREATE DATABASE '.$this->database.';');
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
	
	// adds a save operation into the queue for a certain object
	// meant for internal use by the sqoolobj class only
	public function saveSqoolObject($sqoolObject)
	{	$this->callQueue[] = array					// insert the call into the callQueue
		(	"opName"=>"save", "class"=>$sqoolObject->getClassName(), "vars"=>$sqoolObject->getSetVariables(), "classDefinition"=>$sqoolObject->getClassDefinition()
		);	
		
		$sqoolObject->clearSetVariables();	// since the database has been changed (or will be in the case of a queued call), those variables are no longer needed (this enforces good coding practice - don't update the DB until you have all the data at hand)
		
		if($this->getQueueFlag() == false)
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
	
	// checks to see if $op1 is in $oplist
	// will return true if they have the same value
	private function inOperationsList($op1, $oplist)
	{	return in_array($op1, $oplist);
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
	function getClassDefinition()	// meant for internal use by the sqool class only
	{	return self::$classDefinition;	
	}
	
	function __set($name, $value)
	{	$backendName = strtolower($name);
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
	protected static function make($className, $members)
	{	if(self::$classDefinition === false)	// class can only be made once
		{	$classTableName = strtolower($className);
			sqool::require_AZaz09($classTableName);
			if(in_array($classTableName, sqool::reservedTableNames()))
			{	throw new cept("Sqool reserves the class name ".$classTableName." for its own use. Please choose another class name.");
			}
			sqool::addClass($classTableName);		// add class to sqool's list (throws error if a class is redefined)
			
			self::$className = $classTableName;
			self::$classDefinition = self::parseClassDefinition($members, $className);	// should output array like array(name => array(baseType[, listORclassType][, object_list_type]))
		}
	}
	
	public function save()
	{	if(count($this->setVariables) == 0)
		{	throw new cept("Attempted to save an empty dataset to the database");
		}
		if($this->ID === false)
		{	if($this->con->countCallQueue() != 0)
			{	$this->con->go();
			}
			
			if($this->ID === false)	//if the ID is still false
			{	throw new cept("Attempted to save an object that isn't in a database yet. (Use sqool::insert to insert an object into a database).");
			}
		}
		
		$this->con->saveSqoolObject($this);
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
	
	
	/********************** BELOW THIS ARE MzETH/oDS FOR INTERNAL USE ONLY *************************/
			
	
	// parses type declarations for a sqool class (in the form "type:name  type:name  etc")
	// returns an array with keys representing the names of each member and the values being an array of the form array(baseType[, listORclassType][, className_forObjectList])
	// see parseSingle for examples of the returned data
	private static function parseClassDefinition($members, $className)
	{	$result = array();
		while(true)
		{	$nextMember = self::parseSingle($members, $index);	// set the result array
			
			if($nextMember === false)
			{	break;		// done parsing (no more members)
			}
			$keys = array_keys($nextMember);
			if(in_array($keys[0], array_keys($result)))
			{	throw new cept("Error: can't redeclare member '".$keys[0]."' in class definition (note: member names are NOT case-sensitive)");
			}
			if($keys[0] == 'sq_'.$className.'_id')
			{	throw new cept("Error: sqool reserves the member name '".$keys[0]."' (note: member names are NOT case-sensitive)");
			}
			$result = sqool::assarray_merge($result, $nextMember);
		}
		return $result;
	}
	
	// returns an array where the only member has a key (which represents the name of the member) which points to an array of the form array(baseType[, listORclassType][, className_forObjectList])
	// examples of returned values: array("bogus"=>array("int"))  array("bogus2"=>array("list", "int")
	//		  						array("bogus3"=>array("object", "someobjName")  array("bogus4"=>array("list", "object", "yourmomisanobject") 
	private static function parseSingle($members, &$index)
	{	$whitespace = " \t\n\r";
		
		$index += 	sqool::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
		if($index >= strlen($members))
		{	return false;	// no more members (string is over)
		}
	
		$numchars = sqool::getCertainChars($members, $index, "_", "azAZ09", $baseType);		// get base type
		if($numchars==0)
		{	throw new cept("Error parsing types: type was expected but not found.<br/>\n"); 	// error if type isn't found
		}
		$index += $numchars;
			
		$index += 	sqool::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
		$numchars = sqool::getCertainChars($members, $index, "_", "azAZ09", $listOrRefOrNone);		// get "list" (if it exists)
		$index += $numchars;
		if($listOrRefOrNone=="list")
		{	// 'list' is found
			$listIsFound = true;
		}else if($numchars==0)
		{	$listIsFound = false;
		}else			// something other than "list" or "ref" is found
		{	throw new cept("Error parsing types: 'list' or ':' expected but got'".$listOrRefOrNone."'<br/>\n");
		}
					
		$index += 	sqool::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
		$numchars = sqool::getCertainChars($members, $index, ":", "", $dumdum);			// get colon
		if($numchars==0)
		{	throw new cept("Error parsing types: ':' was expected but not found.<br/>\n");		// error colon isn't found
		}
		$index += $numchars;
		
		$index += 	sqool::getCertainChars($members, $index, $whitespace, '', $dumdum);	// ignore whitespace
		$numchars = sqool::getCertainChars($members, $index, "_", "azAZ09", $name);		// get name
		if($numchars==0)
		{	throw new cept("Error parsing types: className was expected but not found.<br/>\n"); 	// error if name isn't found
		}
		$index += $numchars;
		
		if(in_array($baseType, sqool::primtypes()))	// is a primitive type
		{	$type = array(strtolower($baseType));
		}else
		{	$type = array('object', strtolower($baseType));
		}
		
		if($listIsFound)
		{	return array(strtolower($name) => array_merge(array('list'), $type) );
		}else
		{	return array(strtolower($name) => $type);
		}
	}
	
};
	
?>
