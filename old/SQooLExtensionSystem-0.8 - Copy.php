<?php
/*	See http://www.btetrud.com/Sqool/ for documentation

	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2009, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

include_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces


// todo: use the php object type ArrayObject for lists

/*	Classes that extend SqoolExtensionSystem should have a constructor that can be validly called with 0 arguments.

	Defines:		
		class SqoolExtensionSystem			connection to a database

		 public static members:
            connect			connect to the database
			debug			turns on or off debugging messages (for all sqool connections and objects) - debugging is on by default so.... yeah..
            clearClasses    clears the list of proccessed sqool classes. This should only be used to test automatic handling of schema additions - can only be used in debug mode
			killMagicQuotes	Run this to undo the idiocy of magic quotes (for all sqool connections and objects)
         protected static members:
			addOperation	adds an operation in the form of up to three functions: an SQL generator, a result handler, and an error handler
								Note that the SQL generator for an operator can add to or modify the $op data passed to it, and use that additional or modified data in the result handler
            addType         Adds a sqool type to the list of available types
            $debugFlag		This is made available to sqool sub-classes (ie protected) if you want to use this flag in an extension. It is changed through calls to the method 'debug'
         interface interface members:
            sclass			should return the definition for a sqool class type. This function should be defined for a class extending sqool.
                            Does not create a table in the DB until an object is inserted (lazy table creation).
                                Member types: bool, string, tinyint, int, bigint, float, :class:, :type: list
         public instance members:
 			getDB			returns a connection to another database on the same host
			queue			sets the connection to queue up calls to the database (all the queued calls are performed upon call to the 'go' method)
								calls that are queued include: insert, fetch, sql, save
			go				performs all the queries in the queue
			escapeString	Only needed for using raw sql (with the 'sql' call), because sqool escapes data automactically. Escapes a string based on the charset of the current connection.
         protected instance members:
            $queueFlag      If turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'
			addToCallQueue	Adds an operation at the end of the call queue
 */


 /*	Todo:
  		* add a hook for the debug messages so devs can add their own debug message handler
		* add a "persistent" option when opening a connection (Prepending host by p: opens a persistent connection)
		* don't execute a multiquery if theres no queries to execute
		* make sure case is lowered for all internal names
		* Make sure you lower the case of all member names and classnames as they come in
		* have a facility for limiting operations that can be done on an object (allowOnly and disallow should be mutually exclusive). Make sure the mechanism can't affect internal behaviors (for example the insert call using create table or whatever)
		* Think about adding the ability to specify the length of a string type (which would be good for
		* add the option "noindex" to memberDataControl (so it won't return an object's index)
		* only fully parse the class being searched for (rather than parsing all the classes at once)
 */
 
 /*	List of optimizations (that are already done):
		* Lazy database connection - the database is not connected to until a query needs to be processed
		* using queue and go pack all the queries into one network request, thereby minimizing flight time
 */

// represents a database object (the entire database is also considered an object)
// performs lazy connection (only connects upon actual use)
class SqoolExtensionSystem			// connection to a database
{
	// static variables
	
	protected static $debugFlag = true;

	protected static $initialized=false;	// todo: figure out how to remove this
    private static $operations = array();
    private static $killMagicQuotes=false;	// assumes magic quotes are off
    private $building=false;	// is set to true while the function go() is building SQL (used for the insertOpNext function)
	private static $classes;	// members should be of the form phpClassName=>array("name"=>tableName, "definition"=>definition)
								// where definition should be an array with keys representing the names of each member
                                // and the values being an array of the form array("baseType"=>baseType[, "listType" => listType])

    // constants

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


	const connection_failure 		= 0;
	const invalid_name 		        = 1;
	const general_query_error 		= 2;	// cept::data should hold the error number for the query error


	// instance variables


    protected $queueFlag = false;		// if turned to true, all database accesses will be queued up and not processed until the queue is executed with a call to 'go'


	private $databaseRootObject = false;	// the sqool object that represents the database as a whole (the root sqool object should point to itself here
	private $connectionInfo=array("con"=>false);		// username, password, host, database, con (the connection to the database)

