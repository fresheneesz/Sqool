<?php
require_once('../sqoolUnitTester.php');
require_once('SQooL-0.8.php');
cept::$html = true;
SqoolParser::$html = true;

/*	tests to do:
	* test adding a column to the class def (and using it)
		* test adding a list column - since this has special needs (lists are initialized as null, and when they are first used an ID needs to be given for the list the proper tables may need to be created)
	* test syntax errors in fetch
    * test data inconsistency errors (what if the list table is dropped, what if an object has a list id that doesn't exist in the lists table)
 	* make sure all the functions in a SqoolObject are private, and can't interfere with adding sqool members
 	* test adding a raw list into the database - it should cause an exception (raw lists can't be added to the db)
 * 	* test lists of lists
*/



class nonExistantClass extends SqoolObject
{	function sclass()
	{
	}
}

class objWithPrimitives extends SqoolObject
{	function sclass()
	{	return
		'bool: 		boolTest
		 string: 	stringTest1
		 string: 	stringTest2
		 string: 	stringTest3
		 tinyint:	tinyintTest
		 int:		inttest
		 bigint:	biginttest
		 float:		floattest
		 double:	doubletest
		';
	}
}

class objB extends SqoolObject
{	function sclass()
	{	return
		'objWithPrimitives:	bob_ject
		 int:				intVar
		 objB: 				child
		 condor: 			j
		';
	}
}

class condor extends SqoolObject
{	function sclass()
	{	return
		'int: 		x
		 string: 	y
		';
	}
}

class comment extends SqoolObject
{	function sclass()
	{	return
		'string: 	name
		 string: 	comment
		 int:		time
		';
	}
}

class listContainer extends SqoolObject
{	function sclass()
	{	return
		'int: 				 x
		 list string: 		 y
		 list listContainer: z
		';
	}
}
class metamorph extends SqoolObject
{	public static $sclass = "";
	function sclass()
	{	return self::$sclass;
	}

	public function clear()
	{	$sqool = new ReflectionClass('sqool');
		$types = $sqool->getProperty('types');
		$types->setAccessible(true);
		$types->setValue(array());

		$SqoolExtensionSystem = new ReflectionClass('SqoolExtensionSystem');
		$operations = $SqoolExtensionSystem->getProperty('operations');
		$operations->setAccessible(true);
		$operations->setValue(array());

		sqool::initializeSqoolClass();
	}
}

function gotCeptFromInvalidAccess($object, $member)
{	$gotException = false;
	try
	{	$object->$member;
	}catch(cept $e)
	{	$gotException = true;
	}

	return $gotException;
}

function rmDatabase(sqool $db, $database) {
	$db->sql("drop database ".$database);
}

function initialSetup() {
	$db = sqool::connect("root", "", "garboNonExistant");
	$db->debug(false);
	try { rmDatabase($db, "garboNonExistant"); } catch(Exception $e) {}
	try { rmDatabase($db, "garboNonExistant2"); } catch(Exception $e) {}
	try { rmDatabase($db, "nonexistant"); } catch(Exception $e) {}
	try { rmDatabase($db, "testdb"); } catch(Exception $e) {}
}

function setupTestDatabase() {
	sqool::debug(false);

	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
	rmDatabase($a, "garboNonExistant");

	$arf = new objWithPrimitives();
	$arf->boolTest = true;
	$arf->stringTest1 = "pices of a drem";
	$arf->stringTest2 = "Shanks you";
	$arf->stringTest3 = "TRY\" '' ~~";
	$arf->tinyintTest = 100;
	$arf->inttest = 500;
	$arf->biginttest = 600;
	$arf->floattest = 4.435;
	$arf->doubletest = 5.657;

	$arf2 = new objWithPrimitives();
	$arf2->boolTest = false;
	$arf2->stringTest1 = "drankFu";
	$arf2->stringTest2 = "lets go";
	$arf2->stringTest3 = "Try2\" '' ~~";
	$arf2->tinyintTest = 33;
	$arf2->inttest = 44;
	$arf2->biginttest = 555;
	$arf2->floattest = 5.6665;
	$arf2->doubletest = 11.890890;

	$arf3 = new objWithPrimitives();
	$arf3->boolTest = false;
	$arf3->stringTest1 = "dtaFoo";
	$arf3->stringTest2 = "lets show em up";
	$arf3->stringTest3 = "bastazx2\" '' ~~";
	$arf3->tinyintTest = 77;
	$arf3->inttest = 88;
	$arf3->biginttest = 1111;
	$arf3->floattest = 2436.2345;
	$arf3->doubletest = 2345325.43;

	$a->insert($arf);
	$a->insert($arf2);
	$a->insert($arf3);

	sqool::debug(true);

	return $a;
}

function create2Bs($a)
{	sqool::debug(false);

	$egg2 = new objB();
	$egg2->bob_ject = new objWithPrimitives();
	$egg2->bob_ject->boolTest = true;
	$egg2->bob_ject->stringTest3 = "boooosh";
	$insertedEgg = $a->insert($egg2);

	$insertedEgg->get();
	$insertedEgg->bob_ject->get();

	$egg3 = new objB();			// (this will be objB2)
	$egg3->child = new objB();	// objB3
	$egg3->child->intVar = 5;
	$egg3->intVar = 9;
	$insertedEgg = $a->insert($egg3);

	$egg3 = new objB();				// (this will be objB4)
	$egg3->child = new objB();		// objB5
	$egg3->child->child = new objB();	// objB6
	$insertedEgg = $a->insert($egg3);
	sqool::debug(true);
}

