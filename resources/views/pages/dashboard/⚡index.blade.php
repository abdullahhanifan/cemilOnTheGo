<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component {
    //
};
?>

<div>
  <div class="mb-6">
    <h1 class="text-2xl font-bold tracking-tight text-neutral-heading">Dashboard</h1>
    <p class="mt-1 text-sm text-neutral-label">Welcome back, {{ auth()->user()->name }}.</p>
  </div>

  <div class="min-h-48 rounded-2xl border border-neutral-border bg-neutral-card p-5 xl:p-8"></div>
</div>
