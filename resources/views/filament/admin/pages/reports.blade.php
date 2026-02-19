<x-filament-panels::page>
    @php
        $summary = $this->summaryCounts;
    @endphp

    <style>
        .rp-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .rp-kpi-card {
            border-radius: 0.85rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 0.9rem;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.04), rgba(15, 23, 42, 0));
        }

        .dark .rp-kpi-card {
            border-color: rgba(148, 163, 184, 0.22);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.45));
        }

        .rp-kpi-label {
            margin: 0;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
            color: #64748b;
        }

        .dark .rp-kpi-label {
            color: #94a3b8;
        }

        .rp-kpi-value {
            margin: 0.3rem 0 0;
            font-size: 1.6rem;
            line-height: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }

        .dark .rp-kpi-value {
            color: #f8fafc;
        }

        @media (max-width: 1100px) {
            .rp-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .rp-kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <x-filament::section
        heading="Report Pipeline"
        description="Generate CSV exports, track queue progress, and re-download stored reports from history."
        :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentChartBar"
        class="mb-6"
    >
        <div class="rp-kpi-grid">
            <div class="rp-kpi-card">
                <p class="rp-kpi-label">Queued</p>
                <p class="rp-kpi-value">{{ number_format($summary['queued']) }}</p>
            </div>
            <div class="rp-kpi-card">
                <p class="rp-kpi-label">Running</p>
                <p class="rp-kpi-value">{{ number_format($summary['running']) }}</p>
            </div>
            <div class="rp-kpi-card">
                <p class="rp-kpi-label">Completed</p>
                <p class="rp-kpi-value">{{ number_format($summary['completed']) }}</p>
            </div>
            <div class="rp-kpi-card">
                <p class="rp-kpi-label">No Data</p>
                <p class="rp-kpi-value">{{ number_format($summary['no_data']) }}</p>
            </div>
            <div class="rp-kpi-card">
                <p class="rp-kpi-label">Failed</p>
                <p class="rp-kpi-value">{{ number_format($summary['failed']) }}</p>
            </div>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
