<?php

class DataBase {

	public  $db;
	public  $config;
	public  $driver;
	private $_connected = false;
	private $_table_name;

	public $sqls = array();

	public function init() 
	{
		$driver = $this->driver;
		$driver_path = dirname(__FILE__) . '/driver/'.$driver.'.php';
		if(is_file($driver_path)){
			if(strpos($driver, '_slave')){
				$mdriver_path = dirname(__FILE__) . '/driver/'.str_replace('_slave', '', $driver).'.php';
				if(is_file($mdriver_path)){
					require_once($mdriver_path);
				}
			}

			require($driver_path);
		}

		
		$this->db = new $driver;
		$this->db->set_config($this->config);
	}

	public function connect()
	{
		if(!$this->_connected){
			$this->db->connect();
			$this->_connected = true;
		}
	}

	public function allow_ddl()
	{
		$this->db->allow_ddl = true;
	}

	public function object() 
	{
		return $this->db;
	}

    public function close()
    {
    	return $this->_connected && $this->db->close();
    }

	public function table($table) 
	{
		$this->_table_name = $this->db->table_name($table);
		return $this->_table_name;
	}

	public function delete($table, $condition, $limit = 0, $unbuffered = true) 
	{
		if (empty($condition)) {
			return false;
		} elseif (is_array($condition)) {
			if (count($condition) == 2 && isset($condition['where']) && isset($condition['arg'])) {
				$where = self::format($condition['where'], $condition['arg']);
			} else {
				$where = self::implode_field_value($condition, ' AND ');
			}
		} else {
			$where = $condition;
		}
		$limit = HelperUtils::dintval($limit);
		$sql = "DELETE FROM " . self::table($table) . " WHERE $where " . ($limit > 0 ? "LIMIT $limit" : '');
		return self::query($sql, ($unbuffered ? 'UNBUFFERED' : ''));
	}

	public function insert($table, $data, $return_insert_id = false, $replace = false, $silent = false) 
	{
		$sql = self::implode($data);

		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';

		$table = self::table($table);
		$silent = $silent ? 'SILENT' : '';

		return self::query("$cmd $table SET $sql", null, $silent, !$return_insert_id);
	}

	public function insert_or_update($table, $data, $return_insert_id = false, $silent = false) 
	{
		$sql    = self::implode($data);
		$cmd    = 'INSERT INTO';
		$sql   .= ' ON DUPLICATE KEY UPDATE '.$sql;
		$table  = self::table($table);
		$silent = $silent ? 'SILENT' : '';

		return self::query("$cmd $table SET $sql", null, $silent, !$return_insert_id);
	}


	public function batch_insert($table, $data, $return_insert_id = false, $replace = false, $silent = false)
	{
		if(empty($data)){
			return false;
		}

		$sql     = '';
		$cmd     = 'INSERT IGNORE INTO';
		$tbl     = self::table($table);
		$columns = array_keys($data[0]);

		$sql .= "$cmd $tbl (`".trim(implode('`,`', $columns), ',')."`) VALUES";

		foreach($data as $d){
			$sql .=  '(';
			$con  = '';
			foreach($columns as $column){
				$sql .=  $con . self::quote($d[$column]);
				$con  = ',';
			}
			$sql .= '),';
		}
		$sql = trim($sql, ',');
		return self::query($sql, null);		
	}

	//通过唯一索引或主键批量更新
	public function batch_update($table, $data)
	{
		if(empty($data)){
			return false;
		}

		$sql     = '';
		$cmd     = 'INSERT INTO';
		$tbl     = self::table($table);
		$columns = array_keys($data[0]);

		$sql .= "$cmd $tbl (`".trim(implode('`,`', $columns), ',')."`) VALUES";

		foreach($data as $d){
			$sql .=  '(';
			$con  = '';
			foreach($columns as $column){
				$sql .=  $con . self::quote($d[$column]);
				$con  = ',';
			}
			$sql .= '),';
		}
		$sql = trim($sql, ',');

		$update = '';
		foreach ($columns as $column) {
			$update .= ($update ? ',' : '').'`'.$column.'`=values(`'.$column.'`)';
		}
		$sql = $sql.' ON DUPLICATE KEY UPDATE '.$update;

		return self::query($sql, null);		
	}

	public function update($table, $data, $condition, $unbuffered = false, $low_priority = false) 
	{
		$sql = self::implode($data);
		if(empty($sql)) {
			return false;
		}
		$cmd = "UPDATE " . ($low_priority ? 'LOW_PRIORITY' : '');
		$table = self::table($table);
		$where = '';
		if (empty($condition)) {
			$where = '1';
		} elseif (is_array($condition)) {
			$where = self::implode($condition, ' AND ');
		} else {
			$where = $condition;
		}
		$res = self::query("$cmd $table SET $sql WHERE $where", $unbuffered ? 'UNBUFFERED' : '');
		return $res;
	}

