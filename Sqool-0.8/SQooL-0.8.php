<?php

require_once(dirname(__FILE__)."/SQooLExtensionSystem-0.8.php");
require_once(dirname(__FILE__)."/SQooLParser-0.8.php");
require_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces


/*	Classes that extend sqool should have a constructor that can be validly called with 0 arguments.

	Defines:


	  - class Sqool			connection to a database

		 inherits from SqoolExtensionSystem

		 static members:
            addType         Adds a sqool type to the list of available types
			connect			Overrides definition in SqoolExtensionSystem. Adds the option to specify the database (default localhost)

		 instance methods:
			get				selects a list of objects based on conditions
			getu			selects a single unique object
			sql				executes a single sql command (this is queueable)
			insert			inserts an object into the database
			getDB			returns a connection to another database on the same host
			switchDB		switches the database
			same			returns true if the passed in database is the same as the current one (as in same host, same database)
			toEntity		takes a list of table rows (as you would get back from the sql command) and a class, and returns a list of SqoolObjects


	  - class SqoolObject

		 instance members:
			id				id is read-only, and holds the id of the object. Holds null if the object isn't in a database yet.

		 instance methods:
 			getter			getter for any members defined in the sclass function
 			setter			setter for any members defined in the sclass function
			save			saves variables into the database that have been set for an object
			revert			unsets a member of the object (so that when you save, that member won't be updated)
			get				returns select object members and (if any members are objects or lists) members of those members, etc. See the function for its use.
			rm				Deletes (removes) an entity in the database server (either a class [table] or a whole database)

		 interface members:
            sclass			init function. Operations related to a class should be added here and should return the definition for a sqool class type.
							This function should be defined for a class extending sqool.
                            Does not create a table in the DB until an object is inserted (lazy table creation).
                                Member types: bool, string, tinyint, int, bigint, float, :class:, :type: list

	  - class SqoolList inherits from ArrayObject - can be used like a normal array in many cases (count, appending, getting a member, setting a member, looping with foreach)

		 instance members:
			id				id is read-only, and holds the list-id of the list. Holds null if the list isn't in a database yet.

		 instance methods:
			toArray			converts the object into a normal array (does the same thing as a cast to an array)
			count			returns the count of the array (you are also able to do count($object) )




	Tables and naming conventions (for the tables that make up the objects under the covers):
		* CLASSNAME				table that holds all the objects of a certain type
			* id					the ID of the object
			* member				example field-name of a member named "MEMBER".
									If this is a primitive, it will hold a value.
									If this is a list or object, it will hold an ID. Objects are treated as references - they all start with a null id (note this is not the same as ID 0).

		* sq_list_TYPE			table that holds all the lists for a certain type (the entry with id 0 has list_id null and the value instead represents the next list_id to use for that list type. Value is incremented for every new list)
			* id					the id of each list-entry (Not strictly used right now)
			* list_id				the ID of the list that owns the object
			* value					the value of the primitive it holds, or the id to the object it holds

		* sqool_INDEXTYPE_COLUMN1_and_COLUMN2_and_...
  								an index/constraint name

		* All tables are set to have UTF-8 charsets

	Internal operations - operations used to execute the queueable sqool calls
		* "insert"
		* "save"
		* "get"
		* "sql"
 		* ...
 */




/*	Todo:
		* some lines assume that a class will never have parameters in its constructor - isn't a safe assumption
		* add types: tinyintU and an intU (unsigned)
		* write unset (is this a "revert" ?)
 		* write an easy way to add verbatim sql conditions to fetch data control - have backticks (`) be used to surround verbatim sql
 		* don't allow range, cond, or sort for members that are objects.
 			 Also, have syntax like this to select sublists: get('listcontainer[members: listA[range: 0-45 items[<more list conditions>] ]]')
		* what happens when you insert an object that contains an object - what if that object is from another DB? Can I detect that? I might have to compare database handles on setting the object or something (so that an object from one db pretends to be an object from another
  		* think about what happens when you have a new SqoolObject (without a database root object) and you set objects on it, or deep within its tree, that don't have a database yet
  			* if all the database root objects match you're fine
  			* if not, youll get an error - probably no way to detect this up front (at the line of cause)
		* don't save a particular member if that member hasn't been updated (changedVariables has been created to represent this)
			* write a resave method to save an object even if it hasn't been updated
 			* Maybe instead just write a "modified" method that returns whether or not the object has been modified (then the user can choose if neccessary)
 			* Or maybe resave is still neccessary if you want to save all the member even if only a couple have been updated
		* Make sure that if a "does not exist" error comes back and then we try to create it (column, table, database), then we get "already exists", that we ignore that and move on as if nothing happened
 		* Improve get so that it updates php sub-objects in nested gets (instead of overwriting them)
 			* Example, when you do something like $object->get('object2[members: d e f]') when object2 already has members a b c set, it should have all 6 after this get
		* add:
			* classStruct	returns the code to create the php class for the specified database table
			* dbStruct		returns the code to create sqool classes for all the tables in the database (returned as an array of definitions)
 			* incriment		atomically incriments a field
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
		* test sending and receiving text fields longer than 255 characters - might have to caste varchars to Text
 		* test adding an object from another database as a member to an object then saving and inserting the main object
		* add the option "noindex" to memberDataControl (so it won't return an object's index)

		* make sure reservedMemberNames doesn't disallow new members named the same things as private members
		* Have some helpful messages displayed when in debug mode
			* when someone assigns more than say 50KB of data to a string, output a message that tells the programmer why its better performance-wise to use a
				filesystem than to use a DB for files. Tell them the ups and downs of a FS and a DB for file storage, including that a FS only uses bandwidth to send
				to a client, while a DB has to send to the server then the client, while a FS may not be as scalable - unless you're using some "cloud" service that
				has a scalable filesystem like amazon's S3
		* A book called High performance mysql explained you can use the "ORDER BY SUBSTRING(column, length)" trick to convert the values to character strings, which will permit in-memory temporary tables
 			* remember mysql's ability to disable index updates so maybe you can batch update a bunch of rows quickly then turn the index bback on (unclear how useful this is)

	  	* use foreign key constraints for object and list references
			* unfortunately, this means that each type would have to have its own list table - but its worth it
			   * possibly use a different database for the list tables?
			* note in the section on benefits that Sqool automatically creates databases normalized with foreign key relations
			   * also note that you still have to design your object to be normalized, but that Sqool promotes ease of normalized design
			* use on update restrict, on delete set to null for member objects
			* use restrict on update and on delete for joined tables (subclass tables) - you need to delete all joined rows all at once
 		* Have the ability to specify that an object field doesn't have a forien key constraint (something like noindex) since foreign key constraints require indexes

		* Do some performance testing comparing prepared statements with multi-query
			* I can't find any numbers online and some jerk said that if i create a temporary table, i'm already killing performance



*/

/*	List of optimizations (that are already done):
		* Lazy database/table/column creation - things are created only after they could not be found (that way there needs be no code that checks to make sure the db/table/column is there before asking for it or writing to it)
		* a save is not executed if the object has no set or changed variables

*/





// represents a database
class sqool extends SqoolExtensionSystem {

		/****   public members ****/


		/****   private members ****/

	// class members
	const sqoolSQLvariableNameBase = "sq_var_";
	const sqoolSQLtemporaryTableNameBase = "sq_temporary_";
	const sqoolSQLprocNameBase = 'sqool_temporary_proc';
	const sqoolSQLtableNameBase = 'sq';
	private static $types = array();

	// instance members
	public $database = null;	// only public because an anonymous function in Sqool needs to access it
	private $nextReferenceVariableNumber = 0;


		/***********************   public functions  *********************/

    // **** public static functions  ****

