<?php

require_once 'vendor/autoload.php';
require_once 'wikidata-utils.php';
require_once 'wikidata-authors.php';
require_once 'wikidata-identifiers.php';
use LanguageDetection\Language;

//----------------------------------------------------------------------------------------
// Update based on subset of data, e.g. citations
function update_citation_data($work, $item, $source = array())
{
	if (!isset($work->message)) {
		error_log("Invalid work data structure");
		return null;
	}
	
	$quickstatements = '';
	$w = array();
		
	foreach ($work->message as $k => $v)
	{	
		switch ($k)
		{
			case 'reference':
				if (!is_array($v)) 
				{
					continue 2;
				}

				foreach ($v as $reference)
				{
					try {
						if (isset($reference->DOI))
						{
							// for now just see if this already exists
							$cited = wikidata_item_from_doi($reference->DOI);
							if ($cited != '')
							{
								$w[] = array('P2860' => $cited);
							}					
						}
						else if (isset($reference->ISSN))
						{
							// lets try metadata-based search (OpenURL)
							$parts = array();
							$parts[] = str_replace("http://id.crossref.org/issn/", '', $reference->ISSN);

							if (isset($reference->volume))
							{
								$parts[] = $reference->volume;
							}
							if (isset($reference->{'first-page'}))
							{
								$parts[] = $reference->{'first-page'};
							}
							if (isset($reference->year))
							{
								$parts[] = $reference->year;
							}	
	
							if (count($parts) == 4)
							{
								$cited = wikidata_item_from_openurl_issn($parts[0], $parts[1], $parts[2], $parts[3]);
								
								if ($cited != '')
								{								
									$w[] = array('P2860' => $cited);
								}	
							}						
						}
						else if (isset($reference->unstructured))
						{
							// Skip unstructured references as we can't reliably parse them
							continue;
						}
						else if (!is_array($source))
						{
							error_log("Invalid source array");
							$source = array();
						}
					} catch (Exception $e) {
						error_log("Error processing reference: " . $e->getMessage());
						continue;
					}
				}
				break;
	
			default:
				break;
		}
	}
	
	foreach ($w as $statement)
	{
		foreach ($statement as $property => $value)
		{
			$row = array();
			$row[] = $item;
			$row[] = $property;
			$row[] = $value;
		
			$quickstatements .= join("\t", $row);
			
			// labels don't get references 
			$properties_to_ignore = array();
			
			$properties_to_ignore = array(
				'P724',
				'P953',
				'P407', // language of work is almost never set by the source
				'P1922',
			); // e.g., when adding PDFs or IA to records from JSTOR
							
			if (count($source) > 0 && !preg_match('/^[D|L]/', $property) && !in_array($property, $properties_to_ignore))
			{
				$quickstatements .= "\t" . join("\t", $source);
			}
			
			$quickstatements .= "\n";
			
		}
	}
	
	return $quickstatements;
}