function createListContainers()
{	/*$a = setupTestDatabase();
	sqool::debug(false);

	$lo1 = new listContainer();
	$lo1->x = 71;
	$lo1->y = array();
	$lo1->z = array();

	$lo2 = new listContainer();
	$lo2->x = 72;
	$lo2->y = array("hello");
	$lo2->z = array($lo1);

	$lo3 = new listContainer();
	$lo3->x = 73;
	$lo3->y = array("hello", "wunka", "skeedo");
	$lo3->z = array($lo1, $lo2);


	$lo4 = new listContainer();
	$lo4->x = 74;
	$lo4->y = array("fusikins", "doolettuce");
	$lo4->z = array($lo1, $lo2, $lo3);

	$a->insert($lo1);
	$a->insert($lo2);
	$a->insert($lo3);
	$a->insert($lo4);

	sqool::debug(true);

	return $a;*/
}

// returns true if the names in array $areIn are defined, and the names in $areNotIn are not
function defined_in_table($db, $table, $areIn, $areNotIn)
{	$showColumnsResult = $db->sql("SHOW COLUMNS FROM ".$table);

	$columns = array();
	foreach($showColumnsResult[0] as $DBcol)
	{	$columns[] = $DBcol['Field'];
	}

	foreach($areIn as $is)
	{	if(false === in_array($is, $columns))
		{	return false;
		}
	}
	foreach($areNotIn as $isnt)
	{	if(true === in_array($isnt, $columns))
		{	return false;
		}
	}

	// else
	return true;
}

class SqoolTests extends SqoolUTester {

	// not actually a test of anything
	function turnDebuggingOn() {
		sqool::debug(true);
		sqool::debugHandler(null);

		/*$a = Sqool::connect("root", "", "garboNonExistant");

		$proc = //'delimiter \'$$\'\n'.
		'CREATE PROCEDURE testproc() BEGIN INSERT INTO a set b=8; END select * from a;';

		$result = $a->sql($proc);
		var_dump($result);
		self::assert(false);*/
	}

    function testAddType() {
        // test what happens when an object is named 'int' or 'list' or whatever just to make sure the proper error is thrown
    }


	function testFetchOnNonExistantDB() {

		$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
        $arf = new objWithPrimitives();
		$a->insert($arf);   // insert an object

        // build a list of databases
        $result = $a->sql("show databases");
        $existingDatabases = array();
        foreach($result[0] as $i)
        {   $existingDatabases[] = $i['Database'];
        }
        self::assert(in_array("garbononexistant", $existingDatabases));    // make sure database exists

        print_r($existingDatabases);

		rmDatabase($a, "garboNonExistant");

        // build the list of databases again
        $result = $a->sql("show databases");
        $existingDatabases = array();
        foreach($result[0] as $i) {
            $existingDatabases[] = $i['Database'];
        }
        self::assert( ! in_array("garbononexistant", $existingDatabases)); // make sure database was removed
        print_r($existingDatabases);
        //if($result)
        //{   printf("Select returned %d rows.\n", $result->num_rows);
        //    print_r($result);
        //} else
        //{   printf("Error: %s\n", $con->error);
        //}



		$result = $a->get("nonExistantClass");

		self::assert(is_array($result));
		self::assert(count($result) === 0);
	}

	function testInsertDefaults() {
		$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		rmDatabase($a, "garboNonExistant");

		$arf = new objWithPrimitives();
		$arf2 = $a->insert($arf);

		$objects = $a->get("objWithPrimitives");

		self::assert(count($objects) === 1);
		self::assert($objects[0]->boolTest === false);
		self::assert($objects[0]->stringTest1 === "");
		self::assert($objects[0]->stringTest2 === "");
		self::assert($objects[0]->stringTest3 === "");
		self::assert($objects[0]->tinyintTest === 0);
		self::assert($objects[0]->inttest === 0);
		self::assert($objects[0]->biginttest === 0);
		self::assert($objects[0]->floattest === floatval(0));
		self::assert($objects[0]->doubletest === floatval(0));
	}

