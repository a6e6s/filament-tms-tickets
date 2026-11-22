<div
    x-data="{
        source: @js($source),
        auto: [],
        elements: [],
        pending: false,
        error: null,
        apply(detail) {
            if (! detail) return;
            this.auto = detail.auto ?? this.auto;
            this.elements = detail.elements ?? this.elements;
            this.pending = detail.pending ?? false;
            this.error = detail.error ?? null;
        },
        get items() {
            return this.source === 'auto' ? this.auto : this.elements;
        },
        remove(index) {
            if (this.source === 'auto') {
                window.TmsTickets?.removeAuto();
            } else {
                window.TmsTickets?.removeElement(index);
            }
        },
    }"
    x-init="apply(window.TmsTickets?.snapshot?.())"
    x-on:tms-tickets:captures.window="apply($event.detail)"
    class="space-y-2"
>
    @if ($source === 'element')
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                icon="heroicon-o-cursor-arrow-rays"
                x-on:click="window.TmsTickets?.pick()"
            >
                {{ __('tms-tickets::tickets.element.pick') }}
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                outlined
                x-on:click="window.TmsTickets?.clear()"
            >
                {{ __('tms-tickets::tickets.element.clear') }}
            </x-filament::button>

            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="`${elements.length} / {{ \Tms\FilamentTickets\Services\ScreenshotEncoder::MAX_COUNT }}`"></span>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('tms-tickets::tickets.element.hint') }}</p>

        <p class="text-sm text-danger-600 dark:text-danger-400" x-show="error === 'element'" x-cloak>
            {{ __('tms-tickets::tickets.errors.capture_failed') }}
        </p>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                outlined
                icon="heroicon-o-arrow-path"
                x-on:click="window.TmsTickets?.start()"
            >
                {{ __('tms-tickets::tickets.capture.retake') }}
            </x-filament::button>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400" x-show="pending" x-cloak>
            {{ __('tms-tickets::tickets.capture.capturing') }}
        </p>

        <p class="text-sm text-danger-600 dark:text-danger-400" x-show="error === 'auto'" x-cloak>
            {{ __('tms-tickets::tickets.capture.failed') }}
        </p>
    @endif

    <ul x-show="items.length > 0" x-cloak style="display:flex;flex-wrap:wrap;gap:0.75rem;list-style:none;margin:0;padding:0">
        <template x-for="(src, index) in items" :key="index">
            <li style="width:200px;border:1px solid #d1d5db;border-radius:0.5rem;overflow:hidden;background:#fff">
                <img :src="src" alt="" style="display:block;width:100%;height:120px;object-fit:contain;background:#fff">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.25rem 0.5rem;font-size:0.875rem">
                    <span style="color:#4b5563" x-text="`#${index + 1}`"></span>
                    <button type="button" x-on:click="remove(index)" style="color:#dc2626;background:none;border:0;cursor:pointer">
                        {{ __('tms-tickets::tickets.capture.remove') }}
                    </button>
                </div>
            </li>
        </template>
    </ul>
</div>