    private $callQueue = array();	        // can be accessed from operations added to sqool

    private $id=false;						// the ID of the object (inside the table $classTable)
	private $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
	private $changedVariables = array();	// catalogues the variables that have been changed



    /***********************   static functions  *********************/


    // **** public static functions  ****



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
	{	self::validateWordName($databaseIn);

        $className = get_called_class();
		$returnedObject = new $className();
		$returnedObject->setUpSqoolObject($returnedObject);

		$returnedObject->connectionInfo = array // set connection parameters
		(	"username" => $usernameIn,
			"password" => $passwordIn,
			"host" => $hostIn,
			"database" => $databaseIn,
			"con" => null
		);

		return $returnedObject;
	}

	// Running this function will counteract the extreme stupidity of magic quotes - NOTE THAT THIS WILL ONLY AFFECT SQOOL
	// I hope the guy who invented magic quotes has been repeatedly punched in the face
	public static function killMagicQuotes()
	{	self::$killMagicQuotes = true;
	}



    // **** protected static functions ****


    // This clears the class that have already been processed
    // This should only be used to test automatic handling of schema additions
    protected static function clearClasses()
    {	if(false === self::$debugFlag)
        {	throw new cept("The clearClasses method can only be used in debug mode");
        }

        echo "Executing clearClasses. Note that this function cannot be called when debug mode is off<br>\n";
        self::$classes = array();
    }


	// adds an operation to sqool's backend
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
            // should return null to indicate the error won't be handled (and should be thrown). If an errorHandler isn't set, its treated like it always returns false
            // receives these parameters
                // $op - the operation array
                // $errorNumber - the error number
                // $results - the results of the operation before the errored query
	protected static function addOperation($opName, $callbacks)
	{	if(in_array($opName, array_keys(self::$operations)))
		{	throw new cept("Attempting to redeclare sqool operation '".$opName."'.");
		}

        if( ! isset($callbacks["errorHandler"]))
        {   $callbacks["errorHandler"] = function(){return null;};
        }

		self::$operations[$opName] = $callbacks;
	}


	protected static function primValToSQLVal($val, $type)
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
	protected static function SQLvalToPrimVal($val, $type)
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



	// eventually, this might recognize which need the weird quote and which don't
	// $variableNames should either be  or a single string
		// if $variableNames is an array of strings, the function will return an array of quoted names
		// if $variableNames is a single string, the function will return the quoted name as a single string
	protected static function makeSQLnames($variableNames)
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

    // just like php and C and other programming languages, variables must consist of alphanumeric characters and cannot start with a numerical digit
	protected static function validateWordName($variable)
	{	$string = "".$variable;
		$theArray = str_split($string);

		if(self::charIsOneOf($theArray[0], '', '09'))
		{	throw new cept("Invalid name '".$variable."' - names cannot start with a numerical digit", self::invalid_name);
		}

		foreach($theArray as $c)
		{	if(false == self::charIsOneOf($c, '_', 'azAZ09'))
			{	throw new cept("Name contains the character '".$c."'. Variable name expected - a string containing only alphanumeric characters and the character '_'.", self::invalid_name);
			}
		}
    }

    // returns the string name of the sqool base class
    protected static function getBaseClassName()
    {   return 'SqoolExtensionSystem';
    }


    /********************** Instance functions *************************/

    // **** public instance functions  ****


