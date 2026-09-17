<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StatementParser
{
    public function parse(string $text,string $format):array
    {
        if(str_starts_with($text,"\xFF\xFE")||str_starts_with($text,"\xFE\xFF"))$text=mb_convert_encoding($text,'UTF-8','UTF-16');
        elseif(!mb_check_encoding($text,'UTF-8'))$text=mb_convert_encoding($text,'UTF-8','Windows-1252');
        $text=preg_replace('/^\xEF\xBB\xBF/','',$text);
        if(str_contains($text,"\0")||preg_match('/<!DOCTYPE|<!ENTITY/i',$text))throw ValidationException::withMessages(['file'=>'Arquivo binário ou declaração XML externa não permitida.']);
        $rows=$format==='CSV'?$this->csv($text):$this->ofx($text);
        if(!$rows||count($rows)>2000)throw ValidationException::withMessages(['file'=>'Envie entre 1 e 2.000 registros por arquivo.']);
        return $rows;
    }
    private function csv(string $text):array
    {
        $first=strtok($text,"\r\n");$delimiter=';';$max=0;
        foreach([';',',',"\t"]as$option){$count=count(str_getcsv($first?:'',$option,'"',''));if($count>$max){$max=$count;$delimiter=$option;}}
        $stream=fopen('php://temp','w+');fwrite($stream,$text);rewind($stream);
        try{
            $headers=array_map(fn($v)=>trim((string)$v),fgetcsv($stream,0,$delimiter,'"','')?:[]);
            if(count($headers)<2||count($headers)>40||count(array_unique($headers))!==count($headers)||in_array('',$headers,true))throw ValidationException::withMessages(['file'=>'CSV deve ter cabeçalho com 2 a 40 colunas distintas.']);
            $rows=[];
            while(($cells=fgetcsv($stream,0,$delimiter,'"',''))!==false){
                if(count($cells)===1&&$cells[0]===null)continue;
                if(count($cells)!==count($headers))throw ValidationException::withMessages(['file'=>'Quantidade de colunas inconsistente na linha '.(count($rows)+2).'.']);
                $rows[]=array_combine($headers,$cells);
                if(count($rows)>2000)break;
            }
            return $rows;
        }finally{fclose($stream);}
    }
    private function ofx(string $text):array
    {
        if(!preg_match('/<OFX[>\s]/i',$text)||!preg_match('/<\/OFX>/i',$text))throw ValidationException::withMessages(['file'=>'Documento OFX incompleto.']);
        preg_match_all('/<STMTTRN>\s*(.*?)<\/STMTTRN>/is',$text,$matches);
        $rows=[];
        foreach($matches[1]as$block){
            $get=function(string $tag)use($block){preg_match('/<'. $tag .'>([^<\r\n]*)/i',$block,$m);return trim(html_entity_decode($m[1]??'',ENT_QUOTES|ENT_XML1,'UTF-8'));};
            $rows[]=['date'=>$get('DTPOSTED'),'description'=>$get('MEMO')?:$get('NAME'),'amount'=>$get('TRNAMT'),'external_id'=>$get('FITID'),'source_type'=>$get('TRNTYPE')];
            if(count($rows)>2000)break;
        }
        return $rows;
    }
}
