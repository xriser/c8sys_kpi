<?php
/*
 * This is RTB watch
 * No clicks in 10k hits by bannergroup
 *
 */

//$url1=$_SERVER['REQUEST_URI'];
//header("Refresh: 10; URL=$url1");
//error_reporting('E_ALL');

// 7	РК має 10 000 показів і 0 кліків.	Кожні 10 000 показів

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


    $head = '<style>';
    $head .= '    table {margin-top:20px;}';
    $head .= '    td {border:1px solid #cdcdcd; padding:2px 20px;text-align:center}';
    $head .= '</style>';

    $head .= '<html>';
    $head .= '<body>';
    $head .= '<center>';
    $head .= '<p>&nbsp;</p>';

    $i=1;
    $m = new MongoClient();
    $collection = $m->rtb->data;
    $send_mail = 0;

//  Кастомные РК, исключить

    $bg_custom=array(31820, 32114, 32003, 32392, 32277);

    $banner_array = $m->rtb->data->find();
    // iterator_to_array жрет много памяти и времени. Убрать если что и переделать исключение $bg_custom. :)
   // $banner_array = iterator_to_array($banner_array);

   foreach ($banner_array as $k=>$v){
	$keys .= $k .',';
	}
    $keys = rtrim($keys,',');
    //echo $keys; die();


$sql = $conn->prepare("
		SELECT bannergroup_id, name FROM banner.bangroups where bannergroup_id IN ($keys);
        ");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $mysql_bangroups = $sql->fetchAll();

	//print_r ($mysql_bangroups);

	foreach($mysql_bangroups as $k=>$v){

        if(preg_match('/censor|test/si',$v['name'])) {
    //	 echo $v['bannergroup_id'].',';
               $bg_custom[]=$v['bannergroup_id'];
        }
	}

    $bg_custom = array_flip($bg_custom);

    // print_r ($bg_custom);

//    foreach ($bg_custom as $k=>$v) {
//       unset($banner_array[$v]);
//    }

//$result = $m->rtb->data->update(
//    array('_id' => 31949),
//    array('$pull' =>
//        array('data'=>
//            array(
//            'ts'=>1474462802,
//            'h'=>5812,
//            'c'=>70
//            )
//        )
//
//    ));
//
//    print_r($result);
//
//die();

// $b = $m->rtb->data->find(array('_id'=>31305));

//
// $b1 = $collection->remove(array('_id' => 31305 ), array("justOne" => true));
//              //remove(array('type' => 94), array("justOne" => true));
// echo $b1;
//
//foreach ($b as $k) {
//
//    print_r($k);
//    }
//
//
//die();


    //$data=array('data'=>'');

    $curdate=date("Y-m-d H:i:s");

    $head.= '<p><b>No clicks in 10k hits by bannergroup </b></p>';
    $head.= '<p>Check time: '.date("Y-m-d H:i:s").PHP_EOL.'</p>';

    echo $head;

foreach ($banner_array as $id => $value) {

    if (array_key_exists($id, $bg_custom)) {
        //echo $id.", ";
        continue;
    }


    krsort($value['data']);
    //print_r($value['data']);
    $ts_check = array_values($value['data'])[0]['ts'];
    $curhit = array_values($value['data'])[0]['h'];
    $curclick = array_values($value['data'])[0]['c'];

    $data = array('ts'=>$ts_check, 'h'=>$curhit, 'c'=>$curclick);


//                echo '<td></td>';
//                echo '<td>' . date("Y-m-d H:i:s", array_values($value['data'])[0]['ts']) . '</td>';
//                echo '<td>' . $curhit . '</td>';
//                echo '<td>' . $curclick . '</td></tr>';

// Если хиты растут(текущее больше предыдущего), если РК при этом набирает 10к хитов, и клики не растут (текущее не больше предыдущего)

    //print_r($value['data']);

    foreach ($value['data'] as $k => $v) {

        // ecть 10к показов
        if (($curhit - $v['h']) > 10000) {

            if (($curclick - $v['c']) < 2) {

               $send_mail = 1;

               $msg .=  '<tr><td>';
               $msg .=  '<a href="http://c8.net.ua/banners/index/cid/'.$id.'">'.$id.'</a>';
               $msg .=  '</td></tr>';

               $msg .=  '<tr>';

               $msg .=  '<td></td>';
               $msg .=  '<td>' . date("Y-m-d H:i:s", $ts_check) . '</td>';
               $msg .=  '<td>' . $curhit . '</td>';
               $msg .=  '<td>' . $curclick . '</td></tr>';

               $msg .=  '<tr>';

               $msg .=  '<td></td>';
               $msg .=  '<td>' . date("Y-m-d H:i:s", $v['ts']) . '</td>';
               $msg .=  '<td>' . $v['h'] . '</td>';
               $msg .=  '<td>' . $v['c'] . '</td></tr>';
               //$msg .=  '<hr>';

                //если нашли 10к показов убрать из монги все значения data[ts, h, c] кроме последнего
                //
                //break;
            }

           //print_r($data);

            $id = intval($id);
            $ts = intval($v['ts']);
            $h = intval($v['h']);
            $c = intval($v['c']);

            if ($c == 0) $c = null;

            //echo $id.' - '.$ts.' - '.$h.' - '.$c.'<br>';


//                            $result = $m->rtb->data->update(
//                                array('_id' => $id),
//                                array('$pull' =>
//                                    array('data'=>
//                                        array(
//                                            'ts' => $ts,
//                                            'h' => $h,
//                                            'c' => $c
//                                        )
//                                    )
//                                ));


            $collection->remove(array('_id' => $id), array("justOne" => true));
            $m->rtb->data->update(['_id' => $id],
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


    if ($send_mail==1){

        $msg = '<table><th>bg_id</th><th>time checks</th><th>hits</th><th>clicks</th>'.$msg;
        $msg .= '</table>';

        echo $msg;

        $to      = 'spm_x@z1.kiev.ua';
        $subject = 'RTB watch: No clicks';
        $headers = 'From: spm_x@z1.kiev.ua' . "\r\n" .
            'Reply-To: spm_x@z1.kiev.ua' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

        //mail($to, $subject, $head.$msg, $headers);

    }

?>

