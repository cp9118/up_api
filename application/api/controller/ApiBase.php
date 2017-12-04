<?php
namespace app\api\controller;
use think\Controller;
use think\Request;
use think\Config;

class ApiBase extends Controller
{
    protected $request; //请求
    protected $params; //参数
    protected $token;
    protected $time;

    protected function _initialize()
    { 
        parent::_initialize();
        header('Access-Control-Allow-Origin:*');   // 指定允许其他域名访问 
        $this->request = Request::instance();
        // 不允许get访问
        if (!$this->request->isPost() ) {
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                exit;
            }else{
                //exit('nono');
                //header("HTTP/1.0 Internal Server Error");
                //exit;
            }
        }
        $this->time = $this->request->param('time');
        $this->check_time($this->time);
        $this->token = $this->request->param('token');
        $this->check_token($this->token , $this->time);         
    }

    public function check_time($time){
        /******************  验证时间戳  ******************/
        if (!isset($time) || intval($time) <= 1) {
            $this->return_msg( 0 , '时间戳不能为空');
        }
        // 设置每个token的过期时间为60秒
        if (time() - intval($time) > 72400) {
            $this->return_msg(0,'请求超时');
        }
    }

    /**
     * 验证token
     * @param  [array] $arr [请求的参数]
     * @return [json]      [错误 返回0]
     */
    public function check_token($token,$time){

        /*********** 验证token  ***********/
        if (!isset($token) || empty($token) ) {
            $this->return_msg(0,'token不能为空');
        }
        /******************  服务器token  ******************/
        $server_token = $this->buildToken($time);

        if ($server_token['token'] !== $token) {
            $this->return_msg(0,'token不正确');
        }
    }

    // 验证其他参数
    public function check_data($data=[]){
        foreach ($data as $key => $value) {
            if (empty($value)) {
               break;
               $this->return_msg(0, $key .'不能为空'); 
            }
        }
    }


    // 返回信息提示
    public function return_msg($status,$message='',$data=[],$show_header = true){
        if ($show_header) {
            header("HTTP/1.0 500 Internal Server Error");
        }
        $return_data['status'] = $status;
        $return_data['message'] = $message;
        $return_data['data'] = $data;
        echo json_encode($return_data);
        exit();
    }

    //生成token
    public function buildToken($time=''){
        $key = Config::get('globalsalt'); // 参与加密的盐
        $time = empty($time) ? time() : $time; //时间戳

        /******************  组合参数  ******************/
        $token = md5( 'token__' . md5($key) . md5($time) . '__token');
        return ['token'=>$token ,'time'=> $time];
    }
}
