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

preg_match_all("/<tr height = \"20\" bgcolor=\"#F+\">(.*?)<\/tr>/is", $content , $row_matches);

$result = array();
if ($row_matches) {
  foreach ($row_matches[1] as $row) {
    preg_match_all("/<td[^>]+><[^>]+>(.*?)<\/font><\/td>/is", trim($row), $matches);
    $tmp = array();
    $details = $matches[1];
    $tmp['classid'] = trim($details[0]);
    $tmp['courseid'] = trim($details[1]);
    $tmp['coursename'] = trim($details[2]);
    $tmp['type'] = trim($details[3]);
    $tmp['nature'] = trim($details[4]);
    $tmp['coursenum'] = trim($details[5]);
    $tmp['teacher'] = trim($details[6]);
    $tmp['arrange'] = trim($details[7]);
    $tmp['fromto'] = trim($details[8]);
    $result[] = $tmp;
  }
}

var_dump($result);

function getClassArrange($str) {
  $arrange = array();
  $times = explode("<br/>", $str);
  foreach ($times as $single) {
    $details = explode(",", $single);
    $arrange['weektimes'] = trim($details[0]);
    $arrange['week'] = trim($details[1]);
    $tmp = explode(' ', trim($details[2]));
    $arrange['tmp'] = $tmp;
  }
  return $arrange;
}

?>