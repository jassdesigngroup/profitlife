<?php

namespace App\Livewire\Admin\Training;

use App\Domain\Training\Actions\SaveTrainingProgram;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\TrainingProgram;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Plantillas de programas (sin cliente) para reutilizar.
 */
#[Title('Plantillas de entrenamiento')]
class TemplateIndex extends Component
{
    use InteractsWithToasts;

    public string $search = '';

    public bool $showForm = false;

    public string $name = '';

    public string $goal = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TrainingProgram::class);
    }

    public function create(): void
    {
        $this->authorize('create', TrainingProgram::class);
        $this->reset(['name', 'goal']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(SaveTrainingProgram $save): void
    {
        $this->authorize('create', TrainingProgram::class);
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'nombre', 'goal' => 'objetivo']);

        $template = $save->create(null, ProgramType::Training, [
            'name' => $this->name,
            'goal' => $this->goal,
            'starts_on' => null,
            'ends_on' => null,
            'status' => ProgramStatus::Active,
        ], auth()->user());

        $this->redirectRoute('admin.training.programs.edit', $template, navigate: true);
    }

    public function delete(int $id): void
    {
        $template = TrainingProgram::query()->where('is_template', true)->findOrFail($id);
        $this->authorize('delete', $template);

        $template->delete();
        $this->toast("Plantilla {$template->name} eliminada.", 'warning');
    }

    public function render(): View
    {
        $this->authorize('viewAny', TrainingProgram::class);
        $term = trim($this->search);

        return view('livewire.admin.training.templates', [
            'templates' => TrainingProgram::query()
                ->where('is_template', true)
                ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%'))
                ->with('staff:id,first_name,last_name')
                ->withCount('workouts')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
