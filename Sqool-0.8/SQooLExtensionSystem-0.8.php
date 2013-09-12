<?php
/*	See http://www.btetrud.com/Sqool/ for documentation

	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2009, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

require_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces
require_once(dirname(__FILE__)."/SQooLUtils-0.8.php");



/*	Classes that extend SqoolExtensionSystem should have a constructor that can be validly called with 0 arguments.

	Defines:		
		class SqoolExtensionSystem			connection to a database

		 static members:
            connect			connect to the database (can either use credentials, or a previously opened mysqli connection)
            killMagicQuotes	Run this to undo the idiocy of magic quotes (for all sqool connections and objects)
 			debug			Sets the default debug flag for all new connections (must be called from the static context)
			debugHandler	Sets the default debug handler (which should be a callable function) for all new connections
							Parameters:  * $msg - the debug message
			addOperation	adds an operation in the form of up to three functions: an SQL generator, a result handler, and an error handler
								Note that the SQL generator for an operator can add to or modify the $op data passed to it, and use that additional or modified data in the result handler

         instance members:
 			debug			turns on or off debugging messages (for the current connection) - debugging is on by default so.... yeah..
 							With no parameters, it returns true if debugging is on (false otherwise)
			debugHandler	Sets the debug handler for the current connection.
							Uses same parameters as the debug handler passed to the static version of this method.
			connection		returns the mysqli connection
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
            queue	      	If turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
			go				Performs all the queries in the queue
			addToQueue		Adds an operation at the end of the call queue
			addAllToQueue	Adds a list of operations to the queue


	Recommendations:
		* operations that insert or update should only use data grabbed at the time of call, rather than grabbing it at execution time
			so that any updates to the data after the call (but before the list of queued calls is run with 'go') will not affect the insert or update
		* any validation related to a command should be done on call of that command, rather than during execution of the call.
  			This is so users get the error message up front in the line that caused the problem, rather than in an arbitrary place in the code they know nothing about.
 */


 /*	Todo:
 		* remove 'cond' and just user 'where'
		* Give the option to use mysqli persistent connections when opening a connection (Prepending host by p: opens a persistent connection)
			* note that it uses a connection pool that has some overhead when returning the connection to the pool
		* add go method used to execute all the queries in the queue (without a specific need for the data yet - would be used as an optimization to process things while the program doesn't have intensive activity going on)

 	Todo (for type system):
		* make sure case is lowered for all internal names
		* Make sure you lower the case of all member names and classnames as they come in
		* Think about adding the ability to specify the length of a string type (which would be good for
		* only fully parse the class being searched for (rather than parsing all the classes at once)
  		* possibly add a close method (and note that for persistent connections it doesn't actually close, but hopefully returns the connection to the pool)

 */
 
 /*	List of optimizations (that are already done):
		* Lazy database connection - the database is not connected to until a query needs to be processed
 */

// represents a database object (the entire database is also considered an object)
class SqoolExtensionSystem			// connection to a database
{
		/****   public members ****/

	// class members

	const connection_failure 		= 0;
	const invalid_name 		        = 1;
	const general_query_error 		= 2;	// cept::data should hold the error number for the query error

	// instance members

	public $connectionInfo=array("con"=>false);		// username, password, host, database, con (the connection to the database)

	public $queue = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'


		/****   private members ****/

	// class members

	private static $defaultDebugFlag=true;
	private static $defaultDebugHandler=null;
    private static $killMagicQuotes=false;	// assumes magic quotes are off

    private static $operations = array();

	// instance variables
	private $debugFlag;
	private $debugHandler;

    private $callQueue = array();	        // can be accessed from operations added to sqool


			/***********************   public functions  *********************/


    // **** public static functions  ****


	// Access a local database - attempts to create database if it doesn't exist
	// Can create a database if your host allows you to, otherwise database must already exist
	// To access the database without a password, pass in null for the password
	// returns a sqool object
	public static function connect($usernameIn_or_connection, $passwordIn=null, $hostIn='localhost')
	{	$className = get_called_class();
		$returnedObject = new $className();
		$returnedObject->debugFlag = self::$defaultDebugFlag;
		$returnedObject->debugHandler = self::$defaultDebugHandler;

		$con = null;
		if($passwordIn === null) {
			$con = $usernameIn_or_connection;
			$hostIn = null;
		}

		$returnedObject->connectionInfo = array // set connection parameters
		(	"username" => $usernameIn_or_connection,
			"password" => $passwordIn,
			"host" => $hostIn,
			"con" => $con
		);

		return $returnedObject;
	}


