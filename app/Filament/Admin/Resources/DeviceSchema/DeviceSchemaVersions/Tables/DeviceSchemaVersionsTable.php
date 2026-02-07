<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemaVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schema.name')
                    ->label('Device Schema')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('version')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'active' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('topics_count')
                    ->label('Topics')
                    ->counts('topics')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
