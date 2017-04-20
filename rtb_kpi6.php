<?php

//$url1=$_SERVER['REQUEST_URI'];
//header("Refresh: 10; URL=$url1");
error_reporting('E_ALL');


//По РК не зупинились аукціони після досягнення зазначеної кількості показів, кліків, бюджету чи періоду.

// Будем брать данные со сканера?редиса по профилю: показы\клики

//В настройках профиля ограничения по показам\кликам:
// В день
// Показов
// 1000
// Кликов
// 0
// Остановить при кол-тве:

// Основная ошибка перекрутов была некоректные данные в редисе. Поэтому данные стопов лучше брать из мускула banner.adgroup
//
function from_days($daystamp)
{
    return gmdate('Y-m-d', ($daystamp - 719528) * 86400);
}

function to_days($date)
{
    if (is_numeric($date)) {
        $res = 719528 + (int)($date / 86400);
    } else {
        $TZ = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $res = 719528 + (int)(strtotime($date) / 86400);
        date_default_timezone_set($TZ);
    }
    return $res;
}


$head = '<style>';
$head .= '    table {margin-top:20px;}';
$head .= '    td {border:1px solid #cdcdcd; padding:2px 20px;text-align:center}
                  th {background:#eee; padding: 2px 5px;}   
        ';
$head .= '</style>';

$head .= '<html>';
$head .= '<body>';
$head .= '<center>';
$head .= '<p>&nbsp;</p>';
$head .= '<p><b>Check show\click count stat in MySQL</b></p>';
$head .= '<p>Check time: ' . date("Y-m-d H:i:s") . PHP_EOL . '</p>';


$i = 1;
$m = new MongoClient();
$collection = $m->rtb->data;
$send_mail = 0;

//  Кастомные РК, исключить

//  $bg_custom=array(31820, 32114);

$start_time = microtime(true);

$send_mail = 0;
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
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

//SELECT site_id, banner_id, adgroup_id, bannergroup_id, name, time_start, time_end, budget, budget_day, show_max_per_day, click_max_per_day,
//                  stop_show_count, stop_click_count, sum(today_show_count) as stshc, sum(today_click_count) as stcc, all_show_count, all_click_count,  status
//           FROM banner.adgroup
//
//	       LEFT JOIN adgroup_banner using (adgroup_id)
//           LEFT JOIN banner_stat using (banner_id)
//
//           where status=1  and (
//    (today_show_count > show_max_per_day) or (today_click_count > click_max_per_day and click_max_per_day>0)
//    or (all_show_count > stop_show_count and today_show_count >0)
//    or (UNIX_TIMESTAMP(NOW())>time_end and today_show_count >0)
//)
//           group by adgroup_id;


$sql = $conn->prepare("

           SELECT site_id, banner_id, adgroup_id, bannergroup_id, name, time_start, time_end, budget, budget_day, show_max_per_day, click_max_per_day, 
                  stop_show_count, stop_click_count, sum(today_show_count) as stshc, sum(today_click_count) as stcc, all_show_count, all_click_count,  status
           FROM banner.adgroup 

	       LEFT JOIN adgroup_banner using (adgroup_id)
           LEFT JOIN banner_stat using (banner_id)
	
           where status=1 and today_show_count > 0 
          
           group by adgroup_id;
        
    ");
$sql->execute();
$result = $sql->setFetchMode(PDO::FETCH_ASSOC);
$adgroups = $sql->fetchAll();

// print_r($adgroups);

//die();


foreach ($adgroups as $k => $v) {

    $site_id = $v['site_id'];
    $banner_id = $v['$banner_id'];
    $ag_id = $v['adgroup_id'];
    $bg_id = $v['bannergroup_id'];
    $time_start = $v['time_start'];
    $time_end = $v['time_end'];
    $stshc = $v['stshc']; //sum today show count
    $show_max_day = $v['show_max_per_day'];
    $click_max_per_day = $v['click_max_per_day'];
    $stcc = $v['stcc'];  // sum today click count
    $all_show_count = $v['all_show_count'];
    $all_click_count = $v['all_click_count'];
    $stop_show_count = $v['stop_show_count'];
    $stop_click_count = $v['stop_click_count'];

    if ($stshc > $show_max_day) {
        $tshc_st = 'color:red;';
    } elseif ($stcc > $click_max_per_day && $click_max_per_day > 0) {
        $stcc_st = 'color:red;';
    } elseif ($all_show_count > $stop_show_count && $stop_show_count > 0) {
        $all_show_count_st = 'color:red;';
    } elseif (time() > $time_end) {
        $time_end_st = 'color:red;';
    } else {
        continue;
    }

    $send_mail = 1;

    $msg .= '<tr><td>';
    $msg .= '<a href="http://c8.net.ua/adgroups/banners/sid/' . $site_id . '/cid/' . $bg_id . '/id/' . $ag_id . '">' . $ag_id . '</a>';
    $msg .= '</td></tr>';

    $msg .= '<tr>';

    $msg .= '<td></td>';
    $msg .= '<td>' . date('d-m-Y H:i', $time_start) . '</td>';
    $msg .= '<td style=' . $time_end_st . '>' . date('d-m-Y H:i', $time_end) . '</td>';
    $msg .= '<td style=' . $tshc_st . '>' . $stshc . '</td>';
    $msg .= '<td>' . $show_max_day . '</td>';
    $msg .= '<td style=' . $stcc_st . '>' . $stcc . '</td>';
    $msg .= '<td>' . $click_max_per_day . '</td>';

    $msg .= '<td style=' . $all_show_count_st . '>' . $all_show_count . '</td>';
    $msg .= '<td>' . $stop_show_count . '</td>';
    $msg .= '<td>' . $all_click_count . '</td>';
    $msg .= '<td>' . $stop_click_count . '</td>';

    $msg .= '</tr>';

//                    $msg .=  '<tr>';
//
//                    $msg .=  '<td></td>';
//                    $msg .=  '<td>' . date("Y-m-d H:i:s", $v['ts']) . '</td>';
//                    $msg .=  '<td>' . $v['h'] . '</td>';
//                    $msg .=  '<td>' . $v['c'] . '</td>';
//                    $msg .=  '<td>' . $v['total'] . '</td>';
//
//                    $msg .=  '</tr>';


    $time_end_st = '';
    $tshc_st = '';
    $stcc_st = '';
    $all_show_count_st = '';

}


if ($send_mail == 1) {

    $msg = '<table><th>ag_id</th><th>time start</th><th>time finish</th><th>today show.c </th><th>show max/day</th><th>today click</th><th>click max/day</th>
                <th>all show.c</th><th>stop show.c</th><th>all click.c</th><th>stop click.c</th>

        ' . $msg;
    $msg .= '</table>';

    echo $head . $msg;

    $to = 'spm_x@z1.kiev.ua';
    $subject = 'RTB watch: Company doesnt stop!';
    $headers = 'From: spm_x@z1.kiev.ua' . "\r\n" .
        'Reply-To: spm_x@z1.kiev.ua' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

    //mail($to, $subject, $head.$msg, $headers);

}

//print_r($banner_array);

?>