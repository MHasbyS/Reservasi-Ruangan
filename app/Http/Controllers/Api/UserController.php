<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function index(Request $request)
{
    try {
        $validated = $request->validate([
            'name'       => 'nullable|string',
            'role'       => 'nullable|string|in:admin,karyawan',
            'sort_by'    => 'nullable|string|in:created_at,role,name',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page'   => 'nullable', // Validasi manual di service atau gunakan rule kustom
            'page'       => 'nullable|integer|min:1',
        ]);

        $users = $this->userService->filterUser($validated);

        // Jika menggunakan Resource Collection, metadata paginasi bisa otomatis atau distandarisasi
        return response()->json([
            'success' => true,
            'message' => 'Berhasil memanggil data',
            'pagination' => $this->formatPagination($users, $validated['per_page'] ?? null),
            'data' => UserResource::collection($users),
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal memanggil data user',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

/**
 * Helper untuk standarisasi format pagination agar Vue lebih mudah membacanya
 */
private function formatPagination($resource, $perPage)
{
    if ($perPage === 'all') {
        return [
            'per_page'     => 'all',
            'current_page' => 1,
            'last_page'    => 1,
            'total'        => $resource->count(),
        ];
    }

    return [
        'per_page'     => $resource->perPage(),
        'current_page' => $resource->currentPage(),
        'last_page'    => $resource->lastPage(),
        'total'        => $resource->total(),
    ];
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
