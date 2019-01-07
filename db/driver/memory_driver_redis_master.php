<?php

// redis 主主模式
class memory_driver_redis{

	
	private $conf;			// 配置信息存放
	private $conn;			// 存放连接，如果没有这个连接
	private $is_conn_arr;	// 是否连接上	0 没有连接 	1 连接上		2 没有连接上
	private $redis_count;	// redis 的数量		// 0 算1			0,1,2  值是 2算三个
	private $redis_mod;		// redis 求模值
	
	
	
	private $master_conn;	// 主redis连接
	private $slave_conn;	// 从redis连接
	private $slave_conf;	// 当前连接的 从 redis
	
	
	
	
	/**
	 * // 缓存主 redis   必须是  	(0,1,2,3,4)有序列的排序
	 * $_config['memory']['redis'][0]['server'] 	= '172.16.10.74';// redis 地址
	 * $_config['memory']['redis'][0]['port'] 		= 6385;			// 端口
	 * $_config['memory']['redis'][0]['pwd'] 		= 'bbsnew';		// 密码
	 * $_config['memory']['redis'][0]['pconnect'] 	= 1;			// 长连接
	 * $_config['memory']['redis'][0]['timeout'] 	= '0.6';		// 时间
	 * $_config['memory']['redis'][0]['serializer'] = 1;			// 是否用这个压缩  	1 是
	 * $_config['memory']['redis'][0]['selectdb']	= '0';			// 选择数据库
	 * 
	 * // 主
	 * $_config['memory']['redis'][1]['server'] 	= '172.16.10.74';// redis 地址
	 * $_config['memory']['redis'][1]['port'] 		= 6385;			// 端口
	 * $_config['memory']['redis'][1]['pwd'] 		= 'bbsnew';		// 密码
	 * $_config['memory']['redis'][1]['pconnect'] 	= 1;			// 长连接
	 * $_config['memory']['redis'][1]['timeout'] 	= '0.6';		// 时间
	 * $_config['memory']['redis'][1]['serializer'] = 1;			// 是否用这个压缩  	1 是
	 * $_config['memory']['redis'][1]['selectdb']	= '0';			// 选择数据库
	 * 
	 * */
	
	
	// 初使化
	function init($config) {
		
		$this->conf			= $config;
		$this->redis_mod	= count($config);
		$this->redis_count	= $this->redis_mod-1;
		for($i=0;$i<=$this->redis_count;$i++){
			$this->is_conn_arr[$i]	= 0;
			
		}
		
		
	}

	/*
	function &instance() {
		static $object;
		if(empty($object)) {
			$object = new memory_driver_redis();
			$object->init(getglobal('config/memory/redis'));
		}
		return $object;
	}
	*/
	
	
	
	/**
	 * redis 的连接
	 * */ 
	private function redis_connect($index){
		if($index>$this->redis_count){
			$index	= $this->redis_count;
			
		}else if($index<0){
			$index	= 0;
		}
		$conf	= $this->conf[$index];		// 配置文件
		$this->conn[$index]	= new Redis();
		
		
		try {
		    $connect	= $this->conn[$index]->connect($conf['server'],$conf['port'],$conf['timeout']);
			if(!$connect){
				//echo "redis 没有连上";
			}
			if( isset($conf['pwd']) && $conf['pwd']!=''){
			    $this->conn[$index]->auth($conf['pwd']);	// 密码
			}
			
			if(isset($conf['selectdb'])){
				$this->conn[$index]->select($conf['selectdb']);
			}
			$this->is_conn_arr[$index]	= 1;	// 连接上redis
			
		} catch (Exception $e) {
			//log_message('error', $e, true);
			$this->is_conn_arr[$index]	= 2;	// 没有连接上
		}
        return $this->conn[$index];
		
	}
	
	
	/**
	 * 获得 redis 对象
	 * 
	 * */
	private function get_redis($key){
		$base_str1	= substr($key, -1 , 1);
		$base_num	= ord($base_str1);
		$index		= $base_num % $this->redis_mod;
		
		if($this->is_conn_arr[$index]==0){	// 没有连接上,去连接
			$this->redis_connect($index);
		}
		
		if($this->is_conn_arr[$index]==1){	// 己连接上
			return $this->conn[$index];
		}else{	// 连接出错
			return false;
		}
		
	}
	
	
	
	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
/*	
	private function master(){
		if(!$this->master_conn){
			$this->master_connect();
		}
		if( !is_object($this->master_conn) ){
			return false;
		}else{
			return true;
		}
	}
	
	// 主 redis 连接
	private function master_connect(){
		if($this->master_conn){
			return $this->master_conn;
		}
		
		$this->master_conn = new Redis();
        try {
		    $connect	= $this->master_conn->pconnect($this->conf['server'],$this->conf['port'],$this->conf['timeout']);
			if(!$connect){
				//echo "redis 没有连上";
			}
			if( isset($this->conf['pwd']) && $this->conf['pwd']!=''){
			    $this->master_conn->auth($this->conf['pwd']);	// 密码
			}
			if(isset($this->conf['selectdb'])){
				$this->master_conn->select($this->conf['selectdb']);
			}
			// @$this->master_conn->setOption(Redis::OPT_SERIALIZER, $this->conf['serializer'] );
		} catch (Exception $e) {
			//log_message('error', $e, true);
			$this->master_conn = -1;
		}
        return $this->master_conn;
		
	}
*/	
	
	
	
	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
/*	
	private function slave(){
		if(!$this->slave_conn){
			$this->slave_connect();
		}
		if(!is_object($this->slave_conn)){
			return false;
		}else{
			return true;
		}
	}
*/
		
