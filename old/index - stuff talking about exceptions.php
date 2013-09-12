<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Sqool</title>
		
	<script src="prettify/prettify.js" type="text/javascript"></script>
	<link rel="stylesheet" type="text/css" href="prettify/prettify.css" />
	
	<script src="jquery.js" type="text/javascript"></script>
	<link rel="shortcut icon" href="pics/Sqool-Icon-HW16.png" type="image/x-icon">
	<link rel="stylesheet" type="text/css" href="sqool.css" />
	
	<script>
		function replaceAll(haystack, needle, replacement)
		{	var temp = haystack.split(needle);
			return temp.join(replacement);
		}
		
		$(function()
		{	$("pre").each(function()
			{	newHTML = replaceAll($(this).html(), "<", "&lt;");
				$(this).html(newHTML);
			});
			prettyPrint();
		});
	</script>
</head>
	
<body>
	<div class="center">
		<img src="pics/sqool-icon2.png"><br>
		Putting a friendly face on SQL since August 2009!
	</div>
	
	<div class="offwhitebox">
		<ul>
			<li>Requires no configuration</li>
			<li>Requires no database set-up other than having a working username and password</li>
			<li>Allows you to create and use database entities just like PHP objects</li>
			<li>Is extremely simple</li>
			<li>Is extendable</li>
		</ul>
		<p>	Sqool stands for Standard Query Object-Oriented Language and is a user-friendly Object Relational Database Facade for PHP
			(a <a href="http://en.wikipedia.org/wiki/Database_abstraction_layer">database abstraction layer</a> that mimics an object-oriented database management system, for PHP). 
			It is similar to an <a href="http://en.wikipedia.org/wiki/Object-relational_mapping">ORM</a>, but doesn't need you to explicitly specify the mapping - you can just use it without worrying about the database layer!
			Sqool is made to have both extendable operations and extendable optimizations. 
			Right now, Sqool uses php's mysqli extension. Hopefully in the future, Sqool will also provide a way to plug in support for other database systems.
		</p>
		<p>Currently Sqool supports primitives and objects. Lists are almost fully supported.</p>
		<p>	Note about exceptions: I experimented with using exceptions for developing Sqool. As such, I made the small class 'cept' which makes exceptions both easier to write and adds a useful stacktrace to the thrown cept. I'm pretty happy with using exceptions, but I'm convinced it is because I almost *never* catch the errors. Instead I let them happen and use them to debug. The exceptions also serve to tell the user of the class if they're using it wrong. For information about the downside of exceptions, see
			<a href="http://www.joelonsoftware.com/items/2003/10/13.html">this article by Joel Spolsky</a>.
    		<a href="http://www.joelonsoftware.com/articles/Wrong.html">This other related article by Joel</a>
			is also pretty interesting.
		</p>
		
		<p>	Download the php: <a href="Sqool.zip">Sqool.zip</a><br>
			Download the php and tutorials here: <a>Sqool-Tutorials.zip</a>
		</p>
	</div>
	
	<div class="mediumTitle">
		Tutorial
	</div>
	<div class="wideoffwhitebox">
		<div class="whitebox">
			One of the great things about Sqool is that there is nothing to set up. All you need to do is download cept.php and SQOOL_0.6.php, make sure you have a database server set up with your name and password, then run the following source code (using your name and password instead). You don't even have to change the database name - it will create it for you if you don't already have that database on your server.
		</div>
<pre class="prettyprint" id="PHP">	
	require_once("SQOOL_0.6.php");
</pre>
		<div class="whitebox">
			<p>	The following class extends 'sqool' (the only class defined by the Sqool library). 
				To define a Sqool class type, you need to create a function called 'sclass'. 
			</p>
			<p>	This method will affectively only be executed once (when you instantiate the first object of this type), and defines the members you want to have in the class and their types, just like you would in a stronly typed language like C++.
				The members are written inside a string, with the format "type: name   type: name" etc. You don't need to put commas or anything between each member definition, just whitespace.
			</p>
		</div>
<pre class="prettyprint" id="PHP">	
	class boo extends sqool
	{	function sclass()
		{	return 
			'string:	moose
			 bool:		barbarastreiszand
			 string:	flu
			 float:		moose2
			 int:		fly
			 ';
		}
	}
