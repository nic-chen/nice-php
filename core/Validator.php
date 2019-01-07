<?php

/*
* @author: 4061470@qq.com
*/

class Validator
{

	public function validate($rules, $array_param)
	{
		foreach($rules as $rule){
			$column = $rule[0];
			$type   = $rule[1];
			$value  = isset($array_param[$column]) ? $array_param[$column] : null;
			switch ($type) {
				case 'ip':
					if(!$this->checkIp($value)){
						return false;
					}
					break;
				case 'date':
					if(!$this->checkIsDate($value)){
						return false;
					}
					break;
				case 'url':
					if(!$this->checkUrl($value)){
						return false;
					}
					break;
				case 'email':
					if(!$this->checkEmail($value)){
						return false;
					}
					break;					
				case 'in':
					if($value && !$this->checkIn($value, $rule)){
						return false;
					}
					break;
				case 'between':
					if(!$this->checkBetween($value, $rule)){
						return false;
					}
					break;
				case 'compare':
					if(!$this->checkCompare($value, $rule)){
						return false;
					}
					break;										
				case 'custom':
					if(!$this->customCheck($value, $rule)){
						return false;
					}
					break;

				case 'required':
					if($value===null){
						return false;
					}
					break;					

				default:
					break;
			}
		}

		return true;
	}

	public function checkIsDate($value)
	{
		return (strtotime($value) && substr($value, 0, 10)==date('Y-m-d',strtotime($value))) ;
	}

	public function checkIp($str)
	{
		$arr = explode('.', $str);
		for($i=0;$i<count($arr);$i++)
		{
			if($arr[$i]>255){
				return false;
			}
		}
		return preg_match('^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$',$str);
	}

	public function checkEmail($value)
	{
		$pattern = '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';		
		$valid   = is_string($value) && strlen($value)<=254 && preg_match($pattern, $value);
		if($valid){
			$domain = substr($value, strpos($value, '@')+1);	
		}
		return $valid;
	}

	public function checkUrl($value)
	{
		$pattern='/^((http||https):)?\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)/i';
		return (is_string($value) && strlen($value)<2000 && preg_match($pattern,$value));
	}

	public function checkIn($value, $rule)
	{
		$range = $rule['range'];
		return in_array($value, $range);
	}

	public function checkBetween($value, $rule)
	{
		$max = $rule['max'];
		$min = $rule['min'];
		return $value<=$max && $value>=$min;
	}

	public function checkCompare($value, $rule)
	{
		$compare_value    = $rule['to'];
		$compare_operater = $rule['op'];
		if(in_array($compare_operater, array('==','>','<','>=','<='))){
			return eval('return $value '.$compare_operater.' $compare_value;');
		}
		return true; 
	}

	public function customCheck($value, $rule)
	{
		$object = $rule['object'];
		$method = $rule['method'];
		if(is_callable(array($object, $method))){
			return call_user_func(array($object, $method), $value);
		}
		return true;	
	}
	public function checkPhone($value){
		return preg_match('/^1[0-9]{10}$/iu', $value);
	}


}