	static function __callStatic($name, $args) {
		// debug turns debugging on or off for all new connections (on by default)
		// $setting is true for 'on', false for 'off'
		if($name === 'debug') {
			$c = count($args);
			if($c == 1) {
				self::$defaultDebugFlag = $args[0];
			}

			return self::$defaultDebugFlag;

		// setting the debughanlder
		} else if($name === 'debugHandler') {
			self::$defaultDebugHandler = $args[0];

		} else {
			throw new cept("Public static method '".$name."' doesn't exist");
		}
	}


	// Running this function will counteract the extreme stupidity of magic quotes - NOTE THAT THIS WILL ONLY AFFECT SQOOL
	// I hope the guy who invented magic quotes has been repeatedly punched in the face
	public static function killMagicQuotes()
	{	self::$killMagicQuotes = true;
	}


	// adds a new type of operation
    // $callbacks can contain the following function members:
	    // "generator" => (required) generates SQL that is run for the given operation
            // must return an array of single queries
            // receives these parameters
                // $op - the operation array
	    // "resultHandler" (optional) handles the result returned by the database server
            // receives these parameters
                // $op - the operation array
                // $results - the results of all the queries ran by the operation
	    // "errorHandler" (optional) handles any error that a given operation expects may happen
	        // should return an array of operations to insert before the operation that errored (operations that will hopefully help avoid the error the next time around)
            // should return null to indicate the error won't be handled (and should be thrown). If an errorHandler isn't set, its treated like it always return null
            // receives these parameters
                // $op - the operation array
                // $errorNumber - the error number
                // $results - the results of the operation before the errored query
	public static function addOperation($opName, Array $callbacks)
	{	if(in_array($opName, array_keys(self::$operations)))
		{	throw new cept("Attempting to redefine sqool operation '".$opName."'.");
		}

		if( ! isset($callbacks['generator'])) {
			throw new cept("Attempting to define the type '".$opName."' without a generator function");
		}

        if( ! isset($callbacks["errorHandler"])) {
			$callbacks["errorHandler"] = function(){return null;};
        }

		self::$operations[$opName] = $callbacks;
	}



    // **** public instance functions  ****


	// debug turns debugging on or off for the current connections
	// $setting is true for 'on', false for 'off'
	function __call($name, $args) {
		if($name === 'debug') {
			$c = count($args);
			if($c == 1) {
				$this->debugFlag = $args[0];
			} else if($c>1) {
				throw new cept("Too many arguments (".$c.") to method 'debug'");
			}
			return $this->debugFlag;

		} else if($name === 'debugHandler') {
			$this->debugHandler = $args[0];

		} else {
			throw new cept("Public instance method '".$name."' doesn't exist");
		}
	}

	public function connection() {
		$this->connectIfNot();
		return $this->connectionInfo["con"];
	}

	// processes the queued calls, performing their functions in order
	public function go()
	{	//$nonRepeatableCalls = array();	// record which calls should generate errors if they are tried multiple times

		$buildResult = $this->buildSQL();

		$this->executeQueriesAndHandleResult($buildResult["multiqueries"], $buildResult["numberOfCommands_inEachMultiquery"]);
	}

    
    public function escapeString($string) {
    	if(self::$killMagicQuotes) {
    		$string = stripslashes($string);
		}
		return $this->connection()->real_escape_string($string);
	}



	// $op should contain the key 'op' that has the operation name
    public function addToQueue($op)
    {	$this->callQueue[] = $op;

    	if( ! $this->queue) {
    		$this->go();
		}
    }

    public function addAllToQueue($ops) {
		$saveQueueFlag = $this->queue;
		$this->queue = true;
		foreach($ops as $operation) {
			$this->addToQueue($operation);	// insert the call into the callQueue
		}
		$this->queue = $saveQueueFlag;

		if( ! $this->queue) {
			$this->go();
		}
	}





