<?php
class MySQL {
	private $con, $result, $attr;
	public $lastQuery;
	
	function __construct($host, $user, $pw, $db, $port = 3306) {
		$this->con = new mysqli($host, $user, $pw, $db);
		
		if ($this->con->connect_error) {
			die('Connect Error (' . $this->con->connect_errno . ') '
					. $this->con->connect_error);
		}
		$this->attr = array();
	}
	
	function __destruct() {
		$this->con->close();
	}
	
	public function where($field, $cond, $op = '=', $type = 'AND') {
		$this->attr['where'][] = array('field' => $field, 'cond' => $cond, 'op' => $op, 'type' => $type);
	}
	
	private function whereString(): string {
		if(!isset($this->attr['where']) || empty($this->attr['where'])) return '';
		
		$str = '';
		
		foreach($this->attr['where'] AS $cWhere) {
			$str .= $cWhere['type']." ".$cWhere['field']." ".$cWhere['op']." ? ";
			$this->attr['cond'][] = $cWhere['cond'];
		}
		
		$str = ' WHERE '.substr($str, strpos($str, ' '));
		$str = substr($str, 0, strlen($str) - 1);
		unset($this->attr['where']);
		return $str;
	}
	
	private function joinString(): string {
		if(!isset($this->attr['join']) || empty($this->attr['join'])) return '';
	
		$str = '';
	
		foreach($this->attr['join'] AS $cJoin) 
			$str .= $cJoin['type']." JOIN ".$cJoin['tB']." ON `".$cJoin['tA']."`.`".$cJoin['fA']."` = `".$cJoin['tB']."`.`".$cJoin['fB']."` ";		
		$str = substr($str, 0, strlen($str) - 1);
		unset($this->attr['join']);
		return $str;
	}
	
	private function orderString(): string {
		if(!isset($this->attr['order']) || empty($this->attr['order'])) return '';
		
		$str = ' ORDER BY ';
		foreach($this->attr['order'] AS $cOrder) 
			$str .= "`".$cOrder['field']."` ".$cOrder['sort'].", ";
		unset($this->attr['order']);
		$str = substr($str, 0, strlen($str) - 2);
		return $str;
	}
	
	public function orderBy($field, $sort = 'ASC') {
		$this->attr['order'][] = array('field' => $field, 'sort' => $sort);
	}
	
	public function join($tableA, $fieldA, $tableB, $fieldB, $type = 'INNER') {
		$this->attr['join'][] = array('tA' => $tableA, 'tB' => $tableB, 'fA' => $fieldA, 'fB' => $fieldB, 'type' => $type);
	}
	
	public function fetchAll() {
		if(gettype($this->result) != 'object') return false;
		$retval = $this->result->fetch_all(MYSQLI_ASSOC);
		$this->result->free();
		$this->result = null;
		return $retval;
	}
	
	public function fetchRow($asArray = false) {
		if(gettype($this->result) != 'object') return false;
		if($asArray) $retval = $this->result->fetch_assoc();
		else $retval =  $this->result->fetch_object();
	
		if($retval == null) {
			$this->result->free();
			$this->result = null;
			return false;
		}
		else return $retval;
	}
	
	public function countResult(): int {
		if(gettype($this->result) != 'object') return -1;
		return $this->result->num_rows;
	}
	
	public function count($table, $field = '*'): int {
		$query = "SELECT COUNT(`$field`) AS `count` FROM `$table`";
		$query .= " ".$this->whereString();
		$query = preg_replace('/`\*`/', '*', $query);
		$this->execute($query);
		$val = $this->fetchRow(TRUE);
		return $val['count'];
	}
	
	public function update($table, $values) {
		$query = "UPDATE `$table` SET ";
		
		foreach($values AS $cField => $cVal) {
			$query .= "`$cField` = ?, ";
			$this->attr['cond'][] = $cVal;
		}
		
		$query = substr($query, 0, -2).' ';
		$query .= $this->whereString();
		
		return $this->execute($query);
		
	}
	
	private function execute($query) {
		/*
		 * Erstellung und verarbeitung Statement
		 */
		
		$stmt = $this->con->prepare($query);
		if(!$stmt) {
			echo "Fehler beim Erstellen des Statements: ".$this->con->error."\n Query: ".$query;
			return false;
		}
		
		if(isset($this->attr['cond'])) {			
			$types = '';
			while(current($this->attr['cond'])) {
				if(is_int(current($this->attr['cond']))) $types .= 'i';
				elseif(is_numeric(current($this->attr['cond']))) $types .= 'd';
				else $types .= 's';
				next($this->attr['cond']);
			}
			
			$toInvoke = array($types);
			reset($this->attr['cond']);
			while(current($this->attr['cond'])) {
				$toInvoke[] = &$this->attr['cond'][key($this->attr['cond'])];
				next($this->attr['cond']);
			}
		
			$ref = new ReflectionClass('mysqli_stmt');
			$method = $ref->getMethod("bind_param");
			$method->invokeArgs($stmt,$toInvoke);
		}
		
		$stmt->execute();
		$this->result = $stmt->get_result();
		unset($this->attr['cond']);
		
		return true;
	}
	
	public function insert($table, $data) {
		$query = "INSERT INTO `$table` (";
		
		$toStep = !is_array($data[key($data)]) ? $toStep = array($data) : $toStep = $data;
		foreach($toStep AS $currInsert) {
			foreach($currInsert AS $field => $val) {
				$query .= "`$field`, ";
				$this->attr['cond'][] = $val;
			}
		}
		$query = substr($query, 0, -2).") VALUES ";
		foreach($toStep AS $currStep) {
			$query .= "(";
			for($i = 0; $i < count($currStep); $i++) $query .= "?, ";
			$query = substr($query, 0, -2)."), "; 
		}
		$query = substr($query, 0, -2);
		
		return $this->execute($query);
	}
	
	public function delete($table) {
		$query = 'DELETE FROM `'.$table.'` ';
		$query .= $this->whereString();
		return $this->execute($query);	
	}
	
	public function select($table, $fields = NULL, $limit = NULL) {
		$query = 'SELECT ';
		
		if(is_null($fields)) $query .= '*';
		else {
			foreach($fields AS $cast => $field) {
				if(count(explode('.', $field)) == 1) $field = "`".$table."`.`".$field."`";
				else $field = "`".substr($field, 0, strpos($field, '.'))."`.`".substr($field, strpos($field, '.') + 1)."`";
				
				if(!is_numeric($cast)) $field .= 'AS '.$cast;
				
				$query .= $field.", ";
			}
			$query = preg_replace('/`\*`/', '*', $query);
			$query = substr($query, 0, strlen($query) - 2);
		}
		
		$query .= ' FROM '.$table.' ';
		$query .= $this->joinString();
		$query .= $this->whereString();
		$query .= $this->orderString();
		
		if(!is_null($limit)) $query .= ' LIMIT '.$limit;
		
		return $this->execute($query);	
	}
}