<?php
declare(strict_types=1);
namespace Finance\Auth;

use Finance\Api\ApiClient;

final class SessionAuth
{
    public function __construct(private ApiClient $api){}
    public function login(string $email,string $password):array
    {
        $result=$this->api->request('POST','/auth/login',['email'=>$email,'password'=>$password]);
        if($result['status']===200){session_regenerate_id(true);$this->store($result['data']['data']);$_SESSION['csrf']=bin2hex(random_bytes(32));$me=$this->api->request('GET','/auth/me',[],$_SESSION['access_token']);$_SESSION['user']=$me['data']['data']??null;}
        return $result;
    }
    private function store(array $tokens):void{$_SESSION['access_token']=$tokens['access_token'];$_SESSION['refresh_token']=$tokens['refresh_token'];$_SESSION['token_expires_at']=time()+$tokens['expires_in'];$_SESSION['last_seen']=time();}
    public function loggedIn():bool{return isset($_SESSION['access_token']);}
    public function call(string $method,string $path,array $data=[],?array $file=null):array
    {
        if(!$this->loggedIn())return $this->expired();
        if(($_SESSION['token_expires_at']??0)<=time()+20&&!$this->refresh())return $this->expired();
        $result=$this->api->request($method,$path,$data,$_SESSION['access_token'],$file);
        if($result['status']===401){if(!$this->refresh())return $this->expired();$result=$this->api->request($method,$path,$data,$_SESSION['access_token'],$file);if($result['status']===401)$this->clear();}
        $_SESSION['last_seen']=time();return $result;
    }
    private function refresh():bool
    {
        $result=$this->api->request('POST','/auth/refresh',['refresh_token'=>$_SESSION['refresh_token']??'']);
        if($result['status']!==200){$this->clear();return false;}$this->store($result['data']['data']);return true;
    }
    private function expired():array{return ['status'=>401,'data'=>['success'=>false,'message'=>'Sua sessão expirou. Entre novamente.','data'=>null,'errors'=>[]],'body'=>'','content_type'=>'application/json'];}
    public function clear():void{$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));}
    public function logout():void{try{if($this->loggedIn())$this->api->request('POST','/auth/logout',[],$_SESSION['access_token']);}finally{$this->clear();}}
}
