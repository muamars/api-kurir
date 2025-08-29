<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class GenerateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-token
                            {email : User email address}
                            {--name=api-token : Token name}
                            {--expires= : Token expiration in days (leave empty for permanent)}
                            {--abilities=* : Token abilities/permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API token for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $tokenName = $this->option('name');
        $expires = $this->option('expires');
        $abilities = $this->option('abilities');

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found!");
            return 1;
        }

        // Set abilities (default to all if not specified)
        if (empty($abilities)) {
            $abilities = ['*']; // All abilities
        }

        // Create token
        $tokenResult = $user->createToken($tokenName, $abilities);
        $token = $tokenResult->plainTextToken;

        // Set expiration if specified
        if ($expires) {
            $expirationDate = Carbon::now()->addDays((int)$expires);
            $tokenResult->accessToken->update([
                'expires_at' => $expirationDate
            ]);

            $this->info("Token will expire on: {$expirationDate->format('Y-m-d H:i:s')}");
        } else {
            $this->info("Token is permanent (no expiration)");
        }

        // Display user info
        $this->info("Token generated for user:");
        $this->line("- Name: {$user->name}");
        $this->line("- Email: {$user->email}");
        $this->line("- Roles: " . $user->getRoleNames()->implode(', '));
        $this->line("- Division: " . ($user->division ? $user->division->name : 'None'));

        // Display token
        $this->newLine();
        $this->info("API Token:");
        $this->line($token);

        // Display usage example
        $this->newLine();
        $this->info("Usage example:");
        $this->line("curl -H 'Authorization: Bearer {$token}' http://localhost:8000/api/v1/auth/me");

        return 0;
    }
}