	public function insert_id() 
	{
		return $this->db->insert_id();
	}

	public function fetch($resourceid, $type = MYSQLI_ASSOC) 
	{
		return $this->db->fetch_array($resourceid, $type);
	}
	
	// 查从库		// 查询获得一条数据
	public function fetch_first($sql, $arg = array(), $silent = false) 
	{
		$res = self::query($sql, $arg, $silent, false);
		$ret = $this->db->fetch_array($res);
		$this->db->free_result($res);
		return $ret ? $ret : array();
	}
	
	// 查主库		// 查询获得一条数据
	public function fetch_first_master($sql, $arg = array(), $silent = false)
	{
		$res = self::query_master($sql, $arg, $silent, false);
		$ret = $this->db->fetch_array($res);
		$this->db->free_result($res);
		return $ret ? $ret : array();
		
	}
	
	// 查从库		// 查询获得多条数据
	public function fetch_all($sql, $arg = array(), $keyfield = '', $silent=false) 
	{
		$data = array();
		$query = self::query($sql, $arg, $silent, false);
		while (($row = $this->db->fetch_array($query))!=false) {
			if ($keyfield && isset($row[$keyfield])) {
				$data[$row[$keyfield]] = $row;
			} else {
				$data[] = $row;
			}
		}
		$this->db->free_result($query);
		return $data;
	}
	
	// 查主库		// 查询获得多条数据
	public function fetch_all_master($sql, $arg = array(), $keyfield = '', $silent=false)
	{
		$data = array();
		$query = self::query_master($sql, $arg, $silent, false);
		while (($row = $this->db->fetch_array($query))!=false) {
			if ($keyfield && isset($row[$keyfield])) {
				$data[$row[$keyfield]] = $row;
			} else {
				$data[] = $row;
			}
		}
		$this->db->free_result($query);
		return $data;
	}
	
	
	

	public function result($resourceid, $row = 0) 
	{
		return $this->db->result($resourceid, $row);
	}

	public function result_first($sql, $arg = array(), $silent = false) 
	{
		$res = self::query($sql, $arg, $silent, false);
		$ret = $this->db->result($res, 0);
		$this->db->free_result($res);
		return $ret;
	}

	public function result_first_master($sql, $arg = array(), $silent = false) 
	{
		$res = self::query_master($sql, $arg, $silent, false);
		$ret = $this->db->result($res, 0);
		$this->db->free_result($res);
		return $ret;
	}