	function testInsert() {
		$a = setupTestDatabase();


		$objects = $a->get("objWithPrimitives");

		self::assert(count($objects) === 3);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);
	}


	/*
	function testInsertBadValues()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		rmDatabase($a);

		$arf = new objWithPrimitives();
		$arf->boolTest = 3;
		$arf->stringTest1 = 4.3;
		$arf->stringTest2 = array();
		$arf->stringTest3 = 39;
		$arf->tinyintTest = 500;
		$arf->inttest = "momo";
		$arf->biginttest = 3.34;
		$arf->floattest = "hellio";
		$arf->doubletest = "supershak";

		$arf2 = $a->insert($arf);

		$a->get(array
		(	"objWithPrimitives"
		));

		self::assert(count($a->objWithPrimitives) === 1);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
	}
	*/

	/*
	function testSave()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		rmDatabase($a, "garboNonExistant");

		$arf = new objWithPrimitives();
		$a->insert($arf);

		$arf->boolTest = true;
		$arf->stringTest1 = "pices of a drem";
		$arf->stringTest2 = "Shanks you";
		$arf->stringTest3 = "TRY\" '' ~~";
		$arf->tinyintTest = 100;
		$arf->inttest = 500;
		$arf->biginttest = 600;
		$arf->floattest = 4.435;
		$arf->doubletest = 5.657;

		$arf->save();

		$arfAgain = $a->insert(new objWithPrimitives());

		$arfAgain->biginttest = 1;
		$arfAgain->floattest = 1;
		$arfAgain->doubletest = 1;
		$arfAgain->save();

		$objects = $a->get("objWithPrimitives");


		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);

		self::assert($objects[1]->biginttest === 1);
		self::assert($objects[1]->floattest === 1.0);
		self::assert($objects[1]->doubletest === 1.0);


	}

	function testCommentsThing()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		rmDatabase($a, "garboNonExistant");

		$a->get("comment");

	}

	function testFetch1()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)

		rmDatabase($a, "garboNonExistant");

		$arf = new objWithPrimitives();
		$arf->boolTest = true;
		$arf->stringTest1 = "pices of a drem";
		$arf->stringTest2 = "Shanks you";
		$arf->stringTest3 = "TRY\" '' ~~";
		$arf->tinyintTest = 100;
		$arf->inttest = 500;
		$arf->biginttest = 600;
		$arf->floattest = 4.435;
		$arf->doubletest = 5.657;

		$a->insert($arf);
		self::expectCept(function()use($a, $arf) {$a->insert($arf);});
		$a->insert($arf->copy());

		$com = new comment();
		$com->name = "your mom";

		$a->insert($com);
		$a->insert($com->copy());
		$a->insert($com->copy());

		$objects = $a->get("objWithPrimitives");
		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);

		$objects = $a->get("objWithPrimitives");
		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);

		$objects = $a->get("objWithPrimitives");
		$comments = $a->get("comment");
		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);
		self::assert(count($comments) === 3);
		self::assert($comments[0]->name === "your mom");

        $a = sqool::connect("root", "", "garboNonExistant");	// reset $a

		$objects = $a->get("objWithPrimitives[]");
		$comments = $a->get("comment[]");
		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest1 === "pices of a drem");
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert($objects[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($objects[0]->tinyintTest === 100);
		self::assert($objects[0]->inttest === 500);
		self::assert($objects[0]->biginttest === 600);
		self::assert($objects[0]->floattest === 4.435);
		self::assert($objects[0]->doubletest === 5.657);
		self::assert(count($comments) === 3);
		self::assert($comments[0]->name === "your mom");

		$objects = $a->get("objWithPrimitives[members: boolTest]");
		$comments = $a->get("comment[]");
		self::assert(count($objects) === 2, "So apparently ".count($objects)." doesn't pass for 2 these days");
		self::assert($objects[0]->boolTest === true);
		self::assert(gotCeptFromInvalidAccess($objects[0], "stringTest1"));

		self::assert(count($comments) === 3);
		self::assert($comments[0]->name === "your mom");


		$objects = $a->get("objWithPrimitives[members: boolTest stringTest2]");
		$comments = $a->get("comment[members:name]");
		self::assert(count($objects) === 2);
		self::assert($objects[0]->boolTest === true);
		self::assert($objects[0]->stringTest2 === "Shanks you");
		self::assert(gotCeptFromInvalidAccess($objects[0], "stringTest1"));

		self::assert(count($comments) === 3);
		self::assert($comments[0]->name === "your mom");
		self::assert(gotCeptFromInvalidAccess($comments[0], "time"));
	}


	function testFetch2()
	{	$a = setupTestDatabase();
		$a->debug(true);

		$booObj = $a->getu("objWithPrimitives[cond: id=1 members:]");	// fetches no members
		$booObj->get();

		self::assert($booObj->boolTest === true);
		self::assert($booObj->stringTest1 === "pices of a drem");
		self::assert($booObj->stringTest2 === "Shanks you");
		self::assert($booObj->stringTest3 === "TRY\" '' ~~");
		self::assert($booObj->tinyintTest === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);

		$booObj = $a->get("objWithPrimitives");
		$booObj = $booObj[0];	// testing that the first objWithPrimitives object is the one selected above (with id 1)
		$booObj->get();

		self::assert($booObj->boolTest === true);
		self::assert($booObj->stringTest1 === "pices of a drem");
		self::assert($booObj->stringTest2 === "Shanks you");
		self::assert($booObj->stringTest3 === "TRY\" '' ~~");
		self::assert($booObj->tinyintTest === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);

		$booObj = $a->getu("objWithPrimitives[cond: id=2]");
        $booObj->get();

		self::assert($booObj->boolTest === false);
		self::assert($booObj->stringTest1 === "drankFu");
		self::assert($booObj->stringTest2 === "lets go");
		self::assert($booObj->stringTest3 === "Try2\" '' ~~");
		self::assert($booObj->tinyintTest === 33);
		self::assert($booObj->inttest === 44);
		self::assert($booObj->biginttest === 555);
		self::assert($booObj->floattest === 5.6665);
		self::assert($booObj->doubletest === 11.890890);


		$booObj = $a->getu("objWithPrimitives[cond: id=1]");
        $booObj->get("boolTest");

		self::assert($booObj->boolTest === true);



		// sort test


		$objects = $a->get("objWithPrimitives[range: 1:1]");

		self::assert(count($objects) === 1);

		self::assert($objects[0]->boolTest === false);
		self::assert($objects[0]->stringTest1 === "drankFu");
		self::assert($objects[0]->stringTest2 === "lets go");
		self::assert($objects[0]->stringTest3 === "Try2\" '' ~~");
		self::assert($objects[0]->tinyintTest === 33);
		self::assert($objects[0]->inttest === 44);
		self::assert($objects[0]->biginttest === 555);
		self::assert($objects[0]->floattest === 5.6665);
		self::assert($objects[0]->doubletest === 11.890890);


		$objects = $a->get("objWithPrimitives[sort asc: inttest]");

		self::assert(count($objects) === 3);
		self::assert($objects[0]->inttest < $objects[1]->inttest);
		self::assert($objects[1]->inttest < $objects[2]->inttest);

		$a->get("objWithPrimitives[sort asc: inttest floattest]");

		// cond test

		$objects = $a->get("objWithPrimitives[where: tinyintTest =",33,"]");
		self::assert(count($objects) === 1);
		self::assert($objects[0]->tinyintTest === 33);

		$objects = $a->get("objWithPrimitives[cond: tinyintTest >",33,"&& inttest <", 400,"]");
		self::assert(count($objects) === 1);
		self::assert($objects[0]->tinyintTest === 77);
		self::assert($objects[0]->inttest === 88);

	}

	function testObjects()
	{	$a = setupTestDatabase();
		$a->debug(true);

		$egg2 = new objB();
		$newBobject = new objWithPrimitives();
		$egg2->bob_ject = $newBobject;
		$egg2->bob_ject->boolTest = true;
		$egg2->bob_ject->stringTest3 = "boooosh";
		self::expectCept(function()use($egg2) {$id = $egg2->id;});
		self::expectCept(function()use($egg2) {$id = $egg2->bob_ject->id;});
		self::expectCept(function()use($newBobject) {$id = $newBobject->id;});
		$insertedEgg = $a->insert($egg2);

		self::assert($insertedEgg === $egg2);	// the input object should be the same object as the returned

		self::assert($insertedEgg->id === 1);
		self::assert($insertedEgg->bob_ject->id === 4);
		self::assert($newBobject->id === 4);


		$a2 = sqool::connect("root", "", "garboNonExistant2");	// connect to a database that doesn't exist (connects on localhost)
		rmDatabase($a2, "garboNonExistant2");
		$insertedEgg2 = $a2->insert($egg2->copy());
		self::assert($egg2->id === 1);
		self::assert($insertedEgg2->id === 1);
		self::assert($insertedEgg2->bob_ject->id === 4);
		self::assert($newBobject->id === 4);

		// back to the first database (garboNonExistant)
		$insertedEgg->get();
		$insertedEgg->bob_ject->get();
		self::assert($insertedEgg->bob_ject->boolTest === true);
		self::assert($insertedEgg->bob_ject->stringTest3 === "boooosh");


		$egg3 = new objB();	// (this will be objB2)
		$egg3->child = new objB();
		$egg3->child->intVar = 5;
		$egg3->intVar = 9;
		$insertedEgg = $a->insert($egg3);

		$insertedEgg->get();
		$insertedEgg->child->get();
		self::assert($insertedEgg->intVar === 9);
		self::assert($insertedEgg->child->intVar === 5);

		$boo1 = $a->get("objWithPrimitives[cond: id=1]");

		$egg3 = new objB();
		$egg3->bob_ject = $boo1[0];
		$egg3->intVar = 9;
		$insertedEgg = $a->insert($egg3);

		$insertedEgg->get();
		$insertedEgg->bob_ject->get();
		self::assert($insertedEgg->intVar === 9);
		self::assert($insertedEgg->bob_ject->boolTest === true);
		self::assert($insertedEgg->bob_ject->stringTest1 === "pices of a drem");
		self::assert($insertedEgg->bob_ject->stringTest2 === "Shanks you");
		self::assert($insertedEgg->bob_ject->stringTest3 === "TRY\" '' ~~");
		self::assert($insertedEgg->bob_ject->tinyintTest === 100);
		self::assert($insertedEgg->bob_ject->inttest === 500);
		self::assert($insertedEgg->bob_ject->biginttest === 600);
		self::assert($insertedEgg->bob_ject->floattest === 4.435);
		self::assert($insertedEgg->bob_ject->doubletest === 5.657);

		$insertedEgg->bob_ject->stringTest2 = "bizarroooo";
		$insertedEgg->bob_ject->save();
		$boo1 = $a->get("objWithPrimitives[cond: id=".$insertedEgg->bob_ject->id."]");
        $boo1 = $boo1[0];
		$boo1->get();
		self::assert($boo1->stringTest2 === "bizarroooo");


		$egg3 = new objB();
		$egg3->bob_ject = $boo1;
		$egg3->bob_ject->boolTest = true;
		$egg3->intVar = 9;
		$insertedEgg = $a->insert($egg3);

		echo "saving<br>";

		$insertedEgg->bob_ject = $boo1;
		$boo1->stringTest2 = "alright then";
		$insertedEgg->save();

		$insertedEgg->get();
		$insertedEgg->bob_ject->get();
		self::assert($insertedEgg->intVar === 9);
		self::assert($insertedEgg->bob_ject->boolTest === true);
		self::assert($insertedEgg->bob_ject->stringTest2 === "alright then");

		$insertedEgg->bob_ject = new objWithPrimitives();
		$insertedEgg->bob_ject->stringTest2 = "whatever dude";
		$insertedEgg->j = new condor();
		$insertedEgg->save();

		$insertedEgg->get();
		$insertedEgg->bob_ject->get();
		self::assert($insertedEgg->bob_ject->stringTest2 === "whatever dude");

		$insertedEgg->bob_ject->stringTest3 = "new string";
		$insertedEgg->save();

		$insertedEgg->get('bob_ject');
		self::assert($insertedEgg->bob_ject->stringTest3 === "new string");
	}

	function testFetchObjects() {
		$a = setupTestDatabase();
		$a->debug(true);
		create2Bs($a);

		echo "fetching<br>";

		$objB2 = $a->getu("objB[cond: id=2]");
		self::assert($objB2->intVar === 5);
		self::assert($objB2->child === null);

		$objB3 = $a->get("objB[cond: id=3]");
		$objB3 = $objB3[0];
        $objB3->get('
			intVar
			child
		');

		self::assert($objB3->intVar === 9);
		self::assert($objB3->child->intVar === 5);

		// The following selects all objB objects (should be 6 or so of them) and attempts to get the object-member child from each of them
		$objBs = $a->get("objB[members:child]");

		self::assert(count($objBs) == 6);
			self::assert($objBs[0]->id === 1);
			self::expectCept(function()use($objBs) {$results = $objBs[0]->intVar;});	// intVar should not be available (for any of the objects in the list)
				self::assert($objBs[0]->child === null);
			self::assert($objBs[1]->id === 2);
				self::assert($objBs[1]->child === null);
			self::assert($objBs[2]->id === 3);
				self::assert($objBs[2]->child->id === 2);
				self::assert(get_class($objBs[2]->child) === "objB");
					self::assert($objBs[2]->child->intVar === 5);
					self::assert($objBs[2]->child->child === null);
			self::assert($objBs[3]->id === 4);
				self::assert($objBs[3]->child === null);
			self::assert($objBs[4]->id === 5);
				self::assert($objBs[4]->child->id == 4);
				self::assert(get_class($objBs[4]->child) === "objB");
					self::assert($objBs[4]->child->intVar === 0);
					self::assert($objBs[4]->child->child === null);
			self::assert($objBs[5]->id === 6);
				self::assert($objBs[5]->child->id == 5);
				self::assert(get_class($objBs[5]->child) === "objB");
					self::assert(get_class($objBs[5]->child->child) === "objB");
					self::assert($objBs[5]->child->child->id === 4);
					self::expectCept(function()use($objBs) {$results = $objBs[5]->child->child->intVar;});	// intVar should not be available
					self::expectCept(function()use($objBs) {$results = $objBs[5]->child->child->child;});	// child should not be available


		// The following selects all objB objects (should be 6 or so of them) and attempts to get a couple different object-members from each of them
		$objBs = $a->get("objB[members: bob_ject child intVar]");

		self::assert(count($objBs) == 6);

			self::assert($objBs[0]->id === 1);
				self::assert($objBs[0]->intVar === 0);
				self::assert($objBs[0]->child === null);
				self::assert(get_class($objBs[0]->bob_ject) === "objWithPrimitives");
				self::assert($objBs[0]->bob_ject->boolTest === true);
				self::assert($objBs[0]->bob_ject->stringTest3 === "boooosh");

			self::assert($objBs[1]->id === 2);
				self::assert($objBs[1]->child === null);
				self::assert($objBs[1]->intVar === 5);
				self::assert($objBs[1]->bob_ject === null);

			self::assert($objBs[2]->id === 3);
			self::assert(get_class($objBs[2]) === "objB");
				self::assert($objBs[2]->intVar === 9);
				self::assert($objBs[2]->bob_ject === null);
				self::assert($objBs[2]->child->id === 2);
				self::assert(get_class($objBs[2]->child) === "objB");
					self::assert($objBs[2]->child->intVar === 5);
					self::assert($objBs[2]->child->child === null);

			self::assert($objBs[3]->id === 4);
				self::assert($objBs[3]->child === null);
				self::assert($objBs[3]->bob_ject === null);

			self::assert(get_class($objBs[4]) === "objB");
			self::assert($objBs[4]->id === 5);
				self::assert($objBs[4]->bob_ject === null);
				self::assert($objBs[4]->child->id == 4);
				self::assert(get_class($objBs[4]->child) === "objB");
					self::assert($objBs[4]->child->intVar === 0);
					self::assert($objBs[4]->child->child === null);

			self::assert($objBs[5]->id === 6);
				self::assert($objBs[5]->bob_ject === null);
				self::assert($objBs[5]->child->id == 5);
				self::assert(get_class($objBs[5]->child) === "objB");
					self::assert(get_class($objBs[5]->child->child) === "objB");
					self::assert($objBs[5]->child->child->id === 4);
					self::expectCept(function()use($objBs) {$results = $objBs[5]->child->child->intVar;});	// intVar should not be available
					self::expectCept(function()use($objBs) {$results = $objBs[5]->child->child->child;});	// child should not be available


		// test to make sure you can fetch nested sub members when you're fetching a list of objects
		$objBs = $a->get("objB[members:child[members:child[members:child[members:child]]]]"); // note that this can select one more level than is actually there - this shouldn't cause a problem
		self::assert(count($objBs) == 6);
			self::assert($objBs[0]->id === 1);
				self::assert($objBs[0]->child == null);
			self::assert($objBs[1]->id === 2);
				self::assert($objBs[1]->child == null);
			self::assert($objBs[2]->id === 3);
				self::assert($objBs[2]->child->id == 2);
					self::assert($objBs[2]->child->child == null);
			self::assert($objBs[3]->id === 4);
				self::assert($objBs[3]->child == null);
			self::assert($objBs[4]->id === 5);
				self::assert($objBs[4]->child->id == 4);
					self::assert($objBs[4]->child->child == null);
			self::assert($objBs[5]->id === 6);
				self::assert($objBs[5]->child->id == 5);
					self::assert($objBs[5]->child->child->id == 4);
						self::assert($objBs[5]->child->child->child == null);

	}

	function nulls() {
		$a = setupTestDatabase();
		$a->debug(true);
		create2Bs($a);

		echo "save/insert nulls<br>";
		$egg2 = new objB();
		$egg2->bob_ject = null;
		$egg2->child = new objB();
		$egg2->intVar = 89;
		$insertedEgg = $a->insert($egg2);

		$insertedEgg->get();

		self::assert($insertedEgg->intVar === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert(get_class($insertedEgg->child) === "objB");

		$insertedEgg->child = null; //set member back to null
		$insertedEgg->save();

		$insertedEgg->get();
		self::assert($insertedEgg->intVar === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert($insertedEgg->child === null);

        $objBs = $a->get("objB[cond: bob_ject != null]");
        $objBs[0]->bob_ject = null;
        $objBs[0]->save();


		$objBs = $a->get("objB[members: bob_ject]");

		foreach($objBs as $c)
		{	self::assert($c->bob_ject === null);
		}
	}

	function addMembers()
	{	$a = setupTestDatabase();

		$x = new metamorph();

		$found = false;
		try
		{	$x->save();
		}catch(cept $c)
		{	$found = true;
		}
		self::assert($found);

		$x = $a->insert($x);
		self::assert(defined_in_table($a, "metamorph", array(), array("a","b")));

		$x->clear();
		metamorph::$sclass = "int : a";

		$x = new metamorph();
		$x = $a->insert($x);
		self::assert(defined_in_table($a, "metamorph", array(), array("a","b")));
		$x->a = 5;
		$x->save();
		self::assert(defined_in_table($a, "metamorph", array("a"), array("b")));

		$x->clear();
		metamorph::$sclass .= " string : b";

		$x = new metamorph();
		$x = $a->insert($x);
		self::assert(defined_in_table($a, "metamorph", array("a"), array("b")));
		$x->a = 5;
		$x->b = "WEFIOJ";
		$x->save();
		self::assert(defined_in_table($a, "metamorph", array("a", "b"), array()));

	}
	//*/

	function objectsWithLists()
	{	$a = setupTestDatabase();
		$a->debug(true);

		$lo1 = new listContainer();
		$lo1->x = 71;
		$lo1->y = array();
		$newLo1 = $a->insert($lo1);

        // commented out because I want/need to test insert first
        $newLo1->get();
        self::assert($newLo1->x === 71);
        self::assert(get_class($newLo1->y) === 'SqoolList' && count($newLo1->y) === 0);
        self::assert(get_class($newLo1->z) === 'SqoolList' && count($newLo1->z) === 0);


		$lo2 = new listContainer();
		$lo2->x = 72;
		$lo2->y = array("hello");
		$lo2->z = array();
		$newLo2 = $a->insert($lo2);

		$newLo2_b = $a->getu('listContainer[cond: x=72]');
		self::assert($newLo2_b->x === 72);
		self::assert(count($newLo2_b->y) === 1);
		self::assert(count($newLo2_b->z) === 0);


		$lo3 = new listContainer();
		$lo3->x = 73;
		$lo3->y = array("hello", "wunka", "skeedo");
		$lo3->z = array($lo1, $lo2);
		$newLo3 = $a->insert($lo3);

		$newLo3->z = array();
		$newLo3->save();	// now there should still be the array's id in the listcontainer table, but there should be no elements (instead of the id being null)


	}

    function testErrors()
    {   $a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
        rmDatabase($a, "garboNonExistant");

        $arf = new objWithPrimitives();
        $arf->boolTest = 1;
        $arf->stringTest1 = true;
        $arf->stringTest2 = 23;
        $arf->stringTest3 = 34.532;
        $arf->tinyintTest = 123456;
        $arf->inttest = "testing";
        $arf->biginttest = "test";
        $arf->floattest = "test";
        $arf->doubletest = "test";

        $arf2 = new objWithPrimitives();
        $arf2->boolTest = "testing";
        $arf2->stringTest1 = "drankFu";
        $arf2->stringTest2 = "lets go";
        $arf2->stringTest3 = "Try2\" '' ~~";
        $arf2->tinyintTest = "testerizer";
        $arf2->inttest = 44.34;
        $arf2->biginttest = 555.234;
        $arf2->floattest = 5.6665;
        $arf2->doubletest = 11.890890;

        $arf3 = new objWithPrimitives();
        $arf3->boolTest = false;
        $arf3->stringTest1 = "dtaFoo";
        $arf3->stringTest2 = "lets show em up";
        $arf3->stringTest3 = "bastazx2\" '' ~~";
        $arf3->tinyintTest = 77;
        $arf3->inttest = 88;
        $arf3->biginttest = 1111;
        $arf3->floattest = 2436.2345;
        $arf3->doubletest = 2345325.43;

        $a->insert($arf);
        $a->insert($arf2);
        $a->insert($arf3);

        throw new cept("something should be thrown");
    }

}




