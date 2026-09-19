<?php
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public array $notifications = [];

    #[On('notify')]
    public function addNotification(string $type, string $message): void
    {
        $id = uniqid();
        $this->notifications[$id] = [
            'id' => $id,
            'type' => $type,
            'message' => $message,
        ];
    }

    public function removeNotification(string $id): void
    {
        unset($this->notifications[$id]);
    }
};
?>

<div class="fixed right-6 top-6 z-[2147483647] flex flex-col gap-3 w-full sm:max-w-[340px]">
    @foreach ($notifications as $id => $n)
        <div wire:key="{{ $id }}">
            <x-ui.notification.notification :type="$n['type']" :message="$n['message']" />
        </div>
    @endforeach
</div>