	// adds a new sqool type
    // $callbacks can contain the following function members:
    	// "sqlType" => (required) generates the sql type for use in generating the schema
	    // "sqlValue" => (required) generates SQL used in putting into the database
            // receives these parameters:
				//* $thisType
				//* sqool $thisSqool
				//* $phpValue
	    // "phpValue" (required) generates a php value (as would be referenced in another object) from an SQL result
            // receives these parameters:
				//* $thisType
				//* sqool $thisSqool
				//* $sqlValue
		// "phpObject" (optional - required for objects) generates a php object from an SQL result set
        // "fields" (optional - required for objects) is a list of object fields
        // "members" (optional - required for objects) is a list of object members keyed by their fields
        // "updateOps" (optional - required for objects) - returns the update operations to update the object
        	// receives these parameters
				//* $object
    // this should *not* be called internally to add a class as a type (since this checks to make sure there isn't a SqoolObject object with that type name)
	public static function addType($typeName, Array $callbacks) {
		if(in_array($typeName, array_keys(self::$types)))
		{	throw new cept("Attempting to redefine sqool type '".$typeName."'.");
		}

        // make sure there isn't a clash between added types and SqoolEntities
        if(class_exists($typeName) && in_array("SqoolObject", SqoolUtils::getFamilyTree($typeName))) {
            throw new cept("Attempting to add the type '".$typeName."' when a SqoolObject already exists with that name. Please choose another type name or rename the class.");
        }

        // check for required members
        foreach(array('sqlType', 'sqlValue', 'phpValue') as $member) {
            if( ! isset($callbacks[$member])) {
                throw new cept("Attempting to define the type '".$typeName."' without a ".$member." function");
            }
        }

		$callbacks['name'] = $typeName;
		self::$types[$typeName] = $callbacks;
	}

	public static function connect($usernameIn_or_connection, $passwordIn=null, $database=null, $hostIn='localhost') {
		$db = parent::connect($usernameIn_or_connection, $passwordIn, $hostIn);
		$db->database = $database;

		$saveQueueFlag = $db->queue;
		$db->queue = true;
		$db->addToQueue(array('op'=>"selectDatabase", "databaseName"=>$database));
		$db->queue = $saveQueueFlag;

		return $db;
	}


    // **** public instance methods  ****

	// override
	public function go() {
		parent::go();
		$this->nextReferenceVariableNumber = 0; // reset the nextReferenceVariableNumber
	}

	// fetches objects from the database
	// Each table is considered a member of a list
	/*
		object->get("<memberList>");	// note that at the top level, only one member may be in the memberList (inner memberLists don't have this restriction)

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
			// ranges: used for selecting a slice of the list to fetch (after being sorted).
				// Each number is an index (inclusive).
					// e.g. in "range: 4:8" the 5th through the 9th items are selected, a total of 5 items.



		// <expression> represents a boolean or mathematical expression (ie a where clause)
		// Right now the conditional expects the only words to be function names or columns,
		//	this means that any operators need to be character opratoes (e.g. "&&" instead of "and")
		// = null and != null are transformed to the sql "is null" and "is not null" respectively
		// The sql condition:
			//	`x` > '5' AND `y` < '3' AND (`x` = `y` OR `y`*'5' >= `x`*`z`)
			//	would be written in sqool as:
			//	x > ',5,'&& y <',3,'&& (x=y || y*',5,'>= x * z )
	*/
	public function get(/*$memberList*/) {
		$args = func_get_args();
		$memberListResult = self::getFetchMemberListForTopLevel($args);

		$list = new SqoolList($this, null);
		$relMember = new SqoolRelativeMember($list);

		$this->addAllToQueue($this->createFetchOperation($memberListResult['type'], $memberListResult['control'], $relMember));

		return $list->toArray();
	}

	// get unique
	// returns a single object
	// throws exception unless exactly one object is found (more than one result and no results both cause exceptions)
	// takes the same arguments as get
	public function getu(/*$memberList*/) {
		$args = func_get_args();
		$memberListResult = self::getFetchMemberListForTopLevel($args);

		// set up new object to return
		$class = $memberListResult['type']['name'];
		$object = new $class();
		$object->setupSqoolObject($this);


		if(array_key_exists('members', $memberListResult['control'])) {
			// if the args list some members, grab those members (plus the id member)

			$members = array_keys($memberListResult['control']['members']);
			$idMember = $memberListResult['type']['idMember'];
			if( ! in_array($idMember, $members)) {
				$members[] = $idMember;
			}
		} else {
			// otherwise grab all the fields
			$members = array_keys($memberListResult['type']['fields']($memberListResult['type']));
		}

		return $this->fetchForSingleObject($object, $memberListResult['type'], $members, $memberListResult['control']);
	}


	public function sql($sql) {
		$results = new SqoolList($this, null);
		$this->addToQueue(array('op'=>"sql", "sql"=>$sql, 'forward'=>$results));
		return $results->toArray();
	}

	// inserts an object into the database
	// if database accesses are being queued, the returned object's ID won't be set until after the queue is executed with 'go'
	// returns the object for convenience
	public function insert(SqoolObject $object) {
		if( $object->databaseRootObject !== null) {
			throw new cept("Trying to insert an object that's already stored in a database. Please create a copy of the object first.");
		}

		$object->setUpSqoolObject($this);
		$this->addAllToQueue($this->createInsertOp($object));
		return $object;
	}

	// returns a connection to another database on the same host
	public function getDB(String $databaseName)
	{	return $this->connect($this->connectionInfo["host"], $this->connectionInfo["username"], $this->connectionInfo["password"], $databaseName);
	}

	public function switchDB(String $databaseName) {
		$this->addToQueue(array('op'=>"selectDatabase", "databaseName"=>$databaseName));
	}

	public function same(sqool $db) {
		return $this->connectionInfo["host"] === $db->connectionInfo["host"]
				&& $this->database === $db->database;
	}



		/***********************   private methods *********************/


    // **** private static functions  ****



		/*private*/ public function fetchForSingleObject($object, $type, $members, $dataControl) {	// only public so that SqoolObject can use this
			$relMember = new SqoolRelativeMember($object);
			$this->addAllToQueue($this->createFetchOperation($type, $dataControl, $relMember));

			return $object;
		}

		private static function getFetchMemberListForTopLevel($args) {
			if(count($args) === 0) {
				throw new cept("A call to 'sqool::get' must have fetch options.");
			}

			$fetchControl = self::parseFetchMemberList($args);
			if( count($fetchControl) !== 1) {
				throw new cept("A fetch (get) from a Sqool Database must have one and only one class to fetch for at the top level.");
			}

			$keys = array_keys($fetchControl);
			$class = $keys[0];
			$type = self::getSqoolType($class);

			return array('type'=>$type, 'control'=>$fetchControl[$class]);
		}

		/*private*/ public static function parseFetchMemberList($args) {	// only public so that SqoolObject can use this
			$memberList = self::mergeStringFetchOptions($args);

			$parser = new SqoolFetchParser($memberList);
			if( ! $parser->topLevelMemberList($fetchControl)) {
				throw new cept("Parse error when parsing the  member list for get call '".$memberList."': ".$parser->failureTrace());
			}
			return $fetchControl;
		}