class SqoolParserTests extends SqoolUTester {

	static function testSimpleParser() {
		$parser = new SqoolParser("testing");

		$parser->peek();
		$result = $parser->match("t", $out, false);
		self::assert($result === true);
		self::assert($out === 't');

		$parser->unpeek();

		$result = $parser->peek()->match("t", $out, true);
		self::assert($result === true);
		self::assert($out === 't');

		$parser = new SqoolParser(" \t\r \n\t testing");

		$result = $parser->peek()->match("t", $out, false);
		self::assert($result === false);

		$result = $parser->unpeek()->peek()->match("t", $out, true);
		self::assert($result === true);
		self::assert($out === 't');


		$parser = new SqoolParser("tes ting");

		$result = $parser->match("t", $out);
		self::assert($result === true);
		self::assert($out === 't');

		$result = $parser->match("e", $out);
		self::assert($result === true);
		self::assert($out === 'e');

		$result = $parser->match("s", $out) && $parser->match("ting", $out2);
		self::assert($result === true);
		self::assert($out === 's');
		self::assert($out2 === 'ting');


		$parser = new SqoolParser("testing");

		$parser->match('nope');
		$parser->match('nop2');
		$parser->match('nop3');

		self::assert(count($parser->cursor->rejectedSubCursors) === 3);
		SqoolParser::$html = false;
		self::assert($parser->failureTrace() ===
		  	 "Input did not match the expression: 'nope' at character 0 starting with \"testing\"\n"
		  	."Input did not match the expression: 'nop2' at character 0 starting with \"testing\"\n"
			."Input did not match the expression: 'nop3' at character 0 starting with \"testing\"");

		//var_dump($parser->failureTrace());

		SqoolParser::$html = true;
		self::assert($parser->failureTrace() ===
					"<ul><li>Input did not match the expression: 'nope' at character 0 starting with \"testing\"</li>"
				   ."<li>Input did not match the expression: 'nop2' at character 0 starting with \"testing\"</li>"
				   ."<li>Input did not match the expression: 'nop3' at character 0 starting with \"testing\"</li></ul>");

		//echo $parser->failureTrace(true);

		SqoolParser::$html = false;
		$parser = new SqoolParser("testing");

		$parser->save();
		self::assert($parser->reject("Testing rejection") === false);
		var_dump($parser->failureTrace());
		self::assert($parser->failureTrace() === 'Testing rejection at character 0 starting with "testing"');


	}

