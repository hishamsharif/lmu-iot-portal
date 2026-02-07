<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeviceSchemaVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version Details')
                    ->schema([
                        Select::make('device_schema_id')
                            ->label('Device Schema')
                            ->relationship('schema', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('version')
                            ->required()
                            ->integer()
                            ->minValue(1),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'archived' => 'Archived',
                            ])
                            ->required()
                            ->default('draft'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
