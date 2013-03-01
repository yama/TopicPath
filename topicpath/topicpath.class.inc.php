<?php
class TopicPath
{
	function TopicPath()
	{
		global $modx;
		if($modx->event->params) extract($modx->event->params);
		$this->menuitemOnly = 1;
		$this->ignoreIDs = array();
		$this->showTopicsAtHome = (!isset($showTopicsAtHome)) ? '0' : $showTopicsAtHome;
		$this->ignoreIDs        = (!isset($ignoreIDs))        ? array() :explode(',',$ignoreIDs);
		$this->disabledOn       = (!isset($disabledOn))       ? array() :explode(',',$disabledOn);
		$this->disabledUnder    = (!isset($disabledUnder))    ? '' :$disabledUnder;
		$this->menuItemOnly     = (!isset($menuItemOnly))     ? '1' : $menuItemOnly;
		$this->limit            = (!isset($limit))            ? 100 :$limit;
		$this->topicGap         = (!isset($topicGap))         ? '...' :$topicGap;
		$this->titleField       = (!isset($titleField))       ? array('menutitle','pagetitle')              :explode(',',$titleField);
		$this->descField        = (!isset($descField))        ? array('description','longtitle','pagetitle'):explode(',',$descField);
		$this->homeId           = (!isset($homeId))           ? $modx->config['site_start'] :$homeId;
		$this->stopIDs          = (!isset($stopIDs))          ? array() :explode(',', $stopIDs);
		
		if(isset($homeTopicTitle)) $this->homeTopicTitle = $homeTopicTitle;
		if(isset($homeTopicDesc))  $this->homeTopicDesc  = $homeTopicDesc;
		
		$this->theme            = $theme;
		$this->tpl = array();
		if(isset($tplOuter))        $this->tpl['Outer']        = $tplOuter;
		if(isset($tplHomeTopic))    $this->tpl['HomeTopic']    = $tplHomeTopic;
		if(isset($tplCurrentTopic)) $this->tpl['CurrentTopic'] = $tplCurrentTopic;
		if(isset($tplOtherTopic))   $this->tpl['OtherTopic']   = $tplOtherTopic;
		if(isset($tplSeparator))    $this->tpl['Separator']    = $tplSeparator;
	}
	
	function getTopicPath()
	{
		global $modx;
		$id = $modx->documentIdentifier;
		
		$this->disabledOn = $this->getDisableDocs();
		
		if(!$this->showTopicsAtHome && $id === $modx->config['site_start']) return;
		elseif(in_array($id,$this->disabledOn))                             return;
		
		switch(strtolower($this->theme))
		{
			case 'list':
			case 'li':
				$tpl['Outer']        = '<ul class="topicpath">[+topics+]</ul>';
				$tpl['HomeTopic']    = '<li class="home"><a href="[+href+]">[+title+]</a></li>';
				$tpl['CurrentTopic'] = '<li class="current">[+title+]</li>';
				$tpl['OtherTopic']   = '<li><a href="[+href+]">[+title+]</a></li>';
				$tpl['Separator']    = "\n";
				break;
			default:
				$tpl['Outer']        = '[+topics+]';
				$tpl['HomeTopic']    = '<a href="[+href+]" class="home">[+title+]</a>';
				$tpl['CurrentTopic'] = '[+title+]';
				$tpl['OtherTopic']   = '<a href="[+href+]">[+title+]</a>';
				$tpl['Separator']    = ' &raquo; ';
		}
		$tpl = array_merge($tpl, $this->tpl);
		
		$docs   = $this->getDocs();
		$topics = $this->setTopics($docs,$tpl);
		
		if($this->limit < count($topics)) $topics = $this->trimTopics($topics);
		
		if(0<count($topics))
		{
			$rs = join($tpl['Separator'],$topics);
			$rs = $modx->parsePlaceholder($tpl['Outer'],array('topics'=>$rs));
		}
		else $rs = '';
		
		return $rs;
	}
	