		// creates a fetch operation, and any operations that need to be done to fulfil that fetch
		// returns a list of operations
		// $relMember represents the object to modify
			// should be an array of names representing an inner member relative to an object (eg array($object, 'a','b','c') would represent $object->a->b->c)
		private function createFetchOperation($type, $dataControl, SqoolRelativeMember $relMember) {

			$fields = $type['fields']($type);

			if(array_key_exists('members', $dataControl)) {
				$membersToFetch = array_keys($dataControl['members']);
				if(!in_array($type['idMember'], $membersToFetch))
					$membersToFetch[] = $type['idMember'];
			} else {
				$membersToFetch = array_keys($fields);
			}

			$fieldsToFetch = array();
			foreach($membersToFetch as $member) {
				$fieldsToFetch[] = $fields[$member]['sqlName'];
			}

			$temporaryTable = self::sqoolSQLtemporaryTableNameBase.$this->getNextReferenceNumber();
			$mainOp = array
			(	"op" => "fetch",
				"type"=>$type, "fields" => $fieldsToFetch,
				"temporaryTable"=>$temporaryTable,
				"relMember"=>$relMember
			);

			// add other data control
			foreach(array('cond', 'sort', 'range') as $controlType) {
				if(array_key_exists($controlType, $dataControl)) {
					if($controlType === 'cond') {
						$control = self::buildCondition($type, $dataControl[$controlType]);
					} else {
						$control = $dataControl[$controlType];
					}

					$mainOp[$controlType] = $control;
				}
			}

			$operations = array($mainOp);
			if(isset($dataControl['members'])) {
				foreach($dataControl['members'] as $name => &$innerDataControl) {
					$memberDefinition = $fields[$name];
					$memberType = self::getSqoolType($memberDefinition['typeParameters']);

					if(array_key_exists('fields', $memberType)) {	// for now, if the type doesn't have any fields, then don't attempt to create a fetch operation for them

						$idMember = $memberType['idMember'];

						// add the condition that the id is in the object's selected by the parent select
						self::addCondition($innerDataControl, array(
							array('type'=>'word','string'=>$idMember),
							array('type'=>'other','string'=>' in(select '.$memberDefinition['sqlName'].' from '.$temporaryTable.')')
						));

						$operations = array_merge
						(	$operations,
							$this->createFetchOperation($memberType, $innerDataControl, $relMember->subMember($name))
						);
					} else if($memberType['name'] === 'list') {
						// for now, just always get the whole array
						// when theres better syntax for fetching lists, we'll add back conditions

						if(true) { // primitive
							$listTemporaryTable = self::sqoolSQLtemporaryTableNameBase.$this->getNextReferenceNumber();
							$operations[] = array(
								'op'=>"getListValues", 'temporaryTable'=>$listTemporaryTable,
								'type'=>$memberType,
								'cond'=>'list_id in(select '.$memberDefinition['sqlName'].' from '.$temporaryTable.')',
								"relMember"=>$relMember
							);

						} else { // not primitive
							$this->createFetchOperation(array_slice($type, 1), array(
								array('type'=>'word','string'=>$temporaryTable),
								array('type'=>'other','string'=>' in(select '.$memberDefinition['sqlName'].' from '.$temporaryTable.')')
							), $relMember);
						}


						$operations[] = array("op"=>"rmTable", "table"=>$listTemporaryTable, "temporary"=> true);
					}
				}
			}

			$operations[] = array("op"=>"rmTable", "table"=>$temporaryTable, "temporary"=> true);

			return $operations;
		}

		// $newConditions should be an array of conditions in post-parsed format
		private static function addCondition(&$originalDataControl, $newConditions) {
			// add the condition that the id is in the object's selected by the parent select
			$condition = array();
			if(array_key_exists('cond', $originalDataControl)) {
				$condition[] = array('other',' and ');
			} else {
				$originalDataControl['cond'] = array();
			}
			$condition = array_merge($condition, $newConditions);

			$originalDataControl['cond'] = array_merge($originalDataControl['cond'], $condition);
		}

		private function buildCondition($type, $conditionParts) {
			$result = '';
			foreach($conditionParts as $part) {
				if($part['type']==='word') {
					$fields = $type['fields']($type['fields']);
					$result .= $fields[$part['string']]['sqlName'];
				} else {
					$result .= $part['string'];
				}
			}
			return $result;
		}


	function getNextReferenceNumber() {
		$this->nextReferenceVariableNumber += 1;
		return $this->nextReferenceVariableNumber;
	}

    // $typeName should be a string that either contains a Sqool class name or a type added through sqool::addType
    /* returns an associative array that looks like this: array
		 (	'name'=>$sqoolClassName,
			'table'=> strtolower($sqoolClassName),
			'idMember' => $idMemberName,
			'fields'=> array("memberName" => array("sqlName"=>sqlName, "typeParameters"=>array($mainType[, $subtype1][, ...]), ...),
			'sqlType' => function() {},
			'sqlValue' => function($a) {},
			'phpValue' => function($a) {},
    		'updateOps' => function($object) {}
		 );
     */
    // this is not intended to be a public method (but is public so SqoolObject can access it)
    static function getSqoolType($type) {
		if( ! is_array($type)) {
			$type = array($type);
		}
		self::verifySqoolType($type[0]);
        $typeCopy = self::$types[$type[0]];
        if(count($type) > 1 && (!array_key_exists('expects', $typeCopy) || $typeCopy['expects'] !== 'typeParameters')) {
        	throw new cept('Type '.$type[0].' does not expect type parameters, but got them: '.print_r($type,true));
		}
		$typeCopy['typeParameters'] = array_slice($type, 1);
		return $typeCopy;
    }

    private static function verifySqoolType($name) {
		static $typesAlreadyBeingParsed = array();

		if(in_array($name, $typesAlreadyBeingParsed)) {
			return;
		}

        if( ! in_array($name, array_keys(self::$types))) {	// process the class and add it as a type
			array_push($typesAlreadyBeingParsed,$name);

			$sclass = self::processSqoolClass($name);
			self::$types[$name] = $sclass;
			if( ! array_key_exists($sclass['name'], self::$types)) {	// add sqool class definition as well if its different
				self::$types[$sclass['name']] = $sclass;
			}

			array_pop($typesAlreadyBeingParsed);
        }
    }
    private static function processSqoolClass($c) {
		if( ! in_array("SqoolObject", SqoolUtils::getFamilyTree($c))) {
            throw new cept("The class '".$c."' doesn't descend from SqoolObject.");
        }

        $classNames = SqoolUtils::methodIsDefinedIn($c, "sclass");
        if(count($classNames) === 0) {
        	throw new cept("The sqool class '".$c."' doesn't define an 'sclass' method.");
        }

		$sqoolClassName = $classNames[count($classNames)-1];
		if(array_key_exists($sqoolClassName, self::$types)) {
			return self::$types[$sqoolClassName];
		}

        // get all the members of the object from their sclass method
        $members = "";
        $shapeShifter = new SqoolShapeShifter();	// doesn't matter what kind of object is created here (since it will be casted)
        foreach($classNames as $className) {
        	$members .= SqoolUtils::classCast_callMethod($shapeShifter, $className, "sclass");
        }

        //self::$types[$sqoolClassName] = array();	// placeholder so that the class is seen as existing

		try {
			$sclassInfo = self::parseSclass($members);
		} catch(Exception $e) {
			$newE = new cept("Couldn't parse sclass for ".$c);
			throw $newE->causedBy($e);
		}

        // add class to list of $classes
        return array
        (	'name'=>$sqoolClassName,
        	'table'=> function($thisType) {return strtolower($thisType['name']);},
        	'idMember' => $sclassInfo['idMember'],
			'fields'=>function($thisType) use($sclassInfo){ return $sclassInfo['fieldsInfo'];},	// fields and other info keyed by member name
			'members'=>$sclassInfo['members'],	// members keyed by field name (basically for reverse lookup)
			'sqlType' => function() {
				return "BIGINT UNSIGNED";
			},

			// if the object hasn't yet been inserted, $ops will be set to an array of operations needed to insert the object
			// otherwise $ops will be set to null
			'sqlValue' => function($thisType, sqool $thisSqool, SqoolObject $a=null, &$ops) {
				if($a === null) return 'null';

				$ops = array();
				if($a->hasId()) {
					if( $a->databaseRootObject !== null && ! $a->sameDbRoot($thisSqool)) {
						throw new cept("Trying to get the sql value for an object stored in different database. Please create a copy of the object first.");
					}
					$idMember = $thisType['idMember'];
					return $a->$idMember;
				} else {
					if($a->sqlIdVariable === null) {
						$sqlIdVariable = null;	// output variable
						$ops = $thisSqool->createInsertOp($a, $sqlIdVariable);
						$a->sqlIdVariable = $sqlIdVariable;
					}

					return $a->sqlIdVariable;
				}
			},
			'phpValue' => function($thisType, sqool $thisSqool, $id) {
				if($id === null) {
					return null;
				} else {
					$object = new $thisType['name']();
					$object->setUpSqoolObject($thisSqool);
					$object->setId(intval($id));
					return $object;
				}
			},
			'phpObject' => function($thisType, sqool $thisSqool, $values) {
				$object = new $thisType['name']();
				$fields = $thisType['fields']($thisType);
				$members = array();
				foreach($values as $fieldName => $v) {
					$member = $thisType['members'][$fieldName];
					$fieldInfo = $fields[$member];
					$fieldType = $thisSqool->getSqoolType($fieldInfo['typeParameters']);

					$members[$member] = $fieldType['phpValue']($fieldType, $thisSqool, $v);
				}

				$object->setMembers($thisSqool, $members);

				return $object;
			},

			'updateOps' => function($sqoolType, SqoolObject $object) {
				$idMember = $sqoolType['idMember'];
				if(array_key_exists($idMember, $object->setVariables)) {	// only create a save op if the object is already in the database
					return $object->createSaveOp();
				} else {
					return array();	// otherwise assume it will be inserted somewhere else, and don't attempt to save it
				}
			}

        );
    }