//----------------------------------------------------------------------------------------
// Convert a csl json object to Wikidata quickstatments
function csljson_to_wikidata($work, $check = true, $update = true, $languages_to_detect = array('en'), $source = array(), $always_english_label = true)
{
	$MAX_LABEL_LENGTH = 250;

	$quickstatements = '';
	
	$description = '';
	
	// Map language codes to Wikidata items
	$language_map = array(
		'ca' => 'Q7026',
		'cs' => 'Q9056',
		'da' => 'Q9035',
		'de' => 'Q188',
		'en' => 'Q1860',
		'es' => 'Q1321',
		'fr' => 'Q150',
		'hu' => 'Q9067',
		'it' => 'Q652',
		'ja' => 'Q5287',
		'la' => 'Q397',
		'nl' => 'Q7411',
		'pl' => 'Q809',
		'pt' => 'Q5146',
		'ru' => 'Q7737',
		'sv' => 'Q9027',
		'th' => 'Q9217',
		'un' => 'Q22282914', 
		'vi' => 'Q9199',
		'zh' => 'Q7850',		
	);
	
	// Journals that are Portuguese (or contain signifcant Portuguese content)
	$pt_issn = array(
		'2178-0579', 
		'2175-7860', 
		'1983-0572',
		'1808-2688',
		'2175-7860', 
		'0101-8175', 
		'1806-969X',
		'0328-0381',
		'0074-0276',
		'0065-6755',
		'2317-6105',
		'0034-7108',
	);
	
	// labels don't get references 
	$properties_to_ignore = array();
	
	$properties_to_ignore = array(
		'P724',
		'P953',
		'P407', // language of work is almost never set by the source
		'P1922',
		'P6535', // credit BHL separately
		'P687', // credit BHL separately
	); // e.g., when adding PDFs or IA to records from JSTOR
	
	// Is record sane?
	if (!isset($work->message->title))
	{
		return;
	}

	if (isset($work->message->title))
	{
		if (is_array($work->message->title) && count($work->message->title) == 0)
		{
			return;
		}
		else
		{
			if ($work->message->title == '')
			{
				return;
			}
		}
	}

	// Do we have this already in wikidata?
	$item = '';
	
	if ($check)
	{
		// DOI
		if (isset($work->message->DOI))
		{
			$item = wikidata_item_from_doi($work->message->DOI);
		}
		
		// PMID
		if (isset($work->message->PMID))
		{
			$item = wikidata_item_from_pmid($work->message->PMID);
		}

		// PMC
		if (isset($work->message->PMC))
		{
			$item = wikidata_item_from_pmc($work->message->PMC);
		}
		
		// ZooBank
		if (isset($work->message->ZOOBANK))
		{
			$item = wikidata_item_from_zoobank($work->message->ZOOBANK);
		}
			
		// JSTOR
		if ($item == '')
		{
			if (isset($work->message->JSTOR))
			{
				$item = wikidata_item_from_jstor($work->message->JSTOR);
			
			}
		}	
				
		// HANDLE
		if ($item == '')
		{
			if (isset($work->message->HANDLE))
			{
				$item = wikidata_item_from_handle($work->message->HANDLE);
			
			}
		}					

		// SUDOC
		if ($item == '')
		{
			if (isset($work->message->SUDOC))
			{
				$item = wikidata_item_from_sudoc($work->message->SUDOC);
			
			}
		}					
	
		// BioStor
		if ($item == '')
		{
			if (isset($work->message->BIOSTOR))
			{
				$item = wikidata_item_from_biostor($work->message->BIOSTOR);
			}
		}		

		// CNKI
		if ($item == '')
		{
			if (isset($work->message->CNKI))
			{
				$item = wikidata_item_from_cnki($work->message->CNKI);
			}
		}		
		
		if ($item == '')
		{
			if (isset($work->message->PERSEE))
			{
				$item = wikidata_item_from_persee_article($work->message->PERSEE);
			}
		}		
		
		
		if ($item == '')
		{
			if (isset($work->message->DIALNET))
			{
				$item = wikidata_item_from_dialnet($work->message->DIALNET);
			}
		}		

		if ($item == '')
		{
			if (isset($work->message->CINII))
			{
				$item = wikidata_item_from_cinii($work->message->CINII);
			}
		}		
	
		// PDF
		if ($item == '')
		{
			if (isset($work->message->link))
			{
				foreach ($work->message->link as $link)
				{
					if ($link->{'content-type'} == 'application/pdf')
					{
						$item = wikidata_item_from_pdf($link->URL);
					}
				}
			}
		}
		
		// URL
		if ($item == '')
		{
			if (isset($work->message->URL))
			{
				$item = wikidata_item_from_url($work->message->URL);
			}
		}
		
		
		// OpenURL
		if ($item == '')
		{
			$parts = array();
	
			if (isset($work->message->ISSN))
			{
				$parts[] = $work->message->ISSN[0];
			}
			if (isset($work->message->volume))
			{
				$parts[] = $work->message->volume;
			}
			if (isset($work->message->page))
			{
				if (preg_match('/^(?<spage>\d+)(-\d+)?/', $work->message->page, $m))
				{
					$parts[] = $m['spage'];
				}
			}
			
			if (isset($work->message->{'issued'}))
			{
				$parts[] = $work->message->{'issued'}->{'date-parts'}[0][0];
			}
			
			if (count($parts) == 4)
			{
				$item = wikidata_item_from_openurl_issn($parts[0], $parts[1], $parts[2], $parts[3]);
			}
		}
	}
	
	if ($item != '')
	{
		// already exists, if $update is false then exit		
		if (!$update)
		{
			return $item;
		}	
	}
	
	
	if ($item == '')
	{
		$item = 'LAST';
	}
	
	$w = array();
			
	$wikidata_properties = array(
		'type'					=> 'P31',
		'BHL' 					=> 'P687',
		'BHLPART' 				=> 'P6535',
		'BIOSTOR' 				=> 'P5315',
		'CINII'					=> 'P2409',
		'CNKI'					=> 'P6769',
		'DIALNET'				=> 'P1610',
		'DOI' 					=> 'P356',
		'HANDLE'				=> 'P1184',
		'JSTOR'					=> 'P888',
		'PMID'					=> 'P698',
		'PMC' 					=> 'P932',
		'SUDOC' 				=> 'P1025',
		'URL'					=> 'P953',	// https://twitter.com/EvoMRI/status/1062785719096229888
		'title'					=> 'P1476',	
		'volume' 				=> 'P478',
		'issue' 				=> 'P433',
		'page' 					=> 'P304',
		'PERSEE'				=> 'P8758',
		'PDF'					=> 'P953',
		'ARCHIVE'				=> 'P724',
		'ZOOBANK_PUBLICATION' 	=> 'P2007',
		'abstract'				=> 'P1922', // first line
		'article-number'		=> 'P1545', // series ordinal
	);
	
	// Need to think how to handle multi tag	
	foreach ($work->message as $k => $v)
	{	
		switch ($k)
		{
			//----------------------------------------------------------------------------
			case 'type':
				switch ($v)
				{
					case 'dataset':
						$w[] = array('P31' => 'Q1172284');												
						$description = "Dataset";
						break;
				
					case 'dissertation':
						// default is thesis
						$dissertation_type = 'Q1266946';
						
						if (isset($work->message->degree))
						{
							switch ($work->message->degree[0])
							{
								case 'PhD Thesis':
									$dissertation_type = 'Q187685';
									break;
									
								default:
									break;
							}
						}					
						$w[] = array('P31' => $dissertation_type);						
						$description = "Dissertation";
						break;
												
					case 'book-chapter':
						$w[] = array('P31' => 'Q1980247');						
						$description = "Book chapter";
						break;	
												
					case 'book':
						$w[] = array('P31' => 'Q47461344'); // written work						
						$description = "Book";
						break;		

					case 'edited-book':
						$w[] = array('P31' => 'Q1711593'); // edited volume						
						$description = "Edited book";
						break;		
						
					case 'monograph':		
						$w[] = array('P31' => 'Q571'); // book
						$w[] = array('P31' => 'Q193495'); // monograph						
						$description = "Monograph";
						break;	
						
					case 'reference-book':
						$w[] = array('P31' => 'Q47461344'); // written work						
						$description = "Book";
						break;	
						
					case 'report':	
						$w[] = array('P31' => 'Q10870555'); // report					
						$description = "Report";
						break;							
													
					case 'article-journal':
					case 'journal-article':
					default:
						$w[] = array('P31' => 'Q13442814');						
						$description = "Scholarly article";
						break;											
				}
				break;
				
			//----------------------------------------------------------------------------
			case 'volume':
			case 'issue':
			case 'page':
			case 'article-number':
				// clean				
				$v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');			
				$w[] = array($wikidata_properties[$k] => '"' . $v . '"');
				break;
				
			//----------------------------------------------------------------------------
			case 'BHL':
			case 'BHLPART':
			case 'BIOSTOR':
			case 'CNKI':
			case 'DOI':
			case 'HANDLE':
			case 'JSTOR':
			case 'PMID':
			case 'PMC':
			case 'SUDOC':
			case 'PERSEE':
			case 'DIALNET':
			case 'CINII':
			case 'ZOOBANK':
				$w[] = array($wikidata_properties[$k] => '"' . $v . '"');
				break;
				
			//----------------------------------------------------------------------------
			case 'URL':
				if (is_array($v))
				{
					foreach ($v as $url)
					{
						$w[] = array($wikidata_properties[$k] => '"' . $url . '"');
					}
				}
				else
				{
					$w[] = array($wikidata_properties[$k] => '"' . $v . '"');
				}
				break;
				
			//----------------------------------------------------------------------------
			case 'link':
				foreach ($v as $link)
				{
					if ($link->{'content-type'} == 'application/pdf')
					{
						$w[] = array($wikidata_properties['PDF'] => '"' . $link->URL . '"');
					}
				}
				break;
				
			//----------------------------------------------------------------------------
			case 'container-title':
				if (isset($work->message->ISSN))
				{
					$journal_item = '';
					
					foreach ($work->message->ISSN as $issn)
					{
						if ($journal_item == '')
						{
							$journal_item = wikidata_item_from_issn($issn);
						}
					}
					
					if ($journal_item != '')
					{
						$w[] = array('P1433' => $journal_item);
					}
				}
				break;
				
			//----------------------------------------------------------------------------
			case 'issued':
				if (isset($v->{'date-parts'}))
				{
					$date = '';
					
					$d = $v->{'date-parts'}[0];
					
					if (count($d) > 0) $year = $d[0];
					if (count($d) > 1) $month = sprintf('%02d', $d[1]);
					if (count($d) > 2) $day = sprintf('%02d', $d[2]);
					
					if (isset($month) && isset($day))
					{
						$date = "+$year-$month-$day" . "T00:00:00Z/11";
					}
					else if (isset($month))
					{
						$date = "+$year-$month-00T00:00:00Z/10";
					}
					else if (isset($year))
					{
						$date = "+$year-00-00T00:00:00Z/9";
					}
					
					if ($date != '')
					{
						$w[] = array('P577' => $date);
					}
				}
				break;
		}
	}
	
	// assume create
	if ($item == 'LAST')
	{
		$quickstatements .= "CREATE\n";
	}	
	
	foreach ($w as $statement)
	{
		foreach ($statement as $property => $value)
		{
			$row = array();
			$row[] = $item;
			$row[] = $property;
			$row[] = $value;
		
			$quickstatements .= join("\t", $row);			
							
			if (count($source) > 0 && !preg_match('/^[D|L]/', $property) && !in_array($property, $properties_to_ignore))
			{
				$quickstatements .= "\t" . join("\t", $source);
			}
			
			$quickstatements .= "\n";
			
		}
	}
	
	return $quickstatements;
}

?>
