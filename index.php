<?php

//https://www.flashradio.info/cors/?q=https://www.sodah.de

//oder alternativ

//https://galvanize-cors-proxy.herokuapp.com/https://www.sodah.de


define('PROXY_START', microtime(true));

require("vendor/autoload.php");

use Proxy\Http\Request;
use Proxy\Http\Response;
use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\FilterEvent;
use Proxy\Config;
use Proxy\Proxy;

session_start();

Config::load('./config.php');

if(!Config::get('app_key')){
	die("app_key inside config.php cannot be empty!");
}

if(!function_exists('curl_version')){
	die("cURL extension is not loaded!");
}

if(Config::get('url_mode') == 2){
	Config::set('encryption_key', md5(Config::get('app_key').$_SERVER['REMOTE_ADDR']));
} else if(Config::get('url_mode') == 3){
	Config::set('encryption_key', md5(Config::get('app_key').session_id()));
}

session_write_close();

function get_content($URL){
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_URL, $URL);
      $data = curl_exec($ch);
      curl_close($ch);
      return $data;
}

$uri = parse_url(@$_SERVER['REQUEST_URI']); // dom.com?q=web.com:8080
$uri2 = $_SERVER['REQUEST_URI'];
$urlpi = str_replace("/", "", $uri2); // q=web.com:8080
$url = get_content('http://first.my-servers.org/cors/'.$urlpi);


$url = url_decrypt($url);
$proxy = new Proxy();

foreach(Config::get('plugins', array()) as $plugin){
	$plugin_class = $plugin.'Plugin';
	if (file_exists('./plugins/'.$plugin_class.'.php')){
		require_once('./plugins/'.$plugin_class.'.php');
	} else if(class_exists('\\Proxy\\Plugin\\'.$plugin_class)){
		$plugin_class = '\\Proxy\\Plugin\\'.$plugin_class;
	}
	$proxy->getEventDispatcher()->addSubscriber(new $plugin_class());
}

try {
	$request = Request::createFromGlobals();
	$request->get->clear();
	$response = $proxy->forward($request, $url);
	$response->send();
} catch (Exception $ex){
	if(Config::get("error_redirect")){
		$url = render_string(Config::get("error_redirect"), array(
			'error_msg' => rawurlencode($ex->getMessage())
		));
		header("HTTP/1.1 302 Found");
		header("Location: {$url}");
	}
}

?>