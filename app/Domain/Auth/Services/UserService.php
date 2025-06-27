<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\DTOs\CreateUserDTO;
use App\Domain\Auth\DTOs\UpdateUserDTO;
use App\Domain\Member\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function createUser(CreateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'phone' => $dto->phone,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            if ($dto->roles) {
                $user->assignRole($dto->roles);
            }

            Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);

            return $user;
        });
    }

    public function updateUser(int $id, UpdateUserDTO $dto): User
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = User::findOrFail($id);

            $updateData = array_filter([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'updated_by' => auth()->id(),
            ], fn($value) => $value !== null);

            if ($dto->password) {
                $updateData['password'] = Hash::make($dto->password);
            }

            $user->update($updateData);

            Log::info('User updated', ['user_id' => $id, 'updated_fields' => array_keys($updateData)]);

            return $user;
        });
    }

    public function createMemberUser(Member $member, string $password): User
    {
        return $this->createUser(new CreateUserDTO(
            name: $member->name,
            email: $member->email,
            password: $password,
            phone: $member->phone,
            roles: ['member']
        ));
    }
}