	// parses type declarations for a sqool class (in the form "type:name  type:name  etc")
	// returns an array of the form array
						//			(	'idMember'=>$idMember,
						//				'fieldsInfo'=> array
							//			(	"memberName" => array
								//			(	"sqlName"=>sqlName,
								//				"typeParameters"=> array($mainType[, $subtype][, ...])
								//			),
								//			...
							//			),
							//			'members' => array($fieldName=> $memberName, ...)
							//		)
	// see SqoolTypeParser.parseMemberDefinition for examples of the returned data
	private static function parseSclass($members) {
		$parser = new SqoolTypeParser($members);

		if( ! $parser->sclass($result)) {
			throw new cept("Couldn't parse sclass: ".$parser->failureTrace(true));
		}

		$members = array();
		foreach($result['fieldsInfo'] as $name => &$memberAttributes) {
			self::verifySqoolTypes($memberAttributes['typeParameters']);
			if( ! array_key_exists('sqlName', $memberAttributes)) {
				$memberAttributes['sqlName'] = strtolower($name);	// default sql name
			}

			$members[$memberAttributes['sqlName']] = $name;
		}

		$result['members'] = $members;

		// default id field
		if( ! array_key_exists('idMember', $result)) {
			if(array_key_exists('id', $result['fieldsInfo'])) {
				throw new cept("'id' defined as a non-id member without choosing a replacement id member. Please define an id or change the name for the member 'id'.");
			}

			$result['idMember'] = 'id';
			$result['fieldsInfo']['id'] =  array('sqlName'=>'id', 'typeParameters'=>array('id'));	// add an object id field
			$result['fieldsInfo'] = array_merge(
				array('id'=>array('sqlName'=>'id', 'typeParameters'=>array('id'))),
				$result['fieldsInfo']
			);
			$result['members']['id'] = 'id';
		}


		return $result;
	}

	// $typesList contains an array of the form array($mainType[, $subtype1][, ...])
	private static function verifySqoolTypes($typesList) {
		foreach($typesList as $type) {
			self::verifySqoolType($type);
		}
	}

	// concatenates toegether the fetch arguments, transforming every other argument into safe-input
	private static function mergeStringFetchOptions($fetchArguments)
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

	function createInsertOp($objectToInsert, &$idReferenceVariable=null) {

		$ops = array();
		if(is_a($objectToInsert, 'SqoolObject')) {

			$sqoolType = self::getSqoolType(get_class($objectToInsert));
			$fields = $sqoolType['fields']($sqoolType);

			$vars = array();
			foreach($objectToInsert->getSetVariables() as $member => $value) {

				$fieldType = $fields[$member];

				$memberType = self::getSqoolType($fieldType['typeParameters']);

				$otherOps = array();	// output variable
				$vars[$fieldType['sqlName']] = $memberType['sqlValue']($memberType, $this, $value, $otherOps);

				$ops = array_merge($ops, $otherOps);
			}

			$idReferenceVariable = '@'.self::sqoolSQLvariableNameBase.$this->getNextReferenceNumber();

			$ops[] = array(
				'op'=>'insert',
				'table'=> $sqoolType['table']($sqoolType),
				'vars'=>$vars,
				'returnedObjectReference' => &$objectToInsert,
				'referenceVariable' => $idReferenceVariable
			);

			return $ops;
		} else if(is_a($objectToInsert, 'SqoolList')) {

			// create an operation to incriment and return the object_id value in entry 0 - will be the list id
			$type = $objectToInsert->getType();
			$idReferenceVariable = '@'.self::sqoolSQLvariableNameBase.$this->getNextReferenceNumber();
			$ops[] = array('op'=>'newListId', 'referenceVariable'=> $idReferenceVariable, 'type'=>$type);

			// create an operation to append each item to the array
				// should also create or update the neccessary objects in the array entries
			$elementType = self::getSqoolType($type['typeParameters'][1]);
			foreach($objectToInsert as $item) {
				$otherOps = array();	// output variable
				$objectId = $elementType['sqlValue']($elementType, $this, $item, $otherOps);
				$ops = array_merge($ops, $otherOps);

				$ops[] = array("op"=>"listAppend", "table"=>$type['table']($type), "listId"=>$idReferenceVariable, "objectId"=>$objectId);

			}

			return $ops;
		} else {
			throw new cept('Sqool::createInsertOp\'s first argument should be a SqoolObject or SqoolList. It is a: '.gettype($objectToInsert));
		}
	}

	// returns an addColumns operation that can be put into sqool's call queue
	// add columns defined in this class that aren't in the table schema yet
	static function getAddColumnsOp($classDefinition /*, $nonRepeatableCalls*/) {
		return array("op"=>"addColumns", "table"=>$classDefinition['table']($classDefinition), "columnDefinitions"=>self::sqlFieldDefinitions($classDefinition));

	}


	static function sqlFieldDefinitions($type) {
		$fields = $type['fields']($type);	// assumes this is an object type

		$columns = array();
		foreach($fields as $fieldType) {
			$fieldSqoolType = self::getSqoolType($fieldType['typeParameters']);
			$columns[$fieldType['sqlName']] = $fieldSqoolType['sqlType']($fieldSqoolType);
		}
		return $columns;
	}


