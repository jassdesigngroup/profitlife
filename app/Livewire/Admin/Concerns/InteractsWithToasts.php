<?php

namespace App\Livewire\Admin\Concerns;

trait InteractsWithToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
