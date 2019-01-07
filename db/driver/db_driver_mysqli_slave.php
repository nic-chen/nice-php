<?php

if(!defined('BASE_ROOT')) {
	exit('Access Denied');
}

class db_driver_mysqli_slave extends db_driver_mysqli
{

	var $slaveid      = null;
	var $db_type      = 'slave';		// 标记， 从库
	var $slavequery   = 0;
	var $slaveexcept  = false;
	var $excepttables = array();

	function set_config($config) 
	{
		parent::set_config($config);
		if(!empty ($this->config['slave'])) {
			$sid = array_rand($this->config['slave']);
			$this->slaveid = 1000 + $sid;
			$this->config[$this->slaveid] = $this->config['slave'][$sid];

			if(isset($this->config['common']) && isset($this->config['common']['slave_except_table']) && $this->config['common']['slave_except_table']) {
				$this->excepttables = explode(',', str_replace(' ', '', $this->config['common']['slave_except_table']));
			}
			unset($this->config['slave']);
		}
	}

    function table_name($tablename) 
    {
		if($this->slaveid && !$this->slaveexcept && $this->excepttables) {
			if(in_array($tablename, $this->excepttables)) {
				$this->slaveexcept = true;
			}
		}
		return parent::table_name($tablename);
    }

	function slave_connect() 
	{
		if($this->slaveid) {
			if(!isset($this->link[$this->slaveid])) {
				$this->connect($this->slaveid);
			}
			$this->slavequery ++;
			$this->curlink = $this->link[$this->slaveid];
		}
	}
	
	
	//  主库的连接选择
	var $master_id 	= null;
	function master_connect()
	{
		if(!$this->master_id) {
			foreach($this->link as $key=>$val){
				if($key!=$this->slaveid){
					$this->master_id = $key;
				}
			}
		}
		if($this->master_id){
			$this->curlink = $this->link[$this->master_id];
		}else{
			$this->master_id = 1;
			if(!isset($this->link[$this->master_id])){
				$this->connect($this->master_id);
			}
			$this->curlink = $this->link[$this->master_id];
		}
	}
	
	
	// 由 sql 决定 查主库还是从库
	function query($sql, $silent = false, $unbuffered = false) 
	{
		if($this->slaveid && !$this->slaveexcept && strtoupper(substr($sql, 0 , 6)) == 'SELECT') {
			$this->slave_connect();
		}else{
			$this->master_connect();
		}
		
		$this->slaveexcept = false;
		return parent::query($sql, $silent, $unbuffered);
	}
	
	// 只查主库上的数据
	function query_master($sql, $silent = false, $unbuffered = false)
	{
		$this->slaveexcept = false;
		$this->master_connect();
		return parent::query($sql, $silent, $unbuffered);
		
	}
	
}
