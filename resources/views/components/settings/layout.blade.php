<div class="flex items-start gap-10 max-md:flex-col max-md:gap-0">
    <div class="w-full pb-4 md:w-50 md:shrink-0">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="flex flex-col gap-1">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ $heading ?? '' }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $subheading ?? '' }}</p>
        </div>

        <div class="mt-6 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