    /********************** Private functions *************************/


	// **** private instance functions ****


	private function debugLog($msg) {
		if($this->debugHandler === null) {
			echo "\n<br>\n".$msg."\n<br>\n";
		} else {
			call_user_func_array($this->debugHandler, array($msg));
		}
	}

	private function buildSQL() {

		// build the sql multiquery
		$multiqueries = array();
		$numberOfCommands_inEachMultiquery = array();

		for($n=0; $n<count($this->callQueue); $n++) {	// not done as a foreach beacuse of the possibility of inserting another call into the next callQueue index (insertOpNext)
			$op = &$this->callQueue[$n];

			if(false == in_array($op["op"], array_keys(self::$operations))) {
				throw new cept("Invalid operation: '".$op["op"]."'");
			}

			$generatorResult = SqoolUtils::call_function_ref($this, self::$operations[$op["op"]]["generator"], array($this, &$op));
			if(gettype($generatorResult) !== 'array') {
            	throw new cept("Generator result for ".$op["op"]." is not an array.");
            }
			$numberOfCommands_inEachMultiquery[$n] = count($generatorResult);
			$multiqueries[] = implode(";",$generatorResult).";";
		}

		return array(
			"numberOfCommands_inEachMultiquery" => $numberOfCommands_inEachMultiquery,
			"multiqueries" => $multiqueries
		);
	}

	private function executeQueriesAndHandleResult($multiqueries, $numberOfCommands_inEachMultiquery)
	{	if(count($multiqueries) === 0)
        {   throw new cept("Strange... there are no querys to run");
        }

        // run the multiquery
		$query = implode("", $multiqueries);
		$multiqueryInfo = $this->startMultiquery($query);

		// handle the results
		$resultsIndex = 0;	// holds the current results index
		//$lastResultsIndex = count($results["resultSet"])-1;	// In the case of an error, the results that were received are processed first, then the error is processed
		foreach($this->callQueue as $n => &$op) {
			$numApplicableResults = $numberOfCommands_inEachMultiquery[$n];
			$applicableResults = array(); //array_slice($results["resultSet"], $resultsIndex, $numApplicableResults);
			for($j=0; $j<$numApplicableResults; $j++) {	// get the applicable results
				$result = $this->getNextMySQLiResult($multiqueryInfo);
				if($result === null) break;
				$applicableResults[] = $result;
			}

		    $operationDefinition = self::$operations[$op["op"]];

			if(count($applicableResults) != $numApplicableResults) {	// if the current operation was responsible for an error, it won't have the correct number of results
				$error = $this->getMySQLiError($multiqueryInfo);

				if($this->debug())
				{	$this->debugLog("Error: ".print_r($error, true));
				}

				$sliceIndex = $n+1;
				$this->handleError($op, $error["errorNumber"], $error["errorMsg"], $sliceIndex, $operationDefinition, $multiqueries, $n, $applicableResults, $numberOfCommands_inEachMultiquery);
				return;		// abort the rest of this one (as its function has been executed by the above call to $this->go
			}
			//else...

			// run the resultHandler with the operation call and relevant query results as parameters
            if(isset($operationDefinition["resultHandler"]))
            {   SqoolUtils::call_function_ref($this, $operationDefinition["resultHandler"], array($this, $op, $applicableResults));
            }
			$resultsIndex += $numApplicableResults;
		}

		if($this->getNextMySQLiResult($multiqueryInfo) !== null)
		{	throw new cept("There are too many results for the query/queries being processed. Make sure your 'sql' calls only contain one query each and do NOT end in a semi-colon.");
		}

		$this->callQueue = array();	// reset callQueue
	}


	private function handleError(&$op, &$errorNumber, &$errorMessage, &$sliceIndex, &$operationDefinition,
									&$multiqueries, $n, &$applicableResults, &$numberOfCommands_inEachMultiquery) {

		if(array_key_exists('sqoolFailedOnce', $op) && $op['sqoolFailedOnce'] === $errorMessage) {
			throw new cept('Operation failed twice with the same error, halting operation. '.$errorMessage);
		}

		$op['sqoolFailedOnce'] = $errorMessage;
		$cutInLine = SqoolUtils::call_function_ref($this, $operationDefinition["errorHandler"], array($this, $op, $errorNumber, $applicableResults, $errorMessage));
		if($cutInLine === null)
		{	throw new cept("* ERROR(".$errorNumber.") in query: <br>\n'".$multiqueries[$n]."' <br>\n".$errorMessage, self::general_query_error, $errorNumber);
		}
		if(SqoolUtils::isAssoc($cutInLine)) {
			throw new cept("The error handler for ".$op['op']." isn't returning null or a list of operations (it has non-consecutive or non-numeric keys).");
		}



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
	}



