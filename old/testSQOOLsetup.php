<?php
	require_once("SQOOL.php");
	//sqoolDebug(true);
	
	sqoolAccess("tetrudco_sqltest", "tetrudco_freshen", "Frenchy189AoP9&^");
	sqoolSetUp("tetrudco_sqltest", "tetrudco_freshen", "Frenchy189AoP9&^");
	
	sqoolClass("Dmose", "bool:key, string:val, int:crap");
	sqoolClass("ltest", "string:a, float:f, int list:crap");
	sqoolClass("rtest", "ltest:obj, ltest list:val, ltest ref:crap");
	sqoolClass("otest", "int list:key, string list:val, rtest ref list:crap");
	
	if(sqoolCreate("Dmose", "moose1") == false)
	{	echo "Creation error: moose1 already exists.<br/>";
	}
	if(sqoolCreate("ltest", "moose2") == false)
	{	echo "Creation error: moose2 already exists.<br/>";
	}
	if(sqoolCreate("rtest", "moose3") == false)
	{	echo "Creation error: moose3 already exists.<br/>";
	}
	if(sqoolCreate("otest", "moose4") == false)
	{	echo "Creation error: moose4 already exists.<br/>";
	}
?>
