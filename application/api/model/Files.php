<?php 
namespace app\api\model;
use \think\Model;

class Files extends Model{
	protected $autoWriteTimestamp = true;

	// 新增资源信息
	public function addInfo($data){
		$file = self::create($data);
		return $file->id;
	}
}