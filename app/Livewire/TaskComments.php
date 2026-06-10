<?php

namespace App\Livewire;

use App\Models\Task;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TaskComments extends Component
{
    public Task $task;

    public bool $canManage = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public function mount(Task $task, bool $canManage = false): void
    {
        $this->task = $task;
        $this->canManage = $canManage;
    }

    public function addComment(): void
    {
        abort_unless(auth()->user()?->can('comment', $this->task), 403);

        $this->validate();

        $this->task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $this->reset('body');
    }

    public function render()
    {
        return view('livewire.task-comments', [
            'comments' => $this->task->comments()->with('author')->get(),
        ]);
    }
}
