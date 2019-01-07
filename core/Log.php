<?php

/*
* @author: 4061470@qq.com
*/

class Log extends Component
{
	public $logPath;
	public $logFile      = 'application.log';
	public $maxFileSize  = 1024; // in KB
	public $maxLogFiles  = 5;
	public $autoFlush    = 3000;
	
	private $_logs       = array();
	private $_processing = false;
	private $_logCount   = 0;
	
	const LEVEL_TRACE    = 'trace';
	const LEVEL_WARNING  = 'warning';
	const LEVEL_ERROR    = 'error';
	const LEVEL_INFO     = 'info';
	const LEVEL_PROFILE  = 'profile';
 
	public function __construct()
	{
	}

	public function init()
	{
		parent::init();
		if($this->getLogPath()===null){
			throw new CoreException('Log path can not be null.');
		}
		Nice::event()->bindEventHandler('onEndRequest', array($this,'dump'), Nice::app());
		//Yii::event()->bindEventHandler('onEndRequest',);
	}

	public function getLogPath()
	{
		return $this->logPath;
	}

	public function setLogPath($value)
	{
		$this->logPath = realpath($value);
		if($this->logPath===false || !is_dir($this->logPath) || !is_writable($this->logPath)){
			$message = sprintf('Log path  "{%s}" does not point to a valid directory. Make sure the directory exists and is writable by the Web server process.', $this->logPath);
			throw new CoreException($message);
		}
	}

	public function getLogFile()
	{
		return $this->logFile;
	}

	public function setLogFile($value)
	{
		$this->logFile = $value;
	}

	public function getMaxFileSize()
	{
		return $this->maxFileSize;
	}

	public function setMaxFileSize($value)
	{
		if(($this->maxFileSize=(int)$value)<1){
			$this->maxFileSize=1;
		}
	}

	public function getMaxLogFiles()
	{
		return $this->maxLogFiles;
	}

	public function setMaxLogFiles($value)
	{
		if(($this->maxLogFiles=(int)$value)<1)
			$this->maxLogFiles=1;
	}

	public function log($message, $level=self::LEVEL_ERROR)
	{
		list($msec, $usec) = explode(" ", microtime());
		$time              = date('Y-m-d H:i:s') .'.'. $msec;
		$this->_logs[]     = array($message, $level, $time);

		$this->_logCount++;
		if($this->autoFlush>0 && $this->_logCount>=$this->autoFlush && !$this->_processing)
		{
			$this->_processing=true;
			$this->dump();
			$this->_processing=false;
		}
	}

	public function dump()
	{
		$logFile = $this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
		if(@filesize($logFile)>$this->getMaxFileSize()*1024){
			$this->rotateFiles();
		}

		$fp=@fopen($logFile, 'a');
		@flock($fp, LOCK_EX);
		foreach($this->_logs as $log){
			@fwrite($fp, $this->format($log[0], $log[1], $log[2]));
		}
		@flock($fp, LOCK_UN);
		@fclose($fp);
	}

	protected function format($message, $level, $time)
	{
		return "[{$level}]:".$message.',time:'.$time."\n";
	} 

	protected function rotateFiles()
	{
		$file = $this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
		$max  = $this->getMaxLogFiles();
		for($i=$max;$i>0;--$i)
		{
			$rotateFile=$file.'.'.$i;
			if(is_file($rotateFile))
			{
				// suppress errors because it's possible multiple processes enter into this section
				if($i===$max){
					@unlink($rotateFile);
				}else{
					@rename($rotateFile,$file.'.'.($i+1));
				}
			}
		}
		if(is_file($file)){
			@rename($file, $file.'.1'); // suppress errors because it's possible multiple processes enter into this section
		}
	}

}
