<?php 
// $redis = new Redis();
// $redis->connect('127.0.0.1', 9009);
// $redis->auth('545408');

// for ($i=0; $i < 10; $i++) { 
// 	$redis->Lpush('test' , $i);
// }
// echo "ok\r\n";
// $i = true;
// do {
// 	$r = $redis->rpop('test');	
// 	if ( $redis->llen('test') == 0) {
// 		$i = false;
// 	}else{
// 		echo "{$r}\r\n";
// 	}
// }while ($i);


/************************************************************/
// ffmpeg


同时加水印和文字
ffmpeg -i /home/wwwroot/api.com/uploads/b65088eb3a746691/b65088eb3a746691.mp4 -vf "drawtext='fontsize=14:fontfile=heiti.TTF:fontcolor=gray:text='ARAV8免费在线影视提醒您：本视频由fuckyou于2017-11-11上传至 ARAV8视频网 WWW.ARAV8.COM':x=w-t*20+text_w*1:y=(h-text_h)/1.5:enable=if(gt(t,10),30)'[text];movie=logo.png[wm];[text][wm]overlay=0:0[out]" -acodec copy output.mp4

转码、加水印、加文字、切片
ffmpeg -i /home/wwwroot/api.com/uploads/b65088eb3a746691/b65088eb3a746691.mp4 -b 300k -b:v 300k -r 15.17 -s 640x360 -vf "drawtext='fontsize=14:fontfile=heiti.TTF:fontcolor=gray:textfile=textlog:x=w-t*20+text_w*1:y=(h-text_h)/1.5:enable=if(gt(t,10),30)'[text];movie=logo.png[wm];[text][wm]overlay=0:0[out]" -map 0 -f segment -segment_list m3u8/playlist.m3u8 -segment_time 100 m3u8/output%03d.ts

转码、加水印、加文字
ffmpeg -i /home/wwwroot/api.com/uploads/b65088eb3a746691/b65088eb3a746691.mp4 -b 300k -b:v 300k -r 15.17 -s 640x360 -vf "drawtext='fontsize=14:fontfile=heiti.TTF:fontcolor=gray:textfile=textlog:x=w-t*20+text_w*1:y=(h-text_h)/1.5:enable=if(gt(t,10),30)'[text];movie=logo.png[wm];[text][wm]overlay=0:0[out]" outpu6.mp4

转码参数
ffmpeg -i /home/wwwroot/api.com/uploads/b65088eb3a746691/b65088eb3a746691.mp4 -b 300k -b:v 300k -r 15.17 -s 640x360  out6.mp4
