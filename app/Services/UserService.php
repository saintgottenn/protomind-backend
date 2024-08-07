<?php

namespace App\Services;

use App\Enums\RolesEnum;
use App\Http\Filters\UserFilter;
use App\Mail\ConfirmEmailMail;
use App\Models\ManagerSecretary;
use App\Models\User;
use App\Notifications\ConfirmEmailNotification;
use App\Scopes\UserScope;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class UserService
{
    private const ERROR_MESSAGES = [
        'unexpectedRole' => 'Unexpected role',
    ];

    public function getAll(array $data, UserFilter $filter): LengthAwarePaginator
    {
        $user = auth()->user();

        $limit = $data['limit'] ?? config('constants.paginator.limit');

        $query = User::filter($filter);

        if($user->hasRole(RolesEnum::SECRETARY->value)) {
            $query = $query->whereHas('roles', function($q) {
                $q->whereNot('name', 'admin');
            });
        }

        if($user->hasRole(RolesEnum::MANAGER->value)) {
            $secretaryQuery = $user->secretaries();

            if (isset($data['with_blocked']) && filter_var($data['with_blocked'], FILTER_VALIDATE_BOOLEAN)) {
                $secretaryQuery->withoutGlobalScope(UserScope::class);
            }

            $secretaryIds = $secretaryQuery->pluck('secretary_id')->toArray();

            $query->whereIn('id', $secretaryIds);
        }

        if($user->hasRole(RolesEnum::ADMIN->value)) {
            $query = $query->whereNot('id', $user->id);
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * @param array $data
     * @return User
     * @throws Exception
     */
    public function create(array $data): User
    {
        $user = auth()->user();

        if(isset($data['external']) && $data['external']) {
            return $this->createExternal($data);
        }

        if ($user->hasRole([RolesEnum::ADMIN->value, RolesEnum::MANAGER->value])) {
            $originalPassword = $data['password'];

            $data['password'] = Hash::make($data['password']);
            $newUser = User::create($data);

            $role = Role::firstOrCreate(['name' => RolesEnum::SECRETARY->value, 'guard_name' => 'api']);

            if ($user->hasRole('admin')) {
                $role = Role::firstOrCreate(['name' => RolesEnum::MANAGER->value, 'guard_name' => 'api']);
            }

            if($user->hasRole('manager')) {
                ManagerSecretary::create([
                    'manager_id' => $user->id,
                    'secretary_id' => $newUser->id,
                ]);
            }

            if(isset($data['avatar']) && $data['avatar']) {
                $newUser->addMedia($data['avatar'])->toMediaCollection('avatar');
            }

            $newUser->notify(new ConfirmEmailNotification($originalPassword));

            return $newUser->assignRole($role->name);
        }

        throw new Exception(self::ERROR_MESSAGES['unexpectedRole']);
    }

    /**
     * @param array $data
     * @return User
     */
    public function createExternal(array $data): User
    {
        if(isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $role = Role::firstOrCreate(['name' => RolesEnum::EXTERNAL->value, 'guard_name' => 'api']);

        return User::create($data)->assignRole($role->name);
    }
}
