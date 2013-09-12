<?php
	require_once("SQOOL_0.3.php");
	//sqoolDebug(true);
	
	//sqoolAccess("tetrudco_sqltest", "tetrudco_freshen", "Frenchy189AoP9&^");
	
	/*
	echo "Result: " . $result . "\n";
	
	*/
	
	
	/*
	$moose1 = sqoolLoad("moose1");
	
	if(sqoolExists("moose1"))
		echo "It exists!<br/>\n";
	
	$moose1->set("key", true);
	$moose1->set("val", "ok then");
	$moose1->set("crap", 45);
	
	echo "And key is: ".$moose1->get("key")."<br/>";
	echo "And val is: ".$moose1->get("val")."<br/>";
	echo "And crap is: ".$moose1->get("crap")."<br/>";
	*/
	
	
	class boo extends sqoolobj
	{	function boo()
		{	$this->make("boo",
			'string: 	moose
			 bool: 		barbarastreisznad
			 float: 	dickweed
			 tanks list: backwash
			 float: 	moose2
			 int:		doosh3
			');
		}
	}
	
	$arf = new boo();
	$arf->moose = "gabo";
	
	
	$a = new sqool("root", "", "garbo");//"tetrudco_freshen", "Frenchy189AoP9&^", "tetrudco_sqltest");//
	
	$arf2 = $a->insert($arf);
	
	$arf2->doosh3 = "bang!";
	
	print_r($arf);
	print_r($arf2);
	
	$arf2->save();
	
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

