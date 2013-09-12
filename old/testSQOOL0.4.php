<?php
	require_once("SQOOL_0.4.php");	
	
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
	*/
	class boo extends sqoolobj
	{	function boo()
		{	$this->make("boo",
			'string: 	moose
			 bool: 		barbarastreisznad
			 float: 	dickweed
			 tanks list: backwash
			 float: 	moose2
			 int:		doosh4
			');
		}
	}
	
	$arf = new boo();
	$arf->moose = "gabo";
	
	$jank = new boo();
	$jank->dickweed = 4.35;
	
	$a = new sqool("root", "", "garbo3001");//"tetrudco_freshen", "Frenchy189AoP9&^", "tetrudco_sqltest");//
	
	$a->debug(true);
	$a->queue();
	
	$arf2 = $a->insert($arf);
	$jank = $a->insert($jank);
	
	$arf2->doosh4 = "bang!";
	
	print_r($arf);
	print_r($arf2);
	
	$arf2->save();
	
	$a->go();
	
	$stuff = $a->fetch(array
	(	"boo"
	));
	
	var_dump($stuff);
	
	//$r = $a->sqlquery("SHOW TABLES;SHOW TABLES;");
	//print_r($r);
	
	
	//$a->printcrap();
	
	
	
	
	/*$blah = new mysqli("localhost", "root", "", "garbo");

	$resultSet = array();
	if($blah->multi_query('INSERT INTO boo (whatever) VALUES (34);'))
	{	do	// store first result set 
		{	if($result = $blah->store_result())
			{	$results = array();
				while($row = $result->fetch_row())
				{	$results[] = $row;
				}
				$result->free();
				$resultSet[] = $results;
			}else
			{	$resultSet[] = array();
			}
		}while($blah->next_result());
	}
	
	
	if($blah->errno)
	{	echo "* The error is: ERROR(".$blah->errno.") ".$blah->error . "<br>";
	}
	*/
	
	
	$fp = fopen('php://stdin', 'r');
	fgets($fp, 2);
?>

