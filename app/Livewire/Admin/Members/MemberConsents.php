<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Consents\Actions\RecordConsent;
use App\Domain\Consents\Actions\RevokeConsent;
use App\Domain\Consents\Enums\ConsentMethod;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class MemberConsents extends Component
{
    use InteractsWithToasts, ResolvesMember, WithFileUploads;

    public bool $showForm = false;

    #[Locked]
    public ?int $templateId = null;

    public string $method = 'digital';

    public string $signedName = '';

    public bool $accepted = false;

    /** @var TemporaryUploadedFile|null */
    public $scan = null;

    public function capture(int $templateId): void
    {
        $member = $this->member();
        $this->authorize('update', $member);
        $template = ConsentTemplate::query()->active()->findOrFail($templateId);

        $this->reset(['method', 'accepted', 'scan']);
        $this->resetValidation();
        $this->templateId = $template->id;
        $this->signedName = $member->isMinor() ? '' : $member->full_name;
        $this->showForm = true;
    }

    public function save(RecordConsent $record): void
    {
        $member = $this->member();
        $this->authorize('update', $member);
        $template = ConsentTemplate::query()->findOrFail((int) $this->templateId);

        $this->validate([
            'method' => ['required', Rule::enum(ConsentMethod::class)],
            'signedName' => ['required', 'string', 'max:150'],
            'accepted' => $this->method === ConsentMethod::Digital->value ? ['accepted'] : ['boolean'],
            'scan' => $this->method === ConsentMethod::Paper->value
                ? ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.config('profitlife.documents.max_kb')]
                : ['nullable'],
        ], [
            'accepted.accepted' => 'Confirme que la persona leyó y acepta el texto.',
        ], ['signedName' => 'nombre de quien firma', 'scan' => 'documento firmado']);

        $record->execute(
            $member,
            $template,
            ConsentMethod::from($this->method),
            trim($this->signedName),
            $this->method === ConsentMethod::Paper->value ? $this->scan : null,
            auth()->user(),
            request()->ip(),
        );

        $this->reset(['showForm', 'templateId', 'scan', 'accepted']);
        $this->toast('Consentimiento registrado.');
    }

    public function revoke(int $consentId, RevokeConsent $revoke): void
    {
        $member = $this->member();
        $this->authorize('update', $member);
        $consent = $member->consents()->findOrFail($consentId);

        $revoke->execute($consent, auth()->user());
        $this->toast('Consentimiento revocado.', 'warning');
    }

    public function render(): View
    {
        $member = $this->member();
        $consents = $member->consents()->with(['template', 'capturer:id,name', 'document'])->latest('accepted_at')->get();
        $templates = ConsentTemplate::query()->active()->orderBy('type')->get();

        // Estado por tipo: vigente (versión activa aceptada), desactualizado o pendiente.
        $status = $templates->mapWithKeys(function (ConsentTemplate $template) use ($consents) {
            $valid = $consents->first(fn (Consent $c) => ! $c->isRevoked() && $c->consent_template_id === $template->id);
            $older = $consents->first(fn (Consent $c) => ! $c->isRevoked() && $c->template->type === $template->type);

            return [$template->id => $valid ? 'valid' : ($older ? 'outdated' : 'pending')];
        });

        return view('livewire.admin.members.consents', [
            'member' => $member,
            'templates' => $templates,
            'status' => $status,
            'consents' => $consents,
            'current' => $this->templateId ? $templates->firstWhere('id', $this->templateId) : null,
            'methods' => ConsentMethod::options(),
        ]);
    }
}
