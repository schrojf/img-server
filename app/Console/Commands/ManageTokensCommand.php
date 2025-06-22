<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class ManageTokensCommand extends Command
{
    protected $signature = 'manage:tokens {action?} {--user=} {--name=} {--abilities=*} {--token-id=} {--force}';

    protected $description = 'Manage Laravel Sanctum API tokens (create, list, show, revoke, revoke-all, prune)';

    protected array $availableActions = [
        'create' => 'Create a new API token for a user',
        'list' => 'List API tokens (all or by user)',
        'show' => 'Show token details',
        'revoke' => 'Revoke a specific token',
        'revoke-all' => 'Revoke all tokens for a user',
        'prune' => 'Remove expired tokens from database',
    ];

    protected array $defaultAbilities = ['*'];

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
            'create' => $this->createToken(),
            'list' => $this->listTokens(),
            'show' => $this->showToken(),
            'revoke' => $this->revokeToken(),
            'revoke-all' => $this->revokeAllTokens(),
            'prune' => $this->pruneTokens(),
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
        $this->line('  php artisan token:manage create --user=user@example.com');
        $this->line('  php artisan token:manage list');
        $this->line('  php artisan token:manage list --user=user@example.com');
        $this->line('  php artisan token:manage show --token-id=1');
        $this->line('  php artisan token:manage revoke --token-id=1');
        $this->line('  php artisan token:manage revoke-all --user=user@example.com');
        $this->line('  php artisan token:manage prune');
    }

    protected function createToken(): int
    {
        $this->info('Creating a new API token...');
        $this->newLine();

        // Get user
        $user = $this->selectUser('Select user to create token for');
        if (! $user) {
            return self::FAILURE;
        }

        // Get token name with retry mechanism
        $tokenName = $this->getValidatedInput('name', 'Enter token name', ['required', 'string', 'max:255']);
        if ($tokenName === null) {
            return self::FAILURE;
        }

        // Get abilities
        $abilities = $this->getAbilities();

        // Ask for expiration
        $expiresAt = null;
        if ($this->confirm('Set expiration date?', false)) {
            $expiresAt = $this->getExpirationDate();
            if ($expiresAt === false) {
                return self::FAILURE;
            }
        }

        try {
            $token = $user->createToken($tokenName, $abilities, $expiresAt);

            $this->newLine();
            $this->info('✅ API token created successfully!');
            $this->displayTokenInfo($token->accessToken, $token->plainTextToken);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create token: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function listTokens(): int
    {
        $userEmail = $this->option('user');

        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            if (! $user) {
                $this->error("User not found with email: {$userEmail}");

                return self::FAILURE;
            }
            $tokens = $user->tokens()->latest()->get();
            $this->info("API tokens for user: {$user->name} ({$user->email})");
        } else {
            $tokens = PersonalAccessToken::with('tokenable')->latest()->get();
            $this->info('All API tokens:');
        }

        if ($tokens->isEmpty()) {
            $this->info('No tokens found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['ID', 'Name', 'User', 'Abilities', 'Last Used', 'Expires', 'Created'],
            $tokens->map(fn ($token) => [
                $token->id,
                $token->name,
                $token->tokenable ? "{$token->tokenable->name} ({$token->tokenable->email})" : 'N/A',
                $this->formatAbilities($token->abilities),
                $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i:s') : 'Never',
                $token->expires_at ? $token->expires_at->format('Y-m-d H:i:s') : 'Never',
                $token->created_at->format('Y-m-d H:i:s'),
            ])
        );

        $this->info("Total tokens: {$tokens->count()}");

        // Show expired tokens count
        $expiredCount = $tokens->where('expires_at', '<', now())->count();
        if ($expiredCount > 0) {
            $this->warn("⚠️  {$expiredCount} expired token(s) found. Run 'php artisan token:manage prune' to remove them.");
        }

        return self::SUCCESS;
    }

    protected function showToken(): int
    {
        $tokenId = $this->option('token-id') ?: $this->ask('Enter token ID');

        if (! $tokenId) {
            $this->error('Token ID is required.');

            return self::FAILURE;
        }

        $token = PersonalAccessToken::with('tokenable')->find($tokenId);

        if (! $token) {
            $this->error("Token not found with ID: {$tokenId}");

            return self::FAILURE;
        }

        $this->displayTokenInfo($token);

        return self::SUCCESS;
    }

    protected function revokeToken(): int
    {
        $tokenId = $this->option('token-id') ?: $this->ask('Enter token ID to revoke');

        if (! $tokenId) {
            $this->error('Token ID is required.');

            return self::FAILURE;
        }

        $token = PersonalAccessToken::with('tokenable')->find($tokenId);

        if (! $token) {
            $this->error("Token not found with ID: {$tokenId}");

            return self::FAILURE;
        }

        $this->warn("You are about to revoke token: {$token->name} (ID: {$token->id})");
        if ($token->tokenable) {
            $this->info("User: {$token->tokenable->name} ({$token->tokenable->email})");
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to revoke this token?', false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $token->delete();
            $this->info('✅ Token revoked successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to revoke token: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function revokeAllTokens(): int
    {
        $user = $this->selectUser('Select user to revoke all tokens for');
        if (! $user) {
            return self::FAILURE;
        }

        $tokensCount = $user->tokens()->count();

        if ($tokensCount === 0) {
            $this->info("User {$user->name} has no tokens to revoke.");

            return self::SUCCESS;
        }

        $this->warn("You are about to revoke ALL {$tokensCount} token(s) for user: {$user->name} ({$user->email})");

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to revoke all tokens?', false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $user->tokens()->delete();
            $this->info("✅ All {$tokensCount} token(s) revoked successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to revoke tokens: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function pruneTokens(): int
    {
        $expiredTokens = PersonalAccessToken::where('expires_at', '<', now())->get();

        if ($expiredTokens->isEmpty()) {
            $this->info('No expired tokens found.');

            return self::SUCCESS;
        }

        $count = $expiredTokens->count();
        $this->warn("Found {$count} expired token(s).");

        if (! $this->option('force') && ! $this->confirm("Remove all {$count} expired token(s)?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            PersonalAccessToken::where('expires_at', '<', now())->delete();
            $this->info("✅ {$count} expired token(s) removed successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to prune tokens: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function selectUser(string $message): ?User
    {
        $email = $this->option('user') ?: $this->ask($message.' (enter email)');

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

    protected function getAbilities(): array
    {
        $abilitiesInput = $this->option('abilities');

        if (! empty($abilitiesInput)) {
            return $abilitiesInput;
        }

        $this->info('Token abilities (permissions):');
        $this->line('Examples: create-posts, read-users, update-profile, delete-comments');
        $this->line('Use "*" for all abilities (default)');

        $abilities = $this->ask('Enter abilities (comma-separated, or press Enter for "*")');

        if (empty($abilities)) {
            return $this->defaultAbilities;
        }

        return array_map('trim', explode(',', $abilities));
    }

    protected function getExpirationDate(): \DateTime|false|null
    {
        $this->info('Expiration options:');
        $this->line('1. Never expires (default)');
        $this->line('2. Expires in X days');
        $this->line('3. Expires on specific date (YYYY-MM-DD)');

        $choice = $this->choice('Choose expiration type', ['never', 'days', 'date'], 'never');

        return match ($choice) {
            'never' => null,
            'days' => $this->getExpirationInDays(),
            'date' => $this->getExpirationByDate(),
            default => null,
        };
    }

    protected function getExpirationInDays(): \DateTime|false
    {
        $days = $this->ask('Enter number of days until expiration');

        if (! is_numeric($days) || $days < 1) {
            $this->error('Please enter a valid number of days (minimum 1).');

            return false;
        }

        return now()->addDays((int) $days);
    }

    protected function getExpirationByDate(): \DateTime|false
    {
        $date = $this->ask('Enter expiration date (YYYY-MM-DD)');

        try {
            $expirationDate = \DateTime::createFromFormat('Y-m-d', $date);

            if (! $expirationDate) {
                throw new \Exception('Invalid date format');
            }

            if ($expirationDate < now()) {
                $this->error('Expiration date cannot be in the past.');

                return false;
            }

            return $expirationDate;
        } catch (\Exception $e) {
            $this->error('Please enter a valid date in YYYY-MM-DD format.');

            return false;
        }
    }

    protected function formatAbilities(array $abilities): string
    {
        if (in_array('*', $abilities)) {
            return '*';
        }

        return implode(', ', array_slice($abilities, 0, 3)).(count($abilities) > 3 ? '...' : '');
    }

    protected function displayTokenInfo(PersonalAccessToken $token, ?string $plainTextToken = null): void
    {
        $this->newLine();

        $data = [
            ['ID', $token->id],
            ['Name', $token->name],
            ['Abilities', implode(', ', $token->abilities)],
            ['User', $token->tokenable ? "{$token->tokenable->name} ({$token->tokenable->email})" : 'N/A'],
            ['Last Used', $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i:s') : 'Never'],
            ['Expires', $token->expires_at ? $token->expires_at->format('Y-m-d H:i:s') : 'Never'],
            ['Created', $token->created_at->format('Y-m-d H:i:s')],
            ['Updated', $token->updated_at->format('Y-m-d H:i:s')],
        ];

        // Add expiration status
        if ($token->expires_at) {
            $isExpired = $token->expires_at < now();
            $data[] = ['Status', $isExpired ? '❌ Expired' : '✅ Active'];
        } else {
            $data[] = ['Status', '✅ Active'];
        }

        $this->table(['Field', 'Value'], $data);

        if ($plainTextToken) {
            $this->newLine();
            $this->warn('🔑 API Token (save this as it won\'t be shown again):');
            $this->line("<fg=yellow>{$plainTextToken}</>");
            $this->newLine();
            $this->info('📋 Usage example:');
            $this->line('curl -H "Authorization: Bearer '.$plainTextToken.'" '.config('app.url').'/api/user');
        }
    }
}
