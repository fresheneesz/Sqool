<?php
require_once('sqoolUnitTester.php');
require_once("prettyTree.php");
require_once('SQOOL_0.6.php');

$bangalore=false;


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

class boo extends sqool
{	function sclass()
	{	return 
		'bool: 		moose
		 string: 	barbarastreisznad
		 string: 	dickweed
		 string: 	moose2
		 tinyint:	doosh4
		 int:		inttest
		 bigint:	biginttest
		 float:		floattest
		 double:	doubletest
		';
	}
}

class crack extends sqool
{	function sclass()
	{	return 
		'boo:	bob_ject
		 int:	necklace
		 crack: crackJacket
		 condor: j
		';
	}
}

class condor extends sqool
{	function sclass()
	{	return
		'int: x
		 string: y
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
		'int: x
		 string list: y
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
	
	$arf = new boo();
	$arf->moose = true;
	$arf->barbarastreisznad = "pices of a drem";
	$arf->dickweed = "Shanks you";
	$arf->moose2 = "SUCKT\" '' ~~";
	$arf->doosh4 = 100;
	$arf->inttest = 500;
	$arf->biginttest = 600;
	$arf->floattest = 4.435;
	$arf->doubletest = 5.657;
	
	$arf2 = new boo();
	$arf2->moose = false;
	$arf2->barbarastreisznad = "drankFU";
	$arf2->dickweed = "lets go";
	$arf2->moose2 = "SUCKT2\" '' ~~";
	$arf2->doosh4 = 33;
	$arf2->inttest = 44;
	$arf2->biginttest = 555;
	$arf2->floattest = 5.6665;
	$arf2->doubletest = 11.890890;
	
	$arf3 = new boo();
	$arf3->moose = false;
	$arf3->barbarastreisznad = "dtaFU";
	$arf3->dickweed = "lets show em up";
	$arf3->moose2 = "bastazx2\" '' ~~";
	$arf3->doosh4 = 77;
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

function create2cracks($a)
{	sqool::debug(false);

	$egg2 = new crack();
	$egg2->bob_ject = new boo();
	$egg2->bob_ject->moose = true;
	$egg2->bob_ject->moose2 = "boooosh";
	$insertedEgg = $a->insert($egg2);
	
	$insertedEgg->fetch();		
	$insertedEgg->bob_ject->fetch();
	
	$egg3 = new crack();	// (this will be crack2)
	$egg3->crackJacket = new crack();	// crack3	
	$egg3->crackJacket->necklace = 5;
	$egg3->necklace = 9;
	$insertedEgg = $a->insert($egg3);
	
	$egg3 = new crack();	// (this will be crack4)
	$egg3->crackJacket = new crack();	// crack5
	$egg3->crackJacket->crackJacket = new crack();	// crack6
	$insertedEgg = $a->insert($egg3);
	
	/*$egg3 = new crack();	// (this will be crack7)
	$egg3->crackJacket = new crack();	// crack8
	$egg2->bob_ject = new boo();	
	$egg3->crackJacket->necklace = 5;
	$egg2->bob_ject->moose2 = "boooosh";
	$insertedEgg = $a->insert($egg3);
	*/
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
		
		$arf = new boo();
		$arf2 = $a->insert($arf);
		
		$a->fetch(array
		(	"boo"
		));
		
		self::assert(count($a->boo) === 1);
		self::assert($a->boo[0]->moose === false);
		self::assert($a->boo[0]->barbarastreisznad === "");
		self::assert($a->boo[0]->dickweed === "");
		self::assert($a->boo[0]->moose2 === "");
		self::assert($a->boo[0]->doosh4 === 0);
		self::assert($a->boo[0]->inttest === 0);
		self::assert($a->boo[0]->biginttest === 0);
		self::assert($a->boo[0]->floattest === floatval(0));
		self::assert($a->boo[0]->doubletest === floatval(0));		
	}
	
	function testInsert()
	{	$a = setupTestDatabase();
		
		
		$a->fetch(array
		(	"boo"
		));
		
		self::assert(count($a->boo) === 3);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);		
	}
	