	// returns an info array that tells getNextMySQLiResult and getMySQLiError how to return results
	private function startMultiquery($multiquery) {
		if($this->debug()) {
			$this->debugLog("Executing: ".$multiquery);
		}

		$connection = $this->connection();

		// execute multi query
		$info = array('firstResult'=>true, 'connection'=>$connection, 'multiqueryFail'=>false);
		if( ! $connection->multi_query($multiquery)) {
			$info['multiqueryFail'] = true ;
		}

		return $info;
	}

	// returns null if theres no more results
	private function getNextMySQLiResult(&$info) {
		if($info['firstResult']) {
			if($info['multiqueryFail']) {
				return null;
			}

			// don't need to get next_result if its the first result
			$info['firstResult'] = false;

		} else if($info['connection']->more_results()) {
			$info['connection']->next_result();

		} else {
			return null;
		}

		if($result = $info['connection']->store_result()) {
			$resultRows = $result->fetch_all(MYSQLI_ASSOC);//array();
			/*
			$results = array();
			while($row = $result->fetch_array(MYSQLI_ASSOC))
			{	$results[] = $row;
			}
			//*/
			$result->free();
		} else {
			$resultRows = array();	// store_result returns false if there is no result set (like for an insert statement)
		}

		if($this->debug())
		{	$this->debugLog("Results: ".print_r($resultRows, true));
		}

		return $resultRows;
	}

	private function getMySQLiError($info) {
		return array("errorNumber"=>$info['connection']->errno, "errorMsg"=>$info['connection']->error);
	}

	// executes a multiquery
	/*
	private function rawSQLquery($query)
	{	$connectResult = $this->connectIfNot();
		if(is_array($connectResult))
		{	return SqoolUtils::assarray_merge( $connectResult, array("resultSet"=>array()) );	// error information
		}

		if($this->debug())
		{	$this->debugLog("Executing: ".$query);
		}

		$connection = $this->connection();

		// execute multi query
		$resultSet = array();
		if($connection->multi_query($query))
		{	do	// store first result set
			{	if($result = $connection->store_result())
				{	$resultRows = $result->fetch_all(MYSQLI_ASSOC);//array();
					/* todo: possibly pass the mysqli_result object through to the operation handler (for performance reasons)
					$resultRows = array();
					while($row = $result->fetch_array(MYSQLI_ASSOC))
					{	$resultRows[] = $row;
					}
					//*//*
					$result->free();
					$resultSet[] = $resultRows;
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

		if($this->debug())
		{	$this->debugLog("Results: ".print_r($returnResult, true));
		}

		return $returnResult;
	}
	//*/

	// if the object is not connected, it connects
	// returns true if a new connection was made
	// returns false if a connection already exists
	private function connectIfNot()
	{	static $debugMessageHasBeenWritten = false;
        if($this->debug() && ! $debugMessageHasBeenWritten)
        {	$debugMessageHasBeenWritten = true;
            $this->debugLog("***** To turn off debug messages, add \"".get_class()."::debug(false);\" to your code *****");
        }

        if($this->connectionInfo["con"] === null)
		{	if($this->debug()) {
				$this->debugLog("Attempting to connect to ".$this->connectionInfo["host"]." with the username ".$this->connectionInfo["username"].".");
			}

			// why are errors surpressed here? Probably because any error will be thrown in the next few lines
			@$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"]);

			if($this->connectionInfo["con"]->connect_errno) {
			   throw new cept('Connect Error (' . $this->connectionInfo["con"]->connect_errno . ') ' . $this->connectionInfo["con"]->connect_error, sqool::connection_failure, $this->connectionInfo["con"]->connect_errno);
			}
			return true;
		}
		return false;
	}


}