	// returns a connection to another database on the same host
	public function getDB($databaseName)
	{	$this->validateRoot("Attempting to get a sibling database from an object that isn't a database. I think you can figure out why thats wrong.");
		return $this->connect($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"], $databaseName);
	}

	// sets up queueing all database accesses (use 'go' to process the queue - which does all the calls in order)
	public function queue()
	{	$this->validateRoot("Attempting to begin queueing calls on something other than a database.");
		$this->databaseRootObject->queueFlag = true;
	}

	// processes the queued calls, performing their functions in order
	public function go()
	{	$this->validateRoot("Attempting to make something other than a database execute queued calls.");

		$this->databaseRootObject->queueFlag = false;	// reset queueFlag back to false (off)
		//$nonRepeatableCalls = array();	// record which calls should generate errors if they are tried multiple times

		$buildResult = $this->buildSQL($this->callQueue);

		$this->executeQueriesAndHandleResult($buildResult["multiqueries"], $buildResult["numberOfCommands_inEachMultiquery"]);
	}

    
    public function escapeString($string)
	{	$this->connectIfNot();
		if(self::$killMagicQuotes)
		{	$string = stripslashes($string);
		}
		return $this->connectionInfo["con"]->real_escape_string($string);
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

    // to use the '__get' magic function in child classes, use ___get (with three underscores instead of two)
	function __get($name)
	{	if($name == 'id')
		{	return $this->id;
		}else if(array_key_exists($name, $this->setVariables))
		{	return $this->setVariables[$name];
		}else if( $this->containsMember($name) )	// if sqool class has the member $name (but it isn't set)
		{	throw new cept("Attempted to get the member '".$name."', but it has not been fetched yet.");
		}else
		{	if(method_exists($this, "___get"))
			{	return $this->___get($name);
			}else
			{	throw new cept("Object doesn't contain the member '".$name."'.");
			}
		}
	}



    // **** protected instance functions  ****



    // throws error if this object doesn't have an ID (or doesn't have an ID waiting for it in the queue)
	protected function requireID($actionText)
	{	if($this->id === false)
		{	if($this->databaseRootObject !== false && count($this->databaseRootObject->callQueue) != 0)
			{	$queueFlag = $this->databaseRootObject->queueFlag;  // save the state of the queue flag

                $this->databaseRootObject->go();			// TODO: handle this better later (write code so that an extra multi-query isn't needed)
				$this->databaseRootObject->queueFlag = $queueFlag;    // reset the queue flag as it was
			}

			if($this->id === false)	//if the ID is still false
			{	throw new cept("Attempted to ".$actionText." an object that isn't in a database yet. (Use sqool::insert to insert an object into a database).");
			}
		}
	}
	protected function isRoot()
	{	if($this->databaseRootObject === $this)
		{	return true;
		}else
		{	return false;
		}
	}

	// make sure the calling function is the root
	protected function validateRoot($error)
	{	if(false === $this->isRoot())
		{	throw new cept($error);
		}
	}
	// make sure the calling function is NOT the root
	protected function validateNOTRoot($error)
	{	if($this->isRoot())
		{	throw new cept($error);
		}
	}

    // basically just gives a new Sqool object its root
	protected function setUpSqoolObject($root)
	{	$this->databaseRootObject = $root;
	}




    protected function clearChangedVariables()
    {	$this->changedVariables = array();
    }

    // meant for use by a class that extends sqool

    protected function addToCallQueue($operation)
    {	$this->callQueue[] = $operation;
    }

	protected function containsMember($memberName)
	{	if(get_class($this) === self::getBaseClassName())
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

    // returns the value, and any operations needed to create that value
	// $fieldToUpdateAfterInsert is only used if the $sqoolVal is a sqool object that is not in the database yet (and thus needs to be inserted)
		// has the form array("class"=>$className, "member"=>$member, "variable"=>$numberForVariable, "objectReference"=>$newObject),
		// "objectReference" is only needed for inserting lists
	// $listInfo can either be "new", an integer listID, or false if it isn't new but not ID is immediately available
	protected function PHPvalToSqoolVal($sqoolType, $sqoolVal, $fieldToUpdateAfterInsert, $listInfo=false)
	{	$ops = array();

        $isPrimitiveType = in_array($sqoolType["baseType"], self::primtypes());
        if(!$isPrimitiveType && ! in_array(self::getBaseClassName(), self::getFamilyTree($sqoolType["baseType"])))
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
            {   $sqlVal = self::primValToSQLVal($sqoolVal, $sqoolType["baseType"]);

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


	protected static function debugLog($msg) {
		echo "\n<br>\n".$msg."\n<br>\n";
	}

    /********************** UTILITY METHODS ********************/


    // merges associative arrays
	// keys of array2 will take precedence
	private static function assarray_merge($array1, $array2)
	{	foreach($array2 as $k => $v)
		{	$array1[$k] = $v;
		}
		return $array1;
	}

	// calls function references, even if they start with 'self::' or '$this->'
	// $params should be an array of parameters to pass into $function
	private static function call_function_ref($thisObject, $function, $params)
	{	if(gettype($function) === 'object' )
        {   return call_user_func_array($function, $params);
        } else if('$this->' == substr($function, 0, 7))
		{	return call_user_func_array(array($thisObject, substr($function, 7)), $params);
		}else if('self::' == substr($function, 0, 6))
		{	return call_user_func_array(get_called_class()."::".substr($function, 6), $params);
		}else
		{	return call_user_func_array($function, $params);
		}
	}

	// returns all the classes an object or class inherits from (the list of class parents basically)
	// returns the root class first, the object's class last
	private static function getFamilyTree($objectOrClassName)
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
	private static function methodIsDefinedIn($objectOrClassName, $methodName)
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
	private static function get_defined_class_methods($className)
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
	private static function changeClass(&$obj, $newClass)
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
	private static function classCast_callMethod(&$obj, $newClass, $methodName, $methodArgs=array())
	{	$oldClass = get_class($obj);

		self::changeClass($obj, $newClass);
		$result = call_user_func_array(array($obj, $methodName), $methodArgs);	// get result of method call
		self::changeClass($obj, $oldClass);	// change back

		return $result;
	}

	private static function in_array_caseInsensitive($thing, $array)
	{	$loweredList = array();
		foreach($array as $a)
		{	$loweredList[] = strtolower($a);
		}
		return in_array(strtolower($thing), $loweredList);
	}

	private static function array_insert($array, $pos, $value)
	{	$array2 = array_splice($array,$pos);
	    $array[] = $value;
	    return array_merge($array,$array2);
	}

        //  **** string parsing functions ****

        // tests if a character is in the list of "singles" or in one of the "ranges"
        private static function charIsOneOf($theChar, $singles, $ranges)
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





    /********************** Private functions *************************/




    // returns true if the className is a valid sqool class
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

    // checks if a sqool type $typeName has been defined
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
			} else
			{	$lastClassFirst = array_reverse($classNames);

                if(false === self::isADefinedClassName($lastClassFirst[0]))
                {	throw new cept("'".$lastClassFirst[0]."' is not a defined class name.");
                }

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
	{	if(false == in_array(self::getBaseClassName(), self::getFamilyTree($c)))
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
        $baseClassName = self::getBaseClassName();
		$shapeShifter = new $baseClassName();	// doesn't matter what kind of object is created here (since it will be casted)
		foreach($classNames as $className)
		{	$members .= self::classCast_callMethod($shapeShifter, $className, "sclass");
		}
		
		$sqoolFrontendClassName = $classNames[count($classNames)-1];
		self::$classes[$sqoolFrontendClassName] = array();	// placeholder so that the class is seen as existing
		
		// add the class definition to sqool
		$className = $sqoolFrontendClassName;
		self::validateWordName($sqoolFrontendClassName);
		if(self::isReservedName($sqoolFrontendClassName))
		{	throw new cept("Sqool reserves the class name ".$sqoolFrontendClassName." for its own use. Please choose another class name.");
		}
		
		// add class to list of $classes
		self::$classes[$sqoolFrontendClassName] = array
		(	"name"=>strtolower($sqoolFrontendClassName), 
			"definition"=>self::parseClassDefinition($members, $sqoolFrontendClassName)
		);
	}
	


	
	// todo: make sure reservedMemberNames doesn't disallow new members named the same things as private members
	private static function reservedMemberNames()
	{	$results = array();
		foreach(get_class_methods(self::getBaseClassName()) as $r)   // I think get_class_methods might have to be called outside the current scope so that private members aren't ocunted
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
	protected static function isReservedName($name)
	{	return self::isReservedSqoolClassName($name);
	}


	
	private function buildSQL()
	{	$this->building=true;
		
		// build the sql multiquery
		$multiqueries = array();
		$numberOfCommands_inEachMultiquery = array();
		
		for($n=0; $n<count($this->callQueue); $n++)	// not done as a foreach beacuse of the possibility of inserting another call into the next callQueue index (insertOpNext)
		{	$op = &$this->callQueue[$n];
			
			if(false == in_array($op["opName"], array_keys(self::$operations)))
			{	throw new cept("Invalid operation: '".$op["opName"]."'");
			}
			
			$generatorResult = self::call_function_ref($this, self::$operations[$op["opName"]]["generator"], array(&$op));
			if(gettype($generatorResult) !== 'array')
            {   throw new cept("Generator result for ".$op["opName"]." is not an array.");
            }
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
		    $operationDefinition = self::$operations[$op["opName"]];

			if
			(	$errorNumber != 0 && 
				$lastResultsIndex < $resultsIndex + $numApplicableResults - 1 && 	// tests that the current operation was responsible for an error (even if the first result of the operation didn't cause an error)
				$numApplicableResults != 0		// this makes it so results-less operations don't "steal" an error from an operation that does have results (and thus possible errors) (not actually sure if this is neccessary, but it doesn't hurt)
			)
			{	$cutInLine = self::call_function_ref($this, $operationDefinition["errorHandler"], array($op, $errorNumber, $applicableResults, $results["errorMsg"]));
                if($cutInLine === null)
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
            if(isset($operationDefinition["resultHandler"]))
            {   self::call_function_ref($this, $operationDefinition["resultHandler"], array($op, $applicableResults));
            }
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


	



    // executes a multiquery
	private function rawSQLquery($query)
	{	$connectResult = $this->connectIfNot();
		if(is_array($connectResult))
		{	return self::assarray_merge( $connectResult, array("resultSet"=>array()) );	// error information
		}

		if(self::$debugFlag)
		{	self::debugLog("Executing: ".$query);
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
		{	self::debugLog("Results: ".print_r($returnResult, true));
		}

		return $returnResult;
	}

	// if the object is not connected, it connects
	// returns true if a new connection was made
	// returns -1 if requested database doesn't exist
	private function connectIfNot()
	{	static $debugMessageHasBeenWritten = false;
        if(self::$debugFlag && ! $debugMessageHasBeenWritten)
        {	$debugMessageHasBeenWritten = true;
            self::debugLog("***** To turn off debug messages, add \"".get_class()."::debug(false);\" to your code *****");
        }

        if($this->connectionInfo["con"] === null)
		{	if(self::$debugFlag)
			{	self::debugLog("Attempting to connect to the database ".$this->connectionInfo["database"]." on ".$this->connectionInfo["host"]." with the username ".$this->connectionInfo["username"].".");
			}

			// why are errors surpressed here?
			@$this->connectionInfo["con"] = new mysqli($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"]);

			if($this->connectionInfo["con"]->connect_errno)
			{   throw new cept('Connect Error (' . $this->connectionInfo["con"]->connect_errno . ') ' . $this->connectionInfo["con"]->connect_error, sqool::connection_failure, $this->connectionInfo["con"]->connect_errno);
			}
			return true;
		}
		return false;
	}

	// takes some variable definitions
	// returns an associative array where the keys are the names of the members, and the values are their mysql type
	protected static function sqoolTypesToMYSQL($variableDefinitions)
	{	$result = array();
		foreach($variableDefinitions as $memberName => $definition)
		{	if(isset($definition["listType"]) && $definition["listType"] === "list")
			{	$type = "INT DEFAULT NULL";
			}else if(in_array($definition["baseType"], self::primtypes()))
			{	$thePrimitives = self::primitives();
				$type = $thePrimitives[$definition["baseType"]]." NOT NULL";
			}else if(in_array(self::getBaseClassName(), self::getFamilyTree($definition["baseType"])))	// if it actually is a sqool object
			{	$type = "INT DEFAULT NULL";
			}else
			{	throw new cept("Invalid baseType '".$definition["baseType"]."'");
			}
			$result[$memberName] = $type;
		}
		return $result;
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


}
