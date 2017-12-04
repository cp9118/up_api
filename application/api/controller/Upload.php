<?php
namespace app\api\controller;
use think\Validate;
use think\Cache;
use app\api\model\Files;
use app\api\controller\Cron;

class Upload extends ApiBase
{
    protected $fileData;
    protected $fileHash; //文件哈希
    protected $chunk; // 分片序号
    protected $chunks; // 分片总数

    protected $uploadPath; //最终保存目录
    protected $uploadTmp; //临时保存目录
    protected $fileSavePath; //临时保存目录
    protected $thumbPath; //缩略图保存路径
    protected $thumbName; //缩略图名称

    protected function _initialize()
    {
        parent::_initialize();
        $this->fileData = $this->request->param();
        // 移除token 和time，因为访问到这里token和time已经验证通过
        unset($this->fileData['token']);
        unset($this->fileData['time']);
        //判断文件参数
        if ( !$this->fileData || 
            empty($this->fileData['name']) || 
            empty($this->fileData['size']) || 
            empty($this->fileData['lastModifiedDate']) ||
            empty($this->fileData['username'])
        ){
            $this->return_msg(0,'请选择文件');
            header("HTTP/1.0 500 Internal Server Error");exit;
        }

        /******************  判斷是否有分片  ******************/
        $this->chunk = isset($this->fileData["chunk"]) ? intval($this->fileData["chunk"]) : 0;
        $this->chunks = isset($this->fileData["chunks"]) ? intval($this->fileData["chunks"]) : 0;

        // 构造文件哈希
        $this->fileHash = get_md5_16(md5( $this->fileData['name'] . $this->fileData['size'] .$this->fileData['lastModifiedDate']));

        /******************  文件储存路径  ******************/
        $this->uploadPath = ROOT_PATH . 'uploads' ;// . DS . 'uploads';
        $this->uploadTmp = ROOT_PATH . 'uploads_tmp';// . DS . 'upload_tmp';
        $this->thumbPath = ROOT_PATH . 'public' . DS .'thumb';
        $this->thumbName = get_md5_16( md5('thumb_' . $this->fileHash . '_thumb') ) . '.jpg';
               
    }