	function trimTopics($topics)
	{
		$last_topic = array_pop($topics);
		array_splice($topics,$this->limit-1);
		$topics[] = $this->topicGap;
		$topics[] = $last_topic;
		
		return $topics;
	}
	
	function isEnable($doc)
	{
		if(in_array($doc['id'],$this->ignoreIDs))       $rs = false;
		elseif($this->menuItemOnly && $doc['hidemenu']) $rs = false;
		elseif(!$doc['published'])                      $rs = false;
		else                                            $rs = true;
		
		return $rs;
	}
	
	function isEnd($doc)
	{
		if(in_array($doc['id'],$this->stopIds) || !$doc['parent'] || ( !$doc['published'] && !$this->pathThruUnPub ))
		{
			$rs = true;
		}
		else $rs = false;
		
		return $rs;
	}
	
	function getDocs()
	{
		global $modx;
		
		$docs = array();
		$id = $modx->documentIdentifier;
		$fields = 'id,parent,pagetitle,longtitle,menutitle,description,published,hidemenu';
		$c = 0;
		$doc = array();
		while ($id !== $this->homeId  && $c < 1000 )
		{
			$doc = $modx->getPageInfo($id,0,$fields);
			if($this->isEnable($doc)) $docs[] = $doc;
			$id = $doc['parent'];
			if($id==='0') $id = $this->homeId ;
			$c++;
		}
		$docs[] = $modx->getPageInfo($this->homeId ,0,$fields);
		return $docs;
	}
	
	function setTopics($docs,$tpl)
	{
		global $modx;
		$topics = array();
		$docs = array_reverse($docs);
		$i = 0;
		$c = count($docs);
		foreach($docs as $doc)
		{
			$ph = array();
			if(in_array($doc['id'],$this->stopIDs)) break;
			$ph['href']  = ($doc['id'] == $modx->config['site_start']) ? $modx->config['base_url'] : $modx->makeUrl($doc['id']);
			foreach($this->titleField as $f)
			{
				if($doc[$f]!=='')
				{
					$ph['title'] = $doc[$f];
					break;
				}
			}
			if(!isset($ph['title'])) $ph['title'] = $doc['pagetitle'];
			
			foreach($this->descField as $f)
			{
				if($doc[$f]!=='')
				{
					$ph['desc'] = $doc[$f];
					break;
				}
			}
			if(!isset($ph['desc'])) $ph['desc'] = $doc['pagetitle'];
			
			if(isset($this->homeTopicTitle) && $doc['id'] == $this->homeId)
			{
				$ph['title'] = $this->homeTopicTitle;
			}
			if(isset($this->homeTopicDesc) && $doc['id'] == $this->homeId)
			{
				$ph['desc'] = $this->homeTopicDesc;
			}
			
			$ph['title'] = htmlspecialchars($ph['title'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['desc']  = htmlspecialchars($ph['desc'], ENT_QUOTES, $modx->config['modx_charset']);
			
			if($i===$c-1)  $topics[$i] = $modx->parsePlaceholder($tpl['CurrentTopic'],$ph);
			elseif($i===0) $topics[$i] = $modx->parsePlaceholder($tpl['HomeTopic'],$ph);
			else           $topics[$i] = $modx->parsePlaceholder($tpl['OtherTopic'],$ph);
			
			$i++;
		}
		return $topics;
	}
	
	function getDisableDocs()
	{
		global $modx;
		$tbl_site_content = $modx->getFullTableName('site_content');
		
		if(empty($this->disabledUnder)) return $this->disabledOn;
		
		$rs = $modx->db->select('id', $tbl_site_content, "parent IN ({$this->disabledUnder})");
		while ($id = $modx->db->getValue($rs))
		{
			$hidden[] = $id;
		}
		return array_merge($this->disabledOn,$hidden);
	}
}
