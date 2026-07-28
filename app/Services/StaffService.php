<?php

namespace App\Services;

use App\IService\IStaffService;
use App\Models\User;
use App\Traits\FileTrait;
use Illuminate\Http\UploadedFile;

class StaffService implements IStaffService
{
    use FileTrait;

    public function updateStaff(User $user, array $data): User
    {
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($user->image) {
                $this->deleteFile($user->image);
            }
            $data['image'] = $this->saveFile($data['image'], 'users/images');
        }

        $user->update($data);
        if ($roleId) {
            $user->syncRoles([(int) $roleId]);
        }

        return $user->load('roles.permissions');
    }

    public function createStaff(array $data): User
    {
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->saveFile($data['image'], 'users/images');
        }

        $user = User::create($data);

        if ($roleId) {
            $user->syncRoles([(int) $roleId]);
        }

        return $user->load('roles.permissions');
    }

    public function assignRoleToUser(User $user, int $roleId): void
    {
        $user->syncRoles([(int) $roleId]);
    }

    public function getStaffById(int $id): ?User
    {
        return User::with('roles.permissions')->find($id);
    }

    public function deleteStaff(int $id): bool
    {
        $user = User::find($id);

        if (! $user || $user->projects()->exists() || $user->circles()->exists()) {
            return false;
        }

        if ($user->image) {
            $this->deleteFile($user->image);
        }

        return $user->delete();
    }

    public function getAllStaff(?string $name = null, $limit = null)
    {
        $query = User::with('roles.permissions')
            ->when($name, function ($query, $name) {
                return $query->where(function ($q) use ($name) {
                    $q->where('first_name', 'LIKE', '%'.$name.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$name.'%');
                });
            })
            ->orderBy('id', 'desc');

        return $limit ? $query->paginate($limit) : $query->get();
    }
}
