<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions;

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages\CreateDeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages\EditDeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages\ListDeviceSchemaVersions;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages\ViewDeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers\DerivedParameterDefinitionsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers\ParameterDefinitionsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers\TopicsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Schemas\DeviceSchemaVersionForm;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Schemas\DeviceSchemaVersionInfolist;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Tables\DeviceSchemaVersionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceSchemaVersionResource extends Resource
{
    protected static ?string $model = DeviceSchemaVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('IoT Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Schema Versions');
    }

    public static function form(Schema $schema): Schema
    {
        return DeviceSchemaVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeviceSchemaVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeviceSchemaVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TopicsRelationManager::class,
            ParameterDefinitionsRelationManager::class,
            DerivedParameterDefinitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceSchemaVersions::route('/'),
            'create' => CreateDeviceSchemaVersion::route('/create'),
            'view' => ViewDeviceSchemaVersion::route('/{record}'),
            'edit' => EditDeviceSchemaVersion::route('/{record}/edit'),
        ];
    }
}