    // 视频上传
    public function video(){ 

        
        /******************  获取文件对象  ******************/
        $file = $this->request->file('file');
        /******************  文件验证  ******************/
        $rule = ['file' => [
                'require',
                'fileExt'=>'avi,mpg,mpeg,mov,mkv,mp4,wmv,flv',
                'fileSize'=>524288000]
            ];
        $msg = ['file' => [
                'require' =>'请选择上传文件',
                'fileExt'=>lang('允许的视频格式1：avi、mpg、mpeg、mov、mkv、mp4、wmv、flv'),
                'fileSize'=>lang('视频大小不能超过500 MB')]
            ];
        $check_file = $this->validate(['file' => $file], $rule , $msg);
        if ( true !== $check_file ) {
            $this->return_msg( 0 ,lang( $check_file ));
        }
        /******************  获取文件后缀  ******************/
        $exp = $this->get_exp($this->fileData['type']);
        /******************  构造最终保存目录  ******************/
        $fileHash = $this->fileHash;
        //$uploadPath = $this->chunks == true ?  $this->uploadTmp  : $this->uploadPath;
        $uploadPath = $this->uploadTmp . DS . $fileHash ;
        $saveName = $fileHash . DS .$fileHash . $exp .'.prak_'. $this->chunk;
        
        // 移动文件
        $info = $file->move( $this->uploadTmp , $saveName );
        // 上传失败
        if (!$info) {
            $this->return_msg( 0 ,lang($file->getError()));
        }
        
        // 上传成功
        $filesavepath =  $this->uploadTmp . DS . $saveName; //文件路径+文件名 $info->getSaveName()
        
        // 设置两个状态 $done - 文件存在检查 ， $done_all - 文件合并检查
        $done = true;
        $done_all = false;

        // 逐个检查文件是否存在
        for ( $i=0; $i < $this->chunks; $i++ ) {
            $f = $this->uploadTmp . DS . $fileHash . DS .$fileHash . $exp .'.prak_'. $i ;
            if (!file_exists( $this->uploadTmp . DS . $fileHash . DS .$fileHash . $exp .'.prak_'. $i )) {
                $done = false;
                break;
            }
        }
        
        if ($done) {
            unset($info);
            unset($file);
            //合并转移文件            
            $outpath = $this->uploadPath . DS . $fileHash . DS . $fileHash . $exp;
            //创建新目录
            if (!file_exists($this->uploadPath . DS . $fileHash)) {
                mkdir($this->uploadPath . DS . $fileHash);
            }
            
            if (!$out = fopen($outpath, "wb")) {
                $this->return_msg( 0 , '无法创建新文件') ;
            }
            if ( flock($out, LOCK_EX) ) {
                $chunks = $this->chunks ? $this->chunks : 1 ;
                for( $i = 0; $i < $chunks; $i++ ) {
                    $in_file = $this->uploadTmp . DS . $fileHash . DS .$fileHash . $exp .'.prak_'. $i ;
                    if (!$in = fopen($in_file, "rb")) {
                        break;
                    }
                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    //删除临时分片文件
                    unlink($in_file);
                }
                flock($out, LOCK_UN);
            }
            fclose($out);
            //删除临时目录
            rmdir($uploadPath);
            $done_all = true;
        }
        // 合并完成 返回信息
        if ($done_all) {
            /******************  ffmpeg截图和获取时间  ******************/
            // 获取视频时间
            $vtime = $this->get_time($outpath);
            /******************  获取视频截图  ******************/
            // 生成缩略图
            $this->get_image($vtime , $fileHash , $outpath);
           
            
            $return_data['file_name'] = $fileHash . $exp;  // 视频名   
            $return_data['type'] = 2; //文件类型 1图片 2视频
            $return_data['status'] = 4; // 文件状态 1上传中，2待合并，3上传完成 ，4待转码
            $return_data['from_user'] = $this->fileData['username'];
            $return_data['from_ip'] = $this->request->ip();

            /******************  文件保存为json  ******************/
            // 重构视频时间
            $vtime = explode( ':' , $vtime );
            $vtime[0] = $vtime[0] == '00' ? '' : $vtime[0] . ':';
            $vtime[1] = $vtime[1] == '00' ? '' : $vtime[1] . ':';
            $vtime[2] = round($vtime[2]);
            $file_infos['video']['vtime'] = $vtime[0] . $vtime[1] . $vtime[2];

            $file_infos['video']['thumb'] = 'thumb/'.$fileHash .'/'.$this->thumbName; 
            $file_infos['video']['sprites'] = '';
            $file_infos['video']['preview'] = '';
            $file_infos['video']['fps'] = $this->get_fps($this->uploadPath . DS . $fileHash . DS . $return_data['file_name']);
            $file_infos['video']['fbl'] = $this->get_fbl($this->uploadPath . DS . $fileHash . DS . $return_data['file_name']);
            $file_infos['video']['size_original'] = $this->fileData['size'];
            $file_infos['path']['thumb_path'] = 'public' . DS .'thumb' . DS . $fileHash;  // 缩略图保存路径
            $file_infos['path']['video_path'] = 'uploads' . DS . $fileHash;  // 视频保存路径
            $return_data['file_infos'] = json_encode($file_infos);
             /******************  写入数据库  ******************/
            //$files = new Files;
            $res = Files::create($return_data);

            //$res = $files->addInfo($return_data);
            if (false !== $res) {
                /******************  添加Redis队列 让定时执行  ******************/
                $return_data['id'] = $res->id;
                $_redis = new Cron;
                $_redis->add($return_data);
                
                //dump($return_data);
                /******************  返回信息  ******************/
                $data['user'] = $this->fileData['username'];
                $data['name'] = $return_data['file_name'];
                $data['uptime'] = date("Y-m-d");
                $data['vtime'] = $file_infos['video']['vtime'];
                $data['thumb'] = $file_infos['video']['thumb'];
                $data['size'] = round($file_infos['video']['size_original'] /1024/1024);
                $this->return_msg(200,'上传成功',['datas'=>$data],false);
            }
        }
    }