	public function query($sql, $arg = array(), $silent = false, $unbuffered = false) 
	{
		if (!empty($arg)) {
			if (is_array($arg)) {
				$sql = self::format($sql, $arg);
			} elseif ($arg === 'SILENT') {
				$silent = true;

			} elseif ($arg === 'UNBUFFERED') {
				$unbuffered = true;
			}
		}

		self::checkquery($sql);
		self::connect();

		$this->sqls[] = $sql;

		$ret = $this->db->query($sql, $silent, $unbuffered);
		if (!$unbuffered && $ret) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if ($cmd === 'SELECT') {

			} elseif ($cmd === 'UPDATE' || $cmd === 'DELETE') {
				$ret = $this->db->affected_rows();
			} elseif ($cmd === 'INSERT') {
				$ret = $this->db->insert_id();
			}
		}
		return $ret;
	}
	
	// 只查主库上的数据
	public function query_master($sql, $arg = array(), $silent = false, $unbuffered = false) 
	{
		if (!empty($arg)) {
			if (is_array($arg)) {
				$sql = self::format($sql, $arg);
			} elseif ($arg === 'SILENT') {
				$silent = true;

			} elseif ($arg === 'UNBUFFERED') {
				$unbuffered = true;
			}
		}

		self::checkquery($sql);
		self::connect();

		$this->sqls[] = $sql;
		
		if($this->db->db_type=='master'){	// 只有主时   是连去主
			$ret = $this->db->query($sql, $silent, $unbuffered);
		}else{
			$ret = $this->db->query_master($sql, $silent, $unbuffered);
		}
		if (!$unbuffered && $ret) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if ($cmd === 'SELECT') {

			} elseif ($cmd === 'UPDATE' || $cmd === 'DELETE') {
				$ret = $this->db->affected_rows();
			} elseif ($cmd === 'INSERT') {
				$ret = $this->db->insert_id();
			}
		}
		return $ret;
	}
	
	public function begin()
	{
		$this->query_master("BEGIN"); 
	}

	public function commit()
	{
		$this->query_master("COMMIT"); 
	}

	public function rollback()
	{
		$this->query_master("ROLLBACK");
	}

	public function num_rows($resourceid) 
	{
		return $this->db->num_rows($resourceid);
	}

	public function affected_rows() 
	{
		return $this->db->affected_rows();
	}

	public function free_result($query) 
	{
		return $this->db->free_result($query);
	}

	public function error() 
	{
		return $this->db->error();
	}

	public function errno() 
	{
		return $this->db->errno();
	}

	public function checkquery($sql) 
	{
		$safe_check_obj = new database_safecheck(); 
		return $safe_check_obj->checkquery($sql);
	}

	public function quote($str, $noarray = false) 
	{

		if (is_string($str))
			return '\'' . addcslashes($str, "\n\r\\'\"\032") . '\'';

		if (is_int($str) or is_float($str))
			return '\'' . $str . '\'';

		if (is_array($str)) {
			if($noarray === false) {
				foreach ($str as &$v) {
					$v = self::quote($v, true);
				}
				return $str;
			} else {
				return '\'\'';
			}
		}

		if (is_bool($str))
			return $str ? '1' : '0';

		return '\'\'';
	}

	public function quote_field($field) 
	{
		if (is_array($field)) {
			foreach ($field as $k => $v) {
				$field[$k] = self::quote_field($v);
			}
		} else {
			if (strpos($field, '`') !== false)
				$field = str_replace('`', '', $field);
			$field = '`' . $field . '`';
		}
		return $field;
	}

	public function limit($start, $limit = 0) 
	{
		$limit = intval($limit > 0 ? $limit : 0);
		$start = intval($start > 0 ? $start : 0);
		if ($start > 0 && $limit > 0) {
			return " LIMIT $start, $limit";
		} elseif ($limit) {
			return " LIMIT $limit";
		} elseif ($start) {
			return " LIMIT $start";
		} else {
			return '';
		}
	}

	public function order($field, $order = 'ASC') 
	{
		if(empty($field)) {
			return '';
		}
		$order = strtoupper($order) == 'ASC' || empty($order) ? 'ASC' : 'DESC';
		return 'ORDER BY '.self::quote_field($field) . ' ' . $order;
	}

	public function field($field, $val, $glue = '=') 
	{

		$field = self::quote_field($field);

		if (is_array($val)) {
			$glue = $glue == 'notin' ? 'notin' : 'in';
		} elseif ($glue == 'in') {
			$glue = '=';
		}

		switch ($glue) {
			case '=':
				return $field . $glue . self::quote($val);
				break;
			case '-':
			case '+':
				return $field . '=' . $field . $glue . self::quote((string) $val);
				break;
			case '|':
			case '&':
			case '^':
				return $field . '=' . $field . $glue . self::quote($val);
				break;
			case '>':
			case '<':
			case '<>':
			case '<=':
			case '>=':
				return $field . $glue . self::quote($val);
				break;

			case 'like':
				return $field . ' LIKE(' . self::quote($val) . ')';
				break;

			case 'in':
			case 'notin':
				$val = $val ? implode(',', self::quote($val)) : '\'\'';
				return $field . ($glue == 'notin' ? ' NOT' : '') . ' IN(' . $val . ')';
				break;

			default:
				throw new DbException('Not allow this glue between field and value: "' . $glue . '"');
		}
	}

	public function implode($array, $glue = ',') 
	{
		$sql = $comma = '';
		$glue = ' ' . trim($glue) . ' ';
		foreach ($array as $k => $v) {
			$sql .= $comma . self::quote_field($k) . '=' . self::quote($v);
			$comma = $glue;
		}
		return $sql;
	}

	public function implode_field_value($array, $glue = ',') 
	{
		return self::implode($array, $glue);
	}

	public function format($sql, $arg) 
	{
		$count = substr_count($sql, '%');
		if (!$count) {
			return $sql;
		} elseif ($count > count($arg)) {
			throw new DbException('SQL string format error! This SQL need "' . $count . '" vars to replace into.', 0, $sql);
		}

		$len = strlen($sql);
		$i = $find = 0;
		$ret = '';
		while ($i <= $len && $find < $count) {
			if ($sql{$i} == '%') {
				$next = $sql{$i + 1};
				if ($next == 't') {
					$ret .= self::table($arg[$find]);
				} elseif ($next == 's') {
					$ret .= self::quote(is_array($arg[$find]) ? serialize($arg[$find]) : (string) $arg[$find]);
				} elseif ($next == 'f') {
					$ret .= sprintf('%F', $arg[$find]);
				} elseif ($next == 'd') {
					$ret .= HelperUtils::dintval($arg[$find]);
				} elseif ($next == 'i') {
					$ret .= $arg[$find];
				} elseif ($next == 'n') {
					if (!empty($arg[$find])) {
						$ret .= is_array($arg[$find]) ? implode(',', self::quote($arg[$find])) : self::quote($arg[$find]);
					} else {
						$ret .= '0';
					}
				} else {
					$ret .= self::quote($arg[$find]);
				}
				$i++;
				$find++;
			} else {
				$ret .= $sql{$i};
			}
			$i++;
		}
		if ($i < $len) {
			$ret .= substr($sql, $i);
		}
		return $ret;
	}

}

