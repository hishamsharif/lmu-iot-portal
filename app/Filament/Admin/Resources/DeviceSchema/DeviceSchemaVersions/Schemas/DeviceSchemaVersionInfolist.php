<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Schemas;

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\DeviceSchemaResource;
use Filament\Forms\Components\CodeEditor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DeviceSchemaVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version')
                    ->schema([
                        TextEntry::make('schema.name')
                            ->label('Device Schema')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->url(fn (DeviceSchemaVersion $record): ?string => $record->device_schema_id
                                ? DeviceSchemaResource::getUrl('view', ['record' => $record->device_schema_id])
                                : null),
                        TextEntry::make('version')
                            ->label('Version'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),
                    ])
                    ->columns(2),

                Section::make('Firmware')
                    ->schema([
                        TextEntry::make('firmware_filename')
                            ->label('File Name')
                            ->placeholder('—'),

                        CodeEditor::make('firmware_template')
                            ->label('Template')
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap'])
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
