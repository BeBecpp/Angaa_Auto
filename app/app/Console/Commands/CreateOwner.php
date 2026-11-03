<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Enums\UserType;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps the owner staff account. The password is always prompted (hidden); never passed as an argument.
 */
#[Signature('app:owner-create {--email= : Owner login email} {--name= : Display name}')]
#[Description('Create (or reset) the owner staff account with a prompted password')]
class CreateOwner extends Command
{
    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Имэйл (нэвтрэх)');
        $name = $this->option('name') ?: $this->ask('Нэр', 'Ангаа');
        $password = $this->secret('Нууц үг (10+ тэмдэгт, үсэг ба тоо)');
        $confirm = $this->secret('Нууц үг давтах');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email'], 'name' => ['required', 'string', 'max:80'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first() ?? new User;

        if ($user->exists && ! $user->isStaff()) {
            $this->error('Энэ имэйл үйлчлүүлэгчийн бүртгэлд ашиглагдаж байна.');

            return self::FAILURE;
        }

        $user->fill(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['type' => UserType::Staff, 'staff_role' => StaffRole::Owner, 'active' => true])->save();
        $user->staffProfile()->updateOrCreate([], ['display_name' => $name, 'bookable' => false, 'capabilities' => ['qc']]);

        Audit::record('staff.owner_bootstrap', $user, actorId: $user->id);
        $this->info("Эзэн бүртгэгдлээ: {$email}. /admin руу нэвтэрнэ үү.");

        return self::SUCCESS;
    }
}
