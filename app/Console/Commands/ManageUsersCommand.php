<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ManageUsersCommand extends Command
{
    protected $signature = 'manage:users {action?} {--email=} {--name=} {--password=} {--force}';

    protected $description = 'Manage users (create, list, show, update, delete, reset-password)';

    protected array $availableActions = [
        'create' => 'Create a new user',
        'list' => 'List all users',
        'show' => 'Show user details',
        'update' => 'Update user information',
        'delete' => 'Delete a user',
        'reset-password' => 'Reset user password',
    ];

    public function handle(): int
    {
        $action = $this->argument('action');

        if (! $action) {
            $this->showAvailableActions();

            return self::FAILURE;
        }

        if (! array_key_exists($action, $this->availableActions)) {
            $this->error("Invalid action: {$action}");
            $this->showAvailableActions();

            return self::FAILURE;
        }

        return match ($action) {
            'create' => $this->createUser(),
            'list' => $this->listUsers(),
            'show' => $this->showUser(),
            'update' => $this->updateUser(),
            'delete' => $this->deleteUser(),
            'reset-password' => $this->resetPassword(),
            default => self::FAILURE,
        };
    }

    protected function showAvailableActions(): void
    {
        $this->error('Not enough arguments (missing: "action").');
        $this->newLine();
        $this->info('Available actions:');

        foreach ($this->availableActions as $action => $description) {
            $this->line("  <fg=green>{$action}</> - {$description}");
        }

        $this->newLine();
        $this->info('Usage examples:');
        $this->line('  php artisan user:manage create');
        $this->line('  php artisan user:manage list');
        $this->line('  php artisan user:manage show');
        $this->line('  php artisan user:manage update --email=user@example.com');
        $this->line('  php artisan user:manage delete');
        $this->line('  php artisan user:manage reset-password');
    }

    protected function createUser(): int
    {
        $this->info('Creating a new user...');
        $this->newLine();

        // Get user input with retry mechanism
        $name = $this->getValidatedInput('name', 'Enter name', ['required', 'string', 'max:255']);
        if ($name === null) {
            return self::FAILURE;
        }

        $email = $this->getValidatedInput('email', 'Enter email', ['required', 'email', 'unique:users,email']);
        if ($email === null) {
            return self::FAILURE;
        }

        // Handle password - auto-generate if not provided
        $password = $this->option('password') ?: $this->ask('Enter password (leave empty to auto-generate)');
        $isGenerated = false;

        if (empty($password)) {
            $password = $this->generateSecurePassword();
            $isGenerated = true;
            $this->info("Auto-generated password: <fg=yellow>{$password}</>");
        } else {
            // Validate password if provided
            $passwordValidation = $this->validateInput($password, [Password::min(8)->letters()->mixedCase()->numbers()]);
            while ($passwordValidation !== true) {
                $this->error($passwordValidation);
                $password = $this->askWithRetry('Enter a valid password');
                if ($password === null) {
                    return self::FAILURE;
                }
                $passwordValidation = $this->validateInput($password, [Password::min(8)->letters()->mixedCase()->numbers()]);
            }
        }

        // Ask if email should be verified
        $emailVerified = $this->confirm('Mark email as verified?', true);

        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            if ($emailVerified) {
                $user->email_verified_at = now();
                $user->save();
            }

            $this->newLine();
            $this->info('✅ User created successfully!');
            $this->displayUserInfo($user, $isGenerated ? $password : null);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create user: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function listUsers(): int
    {
        $users = User::orderBy('created_at', 'desc')->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Email Verified', 'Tokens Count', 'Created At'],
            $users->map(fn ($user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->email_verified_at ? '✅ Yes' : '❌ No',
                $user->tokens()->count(),
                $user->created_at->format('Y-m-d H:i:s'),
            ])
        );

        $this->info("Total users: {$users->count()}");

        return self::SUCCESS;
    }

    protected function showUser(): int
    {
        $user = $this->selectUser('Select user to show');
        if (! $user) {
            return self::FAILURE;
        }

        $this->displayUserInfo($user);

        return self::SUCCESS;
    }

    protected function updateUser(): int
    {
        $user = $this->selectUser('Select user to update');
        if (! $user) {
            return self::FAILURE;
        }

        $this->info("Updating user: {$user->name} ({$user->email})");
        $this->newLine();

        $updateData = [];

        // Update name
        if ($this->confirm('Update name?', false)) {
            $name = $this->getValidatedInput('name', "Enter new name (current: {$user->name})", ['required', 'string', 'max:255']);
            if ($name === null) {
                return self::FAILURE;
            }
            $updateData['name'] = $name;
        }

        // Update email
        if ($this->confirm('Update email?', false)) {
            $email = $this->getValidatedInput('email', "Enter new email (current: {$user->email})", ['required', 'email', "unique:users,email,{$user->id}"]);
            if ($email === null) {
                return self::FAILURE;
            }
            $updateData['email'] = $email;
        }

        // Update email verification
        if ($this->confirm('Update email verification status?', false)) {
            $verified = $this->confirm('Mark email as verified?', $user->email_verified_at !== null);
            $user->email_verified_at = $verified ? now() : null;
        }

        try {
            if (! empty($updateData)) {
                $user->update($updateData);
            }
            $user->save(); // Save email_verified_at changes

            $this->info('✅ User updated successfully!');
            $this->displayUserInfo($user);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to update user: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function deleteUser(): int
    {
        $user = $this->selectUser('Select user to delete');
        if (! $user) {
            return self::FAILURE;
        }

        $this->warn("You are about to delete user: {$user->name} ({$user->email})");
        $tokensCount = $user->tokens()->count();
        if ($tokensCount > 0) {
            $this->warn("This user has {$tokensCount} API token(s) that will also be deleted.");
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to delete this user?', false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            // Delete user tokens first
            $user->tokens()->delete();
            $user->delete();

            $this->info('✅ User deleted successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to delete user: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function resetPassword(): int
    {
        $user = $this->selectUser('Select user to reset password');
        if (! $user) {
            return self::FAILURE;
        }

        $this->info("Resetting password for: {$user->name} ({$user->email})");
        $this->newLine();

        // Ask for new password or auto-generate
        $password = $this->ask('Enter new password (leave empty to auto-generate)');
        $isGenerated = false;

        if (empty($password)) {
            $password = $this->generateSecurePassword();
            $isGenerated = true;
            $this->info("Auto-generated password: <fg=yellow>{$password}</>");
        } else {
            // Validate password if provided
            $passwordValidation = $this->validateInput($password, [Password::min(8)->letters()->mixedCase()->numbers()]);
            while ($passwordValidation !== true) {
                $this->error($passwordValidation);
                $password = $this->askWithRetry('Enter a valid password');
                if ($password === null) {
                    return self::FAILURE;
                }
                $passwordValidation = $this->validateInput($password, [Password::min(8)->letters()->mixedCase()->numbers()]);
            }
        }

        try {
            $user->update(['password' => Hash::make($password)]);

            $this->newLine();
            $this->info('✅ Password reset successfully!');

            if ($isGenerated) {
                $this->warn('📋 Make sure to save this password as it won\'t be shown again:');
                $this->line("Password: <fg=yellow>{$password}</>");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to reset password: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function selectUser(string $message): ?User
    {
        $email = $this->option('email') ?: $this->ask($message.' (enter email)');

        if (! $email) {
            $this->error('Email is required.');

            return null;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found with email: {$email}");

            return null;
        }

        return $user;
    }

    protected function getValidatedInput(string $field, string $question, array $rules): ?string
    {
        $value = $this->option($field) ?: $this->ask($question);

        if ($value === null) {
            $this->error("Value is required for {$field}.");

            return null;
        }

        $validation = $this->validateInput($value, $rules);

        while ($validation !== true) {
            $this->error($validation);
            $value = $this->askWithRetry("Enter a valid {$field}");
            if ($value === null) {
                return null;
            }
            $validation = $this->validateInput($value, $rules);
        }

        return $value;
    }

    protected function askWithRetry(string $question, int $maxAttempts = 3): ?string
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $value = $this->ask($question.($attempts > 0 ? ' (attempt '.($attempts + 1)."/{$maxAttempts})" : ''));

            if ($value !== null && $value !== '') {
                return $value;
            }

            $attempts++;
            if ($attempts < $maxAttempts) {
                $this->warn('Value cannot be empty. Please try again.');
            }
        }

        $this->error('Maximum attempts reached. Operation cancelled.');

        return null;
    }

    protected function validateInput(mixed $value, array $rules): string|true
    {
        $validator = Validator::make(['field' => $value], ['field' => $rules]);

        if ($validator->fails()) {
            return $validator->errors()->first('field');
        }

        return true;
    }

    protected function generateSecurePassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        $allChars = $uppercase.$lowercase.$numbers.$symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    protected function displayUserInfo(User $user, ?string $plainPassword = null): void
    {
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Email Verified', $user->email_verified_at ? '✅ '.$user->email_verified_at->format('Y-m-d H:i:s') : '❌ No'],
                ['API Tokens', $user->tokens()->count()],
                ['Created', $user->created_at->format('Y-m-d H:i:s')],
                ['Updated', $user->updated_at->format('Y-m-d H:i:s')],
            ]
        );

        if ($plainPassword) {
            $this->newLine();
            $this->warn('📋 Save this password as it won\'t be shown again:');
            $this->line("Password: <fg=yellow>{$plainPassword}</>");
        }
    }
}
