<?php
    $head = '<style>';
    $head .= '    table {margin-top:20px;}';
    $head .= '    td {border:1px solid #cdcdcd; padding:2px 20px;text-align:center}';
    $head .= '</style>';

    $head .= '<html>';
    $head .= '<body>';
    $head .= '<center>';
    $head .= '<p>&nbsp;</p>';

    $td='<td style="border:1px solid #cdcdcd; padding:2px 20px;text-align:center">';


    $head .= '<p><b>Check event stat in MySQL</b></p>';
    $head .= '<p>Check time: '.date("Y-m-d H:i:s").PHP_EOL.'</p>';

    $legend .='<div style="font-size:11px; color:#555;"><p><b>t.sh.c</b> = sum(today_show_count) from ssp_banner_site_stat</p>
             <p><b>b.e</b> = sum(start) from banner_event_stat_day (today)</p>
             <p><b>s.b.s.e</b> = sum(start) from ssp_banner_site_event_stat_day (today)</p>
             </div>
    
    ';

    $head .= '<table><th>banner_id</th><th>t.sh.c</th><th>b.e.</th><th>s.b.s.e</th>';


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


//    $query ="
//        SELECT sbss.banner_id,
//               (sbss.today_show_count) as sm1,
//               (bsesd.start) as sm3,
//               (besd.start) as sm2
//
//        FROM banner.ssp_banner_site_stat as sbss
//
//        LEFT JOIN banner.ssp_banner_site_event_stat_day as bsesd USING (banner_id)
//        LEFT JOIN banner.banner_event_stat_day as besd USING (banner_id)
//
//        where from_days(bsesd.day)=DATE(NOW()) AND from_days(besd.day)=DATE(NOW())
//
//        group by banner_id;
//    ";

    $sql = $conn->prepare("SELECT banner_id, sum(today_show_count) as sm1 FROM banner.ssp_banner_site_stat group by banner_id;");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $ssp_banner_site_stat = $sql->fetchAll();


    foreach ($ssp_banner_site_stat as $k=>$v) {
        $data[$v['banner_id']]['sm1']=$v['sm1'];
    }


    $sql = $conn->prepare("SELECT banner_id, sum(start) as sm2 FROM banner.banner_event_stat_day where day=to_days(NOW()) group by banner_id;");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $banner_event_stat_day = $sql->fetchAll();

    foreach ($banner_event_stat_day as $k=>$v) {
        $data[$v['banner_id']]['sm2'] = $v['sm2'];
    }


    $sql = $conn->prepare("SELECT banner_id, sum(start) as sm3 FROM banner.ssp_banner_site_event_stat_day where day=to_days(NOW()) group by banner_id;");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $ssp_banner_site_event_stat_day = $sql->fetchAll();

    foreach ($ssp_banner_site_event_stat_day as $k=>$v) {
        $data[$v['banner_id']]['sm3'] = $v['sm3'];
    }


    //print_r($data);
$i=0;
    foreach ($data as $k=>$v) {

        if (!empty($v['sm1']) && !empty($v['sm2']) && !empty($v['sm3']) ){

            $sm1+=$v['sm1'];
            $sm2+=$v['sm2'];
            $sm3+=$v['sm3'];

            if ($v['sm1']<$v['sm2'] || $v['sm1']<$v['sm3']){
                $i++;
                $msg .= '<tr>'.$td . $k . '</td>'.$td . $v['sm1'] . '</td>'.$td . $v['sm2'] . '</td>'.$td . $v['sm3'] . '</td></tr>';
            }

        }

    }
    $msg.='</table>';

    echo $head.'<p>'.$i.'</p>';

    $msg = '<tr>'.$td . '<b>SUM</b>' . '</td>'.$td . $sm1 . '</td>'.$td . $sm2 . '</td>'.$td . $sm3 . '</td></tr>' . $msg;

    echo $msg;
    echo $legend;

    // с 00:00 до 02:00 почту не шлем, там статистика обычно еще кривая
    if ($sm1<$sm2 || $sm1<$sm3 && date("H")>1){


        //echo $msg;
//,c8td@z1.kiev.ua
        $to      = 'spm_x@z1.kiev.ua';
        $subject = 'RTB watch: Mysql data check. Event stat';
        $headers = 'From: spm_x@z1.kiev.ua' . "\r\n" .
            'Reply-To: spm_x@z1.kiev.ua' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

        mail($to, $subject, $head.$msg.$legend, $headers);

    }




?>