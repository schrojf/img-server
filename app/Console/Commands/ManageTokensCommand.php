<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class ManageTokensCommand extends Command
{
    protected $signature = 'token:manage
                            {action : Action to perform (create|list|show|revoke|revoke-all|prune)}
                            {--user-id= : User ID for token operations}
                            {--token-id= : Token ID for specific token operations}
                            {--name= : Token name}
                            {--abilities=* : Token abilities (comma-separated or multiple --abilities)}
                            {--expires-at= : Token expiration date (Y-m-d H:i:s format)}
                            {--per-page=10 : Number of tokens per page for list}
                            {--page=1 : Page number for list}
                            {--search= : Search term for filtering tokens}
                            {--show-token : Show the plain text token (only for create action)}';

    protected $description = 'Manage Laravel Sanctum API tokens via CLI';

    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createToken(),
            'list' => $this->listTokens(),
            'show' => $this->showToken(),
            'revoke' => $this->revokeToken(),
            'revoke-all' => $this->revokeAllTokens(),
            'prune' => $this->pruneTokens(),
            default => $this->error("Invalid action: {$action}")
        };
    }

    private function createToken()
    {
        $userId = $this->option('user-id') ?? $this->ask('Enter user ID');

        if (! $userId) {
            $this->error('User ID is required');

            return Command::FAILURE;
        }

        $user = User::find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found");

            return Command::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Enter token name', 'API Token');

        $abilities = $this->option('abilities');
        if (empty($abilities)) {
            $abilitiesInput = $this->ask('Enter token abilities (comma-separated, * for all)', '*');
            $abilities = $abilitiesInput === '*' ? ['*'] : array_map('trim', explode(',', $abilitiesInput));
        } elseif (count($abilities) === 1 && str_contains($abilities[0], ',')) {
            $abilities = array_map('trim', explode(',', $abilities[0]));
        }

        $expiresAt = $this->option('expires-at');
        if ($expiresAt) {
            try {
                $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $expiresAt);
            } catch (\Exception $e) {
                $this->error('Invalid expiration date format. Use Y-m-d H:i:s format (e.g., 2024-12-31 23:59:59)');

                return Command::FAILURE;
            }
        }

        $token = $user->createToken($name, $abilities, $expiresAt);

        $this->info('Token created successfully!');
        $this->line('');
        $this->displayTokenInfo($token->accessToken);

        if ($this->option('show-token')) {
            $this->line('');
            $this->warn('Plain text token (save this, it won\'t be shown again):');
            $this->line($token->plainTextToken);
        }

        return Command::SUCCESS;
    }

    private function listTokens()
    {
        $userId = $this->option('user-id');
        $perPage = (int) $this->option('per-page');
        $page = (int) $this->option('page');
        $search = $this->option('search');

        $query = PersonalAccessToken::query()->with('tokenable');

        if ($userId) {
            $query->where('tokenable_id', $userId)->where('tokenable_type', User::class);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $tokens = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($tokens->isEmpty()) {
            $this->info('No tokens found.');

            return Command::SUCCESS;
        }

        $this->info("Tokens (Page {$tokens->currentPage()} of {$tokens->lastPage()}):");
        $this->line('');

        $headers = ['ID', 'Name', 'User', 'Abilities', 'Last Used', 'Expires', 'Created'];
        $rows = [];

        foreach ($tokens as $token) {
            $rows[] = [
                $token->id,
                $token->name,
                $token->tokenable?->email ?? 'N/A',
                implode(', ', $token->abilities ?: ['*']),
                $token->last_used_at?->format('Y-m-d H:i') ?? 'Never',
                $token->expires_at?->format('Y-m-d H:i') ?? 'Never',
                $token->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);

        $this->info("Showing {$tokens->count()} of {$tokens->total()} tokens");

        return Command::SUCCESS;
    }

    private function showToken()
    {
        $tokenId = $this->option('token-id') ?? $this->ask('Enter token ID');

        if (! $tokenId) {
            $this->error('Token ID is required');

            return Command::FAILURE;
        }

        $token = PersonalAccessToken::with('tokenable')->find($tokenId);

        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return Command::FAILURE;
        }

        $this->displayTokenInfo($token);

        return Command::SUCCESS;
    }

    private function revokeToken()
    {
        $tokenId = $this->option('token-id') ?? $this->ask('Enter token ID');

        if (! $tokenId) {
            $this->error('Token ID is required');

            return Command::FAILURE;
        }

        $token = PersonalAccessToken::with('tokenable')->find($tokenId);

        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return Command::FAILURE;
        }

        $this->info('Token to revoke:');
        $this->displayTokenInfo($token);

        if (! $this->confirm('Are you sure you want to revoke this token?')) {
            $this->info('Token revocation cancelled.');

            return Command::SUCCESS;
        }

        $token->delete();

        $this->info('Token revoked successfully!');

        return Command::SUCCESS;
    }

    private function revokeAllTokens()
    {
        $userId = $this->option('user-id') ?? $this->ask('Enter user ID (leave empty to revoke ALL tokens)');

        if ($userId) {
            $user = User::find($userId);

            if (! $user) {
                $this->error("User with ID {$userId} not found");

                return Command::FAILURE;
            }

            $tokenCount = $user->tokens()->count();

            if ($tokenCount === 0) {
                $this->info('User has no tokens to revoke.');

                return Command::SUCCESS;
            }

            $this->warn("This will revoke {$tokenCount} tokens for user: {$user->email}");

            if (! $this->confirm('Are you sure you want to revoke all tokens for this user?')) {
                $this->info('Token revocation cancelled.');

                return Command::SUCCESS;
            }

            $user->tokens()->delete();

            $this->info("All {$tokenCount} tokens revoked for user: {$user->email}");
        } else {
            $tokenCount = PersonalAccessToken::count();

            if ($tokenCount === 0) {
                $this->info('No tokens found to revoke.');

                return Command::SUCCESS;
            }

            $this->warn("This will revoke ALL {$tokenCount} tokens in the system!");

            if (! $this->confirm('Are you absolutely sure you want to revoke ALL tokens?')) {
                $this->info('Token revocation cancelled.');

                return Command::SUCCESS;
            }

            if (! $this->confirm('This cannot be undone. Continue?')) {
                $this->info('Token revocation cancelled.');

                return Command::SUCCESS;
            }

            PersonalAccessToken::query()->delete();

            $this->info("All {$tokenCount} tokens have been revoked!");
        }

        return Command::SUCCESS;
    }

    private function pruneTokens()
    {
        $expiredCount = PersonalAccessToken::where('expires_at', '<', now())->count();

        if ($expiredCount === 0) {
            $this->info('No expired tokens found to prune.');

            return Command::SUCCESS;
        }

        $this->info("Found {$expiredCount} expired tokens.");

        if (! $this->confirm('Do you want to delete all expired tokens?')) {
            $this->info('Token pruning cancelled.');

            return Command::SUCCESS;
        }

        PersonalAccessToken::where('expires_at', '<', now())->delete();

        $this->info("Pruned {$expiredCount} expired tokens successfully!");

        return Command::SUCCESS;
    }

    private function displayTokenInfo(PersonalAccessToken $token): void
    {
        $this->line('Token Details:');
        $this->line("  ID: {$token->id}");
        $this->line("  Name: {$token->name}");
        $this->line('  User: '.($token->tokenable?->email ?? 'N/A'));
        $this->line('  Abilities: '.implode(', ', $token->abilities ?: ['*']));
        $this->line('  Last Used: '.($token->last_used_at?->format('Y-m-d H:i:s') ?? 'Never'));
        $this->line('  Expires: '.($token->expires_at?->format('Y-m-d H:i:s') ?? 'Never'));
        $this->line("  Created: {$token->created_at->format('Y-m-d H:i:s')}");
        $this->line("  Updated: {$token->updated_at->format('Y-m-d H:i:s')}");
    }
}
