<?php

require_once 'vendor/autoload.php';
require_once 'wikidata-utils.php';

//----------------------------------------------------------------------------------------
// Convert CSL author name to a simple string
function csl_author_to_name($author)
{
	$name = '';	
	
	// Get name as string
	$parts = array();
	if (isset($author->given))
	{
		$parts[] = $author->given;
	}
	
	if (isset($author->family))
	{
		$parts[] = $author->family;
	}
	
	if (isset($author->suffix))
	{
		$parts[] = $author->suffix;
	}
		
	if (count($parts) > 0)
	{								
		$name = join(' ', $parts);	
		$name = preg_replace('/\s\s+/u', ' ', $name);
	}
	else
	{
		if (isset($author->literal))
		{
			$name = $author->literal;
		}								
	}
	
	return $name;
}

?>