	// private in that it isn't intended to be run outside this file
	static function initializeSqoolClass() {


		// handles creating a database if it doesn't exist, creating a table if it doesn't exist, and adding columns if they don't exist
		// $errorsToHandle should be an array of the possible values "database", "table", or "column"
		$genericErrorHandler = function(sqool $thisSqool, $op, $errorNumber, $errorsToHandle, $classDefinition=null, $databaseName=null) {


			if(($errorNumber == 1146 || $errorNumber == 656434540) && in_array("table", $errorsToHandle)) {	// table doesn't exist - the 656434540 is probably a corruption in my current sql installation (this should be removed sometime)
				// queue creating a table, a retry of the insert, and the following queries that weren't executed

				$callToQueue = array('op'=>"createTable", "table"=>$classDefinition['table']($classDefinition), 'columns'=>$thisSqool->sqlFieldDefinitions($classDefinition));

				//if(inOperationsList($callToQueue, $nonRepeatableCalls))
				//{	throw new cept("Could not create table '".$op["class"]."'", self::$table_creation_error);
				//}

				$resultOps = array($callToQueue);
				if($classDefinition['name'] === "list") {
					$referenceVariable = '@'.sqool::sqoolSQLvariableNameBase.$thisSqool->getNextReferenceNumber();
					$resultOps[] = array(
						'op'=>"insert", "table"=>$classDefinition['table']($classDefinition), "vars"=>array('list_id'=>0, 'value'=>1),
						"returnedObjectReference"=>null, "referenceVariable"=>$referenceVariable
					);
				}
				$resultOps[] = $op;

				return $resultOps;	// insert the createTable op at the front of the queue, along with the errored op (try it again)

			}
			else if($errorNumber == 1046 && in_array("noSelectedDB", $errorsToHandle)) {	// theres no selected database
				$callToQueue = array('op'=>"selectDatabase", "databaseName"=>$databaseName);
				return array($callToQueue, $op);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)

			}
			else if(($errorNumber == 1054 || $errorNumber == 1853321070) && in_array("column", $errorsToHandle)) {	// column doesn't exist - the 1853321070 is probably because my sql instalation is corrupted... it should be removed eventually
				return array(Sqool::getAddColumnsOp($classDefinition), $op);

			} else {
				return null;	// don't handle the error (let the system throw an error)
			}
		};

		// $op holds: array('op'=>'sql', 'sql'=>$sql, 'forward'=>$forwardObject)
		self::addOperation("sql", array(
			'generator'=>function(sqool $thisSqool, $op) {
				return array($op['sql']);
			},

			'resultHandler' => function($thisSqoolObject, $op, $results) {
				$op['forward']->exchangeArray($results);
			}
		));

		// $op holds: array('op'=>"createDatabase", "databaseName"=>databaseName);
		self::addOperation("createDatabase", array(
			'generator'=>function(sqool $this, $op) {
				return array(
					'CREATE DATABASE '.$op["databaseName"]
				);
			}
		));

		// $op holds: array('op'=>"rmDatabase", "databaseName"=>databaseName);
		self::addOperation("rmDatabase", array(
			'generator'=>function($thisSqoolObject, $op) {
				return array('drop database `'.$op['databaseName']);
			}
		));

		// $op holds: array('op'=>"selectDatabase", "databaseName"=>databaseName);
		self::addOperation("selectDatabase", array(
			'generator'=>function(sqool $this, $op) {
				return array(
					'USE '.$op["databaseName"]
				);
			},
			'errorHandler' => function($thisSqool, $op, $errorNumber, $results) {
				if($errorNumber === 1049) {	// database doesn't exist
					$createDBop = array('op'=>"createDatabase", "databaseName"=>$thisSqool->database);
					return array($createDBop, $op);	// insert the createDatabase op at the front of the queue, along with the errored op (try it again)

				} else {
					return null;
				}
			}
		));


		// returns the SQL to create a mysql table named $table
		// $op holds: array('op'=>"createTable", "table"=>$table, "columns"=>$columns);
		self::addOperation("createTable", array(
			'generator'=>function(sqool $thisSqool, $op) {

				$fieldDefinitions = array();
				foreach($op["columns"] as $field => $sqlType) {
					$fieldDefinitions[] = '`'.$field.'` '.$sqlType;	// name -space- type
				}

				return array('CREATE TABLE `'.$op["table"].'` ('.implode(',',$fieldDefinitions).') CHARACTER SET = utf8');
			}
		));


		// $op holds: array("op"=>"rmTable", "table"=>$table)
		self::addOperation("rmTable", array(
			'generator'=>function(sqool $thisSqoolObject, $op) {
				$temporary = '';
				if($op['temporary']) {
					$temporary = ' TEMPORARY';
				}

				return array
				(	'DROP'.$temporary.' TABLE '.$op["table"]
				);
			}
		));


		// $op holds: 	array
		//				(	"op"=>"save", "table"=>$table,
		//					"id"=>$id, 'idField'=>$idField
		//					"fieldsToUpdate"=> $sqoolObject->changedVariables,
		//					"type"=>$type, "className"=>$className
		//				);
		self::addOperation("save", array(
			'generator'=>function(sqool $thisSqool, $op) {	// renders the SQL for saving $setVariables onto a database object referenced by $sqoolObject
				$type = $op['type'];
				$columnUpdates = array();
				foreach($op['fieldsToUpdate'] as $name => $value) {
					$columnUpdates[] = $name.'='.$value;
				}
				return array
				(	'UPDATE '.$op["table"].' SET '.implode(",", $columnUpdates)." WHERE ".$op['idField']."=".$op["id"]
				);
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) use($genericErrorHandler) {
				return $genericErrorHandler($thisSqool, $op, $errorNumber, array("column", "noSelectedDB"), $op['type']);
			}
		));


		//$op holds: array
		//			(	'op' => "insert",
		//				"table" => $table,
		//				"vars" => array($column => $value, ...),
		//				"returnedObjectReference" => $newObject,
		//				"fieldToUpdate"=>array(
			//				'table'=>$table,
			//				'field'=>$field,
			//				'idField'=>$idField
			//				'idReference'=>$id),	// idReference is the name of an SQL variable that will be holding the id of the row to update
		//				"referenceVariable"=>$referenceVariable
		//			);
		self::addOperation("insert", array(
			// inserts a row into a table
			// the resultset of the sql includes the last_insert_ID
			'generator'=>function(sqool $thisSqool, $op) {
				$referenceVariable = $op["referenceVariable"];

				$queries = array(
					'INSERT INTO '.$op['table'].' ('.implode(",", array_keys($op["vars"])).') '.
						'VALUES ('.implode(",", array_values($op["vars"])).')',
					'SELECT '.$referenceVariable.':= LAST_INSERT_ID() as `id`'
				);

				if(array_key_exists("fieldToUpdate", $op)) {
					$queries[] =
						'UPDATE '.$op["fieldToUpdate"]["table"].
						' SET '.$op["fieldToUpdate"]["field"].'='.$referenceVariable.
						" WHERE ".$op["fieldToUpdate"]["idField"]."= ".$op["fieldToUpdate"]["idReference"];
				}

				return $queries;
			},

			'resultHandler' => function(sqool $thisSqool, $op, $results) {
				if($op["returnedObjectReference"] !== null)	{	// this is null for inserting the first row into the list table
					$op["returnedObjectReference"]->setId(intval($results[1][0]['id']));	// set the ID to the primary key of the object inserted
				}
			},

			'errorHandler' => function($thisSqool, $op, $errorNumber, $results) use($genericErrorHandler) {

				if(get_class($op["returnedObjectReference"]) === 'SqoolList') {
					$typeParameters = $op["returnedObjectReference"]->getType();
				} else {
					$typeParameters = get_class($op["returnedObjectReference"]);
				}


				return $genericErrorHandler(
					$thisSqool, $op, $errorNumber, array("database", "table", "column", "noSelectedDB"),
					sqool::getSqoolType($typeParameters),
					$thisSqool->database
				);
			}
		));


