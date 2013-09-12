<?php
require_once('sqoolUnitTester.php');
require_once("prettyTree.php");
require_once('SQOOL_0.6.php');

$bangalore=false;
function getchar()
{	$fp = fopen('php://stdin', 'r');
	return fgetc($fp);
}

/*	tests to do:
	* test adding a column to the class def (and using it)
		* test adding a list column - since this has special needs (lists are initialized as null, and when they are first used an ID needs to be given for the list the proper tables may need to be created)
*/

//sqool::debug(true); // this will print out all the SQL queries that run
sqool::debug(false);

class nonExistantClass extends sqool
{	function sclass()
	{	
	}
}

class objWithPrimitives extends sqool
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

class objB extends sqool
{	function sclass()
	{	return 
		'objWithPrimitives:	bob_ject
		 int:				intVar
		 objB: 				child
		 condor: 			j
		';
	}
}

class condor extends sqool
{	function sclass()
	{	return
		'int: 		x
		 string: 	y
		';
	}	
}

class comment extends sqool
{	function sclass()
	{	return
		'string: 	name
		 string: 	comment
		 int:		time
		';
	}
}

class listContainer extends sqool
{	function sclass()
	{	return
		'int: 				 x
		 string list: 		 y
		 listContainer list: z
		';
	}	
}
class metamorph extends sqool
{	public static $sclass = "";
	function sclass()
	{	return self::$sclass;
	}
	
