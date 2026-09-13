<?php

namespace App\Livewire\Settings;

use App\Actions\DeleteWorkspace;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, DeleteWorkspace $deleteWorkspace): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        foreach ($user->workspaces()->cursor() as $workspace) {
            $deleteWorkspace->execute($workspace);
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