		// $op holds: 	array
					//	(	"op" => "fetch",
					//		"type"=>$type,
					//		"fields" => $fields,
					//		"cond" => $cond,
					//		"sort" => $sort,
					//		"range" => $range,
					//		"temporaryTable"=> $temporaryTableName,
					//		"relMember"=> array($object, member, submember, sub-submember, ...),	// the member to update
		// creates a temporary table, but does not handle dropping it
		self::addOperation("fetch", array(
			'generator'=>function(sqool $thisSqool, $op) {

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

				// using a temporary table here so data from that table can be used for subsequent queries
					// in the same op queue (e.g. so objects can be grabbed by their ID without waiting for the first to return to the php)
				$memberQueryPart =
					"CREATE TEMPORARY TABLE ".$op['temporaryTable']." SELECT ".implode(",", $op['fields']).
					" FROM ".$op['type']['table']($op['type']);


				if(isset($op["cond"])) {
					$whereClause = " WHERE ".$op["cond"];
				} else {
					$whereClause = "";
				}

				if(isset($op["sort"])) {
					$fields = $op['type']['fields']($op['type']);
					$sortStatements = array();
					$currentDirection = "ASC";
					foreach($op["sort"] as $x) {
						if($x['type']==='direction') {
							$currentDirection = $x['value'];
						} else if($x['type']==='member') {
							$sortStatements[] = $fields[$x['value']]['sqlName']." ".$currentDirection;
						} else {
							throw new cept('Invalid sort type: '.$x['type']);
						}
					}

					$sortClause = " ORDER BY ".implode(",", $sortStatements);
				}else {
					$sortClause = "";
				}

				// ranges is limited to a start position and an end position - but multiple pieces of a sorted list should be supported later
				if(isset($op["range"])) {
					if(count($op["range"])>2)
					{	throw new cept("range does not support more than one range yet");
					}

					$firstRange = $op["range"][0];

					$limitClause = " LIMIT ".$firstRange[0].",".($firstRange[1] - $firstRange[0] + 1);
				}else
				{	$limitClause = "";
				}

				//return $outgoingExtraInfo;

				return array
				(	$memberQueryPart.$whereClause.$sortClause.$limitClause,
					"SELECT * FROM ".$op['temporaryTable']
				);

			},

			'resultHandler' => function(sqool $thisSqool, $op, $results) {
				$resultSet = $results[1];

				$objects = array();
				foreach($resultSet as $result) {
					$objects[] = $op["type"]['phpObject']($op["type"], $thisSqool, $result);
				}

				$op["relMember"]->set($objects);
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) use($genericErrorHandler) {
				return $genericErrorHandler
				(	$thisSqool, $op, $errorNumber, array("database", "table", "column", "noSelectedDB"),
					$op['type'],
					$thisSqool->database
				);
			}
		));

		// $op holds: 	array
					//	(	"op" => "getListValues",
					//		"temporaryTable"=> ,
					// 		"type"=> ,
					//		"cond"=> ,
					//		"relMember"=>
		// creates a temporary table, but does not handle dropping it
		self::addOperation("getListValues", array(
			'generator'=>function(sqool $thisSqool, $op) {
				return array
				(	"CREATE TEMPORARY TABLE ".$op['temporaryTable']." SELECT value FROM ".$op['type']['table']($op['type']).' WHERE '.$op['cond'],
					"SELECT * FROM ".$op['temporaryTable']
				);
			},
			'resultHandler' => function(sqool $thisSqool, $op, $results) {
				$resultSet = $results[1];

				$objects = array();
				foreach($resultSet as $result) {
					$objects[] = $op["type"]['phpObject']($op["type"], $thisSqool, $result);
				}

				$op["relMember"]->set($objects);
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) use($genericErrorHandler) {
				return $genericErrorHandler
				(	$thisSqool, $op, $errorNumber, array("database", "table", "column", "noSelectedDB"),
					$op['type'],
					$thisSqool->database
				);
			}
		));


		// $op holds: array("op"=>"addColumns", "table"=>$tableName, "columnDefinitions"=>$newColumns);
		// $newColumns is an array with members of the form $memberName => $type
		self::addOperation("addColumns", array(
			'generator'=>function(sqool $thisSqool, $op) {
				$procedureName = sqool::sqoolSQLprocNameBase.$thisSqool->getNextReferenceNumber();

				$alterStatments = array();
				foreach($op["columnDefinitions"] as $fieldName => $SQLtype)
				{	$alterStatments[] = 'IF \''.$fieldName.'\' not in(select COLUMN_NAME from INFORMATION_SCHEMA.COLUMNS'
															.' where TABLE_SCHEMA=\''.$thisSqool->database.'\' and TABLE_NAME=\''.$op['table'].'\')'
										 .' THEN ALTER TABLE '.$op['table'].' ADD COLUMN '.$fieldName.' '.$SQLtype.'; END IF';
				}

				return array
				(	'CREATE PROCEDURE '.$procedureName.'() BEGIN'
						.' '.implode(';', $alterStatments).';'
					.' END',

					'CALL '.$procedureName.'()',
					'DROP PROCEDURE '.$procedureName
				);
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) {
				if($errorNumber == 1075) {	// Incorrect table definition; there can be only one auto column and it must be defined as a key
					throw new cept
					(	"SQooL attempted to create a second auto-incriment key."
						." This means you have to indicate (or change) the primary key's name for the SQooL class '".$op["class"]."' in its definition."
						." Alternatively, you can rename the offending column in the database."
						." Here is the SQL error (number 1075): "
						.$results['errorMsg']
					);
				} else {
					return null;	// don't handle the error (let the system throw an error)
				}
			}
		));

		// $op holds: array("op"=>"newListId", "referenceVariable"=>$referenceVariable, "type"=>$type);
		self::addOperation("newListId", array(
			'generator'=>function(sqool $thisSqool, $op) {
				$table = $op['type']['table']($op['type']);
				return array(
					'update '.$table.' set list_id = last_insert_id(list_id)+1 where id=0',
					'set '.$op['referenceVariable'].'=last_insert_id()'
				);
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) use($genericErrorHandler) {
				return $genericErrorHandler
				(	$thisSqool, $op, $errorNumber, array("database", "noSelectedDB", "table"),
					$op['type'],
					$thisSqool->database
				);
			}
		));

