<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                @php
                    $overall = $this->getOverallStatus();
                @endphp
                <x-filament::icon :icon="$overall['icon']" @class([
                    'h-5 w-5',
                    'text-success-500' => $overall['color'] === 'success',
                    'text-warning-500' => $overall['color'] === 'warning',
                    'text-danger-500' => $overall['color'] === 'danger',
                    'text-gray-500' => $overall['color'] === 'gray',
                ]) />
                <span>Commerce Health Status</span>
            </div>
        </x-slot>

        <x-slot name="headerEnd">
            @php
                $counts = $this->getStatusCounts();
            @endphp
            <div class="flex items-center gap-3 text-sm">
                @if ($counts['ok'] > 0)
                    <span class="flex items-center gap-1 text-success-600 dark:text-success-400">
                        <x-heroicon-o-check-circle class="h-4 w-4" />
                        {{ $counts['ok'] }}
                    </span>
                @endif
                @if ($counts['warning'] > 0)
                    <span class="flex items-center gap-1 text-warning-600 dark:text-warning-400">
                        <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                        {{ $counts['warning'] }}
                    </span>
                @endif
                @if ($counts['failed'] > 0)
                    <span class="flex items-center gap-1 text-danger-600 dark:text-danger-400">
                        <x-heroicon-o-x-circle class="h-4 w-4" />
                        {{ $counts['failed'] }}
                    </span>
                @endif
            </div>
        </x-slot>

        @php
            $results = $this->getHealthResults();
        @endphp

        @if (empty($results))
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-heart class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-2">No health checks registered</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($results as $result)
                    <div @class([
                        'rounded-lg border p-4',
                        'border-success-500/50 bg-success-50 dark:bg-success-950/20' => $result['status'] === 'ok',
                        'border-warning-500/50 bg-warning-50 dark:bg-warning-950/20' => $result['status'] === 'warning',
                        'border-danger-500/50 bg-danger-50 dark:bg-danger-950/20' => $result['status'] === 'failed',
                        'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' => !in_array($result['status'], ['ok', 'warning', 'failed']),
                    ])>
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $result['label'] }}
                                </h4>
                                <p @class([
                                    'mt-1 text-sm',
                                    'text-success-700 dark:text-success-400' => $result['status'] === 'ok',
                                    'text-warning-700 dark:text-warning-400' => $result['status'] === 'warning',
                                    'text-danger-700 dark:text-danger-400' => $result['status'] === 'failed',
                                    'text-gray-600 dark:text-gray-400' => !in_array($result['status'], ['ok', 'warning', 'failed']),
                                ])>
                                    {{ $result['message'] ?: 'No details available' }}
                                </p>
                            </div>
                            <x-filament::icon :icon="$this->getStatusIcon($result['status'])" @class([
                                'h-5 w-5 flex-shrink-0',
                                'text-success-500' => $result['status'] === 'ok',
                                'text-warning-500' => $result['status'] === 'warning',
                                'text-danger-500' => $result['status'] === 'failed',
                                'text-gray-400' => !in_array($result['status'], ['ok', 'warning', 'failed']),
                            ]) />
                        </div>

                        @if (!empty($result['meta']))
                            <div class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
                                <dl class="text-xs text-gray-600 dark:text-gray-400">
                                    @foreach (array_slice($result['meta'], 0, 3) as $key => $value)
                                        <div class="flex justify-between py-0.5">
                                            <dt class="capitalize">{{ str_replace('_', ' ', $key) }}</dt>
                                            <dd class="font-medium">
                                                @if (is_bool($value))
                                                    {{ $value ? 'Yes' : 'No' }}
                                                @elseif (is_array($value))
                                                    {{ count($value) }} items
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>