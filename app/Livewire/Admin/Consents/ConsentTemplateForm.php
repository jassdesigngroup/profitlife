<?php

namespace App\Livewire\Admin\Consents;

use App\Domain\Consents\Actions\PublishConsentTemplate;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\ConsentTemplate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Publica una versión nueva. Nunca edita una versión existente: lo que un
 * cliente aceptó debe poder consultarse tal cual.
 */
#[Title('Nueva versión de consentimiento')]
class ConsentTemplateForm extends Component
{
    #[Url]
    public string $type = '';

    public string $title = '';

    public string $body = '';

    public function mount(): void
    {
        $this->authorize('create', ConsentTemplate::class);

        $type = ConsentType::tryFrom($this->type);
        $latest = $type ? ConsentTemplate::query()->where('type', $type)->orderByDesc('version')->first() : null;

        if ($latest) {
            $this->title = $latest->title;
            $this->body = $latest->body;
        } elseif ($type) {
            $this->title = $type->label();
        }
    }

    public function save(PublishConsentTemplate $publish): void
    {
        $this->authorize('create', ConsentTemplate::class);

        $data = $this->validate([
            'type' => ['required', Rule::enum(ConsentType::class)],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:30', 'max:60000'],
        ], [], ['type' => 'tipo', 'title' => 'título', 'body' => 'texto']);

        $template = $publish->execute(ConsentType::from($data['type']), $data['title'], $data['body'], auth()->user());

        session()->flash('success', "Publicada la versión {$template->version} de «{$template->title}».");
        $this->redirectRoute('admin.consents.index', navigate: true);
    }

    public function render(): View
    {
        $type = ConsentType::tryFrom($this->type);

        return view('livewire.admin.consents.form', [
            'types' => ConsentType::options(),
            'nextVersion' => $type ? ((int) ConsentTemplate::query()->where('type', $type)->max('version')) + 1 : null,
        ]);
    }
}
