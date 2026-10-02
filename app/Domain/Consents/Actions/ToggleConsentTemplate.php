<?php

namespace App\Domain\Consents\Actions;

use App\Domain\Consents\Models\ConsentTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva una versión. Solo una versión por tipo puede estar activa.
 */
class ToggleConsentTemplate
{
    public function execute(ConsentTemplate $template): ConsentTemplate
    {
        return DB::transaction(function () use ($template) {
            if (! $template->is_active) {
                ConsentTemplate::query()->where('type', $template->type)->whereKeyNot($template->id)
                    ->where('is_active', true)->get()
                    ->each(fn (ConsentTemplate $t) => $t->update(['is_active' => false]));
            }

            $template->update(['is_active' => ! $template->is_active]);

            return $template;
        });
    }
}
