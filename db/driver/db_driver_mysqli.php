<?php

if(!defined('BASE_ROOT')) {
	exit('Access Denied');
}

class db_driver_mysqli
{
	var $tablepre;
	var $version = '';
	var $querynum = 0;
	var $slaveid = 0;
	var $curlink;
	var $link = array();
	var $config = array();
	var $sqldebug = array();
	var $map = array();
	var $db_type 	= 'master';	// 标记，主库
	public $allow_ddl = false;

	function db_mysql($config = array()) {
		if(!empty($config)) {
			$this->set_config($config);
		}
	}

	function set_config($config) {
		$this->config = &$config;
		$this->tablepre = $config['1']['tablepre'];
		if(!empty($this->config['map'])) {
			$this->map = $this->config['map'];
			for($i = 1; $i <= 100; $i++) {
				if(isset($this->map['forum_thread'])) {
					$this->map['forum_thread_'.$i] = $this->map['forum_thread'];
				}
				if(isset($this->map['forum_post'])) {
					$this->map['forum_post_'.$i] = $this->map['forum_post'];
				}
				if(isset($this->map['forum_attachment']) && $i <= 10) {
					$this->map['forum_attachment_'.($i-1)] = $this->map['forum_attachment'];
				}
			}
			if(isset($this->map['common_member'])) {
				$this->map['common_member_archive'] =
				$this->map['common_member_count'] = $this->map['common_member_count_archive'] =
				$this->map['common_member_status'] = $this->map['common_member_status_archive'] =
				$this->map['common_member_profile'] = $this->map['common_member_profile_archive'] =
				$this->map['common_member_field_forum'] = $this->map['common_member_field_forum_archive'] =
				$this->map['common_member_field_home'] = $this->map['common_member_field_home_archive'] =
				$this->map['common_member_validate'] = $this->map['common_member_verify'] =
				$this->map['common_member_verify_info'] = $this->map['common_member'];
			}
		}
	}

	function connect($serverid = 1) 
	{
		if(empty($this->config) || empty($this->config[$serverid])) {
			$this->halt('config_db_not_found');
		}
		$this->link[$serverid] = $this->_dbconnect(
			$this->config[$serverid]['dbhost'],
			(isset($this->config[$serverid]['dbport']) ? $this->config[$serverid]['dbport'] : null),
			$this->config[$serverid]['dbuser'],
			$this->config[$serverid]['dbpw'],
			$this->config[$serverid]['dbcharset'],
			$this->config[$serverid]['dbname'],
			$this->config[$serverid]['pconnect']
		);
		$this->curlink = $this->link[$serverid];

	}