</pre>
		<div class="whitebox">
			<p>	The first thing you should probably do is define a new connection. sqool:connect doesn't actually connect immediately, but it returns a sqool object that represents the database.
				The sqool object ($a in this case) will connect to the database the first time it needs to (lazy connection). 
			</p>
			<p>	Also, since this is a tutorial, its nice to show some debugging information. By turning debugging on (true) with the 'debug' method, the SQL commands sent to the server will be printed out for you to see when it sent. 
				The 'queue' method tells the sqool class ($a) to start storing methods for later execution. Don't worry about it for now, we'll explain it a little later.
			</p>
		</div>
<pre class="prettyprint" id="PHP">	
	$a = sqool::connect("yourUserName", "yourPassword", "aDatabaseName");
	$a->debug(true); // this will print out all the SQL queries that run
	$a->queue();
</pre>
		<div class="whitebox">
			<p>	This next bit of code creates a new object from the user-defined class 'boo'. 
				It then sets one of its string members to "some string value", just as you could with a normal object.
				Lastly, it uses sqool's 'insert' method to insert this new object into the database (represented by $a).
			</p>
			<p>	If the table 'boo' does not exist, don't worry, it will be created for you. 
				Note that it doesn't check to make sure the table exists first, it simply expects the table to be there and creates it if SQL returns a 'table doesn't exist' error.
			</p>
			<p>	Also notice that the 'insert' method returns a new object (captured by $newObject). 
				This new object has an ID, which means it can use the 'save' and 'fetch' methods (explained in just a second). 
				The $object variable, on the other hand, does not have an ID and thus cannot use methods that require it to be "in" a database. 
			</p>
			<p>	Note that 'insert' does not modify its argument, you can safely use this to copy an object from one database into another. 
			</p>
		</div>
<pre class="prettyprint" id="PHP">	
	$object = new boo();
	$object->moose = "some string value";
	$newObject = $a->insert($object);
</pre>
		<div class="whitebox">
			Here, the code assigns $newObject's member 'fly' with the value 45. Then saves it. 
			Since $newObject has an ID and represents an object/table in the database, saving does what you would expect - the value 45 will be written into the database for that member.
		</div>
<pre class="prettyprint" id="PHP">		
	$newObject->fly = 45;   
	$newObject->save();
</pre>
		<div class="whitebox">
			<p>	The next method call is the final major method in the sqool class. 
				As you might expect, 'fetch' gets objects and object members from the database. 
			</p>
			<p>	Fetch can take semi-complicated arguments describing exactly what objects, object members, and sub-object members (etc) to grab from the database,
					however here it is simply getting the object "boo".
			</p>
			<p>	As you can see above, 'boo' is a sqool class type we already defined. 
				The sqool object representing the database ($a in our case) treats database classes as array members (lists of objects of each class type).
				Each of these list-members is named after their class.
				So in this case, the fetch method is geting the whole list of "boo" objects.
			</p>
			<p>	Fetch can have much more complicated arguments than this, but thats for another tutorial.
			</p>
		</div>
<pre class="prettyprint" id="PHP">		
	$a->fetch(array
	(	"boo"
	));
</pre>
		<div class="whitebox">
			<p>	Finally, we can see some data "in motion" so to speak. Remember that 'queue' method from earlier in the tutorial? Now its time to process that queue.
			The 'go' method is what does that. 
			To explain, 'queue' tells the sqool object representing the database to queue up all the database commands you ask it to make,
				so sqool never actually executes them until you say 'go'. 
			</p>
			This is important for two reasons:
			<ol>
				<li>It is faster, because sqool (ideally) only has to make one request to the database server per 'go' (rather than making one request for every sqool call you make).</li>
				<li>It allows optimizations that would otherwise be impossible, including optimization plugins users can write themselves.</li>
			</ol>
			But using the 'queue' and 'go' methods isn't strictly neccessary. 
			But if you don't use 'queue' and 'go', sqool may have to make a database request every time you call a sqool method.
			This can lead to significant delay caused by communication beween the script and the database server.
		</div>
<pre class="prettyprint" id="PHP">		
	$a->go();