		// $op holds: array("op"=>"listAppend", "table"=>$table, "listId"=>$listId, "objectId"=>$objectId);
		self::addOperation("listAppend", array(
			'generator'=>function(sqool $thisSqool, $op)  {
				return array('insert into '.$op['table'].' (list_id, value) values ('.$op['listId'].','.$op['objectId'].')');
			},
			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) use($genericErrorHandler)  {
				return $genericErrorHandler
				(	$thisSqool, $op, $errorNumber, array("database", "noSelectedDB", "table"),
					$op['type'],
					$thisSqool->database
				);
			}
		));


		/*
		self::addOperation("", array(
			'generator'=>function(sqool $thisSqool, $op) {
			},

			'resultHandler' => function(sqool $thisSqool, $op, $results) {
			},

			'errorHandler' => function(sqool $thisSqool, $op, $errorNumber, $results) {
			}
		));
		*/





		$prims = array
		(	'bool'		=>"BOOLEAN",
			'string'	=>"LONGTEXT",
			'tinyint'	=>"TINYINT",
			'int'		=>"INT",
			'bigint'	=>"BIGINT",
			'ubigint' 	=>"BIGINT UNSIGNED",
			'float'		=>"FLOAT",
			'double'	=>"DOUBLE",
			'id'		=>'BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT'
		);
		foreach($prims as $p => $sqlType) {
			$type = array(
				'sqlType' => function() use($sqlType) {
					return $sqlType." NOT NULL";
				},
				'sqlValue' => function($thisType, sqool $thisSqool, $phpValue) {return "'".$thisSqool->escapeString($phpValue)."'";},
				'phpValue' => function($thisType, sqool $thisSqool, $sqlValue) {return $sqlValue;}
			);

			if($p === 'bool') {
				$type['sqlValue'] = function($thisType, sqool $thisSqool, $phpValue) {
					if($phpValue) return 1;
					else return 0;
				};
				$type['phpValue'] = function($thisType, sqool $thisSqool, $sqlValue) {
					return $sqlValue === '1';
				};
			}

			if($p === 'id') {
				$type['sqlType'] = function() use($sqlType) {
					return $sqlType;	// don't need the extra not null
				};
			}

			if(in_array($p, array('tinyint', 'int', 'bigint', 'id'))) {
				$type['phpValue'] = function($thisType, sqool $thisSqool, $sqlValue) { return intval($sqlValue); };
			} else if($p === 'float') {
				$type['phpValue'] = function($thisType, sqool $thisSqool, $sqlValue) { return floatval($sqlValue); };
			} else if($p === 'double') {
				$type['phpValue'] = function($thisType, sqool $thisSqool, $sqlValue) { return doubleval($sqlValue); };
			}

			self::addType($p, $type);
		}

		self::addType('list', array(
			'expects' => 'typeParameters',	// indicates that this type expects the key 'typeParameters' to be set on it

			'fields'=> function($thisType) {
				return array
				(	"id" => array("sqlName"=>'id', "typeParameters"=> array('id')),
					"list_id" => array("sqlName"=>'list_id', "typeParameters"=> array('ubigint')),
					"value" => array
					(	"sqlName"=>'value',
						"typeParameters"=> array_slice($thisType['typeParameters'], 1)
					)
				);
			},

			'table'=> function($thisType) {
				$tableName = sqool::sqoolSQLtableNameBase;
				foreach($thisType['typeParameters'] as $t) {
					$tableName.="_".$t;
				}
				return $tableName;
			},

			'sqlType'=>function($thisType) { return 'BIGINT UNSIGNED'; },
			'sqlValue' => function($thisType, sqool $thisSqool, $list, &$ops) {
				$ops = array();
				if($list->id !== null) {
					if( $list->databaseRootObject !== null && $list->databaseRootObject !== $thisSqool) {
						throw new cept("Trying to get the sql value for an object stored in different database. Please create a copy of the object first.");
					}
					return $list->id;
				} else {
					if(count($list) === 0) {	// when the list hasn't been created yet (no id) and it's empty, null is more efficient
						return 'null';

					} else {
						if($list->sqlIdVariable === null) {
							$sqlIdVariable = null;	// output variable
							$ops = $thisSqool->createInsertOp($list, $sqlIdVariable);
							$list->sqlIdVariable = $sqlIdVariable;
						}

						return $list->sqlIdVariable;
					}
				}
			},
			'phpValue' => function($thisType, sqool $thisSqool, $id) {
				$newList = new SqoolList($thisSqool, $thisType['typeParameters']);
				$newList->setId($id);
				return $newList;
			},

			'phpObject' => function($thisType, sqool $thisSqool, $values) {
				$listObject = new SqoolList($thisSqool, $thisType['typeParameters']);
				$listObject->setId($values['list_id']);	// set the list id (not the entry id)

				$fields = $thisType['fields']($thisType);
				foreach($values as $fieldName => $v) {
					$member = $thisType['members'][$fieldName];
					$fieldInfo = $fields[$member];
					$fieldType = $thisSqool->getSqoolType($fieldInfo['typeParameters']);

					$listObject[] = $fieldType['phpValue']($fieldType, $thisSqool, $v);
				}

				return $listObject;
			},

			'updateOps' => function($sqoolType, SqoolList $listObject) {
				if($listObject->id !== null) {	// only create a save op if the object is already in the database
					return $listObject->createSaveOp();
				} else {
					return array();	// otherwise assume it will be inserted somewhere else, and don't attempt to save it
				}
			}


		));

	}



    // **** private instance methods  ****



}
sqool::initializeSqoolClass();


// represents a single type of sqool object (ie it represents a table)
abstract class SqoolObject {

		/**** private members****/

	// private static variables
	/*not*/public $databaseRootObject = null;	// the object that represents the database as a whole
	public $setVariables = array();		// variables that have been set, waiting to be 'save'd to the database
	public $changedVariables = array();	// variables that have been changed
	/*not*/public $sqlIdVariable = null;	// (intended to be private) mysql variable that will hold the id if it hasn't been inserted yet


		/***********************   public functions  *********************/

	// Instance functions

    // to use the '__set' magic function in child classes, use ___set (with three underscores instead of two)
	public function __set($name, $value) {

		$type = $this->getType();

		if(is_array($value)) {
			$fields = $type['fields']($type);
			$newList = new SqoolList($this->databaseRootObject, $fields[$name]['typeParameters']);
			$newList->exchangeArray($value);
			$value = $newList;
		}

		if( is_a($value, get_class()) && $this->databaseRootObject !== null && ! $value->sameDbRoot($this->databaseRootObject)) {
			if($value->databaseRootObject === null) {
				$value->databaseRootObject = $this->databaseRootObject;	// set databaseRootObject on object being set on $this if it doesn't have one
			} else {
				throw new cept("Trying to set one object on an object that's stored in different database. Please create a copy of the object first.");
			}
		}


		//$type['fields'][$name]['validate']($value);

		/*
		$t = $this->getType($name);
		if($t[0] === 'listType' && gettype($value) !== "array") {
			throw new cept("Attempting to set array field (".$name.") as a".gettype($value));

		} else if(in_array($t[1], array_keys(self::primitives())) && in_array(gettype($value), array("object", "array")) || $value === null) {
			throw new cept("Attempting to set ".print_r($t[1],true)." field (".$name.") as a ".gettype($value));

		} else if($value !== null && gettype($value) !== "object") {
			throw new cept("Attempting to set object field (".$name.") as a ".gettype($value));
		}
		*/


		if($name == $type['idMember']) {
			throw new cept("You can't manually set the object's id. Sorry.");

		} else if( ! array_key_exists($name, $type['fields']($type)) ) {
			throw new cept("Object doesn't contain the member '".$name."'.");
		}

		$this->setVariables[$name] = $value;
		$this->changedVariables[] = $name;
	}

    // to use the '__get' magic function in child classes, use ___get (with three underscores instead of two)
	public function __get($name) {
		if(array_key_exists($name, $this->setVariables)) {
			return $this->setVariables[$name];

		} else {
			$type = $this->getType();
			if( array_key_exists($name, $type['fields']($type))) { // if sqool class has the member $name (but it isn't set)
				throw new cept("Attempted to get the member '".$name."', but it has not been fetched yet.");
			} else {
				throw new cept("Object doesn't contain the member '".$name."'.");
			}
		}
	}

	public function save() {
		$this->requireID("save");

		$ops = $this->createSaveOp();
		if(count($ops) > 0) {
			$this->databaseRootObject->addAllToQueue($ops);	// insert the calls into the callQueue
		}
	}

	/*private*/ public function createSaveOp() {
		$class = get_called_class();
		$type = $this->getType();
		$idMember = $type['idMember'];
		$fields = $type['fields']($type);

		$fieldsToUpdate = array();
		$ops = array();
		foreach($this->changedVariables as $v) {
			$memberType = $this->databaseRootObject->getSqoolType($fields[$v]['typeParameters']);
			$otherOps = null;	// output variable
			$fieldsToUpdate[$v] = $memberType['sqlValue']($memberType, $this->databaseRootObject, $this->setVariables[$v], $otherOps);

			if($otherOps !== null )	$ops = array_merge($ops, $otherOps);
		}

		// create update ops for members that have members that have changed as well
		foreach($fields as $fieldName=>$fieldInfo) {
			$fieldType = $this->databaseRootObject->getSqoolType($fieldInfo['typeParameters']);
			if(array_key_exists($fieldName, $this->setVariables) && $this->setVariables[$fieldName] !== null
				&& array_key_exists('updateOps', $fieldType)) {

				$updateOps = $fieldType['updateOps']($fieldType, $this->setVariables[$fieldName]);
				$ops = array_merge($ops, $updateOps);
			}
		}

		if(count($fieldsToUpdate) > 0) {
			$ops[] = array
			(	"op"=>"save", "table"=>$type['table']($type),
				"id"=>$this->setVariables[$idMember], 'idField'=> $fields[$idMember]['sqlName'],
				'fieldsToUpdate'=>$fieldsToUpdate,
				"type"=>$type, 'className'=>$class
			);
		}

		return $ops;
	}


