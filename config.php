<?php
class Config {
	static $Settings = array(
		"subreddit"=>"dotavods",				//Subreddit for bot to run on
		"spoilers"=>array("final", "playoffs"),
	);
	//Wikipage that bot will access to load sidebar details
	//Make sure your bot account has access to it
	static $wiki = array(
		"page"=>"sidebar",
		"template"=>"%%matches%%",
	);
	//User account details
	static $User = array(
		"user"=>'',
		"passwd"=>"",
		"api_type"=>"json",
	);
	static $Teams = array(
		"NAR v2" => "Archon",
	);
	static $Icons = array(
		"Fnatic" => "fnc",
	);
	static $Blacklisted_Teams = array(
		"The International 2015" => array("CDEC", "MVP"),
	);
}
?>