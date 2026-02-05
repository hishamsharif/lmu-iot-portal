<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Tables;

use App\Domain\IoT\Enums\ProtocolType;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->icon(fn (ProtocolType $state): string => match ($state) {
                        ProtocolType::Mqtt => Heroicon::OutlinedSignal->value,
                        ProtocolType::Http => Heroicon::OutlinedGlobeAlt->value,
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
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
