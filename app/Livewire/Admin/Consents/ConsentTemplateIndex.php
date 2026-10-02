<?php

namespace App\Livewire\Admin\Consents;

use App\Domain\Consents\Actions\ToggleConsentTemplate;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Consentimientos')]
class ConsentTemplateIndex extends Component
{
    use InteractsWithToasts;

    public ?int $previewId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ConsentTemplate::class);
    }

    public function preview(int $templateId): void
    {
        $this->authorize('viewAny', ConsentTemplate::class);
        $this->previewId = ConsentTemplate::query()->findOrFail($templateId)->id;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function toggle(int $templateId, ToggleConsentTemplate $toggle): void
    {
        $template = ConsentTemplate::query()->findOrFail($templateId);
        $this->authorize('update', $template);

        $toggle->execute($template);
        $this->toast($template->is_active ? 'Versión activada.' : 'Versión desactivada.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', ConsentTemplate::class);

        $templates = ConsentTemplate::query()->withCount(['consents' => fn ($q) => $q->whereNull('revoked_at')])
            ->orderBy('type')->orderByDesc('version')->get()->groupBy(fn ($t) => $t->type->value);

        return view('livewire.admin.consents.index', [
            'types' => ConsentType::cases(),
            'templates' => $templates,
            'preview' => $this->previewId ? ConsentTemplate::query()->find($this->previewId) : null,
        ]);
    }
}
