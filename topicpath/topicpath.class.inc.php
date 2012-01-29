<?php
class TopicPath
{
	function TopicPath()
	{
	}
	
	function getTopicPath()
	{
		global $modx;
		if($modx->event->params) extract($modx->event->params);
		
		if(!isset($theme))                $theme             = 'raw';
		if(!isset($pathThruUnPub))        $pathThruUnPub     = 1;
		if(!isset($respectHidemenu))      $respectHidemenu   = 1;
		if(!isset($showCurrentTopic))     $showCurrentTopic  = 1;
		if(!isset($currentAsLink))        $currentAsLink     = 0;
		if(!isset($linkTextField))        $linkTextField     = 'menutitle,pagetitle,longtitle';
		if(!isset($linkDescField))        $linkDescField     = 'description,longtitle,pagetitle,menutitle';
		if(!isset($showTopicsAsLinks))    $showTopicsAsLinks = 1;
		if(!isset($topicGap))             $topicGap          = '...';
		if(!isset($showHomeTopic))        $showHomeTopic     = 1;
		if(!isset($homeId))               $homeId            = $modx->config['site_start'];
		if(!isset($homeTopicTitle) )      $homeTopicTitle    = '';
		if(!isset($homeTopicDesc))        $homeTopicDesc     = '';
		if(!isset($showTopicsAtHome))     $showTopicsAtHome  = 0;
		if(!isset($hideOn))               $hideOn            = '';
		if(!isset($hideUnder))            $hideUnder         = '';
		if(!isset($stopIds))              $stopIds           = '';
		if(!isset($ignoreIds))            $ignoreids         = '';
		if(!isset($maxTopics))            $maxTopics         = 100;
		
		if(isset($templateSet)) $theme = $templateSet;
		switch(strtolower($theme))
		{
			case 'list':
			case 'li':
			case 'defaultlist':
				$tpl['outer']             = '<ul class="topicpath">[+topics+]</ul>';
				$tpl['first_topic_inner'] = '<span class="first">[+topic+]</span>';
				$tpl['last_topic_inner']  = '<span class="last">[+topic+]</span>';
				$tpl['topic']             = '<li>[+topic+]</li>';
				$tpl['separator']         = '';
				break;
			case 'raw':
			case 'defaultstring':
				$tpl['outer']             = '<span class="topicpath">[+topics+]</span>';
				$tpl['first_topic_inner'] = '<span class="first">[+topic+]</span>';
				$tpl['last_topic_inner']  = '<span class="last">[+topic+]</span>';
				$tpl['topic']             = '[+topic+]';
				$tpl['separator']         = ' &raquo; ';
				break;
			default:
		}
		
		if(isset($tplOuter))           $tpl['outer']             = $tplOuter;
		if(isset($tplTopic))           $tpl['topic']             = $tplTopic;
		if(isset($tplFirstTopicInner)) $tpl['first_topic_inner'] = $tplFirstTopicInner;
		if(isset($tplLastTopicInner))  $tpl['last_topic_inner']  = $tplLastTopicInner;
		if(isset($tplSeparator))       $tpl['separator']         = $tplSeparator;
		
		// Return blank if necessary: on home page
		if ( !$showTopicsAtHome && $homeId == $modx->documentIdentifier )
		{
			return '';
		}
		// Return blank if necessary: specified pages
		if ($hideOn || $hideUnder)
		{
			// Create array of hide pages
			$hideOn = str_replace(' ','', $hideOn);
			$hideOn = explode(',', $hideOn);
			
			// Get more hide pages based on parents if needed
			if ( $hideUnder )
			{
				$hiddenKids = array();
				// Get child pages to hide
				$hideKidsQuery = $modx->db->select('id',$modx->getFullTableName('site_content'),"parent IN ({$hideUnder})");
				while ($hideKid = $modx->db->getRow($hideKidsQuery))
				{
					$hiddenKids[] = $hideKid['id'];
				}
				// Merge with hideOn pages
				$hideOn = array_merge($hideOn,$hiddenKids);
			}
			
			if (in_array($modx->documentIdentifier,$hideOn))
			{
				return '';
			}
		}
		
		// Initialize ------------------------------------------------------------------
		
		// Put certain parameters in arrays
		$stopIds       = $this->convert_array($stopIds);
		$linkTextField = $this->convert_array($linkTextField);
		$linkDescField = $this->convert_array($linkDescField);
		$ignoreIds     = $this->convert_array($ignoreIds);
		
		/* $topics
		* Topic elements are: id, parent, pagetitle, longtitle, menutitle, description,
		* published, hidemenu
		*/
		$topics = array();
		$parent = &$modx->documentObject['parent'];
		$output = '';
		$maxTopics += ($showCurrentTopic) ? 1 : 0;
		
		// Replace || in snippet parameters that accept them with =
		$topicGap = str_replace('||','=',$topicGap);
		
		// Curent topic ----------------------------------------------------------------
		
		// Decide if current page is to be a topic
		if ( $showCurrentTopic )
		{
			$topics[] = &$modx->documentObject;
		}
		
		// Intermediate topics ---------------------------------------------------------
		
		
		// Iterate through parents till we hit root or a reason to stop
		$loopSafety = 0;
		while ( $parent && $parent!=$modx->config['site_start'] && $loopSafety < 1000 )
		{
			// Get next topic
			$doc = $modx->getPageInfo($parent,0,'id,parent,pagetitle,longtitle,menutitle,description,published,hidemenu');
			
			// Check for include conditions & add to topics
			if ($doc['published']
			    && (!$doc['hidemenu'] || !$respectHidemenu)
			    && !in_array($doc['id'],$ignoreIds))
			{
				// Add topic
				$topics[] = &$doc;
			}
			
			// Check stop conditions
			if (
			in_array($doc['id'],$stopIds) ||  // Is one of the stop IDs
			!$doc['parent'] || // At root
			( !$doc['published'] && !$pathThruUnPub ) // Unpublished
			)
			{
				// Halt making topics
				break;
			}
			
			// Reset parent
			$parent = $doc['parent'];
			
			// Increment loop safety
			$loopSafety++;
		}
		
		// Home topic ------------------------------------------------------------------
		$homeTopic = $modx->getPageInfo($homeId,0,'id,parent,pagetitle,longtitle,menutitle,description,published,hidemenu');
		if($showHomeTopic && $homeId != $modx->documentIdentifier && !empty($homeTopic))
		{
			$topics[] = &$homeTopic;
		}
		
		// Process each topic ----------------------------------------------------------
		$pretplTopics = array();
		
		foreach ($topics as $row )
		{
			// Skip if we've exceeded our topic limit but we're waiting to get to home
			if (count($pretplTopics) > $maxTopics && $row['id'] != $homeId )
			{
				continue;
			}
			
			$text  = '';
			$title = '';
			
			// Determine appropriate span/link text: home link specified
			if ( $row['id'] == $homeId && $homeTopicTitle )
			{
				$text = $homeTopicTitle;
			}
			else
			// Determine appropriate span/link text: home link not specified
			{
				for ($i = 0; !$text && $i < count($linkTextField); $i++)
				{
					if ( $row[$linkTextField[$i]] )
					{
						$text = $row[$linkTextField[$i]];
					}
				}
			}
			
			// Determine link/span class(es)
			if ($row['id'] == $homeId )                   $topicClass = 'home';
			elseif($modx->documentIdentifier==$row['id']) $topicClass = 'current';
			else                                          $topicClass = 'each';
			
			// Make link
			if (
			( $row['id'] != $modx->documentIdentifier && $showTopicsAsLinks ) ||
			( $row['id'] == $modx->documentIdentifier && $currentAsLink )
			)
			{
				// Determine appropriate title for link: home link specified
				if ($row['id'] == $homeId && $homeTopicDesc )
				{
					$title = htmlspecialchars($homeTopicDesc);
				}
				else
				// Determine appropriate title for link: home link not specified
				{
					for ($i = 0; !$title && $i < count($linkDescField); $i++)
					{
						if ($row[$linkDescField[$i]] )
						{
							$title = htmlspecialchars($row[$linkDescField[$i]]);
						}
					}
				}
				$url = ($row['id'] == $modx->config['site_start']) ? $modx->config['base_url'] : $modx->makeUrl($row['id']);
				$pretplTopics[] = '<a href="' . $url . '" class="' . $topicClass . '" title="' . $title . '">' . $text . '</a>';
			}
			else
			// Make a span instead of a link
			{
				$pretplTopics[] = '<span class="'.$topicClass.'">'.$text.'</span>';
			}
			
			// If we have hit the topic limit
			if ( count($pretplTopics) == $maxTopics )
			{
				if ( count($topics) > ($maxTopics + (($showHomeTopic) ? 1 : 0)) )
				{
					// Add gap
					$pretplTopics[] = '<span class="hidden">' . $topicGap . '</span>';
				}
				
				// Stop here if we're not looking for the home topic
				if ( !$showHomeTopic )
				{
					break;
				}
			}
		}
		
		// Put in correct order for output
		$pretplTopics = array_reverse($pretplTopics);
		
		// Wrap first/last spans
		
		$pretplTopics[0] = str_replace('[+topic+]',$pretplTopics[0],$tpl['first_topic_inner']);
		
		$last = count($pretplTopics)-1;
		$pretplTopics[$last] = str_replace('[+topic+]',$pretplTopics[$last],$tpl['last_topic_inner']);
		
		// Insert topics into topic template
		$processedTopics = array();
		foreach ( $pretplTopics as $pc )
		{
			$processedTopics[] = str_replace('[+topic+]',$pc,$tpl['topic']);
		}
		
		// Combine topics together into one string with separator
		$processedTopics = implode($tpl['separator'],$processedTopics);
		
		// Put topics into topic container template
		$container = str_replace('[+topics+]',$processedTopics,$tpl['outer']);
		// Return topics
		return $container;
	}
	function convert_array($str)
	{
		if($str == '') return array();
		
		$str = str_replace(' ','',$str);
		return explode(',',$str);
	}
}
