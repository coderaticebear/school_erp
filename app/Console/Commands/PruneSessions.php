<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Session\SessionManager;

/**
 * Delete sessions past their lifetime (SEC-09). Laravel only does this on a random 2% of requests,
 * so on a quiet server expired rows, each with an IP address and browser details, would pile up.
 * Scheduled hourly in routes/console.php.
 */
class PruneSessions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sessions:prune';

    /**
     * @var string
     */
    protected $description = 'Delete sessions that have passed their lifetime';

    public function handle(SessionManager $sessions): int
    {
        $deleted = $sessions->driver()->getHandler()->gc((int) config('session.lifetime') * 60);

        $this->components->info(sprintf('Deleted %d expired %s.', (int) $deleted, $deleted === 1 ? 'session' : 'sessions'));

        return self::SUCCESS;
    }
}
