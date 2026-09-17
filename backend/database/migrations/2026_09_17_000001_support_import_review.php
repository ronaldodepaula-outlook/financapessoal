<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{Schema::table('import_rows',fn(Blueprint $t)=>$t->boolean('selected')->default(true));Schema::table('imports',fn(Blueprint $t)=>$t->dateTime('confirmed_at')->nullable()->change());}
    public function down():void{Schema::table('import_rows',fn(Blueprint $t)=>$t->dropColumn('selected'));}
};
