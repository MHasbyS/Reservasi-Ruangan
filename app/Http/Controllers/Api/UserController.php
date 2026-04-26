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
// use Illuminate\Http\Response;
use App\Services\UserService;

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
    public function index(GetUserRequest $request)
    {
        $users = User::search($request->search)->latest()->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($users, UserResource::class),
            'Berhasil memanggil data',
        );
    }

    /**
     * Display a listing of user options.
     */
    public function options(GetUserRequest $request)
    {
        $users = User::select('id', 'name')->search($request->search)->orderBy('name')->get();

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
        try {
            $user = $this->userService->storeUser($request->validated());

            return ApiResponse::success(
                new UserResource($user),
                'Berhasil menambahkan user',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $status = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

            return ApiResponse::error(
                $e->getMessage(),
                $status,
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = $this->userService->showUserById($id);

            return ApiResponse::success(
                new UserResource($user),
                'Berhasil memanggil detail user',
            );
        } catch (\Exception $e) {
            $status = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

            return ApiResponse::error(
                $e->getMessage(),
                $status,
            );
        }
    }

    /**
     * Display a listing of roles.
     */
    public function getRoles()
    {
        try {
            $roles = User::getRoleNames();

            return ApiResponse::success(
                $roles,
                'Berhasil mengambil data role',
            );
        } catch (\Exception $e) {
            $status = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

            return ApiResponse::error(
                $e->getMessage(),
                $status,
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        try {
            $user = $this->userService->updateUser($id, $request->validated());

            return ApiResponse::success(
                new UserResource($user),
                'Berhasil mengubah user',
            );
        } catch (\Exception $e) {
            $status = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

            return ApiResponse::error(
                $e->getMessage(),
                $status,
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->userService->deleteUser($id);

            return ApiResponse::success(
                null,
                'Berhasil menghapus user',
            );
        } catch (\Exception $e) {
            $status = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

            return ApiResponse::error(
                $e->getMessage(),
                $status,
            );
        }
    }
}
