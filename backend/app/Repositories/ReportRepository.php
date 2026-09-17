<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportRepository
{
    public function query(User $user,array $filters):Builder
    {
        $query=Transaction::forUser($user->id)->where('status','!=','CANCELADA');
        foreach(['category_id','subcategory_id','account_id','card_id','merchant_id','payment_method','transaction_type','status','is_fixed']as$field)if(isset($filters[$field]))$query->where($field,$filters[$field]);
        if(isset($filters['year']))$query->where('competence_year',$filters['year']);
        if(isset($filters['month']))$query->where('competence_month',$filters['month']);
        if(isset($filters['date_from']))$query->where('transaction_date','>=',$filters['date_from']);
        if(isset($filters['date_to']))$query->where('transaction_date','<=',$filters['date_to']);
        return $query;
    }
}
