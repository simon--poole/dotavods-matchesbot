<?php
date_default_timezone_set("UTC");
class MatchesBot {
	private $token;
	public function __construct() {
		require 'config.php';
		require 'lib/httpful.phar';
		$this->limit = 10;
	}
	public function run(){
		$matches = $this->getMatches();
		if(is_null($matches)){
			$sidebar = $this->loadSidebar("* No upcoming matches.");
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		} else {
			$matches = $this->sortMatches($matches);
			$result = $this->parseMatches($matches);
			$sidebar = $this->loadSidebar($result);
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		}
	}
	private function login() {
			$array = array(
				"grant_type" => "password",
				"username" => Config::$User['user'],
				"password" => Config::$User['password']
			);
			$response = \Httpful\Request::post("https://www.reddit.com/api/v1/access_token")
				->sendsType(\Httpful\Mime::FORM)
				->expectsJson()
				->body($array)
				->authenticateWith(Config::$User['client_id'], Config::$User['client_secret'])
				->userAgent(Config::$Settings['useragent'])
				->send();
			$this->token = $response->body->access_token;
	}
	private function getMatches(){
		$today = date('Y-m-d',strtotime('today'));
		$data = json_decode(file_get_contents("http://dailydota2.com/match-api"));
		if(count($data->matches) == 0)
			return null;
		foreach($data->matches as $match){
			$tournament = $match->league->name;
			$redTeam = $match->team2->team_tag;
			$blueTeam = $match->team1->team_tag;
			$timeDiff = $match->timediff;
			$bo_x = $match->series_type;
			$matches[] = array($tournament, $redTeam, $blueTeam, $timeDiff, $bo_x);
		}
		return $matches;
	}
	private function sortMatches($matches){
		usort($matches, function($key1,$key2) {
			if($key1[3] < 0 && $key2[3] < 0)
				return 0;
			else if ($key1[3] < 0 && $key2[3] >= 0)
				return -1;
			else if ($key1[3] < 0 && $key2[3] >= 0)
				return 1;
			else
				return ($key1[3]<$key2[3])?-1:1;
		});
		return $matches;
	}
	private function parseMatches($matches){
		$previous_tournament = "";
		$count = 0;
		$result = "";
		foreach($matches as $match){
			if($match[3] < (-3600 * $match[4]))
				continue 1;
			else if($match[3] <= 0)
				$time = "**LIVE**";
			else {
				$hrs = floor((int) $match[3] / 3600);
				$mins = floor(($match[3] / 60) % 60);
				$time = $hrs."h ".$mins."m";
			}
			$time = str_replace(array(" 0d,", " 0h,"), "", $time);
			if($previous_tournament != $match[0]){
				$result .= PHP_EOL;
				$result .=  "**" . $match[0] . "**";
				$result .= PHP_EOL;
				$result .= PHP_EOL;
				$result .= "| | | | |\n:--:|:--|:--:|--:";
			}
			$previous_tournament = $match[0];
			$teams = $this->formatTeams($match[0], $match[1], $match[2]);
			$result .= PHP_EOL."$time | $teams[0] | vs. | $teams[1]";
			if(++$count == $this->limit)
					break;
		}
		return $result;
	}
	 private function formatTeams($event, $team1, $team2){
	 	$team1 = $this->getTeam($team1);
	 	$team2 = $this->getTeam($team2);
		 if($this->checkMatchSpoiler($event, $team1, $team2))
			return array("[$team1](/spoiler) [](#spoiler)", "[](#spoiler) [$team2](/spoiler)");
		else {
			$icon1 = $this->getIcon($team1);
			$icon2 = $this->getIcon($team2);
			$team1 = $this->checkTeamSpoiler($event, $team1) ? "[$team1](/spoiler) [](#spoilers)" : "**$team1** [](#$icon1)";
			$team2 = $this->checkTeamSpoiler($event, $team2) ? "[](#spoilers) [$team2](/spoiler)" : "[](#$icon2) **$team2**";
			return array($team1, $team2);
		}
	 }
	public function loadSidebar($matches) {
		$this->login();
		$response = \Httpful\Request::get("https://oauth.reddit.com/r/".Config::$Settings['subreddit']."/wiki/sidebar")
				->expectsJson()
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
		$sidebar = str_replace(Config::$wiki['template'], $matches, $response->body->data->content_md);
		return $sidebar;
	}
 
	protected function post($content) {
		$response = \Httpful\Request::get("https://oauth.reddit.com/r/".Config::$Settings['subreddit']."/about/edit/.json")
				->expectsJson()
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
		$settings = (array) $response->body->data;
		$settings['description'] = htmlspecialchars_decode($content);
		$settings['sr'] = $settings['subreddit_id'];
		$settings['link_type'] = $settings['content_options'];
		$settings['type'] = $settings['subreddit_type'];
		$settings['over_18'] = "false";
		unset($settings['hide_ads']);
		$response = \Httpful\Request::post("https://oauth.reddit.com/api/site_admin?api_type=json")
				->sendsType(\Httpful\Mime::FORM)
				->expectsJson()
				->body($settings)
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
	}
	
	private function getIcon($team){
		if(array_key_exists($team, Config::$Icons)){
			return Config::$Icons[$team];
		}
		return str_replace("`", "", preg_replace('/\s+/', '', strtolower($team)));
	}
	private function getTeam($team){
		if(array_key_exists($team, Config::$Teams))
			return Config::$Teams[$team];
		else
			return $team;
	}
	private function checkMatchSpoiler($title, $t1, $t2){
		if($t1 == "TBD" && $t2 == "TBD")
			return false;
		foreach(Config::$Settings['spoilers'] as $spoiler){
			if(strpos(strtolower($title), strtolower($spoiler)) !== false)
				return true;
		}
		return false;
	}
	private function checkTeamSpoiler($title, $team){
		if(array_key_exists($title, Config::$Blacklisted_Teams) && in_array($team, Config::$Blacklisted_Teams[$title]))
			return true;
		return false;
	}
}
?>