	static function testSaving() {
		$parser = new SqoolParser("testing");

		$parser->save();
		$parser->save();
		self::assert($parser->match("t") === true);
		$parser->accept();
		self::assert($parser->match("e") === true);
		$parser->accept();

		self::assert($parser->match("s") === true);

		$parser->save();
		self::assert($parser->match("X") === false);
		$parser->reject("Test rejection 1");
		$parser->save();
		self::assert($parser->match("X") === false);
		$parser->reject("Test rejection 2");

		echo $parser->failureTrace(true);

		self::assert($parser->failureTrace() ===
					"Test rejection 1 at character 3 starting with \"ting\"\n"
				   ."  Input did not match the expression: 'X' at character 3 starting with \"ting\"\n"
				   ."Test rejection 2 at character 3 starting with \"ting\"\n"
				   ."  Input did not match the expression: 'X' at character 3 starting with \"ting\"");

		var_dump($parser->failureTrace());

		self::assert($parser->match("X") === false);

		SqoolParser::$html = true;	// set back to true
	}
}






class SqoolTestClassA extends SqoolExtensionSystem {
	function execute($name) {
		$results = array();
		$this->addToQueue(array('op'=>$name, 'results'=>&$results));
		return $results;
	}
	function testop()
    {   $this->addToQueue(array('op'=>"testop"));
    }

}

