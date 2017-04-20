<?php
/* RTB data collector by riser
 * Сollect hits, click, by bg_id to mongodb
 * з
 *
 * last update 20.09.2016
 */

    //Берем данные из сканнера, только по своей сети ssp_id=3634
    $scanner_url = 'http://scanner.c8.net.ua/c8scanner.php?method=getBannerLogs&viacustom=bg_id,type&type=*&bg_id=*&period=m&timeEnd=0&sort=asc&sortkey=c';
    $bg_array = json_decode(file_get_contents($scanner_url), true);

    unset($bg_array['status']);
    ksort($bg_array['via']);

    //$m = new \MongoDB\Driver\Manager();
    //echo extension_loaded("mongodb") ? "loaded\n" : "not loaded\n";
    $m = new MongoClient();

    $ctime=time();
    //  echo '<table>';
    //  echo '<th>bg_id</th><th>clicks</th><th>hits old</th><th>hits now</th>';


    //$data=[];

    foreach ($bg_array['via'] as $key=>$val) {

        $hits_m = $val['hit']['c'];
        $clicks_m = $val['click']['c'];
        $total_m = $val['total']['c'];

        if ($hits_m >= 0 ) {
        //    echo '<tr><td>' . $key . '</td><td>' . $clicks_m . '</td><td>' . $hits_d . '</td><td>' . $hits_m . '</td></tr>';

            $data = array(
                "ts" => $ctime,
                "h" => $hits_m,
                "c" => $clicks_m,
                "total"=>$total_m,
               );

          //$m->rtb->data->update(['_id' => $key], $data, ["upsert" => true]);
          //$m->rtb->data->update(['_id' => $key], array('$addToSet' => $data), ["upsert" => true]);

            $m->rtb->data->update(['_id' => $key],
                ['$set' => [
                    'expireAt' => new MongoDate(time() + 3600*24*31)
                ],
                    '$addToSet' => [
                        'data' => $data
                    ],
                ],
                ['upsert' => true]);


        }

}


?>