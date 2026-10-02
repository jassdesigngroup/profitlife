<?php

namespace App\Console\Commands;

use App\Domain\Memberships\Services\ProcessMemberships;
use Illuminate\Console\Command;

class ProcessMembershipsCommand extends Command
{
    protected $signature = 'memberships:process';

    protected $description = 'Activa, reanuda, renueva, vence y suspende membresías según la fecha de hoy';

    public function handle(ProcessMemberships $process): int
    {
        $summary = $process->run();

        $this->info(sprintf(
            'Reanudadas: %d · Renovadas: %d · Vencidas: %d · Activadas: %d · Suspendidas por pago: %d',
            $summary['resumed'], $summary['renewed'], $summary['expired'], $summary['activated'], $summary['suspended'],
        ));

        return self::SUCCESS;
    }
}