class SqoolExtensionSystemTests extends SqoolUTester
{
	// tests connect, addOperation, and addToQueue
	static function testAddOperation() {
		$connection = SqoolTestClassA::connect("root", "");


		// test generator

		// make sure the table isn't there
		SqoolTestClassA::addOperation("dropDB", array('generator'=>function($this, $op){return array("drop database if exists testdb");}));
		$connection->execute("dropDB");

	    self::expectCept(function()use($connection) {$results = $connection->execute("testop");});
		SqoolTestClassA::addOperation("testop", array('generator'=>function($this, $op){return array("create database testdb");}));
		$results = $connection->testop();
		self::assert($results === null);


		// test resultHandler

		SqoolTestClassA::addOperation("testop2", array(
			'generator'=>function($this, $op){return array("show databases");},
			'resultHandler' => function($this, $op, $results){
				SqoolExtensionSystemTests::assert($op===array('op'=>'testop2', 'results'=>array()));
				$op['results'] = $results;
			}
		));

		$results = $connection->execute("testop2");
		$gotTestdb = false;
		foreach($results[0] as $r) {
			if($r['Database'] === 'testdb') {
				$gotTestdb = true;
			}
		}
		self::assert($gotTestdb);
	

		// test errorHandler

		SqoolTestClassA::addOperation("testop3", array(
			'generator'=>function($this, $op){return array("drop database nonexistant");},
			'errorHandler' => function($this, $op, $errorNumber, $results) {
				SqoolExtensionSystemTests::assert($errorNumber === 1008);
			}
		));
		self::expectCept(function()use($connection) {$connection->execute("testop3");});	// database doesn't exist, so can't drop it

		self::expectCept(function()use($connection) {SqoolTestClassA::addOperation("dropDB", array('generator'=>function(){}));});	// redeclaration

		// at this point $connection has bad queries

		SqoolTestClassA::addOperation("add nonexistant", array(
			'generator'=>function($this, $op){return array("create database nonexistant");}
		));

		SqoolTestClassA::addOperation("testop4", array(
			'generator'=>function($this, $op){return array("drop database nonexistant");},
			'resultHandler' => function($this, $op, $results){
				SqoolExtensionSystemTests::assert($op===array('op'=>"testop4", 'results'=>array(), 'sqoolFailedOnce'=>"Can't drop database 'nonexistant'; database doesn't exist"));
				SqoolExtensionSystemTests::assert($results===array(array()));
				$op['results'] = $results;
			},
			'errorHandler' => function($this, $op, $errorNumber, $results) {
				SqoolExtensionSystemTests::assert($errorNumber === 1008);
				return array(array('op'=>"add nonexistant"), $op);
			}
		));


		// at this point $connection has bad queries - so use a different sqool connection
		$connectionToo = SqoolTestClassA::connect($connection->connection());
		$connectionToo->execute("testop4");

		SqoolTestClassA::addOperation("testop5", array(
			'generator'=>function($this, $op){return array("drop database nonexistant");},
			'errorHandler' => function($this, $op, $errorNumber, $results) {
				SqoolExtensionSystemTests::assert($errorNumber === 1008);
				return array('op'=>'whatever');	// should be an array of ops, not a single op
			}
		));

		self::expectCept(function()use($connectionToo) {$connectionToo->execute("testop5");});	// errorHandler not returning the right thing

		$connection3 = SqoolTestClassA::connect($connection->connection());

		SqoolTestClassA::addOperation("testop6", array(
			'generator'=>function($this, $op){return array("drop database nonexistant");},
			'errorHandler' => function($this, $op, $errorNumber, $results) {
				SqoolExtensionSystemTests::assert($errorNumber === 1008);
				return array($op);
			}
		));

		self::expectCept(function()use($connection3) {$connection3->execute("testop6");});	// errorHandler returning just the operation

	}

