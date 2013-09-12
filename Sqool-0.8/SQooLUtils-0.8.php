<?php
/*	See http://www.btetrud.com/Sqool/ for documentation

	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2009, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

require_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces


class SqoolUtils {

	// **** Used by SqoolExtensionSystem ****

    // merges associative arrays
	// keys of array2 will take precedence
	public static function assarray_merge($array1, $array2)
	{	foreach($array2 as $k => $v)
		{	$array1[$k] = $v;
		}
		return $array1;
	}

	// calls function references, even if they start with 'self::' or '$this->'
	// $params should be an array of parameters to pass into $function
	public static function call_function_ref($thisObject, $function, $params)
	{	if(gettype($function) === 'object' )
        {   return call_user_func_array($function, $params);
        } else if('$this->' == substr($function, 0, 7))
		{	return call_user_func_array(array($thisObject, substr($function, 7)), $params);
		}else if('self::' == substr($function, 0, 6))
		{	return call_user_func_array(get_called_class()."::".substr($function, 6), $params);
		}else
		{	return call_user_func_array($function, $params);
		}
	}



	// **** Not used by SqoolExtensionSystem ****

	// returns all the classes an object or class inherits from (the list of class parents basically)
	// returns the root class first, the object's class last
	public static function getFamilyTree($objectOrClassName)
	{	// get the className for $objectOrClassName
		if(is_object($objectOrClassName))
		{	$className = get_class($objectOrClassName);
		}else
		{	$className = $objectOrClassName;
		}

		$classList = array($className);
		while(true)
		{	// get the next parent class up the inheritance hierarchy
			$lastClassFirst = array_reverse($classList);
			$nextClass = get_parent_class($lastClassFirst[0]);
			if($nextClass === false)
			{	break;	// no more classes (it must be defined in the root-parent
			}

			$classList[] = $nextClass;
		}

		return array_reverse($classList);
	}

	// returns a list of parents class name (in an inheritance hierarchy) that the method was originally defined/overridden in
	// $objectOrClassName is the object or class to start looking for the method
	public static function methodIsDefinedIn($objectOrClassName, $methodName)
	{	$family = self::getFamilyTree($objectOrClassName);

		$resultClasses = array();
		foreach($family as $c)
		{	if( in_array($methodName, self::get_defined_class_methods($c)) )
			{	$resultClasses[] = $c;
			}
		}
		return $resultClasses;
	}

	// gets the visible methods actually defined in a given class
	// filters out abstract methods
	public static function get_defined_class_methods($className)
	{	if(false == class_exists($className))
		{	throw new cept("There is no defined class named '".$className."'");
		}

		$reflect = new ReflectionClass($className);
		$methods = $reflect->getMethods();
		$classOnlyMethods = array();
		foreach($methods as $m)
		{	if ($m->getDeclaringClass()->name == $className && !$m->isAbstract())
			{	$classOnlyMethods[] = $m->name;
			}
		}
		return $classOnlyMethods;
	}

	// changes an object to a given class type (used in classCast_callMethod)
	public static function changeClass(&$obj, $newClass)
	{	if(false == class_exists($newClass))
		{	throw new Exception("non-existant class '".$newClass."'");
		}
		//if( false == in_array($newClass, self::getFamilyTree($obj)) )
		//{	throw new Exception("Attempting to cast an object to a non-inherited type");
		//}
		$obj = unserialize(preg_replace		// change object into type $new_class
		(	"/^O:[0-9]+:\"[^\"]+\":/i",
			"O:".strlen($newClass).":\"".$newClass."\":",
			serialize($obj)
		));
	}

	// calls a method on a class casted object
	public static function classCast_callMethod(&$obj, $newClass, $methodName, $methodArgs=array())
	{	$oldClass = get_class($obj);

		self::changeClass($obj, $newClass);
		$result = call_user_func_array(array($obj, $methodName), $methodArgs);	// get result of method call
		self::changeClass($obj, $oldClass);	// change back

		return $result;
	}

	public static function in_array_caseInsensitive($thing, $array)
	{	$loweredList = array();
		foreach($array as $a)
		{	$loweredList[] = strtolower($a);
		}
		return in_array(strtolower($thing), $loweredList);
	}

	public static function array_insert($array, $pos, $value)
	{	$array2 = array_splice($array,$pos);
	    $array[] = $value;
	    return array_merge($array,$array2);
	}

	public static function isAssoc($arr) {
		return is_array($arr) && count($arr) !== 0 && array_keys($arr) !== range(0, count($arr) - 1);
	}

}
