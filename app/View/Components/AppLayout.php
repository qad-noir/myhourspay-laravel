<?php

namespace App\View\Components;

use App\Services\CurrentWorkspace;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(private readonly CurrentWorkspace $current) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = auth()->user();

        return view('layouts.app', [
            'currentWorkspace' => $this->current->for($user),
            'workspaces' => $user->workspaces()->orderBy('name')->get(),
        ]);
    }
}
