<?php 
namespace app\api\controller;
use think\Controller;
use think\cache\driver\Redis;

class Cron extends Controller{
	protected $redis;
	protected $key;
	protected function _initialize(){
		parent::_initialize();
		//实例化redis
		$options = [
			//'host' => '127.0.0.1',
			//'port' => 9009,
			//'password' => '545408',
		];
		$this->redis = new Redis();
		$this->redis = $this->redis->handler();
		$this->key = 'videolist';
	}

	// 新增队列数据
	public function add($value = ''){
		$value = is_array($value) ? json_encode($value) : $value;
		$this->redis->lpush($this->key , $value);
	}

	//获取一个队列数据
	public function get(){
		if ($this->len() == 0) {
			return false;
		}
		$data = $this->redis->rpop($this->key);
		$data = json_decode($data,true);
		$data['file_infos'] = json_decode($data['file_infos'],true);
		return $data;
	}

	// 获取队列长度
	public function len(){
		return intval( $this->redis->llen($this->key) );
	}

}