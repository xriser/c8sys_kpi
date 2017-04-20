<?php
    $head = '<style>';
    $head .= '    table {margin-top:20px;}';
    $head .= '    td {border:1px solid #cdcdcd; padding:2px 20px;text-align:center}';
    $head .= '</style>';

    $head .= '<html>';
    $head .= '<body>';
    $head .= '<center>';
    $head .= '<p>&nbsp;</p>';

    $head .= '<p><b>Check event stat in MySQL</b></p>';
    $head .= '<p>Check time: '.date("Y-m-d H:i:s").PHP_EOL.'</p>';

    $head .='
        
        ';

    $head .= '<table><th>table</th><th>start</th><th>firstQuartile</th><th>midpoint</th><th>thirdQuartile</th><th>complete</th>'.$msg;

    $send_mail=0;
    $servername = "host";
    $port = "port";
    $username = "user";
    $password = "pass";
    $dbname = "banner";
    $h = date("H");

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

    $sql = $conn->prepare("SELECT sum(start) as st, sum(firstQuartile) as fq, sum(midpoint) as mp, sum(thirdQuartile) as tq, sum(complete) as ct FROM banner.banner_event_stat_day where day=to_days(NOW())");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $banner_event_stat_day = $sql->fetchAll();



    $sql = $conn->prepare("SELECT sum(start) as st, sum(firstQuartile) as fq, sum(midpoint) as mp, sum(thirdQuartile) as tq, sum(complete) as ct FROM banner.ssp_banner_site_event_stat_day where day=to_days(NOW())");
    $sql->execute();
    $result = $sql->setFetchMode(PDO::FETCH_ASSOC);
    $ssp_banner_site_event_stat_day = $sql->fetchAll();


    $msg .= '<tr><td>banner_event_stat_day</td><td>' . $banner_event_stat_day[0]['st'] . '</td><td>' . $banner_event_stat_day[0]['fq'] . '</td><td>' . $banner_event_stat_day[0]['mp'] . '</td><td>' . $banner_event_stat_day[0]['tq'] . '</td><td>' . $banner_event_stat_day[0]['ct'] .'</td></tr>';
    $msg .= '<tr><td>ssp_banner_site_event_stat_day</td><td>' . $ssp_banner_site_event_stat_day[0]['st'] . '</td><td>' . $ssp_banner_site_event_stat_day[0]['fq'] . '</td><td>' . $ssp_banner_site_event_stat_day[0]['mp'] . '</td><td>' . $ssp_banner_site_event_stat_day[0]['tq'] . '</td><td>' . $ssp_banner_site_event_stat_day[0]['ct'] .'</td></tr>';

    $diff_st = round(((1 - $banner_event_stat_day[0]['st'] / $ssp_banner_site_event_stat_day[0]['st']) * 100),3);
    $diff_fq = round(((1 - $banner_event_stat_day[0]['fq'] / $ssp_banner_site_event_stat_day[0]['fq']) * 100),3);
    $diff_mp = round(((1 - $banner_event_stat_day[0]['mp'] / $ssp_banner_site_event_stat_day[0]['mp']) * 100),3);
    $diff_tq = round(((1 - $banner_event_stat_day[0]['tq'] / $ssp_banner_site_event_stat_day[0]['tq']) * 100),3);
    $diff_ct = round(((1 - $banner_event_stat_day[0]['ct'] / $ssp_banner_site_event_stat_day[0]['ct']) * 100),3);

    $msg .= '<tr><td>diff, %%</td><td>' . $diff_st . '</td><td>' . $diff_fq . '</td><td>' . $diff_mp . '</td><td>' . $diff_tq . '</td><td>' . $diff_ct .'</td></tr>';




    if ($diff_st > 5 || $diff_fq > 5 || $diff_mp > 5 || $diff_tq > 5 || $diff_ct > 5){
        $send_mail=1;
    }

    if ($banner_event_stat_day[0]['st'] < $banner_event_stat_day[0]['fq'] || $banner_event_stat_day[0]['fq'] < $banner_event_stat_day[0]['mp'] || $banner_event_stat_day[0]['mp'] < $banner_event_stat_day[0]['tq'] || $banner_event_stat_day[0]['tq'] < $banner_event_stat_day[0]['ct']) {
        $send_mail=1;

    }


    if ($ssp_banner_site_event_stat_day[0]['st'] < $ssp_banner_site_event_stat_day[0]['fq'] || $ssp_banner_site_event_stat_day[0]['fq'] < $ssp_banner_site_event_stat_day[0]['mp'] || $ssp_banner_site_event_stat_day[0]['mp'] < $ssp_banner_site_event_stat_day[0]['tq'] || $ssp_banner_site_event_stat_day[0]['tq'] < $ssp_banner_site_event_stat_day[0]['ct']) {
        $send_mail=1;

    }

    echo $head;
    echo $msg;

    // с 00:00 до 01:00 почту не шлем, там статистика обычно еще кривая
    if ($send_mail==1 && date("H")>0 ){

        echo $msg;

        $to      = 'spm_x@z1.kiev.ua';
        $subject = 'RTB watch: Mysql data check. Event stat';
        $headers = 'From: spm_x@z1.kiev.ua' . "\r\n" .
            'Reply-To: spm_x@z1.kiev.ua' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

	if ($h>7) {
        mail($to, $subject, $head.$msg, $headers);
	}

    }




?>
