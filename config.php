<?php
class Config {
	static $Settings = array(
		"useragent" => "/r/DotaVods Sidebar Matches Bot by github.com/simon--poole",
		"subreddit"=>"dotavods", //Subreddit for bot to run on
		"spoilers"=>array("final", "playoffs", "The International 2015"),
	);
	//Wikipage that bot will access to load sidebar details
	//Make sure your bot account has access to it
	static $wiki = array(
		"page"=>"sidebar",
		"template"=>"%%matches%%",
	);
	//User account details
	static $User = array(
		"user"=>"",
		"password"=>"",
		"client_id" => "",
		"client_secret" => "",
	);
	static $Teams = array(
		"NAR v2" => "Archon",
		"MVP.Phoenix" => "MVP.P"
	);
	static $Icons = array(
		"Fnatic" => "fnc",
		"MVP.Hot6" => "mvp",
		"MVP.P" => "mvp",
		"Na'Vi" => "navi"
	);
	static $Blacklisted_Teams = array(
		"The International 2015" => array("CDEC", "MVP.P"),
	);
}
?>