    // 获取视频后缀
    public function get_exp($exp){
        switch ($exp) {
            case 'video/x-msvideo':
                return '.avi';
                break;
            case 'video/mp4':
                return '.mp4';
                break;
            case 'video/mpeg':
                return '.mpeg';
                break;
            case 'video/quicktime':
                return '.mov';
                break;
            case 'video/x-ms-wmv':
                return '.wmv';
                break;
            case 'video/x-flv':
                return '.flv';
                break;
            case 'video/x-matroska':
                return '.mkv';
                break;
            default:
                $this->return_msg( 0 , '上传错误');
                break;
        }
    }

    // 获取时间
    public function get_time($videoPath){
        $cmd = "ffmpeg -i ".$videoPath." 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
        exec($cmd, $res, $status);
        // 不是视频文件 直接干掉
        if ( !$res ) {
            $this->return_msg(0,'上传的不是视频文件,已删除');
        }
        return trim($res[0]);
    }
    // 获取fps
    public function get_fps($videoPath){
       $cmd = "ffmpeg -i ".$videoPath." 2>&1 | grep 'Stream #0:0' | cut -d ',' -f 5";
       exec($cmd, $res, $status);
       $fps = trim(str_replace("fps","",$res[0]));
       return $fps;
    }
    //获取分辨率
    public function get_fbl($videoPath){
        $cmd = "ffmpeg -i ".$videoPath." 2>&1 | grep 'Stream #0:0' | cut -d ',' -f 3";
        exec($cmd, $res, $status);
        $fbl = trim($res[0]);
        if ( strpos($fbl,'[') != false) {
            $fbl = explode(' ',$fbl);
            return $fbl[0];
        }else{
            return $fbl;
        }
    }

    // 获取图片 
    public function get_image($vtime,$fileHash,$outpath){
        //创建缩略图目录
        if (!file_exists($this->thumbPath . DS . $fileHash)) {
            mkdir($this->thumbPath . DS . $fileHash);
        }
        // 缩略图
        $thumb = $this->thumbPath . DS . $fileHash . DS . $this->thumbName;
        // 判断时长
        $vtime = explode( ':' , $vtime );
        $vtime = $vtime[0]*3600 + $vtime[1]*60 + $vtime[2];
        //每隔5秒截图
        $select =  0;
        // 分析颜色
        $imgcolor = true;
        
        do { 
            $select += 5; // 视频不合格每次步长+ 5;
            if ($select > $vtime) {
                break;
                $this->return_msg(1,'缩略图获取失败');
            }          
            //视频缩略图截图
            $cmd = "ffmpeg -ss ".$select." -i ".$outpath."  -r 1 -vframes 1 -an -vcodec mjpeg -y -s 256x145 " . $thumb;
            exec( $cmd, $res, $status );
            if ( $status != '0' ) {
                break;
                $this->return_msg(1,'缩略图获取失败');
            }
            $imgcolor = $this->img_color($thumb);
            
        } while ( $imgcolor ); // 小于这个值的颜色图片属于暗色 需要重新生成
        return $thumb;
    }

    // 分析颜色
    public function img_color($imgUrl){
        $imageInfo = getimagesize($imgUrl);
        //图片类型
        $imgType = strtolower(substr(image_type_to_extension($imageInfo[2]), 1));
        //对应函数
        $imageFun = 'imagecreatefrom' . ($imgType == 'jpg' ? 'jpeg' : $imgType);
        $i = $imageFun($imgUrl);
        //循环色值
        $rColorNum=$gColorNum=$bColorNum=$total=0;
        for ($x=0;$x<imagesx($i);$x++) {
            for ($y=0;$y<imagesy($i);$y++) {
                $rgb = imagecolorat($i,$x,$y);
                //三通道
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rColorNum += $r;
                $gColorNum += $g;
                $bColorNum += $b;
                $total++;
            }
        }
        $rgb = array();
        $rgb['r'] = round($rColorNum/$total);
        $rgb['g'] = round($gColorNum/$total);
        $rgb['b'] = round($bColorNum/$total);

        //色差计算
        $bcolor = 0.299*$rgb['r'] + 0.587*$rgb['g'] + 0.114*$rgb['b'];
        if ($bcolor >= 20) {
            return false; //图像合格,停止循环
        }else{
            return true; // 继续截图
        }
    }

}
