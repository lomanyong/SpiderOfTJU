<?php
# TODO 暂时还没解决同一门课有两个老师上的情况，需要找一个账号来解决.
$login_url = 'http://e.tju.edu.cn/Main/logon.do';
$class_table_url = 'http://e.tju.edu.cn/Education/toModule.do?prefix=/Education&page=/stuslls.do?todo=result';

$username = $_REQUEST['username'];
$password = $_REQUEST['password'];
$cookie = tempnam('cache', 'cookie');

$login_post_fields = 'uid='. $username . '&password='. $password;

# login
$ch = curl_init($login_url);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $login_post_fields);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); // 302
$content = curl_exec($ch);
curl_close($ch);

# class table
$ch = curl_init($class_table_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
$content = curl_exec($ch);
curl_close($ch);

preg_match_all("/<tr height = \"20\" bgcolor=\"#F+\">(.*?)<\/tr>/is", iconv("GBK", "UTF-8", $content), $row_matches);

$result = array();
if ($row_matches) {
  foreach ($row_matches[1] as $row) {
    preg_match_all("/<td[^>]+>[^<]*<[^>]+>(.*?)<\/font>[^<]*<\/td>/is", trim($row), $matches);
    $tmp = array();
    $details = $matches[1];
    $tmp['classid'] = trim($details[0]);
    $tmp['courseid'] = trim($details[1]);
    $tmp['coursename'] = trim($details[2]);
    $tmp['type'] = trim($details[3]);
    $tmp['nature'] = trim($details[4]);
    $tmp['coursenum'] = trim($details[5]);
    $tmp['teacher'] = trim($details[6]);
    $tmp['arrange'] = getClassArrangement(trim($details[8]));
    $tmp['startend'] = getStartAndEndWeek(trim($details[9]));
    $tmp['college'] = trim($details[10]);
    $result[] = $tmp;
  }
}

dd($result);

function dd($obj) {
  echo "<pre>";
  var_dump($obj);
  echo "</pre>";
}

function getClassArrangement($str) {
  $arrange = array();
  $times = explode("<br/>", $str);
  foreach ($times as $single) {
    if ($single != '') {
      $arr = array();
      $details = explode("，", $single);
      $arr['weektimes'] = trim($details[0]);
      $arr['weekday'] = substr($details[1], 3);
      $convert = preg_replace("/\s+/s", "|", $details[2]);
      $tmp = explode('|', $convert);
      $fromto =  explode('-', preg_replace("/[^0-9-]/", "", $tmp[0]));
      $arr['from'] = $fromto[0];
      $arr['to'] = $fromto[1];
      $arr['room'] = $tmp[1];
      $arrange[] = $arr;
    }
  }
  return $arrange;
}

function getStartAndEndWeek($str) {
  $arr = explode('-', $str);
  $startend = array();
  $startend['start'] = $arr[0];
  $startend['end'] = $arr[1];
  return $startend;
}

?>