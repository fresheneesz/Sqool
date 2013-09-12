<?php
require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require_once('SQOOL_0.6.php');
//sqool::debug(true); // this will print out all the SQL queries that run
sqool::debug(false);

class newReporter extends HtmlReporter
{	function newReporter()
	{	$this->HtmlReporter();
	}
	
	function paintException($e)
	{	echo $e;
		$this->paintFail("Uncaught exception above");
	}
	/*
	function paintPass($message)
	{	parent::paintPass($message);
		print "<span class=\"pass\">Pass</span>: ";
		$breadcrumb = $this->getTestList();
		array_shift($breadcrumb);
		print implode("-&gt;", $breadcrumb);
		print "->$message<br />\n";
	}
	//*/
	
	function _getCss()
	{	return parent::_getCss() . ' .pass { color: green; }';
	}
}

class newTestCase extends UnitTestCase
{	/**
     *    Invokes run() on all of the held test cases, instantiating
     *    them if necessary.
     *    @param SimpleReporter $reporter    Current test reporter.
     *    @access public
     */
    function run(&$reporter) {
        $reporter->paintGroupStart($this->getLabel(), count($this->getTests()));
        foreach ($this->getTests() as $method)
        {	$context = &SimpleTest::getContext();
	        $context->setTest($this);
	        $context->setReporter($reporter);
	        $this->_reporter = &$reporter;
	        $started = false;
	        
            if ($reporter->shouldInvoke($this->getLabel(), $method)) {
                $this->skip();
                if ($this->_should_skip) {
                    break;
                }
                if (! $started) {
                    $reporter->paintCaseStart($this->getLabel());
                    $started = true;
                }
                $invoker = &$this->_reporter->createInvoker($this->createInvoker());
                $invoker->before($method);
                $invoker->invoke($method);
                $invoker->after($method);
            }
            
	        if ($started) {
	            $reporter->paintCaseEnd($this->getLabel());
	        }
	        unset($this->_reporter);
        }
        $reporter->paintGroupEnd($this->getLabel());
        return $reporter->getStatus();
    }
}


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

function setupTestDatabase()
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
	
	return $a;
}	


class sqoolTests extends newTestCase 
{	function testFetchOnNonExistantDB()
	{	
		$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$a->fetch(array
		(	"nonExistantClass"
		));
		
		$this->assertTrue(is_array($a->nonExistantClass));
		$this->assertTrue(count($a->nonExistantClass) === 0);
	}
	
