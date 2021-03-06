<?php

namespace App\Http\Controllers\Api\Admin;

use App\Class_Public\GeneralTrait;
use App\Http\Controllers\Api\User\AuthController;
use App\Http\Controllers\Api\User\BookingController;
use App\Http\Controllers\Controller;
use App\Models\bookings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["auth:userapi","multi.auth:2"]);
    }

    public function CountNewAllUsersInLast5Month(): \Illuminate\Http\JsonResponse
    {
        try {
            $data = User::select(DB::raw("count(*) as count"),DB::raw("month(created_at) as month"))
                ->whereYear("created_at",Carbon::now()->year)
                ->where("rule","!=","2")
                ->groupBy(DB::raw("month"))
                ->orderBy("month","desc")
                ->take(5)
                ->pluck("count","month");
            foreach ($data->keys() as $key){
                $temp = date("F",mktime(0,0,0,$key,1));
                $data[$temp] = $data[$key];
                unset($data[$key]);
            }
            return \response()->json(["month"=>$data]);
        } catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function CountUsersInSystem(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validate = Validator::make($request->all(),[
                "rule"=>["nullable","string",Rule::in(["0","1"])]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $users = is_null(($request->rule)) ? User::all()->where("rule","!=","2")->count() : User::all()->where("rule",$request->rule)->count();
            return \response()->json(["numUsers"=>$users]);
        } catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function CountUsersLogoutInSystem(): \Illuminate\Http\JsonResponse
    {
        try {
            return \response()->json(["numUsers"=>$this->GetJsonFile($this->path_file())["countLogout"]]);
        } catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function UserBooking(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validate = Validator::make($request->all(),[
                "id_user" => ["nullable","numeric",Rule::exists("users","id")]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $facilities = User::where("id",$request->id_user)->first()->bookings()
                ->select(DB::raw("bookings.*,facilities.name,photos_facility.path_photo"))
                ->join("facilities","facilities.id","=","bookings.id_facility")
                ->leftJoin("photos_facility","photos_facility.id_facility","=","bookings.id_facility")
                ->groupBy("facilities.id")
                ->paginate($this->NumberOfValues($request));
            $facilities = $this->Paginate("infoBookings",$facilities);
//            foreach ($facilities["infoBookings"] as $item){
//                $item->photos = DB::table("photos_facility")
//                    ->select(["photos_facility.id as id_photo","photos_facility.path_photo"])
//                    ->where("photos_facility.id_facility",$item->id_facility)
//                    ->get();
//            }
            return \response()->json($facilities);
        }catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function UserProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validate = Validator::make($request->all(),[
                "id_user" => ["required",Rule::exists("users","id")]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $user = User::with("profile")
                ->where("id","=",$request->id_user)
                ->first();
            if($user->rule==="2"){
                throw new \Exception("the user is admin");
            }
            return \response()->json([
                "user" => $user
            ]);
        } catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function ShowUsersAllRule(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validate = Validator::make($request->all(),[
                "num_values" => ["nullable","numirce"],
                "rule"=>["nullable","string",Rule::in(["0","1"])]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            if(is_null($request->rule)){
                $users = User::with("profile")
                    ->where("rule","!=","2")
                    ->paginate($this->NumberOfValues($request));
            }else{
                $users = User::with("profile")
                    ->where("rule",$request->rule)
                    ->paginate($this->NumberOfValues($request));
            }
            return \response()->json(
                $this->Paginate("users",$users)
            );
        } catch (\Exception $exception){
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function AddUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $test = new AuthController();
        return $test->register($request);
    }

    public function DeleteUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $path = null;
        DB::beginTransaction();
        try {
            $validate = Validator::make($request->all(),[
                "id" => ["required",Rule::exists("users","id")]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $user = User::all()->where("id",$request->id)->first();
            if($user->rule==="2"){
                throw new \Exception("the user is admin");
            }
            if($user!==null){
                if( $user->profile!==null){
                    $path = $user->profile->path_photo??null;
                }
            }
            $user->tokens()->delete();
            $user->delete();
            if($path!==null){
                unlink($path);
            }
            DB::commit();
            return \response()->json([
                "Message" => "Successfully Deleted User"
            ]);
        }catch (\Exception $exception){
            DB::rollBack();
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

    public function UpdateUser(Request $request): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {
            $validate = Validator::make(["id" => $request->id],[
                "id" => ["required",Rule::exists("users","id")]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $user = User::all()->where("id",$request->id)->first();
            if($user->rule==="2"){
                throw new \Exception("the user is admin");
            }
            $validate = Validator::make($request->all(),[
                "name" => ["nullable","string"],
                "email" => ["nullable",Rule::unique("users","email")->ignore($user->id)],
                "password" => ["nullable","min:8"],
                "gender" => ["nullable",Rule::in(["female","male"])],
                "path_photo" => ["nullable",'mimes:jpeg,png,jpg'],
                "age" => ["nullable","date"],
                "phone" => ["nullable","min:10","numeric"]
            ]);
            if($validate->fails())
            {
                return \response()->json([
                    "Error" => $validate->errors()
                ],401);
            }
            $profile = $user->profile;
            $newPhoto = null;
            $photo = $request->file("path_photo") ?? null;
            if($photo !==null){
                if($photo->isValid()){
                    $newPhoto = time().$photo->getClientOriginalName();
                    $newPhoto = 'uploads/Users/'.$newPhoto;
                }
            }
            $user->update([
                "name" => $request->name ?? $user->name,
                "email" => $request->email ?? $user->email,
                "password" => password_hash($request->password,PASSWORD_DEFAULT) ?? $user->password,
            ]);
            if($profile!==null){
                if($newPhoto!==null&&$profile->path_photo!==null){
                    unlink($profile->path_photo);
                }
                $user->profile()
                    ->update([
                        "path_photo"=>  $newPhoto ?? $profile->path_photo,
                        "gender" => $request->gender ?? $profile->gender,
                        "age" => $request->age ?? $profile->age,
                        "phone" => $request->phone ?? $profile->phone
                    ]);
            }else{
                $user->profile()
                    ->create([
                        "path_photo"=>  $newPhoto,
                        "gender" => $request->gender,
                        "age" => $request->age,
                        "phone" => $request->phone
                    ]);
            }
            if($newPhoto!==null){
                $photo->move('uploads/Users',$newPhoto);
            }
            DB::commit();
            return \response()->json([
                "Message" => "Successfully Update Profile"
            ]);
        }catch (\Exception $exception){
            DB::rollBack();
            return \response()->json([
                "Error" => $exception->getMessage()
            ],401);
        }
    }

}
