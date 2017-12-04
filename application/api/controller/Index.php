<?php
namespace app\api\controller;

class Index extends ApiBase
{
    protected function _initialize()
    {
        
    }
    public function index(){
        //dump($this->params);
        return 'api';
    }

}
