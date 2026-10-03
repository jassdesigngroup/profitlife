<?php

namespace App\Livewire\Admin\Training;

use App\Domain\Training\Actions\SaveExercise;
use App\Domain\Training\Models\Equipment;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\MuscleGroup;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Biblioteca de ejercicios: búsqueda, filtros, ficha con video y edición.
 */
#[Title('Ejercicios')]
class ExerciseLibrary extends Component
{
    use InteractsWithToasts, WithFileUploads, WithPagination;

    /** Solo enlaces de YouTube o Vimeo (se muestran embebidos). */
    public const VIDEO_PATTERN = '~^https://(www\.|m\.)?(youtube\.com/(watch\?v=|shorts/|embed/)[\w-]{11}|youtu\.be/[\w-]{11}|vimeo\.com/(video/)?\d+)~';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'grupo')]
    public string $muscle = '';

    #[Url(as: 'equipo')]
    public string $equipmentFilter = '';

    #[Url(as: 'inactivos')]
    public bool $showInactive = false;

    // Ficha
    #[Locked]
    public ?int $viewingId = null;

    public bool $showView = false;

    // Formulario
    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public string $videoUrl = '';

    public bool $isActive = true;

    /** @var list<string> */
    public array $primary = [];

    /** @var list<string> */
    public array $secondary = [];

    /** @var list<string> */
    public array $equipmentIds = [];

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public bool $removeImage = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Exercise::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'muscle', 'equipmentFilter', 'showInactive'], true)) {
            $this->resetPage();
        }
    }

    public function view(int $id): void
    {
        $this->authorize('viewAny', Exercise::class);
        $this->viewingId = Exercise::query()->findOrFail($id)->id;
        $this->showView = true;
    }

    public function create(): void
    {
        $this->authorize('create', Exercise::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $exercise = Exercise::query()->with('muscleGroups', 'equipment')->findOrFail($id);
        $this->authorize('update', $exercise);

        $this->resetForm();
        $this->editingId = $exercise->id;
        $this->name = $exercise->name;
        $this->description = (string) $exercise->description;
        $this->instructions = (string) $exercise->instructions;
        $this->videoUrl = (string) $exercise->video_url;
        $this->isActive = $exercise->is_active;
        $this->primary = $exercise->muscleGroups->where('pivot.is_primary', true)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->secondary = $exercise->muscleGroups->where('pivot.is_primary', false)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->equipmentIds = $exercise->equipment->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showView = false;
        $this->showForm = true;
    }

    public function save(SaveExercise $save): void
    {
        $exercise = $this->editingId ? Exercise::query()->findOrFail($this->editingId) : null;
        $this->authorize($exercise ? 'update' : 'create', $exercise ?? Exercise::class);

        $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('exercises', 'name')->ignore($exercise?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'videoUrl' => ['nullable', 'url:https', 'max:255', 'regex:'.self::VIDEO_PATTERN],
            'isActive' => ['boolean'],
            'primary' => ['required', 'array', 'max:5'],
            'primary.*' => ['integer', Rule::exists('muscle_groups', 'id')],
            'secondary' => ['array', 'max:8'],
            'secondary.*' => ['integer', Rule::exists('muscle_groups', 'id')],
            'equipmentIds' => ['array', 'max:10'],
            'equipmentIds.*' => ['integer', Rule::exists('equipment', 'id')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'videoUrl.regex' => 'Use un enlace de YouTube o Vimeo.',
        ], [
            'name' => 'nombre', 'description' => 'descripción', 'instructions' => 'instrucciones', 'videoUrl' => 'video',
            'primary' => 'grupos musculares principales', 'image' => 'imagen',
        ]);

        $saved = $save->execute($exercise, [
            'name' => trim($this->name),
            'description' => $this->description,
            'instructions' => $this->instructions,
            'video_url' => $this->videoUrl,
            'is_active' => $this->isActive,
            'primary' => array_map('intval', $this->primary),
            'secondary' => array_map('intval', $this->secondary),
            'equipment' => array_map('intval', $this->equipmentIds),
        ], $this->image, $this->removeImage, auth()->user());

        $this->showForm = false;
        $this->resetForm();
        $this->toast($exercise ? 'Ejercicio actualizado.' : "{$saved->name} agregado a la biblioteca.");
    }

    public function delete(int $id): void
    {
        $exercise = Exercise::query()->findOrFail($id);
        $this->authorize('delete', $exercise);

        // Se archiva (soft delete): los programas que lo usan lo conservan.
        $exercise->delete();
        $this->showView = false;
        $this->toast("{$exercise->name} eliminado de la biblioteca.", 'warning');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Exercise::class);

        return view('livewire.admin.training.exercises', [
            'exercises' => Exercise::query()
                ->search($this->search)
                ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
                ->when($this->muscle !== '', fn ($q) => $q->whereHas('muscleGroups', fn ($m) => $m->whereKey((int) $this->muscle)))
                ->when($this->equipmentFilter !== '', fn ($q) => $q->whereHas('equipment', fn ($m) => $m->whereKey((int) $this->equipmentFilter)))
                ->with(['muscleGroups:id,name', 'equipment:id,name'])
                ->orderBy('name')
                ->paginate(24),
            'muscleGroups' => MuscleGroup::query()->orderBy('name')->pluck('name', 'id'),
            'equipmentList' => Equipment::query()->orderBy('name')->pluck('name', 'id'),
            'viewing' => $this->showView && $this->viewingId
                ? Exercise::query()->with(['muscleGroups:id,name', 'equipment:id,name', 'creator:id,name'])->find($this->viewingId)
                : null,
            'editingImage' => $this->editingId ? Exercise::query()->whereKey($this->editingId)->value('image_path') : null,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'instructions', 'videoUrl', 'isActive', 'primary', 'secondary', 'equipmentIds', 'image', 'removeImage']);
        $this->resetValidation();
    }
}
