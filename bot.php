<?php
date_default_timezone_set("UTC");
class MatchesBot {
	private $userhash;
	public function __construct() {
		require 'config.php';
		require 'lib/snoopy.php';
		$this->snoopy = new \Snoopy;
		$this->limit = 8;
	}
	public function run(){
		$matches = $this->getMatches();
		if(is_null($matches)){
			$sidebar = $this->loadSidebar("No upcoming matches.");
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
			$this->snoopy->submit("http://reddit.com/api/login/".Config::$User['user'], Config::$User);
			$login = json_decode($this->snoopy->results);
			$this->snoopy->cookies['reddit_session'] = $login->json->data->cookie;
			$this->userhash = $login->json->data->modhash;
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
			$matches[] = array($tournament, $redTeam, $blueTeam, $timeDiff);
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
			$spoiler = $this->checkSpoiler($match[0], $match[1], $match[2]);
			if($match[3] <= 0)
				$time = "**LIVE**";
			else {
				$hrs = floor((int) $match[3] / 3600);
				$mins = floor(($match[3] / 60) % 60);
				$time = "$hrs hrs, $mins mins";
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
			$team1 = $this->getTeam($match[1]);
			$team2 = $this->getTeam($match[2]);
			if($spoiler)
				$result .= PHP_EOL."$time | [$team1](/spoiler) | vs. | [$team2](/spoiler)";
			else	{
				$icon1 = $this->getIcon($team1);
				$icon2 = $this->getIcon($team2);
				$result .= PHP_EOL."$time | **$team1** [](#$icon1)| vs. |[](#$icon2) **$team2**";
			}
			if(++$count == $this->limit)
					break;
		}
		return $result;
	}
	public function loadSidebar($matches) {
		$this->login();
		$this->snoopy->fetch("http://www.reddit.com/r/".Config::$Settings['subreddit']."/wiki/".Config::$wiki['page'].".json");
		$sidebar = json_decode($this->snoopy->results);
		$sidebar = $sidebar->data->content_md;
		$sidebar = str_replace("&gt;", ">", $sidebar);
		$sidebar = str_replace("&amp;", "&", $sidebar);
		$sidebar = str_replace(Config::$wiki['template'], $matches, $sidebar);
		return $sidebar;
	}
 
	protected function post($content) {
		$this->snoopy->fetch("http://www.reddit.com/r/".Config::$Settings['subreddit']."/about/edit/.json");
		$about = json_decode($this->snoopy->results);
		$data = $about->data;
		$parameters['sr'] = $data->subreddit_id;
		$parameters['title'] = $data->title;
		$parameters['public_description'] = $data->public_description;
		$parameters['lang'] = $data->language;
		$parameters['type'] = 'restricted';
		$parameters['link_type'] = 'self';
		$parameters['wikimode'] = $data->wikimode;
		$parameters['wiki_edit_karma'] = $data->wiki_edit_karma;
		$parameters['wiki_edit_age'] = $data->wiki_edit_age;
		$parameters['allow_top'] = 'on';
		$parameters['header-title'] = '';
		$parameters['id'] = '#sr-form';
		$parameters['r'] = Config::$Settings['subreddit'];
		$parameters['renderstyle'] = 'html';
		$parameters['comment_score_hide_mins'] = $data->comment_score_hide_mins;
		$parameters['public_traffic'] = $data->public_traffic;
		$parameters['spam_comments'] = $data->spam_comments;
		$parameters['spam_links'] = $data->spam_links;
		$parameters['spam_selfposts'] = $data->spam_selfposts;
		$parameters['description'] = $content;
		$parameters['uh'] = $this->userhash;
 		$parameters['show_media'] = $data->show_media;
		$this->snoopy->submit("http://www.reddit.com/api/site_admin?api_type=json", $parameters);
	}
	
	private function getIcon($team){
		if(array_key_exists($team, Config::$Icons)){
			//
		}
		return str_replace("`", "", preg_replace('/\s+/', '', strtolower($team)));
	}
	private function getTeam($team){
		if(array_key_exists($team, Config::$Teams))
			return Config::$Teams[$team];
		else
			return $team;
	}
	private function checkSpoiler($title, $t1, $t2){
		if($t1 == "TBD" && $t2 == "TBD")
			return false;
		foreach(Config::$Settings['spoilers'] as $spoiler){
			if(strpos(strtolower($title), strtolower($spoiler)) !== false)
				return true;
		}
		return false;
	}	
}
?>
