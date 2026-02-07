<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deviceType.name')
                    ->label('Device Type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