</pre>
		<div class="whitebox">
			Lastly, we can print out the data you wrote to the database.
			Here, the number of 'boo' objects is printed, then the code grabs the first 'boo' object from $a's list, and prints out the values of its members.
			As you can see if you run the tutorial script, string members are initialized to the empty string "", bool members are initialized to false, and other members are initialized to 0.
		</div>
<pre class="prettyprint" id="PHP">		
	echo "count($a->boo) == ".count($a->boo)."<br>\n<br>\n";
	
	$boo0 = $a->boo[0]; 
	echo '$boo0->moose == "'	.$boo0->moose.'"'."<br>\n";
	echo '$boo0->barbarastreiszand == "'.$boo0->barbarastreiszand.'"'."<br>\n";
	echo '$boo0->flu == '		.$boo0->flu."<br>\n";
	echo '$boo0->moose2 == '	.$boo0->moose2."<br>\n";
	echo '$boo0->fly == '		.$boo0->fly."<br>\n";
</pre>
	</div>
	
	<div class="mediumTitle">
		Extending Sqool
	</div>
	<div class="offwhitebox">
		Sqool is written to be extendable by anyone. There are three ways to extend Sqool:
		<ul>
			<li><p>Add new internal operations to Sqool. Things like 'save' and 'fetch' use similarly named internal operations to create the needed SQL and interpret the results obtained from the server. You can make your own operations for general or specific use. I'll document the ways to do this sometime soon.</p></li>
			<li><p>Add optimizations to Sqool. After queuing up a list of operations to run, Sqool's "call queue" can be optimized - either by modifying individual operations, or by combining multiple operations into a single operation. Again, I'll document this sometime soon.</p></li>
			<li><p>Add support for other Database Servers. Right now, Sqool only supports mySQL. In the future, I hope it will support the variety of languages that other database abstraction layers like PDO support (note <a href="#PDO">why I don't use PDO</a>). There is no facility for extending Sqool in this way yet. Coming soon.</p></li>
		</ul>
	</div>
	
	<a name="PDO"></a>
	<div class="mediumTitle">
		Why I don't use PDO
	</div>
	<div class="offwhitebox">
		There's a very simple reason: PDO can't do multi-queries. 
		A multi-query is when you bundle multiple SQL commands up and send them to the database server all at once. There are a few reasons to do this:
		<ol><li>It is faster, because there is significant overhead in setting up a request to a database server.</li>
			<li>It allows Sqool to operate like a <a href="http://en.wikipedia.org/wiki/RISC">RISC architecture</a> - multiple simple instructions can create faster and more elegant code than complicated instructions.
				Its true when building a CPU ISA, and its true here too. Rather than building complicated join statements, which return redundant data and are difficult to put together, Sqool does multiple separate SELECTs in a multi-query.
			</li>
		</ol>
		
		If you want to write or help write a Sqool extension for doing queries with PDO, feel free (once I get around to adding that capability...). I almost guarantee it will be slower though.
	</div>
	
	<br>

	<div class="whitebox" style="width:600px">
		Name: <input id="name"><br>
		<textarea id="comment" class="textarea"></textarea><br>
		<input type="submit" id="submitComment" value="Comment">
	</div>
	<div id="comments"></div>
	
	<script type="text/javascript">
		function getComments(submit)
		{	if(submit==undefined){submit = false;}	// default value
			if(submit)
			{	postData = {"submitComment":1, "name":$("#name").val(), "comment": $("#comment").val(), "page":"sqoolIndex"}
				$("#name").val("");
				$("#comment").val("");
			}else
			{	postData = {page:"sqoolIndex"};
			}
			
			$.ajax
			({	"type": "POST", cache: false, "url": "Forum.php",
				"data": postData,
				"success": function(data)
				{	$("#comments").html(data);
				},
				"error":function(XMLHttpRequest, textStatus, errorThrown)
				{	//alert("god dmanit"+textStatus+" and "+errorThrown);
				}
			});
		}
		
		$(function()
		{	getComments();
			
			$("#submitComment").click(function()
			{	if($("#name").val() != "" && $("#comment").val()!="")
				{	getComments(true);
				}
				
			});
		});
	</script>
	<div class="footer">
		Copyright 2009, Billy Tetrud<br>
		BillyAtLima@<img border="0" src="pics/atgmail.png" width="64" height="15">
	</div>

	</body>
</html>
