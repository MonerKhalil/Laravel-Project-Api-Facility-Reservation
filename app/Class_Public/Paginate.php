<?php

namespace App\Class_Public;

use Illuminate\Http\Request;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;


trait Paginate
{
    public function Paginate(string $namedata,$paginate): array
    {
        return [
            $namedata=> $paginate->items(),
            "current_page" => $paginate->currentPage(),
            "url_next_page" => $paginate->nextPageUrl(),
            "url_first_page" => $paginate->path()."?page=1",
            "url_last_page" => $paginate->path()."?page=".$paginate->lastPage(),
            "total_items" => $paginate->total()
        ];
    }
    public function NumberOfValues(Request $request): int
    {
        try {
            if($request->has("num_values")&&$request->num_values>0){
                return $request->num_values;
            }
            throw new \Exception("");
        }catch (\Exception $exception){
            return 5;
        }
    }
    public function Check_Date($datestr,$dateend): bool
    {
        $num = round(strtotime($dateend) - strtotime($datestr));
//        $DStr = explode('-', $datestr);
//        $DEnd = explode('-', $dateend);
//        for ($i=0;$i<count($DStr);$i++){
//            if($DStr[$i]>$DEnd[$i])
//                return false;
//        }
        if($num<0){
            return false;
        }
        return true;
    }

}
