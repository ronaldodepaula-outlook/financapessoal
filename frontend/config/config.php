<?php
declare(strict_types=1);
$file=dirname(__DIR__).'/.env';$values=[];
if(is_file($file))foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(str_starts_with(trim($line),'#')||!str_contains($line,'='))continue;[$key,$value]=explode('=',$line,2);$values[trim($key)]=trim(trim($value),"\"'");}
$env=fn(string $key,string $default)=>getenv($key)?:($values[$key]??$default);
return ['api_url'=>rtrim($env('API_URL','http://localhost/financapessoal/backend/public/api'),'/'),'app_name'=>$env('APP_NAME','Finança Pessoal'),'session_name'=>$env('SESSION_NAME','FINANCA_SESSION'),'idle_timeout'=>7200];
