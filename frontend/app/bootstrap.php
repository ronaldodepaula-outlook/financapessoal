<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('log_errors','1');
date_default_timezone_set('America/Sao_Paulo');
require_once dirname(__DIR__).'/api/ApiClient.php';
require_once __DIR__.'/Auth/SessionAuth.php';
$config=require dirname(__DIR__).'/config/config.php';
$base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/financapessoal/frontend/public/index.php')),'/');
$storage=dirname(__DIR__).'/storage/sessions';if(!is_dir($storage))mkdir($storage,0700,true);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_save_path($storage);session_name($config['session_name']);
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>($base?:'').'/']);session_start();
$auth=new Finance\Auth\SessionAuth(new Finance\Api\ApiClient($config['api_url']));
if(isset($_SESSION['last_seen'])&&$_SESSION['last_seen']<time()-$config['idle_timeout'])$auth->clear();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
function e(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrfValid():bool{$value=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['_csrf']??'');return is_string($value)&&hash_equals($_SESSION['csrf'],$value);}
function jsonResponse(array $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