	public function clear()
	{	self::clearClasses();
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

function setupTestDatabase()
{	sqool::debug(false);
	
	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
	$a->rm();
	
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
	
	$insertedEgg->fetch();		
	$insertedEgg->bob_ject->fetch();
	
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
	foreach($showColumnsResult->result[0] as $DBcol)
	{	$columns[] = $DBcol[0];
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


class sqoolTests extends sqoolUTester 
{	function testUtilityMethods()
	{	$x = array(1,2,3,4);
		
		$y = sqool::array_insert($x, 2, "BOO");
		print_r($y);
		self::assert($y[0] === 1);
		self::assert($y[1] === 2);
		self::assert($y[2] === "BOO");
		self::assert($y[3] === 3);
		self::assert($y[4] === 4);
	}
	
	function testFetchOnNonExistantDB()
	{	
		$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$a->fetch(array
		(	"nonExistantClass"
		));
		
		self::assert
		(	is_array($a->nonExistantClass)
		);
		self::assert(count($a->nonExistantClass) === 0);
	}
	
	function testInsertDefaults()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$arf = new objWithPrimitives();
		$arf2 = $a->insert($arf);
		
		$a->fetch(array
		(	"objWithPrimitives"
		));
		
		self::assert(count($a->objWithPrimitives) === 1);
		self::assert($a->objWithPrimitives[0]->boolTest === false);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 0);
		self::assert($a->objWithPrimitives[0]->inttest === 0);
		self::assert($a->objWithPrimitives[0]->biginttest === 0);
		self::assert($a->objWithPrimitives[0]->floattest === floatval(0));
		self::assert($a->objWithPrimitives[0]->doubletest === floatval(0));		
	}
	
	function testInsert()
	{	$a = setupTestDatabase();
		
		
		$a->fetch(array
		(	"objWithPrimitives"
		));
		
		self::assert(count($a->objWithPrimitives) === 3);
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
	
	/*
	function testInsertBadValues()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
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
		
		$a->fetch(array
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
	
	
	function testSave()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$arf = new objWithPrimitives();
		$arf2 = $a->insert($arf);
		
		$arf2->boolTest = true;
		$arf2->stringTest1 = "pices of a drem";
		$arf2->stringTest2 = "Shanks you";
		$arf2->stringTest3 = "TRY\" '' ~~";
		$arf2->tinyintTest = 100;
		$arf2->inttest = 500;
		$arf2->biginttest = 600;
		$arf2->floattest = 4.435;
		$arf2->doubletest = 5.657;
		
		$arf2->save();	
		
		$arfAgain = $a->insert(new objWithPrimitives());
		
		$arfAgain->biginttest = 1;
		$arfAgain->floattest = 1;
		$arfAgain->doubletest = 1;	
		$arfAgain->save();
		
		$a->fetch(array
		(	"objWithPrimitives"
		));
		
		
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);	
		
		self::assert($a->objWithPrimitives[1]->biginttest === 1);
		self::assert($a->objWithPrimitives[1]->floattest === 1.0);
		self::assert($a->objWithPrimitives[1]->doubletest === 1.0);	
		
		
	}
	
	function testCommentsThing()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$a->fetch("comment");
			
	}
	
	function testFetch1()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		
		$a->rm();
		
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
		
		$arf2 = $a->insert($arf);
		$arf2 = $a->insert($arf);
		
		$com = new comment();
		$com->name = "your mom";
		
		$a->insert($com);
		$a->insert($com);
		$a->insert($com);
		
		$a->fetch("objWithPrimitives");
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
		
		$a->fetch(array("objWithPrimitives"));
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
		
		$a->fetch(array("objWithPrimitives"));
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
		
		$a->fetch(array("objWithPrimitives", "comment"));
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		$a->fetch(array("objWithPrimitives"=>array(), "comment"=>array()));
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "pices of a drem");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "TRY\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 100);
		self::assert($a->objWithPrimitives[0]->inttest === 500);
		self::assert($a->objWithPrimitives[0]->biginttest === 600);
		self::assert($a->objWithPrimitives[0]->floattest === 4.435);
		self::assert($a->objWithPrimitives[0]->doubletest === 5.657);
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		$a->fetch(array("objWithPrimitives"=>array("members"=>array("boolTest")), "comment"=>array()));
		self::assert(count($a->objWithPrimitives) === 2, "So apparently ".count($a->objWithPrimitives)." doesn't pass for 2 these days");
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert(gotCeptFromInvalidAccess($a->objWithPrimitives[0], "stringTest1"));
		
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		
		
		$a->fetch(array( "objWithPrimitives"=>array("members"=>array("boolTest", "stringTest2")), "comment"=>array("members"=>array("name")) ));
		self::assert(count($a->objWithPrimitives) === 2);
		self::assert($a->objWithPrimitives[0]->boolTest === true);
		self::assert($a->objWithPrimitives[0]->stringTest2 === "Shanks you");	
		self::assert(gotCeptFromInvalidAccess($a->objWithPrimitives[0], "stringTest1"));
		
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");		
	}
	
	function testFetch2()
	{	$a = setupTestDatabase();
		sqool::debug(false);
		
		$booObj = $a->fetch("objWithPrimitives", 1);
		$booObj->fetch();
		
		self::assert($booObj->boolTest === true);
		self::assert($booObj->stringTest1 === "pices of a drem");
		self::assert($booObj->stringTest2 === "Shanks you");
		self::assert($booObj->stringTest3 === "TRY\" '' ~~");
		self::assert($booObj->tinyintTest === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);
				
		$a->fetch("objWithPrimitives");
		$booObj = $a->objWithPrimitives[0];
		$booObj->fetch();
		
		self::assert($booObj->boolTest === true);
		self::assert($booObj->stringTest1 === "pices of a drem");
		self::assert($booObj->stringTest2 === "Shanks you");
		self::assert($booObj->stringTest3 === "TRY\" '' ~~");
		self::assert($booObj->tinyintTest === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);
		
		$booObj = $a->fetch("objWithPrimitives", 2);
		$booObj->fetch();
		
		self::assert($booObj->boolTest === false);
		self::assert($booObj->stringTest1 === "drankFu");
		self::assert($booObj->stringTest2 === "lets go");
		self::assert($booObj->stringTest3 === "Try2\" '' ~~");
		self::assert($booObj->tinyintTest === 33);
		self::assert($booObj->inttest === 44);
		self::assert($booObj->biginttest === 555);
		self::assert($booObj->floattest === 5.6665);
		self::assert($booObj->doubletest === 11.890890);
		
		
		$booObj = $a->fetch("objWithPrimitives", 1);
		$booObj->fetch("boolTest");
		
		self::assert($booObj->boolTest === true);
		self::assert(gotCeptFromInvalidAccess($booObj, "stringTest1"));
		
		
		
		// sort test
		
		
		$a->fetch(array("objWithPrimitives"=>array("ranges"=>array(1,1))));
		
		self::assert(count($a->objWithPrimitives) === 1);
		
		self::assert($a->objWithPrimitives[0]->boolTest === false);
		self::assert($a->objWithPrimitives[0]->stringTest1 === "drankFu");
		self::assert($a->objWithPrimitives[0]->stringTest2 === "lets go");
		self::assert($a->objWithPrimitives[0]->stringTest3 === "Try2\" '' ~~");
		self::assert($a->objWithPrimitives[0]->tinyintTest === 33);
		self::assert($a->objWithPrimitives[0]->inttest === 44);
		self::assert($a->objWithPrimitives[0]->biginttest === 555);
		self::assert($a->objWithPrimitives[0]->floattest === 5.6665);
		self::assert($a->objWithPrimitives[0]->doubletest === 11.890890);
		
		
		$a->fetch(array("objWithPrimitives"=>array("sort"=>array(sqool::a, "inttest"))));
		
		self::assert(count($a->objWithPrimitives) === 3);
		self::assert($a->objWithPrimitives[0]->inttest < $a->objWithPrimitives[1]->inttest);
		self::assert($a->objWithPrimitives[1]->inttest < $a->objWithPrimitives[2]->inttest);
		
		$a->fetch(array("objWithPrimitives"=>array("sort"=>array(sqool::a, "inttest", "floattest"))));
		
		
		// cond test
		
		$a->fetch(array("objWithPrimitives"=>array("cond"=>array("tinyintTest =",33))));
		self::assert(count($a->objWithPrimitives) === 1);
		self::assert($a->objWithPrimitives[0]->tinyintTest === 33);
		
		$a->fetch(array("objWithPrimitives"=>array("cond"=>array("tinyintTest >",33,"&& inttest <", 400))));
		self::assert(count($a->objWithPrimitives) === 1);
		self::assert($a->objWithPrimitives[0]->tinyintTest === 77);
		self::assert($a->objWithPrimitives[0]->inttest === 88);
		
	}
	
	function testObjects()
	{	$a = setupTestDatabase();
		sqool::debug(false);
		
		$egg2 = new objB();
		$egg2->bob_ject = new objWithPrimitives();
		$egg2->bob_ject->boolTest = true;
		$egg2->bob_ject->stringTest3 = "boooosh";
		self::assert($egg2->bob_ject->id === false);
		self::assert($egg2->id === false);
		$insertedEgg = $a->insert($egg2);
		
		self::assert($egg2->id === false);
		self::assert($egg2->bob_ject->id === false);
		
		$a2 = sqool::connect("root", "", "garboNonExistant2");	// connect to a database that doesn't exist (connects on localhost)
		$a2->rm();
		$insertedEgg2 = $a2->insert($egg2);
		self::assert($egg2->id === false);
		self::assert($egg2->bob_ject->id === false);
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
		self::assert($insertedEgg->bob_ject->boolTest === true);
		self::assert($insertedEgg->bob_ject->stringTest3 === "boooosh");
		
		
		$egg3 = new objB();	// (this will be objB2)
		$egg3->child = new objB();		
		$egg3->child->intVar = 5;
		$egg3->intVar = 9;
		$insertedEgg = $a->insert($egg3);
		
		$insertedEgg->fetch();		
		$insertedEgg->child->fetch();		
		self::assert($insertedEgg->intVar === 9);
		self::assert($insertedEgg->child->intVar === 5);
		
		$boo1 = $a->fetch("objWithPrimitives", 1);
		
		$egg3 = new objB();
		$egg3->bob_ject = $boo1;
		$egg3->intVar = 9;
		$insertedEgg = $a->insert($egg3);
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
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
		$boo1 = $a->fetch("objWithPrimitives", 1);
		$boo1->fetch();
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
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
		self::assert($insertedEgg->intVar === 9);
		self::assert($insertedEgg->bob_ject->boolTest === true);
		self::assert($insertedEgg->bob_ject->stringTest2 === "alright then");
		
		$insertedEgg->bob_ject = new objWithPrimitives();
		$insertedEgg->bob_ject->stringTest2 = "whatever dude";
		$insertedEgg->j = new condor();
		$insertedEgg->save();
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();
		self::assert($insertedEgg->bob_ject->stringTest2 === "whatever dude");
	}
	
	function testFetchObjects()
	{	sqool::debug(true);
		$a = setupTestDatabase();
		create2Bs($a);
		
		echo "fetching<br>";
		
		$objB2 = $a->fetch("objB", 2);
		$objB2->fetch(array
		(	"intVar",
			"child"
		));
		
		self::assert($objB2->intVar == 9);
		self::assert($objB2->child->intVar == 5);
		
		// The following selects all objB objects (should be 6 or so of them) and attempts to get the object-member child from each of them
		$a->fetch(array("objB"=>array("members"=>array("child"))));
		
		self::assert(count($a->objB) == 6);
			self::assert($a->objB[0]->id === 1);
			self::assert($a->objB[1]->id === 2);
				self::assert($a->objB[1]->child->id == 3);
				echo "id is: ".$a->objB[4]->child->id."<br>";
			self::assert($a->objB[2]->id === 3);
			self::assert($a->objB[3]->id === 4);
				self::assert($a->objB[3]->child->id == 5);
				echo "id is: ".$a->objB[4]->child->id."<br>";
			self::assert($a->objB[4]->id === 5);
				self::assert($a->objB[4]->child->id == 6);
			self::assert($a->objB[5]->id === 6);
				
		self::assert($a->objB[0]->child === null);
		self::assert(get_class($a->objB[1]->child) === "objB");
			self::assert($a->objB[1]->child->intVar === 5);
		self::assert($a->objB[2]->child === null);
		self::assert(get_class($a->objB[3]) === "objB");
			self::assert(get_class($a->objB[3]->child) === "objB");
			self::assert(get_class($a->objB[3]->child->child) === "objB");
		self::assert(get_class($a->objB[4]) === "objB");
			self::assert(get_class($a->objB[4]->child) === "objB");
			self::assert($a->objB[4]->child->child === null);
		self::assert(get_class($a->objB[5]) === "objB");
			self::assert($a->objB[5]->child === null);
		
		
		// The following selects all objB objects (should be 6 or so of them) and attempts to get a couple different object-members from each of them
		$a->fetch(array("objB"=>array("members"=>array("bob_ject", "child", "intVar"))));
		
		self::assert(count($a->objB) == 6);
		self::assert(get_class($a->objB[0]->bob_ject) === "objWithPrimitives");
		self::assert($a->objB[0]->bob_ject->boolTest === true);
		self::assert($a->objB[0]->bob_ject->stringTest3 === "boooosh");
		self::assert($a->objB[0]->child === null);
		
		self::assert(get_class($a->objB[1]->child) === "objB");
		self::assert($a->objB[1]->child->intVar === 5);
		self::assert($a->objB[1]->intVar === 9);
		self::assert($a->objB[1]->bob_ject === null);
		
		self::assert(get_class($a->objB[2]) === "objB");
		self::assert($a->objB[2]->intVar === 5);
		self::assert($a->objB[2]->bob_ject === null);
		self::assert($a->objB[2]->child === null);
		
		self::assert(get_class($a->objB[3]) === "objB");
		self::assert(get_class($a->objB[3]->child) === "objB");
		self::assert(get_class($a->objB[3]->child->child) === "objB");
		self::assert($a->objB[3]->bob_ject === null);
		
		self::assert(get_class($a->objB[4]) === "objB");
		self::assert(get_class($a->objB[4]->child) === "objB");
		self::assert($a->objB[4]->child->child === null);
		self::assert($a->objB[4]->bob_ject === null);
		
		self::assert(get_class($a->objB[5]) === "objB");
		self::assert($a->objB[5]->child === null);
		self::assert($a->objB[5]->bob_ject === null);
		
	}
	
	function nulls()
	{	sqool::debug(true);
		$a = setupTestDatabase();
		create2Bs($a);
		
		echo "save/insert nulls<br>";
		$egg2 = new objB();
		$egg2->bob_ject = null;
		$egg2->child = new objB();
		$egg2->intVar = 89;
		$insertedEgg = $a->insert($egg2);
		
		$insertedEgg->fetch();
		
		self::assert($insertedEgg->intVar === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert(get_class($insertedEgg->child) === "objB");
		
		$insertedEgg->child = null;
		$insertedEgg->save();
		
		$insertedEgg->fetch();
		self::assert($insertedEgg->intVar === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert($insertedEgg->child === null);
		
		$a->fetch(array("objB"=>array("members"=>array("bob_ject"))));
		print_r($a);
		getchar();
		foreach($a->objB as $c)
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
	
	function objectsWithLists()
	{	$a = setupTestDatabase();
		
		$lo1 = new listContainer();
		$lo1->x = 71;
		$lo1->y = array();
		$newLo1 = $a->insert($lo1);
		
		$lo2 = new listContainer();
		$lo2->x = 72;
		$lo2->y = array("hello");
		$newLo2 = $a->insert($lo2);
		
		/*
		$lo2 = new listContainer();
		$lo2->x = 72;
		$lo2->y = array("hello");
		$lo2->z = array();
		$newLo2 = $a->insert($lo2);
		
		
		$lo3 = new listContainer();
		$lo3->x = 73;
		$lo3->y = array("hello", "wunka", "skeedo");
		$lo3->z = array($lo1, $lo2);
		$newLo3 = $a->insert($lo3);
		
		
		$lo4 = new listContainer();
		$lo4->x = 74;
		$lo4->y = array("fusikins", "doolettuce");
		$lo4->z = array($lo1, $lo2, $lo3);
		*/
		throw new cept("gah");
		
		echo "here<br>";
		
		var_dump($newLo1);
		
	}
}

sqoolTests::run();

//$fp = fopen('php://stdin', 'r');
//fgets($fp, 2);

?>
