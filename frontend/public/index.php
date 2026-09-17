<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$page=is_string($_GET['page']??null)?$_GET['page']:($auth->loggedIn()?'dashboard':'login');
$error='';$email='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrfValid()){$error='O formulário expirou. Tente novamente.';http_response_code(419);}
    elseif($page==='logout'){try{$auth->logout();}catch(Throwable $e){$auth->clear();}header('Location: '.$base.'/?page=login',true,303);exit;}
    elseif($page==='login'){
        $email=is_string($_POST['email']??null)?trim($_POST['email']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
        try{$result=$auth->login($email,$password);if($result['status']===200){header('Location: '.$base.'/?page=dashboard',true,303);exit;}$error=$result['data']['message']??'Não foi possível entrar.';if($result['status']===422)$error='Informe um e-mail válido e sua senha.';}catch(Throwable $e){$error='O serviço está indisponível. Verifique o Apache e o MySQL.';}
        unset($password);
    }
}
if($page==='login'){
    if($auth->loggedIn()){header('Location: '.$base.'/?page=dashboard');exit;}
    require dirname(__DIR__).'/views/auth/login.php';exit;
}
if(!$auth->loggedIn()){header('Location: '.$base.'/?page=login&expired=1');exit;}
$pages=require dirname(__DIR__).'/routes/pages.php';
if(!isset($pages[$page])){$page='not-found';http_response_code(404);}
$definition=$pages[$page]??['title'=>'Página não encontrada','group'=>'Navegação'];
$_SESSION['last_seen']=time();$user=$_SESSION['user']??['name'=>'Minha conta'];
require dirname(__DIR__).'/views/layout.php';