	// fetches an object's members or their sub-members
	/*
		object->get()					// fetches all the members of the object (but does not fetch members of object-members)
		// OR
		object->get("<memberList>");	// fetches specific members of the object (and potentially their sub-members or parts).
										// Refer to sqool::get for how to format <memberList>
	*/
	public function get() {
		$args = func_get_args();
		$memberListResult = sqool::parseFetchMemberList($args);
		$thisType = $this->getType();
		$idMember = $thisType['idMember'];

		if( ! array_key_exists($idMember, $this->setVariables)) {
			throw new cept("Attempting to get members from an object without an id");
		}

		$parser = new SqoolFetchParser($idMember.'='.$this->$idMember);
		$parser->condExpression($condition);

		$dataControl = array('cond'=>$condition);
		if(count($memberListResult) > 0) {
			$members = array_keys($memberListResult);
			$dataControl['members'] = $memberListResult;	// only set the key 'members' if there are members explicitly selected

		} else {
			$members = array_keys($thisType['fields']($thisType));
		}

		$this->databaseRootObject->fetchForSingleObject($this, $thisType, $members, $dataControl);
	}


	// interface functions

	abstract public function sclass();



		/***********************  private functions  *********************/

	private function getType() {
		return sqool::getSqoolType(get_called_class());
	}


	// the following three are only meant to be used by the Sqool class

	function setUpSqoolObject(sqool $root=null) {
		$this->databaseRootObject = $root;
		$this->changedVariables = array();	// clear changed variables
		$type = $this->getType();
		unset($this->setVariables[$type['idMember']]);
	}

	function getChangedVariables() {
		return $this->changedVariables;
	}

	function getSetVariables() {
		return $this->setVariables;
	}

	function hasId() {
		$type = $this->getType();
		return array_key_exists($type['idMember'], $this->setVariables);
	}

	function requireID($actionText) {
		if( ! $this->hasId()) {
			throw new cept("Attempted to ".$actionText." an object that isn't in a database yet. (Use sqool::insert to insert an object into a database).");
		}
	}

	function sameDbRoot(sqool $db) {
		return $this->databaseRootObject !== null && $this->databaseRootObject->same($db);
	}

	function copy() {
		$newObject = clone $this;
		$newObject->setUpSqoolObject();
		return $newObject;
	}

	// $values should be an array of the form array(array('member'=>$memberName, 'value'=>$value), ...)
	function setMembers($thisSqool, $values) {
		$this->setUpSqoolObject($thisSqool);

		foreach($values as $name => $v) {
			$this->setVariables[$name]=$v;
		}
	}

	function setId($id) {
		$type = $this->getType();

		$this->setVariables[$type['idMember']] = $id;
		$this->sqlIdVariable = null;
	}
}

// used for future arrays needed by the sqool::sql and sqool::get
// count() works with objects of this class
class SqoolList extends ArrayObject {

	/**** private members****/

	private $id = null;
	/*not*/public $sqlIdVariable = null;	// (intended to be private) mysql variable that will hold the id if it hasn't been inserted yet
	/*not*/public $databaseRootObject;
	/*temporarily*/public $typeParameters;


	/***********************  public functions  *********************/

	public function __get($name) {
		if($name === 'id') {
			return $this->id;
		} else {
			throw new cept("Object doesn't contain the member '".$name."'.");
		}
	}

	public function toArray() {
		return (array)$this;
	}

	function count() {
		return parent::count();
	}

	public function offsetGet($index) {
		return parent::offsetGet($index);
	}
	public function offsetSet ($index, $newval) {
		return parent::offsetSet($index, $newval);
	}
	public function offsetUnset($index) {
		return parent::offsetUnset($index);
	}

	/***********************  private functions  *********************/

	function __construct($sqoolConection, $typeParameters) {
		$this->databaseRootObject = $sqoolConection;
		$this->typeParameters = $typeParameters;
	}

	function setId($id) {
		$this->id = $id;
		$this->sqlIdVariable = null;
	}

	function getType() {
		return sqool::getSqoolType(array_merge(array('list'), $this->typeParameters));
	}

	/* exchangeArray can set the values inside this list object */
}

// used for describing a future member of an object
class SqoolRelativeMember {
	public $object;
	public $memberPath;

	function __construct() {
		$args = func_get_args();
		$this->object = $args[0];
		$this->memberPath = array_slice($args, 1);
	}

	// returns a new SqoolRelativeMember with another member in the path
	function subMember($newPathMember) {
		$newObject = new self($this->object);
		$newObject->memberPath = $this->memberPath;
		$newObject->memberPath[] = $newPathMember;
		return $newObject;
	}

	// sets the (now existant) object-member to $value
	function set($value) {
		$objects = $this->getObjectsToSetFromPath($this->object, $this->memberPath);

		if($objects === null) {
			if(get_class($this->object) === 'SqoolList') {
				$this->object->exchangeArray($value);
			} else if(is_a($this->object, 'SqoolObject')) {
				$listSize = count($value);
				if($listSize === 0) {
					throw new cept("Didn't find an object for getu call");
				} else if($listSize > 1) {
					throw new cept("sqool::getu or SqoolObject::get selected more than one object from the database");
				}

				$this->object->setMembers($this->object->databaseRootObject, $value[0]->getSetVariables());
			} else {
				throw new cept('The SqoolRelativeMember points to something that\'s not a SqoolList or SqoolObject: '.gettype($this->object));
			}
		} else {
			$lastPathmember = $this->memberPath[count($this->memberPath)-1];	// gets the last path member
			foreach($objects as $o) {
				if($o->$lastPathmember != null) {
					foreach($value as $v) {
						$type = sqool::getSqoolType(get_class($v));
						$idMember = $type['idMember'];
						if($o->$lastPathmember->$idMember === $v->setVariables[$idMember]) {
							$o->setVariables[$lastPathmember] = $v;
						}
					}
				}
			}
		}

	}

	// returns the second to last member in the path
	// returns null if there is no path
	private function getObjectsToSetFromPath($object, $path) {
		$pathCount = count($path);
		if($pathCount === 0) {
			return null;
		}

		$sqoolObject = is_a($object, 'SqoolObject');
		$sqoolList = get_class($object) === 'SqoolList';
		if( !$sqoolObject && !$sqoolList) throw new cept('The SqoolRelativeMember points to something that\'s not a SqoolList or SqoolObject: '.gettype($this->object));

		if($pathCount === 1) {
			if($sqoolObject) 		return array($object);
			else /*if($sqoolList)*/ return $object->toArray();
		} else {
			if($sqoolObject) {
				$nextMemberInPath = $path[0];
				if(array_key_exists($nextMemberInPath, $object->setVariables) && $object->$nextMemberInPath !== null) {
					return $this->getObjectsToSetFromPath($object->$nextMemberInPath, array_slice($path, 1));
				} else {
					return array();	// no matching member
				}

			} else {
				$resultObjects = array();
				foreach($object->toArray() as $item) {
					$resultObjects = array_merge($resultObjects, $this->getObjectsToSetFromPath($item, $path));
				}
				return $resultObjects;
			}
		}
	}
}

// emtpy class used for class casting
class SqoolShapeShifter {

}
