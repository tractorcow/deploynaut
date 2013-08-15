<?php

class DNEnvironment extends DataObject {
	static $db = array(
		"Filename" => "Varchar(255)",
		"Name" => "Varchar",
		"URL" => "Varchar",
		"GraphiteServers" => "Text",
	);
	static $has_one = array(
		"Project" => "DNProject",
	);
	static $many_many = array(
		"Deployers" => "Member",
	);

	static $summary_fields = array(
		"Name",
		"URL",
		"DeployersList",
	);
	static $searchable_fields = array(
		"Name",
	);

	protected static $relation_cache = array();

	static function get($callerClass = null, $filter = "", $sort = "", $join = "", $limit = null,
			$containerClass = 'DataList') {
		return new DNEnvironmentList('DNEnvironment');
	}

	static function create_from_path($path) {
		$e = new DNEnvironment;
		$e->Filename = $path;
		$e->Name = preg_replace('/\.rb$/', '', basename($e->Filename));

		// add each administrator member as a deployer of the new environment
		$adminGroup = Group::get()->filter('Code', 'administrators')->first();
		if($adminGroup && $adminGroup->exists()) {
			foreach($adminGroup->Members() as $member) {
				$e->Deployers()->add($member);
			}
		}

		return $e;
	}

	public function Project() {
		if(!isset(self::$relation_cache['DNProject.' . $this->ProjectID])) {
			self::$relation_cache['DNProject.' . $this->ProjectID] = $this->getComponent('Project');
		}
		return self::$relation_cache['DNProject.' . $this->ProjectID];
	}

	function canView($member = null) {
		return $this->Project()->canView($member);
	}
	function canDeploy($member = null) {
		if(!$member) $member = Member::currentUser();

		return (bool)($this->Deployers()->byID($member->ID));
	}

	function getDeployersList() {
		return implode(", ", $this->Deployers()->column("FirstName"));
	}

	function DNData() {
		return Injector::inst()->get('DNData');
	}

	function CurrentBuild() {
		$buildInfo = $this->DNData()->Backend()->currentBuild($this->Project()->Name.':'.$this->Name);
		return $buildInfo['buildname'];
	}

	/**
	 * A history of all builds deployed to this environment
	 */
	function DeployHistory() {
		$history = $this->DNData()->Backend()->deployHistory($this->Project()->Name.':'.$this->Name);
		$output = new ArrayList;
		foreach($history as $item) {
			$output->push(new ArrayData(array(
				'BuildName' => $item['buildname'],
				'DateTime' => DBField::create_field('SS_Datetime', $item['datetime']),
			)));
		}
		return $output;
	}

	function HasMetrics() {
		return trim($this->GraphiteServers) != "";
	}

	/**
	 * All graphs
	 */
	function Graphs() {
		if(!$this->HasMetrics()) return null;

		$serverList = preg_split('/\s+/', trim($this->GraphiteServers));
		
		return new GraphiteList($serverList);
	}

	/**
	 * Graphs, grouped by server
	 */
	function GraphServers() {
		if(!$this->HasMetrics()) return null;

		$serverList = preg_split('/\s+/', trim($this->GraphiteServers));

		$output = new ArrayList;
		foreach($serverList as $server) {
			// Hardcoded reference to db
			if(strpos($server,'nzaadb') !== false) {
				$metricList = array("Load average", "CPU Usage", "Memory Free", "Physical Memory Used", "Swapping");
			} else {
				$metricList = array("Apache", "Load average", "CPU Usage", "Memory Free", "Physical Memory Used", "Swapping");
			}

			$output->push(new ArrayData(array(
				'Server' => $server,
				'ServerName' => substr($server,strrpos($server,'.')+1),
				'Graphs' => new GraphiteList(array($server), $metricList),
			)));
		}

		return $output;
	}

	
	function Link() {
		return $this->Project()->Link()."/environment/" . $this->Name;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$members = array();
		foreach($this->Project()->Viewers() as $group) {
			foreach($group->Members()->map() as $k => $v) {
				$members[$k] = $v;
			}
		}
		asort($members);

		$fields->fieldByName("Root")->removeByName("Deployers");
		$nameField = $fields->fieldByName('Root.Main.Name')->performReadonlyTransformation();
		$fields->replaceField('Name', $nameField);
		$projectField = $fields->fieldByName('Root.Main.ProjectID')->performReadonlyTransformation();
		$fields->replaceField('ProjectID', $projectField);
		$fields->addFieldToTab("Root.Main", 
			new CheckboxSetField("Deployers", "Users who can deploy to this environment", 
				$members));
		$fields->makeFieldReadonly('Filename');
		
	
		return $fields;
	}
}