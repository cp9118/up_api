<?php 
namespace app\api\controller;
use think\Controller;
use app\api\controller\Cron;
use app\api\model\Files;

class Ffmpeg extends Controller{

	public function run(){
		$cron = new Cron;

		//获取队列数据
		$data = $cron->get();
		if ($data !== false ) {
			// 把取出来的数据存入文件缓存，防止ffmpeg转换出错重新恢复进队列
			//cache($data['file_name'],$data);
			//dump($data);
			$this->ffmpeg($data);
		}else{
			// 没有数据
			exit;
		}
		// dump($data);
		// exit;
		// $data = cache('2766ceb650284681.mp4');
		// //echo "success \n";
		// dump($data);
		// $this->ffmpeg($data);

	}
	public function getdata($id){
		$data = Files::get($id);
		$data = json_decode($data,true);
		$data['file_infos'] = json_decode($data['file_infos'],true);
		dump($data);
	}

	public function ffmpeg($data,$cmd=''){
		$id = $data['id'];
		unset($data['id']);
		// 文件真实路径
		$file = ROOT_PATH . $data['file_infos']['path']['video_path'] . DS . $data['file_name'];
		// 播放预览图地址 
		$preview = ROOT_PATH . $data['file_infos']['path']['thumb_path'] . DS . get_md5_16(md5($data['file_name']) . 'preview') . '.jpg';
		// 精灵图地址
		$sprites = ROOT_PATH . $data['file_infos']['path']['thumb_path'] . DS . get_md5_16(md5($data['file_name']) . 'sprites') . '.jpg';
		
		// 生成播放器预览图 大小 750x413
		$cmd_preview = "ffmpeg -ss 5 -i {$file}  -r 1 -vframes 1 -an -vcodec mjpeg -s 750x413 -y {$preview}";
		exec($cmd_preview, $res, $status);
		
		// 精灵图大小 256x145
		// 判断时间长度，计算fps 提取每针长度的截图
		$time = $this->get_time($data['file_infos']['video']['vtime']);
		
		// 没xx针截图拼接成15x15一张图片 算法。
		$zhen = $time * $data['file_infos']['video']['fps'] / (15 * 15); 
		$cmd_sprites = 'ffmpeg -i '.$file.' -frames 1 -vf "select=not(mod(n\,'.intval($zhen).')),scale=256:145,tile=15x15" -y ' . $sprites;
		exec($cmd_sprites, $res, $status);

		// 更新数据库
		$filis = new Files;
		$data['file_infos']['video']['sprites'] = str_replace(ROOT_PATH . 'public/' , '' , $sprites);
        $data['file_infos']['video']['preview'] = str_replace(ROOT_PATH . 'public/' , '' , $preview);
        $update_data = json_encode($data['file_infos']);
        $res = $filis->update(['id' => $id, 'file_infos' => $update_data]);
		echo "success\n";

	}

	// 把视频时间转换成秒
	private function get_time($time){
		$vtime = explode( ':' , $time );
		if (count($vtime) == 1) {
			return $vtime;
		}elseif(count($vtime) == 2){
			return $vtime[0]*60 + $vtime[1];
		}else{
			return $vtime[0]*3600 + $vtime[1]*60 + $vtime[2];
		}
	}

	// 生成m3u8
	private function create_m3u8($data){

		exec($cmd, $res, $status);
	}

	// 转码 加水印 加 logo
	private function zhuanma($data,$file){
		$zhuanma = false;
		$md5 = get_md5_16(md5($data['file_name'] . $data['from_user']));
		$savePath = ROOT_PATH . 'video' . DS . $md5 ;
		/******************  分析是否需要转码  ******************/
		// 时间小于10分钟不转码 大小小于50M不转码 分辨率 小于640x480 不转码
		$time = $this->get_time($data['file_infos']['video']['vtime']);
		$fbl = explode('x' , $data['file_infos']['video']['fbl']);
		$fbl = count($fbl[1]) == 2 ? $fbl[1] : 0 ;
		if ($time > 600 && $data['file_infos']['video']['size_original'] > 52428800 && $fbl > 480) {
			$zhuanma = true;
		}
		if ($zhuanma) {
			if (!file_exists($savePath)) {
			    mkdir($savePath);
			}			
			$cmd = 'ffmpeg -i '.$file.' -b 300k -b:v 300k -r 24 -s 640x360  ' . $savePath .DS. date('Y-m-d').'_'.$data['file_name'];
			exec($cmd, $res, $status);
		}
	} 

}