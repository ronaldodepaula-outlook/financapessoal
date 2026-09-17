<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
try{
    if(!$auth->loggedIn())jsonResponse(['success'=>false,'message'=>'Entre para continuar.','data'=>null,'errors'=>[]],401);
    $method=$_SERVER['REQUEST_METHOD'];$path=$_GET['path']??'';$routes=require dirname(__DIR__).'/config/api-routes.php';$allowed=false;
    if(is_string($path))foreach($routes[$method]??[]as$pattern)if(preg_match($pattern,$path))$allowed=true;
    if(!$allowed)jsonResponse(['success'=>false,'message'=>'Operação não encontrada.','data'=>null,'errors'=>[]],404);
    if($method!=='GET'&&!csrfValid())jsonResponse(['success'=>false,'message'=>'A sessão do formulário expirou. Atualize a página.','data'=>null,'errors'=>[]],419);
    $data=[];$file=null;
    if(isset($_FILES['file'])){if($_FILES['file']['error']!==UPLOAD_ERR_OK||!is_uploaded_file($_FILES['file']['tmp_name']))jsonResponse(['success'=>false,'message'=>'Não foi possível receber o arquivo. Limite: 2 MB.','data'=>null,'errors'=>[]],422);$file=$_FILES['file'];$data=$_POST;unset($data['_csrf']);}
    elseif($method!=='GET'){$body=file_get_contents('php://input');$data=$body!==''?json_decode($body,true,512,JSON_THROW_ON_ERROR):[];if(!is_array($data))throw new JsonException;}
    $query=$_GET;unset($query['path']);if($query)$path.='?'.http_build_query($query);
    $result=$auth->call($method,$path,$data,$file);
    if(str_starts_with($result['content_type'],'text/csv')&&$result['status']===200){header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="relatorio-financeiro.csv"');echo $result['body'];exit;}
    jsonResponse($result['data']??['success'=>false,'message'=>'Resposta indisponível. Tente novamente.','data'=>null,'errors'=>[]],$result['status']);
}catch(JsonException $e){jsonResponse(['success'=>false,'message'=>'Dados inválidos.','data'=>null,'errors'=>[]],400);}
catch(Throwable $e){jsonResponse(['success'=>false,'message'=>'Não foi possível acessar o serviço. Verifique o Apache e o MySQL e tente novamente.','data'=>null,'errors'=>[]],503);}
