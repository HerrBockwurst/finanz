<?php
class ContentEntry {
	
}

class Button {
	private $order, $action, $target, $string;
	function __construct(SimpleXMLElement $Entry) {
		$this->order = isset($Entry['order']) ? $Entry['order'] : 0;
		$this->action = isset($Entry['action']);
		$this->target = isset($Entry['target']) ? $Entry['target'] : NULL;
		$this->string = $Entry['langString'];
	}
	
	public function getString() {
		return $this->string;
	}
}

class MenuEntry {
	private $ButtonSet, $attributes;
	
	function __construct(SimpleXMLElement $MenuEntry) {
		$this->ButtonSet = array();
		$this->attributes = array();
		foreach($MenuEntry->Set AS $cSet) {
			$this->attributes['startCollapsed'] = $cSet['starting'] == 'collapsed' ? true : false;
			$this->attributes['visible'] = isset($cSet['visible']) ? intval($cSet['visible']) : NULL;
			foreach($cSet AS $Entry) {
				$this->ButtonSet[intval($Entry['order'])] = new Button($Entry);
			}
						
		}
	}
	
	public function isCollapsed() {
		return $this->attributes['startCollapsed']; 
	}
	
	public function getVisibleEntry() {
		if(is_null($this->attributes['visible'])) return '';
		return $this->ButtonSet[$this->attributes['visible']]->getString();
	}
}

class ContentHandler {
	private $menu, $pages, $handlers;
	
	function __construct() {
		$menu = array();
		$pages = array();
		$handlers = array();
	}
	
	public function init() {
		$folder = scandir('modules');
		foreach($folder AS $cFolder) {
			if(preg_match('/\./', $cFolder) || !file_exists('modules/'.$cFolder.'/register.xml')) continue;
			
			$xml = new SimpleXMLElement('modules/'.$cFolder.'/register.xml', NULL, TRUE);
			if(!empty($xml->Menu)) $this->menu[] = new MenuEntry($xml->Menu);
			if(!empty($xml->Pages)) $this->addPage($xml->Pages);
			if(!empty($xml->Handlers)) $this->addHandler($xml->Handlers);
		}
	}
	
	private function addPage(SimpleXMLElement $Pages) {
		
	}
	
	private function addHandler(SimpleXMLElement $Handlers) {
	
	}
	
	/* @var $cSet MenuEntry */
	public function buildMenu() {
		foreach($this->menu AS $cSet) {
			echo $cSet->getVisibleEntry();
		}
	}
	
	public function loadPage() {
		
	}
	
	public function setMenu() {
		
	}
}

$content = new ContentHandler();
$content->init();