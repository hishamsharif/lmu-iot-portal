<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Telemetry\DeviceTelemetryLogs\Tables;

use App\Domain\Telemetry\Enums\ValidationStatus;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceTelemetryLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device.uuid')
                    ->label('Device UUID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('device.name')
                    ->label('Device Name')
                    ->searchable(),

                TextColumn::make('schemaVersion.version')
                    ->label('Schema Version')
                    ->sortable(),

                TextColumn::make('validation_status')
                    ->badge()
                    ->formatStateUsing(fn (ValidationStatus|string $state): string => $state instanceof ValidationStatus ? $state->label() : (string) $state),

                TextColumn::make('processing_state')
                    ->badge()
                    ->sortable(),

                TextColumn::make('recorded_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('validation_status')
                    ->label('Validation Status')
                    ->options(ValidationStatus::class),

                SelectFilter::make('processing_state')
                    ->options([
                        'processed' => 'Processed',
                        'publish_failed' => 'Publish Failed',
                        'invalid' => 'Invalid',
                        'inactive_skipped' => 'Inactive Skipped',
                    ]),

                Filter::make('recorded_at')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('From'),
                        DateTimePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $query->when(
                            $data['from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->where('recorded_at', '>=', $date),
                        );

                        return $query->when(
                            $data['until'] ?? null,
                            fn (Builder $query, $date): Builder => $query->where('recorded_at', '<=', $date),
                        );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}
