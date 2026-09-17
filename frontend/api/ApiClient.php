<?php
declare(strict_types=1);
namespace Finance\Api;

final class ApiClient
{
    public function __construct(private string $baseUrl){}
    public function request(string $method,string $path,array $data=[],?string $token=null,?array $file=null):array
    {
        $curl=curl_init($this->baseUrl.$path);$headers=['Accept: application/json'];
        if($token)$headers[]='Authorization: Bearer '.$token;
        $options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS];
        if(in_array($method,['POST','PUT','DELETE'],true)){
            if($file){$data['file']=new \CURLFile($file['tmp_name'],'application/octet-stream',basename($file['name']));$options[CURLOPT_POSTFIELDS]=$data;}
            else{$headers[]='Content-Type: application/json';$options[CURLOPT_POSTFIELDS]=json_encode($data,JSON_THROW_ON_ERROR);}
        }
        $options[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);
        if($body===false||$status===0)throw new \RuntimeException('O serviço está temporariamente indisponível. Verifique se o Apache e o MySQL estão ligados.');
        $json=str_contains($type,'json')?json_decode($body,true):null;
        return ['status'=>$status,'data'=>$json,'body'=>$body,'content_type'=>$type];
    }
}
