<?php
	require_once("SQOOL.php");
	sqoolDebug(true);
	
	sqoolAccess("tetrudco_sqltest", "tetrudco_freshen", "Frenchy189AoP9&^");
	
	/*echo "FUCK YOU: " . sqool_getCertainChars("GOD DAMNIT", 0, "_", "azAZ09", $result) ."\n";
	
	echo "Result: " . $result . "\n";
	
	$fp = fopen('php://stdin', 'r');
	fgets($fp, 2);*/
	
	
	$moose1 = sqoolLoad("moose1");
	
	if(sqoolExists("moose1"))
		echo "It exists!<br/>\n";
	
	$moose1->set("key", true);
	$moose1->set("val", "ok then");
	$moose1->set("crap", 45);
	
	echo "And key is: ".$moose1->get("key")."<br/>";
	echo "And val is: ".$moose1->get("val")."<br/>";
	echo "And crap is: ".$moose1->get("crap")."<br/>";
	
?>

