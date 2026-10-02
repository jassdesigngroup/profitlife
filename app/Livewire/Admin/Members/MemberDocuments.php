<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Documents\Actions\DeleteDocument;
use App\Domain\Documents\Actions\StoreDocument;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Documents\Enums\DocumentSensitivity;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Documentos del cliente. Los clínicos (categoría "Médico") solo los ven y
 * suben quienes tienen permisos clínicos; para el resto ni siquiera aparecen.
 */
class MemberDocuments extends Component
{
    use InteractsWithToasts, ResolvesMember, WithFileUploads;

    public bool $showForm = false;

    public string $title = '';

    public string $category = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public function create(): void
    {
        abort_if($this->allowedCategories() === [], 403);
        $this->reset(['title', 'category', 'file']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(StoreDocument $store): void
    {
        $member = $this->member();
        abort_if($this->allowedCategories() === [], 403);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys($this->allowedCategories()))],
            'file' => ['required', 'file', 'mimes:'.implode(',', config('profitlife.documents.mimes')), 'max:'.config('profitlife.documents.max_kb')],
        ], [], ['title' => 'título', 'category' => 'categoría', 'file' => 'archivo']);

        $category = DocumentCategory::from($data['category']);

        // Clínicos: permiso clínico. Administrativos: permiso de edición del cliente.
        $category->sensitivity() === DocumentSensitivity::Clinical
            ? $this->authorize('uploadClinicalDocuments', $member)
            : $this->authorize('update', $member);

        $store->execute($member, $this->file, $category, $data['title'], auth()->user());

        $this->reset(['title', 'category', 'file', 'showForm']);
        $this->toast('Documento guardado.');
    }

    public function delete(string $uuid, DeleteDocument $delete): void
    {
        $document = $this->member()->documents()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $document);

        $delete->execute($document, auth()->user());
        $this->toast('Documento eliminado.');
    }

    public function render(): View
    {
        $member = $this->member();
        $user = auth()->user();
        $canClinical = $user->can('viewClinicalDocuments', $member);

        $documents = $member->documents()
            ->with('uploader:id,name')
            ->when(! $canClinical, fn ($q) => $q->where('sensitivity', DocumentSensitivity::Administrative))
            ->latest('id')
            ->get();

        return view('livewire.admin.members.documents', [
            'member' => $member,
            'documents' => $documents,
            'categories' => $this->allowedCategories(),
            'canClinical' => $canClinical,
            'canUpload' => $this->allowedCategories() !== [],
        ]);
    }

    /**
     * Categorías que el usuario puede subir para este cliente.
     *
     * @return array<string, string>
     */
    private function allowedCategories(): array
    {
        $member = $this->member();
        $user = auth()->user();
        $canClinical = $user->can('uploadClinicalDocuments', $member);
        $canAdministrative = $user->can('update', $member);

        return collect(DocumentCategory::cases())
            ->reject(fn (DocumentCategory $c) => $c === DocumentCategory::Consent) // se suben desde Consentimientos
            ->filter(fn (DocumentCategory $c) => $c->sensitivity() === DocumentSensitivity::Clinical ? $canClinical : $canAdministrative)
            ->mapWithKeys(fn (DocumentCategory $c) => [$c->value => $c->label()])
            ->all();
    }
}
