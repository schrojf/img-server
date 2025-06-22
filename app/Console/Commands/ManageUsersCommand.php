<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ManageUsersCommand extends Command
{
    protected $signature = 'user:manage
                            {action : Action to perform (create|list|show|update|delete|activate|deactivate)}
                            {--id= : User ID for show, update, delete, activate, deactivate actions}
                            {--name= : User name}
                            {--email= : User email}
                            {--password= : User password}
                            {--role= : User role}
                            {--verified : Mark email as verified}
                            {--per-page=10 : Number of users per page for list}
                            {--page=1 : Page number for list}
                            {--search= : Search term for filtering users}';

    protected $description = 'Manage users via CLI';

    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createUser(),
            'list' => $this->listUsers(),
            'show' => $this->showUser(),
            'update' => $this->updateUser(),
            'delete' => $this->deleteUser(),
            'activate' => $this->activateUser(),
            'deactivate' => $this->deactivateUser(),
            default => $this->error("Invalid action: {$action}")
        };
    }

    private function createUser()
    {
        $data = $this->gatherUserData();

        if (! $data) {
            return Command::FAILURE;
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return Command::FAILURE;
        }

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        if ($this->option('verified')) {
            $userData['email_verified_at'] = now();
        }

        $user = User::create($userData);

        $this->info('User created successfully!');
        $this->displayUser($user);

        return Command::SUCCESS;
    }

    private function listUsers()
    {
        $perPage = (int) $this->option('per-page');
        $page = (int) $this->option('page');
        $search = $this->option('search');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($users->isEmpty()) {
            $this->info('No users found.');

            return Command::SUCCESS;
        }

        $this->info("Users (Page {$users->currentPage()} of {$users->lastPage()}):");
        $this->line('');

        $headers = ['ID', 'Name', 'Email', 'Verified', 'Created'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->email_verified_at ? 'Yes' : 'No',
                $user->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);

        $this->info("Showing {$users->count()} of {$users->total()} users");

        return Command::SUCCESS;
    }

    private function showUser()
    {
        $id = $this->option('id') ?? $this->ask('Enter user ID');

        if (! $id) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($id);

        if (! $user) {
            $this->error("User with ID {$id} not found");

            return Command::FAILURE;
        }

        $this->displayUser($user);

        return Command::SUCCESS;
    }

    private function updateUser()
    {
        $id = $this->option('id') ?? $this->ask('Enter user ID');

        if (! $id) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($id);

        if (! $user) {
            $this->error("User with ID {$id} not found");

            return Command::FAILURE;
        }

        $this->info('Current user details:');
        $this->displayUser($user);
        $this->line('');

        $updates = [];

        $name = $this->option('name') ?? $this->ask('Enter new name (leave empty to keep current)', $user->name);
        if ($name !== $user->name) {
            $updates['name'] = $name;
        }

        $email = $this->option('email') ?? $this->ask('Enter new email (leave empty to keep current)', $user->email);
        if ($email !== $user->email) {
            $updates['email'] = $email;
        }

        $password = $this->option('password') ?? $this->secret('Enter new password (leave empty to keep current)');
        if ($password) {
            $updates['password'] = Hash::make($password);
        }

        if (empty($updates)) {
            $this->info('No changes made.');

            return Command::SUCCESS;
        }

        $validator = Validator::make($updates, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', Password::defaults()],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return Command::FAILURE;
        }

        $user->update($updates);

        $this->info('User updated successfully!');
        $this->displayUser($user->fresh());

        return Command::SUCCESS;
    }

    private function deleteUser()
    {
        $id = $this->option('id') ?? $this->ask('Enter user ID');

        if (! $id) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($id);

        if (! $user) {
            $this->error("User with ID {$id} not found");

            return Command::FAILURE;
        }

        $this->info('User to delete:');
        $this->displayUser($user);

        if (! $this->confirm('Are you sure you want to delete this user?')) {
            $this->info('User deletion cancelled.');

            return Command::SUCCESS;
        }

        $user->delete();

        $this->info('User deleted successfully!');

        return Command::SUCCESS;
    }

    private function activateUser()
    {
        $id = $this->option('id') ?? $this->ask('Enter user ID');

        if (! $id) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($id);

        if (! $user) {
            $this->error("User with ID {$id} not found");

            return Command::FAILURE;
        }

        if ($user->email_verified_at) {
            $this->info('User is already activated.');

            return Command::SUCCESS;
        }

        $user->update(['email_verified_at' => now()]);

        $this->info('User activated successfully!');
        $this->displayUser($user->fresh());

        return Command::SUCCESS;
    }

    private function deactivateUser()
    {
        $id = $this->option('id') ?? $this->ask('Enter user ID');

        if (! $id) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($id);

        if (! $user) {
            $this->error("User with ID {$id} not found");

            return Command::FAILURE;
        }

        if (! $user->email_verified_at) {
            $this->info('User is already deactivated.');

            return Command::SUCCESS;
        }

        $user->update(['email_verified_at' => null]);

        $this->info('User deactivated successfully!');
        $this->displayUser($user->fresh());

        return Command::SUCCESS;
    }

    private function gatherUserData(): ?array
    {
        $name = $this->option('name') ?? $this->ask('Enter user name');
        $email = $this->option('email') ?? $this->ask('Enter user email');
        $password = $this->option('password') ?? $this->secret('Enter user password');

        if (! $name || ! $email || ! $password) {
            $this->error('Name, email, and password are required.');

            return null;
        }

        return compact('name', 'email', 'password');
    }

    private function displayUser(User $user): void
    {
        $this->line('User Details:');
        $this->line("  ID: {$user->id}");
        $this->line("  Name: {$user->name}");
        $this->line("  Email: {$user->email}");
        $this->line('  Email Verified: '.($user->email_verified_at ? 'Yes ('.$user->email_verified_at->format('Y-m-d H:i:s').')' : 'No'));
        $this->line("  Created: {$user->created_at->format('Y-m-d H:i:s')}");
        $this->line("  Updated: {$user->updated_at->format('Y-m-d H:i:s')}");
        $this->line('  Active Tokens: '.$user->tokens()->count());
    }
}