	/**
	 * 从 redis 的连接
	 * ***/
/*	
	private function slave_connect(){
		if($this->slave_conn){
			return $this->slave_conn;
		}
		
		if( isset($this->conf['slave']) && is_array($this->conf['slave']) ){
			$key	= array_rand($this->conf['slave']);
			$this->slave_conf	= $this->conf['slave'][$key];
			
			$this->slave_conn	= new Redis();
			try{
				$connent	= $this->slave_conn->pconnect($this->slave_conf['server'],$this->slave_conf['port'],$this->slave_conf['timeout']); 
				if(!$connent){
					// echo "redis 没有连上";
				}
				if( isset($this->slave_conf['pwd']) && $this->slave_conf['pwd']!=''){
				    $this->slave_conn->auth($this->slave_conf['pwd']);	// 密码
				}
				if( isset($this->slave_conf['selectdb']) ){
					$this->slave_conn->select($this->slave_conf['selectdb']);
				}
				// @$this->slave_conn->setOption(Redis::OPT_SERIALIZER, $this->conf['serializer'] );
				
			}catch(Exception $e){
				//log_message('error', $e, true);
				$this->slave_conn	= -1;
			}
			
		}else{	// 没有从库  用主
			if(!$this->master_conn){	// 主没有连接
				$this->master_connect();
			}
			$this->slave_conn = $this->master_conn;
		}
		
		return $this->slave_conn;
	}
*/	

	function rget($key){
		$obj 	= $this->get_redis($key);
		if($obj){
			$data 	= $obj->get($key);
			if( !empty($data) ){
				$data	= @ json_decode($data , true);
			}else{
				$data	= false;
			}
			return $data;
		}else{
			return false;
		}
	}
	
	// 获得key		$key 可以是数组
	function get($key) {
		if(is_array($key)) {
			return $this->getMulti($key);
		}
		
		return $this->rget($key);
	}
	
	// 获取
	function getMulti($keys) {
		$newresult = array();
		foreach ($keys as $key){
			$newresult[$key]	= $this->rget($key);
		}
		
		return $newresult;
	}
	
	// 选择
	function select($db=0) {
		$obj 	= $this->get_redis();
		$obj->select($db);
		
		return true;
	}
	
	// 写
	function set($key, $value, $ttl = 0) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		
		$value 	= @json_encode($value);	// 转成  json格式
		if($ttl==0){
			$ttl 	= 3605;
		}
		if($ttl) {
			return $obj->setex($key, $ttl, $value);
		} else {
			return $obj->set($key, $value);
		}
	}
	
	// 删
	function rm($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		
		return $obj->delete($key);
	}
	
	// 写
	function setMulti($arr, $ttl=0) {
		if(!is_array($arr)) {
			return false;
		}
		foreach($arr as $key => $v) {
			$this->set($key, $v, $ttl);
		}
		return true;
	}
	
	// 自增
	function inc($key, $step = 1) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->incr($key, $step);
	}
	
	// 自减
	function dec($key, $step = 1) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->decr($key, $step);
	}
	
	// 写
	function getSet($key, $value) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		$value 	=  @json_encode($value);	// 转成  json格式
		return $obj->getSet($key, $value);
	}
	
	function sRemove($key, $value) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->sRemove($key, $value);
	}

	function sMembers($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->sMembers($key);
	}
	
	// 获得集合值
	function keys($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->keys($key);
	}
	
	// 设置 key 生成时间
	function expire($key, $second){
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->expire($key, $second);
	}

	function sCard($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->sCard($key);
	}
	
	// 添加元素
	function hSet($key, $field, $value) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hSet($key, $field, $value);
	}

	public function hGet($key, $field) 
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hGet($key, $field);
	}

	public function hMSet($key, $data)
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hMSet($key, $data);
	}

	public function hmGet($key, $fields)
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hmGet($key, $fields);
	}



	function hDel($key, $field) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hDel($key, $field);
	}

	function hLen($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hLen($key);
	}

	function hVals($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hVals($key);
	}
	
	// 加值
	function hIncrBy($key, $field, $incr){
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hIncrBy($key, $field, $incr);
	}
	
	// 获取
	function hGetAll($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->hGetAll($key);
	}

	function sort($key, $opt) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->sort($key, $opt);
	}

	function exists($key) {
		$obj 	= $this->get_redis($key);
		if(!$obj){	return false;	}
		return $obj->exists($key);
	}

	function clear() {
		
		return true;
	}


	 //直接调用redis扩展的函数
     public function __call($name, $arguments) 
     {
		if (!$this->slave()) {
			return false;
		}

		if(!method_exists($this->slave_conn, $name)){
			return false;
		}

		return call_user_func_array(array($this->slave_conn, $name), $arguments);
     }

	public function close() 
	{
		$res = $this->slave_conn && $this->slave_conn->close();
		$ret = $this->master_conn && $this->master_conn->close();

		return $res || $ret;
	}

	public function __destruct()
	{
		$this->close();
	}
     

}












?>