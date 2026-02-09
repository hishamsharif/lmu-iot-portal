<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
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

                Section::make('Firmware Template')
                    ->description('Store device firmware with placeholders like {{DEVICE_ID}}, {{CONTROL_TOPIC}}, and {{STATE_TOPIC}}.')
                    ->schema([
                        TextInput::make('firmware_filename')
                            ->label('Firmware File Name')
                            ->maxLength(255)
                            ->placeholder('esp32-device.ino')
                            ->default('firmware.ino'),

                        CodeEditor::make('firmware_template')
                            ->label('Firmware Template')
                            ->language(Language::Cpp)
                            ->columnSpanFull()
                            ->helperText('This template is rendered per device from the Device resource with placeholders replaced automatically.'),
                    ])
                    ->columns(1),
            ]);
    }
}
