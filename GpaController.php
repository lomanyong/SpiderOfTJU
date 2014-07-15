<?php
/*
 * Based on Laravel
 * Created by Yong
 */
class GPAController extends BaseController {
	
	public $cookiefile;
	public $result = array();

	/*
	 *	查询并计算加权和GPA
	 */
	public function postGPA() {
		if( ! $this->checkTjuLogin()) {
			$this->error(Config::get('statuscode.AccountantOrPassword', 999));
			return Response::json($this->response, 401);
		}
		if ( ! $this->getGPA()) {
			return Response::json($this->response, 403);
		}
		$this->response($this->result);
		return Response::json($this->response);
	}

	/*
	 *	查询并计算加权和GPA
	 */
	public function postAuto() {
		if( ! $this->checkTjuLogin()) {
			$this->error(Config::get('statuscode.AccountantOrPassword', 999));
			return Response::json($this->response, 401);
		}
		if ( ! $this->autoEvaluate()) {
			return Response::json($this->response, 403);
		}
		$this->response($this->result);
		return Response::json($this->response);
	}

	private function checkTjuLogin() {
		$this->cookiefile = tempnam('cache', 'cookie');

		$userinfo = array(
			'uid' => Input::get('tjuuname', ''),
			'password' => Input::get('tjupasswd', '')
		);
		$curl = new Curl;
		$curl->create('http://e.tju.edu.cn/Main/logon.do');
		$curl->option(CURLOPT_COOKIEJAR, $this->cookiefile);
		$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
		$curl->post($userinfo);
		$content = $curl->execute();
		return (strpos($content, 'Erorr') === false) ? true : false;;
	}

	private function getGPA() {
		$termurl = $this->getUrl();
		# 如果对方是大一新生的话则没有成绩，返回默认配置设定的值
		if (count($termurl) === 0) {
			return false;
		}
		foreach ($termurl as $v) {
			$this->getPerTerm($v);
		}
		$this->calculateGPA();
		return $this->result;
	}

	/**
	 * 获取每个学期成绩的 URL
	 */
	private function getUrl() {
		$curl = new Curl;
		$curl->create('http://e.tju.edu.cn/Education/stuachv.do');
		$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
		$curl->option(CURLOPT_ENCODING, 'gbk');
		$content = $curl->execute();

		preg_match_all("/<a class=\"titlelink\" href=\"(.*?)term=([^\"]*)/s", $content, $matches);

		$termurl = array();
		if (count($matches) === 0) {
			$this->response(Config::get('gpa.newcome'));
		}
		for ($i = 0; $i < count($matches[1]); $i++) {
			$termurl[] = 'http://e.tju.edu.cn'.$matches[1][$i].'term='.$matches[2][$i];
		}

		return $termurl;
	}

	/**
	 * 获取每个学期的 GPA
	 */
	private function getPerTerm($url) {
		$curl = new Curl;
		$curl->create($url);
		$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
		$content = $curl->execute();

		preg_match_all("/<table.*?bgcolor=\"#[9]+\"[^>]*>/i", $content, $tag);
		preg_match_all("/".$tag[0][0]."(.*?)<\/table>/s", $content, $matches);

		$this->result['terms'][] = $this->convertHTMLToArray($matches[1]);
	}

	private function convertHTMLToArray($content) {
		foreach ($content as &$v) {
			$v = mb_convert_encoding($v, 'utf-8', 'gbk');
		}
		$data = array();
		if (count($content) > 1) $store = $content[1];	// 莫名其妙的bug...需要这样才能正常...不然content[1]的值会在循环中改变
		for ($i = 0; $i < count($content); $i++) {
			if ($i == 1) $content[1] = $store;
			preg_match_all("/<tr.*?bgcolor=\"#[F]+\"[^>]*>(.*?)<\/tr>/s", $content[$i], $matches);
			
			switch ($i) {
				case 0:
					foreach ($matches[1] as $v) {
						preg_match_all('/<td[^>]+><[^>]+>(.*?)<\/font><\/td>/s', $v, $arr);
						
						$tmp = array();
						$tmp['term'] = trim($arr[1][0]);
						$tmp['name'] = str_replace('&nbsp;', '', trim($arr[1][2]));
						$tmp['type'] = trim($arr[1][3]) == '--' ? 1 : 0;
						$tmp['credit'] = trim($arr[1][5]);
						if ($tmp['credit'] == '.5') {
							$tmp['credit'] = '0.5';
						}
						$tmp['score'] = trim($arr[1][6]) == '' ? 0 : trim($arr[1][6]);
						# update by Yong at 14.07.02 办公网加入了可以直接跳转去评价的链接...
						//$tmp['score'] = trim($arr[1][6]) == '评价' ? -1 : $tmp['score'];
						$is_comment = strip_tags($arr[1][6]);
						$tmp['score'] = trim($is_comment) == '评价' ? -1 : $tmp['score'];
						$tmp['reset'] = (trim($arr[1][8])== '重修' || trim($arr[1][8])== '安排重修') ? 1 : 0;
						$data[] = $tmp;
					}
					break;
				case 1:
					# 如果出现社会实践成绩就是如下这种情况
					foreach ($matches[1] as $v) {
						preg_match_all('/<td[^>]+><[^>]+>(.*?)<\/font><\/td>/s', $v, $arr);

						$tmp = array();
						$tmp['term'] = trim($arr[1][0]);
						$tmp['name'] = str_replace('&nbsp;', '', trim($arr[1][2]));
						$tmp['type'] = 0;
						$tmp['credit'] = trim($arr[1][4]);
						if ($tmp['credit'] == '.5') {
							$tmp['credit'] = '0.5';
						}
						$tmp['score'] = trim($arr[1][5]) == '' ? 0 : trim($arr[1][5]);
						$is_comment = strip_tags($arr[1][5]);
						$tmp['score'] = trim($is_comment) == '评价' ? -1 : $tmp['score'];
						//$tmp['score'] = trim($arr[1][5]) == '评价' ? null : $tmp['score'];
						$tmp['reset'] = 0;
						$data[] = $tmp;
					}
					break;
				default:
					break;
			}
		}

		return $data;
	}