	// tests debugHandler and debug (both the static and instance methods for each)
	static function debugHandler() {

		$sqlString = "show databases";
		SqoolTestClassA::addOperation("runStatement", array('generator'=>function($this, $op)use($sqlString){return array($sqlString);}));

		$debugResult = array('connection'=>false, 'executing'=>false, 'results'=>false, 'other'=>false);
		SqoolTestClassA::debugHandler(function($msg)use(&$debugResult, $sqlString) {
			if($msg === 'Attempting to connect to localhost with the username root.') {
				$debugResult['connection'] = true;
			} else if($msg === 'Executing: '.$sqlString.';') {
				$debugResult['executing'] = true;
			} else if(strpos($msg, 'Results') !== false) {
				$debugResult['results'] = true;
			} else {
				$debugResult['other'] = true;
				echo $msg.'<br>/n';
			}
		});


		$connection = SqoolTestClassA::connect("root", "");
		$connection->execute("runStatement");

		self::assert($debugResult['connection']);
		self::assert($debugResult['executing']);
		self::assert($debugResult['results']);
		self::assert($debugResult['other'] == false);

		// reset debugResult
		foreach($debugResult as &$x) {
			$x = false;
		}

		$debugResult2 = array('connection'=>false, 'executing'=>false, 'results'=>false, 'other'=>false);
		$connection->debugHandler(function($msg)use(&$debugResult2, $sqlString) {
			if($msg === 'Attempting to connect to localhost with the username root.') {
				$debugResult2['connection'] = true;
			} else if($msg === 'Executing: '.$sqlString.';') {
				$debugResult2['executing'] = true;
			} else if(strpos($msg, 'Results') !== false) {
				$debugResult2['results'] = true;
			} else {
				$debugResult2['other'] = true;
				echo $msg.'<br>/n';
			}
		});

		$connection->execute("runStatement");

		self::assert($debugResult2['connection'] === false);
		self::assert($debugResult2['executing']);
		self::assert($debugResult2['results']);
		self::assert($debugResult2['other'] == false);

		self::assert($debugResult['connection'] == false);
		self::assert($debugResult['executing'] == false);
		self::assert($debugResult['results'] == false);
		self::assert($debugResult['other'] == false);


		// now turn debug mode off

		SqoolTestClassA::debug(false);

		$gotToDebugHandler = null;
		SqoolTestClassA::debugHandler(function($msg)use(&$gotToDebugHandler) {
			$gotToDebugHandler = true;
		});

		$connection = SqoolTestClassA::connect("root", "");

		$gotToDebugHandler = false;
		$connection->execute("runStatement");
		self::assert($gotToDebugHandler === false);

		$connection->debug(true);

		$gotToDebugHandler = false;
		$connection->execute("runStatement");
		self::assert($gotToDebugHandler === true);

		$connection->debug(false);

		$gotToDebugHandler = false;
		$connection->execute("runStatement");
		self::assert($gotToDebugHandler === false);

	}





}