	function testInsertDefaults()
	{	$a = sqool::connect("root", "", "garboNonExistant");	// connect to a database that doesn't exist (connects on localhost)
		$a->rm();
		
		$arf = new boo();
		$arf2 = $a->insert($arf);
		
		$a->fetch(array
		(	"boo"
		));
		
		$this->assertTrue(count($a->boo) === 1);
		$this->assertTrue($a->boo[0]->moose === false);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "");
		$this->assertTrue($a->boo[0]->dickweed === "");
		$this->assertTrue($a->boo[0]->moose2 === "");
		$this->assertTrue($a->boo[0]->doosh4 === 0);
		$this->assertTrue($a->boo[0]->inttest === 0);
		$this->assertTrue($a->boo[0]->biginttest === 0);
		$this->assertTrue($a->boo[0]->floattest === floatval(0));
		$this->assertTrue($a->boo[0]->doubletest === floatval(0));		
	}
	
	function testInsert()
	{	$a = setupTestDatabase();
		
		
		$a->fetch(array
		(	"boo"
		));
		
		$this->assertTrue(count($a->boo) === 3);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);		
	}
	
	/*function testInsertBadValues()
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
		
		$this->assertTrue(count($a->boo) === 1);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);		
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
		
		
		$this->assertTrue(count($a->boo) === 1);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);			
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
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo"));
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo"));
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);
		
		$a->fetch(array("boo", "comment"));
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);
		$this->assertTrue(count($a->comment) === 3);
		$this->assertTrue($a->comment[0]->name === "your mom");
		
		$a->fetch(array("boo"=>array(), "comment"=>array()));
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->barbarastreisznad === "pices of a drem");
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($a->boo[0]->doosh4 === 100);
		$this->assertTrue($a->boo[0]->inttest === 500);
		$this->assertTrue($a->boo[0]->biginttest === 600);
		$this->assertTrue($a->boo[0]->floattest === 4.435);
		$this->assertTrue($a->boo[0]->doubletest === 5.657);
		$this->assertTrue(count($a->comment) === 3);
		$this->assertTrue($a->comment[0]->name === "your mom");
		
		$a->fetch(array("boo"=>array("members"=>array("moose")), "comment"=>array()));
		$this->assertTrue(count($a->boo) === 2, "So apparently ".count($a->boo)." doesn't pass for 2 these days");
		$this->assertTrue($a->boo[0]->moose === true);
		try
		{	$gotCept = false;
			$a->boo[0]->barbarastreisznad;
		}catch(cept $x)
		{	$gotCept = true;
		}
		$this->assertTrue($gotCept === true);
		
		$this->assertTrue(count($a->comment) === 3);
		$this->assertTrue($a->comment[0]->name === "your mom");
		
		
		
		$a->fetch(array( "boo"=>array("members"=>array("moose", "dickweed")), "comment"=>array("members"=>array("name")) ));
		$this->assertTrue(count($a->boo) === 2);
		$this->assertTrue($a->boo[0]->moose === true);
		$this->assertTrue($a->boo[0]->dickweed === "Shanks you");
		//$this->expectException();
		try
		{	$gotCept = false;
			$a->boo[0]->barbarastreisznad;
		}catch(cept $x)
		{	$gotCept = true;
		}
		$this->assertTrue($gotCept === true);
		
		$this->assertTrue(count($a->comment) === 3);
		$this->assertTrue($a->comment[0]->name === "your mom");		
	}
	
	function testFetch2()
	{	$a = setupTestDatabase();
		
		$booObj = $a->fetch("boo", 1);
		$booObj->fetch();
		
		$this->assertTrue($booObj->moose === true);
		$this->assertTrue($booObj->barbarastreisznad === "pices of a drem");
		$this->assertTrue($booObj->dickweed === "Shanks you");
		$this->assertTrue($booObj->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($booObj->doosh4 === 100);
		$this->assertTrue($booObj->inttest === 500);
		$this->assertTrue($booObj->biginttest === 600);
		$this->assertTrue($booObj->floattest === 4.435);
		$this->assertTrue($booObj->doubletest === 5.657);
				
		$a->fetch("boo");
		$booObj = $a->boo[0];
		$booObj->fetch();
		
		$this->assertTrue($booObj->moose === true);
		$this->assertTrue($booObj->barbarastreisznad === "pices of a drem");
		$this->assertTrue($booObj->dickweed === "Shanks you");
		$this->assertTrue($booObj->moose2 === "SUCKT\" '' ~~");
		$this->assertTrue($booObj->doosh4 === 100);
		$this->assertTrue($booObj->inttest === 500);
		$this->assertTrue($booObj->biginttest === 600);
		$this->assertTrue($booObj->floattest === 4.435);
		$this->assertTrue($booObj->doubletest === 5.657);
		
		$booObj = $a->fetch("boo", 2);
		$booObj->fetch();
		
		$this->assertTrue($booObj->moose === false, "WTF");
		$this->assertTrue($booObj->barbarastreisznad === "drankFU", "WTF");
		$this->assertTrue($booObj->dickweed === "lets go", "WTF");
		$this->assertTrue($booObj->moose2 === "SUCKT2\" '' ~~", "WTF");
		$this->assertTrue($booObj->doosh4 === 33, "WTF");
		$this->assertTrue($booObj->inttest === 44, "WTF");
		$this->assertTrue($booObj->biginttest === 555, "WTF");
		$this->assertTrue($booObj->floattest === 5.6665, "WTF");
		$this->assertTrue($booObj->doubletest === 11.890890, "WTF");
		
		
		$booObj = $a->fetch("boo", 1);
		$booObj->fetch("moose");
		
		$this->assertTrue($booObj->moose === true);
		try
		{	$gotCept = false;
			$booObj->barbarastreisznad;
		}catch(cept $x)
		{	$gotCept = true;
		}
		$this->assertTrue($gotCept === true);
		
		
		
		// sort test
		
		
		$a->fetch(array("boo"=>array("ranges"=>array(1,1))));
		
		$this->assertTrue(count($a->boo) === 1);
		
		$this->assertTrue($a->boo[0]->moose === false, "WTF");
		$this->assertTrue($a->boo[0]->barbarastreisznad === "drankFU", "WTF");
		$this->assertTrue($a->boo[0]->dickweed === "lets go", "WTF");
		$this->assertTrue($a->boo[0]->moose2 === "SUCKT2\" '' ~~", "WTF");
		$this->assertTrue($a->boo[0]->doosh4 === 33, "WTF");
		$this->assertTrue($a->boo[0]->inttest === 44, "WTF");
		$this->assertTrue($a->boo[0]->biginttest === 555, "WTF");
		$this->assertTrue($a->boo[0]->floattest === 5.6665, "WTF");
		$this->assertTrue($a->boo[0]->doubletest === 11.890890, "WTF");
		
		
		$a->fetch(array("boo"=>array("sort"=>array(sqool::a, "inttest"))));
		
		$this->assertTrue(count($a->boo) === 3);
		$this->assertTrue($a->boo[0]->inttest < $a->boo[1]->inttest);
		$this->assertTrue($a->boo[1]->inttest < $a->boo[2]->inttest);
		
		$a->fetch(array("boo"=>array("sort"=>array(sqool::a, "inttest", "floattest"))));
		
		
		// cond test
		
		$a->fetch(array("boo"=>array("cond"=>array("doosh4 =",33))));
		$this->assertTrue(count($a->boo) === 1);
		$this->assertTrue($a->boo[0]->doosh4 === 33);
		
		$a->fetch(array("boo"=>array("cond"=>array("doosh4 >",33,"&& inttest <", 400))));
		$this->assertTrue(count($a->boo) === 1);
		$this->assertTrue($a->boo[0]->doosh4 === 77);
		$this->assertTrue($a->boo[0]->inttest === 88);
		
	}
	
	function testFetch3()
	{	sqool::debug(true);
		$a = setupTestDatabase();
		
		$egg2 = new crack();
		$egg2->bob_ject = new boo();
		$egg2->bob_ject->moose = true;
		$egg2->bob_ject->moose2 = "boooosh";
		$a->insert($egg2);
		
		$egg3 = new crack();
		$egg3->crackJacket = new crack();		
		$egg3->crackJacket->necklace = 5;
		$egg3->necklace = 9;
		$a->insert($egg3);
		
		$boo1 = $a->fetch("boo", 1);
		
		$x = $whatthefuck;
		
		$egg3 = new crack();
		$egg3->bob_ject = $boo1;
		$egg3->necklace = 9;
		$a->insert($egg3);
	}
}

$test = new sqoolTests();
$test->run(new newReporter());

$fp = fopen('php://stdin', 'r');
fgets($fp, 2);

?>