	function _dbconnect($dbhost, $dbport, $dbuser, $dbpw, $dbcharset, $dbname, $pconnect, $halt = true) 
	{
		//兼容 ip:port
		if(strpos($dbhost, ':')){
			$tmp = explode(':', $dbhost);
			$dbhost = $tmp[0];
			$dbport =  $tmp[1] ?  $tmp[1] : $dbport;
		}

		if($pconnect) {
			$link = mysqli_connect('p:'.$dbhost, $dbuser, $dbpw, $dbname, $dbport);
		} else {
			$link = mysqli_connect($dbhost, $dbuser, $dbpw, $dbname, $dbport);
		}
		
		if(!$link) {
			$halt && $this->halt('notconnect h:'.$dbhost.' pc:'.$pconnect.' db:'.$dbname. mysqli_connect_error(), mysqli_connect_errno());
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$dbcharset = $dbcharset ? $dbcharset : $this->config[1]['dbcharset'];
				$serverset = $dbcharset ? 'character_set_connection='.$dbcharset.', character_set_results='.$dbcharset.', character_set_client=binary' : '';
				$serverset .= $this->version() > '5.0.1' ? ((empty($serverset) ? '' : ',').'sql_mode=\'\'') : '';
				$serverset && mysqli_query($link, "SET $serverset");
			}
		}
		return $link;
	}


	public function get_table_name_pre($tablename)
	{
		$pre = $this->tablepre;
		$config = $this->config;
		if(isset($config['bbs']) && isset($config['bbs']['pre']) && isset($config['bbs']['tables']) && in_array($tablename, $config['bbs']['tables'])){
			$pre = $config['bbs']['pre'];
		}

		return $pre;
	}


	function table_name($tablename) 
	{
		if(!empty($this->map) && !empty($this->map[$tablename])) {
			$id = $this->map[$tablename];
			if(!$this->link[$id]) {
				$this->connect($id);
			}
			$this->curlink = $this->link[$id];
		} else {
			$this->curlink = isset($this->link[1]) ? $this->link[1] : null;
		}

		$table_pre = $this->get_table_name_pre($tablename);

		return $table_pre.$tablename;
	}

	function select_db($dbname) 
	{
		return mysqli_select_db($dbname, $this->curlink);
	}

	function fetch_array($query, $result_type = MYSQLI_ASSOC) 
	{
		return mysqli_fetch_array($query, $result_type);
	}

	function fetch_first($sql) 
	{
		return $this->fetch_array($this->query($sql));
	}

	function result_first($sql) 
	{
		return $this->result($this->query($sql), 0);
	}

	public function query($sql, $silent = false, $unbuffered = false) {
		if(!$this->allow_ddl && preg_match('/^\s?(alter|drop|create)/iu', $sql)){
			return false;
		}
    	if(defined('DEBUG') && DEBUG) {
			$starttime = microtime(true);
		}

		if('UNBUFFERED' === $silent) {
			$silent = false;
			$unbuffered = true;
		} elseif('SILENT' === $silent) {
			$silent = true;
			$unbuffered = false;
		}

		if(!($query = mysqli_query($this->curlink, $sql))) {
			if(in_array($this->errno(), array(2006, 2013)) && substr($silent, 0, 5) != 'RETRY') {
				$this->connect();
				return $this->query($sql, 'RETRY'.$silent);
			}
			if(!$silent) {
				$this->halt($this->error(), $this->errno(), $sql);
			}
		}

		if(defined('DEBUG') && DEBUG) {
			$this->sqldebug[] = array($sql, number_format((microtime(true) - $starttime), 6), debug_backtrace(), $this->curlink);
		}

		$this->querynum++;
		return $query;
	}

	function affected_rows() 
	{
		return mysqli_affected_rows($this->curlink);
	}

	function error() 
	{
		return (($this->curlink) ? mysqli_error($this->curlink) : mysqli_error());
	}

	function errno() 
	{
		return intval(($this->curlink) ? mysqli_errno($this->curlink) : mysqli_errno());
	}

	function result($query, $row = 0, $field=0) 
	{
	    $query->data_seek($row);
	    $data = $this->fetch_array($query, is_int($field) ? MYSQLI_NUM : MYSQLI_ASSOC); 
	    return isset($data[$field]) ? $data[$field] : null; 
	}

	function num_rows($query) 
	{
		$query = mysqli_num_rows($query);
		return $query;
	}

	function num_fields($query) 
	{
		return mysqli_num_fields($query);
	}

	function free_result($query) 
	{
		return mysqli_free_result($query);
	}

	function insert_id() 
	{
		return ($id = mysqli_insert_id($this->curlink)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	function fetch_row($query) 
	{
		$query = mysqli_fetch_row($query);
		return $query;
	}

	function fetch_fields($query) 
	{
		return mysqli_fetch_field($query);
	}

	function version() 
	{
		if(empty($this->version)) {
			$this->version = mysqli_get_server_info($this->curlink);
		}
		return $this->version;
	}

	function halt($message = '', $code = 0, $sql = '') 
	{
		throw new DbException($message, $code, $sql);
	}


	public function close() 
	{
		$res = false;
		foreach($this->link  as $link){ 
			$ret = mysqli_close($link);
			$res = $ret ? true : $res;
		}
		return $res;
	}

	public function __destruct()
	{
		$res = $this->close();
	}


}

if(!function_exists('mysqli_result')){
	function mysqli_result($res, $row, $field=0) { 
	    $res->data_seek($row); 
	    $datarow = $res->fetch_array(); 
	    return $datarow[$field]; 
	} 
}