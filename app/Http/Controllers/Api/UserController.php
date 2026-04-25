<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetUserRequest;
use App\Helpers\ApiResponse;
use App\Http\Resources\PaginatedResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(GetUserRequest $request){
    // try {
        $users = User::search($request->search)->latest()->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($users, UserResource::class),
            'Berhasil memanggil data',
            200
        );

        // } catch (\Exception $e) {
        //     return ApiResponse::error(
        //         'Gagal memanggil data user',
        //         500,
        //         $e->getMessage()
        //     );    
        // }
    }

    public function options(GetUserRequest $request){
        $users = User::select('id','name')->search($request->search)->orderBy('name')->get();

        return ApiResponse::success(
            UserResource::collection($users),
            'User List'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['password'] = bcrypt($data['password']);

            $user = User::create($data);

            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'User berhasil dibuat',
                'data' => new UserResource($user)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat user:',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan'
                ], 404);
            }
            return new UserResource($user);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRoles(){
        try {
            $roles = User::getRoleNames();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengambil data role',
                'data' => $roles
            ]);
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan'
                ], 404);
            }
            $data = $request->validated();

            if (!empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'User berhasil diperbarui',
                'data' => new UserResource($user)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->delete();
        return response()->json([
            'message' => 'User berhasil dihapus'
        ], 200);
    }
}
