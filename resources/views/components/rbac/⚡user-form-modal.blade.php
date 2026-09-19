<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

new class extends Component {
  public int $userId = 0; // 0 for create, > 0 for edit

  #[Computed]
  public function allRoles()
  {
      return Role::orderBy('name')->get();
  }
  public string $name = '';
  public string $username = '';
  public string $email = '';
  public string $password = '';
  public string $password_confirmation = '';
  public string $roleName = '';
  public string $status = 'active';

  public bool $isAddEditOpen = false;

  #[On('open-user-form')]
  public function openForm(?int $id = null): void
  {
    $this->resetForm();
    if ($id) {
      $this->userId = $id;
      $user = User::findOrFail($id);
      $this->name = $user->name;
      $this->username = $user->username;
      $this->email = $user->email ?? '';
      $this->status = $user->status;
      $this->roleName = $user->roles->first()?->name ?? '';
    }
    $this->isAddEditOpen = true;
  }

  public function resetForm(): void
  {
    $this->userId = 0;
    $this->name = '';
    $this->username = '';
    $this->email = '';
    $this->password = '';
    $this->password_confirmation = '';
    $this->roleName = '';
    $this->status = 'active';
    $this->resetValidation();
  }

  public function saveUser(): void
  {
    $isEdit = $this->userId > 0;

    abort_unless(
      auth()->user()?->can($isEdit ? 'access-control:users-edit' : 'access-control:users-create'),
      403
    );

    $rules = [
      'name' => 'required|string|max:255',
      'username' => 'required|string|max:255|unique:users,username,' . $this->userId,
      'email' => 'required|email|max:255|unique:users,email,' . $this->userId,
      'roleName' => 'required|string|exists:roles,name',
      'status' => 'required|string|in:active,inactive',
    ];

    if (!$isEdit) {
      $rules['password'] = 'required|string|min:8|confirmed';
    } else {
      $rules['password'] = 'nullable|string|min:8|confirmed';
    }

    $validated = $this->validate($rules);

    try {
      if ($isEdit) {
        $user = User::findOrFail($this->userId);
        $user->update([
          'name' => $this->name,
          'username' => $this->username,
          'email' => $this->email,
          'status' => $this->status,
        ]);

        if ($this->password) {
          $user->update([
            'password' => Hash::make($this->password),
          ]);
        }
      } else {
        $user = User::create([
          'name' => $this->name,
          'username' => $this->username,
          'email' => $this->email,
          'status' => $this->status,
          'password' => Hash::make($this->password),
        ]);
      }

      $user->syncRoles([$this->roleName]);

      $this->isAddEditOpen = false;
      $this->resetForm();
      $this->dispatch('user-saved');
      $msg = $isEdit ? 'User updated successfully!' : 'User created successfully!';
      session()->flash('success', $msg);
      $this->dispatch('notify', type: 'success', message: $msg);
    } catch (\Throwable $e) {
      $this->isAddEditOpen = false;
      $msg = 'Failed to save user: ' . $e->getMessage();
      session()->flash('error', $msg);
      $this->dispatch('notify', type: 'error', message: $msg);
    }
  }
};
?>

<div>
  <!-- Add/Edit User Modal -->
  <div x-data="{ show: @entangle('isAddEditOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex items-center justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative w-full max-w-xl rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
        <h4 class="text-xl font-bold text-gray-800 dark:text-white">
          {{ $userId > 0 ? 'Edit User Access' : 'Add New User' }}
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      <form wire:submit.prevent="saveUser" class="space-y-4">
        <h5
          class="mb-2 border-l-2 border-accent-primary pl-2 text-sm font-bold text-gray-700 dark:text-gray-300">
          Account Information
        </h5>

        <!-- Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Full Name</label>
          <input type="text" wire:model="name" placeholder="e.g. Jane Doe"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror" />
          @error('name')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Username -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Username</label>
          <input type="text" wire:model="username" placeholder="e.g. janedoe"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('username') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror" />
          @error('username')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Email -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email Address</label>
          <input type="email" wire:model="email" placeholder="janedoe@example.test"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('email') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror" />
          @error('email')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Role -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">User Role</label>
            <div x-data="{ isOptionSelected: @entangle('roleName').live }" class="relative z-20 bg-transparent">
              <select wire:model="roleName"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border bg-transparent bg-none pl-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:placeholder:text-white/30 @error('roleName') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror"
                :class="isOptionSelected && 'text-gray-800 dark:text-white/90'" @change="isOptionSelected = true">
                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Select Role</option>
                @foreach ($this->allRoles as $role)
                <option value="{{ $role->name }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $role->name }}</option>
                @endforeach
              </select>
              <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </div>
            @error('roleName')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>

          <!-- Status -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
            <div x-data="{ isOptionSelected: @entangle('status').live }" class="relative z-20 bg-transparent">
              <select wire:model="status"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border bg-transparent bg-none pl-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:placeholder:text-white/30 @error('status') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror"
                :class="isOptionSelected && 'text-gray-800 dark:text-white/90'" @change="isOptionSelected = true">
                <option value="active" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Active</option>
                <option value="inactive" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Inactive</option>
              </select>
              <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </div>
            @error('status')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>
        </div>

        <!-- Password Block -->
        <div class="pt-2">
          <h5
            class="mb-1 border-l-2 border-accent-primary pl-2 text-sm font-bold text-gray-700 dark:text-gray-300">
            Security Credentials
          </h5>
          <p class="mb-3 text-[11px] text-gray-400">
            {{ $userId > 0 ? 'Leave blank to keep current password.' : 'Use at least 8 characters to create a secure password.' }}
          </p>

          <div class="grid grid-cols-2 gap-4">
            <!-- Password -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Password</label>
              <div x-data="{ showPassword: false }" class="relative">
                <input :type="showPassword ? 'text' : 'password'" wire:model="password" placeholder="••••••••"
                  class="dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent pl-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('password') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror" />
                <span @click="showPassword = !showPassword"
                  class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer">
                  <svg x-show="!showPassword" class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" />
                  </svg>
                  <svg x-show="showPassword" class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" />
                  </svg>
                </span>
              </div>
              @error('password')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror
            </div>

            <!-- Confirm Password -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirm Password</label>
              <div x-data="{ showPassword: false }" class="relative">
                <input :type="showPassword ? 'text' : 'password'" wire:model="password_confirmation" placeholder="••••••••"
                  class="dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800" />
                <span @click="showPassword = !showPassword"
                  class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer">
                  <svg x-show="!showPassword" class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" />
                  </svg>
                  <svg x-show="showPassword" class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61687 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" />
                  </svg>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
          <button type="button" @click="show = false"
            class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
            Cancel
          </button>
          <button type="submit" wire:loading.attr="disabled"
            class="rounded-xl bg-accent-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover dark:bg-blue-600 dark:hover:bg-blue-700 flex items-center justify-center gap-2">
            <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="saveUser" />
            <span>Save User</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