	/*
	function testInsertBadValues()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$arf = new boo();
		$arf->moose = 3;
		$arf->barbarastreisznad = 4.3;
		$arf->dickweed = array();
		$arf->moose2 = 39;
		$arf->doosh4 = 500;
		$arf->inttest = "momo";
		$arf->biginttest = 3.34;
		$arf->floattest = "hellio";
		$arf->doubletest = "supershak";
		
		$arf2 = $a->insert($arf);
		
		$a->fetch(array
		(	"boo"
		));
		
		self::assert(count($a->boo) === 1);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);		
	}
	*/
	
	
	function testSave()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$arf = new boo();
		$arf2 = $a->insert($arf);
		
		$arf2->moose = true;
		$arf2->barbarastreisznad = "pices of a drem";
		$arf2->dickweed = "Shanks you";
		$arf2->moose2 = "SUCKT\" '' ~~";
		$arf2->doosh4 = 100;
		$arf2->inttest = 500;
		$arf2->biginttest = 600;
		$arf2->floattest = 4.435;
		$arf2->doubletest = 5.657;
		
		$arf2->save();
		
		$a->fetch(array
		(	"boo"
		));
		
		
		self::assert(count($a->boo) === 1);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);			
	}
	
	function testCommentsThing()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$a->fetch("comment");
			
	}
	
	function testFetch1()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		
		$a->rm();
		
		$arf = new boo();
		$arf->moose = true;
		$arf->barbarastreisznad = "pices of a drem";
		$arf->dickweed = "Shanks you";
		$arf->moose2 = "SUCKT\" '' ~~";
		$arf->doosh4 = 100;
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
		
		$a->fetch("boo");
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo"));
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo"));
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo", "comment"));
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		$a->fetch(array("boo"=>array(), "comment"=>array()));
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->barbarastreisznad === "pices of a drem");
		self::assert($a->boo[0]->dickweed === "Shanks you");
		self::assert($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		self::assert($a->boo[0]->doosh4 === 100);
		self::assert($a->boo[0]->inttest === 500);
		self::assert($a->boo[0]->biginttest === 600);
		self::assert($a->boo[0]->floattest === 4.435);
		self::assert($a->boo[0]->doubletest === 5.657);
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		$a->fetch(array("boo"=>array("members"=>array("moose")), "comment"=>array()));
		self::assert(count($a->boo) === 2, "So apparently ".count($a->boo)." doesn't pass for 2 these days");
		self::assert($a->boo[0]->moose === true);
		self::assert(gotCeptFromInvalidAccess($a->boo[0], "barbarastreisznad"));
		
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");
		
		
		
		$a->fetch(array( "boo"=>array("members"=>array("moose", "dickweed")), "comment"=>array("members"=>array("name")) ));
		self::assert(count($a->boo) === 2);
		self::assert($a->boo[0]->moose === true);
		self::assert($a->boo[0]->dickweed === "Shanks you");	
		self::assert(gotCeptFromInvalidAccess($a->boo[0], "barbarastreisznad"));
		
		self::assert(count($a->comment) === 3);
		self::assert($a->comment[0]->name === "your mom");		
	}
	
	function testFetch2()
	{	$a = setupTestDatabase();
		sqool::debug(false);
		
		$booObj = $a->fetch("boo", 1);
		$booObj->fetch();
		
		self::assert($booObj->moose === true);
		self::assert($booObj->barbarastreisznad === "pices of a drem");
		self::assert($booObj->dickweed === "Shanks you");
		self::assert($booObj->moose2 === "SUCKT\" '' ~~");
		self::assert($booObj->doosh4 === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);
				
		$a->fetch("boo");
		$booObj = $a->boo[0];
		$booObj->fetch();
		
		self::assert($booObj->moose === true);
		self::assert($booObj->barbarastreisznad === "pices of a drem");
		self::assert($booObj->dickweed === "Shanks you");
		self::assert($booObj->moose2 === "SUCKT\" '' ~~");
		self::assert($booObj->doosh4 === 100);
		self::assert($booObj->inttest === 500);
		self::assert($booObj->biginttest === 600);
		self::assert($booObj->floattest === 4.435);
		self::assert($booObj->doubletest === 5.657);
		
		$booObj = $a->fetch("boo", 2);
		$booObj->fetch();
		
		self::assert($booObj->moose === false, "WTF");
		self::assert($booObj->barbarastreisznad === "drankFU", "WTF");
		self::assert($booObj->dickweed === "lets go", "WTF");
		self::assert($booObj->moose2 === "SUCKT2\" '' ~~", "WTF");
		self::assert($booObj->doosh4 === 33, "WTF");
		self::assert($booObj->inttest === 44, "WTF");
		self::assert($booObj->biginttest === 555, "WTF");
		self::assert($booObj->floattest === 5.6665, "WTF");
		self::assert($booObj->doubletest === 11.890890, "WTF");
		
		
		$booObj = $a->fetch("boo", 1);
		$booObj->fetch("moose");
		
		self::assert($booObj->moose === true);
		self::assert(gotCeptFromInvalidAccess($booObj, "barbarastreisznad"));
		
		
		
		// sort test
		
		
		$a->fetch(array("boo"=>array("ranges"=>array(1,1))));
		
		self::assert(count($a->boo) === 1);
		
		self::assert($a->boo[0]->moose === false, "WTF");
		self::assert($a->boo[0]->barbarastreisznad === "drankFU", "WTF");
		self::assert($a->boo[0]->dickweed === "lets go", "WTF");
		self::assert($a->boo[0]->moose2 === "SUCKT2\" '' ~~", "WTF");
		self::assert($a->boo[0]->doosh4 === 33, "WTF");
		self::assert($a->boo[0]->inttest === 44, "WTF");
		self::assert($a->boo[0]->biginttest === 555, "WTF");
		self::assert($a->boo[0]->floattest === 5.6665, "WTF");
		self::assert($a->boo[0]->doubletest === 11.890890, "WTF");
		
		
		$a->fetch(array("boo"=>array("sort"=>array(sqool::a, "inttest"))));
		
		self::assert(count($a->boo) === 3);
		self::assert($a->boo[0]->inttest < $a->boo[1]->inttest);
		self::assert($a->boo[1]->inttest < $a->boo[2]->inttest);
		
		$a->fetch(array("boo"=>array("sort"=>array(sqool::a, "inttest", "floattest"))));
		
		
		// cond test
		
		$a->fetch(array("boo"=>array("cond"=>array("doosh4 =",33))));
		self::assert(count($a->boo) === 1);
		self::assert($a->boo[0]->doosh4 === 33);
		
		$a->fetch(array("boo"=>array("cond"=>array("doosh4 >",33,"&& inttest <", 400))));
		self::assert(count($a->boo) === 1);
		self::assert($a->boo[0]->doosh4 === 77);
		self::assert($a->boo[0]->inttest === 88);
		
	}
	
	function testObjects()
	{	$a = setupTestDatabase();
		sqool::debug(false);
		
		$egg2 = new crack();
		$egg2->bob_ject = new boo();
		$egg2->bob_ject->moose = true;
		$egg2->bob_ject->moose2 = "boooosh";
		$insertedEgg = $a->insert($egg2);
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
		self::assert($insertedEgg->bob_ject->moose === true);
		self::assert($insertedEgg->bob_ject->moose2 === "boooosh");
		
		
		$egg3 = new crack();	// (this will be crack2)
		$egg3->crackJacket = new crack();		
		$egg3->crackJacket->necklace = 5;
		$egg3->necklace = 9;
		$insertedEgg = $a->insert($egg3);
		
		$insertedEgg->fetch();		
		$insertedEgg->crackJacket->fetch();		
		self::assert($insertedEgg->necklace === 9);
		self::assert($insertedEgg->crackJacket->necklace === 5);
		
		$boo1 = $a->fetch("boo", 1);
		
		$egg3 = new crack();
		$egg3->bob_ject = $boo1;
		$egg3->necklace = 9;
		$insertedEgg = $a->insert($egg3);
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
		self::assert($insertedEgg->necklace === 9);		
		self::assert($insertedEgg->bob_ject->moose === true);
		self::assert($insertedEgg->bob_ject->barbarastreisznad === "pices of a drem");
		self::assert($insertedEgg->bob_ject->dickweed === "Shanks you");
		self::assert($insertedEgg->bob_ject->moose2 === "SUCKT\" '' ~~");
		self::assert($insertedEgg->bob_ject->doosh4 === 100);
		self::assert($insertedEgg->bob_ject->inttest === 500);
		self::assert($insertedEgg->bob_ject->biginttest === 600);
		self::assert($insertedEgg->bob_ject->floattest === 4.435);
		self::assert($insertedEgg->bob_ject->doubletest === 5.657);
		
		$insertedEgg->bob_ject->dickweed = "bizarroooo";
		$insertedEgg->bob_ject->save();
		$boo1 = $a->fetch("boo", 1);
		$boo1->fetch();
		self::assert($boo1->dickweed === "bizarroooo");
		
		
		$egg3 = new crack();
		$egg3->bob_ject = $boo1;
		$egg3->bob_ject->moose = true;
		$egg3->necklace = 9;
		$insertedEgg = $a->insert($egg3);
		
		echo "saving<br>";
		
		$insertedEgg->bob_ject = $boo1;
		$boo1->dickweed = "alright then";
		$insertedEgg->save();
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();		
		self::assert($insertedEgg->necklace === 9);
		self::assert($insertedEgg->bob_ject->moose === true);
		self::assert($insertedEgg->bob_ject->dickweed === "alright then");
		
		$insertedEgg->bob_ject = new boo();
		$insertedEgg->bob_ject->dickweed = "whatever dude";
		$insertedEgg->j = new condor();
		$insertedEgg->save();
		
		$insertedEgg->fetch();		
		$insertedEgg->bob_ject->fetch();
		self::assert($insertedEgg->bob_ject->dickweed === "whatever dude");
	}
	
	function testFetchObjects()
	{	sqool::debug(true);
		$a = setupTestDatabase();
		create2cracks($a);
		
		echo "fetching<br>";
		
		$crack2 = $a->fetch("crack", 2);
		$crack2->fetch(array
		(	"necklace",
			"crackJacket"
		));
		
		self::assert($crack2->necklace == 9);
		self::assert($crack2->crackJacket->necklace == 5);
		
		// The following selects all crack objects (should be 6 or so of them) and attempts to get the object-member crackJacket from each of them
		$a->fetch(array("crack"=>array("members"=>array("crackJacket"))));
		
		self::assert(count($a->crack) == 6);
		// the following assertions can only be done when ID is set to public (it is normally private)
		/*	self::assert($a->crack[0]->ID === 1);
			self::assert($a->crack[1]->ID === 2);
				self::assert($a->crack[1]->crackJacket->ID == 3);
				echo "id is: ".$a->crack[4]->crackJacket->ID."<br>";
			self::assert($a->crack[2]->ID === 3);
			self::assert($a->crack[3]->ID === 4);
				self::assert($a->crack[3]->crackJacket->ID == 5);
				echo "id is: ".$a->crack[4]->crackJacket->ID."<br>";
			self::assert($a->crack[4]->ID === 5);
				self::assert($a->crack[4]->crackJacket->ID == 6);
			self::assert($a->crack[5]->ID === 6);
		*/
				
		self::assert($a->crack[0]->crackJacket === null);
		self::assert(get_class($a->crack[1]->crackJacket) === "crack");
			self::assert($a->crack[1]->crackJacket->necklace === 5);
		self::assert($a->crack[2]->crackJacket === null);
		self::assert(get_class($a->crack[3]) === "crack");
			self::assert(get_class($a->crack[3]->crackJacket) === "crack");
			self::assert(get_class($a->crack[3]->crackJacket->crackJacket) === "crack");
		self::assert(get_class($a->crack[4]) === "crack");
			self::assert(get_class($a->crack[4]->crackJacket) === "crack");
			self::assert($a->crack[4]->crackJacket->crackJacket === null);
		self::assert(get_class($a->crack[5]) === "crack");
			self::assert($a->crack[5]->crackJacket === null);
		
		
		// The following selects all crack objects (should be 6 or so of them) and attempts to get a couple different object-members from each of them
		$a->fetch(array("crack"=>array("members"=>array("bob_ject", "crackJacket", "necklace"))));
		
		self::assert(count($a->crack) == 6);
		self::assert(get_class($a->crack[0]->bob_ject) === "boo");
		self::assert($a->crack[0]->bob_ject->moose === true);
		self::assert($a->crack[0]->bob_ject->moose2 === "boooosh");
		self::assert($a->crack[0]->crackJacket === null);
		
		self::assert(get_class($a->crack[1]->crackJacket) === "crack");
		self::assert($a->crack[1]->crackJacket->necklace === 5);
		self::assert($a->crack[1]->necklace === 9);
		self::assert($a->crack[1]->bob_ject === null);
		
		self::assert(get_class($a->crack[2]) === "crack");
		self::assert($a->crack[2]->necklace === 5);
		self::assert($a->crack[2]->bob_ject === null);
		self::assert($a->crack[2]->crackJacket === null);
		
		self::assert(get_class($a->crack[3]) === "crack");
		self::assert(get_class($a->crack[3]->crackJacket) === "crack");
		self::assert(get_class($a->crack[3]->crackJacket->crackJacket) === "crack");
		self::assert($a->crack[3]->bob_ject === null);
		
		self::assert(get_class($a->crack[4]) === "crack");
		self::assert(get_class($a->crack[4]->crackJacket) === "crack");
		self::assert($a->crack[4]->crackJacket->crackJacket === null);
		self::assert($a->crack[4]->bob_ject === null);
		
		self::assert(get_class($a->crack[5]) === "crack");
		self::assert($a->crack[5]->crackJacket === null);
		self::assert($a->crack[5]->bob_ject === null);
		
	}
	
	function nulls()
	{	sqool::debug(true);
		$a = setupTestDatabase();
		create2cracks($a);
		
		echo "save/insert nulls<br>";
		$egg2 = new crack();
		$egg2->bob_ject = null;
		$egg2->crackJacket = new crack();
		$egg2->necklace = 89;
		$insertedEgg = $a->insert($egg2);
		
		$insertedEgg->fetch();
		
		self::assert($insertedEgg->necklace === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert(get_class($insertedEgg->crackJacket) === "crack");
		
		$insertedEgg->crackJacket = null;
		$insertedEgg->save();
		
		$insertedEgg->fetch();
		self::assert($insertedEgg->necklace === 89);
		self::assert($insertedEgg->bob_ject === null);
		self::assert($insertedEgg->crackJacket === null);
		
		$a->fetch(array("crack"=>array("members"=>array("bob_ject"))));
		foreach($a->crack as $c)
		{	self::assert($c->bob_ject === null);
		}
	}
	
	function addMemebers()
	{	$a = setupTestDatabase();
	
		$x = new metamorph();
		
		$found = false;
		try
		{	$x->save();
		}catch(cept $c)
		{	$found = true;
		}
		self::assert($found);
		
		$x->clear();
		metamorph::$sclass = "int : a";
		
		$x = new metamorph();
		$x->a = 5;
		$x->save();
		
	}
	
	function objectsWithLists()
	{	$a = setupTestDatabase();
		
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
		
		$newLo1 = $a->insert($lo1);
		$newLo2 = $a->insert($lo2);
		$newLo3 = $a->insert($lo3);
		$newLo4 = $a->insert($lo4);
		
		var_dump($newLo1);
		
	}
}

sqoolTests::run();

//$fp = fopen('php://stdin', 'r');
//fgets($fp, 2);

?>