	/*
	 *	加权与GPA的计算函数
	 */
	private function calculateGPA() {
		$totalScore = 0;
		$totalGPA = 0;
		$totalCredit = 0;
		$every = array();

		foreach ($this->result['terms'] as $term) {
			$termCredit = 0;
			$termScore = 0;
			$termGPA = 0;
			$cal = array();
			foreach ($term as $v) {
				if ($v['type'] != 1 
					&& $v['score'] >= 60
					&& $v['score'] <= 100
					&& $v['name'] != '社会实践') {

					$termCredit += $v['credit'];
					$termScore += $v['score'] * $v['credit'];
					$termGPA += $this->convertScoreToGPA($v['score']) * $v['credit'];
				}
			}
			# 加了credit判断，防止除数为零的情况，出现在有人出了成绩但是都没有评价的情况下
			$cal['score'] = $termCredit == 0 ?  60.00 : round($termScore / $termCredit, 2);
			$cal['gpa'] = $termCredit == 0 ? 1.00 : round($termGPA / $termCredit, 2);

			$every[] = $cal;
			$totalScore += $termScore;
			$totalCredit += $termCredit;
			$totalGPA += $termGPA;
		}

		$data = array(
			'score' => $totalCredit == 0 ? 60.00 : round($totalScore / $totalCredit, 2),
			'gpa' => $totalCredit == 0 ? 1.00 : round($totalGPA / $totalCredit, 2),
			'credit' => $totalCredit,
			'every' => $every,
		);

		$this->result['data'] = $data;
	}

	/**
	 * 自动评价
	 */
	private function autoEvaluate() {
		$curl = new Curl;
		$curl->create('http://e.tju.edu.cn/Education/evaluate.do?todo=list');
		$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
		$content = $curl->execute();

		preg_match_all("/\"\.\/evaluate(.*?)\"/is", $content ,$arr);

		if(count($arr[0]) == 0) {
			$this->error(Config::get('statuscode.CommentNotStart', 999));
			return false;
		}

		$urlRoot = 'http://e.tju.edu.cn/Education/evaluate';
		$urls = $arr[1];
		foreach ($urls as &$v) {
			$v = $urlRoot.$v;
		}

		$postdata = array();
		foreach ($arr[1] as $v) {
			preg_match_all('/.(\d+)/', $v, $tmp);
			$postdata[] = array(
				'lesson_id' => $tmp[1][0],
				'union_id'  => $tmp[1][1],
				'course_id' => $tmp[1][2],
				'sumScore' => 100,
				'evaluateContent' => ''
			);
		}

		$length = sizeof($urls);
		for ($i=0; $i<$length; $i++) {
			$post = $postdata[$i];
			$curl->create($urls[$i]);
			$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
			$content = $curl->execute();

			preg_match("/evaluate_type\"[^0-9]+(\d+)\"/", $content, $evaluate_value);
			$post['evaluate_type'] = count($evaluate_value)>0 ? $evaluate_value[1] : 1;

			preg_match_all("/\(([a-zA-Z]{0,2}\d{4,6})\)/", $content, $teacher_id);
			preg_match_all("/<table width=\"90%\" align=\"center\">.*?<\/table>/is", mb_convert_encoding($content, 'UTF-8'), $radiocount);
			$num = count($radiocount[0]);
			$count = $num >= 8 ? $num/2-1 : $num-1;
			foreach ($teacher_id[1] as $v) {
				for ($j=1; $j<=$count; $j++) {
					$key = $v.'_'.$j;
					$post[$key] = 100;
				}
			}
			$curl->create('http://e.tju.edu.cn/Education/toModule.do?prefix=/Education&page=/evaluate.do?todo=Submit');
			$curl->option(CURLOPT_COOKIEFILE, $this->cookiefile);
			$curl->post($post);
			$curl->execute();
		}

		$this->getGPA();
		return true;
	}

	private function convertScoreToGPA($score){
		switch ($score) {
			case ($score>=90&&$score<=100): 
				$gpa = 4.0;
				break;
			case ($score>=85&&$score<=89):
				$gpa = 3.7;
	           	break;
			case ($score>=82&&$score<=84):
				$gpa = 3.3;
				break; 
			case ($score>=78&&$score<=81):
				$gpa = 3.0;
				break; 
			case ($score>=75&&$score<=77):
				$gpa = 2.7;
				break;
			case ($score>=72&&$score<=74):
				$gpa = 2.3;
				break;
			case ($score>=68&&$score<=71):
				$gpa = 2.0;
				break;       
			case ($score>=64&&$score<=67):
				$gpa = 1.5;
				break;
			case ($score>=60&&$score<=63):
				$gpa = 1.0; 
				break;  
			default:
				$gpa = 0;
				break;
		}
		return $gpa;
	}



?>