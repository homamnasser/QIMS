<?php

namespace App\Services;

use App\Enums\StaffWorkScope;
use App\IService\IStaffService;
use App\Models\User;
use App\Traits\FileTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class StaffService implements IStaffService
{
    use FileTrait;

    public function updateStaff(User $user, array $data): User
    {
        $data = $this->enforceActorScope($data);
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (($data['work_scope'] ?? null) === StaffWorkScope::Institute->value) {
            $data['mosque_id'] = null;
        }

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

        return $user->load(['roles.permissions', 'mosque']);
    }

    public function createStaff(array $data): User
    {
        $data = $this->enforceActorScope($data);
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (($data['work_scope'] ?? null) === StaffWorkScope::Institute->value) {
            $data['mosque_id'] = null;
        }

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->saveFile($data['image'], 'users/images');
        }

        $user = User::create($data);

        if ($roleId) {
            $user->syncRoles([(int) $roleId]);
        }

        return $user->load(['roles.permissions', 'mosque']);
    }

    public function assignRoleToUser(User $user, int $roleId): void
    {
        $user->syncRoles([(int) $roleId]);
    }

    public function getStaffById(int $id): ?User
    {
        return $this->visibleStaffQuery()
            ->with(['roles.permissions', 'mosque'])
            ->find($id);
    }

    public function deleteStaff(int $id): bool
    {
        $user = $this->visibleStaffQuery()->find($id);

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
        $query = $this->visibleStaffQuery()
            ->with(['roles.permissions', 'mosque'])
            ->when($name, function ($query, $name) {
                return $query->where(function ($q) use ($name) {
                    $q->where('first_name', 'LIKE', '%'.$name.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$name.'%');
                });
            })
            ->orderBy('id', 'desc');

        return $limit ? $query->paginate($limit) : $query->get();
    }

    private function visibleStaffQuery(): Builder
    {
        $query = User::query();
        $actor = Auth::user();

        if ($actor instanceof User && $actor->isMosqueScoped()) {
            $query
                ->where('work_scope', StaffWorkScope::Mosque->value)
                ->where('mosque_id', $actor->mosque_id);
        }

        return $query;
    }

    private function enforceActorScope(array $data): array
    {
        $actor = Auth::user();

        if ($actor instanceof User && $actor->isMosqueScoped()) {
            $data['work_scope'] = StaffWorkScope::Mosque->value;
            $data['mosque_id'] = $actor->mosque_id;
        }

        return $data;
    }
}
