<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Tables;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DeviceTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Key copied to clipboard')
                    ->icon(Heroicon::OutlinedKey)
                    ->description(fn ($record): string => $record->name),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('default_protocol')
                    ->label('Protocol')
                    ->badge()
                    ->formatStateUsing(fn (ProtocolType $state): string => $state->label())
                    ->color(fn (ProtocolType $state): array => match ($state) {
                        ProtocolType::Mqtt => Color::Blue,
                        ProtocolType::Http => Color::Green,
                    })
                    ->icon(fn (ProtocolType $state) => match ($state) {
                        ProtocolType::Mqtt => Heroicon::OutlinedSignal,
                        ProtocolType::Http => Heroicon::OutlinedGlobeAlt,
                    }),

                IconColumn::make('organization_id')
                    ->label('Scope')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedBuildingOffice)
                    ->falseIcon(Heroicon::OutlinedGlobeAlt)
                    ->trueColor(Color::Amber)
                    ->falseColor(Color::Sky)
                    ->tooltip(fn ($record): string => $record->organization_id
                        ? 'Organization-specific'
                        : 'Global catalog'
                    ),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('schemas_count')
                    ->label('Schemas')
                    ->counts('schemas')
                    ->sortable(),

                IconColumn::make('is_onboarding_ready')
                    ->label('Ready')
                    ->boolean()
                    ->state(fn (DeviceType $record): bool => $record->schemas()
                        ->whereHas('versions', fn ($query) => $query->where('status', 'active'))
                        ->exists()
                    )
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedExclamationTriangle)
                    ->trueColor(Color::Green)
                    ->falseColor(Color::Amber)
                    ->tooltip(fn (DeviceType $record): string => $record->schemas()
                        ->whereHas('versions', fn ($query) => $query->where('status', 'active'))
                        ->exists()
                            ? 'Has at least one active schema version'
                            : 'Needs an active schema version before device onboarding'
                    ),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('default_protocol')
                    ->label('Protocol')
                    ->options(ProtocolType::class),

                SelectFilter::make('organization_id')
                    ->label('Scope')
                    ->options([
                        'global' => 'Global Catalog',
                        'organization' => 'Organization-Specific',
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] === 'global',
                            fn ($q) => $q->whereNull('organization_id')
                        )->when(
                            $data['value'] === 'organization',
                            fn ($q) => $q->whereNotNull('organization_id')
                        );
                    }),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\ReplicateAction::make()
                    ->excludeAttributes(['schemas_count', 'created_at', 'updated_at'])
                    ->beforeReplicaSaved(function (DeviceType $record, DeviceType $replica): void {
                        $replica->key = self::generateReplicaKey($record);
                        $replica->name = self::generateReplicaName($record);
                    }),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    private static function generateReplicaKey(DeviceType $record): string
    {
        $base = Str::of($record->key)->lower()->append('_copy')->value();
        $candidate = Str::limit($base, 100, '');
        $counter = 2;

        while (self::replicaKeyExists($record, $candidate)) {
            $suffix = "_{$counter}";
            $prefixLength = 100 - strlen($suffix);
            $candidate = Str::of($base)->substr(0, $prefixLength)->append($suffix)->value();
            $counter++;
        }

        return $candidate;
    }

    private static function replicaKeyExists(DeviceType $record, string $key): bool
    {
        return DeviceType::query()
            ->where('key', $key)
            ->when(
                $record->organization_id === null,
                fn ($query) => $query->whereNull('organization_id'),
                fn ($query) => $query->where('organization_id', $record->organization_id),
            )
            ->exists();
    }

    private static function generateReplicaName(DeviceType $record): string
    {
        return Str::limit("{$record->name} Copy", 255, '');
    }
}