class database_safecheck {

	protected $checkcmd = array('SELECT', 'UPDATE', 'INSERT', 'REPLACE', 'DELETE');
	protected $config = array();

	public function get_config()
	{
		if(empty($this->config)) {
			$this->config['status']         = 1;		// 是否开启SQL安全检测，可自动预防SQL注入攻击
			$this->config['dfunction']['0'] = 'load_file';
			$this->config['dfunction']['1'] = 'hex';
			$this->config['dfunction']['2'] = 'substring';
			$this->config['dfunction']['4'] = 'ord';
			$this->config['dfunction']['5'] = 'char';
			$this->config['daction']['0']   = 'intooutfile';
			$this->config['daction']['1']   = 'intodumpfile';
			$this->config['dnote']['0']     = '/*';
			$this->config['dnote']['1']     = '*/';
			$this->config['dnote']['2']     = '#';
			$this->config['dnote']['3']     = '--';
			$this->config['dnote']['4']     = '"';
			$this->config['dlikehex']       = 1;
			$this->config['afullnote']      = '0';
		}		
	}

	public function checkquery($sql) 
	{
		$this->get_config();
		if($this->config['status']) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if (in_array($cmd, $this->checkcmd)) {
				$test = self::_do_query_safe($sql);
				if ($test < 1) {
					throw new DbException('It is not safe to do this query', 0, $sql);
				}
			}
		}
		return true;
	}

	private function _do_query_safe($sql) 
	{
		$sql = str_replace(array('\\\\', '\\\'', '\\"', '\'\''), '', $sql);
		$mark = $clean = '';
		if (strpos($sql, '/') === false && strpos($sql, '#') === false && strpos($sql, '-- ') === false && strpos($sql, '@') === false && strpos($sql, '`') === false) {
			$clean = preg_replace("/'(.+?)'/s", '', $sql);
		} else {
			$len = strlen($sql);
			$mark = $clean = '';
			for ($i = 0; $i < $len; $i++) {
				$str = $sql[$i];
				switch ($str) {
					case '`':
						if(!$mark) {
							$mark = '`';
							$clean .= $str;
						} elseif ($mark == '`') {
							$mark = '';
						}
						break;
					case '\'':
						if (!$mark) {
							$mark = '\'';
							$clean .= $str;
						} elseif ($mark == '\'') {
							$mark = '';
						}
						break;
					case '/':
						if (empty($mark) && $sql[$i + 1] == '*') {
							$mark = '/*';
							$clean .= $mark;
							$i++;
						} elseif ($mark == '/*' && $sql[$i - 1] == '*') {
							$mark = '';
							$clean .= '*';
						}
						break;
					case '#':
						if (empty($mark)) {
							$mark = $str;
							$clean .= $str;
						}
						break;
					case "\n":
						if ($mark == '#' || $mark == '--') {
							$mark = '';
						}
						break;
					case '-':
						if (empty($mark) && substr($sql, $i, 3) == '-- ') {
							$mark = '-- ';
							$clean .= $mark;
						}
						break;

					default:

						break;
				}
				$clean .= $mark ? '' : $str;
			}
		}

		if(strpos($clean, '@') !== false) {
			return '-3';
		}
		
		$clean = preg_replace("/[^a-z0-9_\-\(\)#\*\/\"]+/is", "", strtolower($clean));

		if ($this->config['afullnote']) {
			$clean = str_replace('/**/', '', $clean);
		}

		if (is_array($this->config['dfunction'])) {
			foreach ($this->config['dfunction'] as $fun) {
				if (strpos($clean, $fun . '(') !== false)
					return '-1';
			}
		}

		if (is_array($this->config['daction'])) {
			foreach ($this->config['daction'] as $action) {
				if (strpos($clean, $action) !== false){
					return '-3';
				}
			}
		}

		if ($this->config['dlikehex'] && strpos($clean, 'like0x')) {
			return '-2';
		}

		if (is_array($this->config['dnote'])) {
			foreach ($this->config['dnote'] as $note) {
				if (strpos($clean, $note) !== false)
					return '-4';
			}
		}

		return 1;
	}

	public function setconfigstatus($data) 
	{
		$this->config['status'] = $data ? 1 : 0;
	}

}