class testClassA {
	static function init() {
	}
}
class testClassB extends testClassA {
}
class testClassC extends testClassB {
	static function init() {
	}
}

class SqoolUtilsTests extends SqoolUTester {
	static function test() {
		$className = 'SqoolUtils';

		$a = array('a'=>1, 'b'=>2, 'c'=>3);
        $b = array('c'=>1, 'd'=>2, 'e'=>3);

        $result = SqoolUTester::call($className, "assarray_merge", array(),array());
        self::assert($result === array());
        $result = SqoolUTester::call($className, "assarray_merge", $a,array());
        self::assert($result === $a);

        $result = SqoolUTester::call($className, "assarray_merge", array(),$b);
        self::assert($result === $b);


        $result = SqoolUTester::call($className, "assarray_merge", $a,$b);
        self::assert($a === array('a'=>1, 'b'=>2, 'c'=>3));
        self::assert($b === array('c'=>1, 'd'=>2, 'e'=>3));
        self::assert($result === array('a'=>1, 'b'=>2, 'c'=>1, 'd'=>2, 'e'=>3));


        $object = new testClassA();
        $resultA = SqoolUTester::call($className, "getFamilyTree", "testClassA");
        $resultB = SqoolUTester::call($className, "getFamilyTree", $object);
        self::assert($resultB == $resultA && $resultA == array("testClassA"));
        $object = new testClassB();
        $resultA = SqoolUTester::call($className, "getFamilyTree", "testClassB");
        $resultB = SqoolUTester::call($className, "getFamilyTree", $object);
        print_r($resultA);
        self::assert($resultB == $resultA && $resultA == array("testClassA", "testClassB"));
        $object = new testClassC();
        $resultA = SqoolUTester::call($className, "getFamilyTree", "testClassC");
        $resultB = SqoolUTester::call($className, "getFamilyTree", $object);
        self::assert($resultB == $resultA && $resultA ==  array("testClassA", "testClassB", "testClassC"));


        $resultA = SqoolUTester::call($className, "methodIsDefinedIn", "testClassA", 'init');
        $resultB = SqoolUTester::call($className, "methodIsDefinedIn", "testClassB", 'init');
        $resultC = SqoolUTester::call($className, "methodIsDefinedIn", "testClassC", 'init');
        self::assert($resultA === array("testClassA"));
        self::assert($resultB === array("testClassA"));
        self::assert($resultC === array("testClassA", "testClassC"));

        // aw f*ck it, I know the rest of these private methods work, i'll only test them if they start breaking down or something
	}

}

initialSetup();

//SqoolUtilsTests::run();
//SqoolExtensionSystemTests::run();
//SqoolParserTests::run();
SqoolTests::run();

