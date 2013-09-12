<?php
	require_once("cept.php"); // exceptions with stack traces
	require_once("SQOOL_0.5.php");
	    
	class boo extends sqool
	{       function boo()
	        {       $this->make("boo",
	                'string:        moose
	                 bool:          barbarastreiszand
	                 float:         flu
	                 float:         moose2
	                 int:           fly
	                ');
	        }
	}
	
	$a = sqool::connect("root", "", "garbo3007");
	$a->debug(true); // this will print out all the SQL queries that run
	
	/*
	$object = new boo();
	$object->moose = "some string value";
	$newObject = $a->insert($object);
	        
	$newObject->fly = 45;   
	$newObject->save();
	
	*/        
	$a->fetch(array
	(       "boo"
	));
	echo "count($a->boo) == ".count($a->boo)."<br>\n<br>\n";
	
	        
	$boo0 = $a->boo[0]; 
	echo '$boo0->moose == "'.$boo0->moose.'"'."<br>\n";
	echo '$boo0->barbarastreiszand == "'.$boo0->barbarastreiszand.'"'."<br>\n";
	echo '$boo0->flu == '.$boo0->flu."<br>\n";
	echo '$boo0->moose2 == '.$boo0->moose2."<br>\n";
	echo '$boo0->fly == '.$boo0->fly."<br>\n";
	
	
	
/*	$a = new sqool("root", "", "garbo3000");//"tetrudco_freshen", "Frenchy189AoP9&^", "tetrudco_sqltest");//
	$a->queue();
	$a->sql($r, "SHOW TABLES");
	$a->sql($r2, "SHOW TABLES");
	print_r($r);
	print_r($r2);
	$a->go();
	print_r($r);
	print_r($r2);
	exit;
	
	class boo extends sqool
	{	function boo()
		{	$this->make("boo",
			'string: 	moose
			 bool: 		barbarastreisznad
			 float: 	dickweed 
			'.//tanks list: backwash
			'float: 	moose2
			 int:		doosh4
			');
		}
	}
	
	class anotherone extends sqool
	{	function anotherone()
		{	$this->make("boo",
			'string: 	moose2
			 bool: 		barbarastreisznad2
			 float: 	dickweed2
			');
		}
	}
	
	$arf = new boo();
	$arf->moose = "gabo";
	
	$jank = new boo();
	$jank->dickweed = 4.35;
	
	$a = sqool::connect("root", "", "garbo3006");//"tetrudco_freshen", "Frenchy189AoP9&^", "tetrudco_sqltest");//
*/	
	/*$result = $a->sql("SET @a := 5");
	print_r($result);
	exit;
	*/
/*	
	$a->debug(true);
	//$a->queue();
	
	//$arf2 = $a->insert($arf);
	//$jank = $a->insert($jank);
	
	$arf2->doosh4 = "bang!";
	
	//print_r($arf);
	//print_r($arf2);
	//print_r($a);
	
	
	//$arf2->save();
	
	$a->fetch(array
	(	"boo"
	));
	
	print_r($a);
	
	$boo0 = $a->boo[0]; 
	
	print_r($boo0);
	
	//$a->go();	
*/	
	
	$fp = fopen('php://stdin', 'r');
	fgets($fp, 2);
?>

