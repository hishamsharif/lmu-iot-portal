<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeviceSchemaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schema Details')
                    ->schema([
                        Select::make('device_type_id')
                            ->label('Device Type')
                            ->relationship('deviceType', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }
}
