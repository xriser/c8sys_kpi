<?php

//$url1=$_SERVER['REQUEST_URI'];
//header("Refresh: 10; URL=$url1");
//error_reporting('E_ALL');


//Відсутність аукціонів у банері більше 5 хв. (при тому, що бюджет, покази, кліки та період не досягнуті)

    $head = '<style>';
    $head .= '    table {margin-top:20px;}';
    $head .= '    td {border:1px solid #cdcdcd; padding:2px 20px;text-align:center}
                  p {margin: 2px};
    ';
    $head .= '</style>';

    $head .= '<html>';
    $head .= '<body>';
    $head .= '<center>';
    $head .= '<p>&nbsp;</p>';

    $i=1;

    //Get array data from mongo
    $m = new MongoClient();
    $collection = $m->rtb->banner_id;
    $send_mail = 0;
    $curtime = time();

    $servername = "host";
    $port = "port";
    $username = "user";
    $password = "pass";
    $dbname = "banner";

    try {
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "Connected successfully";
    }
    catch(PDOException $e)
    {
        echo "Connection failed: " . $e->getMessage();
    }


    try {
        $conn1 = new PDO("mysql:host=localhost;port=3306;dbname=rtbwatch", $username, $password);
        // set the PDO error mode to exception
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "Connected successfully";
    }
    catch(PDOException $e)
    {
        echo "Connection failed: " . $e->getMessage();
    }


    //  Кастомные баннеры, исключить, сделать в основном цикле
    $banner_custom=array(32114);

    $start_time = microtime(true);
    $banner_array = $m->rtb->banner_id->find();
    $banner_array->sort(array("_id" => 1));

    $get_from_mongo_t = microtime(true) - $start_time;
    //echo '<br>get from mongo time: '.$get_from_mongo_t.'<br>';

    // too much time & memory (12s & 2048Mb)
    //$banner_array = iterator_to_array($banner_array);

    //echo '<br>2 array: '; echo microtime(true) - $start_time;
    //ksort($banner_array);


    $sql = $conn->prepare("
              
        SELECT b.banner_id FROM banner.adgroup as ag
    
        LEFT JOIN adgroup_banner as ab using (adgroup_id) 
        LEFT JOIN banner.bangroups as bg using (bannergroup_id)
        LEFT JOIN banner.banner as b using (banner_id)
            
        where (status!=0 or del!=1) and (ag.time_end > unix_timestamp() and bg.time_end > unix_timestamp()) and (b.flags2!=0)
        
            group by banner_id
            order by banner_id DESC
                
        ");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $mysql_active_banners = $sql->fetchAll(PDO::FETCH_COLUMN, 0);
    $mysql_active_banners = array_flip($mysql_active_banners);
//    print_r($mysql_active_banners);
//    die();

    //echo '<br>2 sort array:'; echo microtime(true) - $start_time;

//    foreach ($banner_custom as $k=>$v) {
//        unset($banner_array[$v]);
//    }

    // Get active banners array
    try {
        $redis = new Redis();
        $a = $redis->pconnect('89.184.66.57', 7000, 10);
        $redis->ping();
    } catch(RedisException $e) {
        echo( "$host: Cannot connect to redis server: ".$e->getMessage()."\n" );
        //exit('Connect error to $matches[1] $matches[3]');
    }

    $key = 'allow:router';
    $active_banners=$redis->sMembers('allow:router');
    $active_banners = array_flip($active_banners);

// echo '<br> get from redis:'; echo microtime(true) - $start_time;

// deny:ag_day_hits_clicks
// deny:day_hits_clicks
// deny:ag_money
// deny:bg_money

    $deny_ag_day_hits_clicks = $redis->sMembers('deny:ag_day_hits_clicks');
    $deny_ag_day_hits_clicks = array_flip($deny_ag_day_hits_clicks);

    $deny_day_hits_clicks = $redis->sMembers('deny:day_hits_clicks');
    $deny_day_hits_clicks = array_flip($deny_day_hits_clicks);

    $deny_ag_money = $redis->sMembers('deny:ag_money');
    $deny_ag_money = array_flip($deny_ag_money);

    $deny_bg_money = $redis->sMembers('deny:bg_money');
    $deny_bg_money = array_flip($deny_bg_money);


    $current_hour=date("H");

    $b2d_current_hour =  $redis->sUnion('b2dhour:'.(int)$current_hour, 'b2dhour:all');
    $b2d_current_hour  = array_flip($b2d_current_hour);

    //print_r($active_banners);

    $curdate=date("Y-m-d H:i:s");
    $number_of_week=date("N");

    $b2wday_current =  $redis->sUnion('b2wday:'.(int)$number_of_week, 'b2wday:all');
    $b2wday_current = array_flip($b2wday_current);

    //echo $number_of_week;

    //ksort($banner_array);
    //print_r($banner_array);
    //die();
    $count=0;

    //echo '<br> before foreach:'; echo microtime(true) - $start_time;

foreach ($banner_array as $id => $value) {

        if (!array_key_exists($id, $mysql_active_banners)) {
            continue;
        }

    // если баннер не активный или в множествах deny:ag_day_hits_clicks, $deny_day_hits_clicks   - пропустить
        if (!array_key_exists($id, $active_banners) || array_key_exists($id, $deny_ag_day_hits_clicks)
            || array_key_exists($id,$deny_day_hits_clicks) || array_key_exists($id,$deny_ag_money)  || array_key_exists($id,$deny_bg_money) ) {
            continue;
        }

    // если у баннера есть расписание показов по дням недели.
    //если баннер есть в множестве $b2wday_all или в множестве $b2wday_$number_of_week - учитываем его
        if (!array_key_exists($id, $b2wday_current)) {
            continue;
         }

    // если у баннера есть расписание показов по часам.
    //если баннер есть в множестве $b2dhour_all или в множестве ${'b2dhour_' . (int)$current_hour} - учитываем его
        if (!array_key_exists($id, $b2d_current_hour)) {
            continue;
        }



    // Get banners info
    try {
        $redis_info = new Redis();
        $a = $redis_info->pconnect('89.184.66.57', 9001, 10);
        $redis_info->ping();
    } catch(RedisException $e) {
        echo( "$host: Cannot connect to redis server: ".$e->getMessage()."\n" );

    }

    $key = 'banners:data:'.$id;
    $stop_show_count = $redis_info->hget($key, 'stop_show_count');
    $name = $redis_info->hget($key, 'name');
    $bg_id = $redis_info->hget($key, 'bg_id');
    $ag_id = $redis_info->hget($key, 'ag_id');

    $agency_id = $redis_info->hget($key, 'agency_id'); // 5302

    $bg_name = $redis_info->hget('bg:data:'.$bg_id, 'bg_name');



    if(preg_match('/test|тест/siu', $bg_name) || preg_match('/test|тест/siu', $name) ) {
        continue;

    }


    krsort($value['data']);
    //print_r($value['data']);
    $ts_check = array_values($value['data'])[0]['ts'];
    $curhit = array_values($value['data'])[0]['h'];
    $curclick = array_values($value['data'])[0]['c'];
    $curtotal = array_values($value['data'])[0]['total'];

    $data = array('ts'=>$ts_check, 'h'=>$curhit, 'c'=>$curclick, 'total'=>$curtotal);


//                echo '<td></td>';
//                echo '<td>' . date("Y-m-d H:i:s", array_values($value['data'])[0]['ts']) . '</td>';
//                echo '<td>' . $curhit . '</td>';
//                echo '<td>' . $curclick . '</td></tr>';


    foreach ($value['data'] as $k => $v) {

        // ecть N сек
        if (($ts_check - $v['ts']) > 1200) {
        // если количество аукционов не изменилось
            if (($curtotal - $v['total']) <=0) {

                //берем из мускула время последней отправки
                $sql = $conn1->prepare("
                SELECT last_send from active_banners_auctions where banner_id = $id
                ");
                $sql->execute();
                //$result = $sql->setFetchMode(PDO::FETCH_ASSOC);
                $last_send = $sql->fetchColumn();

                // если последняя отправка была более 60 минут назад то будем отправлять сообщение
                // если это новый баннер, которого не было в мускуле
                if (($curtime - $last_send > 30*60) || (empty($last_send))) {

                    $count++;
                    // флаг отправки почты
                    if (date("H")>9) {
                        $send_mail = 1;
                    }

                    $msg .= '<tr><td colspan="3" style="text-align:left; float:left;">';
                    $msg .= '
                    <p>cid:&nbsp;<a href="http://c8.net.ua/adgroups/banners/sid/' . $agency_id . '/cid/' . $bg_id . '/id/' . $ag_id . '">' . $bg_id . '</a></p>
                    <p>bid:&nbsp;<a href="http://c8.net.ua/banners/edit/bid/' . $id . '">' . $id . '</a></p>
                
                ';
                    $msg .= '</td></tr>';

                    $msg .= '<tr>';

                    $msg .= '<td></td>';
                    $msg .= '<td>' . date("Y-m-d H:i:s", $ts_check) . '</td>';
                    $msg .= '<td>' . $curhit . '</td>';
                    $msg .= '<td>' . $curclick . '</td>';
                    $msg .= '<td>' . $curtotal . '</td></tr>';

                    $msg .= '<tr>';

                    $msg .= '<td></td>';
                    $msg .= '<td>' . date("Y-m-d H:i:s", $v['ts']) . '</td>';
                    $msg .= '<td>' . $v['h'] . '</td>';
                    $msg .= '<td>' . $v['c'] . '</td>';
                    $msg .= '<td>' . $v['total'] . '</td>';
                    $msg .= '<td>' . $bg_name . '</td>';
                    $msg .= '<td>' . $name . '</td>';
                    // $msg .=  '<td>' . $stop_show_count. '</td>';
                    $msg .= '</tr>';


                    if (empty($curhit)) $curhit = 0;
                    if (empty($curclick)) $curclick = 0;
                    if (empty($curtotal)) $curtotal = 0;

                    //echo ' -- ' . $id . ' -- ' . $ts_check . ' -- ' . $ts_check . ' -- ' . $curhit . ' -- ' . $curclick . ' -- ' . $curtotal . ' -- ' . $ts_check . '<br>';

                    //echo $date_ts;
                    //Запишем в базу найденные значения
                    $sql = $conn1->prepare("
                INSERT IGNORE INTO active_banners_auctions (banner_id, time1, time2, hits, clicks, auctions, last_send) VALUES
                ( $id, $ts_check, $ts_check, $curhit, $curclick, $curtotal, $curtime )
                ON DUPLICATE KEY UPDATE banner_id=$id, time1=$ts_check, time2=$ts_check, hits=$curhit, clicks=$curclick, auctions=$curtotal, last_send=$curtime;
                ");
                    $sql->execute();
                }

                //break;
            }

            //print_r($data);

            $id = intval($id);
            $ts = intval($v['ts']);
            $h = intval($v['h']);
            $c = intval($v['c']);
            $total = intval($v['total']);



            if ($c == 0) $c = null;


           // $collection->remove(array('_id' => $id), array("justOne" => true));
            $m->rtb->banner_id->update(['_id' => $id],
                ['$set' => [
                    'expireAt' => new MongoDate(time() + 3600 * 24 * 31)
                ],
                    '$addToSet' => [
                        'data' => $data
                    ],
                ],
                ['upsert' => true]);
            break;
        }

    }

}

    $head.= '<p><b>No auctions in active banners ('.$count.')</b></p>';
    $head.= '<p>Check time: '.date("Y-m-d H:i:s").PHP_EOL.'</p>';

    echo $head;


if ($send_mail==1){

    $msg = '<table><th>banner_id</th><th>time checks</th><th>hits</th><th>clicks</th><th>auctions</th>'.$msg;
    $msg .= '</table>';

    echo $msg;

    $to      = 'spm_x@z1.kiev.ua';
    $subject = 'RTB watch: No auctions ('.$count.')';
    $headers = 'From: spm_x@z1.kiev.ua' . "\r\n" .
        'Reply-To: spm_x@z1.kiev.ua' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

    mail($to, $subject, $head.$msg, $headers);

}




?>

