<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class ManageTokensCommand extends Command
{
    protected $signature = 'manage:tokens
        {action? : create|list|show|revoke|revoke-all|prune}
        {--user= : User email or ID}
        {--name= : Token name}
        {--abilities=* : Token abilities}
        {--token-id= : Token ID}
        {--force : Skip confirmations}';

    protected $description = 'Manage Laravel Sanctum API tokens';

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
            $this->displayAvailableActions();

            return self::FAILURE;
        }

        if (! array_key_exists($action, $this->availableActions)) {
            $this->error("Invalid action: {$action}");
            $this->displayAvailableActions();

            return self::FAILURE;
        }

        return match ($action) {
            'create' => $this->createToken(),
            'list' => $this->listTokens(),
            'show' => $this->showToken(),
            'revoke' => $this->revokeToken(),
            'revoke-all' => $this->revokeAllTokens(),
            'prune' => $this->pruneTokens(),
        };
    }

    protected function displayAvailableActions(): void
    {
        $this->error('Missing or invalid action.');
        $this->newLine();
        $this->info('Available actions:');
        foreach ($this->availableActions as $a => $desc) {
            $this->line("  <fg=green>{$a}</> — {$desc}");
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

    protected function selectUser(string $prompt): ?User
    {
        $input = $this->option('user') ?: $this->ask("$prompt (email or ID)");

        if (! $input) {
            $this->error('User identifier is required.');

            return null;
        }

        $user = is_numeric($input)
            ? User::find((int) $input)
            : User::where('email', $input)->first();

        if (! $user) {
            $this->error("No user found for '{$input}'.");

            return null;
        }

        return $user;
    }

    protected function askWithRetry(string $question, int $maxAttempts = 3): ?string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $suffix = $i > 0 ? ' (attempt '.($i + 1)."/{$maxAttempts})" : '';
            $value = $this->ask($question.$suffix);

            if (! empty($value)) {
                return $value;
            }

            if ($i < $maxAttempts - 1) {
                $this->warn('Input cannot be empty.');
            }
        }

        $this->error('Maximum attempts reached.');

        return null;
    }

    protected function createToken(): int
    {
        $this->info('▶️ Creating new API Token');
        $user = $this->selectUser('Select user');
        if (! $user) {
            return self::FAILURE;
        }

        $name = $this->option('name')
            ?: $this->ask('Enter token name', 'API Token');

        $name = trim($name);
        if ($name === '') {
            $name = 'API Token';
        }

        $abilities = $this->getAbilities();

        $expiresAt = null;
        if ($this->confirm('Do you want to set an expiration date?', false)) {
            $expiresAt = $this->getExpirationDate();
            if ($expiresAt === false) {
                return self::FAILURE;
            }
        }

        try {
            $token = $user->createToken($name, $abilities, $expiresAt);

            $this->info('✅ Token Created!');
            $this->displayTokenInfo($token->accessToken, $token->plainTextToken);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function listTokens(): int
    {
        $input = $this->option('user');

        if ($input) {
            $user = is_numeric($input)
                ? User::find((int) $input)
                : User::where('email', $input)->first();

            if (! $user) {
                $this->error("User not found with identifier: {$input}");

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

        $expiredCount = $tokens->where('expires_at', '<', now())->count();
        if ($expiredCount > 0) {
            $this->warn("⚠️  {$expiredCount} expired token(s) found. Run 'php artisan token:manage prune' to remove them.");
        }

        return self::SUCCESS;
    }

    protected function showToken(): int
    {
        $tokenId = $this->option('token-id') ?: $this->askWithRetry('Enter token ID');
        if (! $tokenId || ! is_numeric($tokenId)) {
            $this->error('Valid token ID is required.');

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
        $tokenId = $this->option('token-id') ?: $this->askWithRetry('Enter token ID to revoke');
        if (! $tokenId || ! is_numeric($tokenId)) {
            $this->error('Valid token ID is required.');

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

        return empty($abilities)
            ? $this->defaultAbilities
            : array_map('trim', explode(',', $abilities));
    }

    protected function getExpirationDate(): \DateTime|false|null
    {
        $this->info('Expiration options:');
        $this->line('1. Never expires (default)');
        $this->line('2. Expires in X days');
        $this->line('3. Expires on specific date (YYYY-MM-DD)');

        return match ($this->choice('Choose expiration type', ['never', 'days', 'date'], 'never')) {
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

            if (! $expirationDate || $expirationDate < now()) {
                $this->error('Invalid or past date.');

                return false;
            }

            return $expirationDate;
        } catch (\Exception) {
            $this->error('Please enter a valid date.');

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
            ['Status', $token->expires_at && $token->expires_at < now() ? '❌ Expired' : '✅ Active'],
        ];

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
