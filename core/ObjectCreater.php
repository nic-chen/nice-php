<?php

/*
* @author: 4061470@qq.com
*/

class ObjectCreater
{
    private $_objs = array();

    static public function instance()
    {
        static $creater = null;
        if(is_null($creater))
        {
            $creater = new ObjectCreater();
        }
        return $creater;
    }

    private function creatImpl($name)
    {
        if(!isset($this->_objs[$name])) 
        {
            //DBC::unExpect($name, "$name no register!");
            $this->_objs[$name] = new $name();
        }
        return $this->_objs[$name] ;
    }

    static public function create($name)
    {
        $creater = self::instance();
        return  $creater->creatImpl($name);
    }

}